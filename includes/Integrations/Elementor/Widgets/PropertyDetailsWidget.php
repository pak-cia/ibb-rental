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
	public function get_style_depends(): array {
		// `elementor-icons` ships the eicon-* glyph font we default to. Without
		// this, the front-end renders the `<i class="eicon-person">` tag fine
		// but the glyph itself is empty (no font face loaded).
		return [ 'ibb-rentals-frontend', 'elementor-icons' ];
	}

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
			'raw'     => __( 'Toggle which property fields to show, and pick an icon for each. Empty fields are skipped automatically (e.g. address won\'t render if the property has no address).', 'ibb-rentals' ),
			'content_classes' => 'elementor-descriptor',
		] );

		// Sensible default icons per field. Editors override via the Icons
		// control on each field. Defaults use Elementor's bundled `eicon-*`
		// set so they render even when no Font Awesome / SVG kit is loaded.
		$default_icons = [
			'guests'         => 'eicon-person',
			'bedrooms'       => 'eicon-time-line',
			'bathrooms'      => 'eicon-tools',
			'beds'           => 'eicon-time-line',
			'check_in_time'  => 'eicon-clock-o',
			'check_out_time' => 'eicon-clock-o',
			'address'        => 'eicon-map-pin',
			'amenities'      => 'eicon-favorite',
			'location'       => 'eicon-map-pin',
			'property_type'  => 'eicon-home-heart',
		];

		// One switcher + one icon control per field — mirrors the block's
		// checkbox list, with the icon control nested under the toggle.
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
			$this->add_control( 'icon_' . $key, [
				'label'            => sprintf( /* translators: %s: field label */ __( '%s icon', 'ibb-rentals' ), $label ),
				'type'             => \Elementor\Controls_Manager::ICONS,
				'default'          => [
					'value'   => $default_icons[ $key ] ?? '',
					'library' => 'eicons',
				],
				'condition'        => [ 'show_' . $key => 'yes' ],
				'skin'             => 'inline',
				'label_block'      => false,
				'exclude_inline_options' => [ 'svg' ],
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

		$this->register_style_controls();
	}

	private function register_style_controls(): void {
		// ---------- Grid items (grid layout only) ----------
		$this->start_controls_section( 'section_style_grid', [
			'label'     => __( 'Grid items', 'ibb-rentals' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => [ 'layout' => 'grid' ],
		] );

		$this->add_responsive_control( 'grid_min_col_width', [
			'label'      => __( 'Min column width', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 80, 'max' => 300 ] ],
			'default'    => [ 'size' => 110, 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-details--grid' => 'grid-template-columns: repeat(auto-fit, minmax({{SIZE}}{{UNIT}}, 1fr));',
			],
		] );

		$this->add_responsive_control( 'grid_gap', [
			'label'      => __( 'Gap between items', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
			'selectors'  => [ '{{WRAPPER}} .ibb-details--grid' => 'gap: {{SIZE}}{{UNIT}};' ],
		] );

		$this->add_control( 'grid_item_bg', [
			'label'     => __( 'Item background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ibb-details--grid .ibb-details__item' => 'background: {{VALUE}};' ],
		] );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
			'name'     => 'grid_item_border',
			'selector' => '{{WRAPPER}} .ibb-details--grid .ibb-details__item',
		] );

		$this->add_control( 'grid_item_radius', [
			'label'      => __( 'Item border radius', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-details--grid .ibb-details__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'grid_item_padding', [
			'label'      => __( 'Item padding', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-details--grid .ibb-details__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();

		// ---------- Value (the bigger / bolder text) ----------
		$this->start_controls_section( 'section_style_value', [
			'label' => __( 'Value', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'value_color', [
			'label'     => __( 'Color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY ],
			'selectors' => [
				'{{WRAPPER}} .ibb-details__value,
				 {{WRAPPER}} .ibb-details--compact strong,
				 {{WRAPPER}} .ibb-details--list dd' => 'color: {{VALUE}};',
			],
		] );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'value_typography',
			'global'   => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY ],
			'selector' => '{{WRAPPER}} .ibb-details__value, {{WRAPPER}} .ibb-details--compact strong, {{WRAPPER}} .ibb-details--list dd',
		] );

		$this->end_controls_section();

		// ---------- Label ----------
		$this->start_controls_section( 'section_style_label', [
			'label' => __( 'Label', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'label_color', [
			'label'     => __( 'Color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ],
			'selectors' => [
				'{{WRAPPER}} .ibb-details__label,
				 {{WRAPPER}} .ibb-details--list dt' => 'color: {{VALUE}};',
				'{{WRAPPER}} .ibb-details--compact'  => 'color: {{VALUE}};',
			],
		] );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'label_typography',
			'global'   => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT ],
			'selector' => '{{WRAPPER}} .ibb-details__label, {{WRAPPER}} .ibb-details--list dt, {{WRAPPER}} .ibb-details--compact',
		] );

		$this->end_controls_section();

		// ---------- Icon ----------
		$this->start_controls_section( 'section_style_icon', [
			'label' => __( 'Icon', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'icon_color', [
			'label'     => __( 'Color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT ],
			'selectors' => [
				'{{WRAPPER}} .ibb-details__icon, {{WRAPPER}} .ibb-details__icon i, {{WRAPPER}} .ibb-details__icon svg' => 'color: {{VALUE}}; fill: {{VALUE}};',
			],
		] );

		$this->add_responsive_control( 'icon_size', [
			'label'      => __( 'Size', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'em' ],
			'range'      => [ 'px' => [ 'min' => 8, 'max' => 80 ] ],
			'default'    => [ 'size' => 20, 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-details__icon i'   => 'font-size: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .ibb-details__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_control( 'icon_spacing', [
			'label'      => __( 'Spacing', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'em' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
			'default'    => [ 'size' => 6, 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-details__icon' => 'margin-right: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();

		// ---------- Alignment ----------
		$this->start_controls_section( 'section_style_alignment', [
			'label' => __( 'Alignment', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'text_align', [
			'label'     => __( 'Alignment', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => [
				'flex-start' => [ 'title' => __( 'Left', 'ibb-rentals' ),   'icon' => 'eicon-text-align-left' ],
				'center'     => [ 'title' => __( 'Center', 'ibb-rentals' ), 'icon' => 'eicon-text-align-center' ],
				'flex-end'   => [ 'title' => __( 'Right', 'ibb-rentals' ),  'icon' => 'eicon-text-align-right' ],
			],
			'selectors_dictionary' => [
				'flex-start' => 'flex-start',
				'center'     => 'center',
				'flex-end'   => 'flex-end',
			],
			'selectors' => [
				'{{WRAPPER}} .ibb-details--grid .ibb-details__item' => 'align-items: {{VALUE}};',
			],
		] );

		$this->add_responsive_control( 'text_align_compact', [
			'label'     => __( 'Compact / list alignment', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => [
				'left'   => [ 'title' => __( 'Left', 'ibb-rentals' ),   'icon' => 'eicon-text-align-left' ],
				'center' => [ 'title' => __( 'Center', 'ibb-rentals' ), 'icon' => 'eicon-text-align-center' ],
				'right'  => [ 'title' => __( 'Right', 'ibb-rentals' ),  'icon' => 'eicon-text-align-right' ],
			],
			'condition' => [ 'layout!' => 'grid' ],
			'selectors' => [ '{{WRAPPER}} .ibb-details--compact, {{WRAPPER}} .ibb-details--list' => 'text-align: {{VALUE}};' ],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$property = ElementorModule::resolve_property_for_widget( (string) ( $settings['property_id'] ?? 'current' ) );
		if ( ! $property ) {
			return;
		}

		$fields = [];
		$icons  = [];
		foreach ( array_keys( $this->field_options() ) as $key ) {
			if ( ( $settings[ 'show_' . $key ] ?? '' ) !== 'yes' ) {
				continue;
			}
			$fields[] = $key;

			$icon_setting = $settings[ 'icon_' . $key ] ?? null;
			if ( is_array( $icon_setting ) && ! empty( $icon_setting['value'] ) ) {
				// Render via Elementor's icon manager — handles eicon, FA, SVG.
				ob_start();
				\Elementor\Icons_Manager::render_icon( $icon_setting, [ 'aria-hidden' => 'true' ] );
				$icons[ $key ] = (string) ob_get_clean();
			}
		}

		$shortcodes = new Shortcodes();
		echo $shortcodes->render_property_details( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'id'     => $property->id,
			'fields' => implode( ',', $fields ),
			'layout' => (string) ( $settings['layout'] ?? 'grid' ),
			'icons'  => $icons,
		] );
	}
}
