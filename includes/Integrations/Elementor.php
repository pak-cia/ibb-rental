<?php
/**
 * Optional Elementor integration.
 *
 * Registers a dynamic-tag in the "gallery" category that returns a property's
 * photo gallery (whole property or a single named sub-gallery). Wired into
 * Elementor's Pro Gallery widget — guests using Elementor's drag-and-drop
 * editor can pick `IBB > Property Gallery` from the dynamic-tag picker.
 *
 * The whole file is gated on Elementor being active. The Tag class itself is
 * declared inside `register()` so we don't reference Elementor's parent class
 * before Elementor's autoloader has loaded it (avoids parse-time fatal).
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations;

use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class Elementor {

	public function register(): void {
		// Elementor fires `elementor/loaded` once core has finished loading.
		add_action( 'elementor/loaded', [ $this, 'on_elementor_loaded' ] );
	}

	public function on_elementor_loaded(): void {
		add_action( 'elementor/dynamic_tags/register', [ $this, 'register_tags' ] );
	}

	public function register_tags( $manager ): void {
		// `register_group` was renamed to `register_group` w/ array signature
		// in newer Elementor versions; the older API still accepts the same
		// shape. Both are safe to call.
		if ( method_exists( $manager, 'register_group' ) ) {
			$manager->register_group( 'ibb-rentals', [
				'title' => __( 'IBB Rentals', 'ibb-rentals' ),
			] );
		}

		require_once __DIR__ . '/Elementor/PropertyGalleryDynamicTag.php';
		if ( method_exists( $manager, 'register' ) ) {
			$manager->register( new \IBB\Rentals\Integrations\Elementor\PropertyGalleryDynamicTag() );
		} elseif ( method_exists( $manager, 'register_tag' ) ) {
			// Pre-3.5 Elementor API.
			$manager->register_tag( \IBB\Rentals\Integrations\Elementor\PropertyGalleryDynamicTag::class );
		}
	}

	/**
	 * Returns a list of all properties suitable for an Elementor SELECT2
	 * control. Cached for the request — Elementor's editor calls this on
	 * every panel render.
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
