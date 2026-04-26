<?php
/**
 * Optional Elementor integration — module entry point.
 *
 * Each integration in this plugin is a self-contained module rooted at
 * `includes/Integrations/<Provider>/`. The module's `Module` class is the
 * single entry point loaded by `IBB\Rentals\Plugin::boot()`; everything
 * else (dynamic tags, widgets, controls, …) lives in subdirectories of
 * the module and is loaded lazily by the entry point.
 *
 * Wired into Elementor's lifecycle:
 *
 *   - Hook directly to `elementor/dynamic_tags/register`, which fires when
 *     Elementor's dynamic-tags manager initialises (during editor load).
 *     The action only exists / fires when Elementor itself is loaded, so
 *     it doubles as the "is Elementor active?" gate.
 *
 *   - DO NOT use `elementor/loaded` as a gate. That action fires during
 *     wp-settings.php's plugin-load loop, BEFORE `plugins_loaded` — by
 *     the time `Plugin::boot()` runs (priority 20) and registers our
 *     handler, the action has already fired and the handler never runs.
 *
 * Tag classes are `require_once`'d at registration time, not via PSR-4
 * autoload, because they extend Elementor base classes that don't exist
 * until Elementor's autoloader has loaded — autoloading too early would
 * trigger a parse-time fatal.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor;

use IBB\Rentals\Domain\Property;
use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class Module {

	public function register(): void {
		add_action( 'elementor/dynamic_tags/register', [ $this, 'register_tags' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_widget_category' ] );
		add_action( 'elementor/frontend/after_register_scripts', [ $this, 'register_widget_scripts' ] );
		add_action( 'elementor/preview/enqueue_scripts', [ $this, 'enqueue_widget_scripts_for_preview' ] );
	}

	/**
	 * Register the carousel-init JS against Elementor's frontend script
	 * registry. The script is queued for any page that uses our carousel
	 * widget; declared in PropertyCarouselWidget::get_script_depends().
	 *
	 * Inits Swiper per-widget-instance via Elementor's
	 * `frontend/element_ready/ibb_property_carousel.default` hook so each
	 * carousel gets its own Swiper, even when the editor re-renders on
	 * control changes or multiple carousels live on the same page.
	 */
	public function register_widget_scripts(): void {
		wp_register_script(
			'ibb-rentals-elementor-carousel',
			'',
			[ 'jquery', 'swiper', 'elementor-frontend' ],
			IBB_RENTALS_VERSION,
			true
		);
		wp_add_inline_script( 'ibb-rentals-elementor-carousel', $this->carousel_init_js() );
	}

	public function enqueue_widget_scripts_for_preview(): void {
		// In Elementor's editor preview, our widget's get_script_depends()
		// is honoured, but we also enqueue the init script directly so live
		// previews in the editor work without first publishing.
		wp_enqueue_script( 'ibb-rentals-elementor-carousel' );
	}

	private function carousel_init_js(): string {
		return <<<'JS'
( function ( $ ) {
	if ( typeof window === 'undefined' ) return;

	function destroySwiperOn( el ) {
		if ( el && el.swiper && typeof el.swiper.destroy === 'function' ) {
			el.swiper.destroy( true, true );
		}
	}

	function initIBBCarousel( $scope ) {
		var $root = $scope.find( '.ibb-property-carousel' ).first();
		if ( ! $root.length ) return;
		var rootNode = $root[0];
		var Swiper = window.Swiper;
		if ( typeof Swiper !== 'function' ) return;

		var config;
		try { config = JSON.parse( rootNode.dataset.ibbCarouselConfig || '{}' ); } catch ( e ) { config = {}; }
		var layout = ( config.layout === 'carousel' ) ? 'carousel' : 'slideshow';

		if ( layout === 'slideshow' ) {
			initSlideshow( rootNode, config );
		} else {
			initCarousel( rootNode, config );
		}
	}

	function initSlideshow( rootNode, config ) {
		var mainEl   = rootNode.querySelector( '.ibb-property-carousel__main' );
		var thumbsEl = rootNode.querySelector( '.ibb-property-carousel__thumbs' );
		if ( ! mainEl || ! thumbsEl ) return;

		// Tear down prior instances (editor re-renders).
		destroySwiperOn( mainEl );
		destroySwiperOn( thumbsEl );

		var thumbs = new window.Swiper( thumbsEl, {
			spaceBetween: 8,
			slidesPerView: config.thumbsPerView || 5,
			watchSlidesProgress: true,
			breakpoints: {
				480: { slidesPerView: Math.min( 4, config.thumbsPerView || 5 ) },
				768: { slidesPerView: config.thumbsPerView || 5 }
			}
		} );

		var mainOpts = {
			spaceBetween: 0,
			slidesPerView: 1,
			loop: !! config.loop,
			speed: config.speed || 500,
			effect: config.effect === 'fade' ? 'fade' : 'slide',
			fadeEffect: { crossFade: true },
			thumbs: { swiper: thumbs }
		};

		if ( config.autoplay ) {
			mainOpts.autoplay = {
				delay: config.autoplayDelay || 4000,
				disableOnInteraction: false,
				pauseOnMouseEnter: !! config.pauseOnHover
			};
		}

		if ( config.showArrows ) {
			mainOpts.navigation = {
				nextEl: rootNode.querySelector( '.ibb-property-carousel__next' ),
				prevEl: rootNode.querySelector( '.ibb-property-carousel__prev' )
			};
		}

		new window.Swiper( mainEl, mainOpts );
	}

	function initCarousel( rootNode, config ) {
		destroySwiperOn( rootNode );

		var opts = {
			slidesPerView: config.slidesPerView || 1,
			spaceBetween: typeof config.spaceBetween === 'number' ? config.spaceBetween : 16,
			loop: !! config.loop,
			speed: config.speed || 500,
			effect: config.effect === 'fade' ? 'fade' : 'slide',
			fadeEffect: { crossFade: true },
			breakpoints: {
				480: { slidesPerView: config.slidesPerViewMobile || 1 },
				768: { slidesPerView: config.slidesPerViewTablet || 1 },
				1024: { slidesPerView: config.slidesPerView || 1 }
			}
		};

		if ( config.autoplay ) {
			opts.autoplay = {
				delay: config.autoplayDelay || 4000,
				disableOnInteraction: false,
				pauseOnMouseEnter: !! config.pauseOnHover
			};
		}

		if ( config.showArrows ) {
			opts.navigation = {
				nextEl: rootNode.querySelector( '.ibb-property-carousel__next' ),
				prevEl: rootNode.querySelector( '.ibb-property-carousel__prev' )
			};
		}

		if ( config.pagination ) {
			opts.pagination = {
				el: rootNode.querySelector( '.ibb-property-carousel__pagination' ),
				type: config.pagination,
				clickable: true
			};
		}

		new window.Swiper( rootNode, opts );
	}

	$( window ).on( 'elementor/frontend/init', function () {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) return;
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/ibb_property_carousel.default',
			initIBBCarousel
		);
	} );
} )( window.jQuery );
JS;
	}

	public function register_widget_category( $manager ): void {
		$manager->add_category( 'ibb-rentals', [
			'title' => __( 'IBB Rentals', 'ibb-rentals' ),
			'icon'  => 'eicon-palmtree',
		] );
	}

	public function register_widgets( $widgets_manager ): void {
		$widget_files = [
			'BookingFormWidget',
			'PropertyDetailsWidget',
			'PropertyGalleryWidget',
			'PropertyCarouselWidget',
		];
		foreach ( $widget_files as $name ) {
			require_once __DIR__ . '/Widgets/' . $name . '.php';
			$cls = '\\IBB\\Rentals\\Integrations\\Elementor\\Widgets\\' . $name;
			if ( class_exists( $cls ) && method_exists( $widgets_manager, 'register' ) ) {
				$widgets_manager->register( new $cls() );
			}
		}
	}

	public function register_tags( $manager ): void {
		if ( method_exists( $manager, 'register_group' ) ) {
			$manager->register_group( 'ibb-rentals', [
				'title' => __( 'IBB Rentals', 'ibb-rentals' ),
			] );
		}

		require_once __DIR__ . '/DynamicTags/PropertyGalleryDynamicTag.php';
		$tag_class = '\\IBB\\Rentals\\Integrations\\Elementor\\DynamicTags\\PropertyGalleryDynamicTag';

		if ( method_exists( $manager, 'register' ) ) {
			$manager->register( new $tag_class() );
		} elseif ( method_exists( $manager, 'register_tag' ) ) {
			// Pre-3.5 Elementor API.
			$manager->register_tag( $tag_class );
		}
	}

	/**
	 * Returns a list of all properties suitable for an Elementor SELECT2
	 * control. Cached for the request — Elementor's editor calls this on
	 * every panel render.
	 *
	 * Used by `DynamicTags\PropertyGalleryDynamicTag` (and any future tag
	 * that needs a property picker).
	 *
	 * @return array<string,string>
	 */
	public static function property_options(): array {
		static $cached = null;
		if ( $cached !== null ) {
			return $cached;
		}
		$cached = [
			'current' => __( 'Current page (if it is a property)', 'ibb-rentals' ),
		];
		$posts = get_posts( [
			'post_type'        => PropertyPostType::POST_TYPE,
			'post_status'      => [ 'publish', 'private', 'draft' ],
			'numberposts'      => 200,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
		] );
		foreach ( $posts as $p ) {
			$cached[ (string) $p->ID ] = $p->post_title !== '' ? $p->post_title : ( '#' . $p->ID );
		}
		return $cached;
	}

	/**
	 * Resolve which property a widget / dynamic tag should render against,
	 * given the picked `property_id` value from the control schema.
	 *
	 * Order:
	 *   1. If a specific property is picked, use it.
	 *   2. If `'current'` (or empty) is picked: use the current post if it's
	 *      an `ibb_property`, otherwise fall back to the FIRST published
	 *      property. This last step is purely an editor-preview convenience —
	 *      while editing a generic Elementor page (e.g. "Elementor #36"),
	 *      `get_the_ID()` returns the page itself, which isn't a property.
	 *      Without the fallback the widget would silently render empty and
	 *      look broken to the editor. On a real single-property template the
	 *      current property always wins, so production rendering is unaffected.
	 *
	 * Used by every IBB Elementor widget and dynamic tag — single source of
	 * truth for "which property?".
	 */
	public static function resolve_property_for_widget( string $picked ): ?Property {
		if ( $picked !== '' && $picked !== 'current' ) {
			return Property::from_id( (int) $picked );
		}

		$current_id = (int) get_the_ID();
		if ( $current_id > 0 ) {
			$current = Property::from_id( $current_id );
			if ( $current ) {
				return $current;
			}
		}

		$ids = get_posts( [
			'post_type'        => PropertyPostType::POST_TYPE,
			'post_status'      => [ 'publish', 'private' ],
			'numberposts'      => 1,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
		] );
		return ! empty( $ids ) ? Property::from_id( (int) $ids[0] ) : null;
	}

	/**
	 * Returns the union of every distinct gallery slug across every property,
	 * suitable for an Elementor SELECT control. Pinned to the top is an
	 * empty-string "All photos" option that means "combine every gallery".
	 *
	 * Slugs are global to the plugin (a property's "bedroom-1" slug means
	 * the same thing as another property's "bedroom-1"), so a tag picking
	 * "bedroom-1" applies cleanly to whichever property is the rendering
	 * context. If a chosen property doesn't have the picked slug, the tag
	 * silently returns no images for that page — which is the right default,
	 * editors can change it.
	 *
	 * @return array<string,string>
	 */
	public static function gallery_slug_options(): array {
		static $cached = null;
		if ( $cached !== null ) {
			return $cached;
		}
		$cached = [
			'' => __( 'All photos (every gallery combined)', 'ibb-rentals' ),
		];

		$post_ids = get_posts( [
			'post_type'        => PropertyPostType::POST_TYPE,
			'post_status'      => [ 'publish', 'private', 'draft' ],
			'numberposts'      => 200,
			'fields'           => 'ids',
			'suppress_filters' => true,
		] );

		$seen = [];
		foreach ( $post_ids as $pid ) {
			$property = Property::from_id( (int) $pid );
			if ( ! $property ) {
				continue;
			}
			foreach ( $property->galleries() as $gallery ) {
				$slug = (string) $gallery['slug'];
				if ( $slug === '' || isset( $seen[ $slug ] ) ) {
					continue;
				}
				$seen[ $slug ] = (string) $gallery['label'];
			}
		}
		asort( $seen );
		foreach ( $seen as $slug => $label ) {
			$cached[ $slug ] = $label . '  (' . $slug . ')';
		}
		return $cached;
	}
}
