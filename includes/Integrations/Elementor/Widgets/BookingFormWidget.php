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

		$this->register_style_controls();
	}

	private function register_style_controls(): void {
		// ---------- Box (container) ----------
		$this->start_controls_section( 'section_style_box', [
			'label' => __( 'Box', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'box_bg', [
			'label'     => __( 'Background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ibb-booking' => 'background: {{VALUE}};' ],
		] );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
			'name'     => 'box_border',
			'selector' => '{{WRAPPER}} .ibb-booking',
		] );

		$this->add_control( 'box_radius', [
			'label'      => __( 'Border radius', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-booking' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'box_padding', [
			'label'      => __( 'Padding', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-booking' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'box_max_width', [
			'label'      => __( 'Max width', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px', '%' ],
			'range'      => [ 'px' => [ 'min' => 200, 'max' => 800 ], '%' => [ 'min' => 30, 'max' => 100 ] ],
			'selectors'  => [ '{{WRAPPER}} .ibb-booking' => 'max-width: {{SIZE}}{{UNIT}};' ],
		] );

		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), [
			'name'     => 'box_shadow',
			'selector' => '{{WRAPPER}} .ibb-booking',
		] );

		$this->end_controls_section();

		// ---------- Title ----------
		$this->start_controls_section( 'section_style_title', [
			'label' => __( 'Title', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'title_color', [
			'label'     => __( 'Color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY ],
			'selectors' => [ '{{WRAPPER}} .ibb-booking__title' => 'color: {{VALUE}};' ],
		] );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'title_typography',
			'global'   => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY ],
			'selector' => '{{WRAPPER}} .ibb-booking__title',
		] );

		$this->end_controls_section();

		// ---------- Fields (labels + inputs) ----------
		$this->start_controls_section( 'section_style_fields', [
			'label' => __( 'Fields', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'label_color', [
			'label'     => __( 'Label color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ],
			'selectors' => [ '{{WRAPPER}} .ibb-booking__field label' => 'color: {{VALUE}};' ],
		] );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'label_typography',
			'global'   => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT ],
			'selector' => '{{WRAPPER}} .ibb-booking__field label',
		] );

		$this->add_control( 'input_color', [
			'label'     => __( 'Input text color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .ibb-booking__field > input,
				 {{WRAPPER}} .ibb-booking__stepper input' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'input_bg', [
			'label'     => __( 'Input background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .ibb-booking__field > input,
				 {{WRAPPER}} .ibb-booking__stepper input' => 'background: {{VALUE}};',
			],
		] );

		$this->add_control( 'input_border_color', [
			'label'     => __( 'Input border color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .ibb-booking__field > input,
				 {{WRAPPER}} .ibb-booking__stepper' => 'border-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'input_radius', [
			'label'      => __( 'Input border radius', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-booking__field > input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .ibb-booking__stepper'        => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_control( 'stepper_btn_bg', [
			'label'     => __( 'Stepper button background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ibb-booking__step' => 'background: {{VALUE}};' ],
		] );

		$this->add_control( 'stepper_btn_color', [
			'label'     => __( 'Stepper button color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ibb-booking__step' => 'color: {{VALUE}};' ],
		] );

		$this->end_controls_section();

		// ---------- Quote panel ----------
		$this->start_controls_section( 'section_style_quote', [
			'label' => __( 'Quote panel', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'quote_bg', [
			'label'     => __( 'Background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ibb-booking__quote' => 'background: {{VALUE}};' ],
		] );

		$this->add_control( 'quote_color', [
			'label'     => __( 'Text color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ],
			'selectors' => [ '{{WRAPPER}} .ibb-booking__quote' => 'color: {{VALUE}};' ],
		] );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'quote_typography',
			'global'   => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT ],
			'selector' => '{{WRAPPER}} .ibb-booking__quote',
		] );

		$this->add_control( 'quote_total_color', [
			'label'     => __( 'Total color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY ],
			'selectors' => [ '{{WRAPPER}} .ibb-booking__quote-total' => 'color: {{VALUE}}; border-top-color: {{VALUE}};' ],
		] );

		$this->end_controls_section();

		// ---------- Submit button ----------
		$this->start_controls_section( 'section_style_button', [
			'label' => __( 'Book button', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->start_controls_tabs( 'button_state_tabs' );

		$this->start_controls_tab( 'button_normal_tab', [ 'label' => __( 'Normal', 'ibb-rentals' ) ] );

		$this->add_control( 'button_color', [
			'label'     => __( 'Text color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ibb-booking__submit' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'button_bg', [
			'label'     => __( 'Background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT ],
			'selectors' => [ '{{WRAPPER}} .ibb-booking__submit' => 'background: {{VALUE}};' ],
		] );

		$this->end_controls_tab();

		$this->start_controls_tab( 'button_hover_tab', [ 'label' => __( 'Hover', 'ibb-rentals' ) ] );

		$this->add_control( 'button_color_hover', [
			'label'     => __( 'Text color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ibb-booking__submit:hover:not([disabled])' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'button_bg_hover', [
			'label'     => __( 'Background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ibb-booking__submit:hover:not([disabled])' => 'background: {{VALUE}};' ],
		] );

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'button_typography',
			'global'   => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_ACCENT ],
			'selector' => '{{WRAPPER}} .ibb-booking__submit',
		] );

		$this->add_control( 'button_radius', [
			'label'      => __( 'Border radius', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-booking__submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'button_padding', [
			'label'      => __( 'Padding', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-booking__submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
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
