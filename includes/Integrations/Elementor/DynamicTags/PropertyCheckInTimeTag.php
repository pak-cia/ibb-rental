<?php
/**
 * Elementor dynamic tag — IBB > Check-in time.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

class PropertyCheckInTimeTag extends AbstractPropertyFieldTag {
	public function get_name(): string  { return 'ibb-property-check-in-time'; }
	public function get_title(): string { return __( 'Check-in Time', 'ibb-rentals' ); }

	public function field_value( Property $property ): string {
		return $property->check_in_time();
	}
}
