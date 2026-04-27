<?php
/**
 * Elementor widget: Property Availability Calendar.
 *
 * Read-only inline Flatpickr calendar showing blocked/available dates for a
 * property. Mirrors the `ibb/calendar` Gutenberg block and the `[ibb_calendar]`
 * shortcode — all three share the same render path via Shortcodes::render_calendar().
 *
 * Style tab controls calendar border, border-radius, and day-cell colour for
 * unavailable dates, all wired to Elementor Global kit tokens where sensible.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\Widgets;

use IBB\Rentals\Frontend\Shortcodes;
use IBB\Rentals\Integrations\Elementor\Module as ElementorModule;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	return;
}

class PropertyAvailabilityWidget extends \Elementor\Widget_Base {

	public function get_name(): string         { return 'ibb_property_availability'; }
	public function get_title(): string        { return __( 'Property Availability', 'ibb-rentals' ); }
	public function get_icon(): string         { return 'eicon-calendar'; }
	public function get_categories(): array    { return [ 'ibb-rentals' ]; }
	public function get_keywords(): array      { return [ 'calendar', 'availability', 'dates', 'rental', 'ibb' ]; }
	public function get_style_depends(): array  { return [ 'ibb-rentals-frontend', 'flatpickr' ]; }
	public function get_script_depends(): array { return [ 'ibb-rentals-booking' ]; }

	protected function register_controls(): void {

		// ── Property ──────────────────────────────────────────────────────
		$this->start_controls_section( 'section_property', [
			'label' => __( 'Property', 'ibb-rentals' ),
		] );

		$this->add_control( 'property_id', [
			'label'   => __( 'Property', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT2,
			'options' => ElementorModule::property_options(),
			'default' => 'current',
		] );

		$this->end_controls_section();

		// ── Display ───────────────────────────────────────────────────────
		$this->start_controls_section( 'section_display', [
			'label' => __( 'Display', 'ibb-rentals' ),
		] );

		$this->add_control( 'months', [
			'label'   => __( 'Months to show', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 2,
			'min'     => 1,
			'max'     => 3,
		] );

		$this->add_control( 'legend', [
			'label'        => __( 'Show legend', 'ibb-rentals' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'ibb-rentals' ),
			'label_off'    => __( 'Hide', 'ibb-rentals' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->end_controls_section();

		$this->register_style_controls();
	}

	private function register_style_controls(): void {

		// ── Calendar box ──────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_calendar', [
			'label' => __( 'Calendar', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
			'name'     => 'calendar_border',
			'selector' => '{{WRAPPER}} .ibb-calendar .flatpickr-calendar',
		] );

		$this->add_responsive_control( 'calendar_radius', [
			'label'      => __( 'Border radius', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-calendar .flatpickr-calendar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), [
			'name'     => 'calendar_shadow',
			'selector' => '{{WRAPPER}} .ibb-calendar .flatpickr-calendar',
		] );

		$this->add_control( 'calendar_bg', [
			'label'     => __( 'Calendar background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ibb-calendar .flatpickr-calendar' => 'background: {{VALUE}};' ],
		] );

		$this->end_controls_section();

		// ── Month header ─────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_header', [
			'label' => __( 'Month header', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'header_bg', [
			'label'     => __( 'Header background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY ],
			'selectors' => [ '{{WRAPPER}} .ibb-calendar .flatpickr-months' => 'background: {{VALUE}};' ],
		] );

		$this->add_control( 'header_color', [
			'label'     => __( 'Month/year text colour', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => [
				'{{WRAPPER}} .ibb-calendar .flatpickr-month'          => 'color: {{VALUE}};',
				'{{WRAPPER}} .ibb-calendar .flatpickr-current-month'  => 'color: {{VALUE}};',
				'{{WRAPPER}} .ibb-calendar .cur-month'                => 'color: {{VALUE}};',
				'{{WRAPPER}} .ibb-calendar .cur-year'                 => 'color: {{VALUE}};',
				'{{WRAPPER}} .ibb-calendar .flatpickr-prev-month svg' => 'fill: {{VALUE}};',
				'{{WRAPPER}} .ibb-calendar .flatpickr-next-month svg' => 'fill: {{VALUE}};',
			],
		] );

		$this->end_controls_section();

		// ── Days ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_days', [
			'label' => __( 'Day cells', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'day_available_color', [
			'label'     => __( 'Available — text colour', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ],
			'selectors' => [
				'{{WRAPPER}} .ibb-calendar .flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'day_unavailable_color', [
			'label'     => __( 'Unavailable — text colour', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .ibb-calendar .flatpickr-day.flatpickr-disabled,
				 {{WRAPPER}} .ibb-calendar .flatpickr-day.flatpickr-disabled:hover' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'day_unavailable_bg', [
			'label'     => __( 'Unavailable — background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .ibb-calendar .flatpickr-day.flatpickr-disabled,
				 {{WRAPPER}} .ibb-calendar .flatpickr-day.flatpickr-disabled:hover' => 'background: {{VALUE}};',
			],
		] );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'day_typography',
			'global'   => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT ],
			'selector' => '{{WRAPPER}} .ibb-calendar .flatpickr-day',
		] );

		$this->end_controls_section();

		// ── Legend ───────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_legend', [
			'label'     => __( 'Legend', 'ibb-rentals' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => [ 'legend' => 'yes' ],
		] );

		$this->add_control( 'legend_color', [
			'label'     => __( 'Text colour', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ],
			'selectors' => [ '{{WRAPPER}} .ibb-calendar__legend' => 'color: {{VALUE}};' ],
		] );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'legend_typography',
			'global'   => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT ],
			'selector' => '{{WRAPPER}} .ibb-calendar__legend',
		] );

		$this->add_responsive_control( 'legend_spacing', [
			'label'      => __( 'Spacing above legend', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
			'default'    => [ 'size' => 8, 'unit' => 'px' ],
			'selectors'  => [ '{{WRAPPER}} .ibb-calendar__legend' => 'margin-top: {{SIZE}}{{UNIT}};' ],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$property = ElementorModule::resolve_property_for_widget( (string) ( $settings['property_id'] ?? 'current' ) );

		if ( ! $property ) {
			$this->editor_placeholder( 'No property resolved. Select one in the widget panel, or place this widget on a single property page.' );
			return;
		}

		$shortcodes = new Shortcodes();
		echo $shortcodes->render_calendar( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'id'     => $property->id,
			'months' => (int) ( $settings['months'] ?? 2 ),
			'legend' => (string) ( $settings['legend'] ?? 'yes' ),
		] );
	}

	private function editor_placeholder( string $message ): void {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}
		$is_editor  = \Elementor\Plugin::$instance->editor  && \Elementor\Plugin::$instance->editor->is_edit_mode();
		$is_preview = \Elementor\Plugin::$instance->preview && \Elementor\Plugin::$instance->preview->is_preview_mode();
		if ( ! $is_editor && ! $is_preview ) {
			return;
		}
		echo '<div class="ibb-property-carousel-placeholder">' . esc_html( $message ) . '</div>';
	}
}
