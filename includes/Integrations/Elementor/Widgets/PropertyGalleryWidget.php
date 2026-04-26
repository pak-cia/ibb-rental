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
