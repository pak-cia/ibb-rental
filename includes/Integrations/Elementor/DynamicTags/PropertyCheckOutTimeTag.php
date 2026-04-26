<?php
/**
 * Elementor dynamic tag — IBB > Check-out time.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

class PropertyCheckOutTimeTag extends AbstractPropertyFieldTag {
	public function get_name(): string  { return 'ibb-property-check-out-time'; }
	public function get_title(): string { return __( 'Check-out Time', 'ibb-rentals' ); }

	public function field_value( Property $property ): string {
		return $property->check_out_time();
	}
}
