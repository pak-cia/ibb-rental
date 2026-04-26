<?php
/**
 * Elementor widget: Property Gallery (static grid + lightbox).
 *
 * Photo grid for a property — full set or one named sub-gallery. Mirrors the
 * `ibb/gallery` Gutenberg block. Delegates to the `[ibb_gallery]` shortcode.
 *
 * For an animated/swipeable carousel layout, see PropertyCarouselWidget.
 * (Both consume the same property gallery data; this widget is a static
 * grid, the carousel is Swiper-driven slides.)
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\Widgets;

use IBB\Rentals\Frontend\Shortcodes;
use IBB\Rentals\Integrations\Elementor\Module as ElementorModule;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	return;
}

class PropertyGalleryWidget extends \Elementor\Widget_Base {

	public function get_name(): string         { return 'ibb_property_gallery'; }
	public function get_title(): string        { return __( 'Property Gallery', 'ibb-rentals' ); }
	public function get_icon(): string         { return 'eicon-gallery-grid'; }
	public function get_categories(): array    { return [ 'ibb-rentals' ]; }
	public function get_keywords(): array      { return [ 'gallery', 'photos', 'images', 'rental', 'ibb' ]; }
	public function get_style_depends(): array  { return [ 'ibb-rentals-frontend' ]; }
	public function get_script_depends(): array { return [ 'ibb-rentals-booking' ]; }

	protected function register_controls(): void {
		$this->start_controls_section( 'section_source', [
			'label' => __( 'Source', 'ibb-rentals' ),
		] );

		$this->add_control( 'property_id', [
			'label'   => __( 'Property', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT2,
			'options' => ElementorModule::property_options(),
			'default' => 'current',
		] );

		$this->add_control( 'gallery_slug', [
			'label'   => __( 'Gallery', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => ElementorModule::gallery_slug_options(),
			'default' => '',
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'section_layout', [
			'label' => __( 'Layout', 'ibb-rentals' ),
		] );

		$this->add_control( 'cols', [
			'label'   => __( 'Columns', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 3,
			'min'     => 1,
			'max'     => 6,
		] );

		$this->add_control( 'size', [
			'label'   => __( 'Image size', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'medium_large',
			'options' => [
				'thumbnail'    => __( 'Thumbnail', 'ibb-rentals' ),
				'medium'       => __( 'Medium', 'ibb-rentals' ),
				'medium_large' => __( 'Medium-large', 'ibb-rentals' ),
				'large'        => __( 'Large', 'ibb-rentals' ),
				'full'         => __( 'Full', 'ibb-rentals' ),
			],
		] );

		$this->add_control( 'link', [
			'label'   => __( 'On click', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'file',
			'options' => [
				'file' => __( 'Open lightbox', 'ibb-rentals' ),
				'none' => __( 'No link', 'ibb-rentals' ),
			],
		] );

		$this->end_controls_section();

		$this->register_style_controls();
	}

	private function register_style_controls(): void {
		// ---------- Grid ----------
		$this->start_controls_section( 'section_style_grid', [
			'label' => __( 'Grid', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'gap', [
			'label'      => __( 'Gap between images', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
			'default'    => [ 'size' => 8, 'unit' => 'px' ],
			'selectors'  => [ '{{WRAPPER}} .ibb-gallery-display' => 'gap: {{SIZE}}{{UNIT}};' ],
		] );

		$this->end_controls_section();

		// ---------- Image ----------
		$this->start_controls_section( 'section_style_image', [
			'label' => __( 'Image', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'aspect_ratio', [
			'label'   => __( 'Aspect ratio', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => [ 'px' => [ 'min' => 0.5, 'max' => 3, 'step' => 0.05 ] ],
			'default' => [ 'size' => 1 ],
			'selectors' => [ '{{WRAPPER}} .ibb-gallery-display__item' => 'aspect-ratio: {{SIZE}};' ],
		] );

		$this->add_control( 'object_fit', [
			'label'     => __( 'Object fit', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'cover',
			'options'   => [
				'cover'   => __( 'Cover (crop)', 'ibb-rentals' ),
				'contain' => __( 'Contain (letterbox)', 'ibb-rentals' ),
			],
			'selectors' => [ '{{WRAPPER}} .ibb-gallery-display__image' => 'object-fit: {{VALUE}};' ],
		] );

		$this->add_responsive_control( 'image_radius', [
			'label'      => __( 'Border radius', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-gallery-display__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
			'name'     => 'image_border',
			'selector' => '{{WRAPPER}} .ibb-gallery-display__item',
		] );

		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), [
			'name'     => 'image_shadow',
			'selector' => '{{WRAPPER}} .ibb-gallery-display__item',
		] );

		$this->add_control( 'hover_zoom', [
			'label'   => __( 'Hover zoom', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => [ 'px' => [ 'min' => 1, 'max' => 1.5, 'step' => 0.01 ] ],
			'default' => [ 'size' => 1.03 ],
			'selectors' => [
				'{{WRAPPER}} .ibb-gallery-display__item:hover .ibb-gallery-display__image' => 'transform: scale({{SIZE}});',
			],
		] );

		$this->add_control( 'hover_overlay', [
			'label'     => __( 'Hover overlay', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .ibb-gallery-display__item' => 'position: relative;',
				'{{WRAPPER}} .ibb-gallery-display__item:hover::after' => 'content: ""; position: absolute; inset: 0; background: {{VALUE}}; pointer-events: none; transition: background .2s;',
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
		echo $shortcodes->render_gallery( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'property' => $property->id,
			'gallery'  => (string) ( $settings['gallery_slug'] ?? '' ),
			'size'     => (string) ( $settings['size'] ?? 'medium_large' ),
			'cols'     => (int) ( $settings['cols'] ?? 3 ),
			'link'     => (string) ( $settings['link'] ?? 'file' ),
		] );
	}
}
