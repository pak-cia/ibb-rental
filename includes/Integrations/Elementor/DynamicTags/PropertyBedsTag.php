<?php
/**
 * Elementor dynamic tag — IBB > Beds.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

class PropertyBedsTag extends AbstractPropertyFieldTag {
	public function get_name(): string  { return 'ibb-property-beds'; }
	public function get_title(): string { return __( 'Beds', 'ibb-rentals' ); }

	public function field_value( Property $property ): string {
		return (string) $property->beds();
	}
}
