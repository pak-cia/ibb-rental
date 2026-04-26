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

		$this->add_control( 'size', [
			'label'   => __( 'Image size', 'ibb-rentals' ),
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

		$this->start_controls_section( 'section_slides_per_view', [
			'label' => __( 'Slides per view', 'ibb-rentals' ),
		] );

		$this->add_control( 'slides_per_view', [
			'label'   => __( 'Desktop', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 1,
			'min'     => 1,
			'max'     => 6,
		] );
		$this->add_control( 'slides_per_view_tablet', [
			'label'   => __( 'Tablet', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 1,
			'min'     => 1,
			'max'     => 6,
		] );
		$this->add_control( 'slides_per_view_mobile', [
			'label'   => __( 'Mobile', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 1,
			'min'     => 1,
			'max'     => 4,
		] );
		$this->add_control( 'space_between', [
			'label'   => __( 'Space between (px)', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 16,
			'min'     => 0,
			'max'     => 100,
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
	}

	protected function render(): void {
		$settings    = $this->get_settings_for_display();
		$property_id = (string) ( $settings['property_id'] ?? 'current' );
		if ( $property_id === 'current' || $property_id === '' ) {
			$property_id = (string) get_the_ID();
		}

		$property = Property::from_id( (int) $property_id );
		if ( ! $property ) {
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
			return;
		}

		$size   = (string) ( $settings['size'] ?? 'large' );
		$config = [
			'slidesPerView'      => max( 1, (int) ( $settings['slides_per_view'] ?? 1 ) ),
			'slidesPerViewTablet'=> max( 1, (int) ( $settings['slides_per_view_tablet'] ?? 1 ) ),
			'slidesPerViewMobile'=> max( 1, (int) ( $settings['slides_per_view_mobile'] ?? 1 ) ),
			'spaceBetween'       => max( 0, (int) ( $settings['space_between'] ?? 16 ) ),
			'loop'               => ( $settings['loop'] ?? '' ) === 'yes',
			'autoplay'           => ( $settings['autoplay'] ?? '' ) === 'yes',
			'autoplayDelay'      => max( 500, (int) ( $settings['autoplay_delay'] ?? 4000 ) ),
			'pauseOnHover'       => ( $settings['pause_on_hover'] ?? '' ) === 'yes',
			'effect'             => ( $settings['effect'] ?? 'slide' ) === 'fade' ? 'fade' : 'slide',
			'speed'              => max( 100, (int) ( $settings['speed'] ?? 500 ) ),
			'showArrows'         => ( $settings['show_arrows'] ?? '' ) === 'yes',
			'pagination'         => (string) ( $settings['pagination'] ?? 'bullets' ),
		];

		$instance_id = 'ibb-carousel-' . $this->get_id();
		?>
		<div
			class="ibb-property-carousel swiper"
			id="<?php echo esc_attr( $instance_id ); ?>"
			data-ibb-carousel-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
		>
			<div class="swiper-wrapper">
				<?php foreach ( $ids as $aid ) :
					$img = wp_get_attachment_image( (int) $aid, $size, false, [
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
