<?php
/**
 * Elementor dynamic tag — IBB > Base Rate (formatted with WC currency).
 *
 * Returns the property's base nightly rate formatted via `wc_price()`. Falls
 * back to a plain numeric format if WC isn't available so the tag never
 * fatals.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

class PropertyBaseRateTag extends AbstractPropertyFieldTag {
	public function get_name(): string  { return 'ibb-property-base-rate'; }
	public function get_title(): string { return __( 'Base Rate (per night)', 'ibb-rentals' ); }

	public function field_value( Property $property ): string {
		$rate = $property->base_rate();
		if ( $rate <= 0 ) {
			return '';
		}
		if ( function_exists( 'wc_price' ) ) {
			return (string) wc_price( $rate );
		}
		return number_format( $rate, 2 );
	}
}
