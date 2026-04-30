<?php
/**
 * Listens to WC order status transitions and drives booking lifecycle.
 *
 * `processing` and `completed` create the booking + block; `cancelled`,
 * `refunded`, and `failed` tear them down. HPOS-safe: every order access
 * goes through `wc_get_order()` and meta read/writes through the order/item
 * objects — never `get_post_meta` / `update_post_meta` on order IDs.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Woo;

use IBB\Rentals\Services\BookingService;

defined( 'ABSPATH' ) || exit;

final class OrderObserver {

	public function __construct(
		private BookingService $bookings,
	) {}

	public function register(): void {
		add_action( 'woocommerce_order_status_processing', [ $this, 'on_paid' ], 10, 2 );
		add_action( 'woocommerce_order_status_completed',  [ $this, 'on_paid' ], 10, 2 );

		add_action( 'woocommerce_order_status_cancelled', [ $this, 'on_cancelled' ], 10, 2 );
		add_action( 'woocommerce_order_status_failed',    [ $this, 'on_cancelled' ], 10, 2 );
		add_action( 'woocommerce_order_refunded',         [ $this, 'on_refunded' ], 10, 2 );

		// Suppress WC's generic customer order emails for IBB bookings.
		// Our BookingConfirmationEmail (triggered on ibb-rentals/booking/created)
		// is the authoritative guest notification — the WC ones are confusing and redundant.
		add_filter( 'woocommerce_email_enabled_customer_processing_order', [ $this, 'suppress_for_ibb_order' ], 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_completed_order',  [ $this, 'suppress_for_ibb_order' ], 10, 2 );
	}

	/**
	 * @param mixed          $enabled
	 * @param \WC_Order|null $order   WC's settings/preview path calls `is_enabled()` without an
	 *                                order context (null). Treat as a non-IBB order — leave the
	 *                                original $enabled alone so the WC Settings → Emails screen renders.
	 */
	public function suppress_for_ibb_order( $enabled, $order = null ): bool {
		if ( ! $enabled ) {
			return false;
		}
		if ( ! $order instanceof \WC_Order ) {
			return (bool) $enabled;
		}
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product && $item->get_meta( '_ibb_property_id', true ) ) {
				return false;
			}
		}
		return (bool) $enabled;
	}

	public function on_paid( int $order_id, \WC_Order $order ): void {
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			if ( ! $item->get_meta( '_ibb_property_id', true ) ) {
				continue;
			}
			$this->bookings->create_from_order_item( $order, $item );
		}
	}

	public function on_cancelled( int $order_id, \WC_Order $order ): void {
		$this->bookings->cancel_for_order( $order, 'order_' . $order->get_status() );
	}

	public function on_refunded( int $order_id, int $refund_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order instanceof \WC_Order ) {
			$this->bookings->refund_for_order( $order );
		}
	}
}
