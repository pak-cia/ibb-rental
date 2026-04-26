<?php
/**
 * Elementor widget: Booking Form.
 *
 * Renders the IBB Rentals date-picker + quote + add-to-cart booking widget
 * for a chosen property. Mirrors the `ibb/booking-form` Gutenberg block,
 * delegating to the same `[ibb_booking_form]` shortcode for the markup so
 * frontend behaviour stays identical across block editor and Elementor.
 *
 * `require_once`d at runtime by Module::register_widgets() — never via
 * PSR-4 autoload, because Widget_Base only exists once Elementor is loaded.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\Widgets;

use IBB\Rentals\Frontend\Shortcodes;
use IBB\Rentals\Integrations\Elementor\Module as ElementorModule;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	return;
}

class BookingFormWidget extends \Elementor\Widget_Base {

	public function get_name(): string         { return 'ibb_booking_form'; }
	public function get_title(): string        { return __( 'Booking Form', 'ibb-rentals' ); }
	public function get_icon(): string         { return 'eicon-form-horizontal'; }
	public function get_categories(): array    { return [ 'ibb-rentals' ]; }
	public function get_keywords(): array      { return [ 'booking', 'reserve', 'rental', 'date picker', 'ibb' ]; }
	public function get_style_depends(): array  { return [ 'ibb-rentals-frontend', 'flatpickr' ]; }
	public function get_script_depends(): array { return [ 'ibb-rentals-booking' ]; }

	protected function register_controls(): void {
		$this->start_controls_section( 'section_source', [
			'label' => __( 'Property', 'ibb-rentals' ),
		] );

		$this->add_control( 'property_id', [
			'label'       => __( 'Property', 'ibb-rentals' ),
			'type'        => \Elementor\Controls_Manager::SELECT2,
			'options'     => ElementorModule::property_options(),
			'default'     => 'current',
			'description' => __( 'Pick "Current page" to auto-resolve from the post on a single-property template, or a specific property by name.', 'ibb-rentals' ),
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$property = ElementorModule::resolve_property_for_widget( (string) ( $settings['property_id'] ?? 'current' ) );
		if ( ! $property ) {
			return;
		}

		$shortcodes = new Shortcodes();
		echo $shortcodes->render_booking_form( [ 'id' => $property->id ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
