<?php
/**
 * Elementor dynamic tag — IBB > Property Title.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

class PropertyTitleTag extends AbstractPropertyFieldTag {
	public function get_name(): string  { return 'ibb-property-title'; }
	public function get_title(): string { return __( 'Property Title', 'ibb-rentals' ); }

	public function field_value( Property $property ): string {
		return $property->title();
	}
}
