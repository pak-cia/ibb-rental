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
