<?php
/**
 * Custom WooCommerce product type `ibb_booking`.
 *
 * Each property post is mirrored 1:1 to a hidden product of this type. The
 * product is what WooCommerce sees in cart/orders/reports; the property post
 * is what guests and admins see in the UI. The product itself is never
 * "purchasable" without cart-item-data carrying valid checkin/checkout dates,
 * so the regular Add-to-Cart button on the product is suppressed — guests
 * are forced through the date-picker on the property page.
 *
 * The actual `WC_Product_IBB_Booking` class lives in WC_Product_IBB_Booking.php
 * (global namespace) — PHP doesn't allow declaring a non-namespaced class
 * inline from a namespaced file.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Woo;

defined( 'ABSPATH' ) || exit;

final class BookingProductType {

	public const TYPE = 'ibb_booking';

	public function register(): void {
		// Force-load the global-namespace product class now so WC's
		// woocommerce_product_class filter can hand back its name.
		if ( class_exists( '\\WC_Product' ) ) {
			require_once __DIR__ . '/WC_Product_IBB_Booking.php';
		}

		add_filter( 'product_type_selector', [ $this, 'add_type_to_selector' ] );
		add_filter( 'woocommerce_product_class', [ $this, 'map_class' ], 10, 2 );
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'filter_data_tabs' ] );
	}

	/** @param array<string, string> $types */
	public function add_type_to_selector( array $types ): array {
		$types[ self::TYPE ] = __( 'Vacation rental', 'ibb-rentals' );
		return $types;
	}

	public function map_class( string $classname, string $type ): string {
		if ( $type === self::TYPE ) {
			return 'WC_Product_IBB_Booking';
		}
		return $classname;
	}

	/**
	 * Hide product-data tabs that don't apply to bookings (Inventory shipping, etc.)
	 * when the admin loads a vacation-rental product directly.
	 *
	 * @param array<string, array<string, mixed>> $tabs
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_data_tabs( array $tabs ): array {
		$hide = [ 'shipping', 'attribute', 'variations', 'linked_product' ];
		foreach ( $hide as $tab_id ) {
			if ( isset( $tabs[ $tab_id ]['class'] ) && is_array( $tabs[ $tab_id ]['class'] ) ) {
				$tabs[ $tab_id ]['class'][] = 'hide_if_ibb_booking';
			}
		}
		return $tabs;
	}
}
