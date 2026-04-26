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
 *   - On `elementor/loaded` (only fires when Elementor itself is active),
 *     register an `elementor/dynamic_tags/register` callback.
 *   - The callback registers our group + tag classes via the manager.
 *
 * Tag classes are `require_once`'d at registration time, not via PSR-4
 * autoload, because they extend Elementor base classes that don't exist
 * until Elementor's autoloader has loaded — autoloading too early would
 * trigger a parse-time fatal.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor;

use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class Module {

	public function register(): void {
		add_action( 'elementor/loaded', [ $this, 'on_elementor_loaded' ] );
	}

	public function on_elementor_loaded(): void {
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
}
