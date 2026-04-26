<?php
/**
 * Global-namespace class declaration for the custom WooCommerce product type.
 *
 * Lives in the global namespace because WC's `woocommerce_product_class`
 * filter expects a top-level class name. The autoloader still locates this
 * file via the `IBB\Rentals\Woo\WC_Product_IBB_Booking` PSR-4 path, but the
 * actual class declared inside is global — declaring a non-namespaced class
 * inline from a namespaced file is a PHP parse error.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Product' ) || class_exists( 'WC_Product_IBB_Booking' ) ) {
	return;
}

class WC_Product_IBB_Booking extends WC_Product {

	public function get_type() {
		return 'ibb_booking';
	}

	public function is_virtual( $context = 'view' ) {
		return true;
	}

	public function needs_shipping() {
		return false;
	}

	public function is_sold_individually() {
		// Returning false avoids WC's hard-coded "cannot add another" exception
		// when the customer re-adds the same booking. The plugin's CartHandler
		// instead clamps quantity to 1 via filters and silently merges duplicate
		// adds — see CartHandler::clamp_quantity / ::reset_merged_quantity.
		return false;
	}

	public function is_purchasable() {
		return $this->exists() && $this->get_status() === 'publish';
	}

	public function add_to_cart_url() {
		$property_id = (int) $this->get_meta( '_ibb_property_id', true );
		if ( $property_id ) {
			$permalink = get_permalink( $property_id );
			if ( $permalink ) {
				return $permalink;
			}
		}
		return parent::add_to_cart_url();
	}

	public function add_to_cart_text() {
		return __( 'Check availability', 'ibb-rentals' );
	}

	public function single_add_to_cart_text() {
		return __( 'Check availability', 'ibb-rentals' );
	}

	public function get_price_html( $deprecated = '' ) {
		$base = (float) $this->get_meta( '_ibb_base_rate', true );
		if ( $base <= 0 ) {
			return '';
		}
		return sprintf(
			/* translators: %s: nightly price */
			esc_html__( 'from %s / night', 'ibb-rentals' ),
			wc_price( $base )
		);
	}
}
