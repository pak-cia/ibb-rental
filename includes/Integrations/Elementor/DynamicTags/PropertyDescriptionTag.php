<?php
/**
 * Elementor dynamic tag — IBB > Property Description.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

class PropertyDescriptionTag extends AbstractPropertyFieldTag {
	public function get_name(): string  { return 'ibb-property-description'; }
	public function get_title(): string { return __( 'Property Description', 'ibb-rentals' ); }

	public function field_value( Property $property ): string {
		return apply_filters( 'the_content', get_post_field( 'post_content', $property->id ) );
	}
}
