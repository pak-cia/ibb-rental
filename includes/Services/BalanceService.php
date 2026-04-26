<?php
/**
 * Schedules and executes the second-instalment charge for deposit-mode bookings.
 *
 * `schedule_for_booking()` runs at booking-creation time and decides whether
 * the gateway supports off-session reuse. The two AS jobs (ChargeBalanceJob,
 * SendPaymentLinkJob) call back into `charge()` and `send_link()` respectively
 * — kept here so the ledger update logic is shared.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Services;

use IBB\Rentals\Repositories\BookingRepository;
use IBB\Rentals\Support\Hooks;
use IBB\Rentals\Support\Logger;
use IBB\Rentals\Woo\GatewayCapabilities;

defined( 'ABSPATH' ) || exit;

final class BalanceService {

	public function __construct(
		private BookingRepository $bookings,
		private GatewayCapabilities $gateways,
		private Logger $logger,
	) {}

	/**
	 * Inspect a freshly-created booking and schedule whichever balance path applies.
	 *
	 * @return string  the chosen path ('auto_charge' | 'payment_link' | 'none')
	 */
	public function schedule_for_booking( int $booking_id ): string {
		$booking = $this->bookings->find_by_id( $booking_id );
		if ( ! $booking || empty( $booking['balance_due_date'] ) || (float) $booking['balance_due'] <= 0 ) {
			return 'none';
		}

		$path = $this->gateways->path_for_gateway( (string) $booking['payment_method'] );
		$ts   = $this->due_timestamp( (string) $booking['balance_due_date'] );

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->logger->warning( 'Action Scheduler unavailable; balance not scheduled.', [ 'booking' => $booking_id ] );
			return 'none';
		}

		if ( $path === GatewayCapabilities::PATH_AUTO_CHARGE ) {
			$action_id = as_schedule_single_action( $ts, Hooks::AS_CHARGE_BALANCE, [ $booking_id ], Hooks::AS_GROUP );
			$this->bookings->update( $booking_id, [ 'balance_charge_action_id' => $action_id ] );
			return GatewayCapabilities::PATH_AUTO_CHARGE;
		}

		// Payment-link path: send 3 days before, then again 1 day before.
		as_schedule_single_action( $ts - 3 * DAY_IN_SECONDS, Hooks::AS_SEND_PAYMENT_LINK, [ $booking_id, 'first' ], Hooks::AS_GROUP );
		as_schedule_single_action( $ts - 1 * DAY_IN_SECONDS, Hooks::AS_SEND_PAYMENT_LINK, [ $booking_id, 'reminder' ], Hooks::AS_GROUP );

		return GatewayCapabilities::PATH_PAYMENT_LINK;
	}

	public function charge( int $booking_id ): void {
		$booking = $this->bookings->find_by_id( $booking_id );
		if ( ! $booking || (float) $booking['balance_due'] <= 0 ) {
			return;
		}
		if ( $booking['status'] !== BookingRepository::STATUS_BALANCE_PENDING ) {
			return;
		}

		$lock_key = 'ibb_balance_lock_' . $booking_id;
		if ( ! add_option( $lock_key, time(), '', false ) ) {
			return; // another worker holds the lock
		}

		try {
			$order = wc_get_order( (int) $booking['order_id'] );
			if ( ! $order instanceof \WC_Order ) {
				throw new \RuntimeException( 'Order not found' );
			}

			$token = $this->gateways->find_reusable_token(
				(int) $order->get_customer_id(),
				(string) $booking['payment_method']
			);
			if ( ! $token ) {
				throw new \RuntimeException( 'No reusable payment token on file' );
			}

			$balance_order = $this->build_balance_order( $order, (float) $booking['balance_due'] );

			$gateway = WC()->payment_gateways->payment_gateways()[ (string) $booking['payment_method'] ] ?? null;
			if ( ! $gateway ) {
				throw new \RuntimeException( 'Gateway not active' );
			}

			$balance_order->add_payment_token( $token );
			$result = $gateway->process_payment( $balance_order->get_id() );

			if ( ! is_array( $result ) || ( $result['result'] ?? '' ) !== 'success' ) {
				throw new \RuntimeException( 'Gateway returned non-success' );
			}

			$this->bookings->update( $booking_id, [
				'status'       => BookingRepository::STATUS_CONFIRMED,
				'deposit_paid' => (float) $booking['deposit_paid'] + (float) $booking['balance_due'],
				'balance_due'  => 0,
			] );

			do_action( Hooks::BALANCE_CHARGED, $booking_id, $balance_order );

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Balance charge failed', [
				'booking' => $booking_id,
				'error'   => $e->getMessage(),
			] );
			$retries = (int) get_post_meta( (int) $booking['order_id'], '_ibb_balance_retries', true );
			if ( $retries < 3 && function_exists( 'as_schedule_single_action' ) ) {
				update_post_meta( (int) $booking['order_id'], '_ibb_balance_retries', $retries + 1 );
				as_schedule_single_action(
					time() + DAY_IN_SECONDS,
					Hooks::AS_CHARGE_BALANCE,
					[ $booking_id ],
					Hooks::AS_GROUP
				);
			} else {
				// Out of retries — fall back to payment-link email.
				as_schedule_single_action( time() + 60, Hooks::AS_SEND_PAYMENT_LINK, [ $booking_id, 'fallback' ], Hooks::AS_GROUP );
			}
			do_action( Hooks::BALANCE_FAILED, $booking_id, $e->getMessage() );
		} finally {
			delete_option( $lock_key );
		}
	}

	public function send_link( int $booking_id, string $kind = 'first' ): void {
		$booking = $this->bookings->find_by_id( $booking_id );
		if ( ! $booking || (float) $booking['balance_due'] <= 0 ) {
			return;
		}
		if ( $booking['status'] !== BookingRepository::STATUS_BALANCE_PENDING ) {
			return;
		}

		$order = wc_get_order( (int) $booking['order_id'] );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$balance_order = $this->build_balance_order( $order, (float) $booking['balance_due'] );
		$pay_url       = $balance_order->get_checkout_payment_url();

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Balance payment for your booking', 'ibb-rentals' ),
			get_bloginfo( 'name' )
		);
		$body = sprintf(
			/* translators: 1: guest name, 2: balance, 3: date, 4: pay URL */
			__( "Hi %1\$s,\n\nThe balance of %2\$s for your stay (check-in %3\$s) is due. You can complete payment securely here:\n\n%4\$s\n\nThanks,\n%5\$s", 'ibb-rentals' ),
			(string) $order->get_billing_first_name(),
			wc_price( (float) $booking['balance_due'] ),
			(string) $booking['checkin'],
			$pay_url,
			get_bloginfo( 'name' )
		);

		wp_mail( (string) $booking['guest_email'], $subject, wp_strip_all_tags( $body ) );
		$this->logger->info( 'Balance payment link sent', [ 'booking' => $booking_id, 'kind' => $kind ] );
	}

	private function due_timestamp( string $date ): int {
		$tz   = wp_timezone();
		$dt   = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date . ' 09:00:00', $tz );
		return $dt ? $dt->getTimestamp() : ( time() + DAY_IN_SECONDS );
	}

	private function build_balance_order( \WC_Order $original, float $balance_due ): \WC_Order {
		$existing_id = (int) $original->get_meta( '_ibb_balance_order_id', true );
		if ( $existing_id ) {
			$existing = wc_get_order( $existing_id );
			if ( $existing instanceof \WC_Order ) {
				return $existing;
			}
		}

		$order = wc_create_order( [
			'customer_id' => $original->get_customer_id(),
			'status'      => 'pending',
		] );

		$item = new \WC_Order_Item_Fee();
		$item->set_name( __( 'Booking balance', 'ibb-rentals' ) );
		$item->set_amount( $balance_due );
		$item->set_total( $balance_due );
		$order->add_item( $item );

		$order->set_address( $original->get_address( 'billing' ), 'billing' );
		$order->set_payment_method( (string) $original->get_payment_method() );
		$order->update_meta_data( '_ibb_parent_order_id', $original->get_id() );
		$order->calculate_totals();
		$order->save();

		$original->update_meta_data( '_ibb_balance_order_id', $order->get_id() );
		$original->save();

		return $order;
	}
}
