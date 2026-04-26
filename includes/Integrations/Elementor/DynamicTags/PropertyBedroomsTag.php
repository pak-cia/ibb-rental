<?php
/**
 * Elementor dynamic tag — IBB > Bedrooms.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

class PropertyBedroomsTag extends AbstractPropertyFieldTag {
	public function get_name(): string  { return 'ibb-property-bedrooms'; }
	public function get_title(): string { return __( 'Bedrooms', 'ibb-rentals' ); }

	public function field_value( Property $property ): string {
		return (string) $property->bedrooms();
	}
}
