<?php
/**
 * Lifecycle of a booking record.
 *
 * Two responsibilities: turning a paid order into a confirmed booking + block,
 * and tearing those down on cancellation/refund. Idempotent — running it twice
 * for the same order item is a no-op (the unique key on `(property_id, source,
 * external_uid)` prevents duplicate blocks).
 */

declare( strict_types=1 );

namespace IBB\Rentals\Services;

use IBB\Rentals\Domain\Block;
use IBB\Rentals\Domain\DateRange;
use IBB\Rentals\Repositories\AvailabilityRepository;
use IBB\Rentals\Repositories\BookingRepository;
use IBB\Rentals\Support\Hooks;
use IBB\Rentals\Support\Logger;

defined( 'ABSPATH' ) || exit;

final class BookingService {

	public function __construct(
		private AvailabilityRepository $blocks,
		private BookingRepository $bookings,
		private Logger $logger,
	) {}

	public function create_from_order_item( \WC_Order $order, \WC_Order_Item_Product $item ): ?int {
		$property_id = (int) $item->get_meta( '_ibb_property_id', true );
		if ( $property_id <= 0 ) {
			return null;
		}

		$existing = $this->bookings->find_by_order( $order->get_id() );
		foreach ( $existing as $row ) {
			if ( (int) $row['order_item_id'] === $item->get_id() ) {
				return (int) $row['id'];
			}
		}

		try {
			$range = DateRange::from_strings(
				(string) $item->get_meta( '_ibb_checkin', true ),
				(string) $item->get_meta( '_ibb_checkout', true )
			);
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Booking creation failed: invalid dates', [
				'order'   => $order->get_id(),
				'item'    => $item->get_id(),
				'error'   => $e->getMessage(),
			] );
			return null;
		}

		$external_uid = sprintf( 'order:%d:item:%d', $order->get_id(), $item->get_id() );

		$block = new Block(
			id:           null,
			property_id:  $property_id,
			range:        $range,
			source:       Block::SOURCE_DIRECT,
			external_uid: $external_uid,
			status:       Block::STATUS_CONFIRMED,
			order_id:     $order->get_id(),
			summary:      'Reserved',
		);
		$block_id = $this->blocks->upsert_by_uid( $block );

		$payment_mode = (string) $item->get_meta( '_ibb_payment_mode', true ) ?: 'full';
		$total        = (float) $item->get_meta( '_ibb_total', true );
		$deposit_due  = (float) $item->get_meta( '_ibb_deposit_due', true );
		$balance_due  = (float) $item->get_meta( '_ibb_balance_due', true );
		$balance_date = (string) $item->get_meta( '_ibb_balance_due_date', true );

		$booking_id = $this->bookings->insert( [
			'property_id'      => $property_id,
			'order_id'         => $order->get_id(),
			'order_item_id'    => $item->get_id(),
			'block_id'         => $block_id,
			'checkin'          => $range->checkin_string(),
			'checkout'         => $range->checkout_string(),
			'guests'           => (int) $item->get_meta( '_ibb_guests', true ),
			'guest_email'      => (string) $order->get_billing_email(),
			'guest_name'       => trim( (string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name() ),
			'total'            => $total,
			'deposit_paid'     => $payment_mode === 'deposit' ? $deposit_due : $total,
			'balance_due'      => $payment_mode === 'deposit' ? $balance_due : 0.0,
			'balance_due_date' => $balance_date !== '' ? $balance_date : null,
			'payment_method'   => (string) $order->get_payment_method(),
			'status'           => $payment_mode === 'deposit' ? BookingRepository::STATUS_BALANCE_PENDING : BookingRepository::STATUS_CONFIRMED,
			'quote_snapshot'   => (string) $item->get_meta( '_ibb_quote_snapshot', true ),
		] );

		do_action( Hooks::BOOKING_CREATED, $booking_id, $order, $item, $payment_mode );

		return $booking_id;
	}

	public function cancel_for_order( \WC_Order $order, string $reason = '' ): void {
		$rows = $this->bookings->find_by_order( $order->get_id() );
		foreach ( $rows as $row ) {
			$this->blocks->update_status( (int) $row['block_id'], Block::STATUS_CANCELLED );
			$this->bookings->update_status( (int) $row['id'], BookingRepository::STATUS_CANCELLED );

			if ( ! empty( $row['balance_charge_action_id'] ) && function_exists( 'as_unschedule_action' ) ) {
				as_unschedule_action( Hooks::AS_CHARGE_BALANCE, [ (int) $row['id'] ], Hooks::AS_GROUP );
				as_unschedule_action( Hooks::AS_SEND_PAYMENT_LINK, [ (int) $row['id'] ], Hooks::AS_GROUP );
			}

			do_action( Hooks::BOOKING_CANCELLED, (int) $row['id'], $order, $reason );
		}
	}

	public function refund_for_order( \WC_Order $order ): void {
		$rows = $this->bookings->find_by_order( $order->get_id() );
		foreach ( $rows as $row ) {
			$this->blocks->update_status( (int) $row['block_id'], Block::STATUS_CANCELLED );
			$this->bookings->update_status( (int) $row['id'], BookingRepository::STATUS_REFUNDED );
			do_action( Hooks::BOOKING_CANCELLED, (int) $row['id'], $order, 'refunded' );
		}
	}
}
