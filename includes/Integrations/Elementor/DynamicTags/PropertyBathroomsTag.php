<?php
/**
 * Elementor dynamic tag — IBB > Bathrooms.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

class PropertyBathroomsTag extends AbstractPropertyFieldTag {
	public function get_name(): string  { return 'ibb-property-bathrooms'; }
	public function get_title(): string { return __( 'Bathrooms', 'ibb-rentals' ); }

	public function field_value( Property $property ): string {
		$n = $property->bathrooms();
		return rtrim( rtrim( number_format( $n, 1, '.', '' ), '0' ), '.' );
	}
}
