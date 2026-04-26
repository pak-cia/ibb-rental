<?php
/**
 * Elementor dynamic tag — IBB > Property URL.
 *
 * Returns the permalink to a property's single page. Bindable to any URL
 * control with `dynamic.active = true` (Button widget's "Link", an Image
 * widget's link, anything that takes a URL).
 *
 * Extends Data_Tag (not Tag) because URL controls expect the value as a
 * return-from-`get_value()` string, not echoed markup.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Integrations\Elementor\Module as ElementorModule;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Data_Tag' ) ) {
	return;
}

class PropertyUrlTag extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name(): string  { return 'ibb-property-url'; }
	public function get_title(): string { return __( 'Property URL', 'ibb-rentals' ); }
	public function get_group(): string { return 'ibb-rentals'; }

	public function get_categories(): array {
		if ( defined( '\\Elementor\\Modules\\DynamicTags\\Module::URL_CATEGORY' ) ) {
			return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ];
		}
		return [ 'url' ];
	}

	protected function register_controls(): void {
		$this->add_control( 'property_id', [
			'label'   => __( 'Property', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT2,
			'options' => ElementorModule::property_options(),
			'default' => 'current',
		] );
	}

	/**
	 * @param array<string,mixed> $options
	 */
	public function get_value( array $options = [] ): string {
		$property = ElementorModule::resolve_property_for_widget(
			(string) $this->get_settings( 'property_id' )
		);
		if ( ! $property ) {
			return '';
		}
		return (string) get_permalink( $property->id );
	}
}
