<?php
/**
 * Elementor widget: Property Carousel.
 *
 * Swiper-driven slide-by-slide carousel populated from a property's photos.
 * Built specifically because Elementor Pro's Media Carousel uses a Repeater
 * for slides, which can't be populated from an array-returning dynamic tag —
 * users wanting a carousel layout couldn't use the Property Gallery dynamic
 * tag in that widget. This widget IS that experience: pick a property +
 * gallery, configure the slider behaviour, slides auto-render.
 *
 * Renders Swiper-compatible HTML and declares `swiper` as a script
 * dependency so Elementor enqueues it. Init is hooked via Elementor's
 * `frontend/element_ready/<widget_name>.default` lifecycle hook so each
 * widget instance gets its own Swiper, even when multiple carousels live
 * on the same page or the editor re-renders on control changes.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\Widgets;

use IBB\Rentals\Domain\Property;
use IBB\Rentals\Integrations\Elementor\Module as ElementorModule;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	return;
}

class PropertyCarouselWidget extends \Elementor\Widget_Base {

	public function get_name(): string      { return 'ibb_property_carousel'; }
	public function get_title(): string     { return __( 'Property Carousel', 'ibb-rentals' ); }
	public function get_icon(): string      { return 'eicon-slider-push'; }
	public function get_categories(): array { return [ 'ibb-rentals' ]; }
	public function get_keywords(): array   { return [ 'carousel', 'slider', 'gallery', 'photos', 'rental', 'ibb' ]; }

	public function get_script_depends(): array {
		return [ 'swiper', 'ibb-rentals-elementor-carousel' ];
	}

	public function get_style_depends(): array {
		return [ 'swiper', 'ibb-rentals-frontend' ];
	}

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

		$this->add_control( 'main_size', [
			'label'   => __( 'Main image size', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'large',
			'options' => [
				'medium'       => __( 'Medium', 'ibb-rentals' ),
				'medium_large' => __( 'Medium-large', 'ibb-rentals' ),
				'large'        => __( 'Large', 'ibb-rentals' ),
				'full'         => __( 'Full', 'ibb-rentals' ),
			],
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'section_layout', [
			'label' => __( 'Layout', 'ibb-rentals' ),
		] );

		$this->add_control( 'layout', [
			'label'       => __( 'Layout', 'ibb-rentals' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'slideshow',
			'options'     => [
				'slideshow' => __( 'Slideshow (large image + thumbnail strip)', 'ibb-rentals' ),
				'carousel'  => __( 'Carousel (multi-slide horizontal scroll)', 'ibb-rentals' ),
			],
			'description' => __( 'Slideshow: one main image with a clickable thumbnail strip below — like Elementor Pro\'s Media Carousel slideshow skin. Carousel: multiple slides per view scrolling horizontally.', 'ibb-rentals' ),
		] );

		$this->add_control( 'thumbs_size', [
			'label'     => __( 'Thumbnail image size', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'thumbnail',
			'options'   => [
				'thumbnail' => __( 'Thumbnail', 'ibb-rentals' ),
				'medium'    => __( 'Medium', 'ibb-rentals' ),
			],
			'condition' => [ 'layout' => 'slideshow' ],
		] );
		$this->add_control( 'thumbs_per_view', [
			'label'     => __( 'Thumbnails per row', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'default'   => 5,
			'min'       => 2,
			'max'       => 10,
			'condition' => [ 'layout' => 'slideshow' ],
		] );

		$this->add_control( 'slides_per_view', [
			'label'     => __( 'Slides per view (desktop)', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'default'   => 1,
			'min'       => 1,
			'max'       => 6,
			'condition' => [ 'layout' => 'carousel' ],
		] );
		$this->add_control( 'slides_per_view_tablet', [
			'label'     => __( 'Slides per view (tablet)', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'default'   => 1,
			'min'       => 1,
			'max'       => 6,
			'condition' => [ 'layout' => 'carousel' ],
		] );
		$this->add_control( 'slides_per_view_mobile', [
			'label'     => __( 'Slides per view (mobile)', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'default'   => 1,
			'min'       => 1,
			'max'       => 4,
			'condition' => [ 'layout' => 'carousel' ],
		] );
		$this->add_control( 'space_between', [
			'label'     => __( 'Space between (px)', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'default'   => 16,
			'min'       => 0,
			'max'       => 100,
			'condition' => [ 'layout' => 'carousel' ],
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'section_navigation', [
			'label' => __( 'Navigation', 'ibb-rentals' ),
		] );

		$this->add_control( 'show_arrows', [
			'label'        => __( 'Arrows', 'ibb-rentals' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		] );
		$this->add_control( 'pagination', [
			'label'   => __( 'Pagination', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'bullets',
			'options' => [
				''         => __( 'None', 'ibb-rentals' ),
				'bullets'  => __( 'Bullets', 'ibb-rentals' ),
				'fraction' => __( 'Fraction (1 / N)', 'ibb-rentals' ),
				'progressbar' => __( 'Progress bar', 'ibb-rentals' ),
			],
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'section_behaviour', [
			'label' => __( 'Behaviour', 'ibb-rentals' ),
		] );

		$this->add_control( 'loop', [
			'label'        => __( 'Infinite loop', 'ibb-rentals' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		] );
		$this->add_control( 'autoplay', [
			'label'        => __( 'Autoplay', 'ibb-rentals' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
		] );
		$this->add_control( 'autoplay_delay', [
			'label'     => __( 'Autoplay delay (ms)', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'default'   => 4000,
			'min'       => 500,
			'max'       => 30000,
			'condition' => [ 'autoplay' => 'yes' ],
		] );
		$this->add_control( 'pause_on_hover', [
			'label'        => __( 'Pause on hover', 'ibb-rentals' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => [ 'autoplay' => 'yes' ],
		] );
		$this->add_control( 'effect', [
			'label'   => __( 'Transition effect', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'slide',
			'options' => [
				'slide' => __( 'Slide', 'ibb-rentals' ),
				'fade'  => __( 'Fade', 'ibb-rentals' ),
			],
		] );
		$this->add_control( 'speed', [
			'label'   => __( 'Transition speed (ms)', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 500,
			'min'     => 100,
			'max'     => 5000,
		] );

		$this->end_controls_section();

		$this->register_style_controls();
	}

	private function register_style_controls(): void {
		// ---------- Image / slide ----------
		$this->start_controls_section( 'section_style_image', [
			'label' => __( 'Image', 'ibb-rentals' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'image_height', [
			'label'      => __( 'Height', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'vh' ],
			'range'      => [
				'px' => [ 'min' => 100, 'max' => 1000 ],
				'vh' => [ 'min' => 10, 'max' => 100 ],
			],
			'selectors'  => [
				'{{WRAPPER}} .ibb-property-carousel--slideshow .ibb-property-carousel__main .ibb-property-carousel__image,
				 {{WRAPPER}} .ibb-property-carousel--carousel .ibb-property-carousel__image' => 'height: {{SIZE}}{{UNIT}}; object-fit: cover;',
			],
		] );

		$this->add_control( 'image_object_fit', [
			'label'     => __( 'Object fit', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'cover',
			'options'   => [
				'cover'   => __( 'Cover (crop)', 'ibb-rentals' ),
				'contain' => __( 'Contain (letterbox)', 'ibb-rentals' ),
				'fill'    => __( 'Fill (stretch)', 'ibb-rentals' ),
			],
			'selectors' => [
				'{{WRAPPER}} .ibb-property-carousel__image' => 'object-fit: {{VALUE}};',
			],
			'condition' => [ 'image_height[size]!' => '' ],
		] );

		$this->add_responsive_control( 'image_border_radius', [
			'label'      => __( 'Border radius', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-property-carousel__image,
				 {{WRAPPER}} .ibb-property-carousel--slideshow .ibb-property-carousel__main' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
			'name'     => 'image_border',
			'selector' => '{{WRAPPER}} .ibb-property-carousel__image',
		] );

		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), [
			'name'     => 'image_box_shadow',
			'selector' => '{{WRAPPER}} .ibb-property-carousel--slideshow .ibb-property-carousel__main, {{WRAPPER}} .ibb-property-carousel--carousel .ibb-property-carousel__slide',
		] );

		$this->end_controls_section();

		// ---------- Navigation arrows ----------
		$this->start_controls_section( 'section_style_arrows', [
			'label'     => __( 'Arrows', 'ibb-rentals' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => [ 'show_arrows' => 'yes' ],
		] );

		$this->add_control( 'arrows_color', [
			'label'     => __( 'Color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ],
			'selectors' => [
				'{{WRAPPER}} .ibb-property-carousel .swiper-button-prev,
				 {{WRAPPER}} .ibb-property-carousel .swiper-button-next' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'arrows_bg', [
			'label'     => __( 'Background', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .ibb-property-carousel .swiper-button-prev,
				 {{WRAPPER}} .ibb-property-carousel .swiper-button-next' => 'background: {{VALUE}};',
			],
		] );

		$this->add_control( 'arrows_bg_hover', [
			'label'     => __( 'Background (hover)', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .ibb-property-carousel .swiper-button-prev:hover,
				 {{WRAPPER}} .ibb-property-carousel .swiper-button-next:hover' => 'background: {{VALUE}};',
			],
		] );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
			'name'     => 'arrows_border',
			'selector' => '{{WRAPPER}} .ibb-property-carousel .swiper-button-prev,
			               {{WRAPPER}} .ibb-property-carousel .swiper-button-next',
		] );

		$this->add_responsive_control( 'arrows_size', [
			'label'      => __( 'Button size', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 20, 'max' => 80 ] ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-property-carousel .swiper-button-prev,
				 {{WRAPPER}} .ibb-property-carousel .swiper-button-next' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'arrows_icon_size', [
			'label'      => __( 'Icon size', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 8, 'max' => 40 ] ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-property-carousel .swiper-button-prev,
				 {{WRAPPER}} .ibb-property-carousel .swiper-button-next' => '--swiper-navigation-size: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_control( 'arrows_border_radius', [
			'label'      => __( 'Border radius', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-property-carousel .swiper-button-prev,
				 {{WRAPPER}} .ibb-property-carousel .swiper-button-next' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'arrows_offset', [
			'label'      => __( 'Side offset', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-property-carousel .swiper-button-prev' => 'left: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .ibb-property-carousel .swiper-button-next' => 'right: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();

		// ---------- Pagination ----------
		$this->start_controls_section( 'section_style_pagination', [
			'label'     => __( 'Pagination', 'ibb-rentals' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => [ 'pagination!' => '', 'layout' => 'carousel' ],
		] );

		$this->add_control( 'pagination_color', [
			'label'     => __( 'Color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .ibb-property-carousel .swiper-pagination-bullet,
				 {{WRAPPER}} .ibb-property-carousel .swiper-pagination-fraction,
				 {{WRAPPER}} .ibb-property-carousel .swiper-pagination-progressbar' => '--swiper-pagination-color: {{VALUE}}; background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'pagination_color_active', [
			'label'     => __( 'Active color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT ],
			'selectors' => [
				'{{WRAPPER}} .ibb-property-carousel .swiper-pagination-bullet-active' => 'background: {{VALUE}}; opacity: 1;',
			],
		] );

		$this->end_controls_section();

		// ---------- Thumbnails (slideshow only) ----------
		$this->start_controls_section( 'section_style_thumbs', [
			'label'     => __( 'Thumbnails', 'ibb-rentals' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => [ 'layout' => 'slideshow' ],
		] );

		$this->add_responsive_control( 'thumbs_gap', [
			'label'      => __( 'Gap below main image', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-property-carousel--slideshow' => 'gap: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'thumbs_aspect_ratio', [
			'label'      => __( 'Aspect ratio', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => [ 'px' => [ 'min' => 0.5, 'max' => 3, 'step' => 0.05 ] ],
			'default'    => [ 'size' => 1.4 ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-property-carousel__thumb-image' => 'aspect-ratio: {{SIZE}};',
			],
		] );

		$this->add_control( 'thumbs_border_radius', [
			'label'      => __( 'Border radius', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-property-carousel__thumb' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_control( 'thumbs_inactive_opacity', [
			'label'     => __( 'Inactive opacity', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => [ 'px' => [ 'min' => 0.1, 'max' => 1, 'step' => 0.05 ] ],
			'default'   => [ 'size' => 0.55 ],
			'selectors' => [
				'{{WRAPPER}} .ibb-property-carousel__thumb' => 'opacity: {{SIZE}};',
			],
		] );

		$this->add_control( 'thumbs_active_outline', [
			'label'     => __( 'Active outline color', 'ibb-rentals' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT ],
			'selectors' => [
				'{{WRAPPER}} .ibb-property-carousel__thumb.swiper-slide-thumb-active' => 'outline-color: {{VALUE}};',
			],
		] );

		$this->add_responsive_control( 'thumbs_active_outline_width', [
			'label'      => __( 'Active outline width', 'ibb-rentals' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 8 ] ],
			'default'    => [ 'size' => 2 ],
			'selectors'  => [
				'{{WRAPPER}} .ibb-property-carousel__thumb' => 'outline-width: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$property = ElementorModule::resolve_property_for_widget( (string) ( $settings['property_id'] ?? 'current' ) );
		if ( ! $property ) {
			$this->editor_placeholder( __( 'No property could be resolved. Pick one in the Source panel, or publish a property post.', 'ibb-rentals' ) );
			return;
		}

		$slug = sanitize_key( (string) ( $settings['gallery_slug'] ?? '' ) );
		if ( $slug !== '' ) {
			$gallery = $property->gallery( $slug );
			$ids     = $gallery ? $gallery['attachments'] : [];
		} else {
			$ids = $property->all_attachments();
		}

		if ( empty( $ids ) ) {
			$this->editor_placeholder( sprintf(
				/* translators: 1: property title, 2: gallery slug or "All photos" */
				__( 'Property "%1$s" has no images in %2$s. Open the property → Photos tab to add some.', 'ibb-rentals' ),
				$property->title(),
				$slug !== '' ? $slug : __( 'any gallery', 'ibb-rentals' )
			) );
			return;
		}

		$layout       = ( $settings['layout'] ?? 'slideshow' ) === 'carousel' ? 'carousel' : 'slideshow';
		$main_size    = (string) ( $settings['main_size'] ?? 'large' );
		$thumbs_size  = (string) ( $settings['thumbs_size'] ?? 'thumbnail' );

		$config = [
			'layout'              => $layout,
			'slidesPerView'       => max( 1, (int) ( $settings['slides_per_view'] ?? 1 ) ),
			'slidesPerViewTablet' => max( 1, (int) ( $settings['slides_per_view_tablet'] ?? 1 ) ),
			'slidesPerViewMobile' => max( 1, (int) ( $settings['slides_per_view_mobile'] ?? 1 ) ),
			'spaceBetween'        => max( 0, (int) ( $settings['space_between'] ?? 16 ) ),
			'thumbsPerView'       => max( 2, (int) ( $settings['thumbs_per_view'] ?? 5 ) ),
			'loop'                => ( $settings['loop'] ?? '' ) === 'yes',
			'autoplay'            => ( $settings['autoplay'] ?? '' ) === 'yes',
			'autoplayDelay'       => max( 500, (int) ( $settings['autoplay_delay'] ?? 4000 ) ),
			'pauseOnHover'        => ( $settings['pause_on_hover'] ?? '' ) === 'yes',
			'effect'              => ( $settings['effect'] ?? 'slide' ) === 'fade' ? 'fade' : 'slide',
			'speed'               => max( 100, (int) ( $settings['speed'] ?? 500 ) ),
			'showArrows'          => ( $settings['show_arrows'] ?? '' ) === 'yes',
			'pagination'          => (string) ( $settings['pagination'] ?? 'bullets' ),
		];

		if ( $layout === 'slideshow' ) {
			$this->render_slideshow( $ids, $main_size, $thumbs_size, $config );
		} else {
			$this->render_carousel( $ids, $main_size, $config );
		}
	}

	/**
	 * Emit a visible placeholder when the widget has nothing to render —
	 * but only inside Elementor's editor / preview, so the front-end
	 * stays silent when there's nothing to show.
	 */
	private function editor_placeholder( string $message ): void {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}
		$is_editor  = \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode();
		$is_preview = \Elementor\Plugin::$instance->preview && \Elementor\Plugin::$instance->preview->is_preview_mode();
		if ( ! $is_editor && ! $is_preview ) {
			return;
		}
		echo '<div class="ibb-property-carousel-placeholder">' . esc_html( $message ) . '</div>';
	}

	/**
	 * @param array<int, int>     $ids
	 * @param array<string,mixed> $config
	 */
	private function render_slideshow( array $ids, string $main_size, string $thumbs_size, array $config ): void {
		?>
		<div
			class="ibb-property-carousel ibb-property-carousel--slideshow"
			data-ibb-carousel-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
		>
			<div class="ibb-property-carousel__main swiper">
				<div class="swiper-wrapper">
					<?php foreach ( $ids as $aid ) :
						$img = wp_get_attachment_image( (int) $aid, $main_size, false, [
							'class'   => 'ibb-property-carousel__image',
							'loading' => 'lazy',
						] );
						if ( ! $img ) {
							continue;
						}
						?>
						<div class="swiper-slide ibb-property-carousel__slide"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php endforeach; ?>
				</div>

				<?php if ( $config['showArrows'] ) : ?>
					<button type="button" class="swiper-button-prev ibb-property-carousel__prev" aria-label="<?php esc_attr_e( 'Previous slide', 'ibb-rentals' ); ?>"></button>
					<button type="button" class="swiper-button-next ibb-property-carousel__next" aria-label="<?php esc_attr_e( 'Next slide', 'ibb-rentals' ); ?>"></button>
				<?php endif; ?>
			</div>

			<div class="ibb-property-carousel__thumbs swiper">
				<div class="swiper-wrapper">
					<?php foreach ( $ids as $aid ) :
						$thumb = wp_get_attachment_image( (int) $aid, $thumbs_size, false, [
							'class'   => 'ibb-property-carousel__thumb-image',
							'loading' => 'lazy',
						] );
						if ( ! $thumb ) {
							continue;
						}
						?>
						<div class="swiper-slide ibb-property-carousel__thumb"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<int, int>     $ids
	 * @param array<string,mixed> $config
	 */
	private function render_carousel( array $ids, string $main_size, array $config ): void {
		?>
		<div
			class="ibb-property-carousel ibb-property-carousel--carousel swiper"
			data-ibb-carousel-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
		>
			<div class="swiper-wrapper">
				<?php foreach ( $ids as $aid ) :
					$img = wp_get_attachment_image( (int) $aid, $main_size, false, [
						'class'   => 'ibb-property-carousel__image',
						'loading' => 'lazy',
					] );
					if ( ! $img ) {
						continue;
					}
					?>
					<div class="swiper-slide ibb-property-carousel__slide"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endforeach; ?>
			</div>

			<?php if ( $config['pagination'] !== '' ) : ?>
				<div class="swiper-pagination ibb-property-carousel__pagination"></div>
			<?php endif; ?>

			<?php if ( $config['showArrows'] ) : ?>
				<button type="button" class="swiper-button-prev ibb-property-carousel__prev" aria-label="<?php esc_attr_e( 'Previous slide', 'ibb-rentals' ); ?>"></button>
				<button type="button" class="swiper-button-next ibb-property-carousel__next" aria-label="<?php esc_attr_e( 'Next slide', 'ibb-rentals' ); ?>"></button>
			<?php endif; ?>
		</div>
		<?php
	}
}
