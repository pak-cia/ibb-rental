<?php
/**
 * Elementor widget: Property Details.
 *
 * Property metadata in a chosen layout (grid / compact / list). Mirrors the
 * `ibb/property-details` Gutenberg block. Delegates to the
 * `[ibb_property_details]` shortcode for rendering.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\Widgets;

use IBB\Rentals\Frontend\Shortcodes;
use IBB\Rentals\Integrations\Elementor\Module as ElementorModule;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	return;
}

class PropertyDetailsWidget extends \Elementor\Widget_Base {

	public function get_name(): string         { return 'ibb_property_details'; }
	public function get_title(): string        { return __( 'Property Details', 'ibb-rentals' ); }
	public function get_icon(): string         { return 'eicon-info-box'; }
	public function get_categories(): array    { return [ 'ibb-rentals' ]; }
	public function get_keywords(): array      { return [ 'property', 'details', 'specs', 'amenities', 'ibb' ]; }
	public function get_style_depends(): array { return [ 'ibb-rentals-frontend' ]; }

	/** @return array<string, string> */
	private function field_options(): array {
		return [
			'guests'         => __( 'Guests', 'ibb-rentals' ),
			'bedrooms'       => __( 'Bedrooms', 'ibb-rentals' ),
			'bathrooms'      => __( 'Bathrooms', 'ibb-rentals' ),
			'beds'           => __( 'Beds', 'ibb-rentals' ),
			'check_in_time'  => __( 'Check-in time', 'ibb-rentals' ),
			'check_out_time' => __( 'Check-out time', 'ibb-rentals' ),
			'address'        => __( 'Address', 'ibb-rentals' ),
			'amenities'      => __( 'Amenities', 'ibb-rentals' ),
			'location'       => __( 'Location', 'ibb-rentals' ),
			'property_type'  => __( 'Property type', 'ibb-rentals' ),
		];
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_source', [
			'label' => __( 'Property', 'ibb-rentals' ),
		] );

		$this->add_control( 'property_id', [
			'label'   => __( 'Property', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT2,
			'options' => ElementorModule::property_options(),
			'default' => 'current',
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'section_fields', [
			'label' => __( 'Fields', 'ibb-rentals' ),
		] );

		$this->add_control( 'fields_intro', [
			'type'    => \Elementor\Controls_Manager::RAW_HTML,
			'raw'     => __( 'Toggle which property fields to show. Empty fields are skipped automatically (e.g. address won\'t render if the property has no address).', 'ibb-rentals' ),
			'content_classes' => 'elementor-descriptor',
		] );

		// One switcher per field — mirrors the block's checkbox list.
		$default_on = [ 'guests', 'bedrooms', 'bathrooms', 'beds' ];
		foreach ( $this->field_options() as $key => $label ) {
			$this->add_control( 'show_' . $key, [
				'label'        => $label,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => in_array( $key, $default_on, true ) ? 'yes' : '',
				'label_on'     => __( 'Show', 'ibb-rentals' ),
				'label_off'    => __( 'Hide', 'ibb-rentals' ),
				'return_value' => 'yes',
			] );
		}

		$this->end_controls_section();

		$this->start_controls_section( 'section_layout', [
			'label' => __( 'Layout', 'ibb-rentals' ),
		] );

		$this->add_control( 'layout', [
			'label'   => __( 'Layout', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'grid',
			'options' => [
				'grid'    => __( 'Grid (large value + label)', 'ibb-rentals' ),
				'compact' => __( 'Compact (one line)', 'ibb-rentals' ),
				'list'    => __( 'List (key/value pairs)', 'ibb-rentals' ),
			],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings    = $this->get_settings_for_display();
		$property_id = (string) ( $settings['property_id'] ?? 'current' );
		if ( $property_id === 'current' || $property_id === '' ) {
			$property_id = (string) get_the_ID();
		}

		$fields = [];
		foreach ( array_keys( $this->field_options() ) as $key ) {
			if ( ( $settings[ 'show_' . $key ] ?? '' ) === 'yes' ) {
				$fields[] = $key;
			}
		}

		$shortcodes = new Shortcodes();
		echo $shortcodes->render_property_details( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'id'     => (int) $property_id,
			'fields' => implode( ',', $fields ),
			'layout' => (string) ( $settings['layout'] ?? 'grid' ),
		] );
	}
}
