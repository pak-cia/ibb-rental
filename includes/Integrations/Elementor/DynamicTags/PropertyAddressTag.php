<?php
/**
 * Elementor dynamic tag — IBB > Property Address.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

class PropertyAddressTag extends AbstractPropertyFieldTag {
	public function get_name(): string  { return 'ibb-property-address'; }
	public function get_title(): string { return __( 'Property Address', 'ibb-rentals' ); }

	public function field_value( Property $property ): string {
		return trim( (string) $property->meta( '_ibb_address', '' ) );
	}
}
