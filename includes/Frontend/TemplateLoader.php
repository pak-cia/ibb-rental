<?php
/**
 * Single-property template override.
 *
 * Lookup order on a singular `ibb_property`:
 *   1. theme child:  vrp-rentals/single-ibb_property.php
 *   2. theme:        single-ibb_property.php
 *   3. plugin:       templates/single-ibb_property.php
 *
 * The plugin template uses the active theme's get_header()/get_footer() so
 * it inherits site chrome regardless of theme.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Frontend;

use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class TemplateLoader {

	public function register(): void {
		add_filter( 'template_include', [ $this, 'route' ], 99 );
	}

	public function route( string $template ): string {
		if ( ! is_singular( PropertyPostType::POST_TYPE ) ) {
			return $template;
		}

		$theme_locations = [ 'ibb-rentals/single-ibb_property.php', 'single-ibb_property.php' ];
		$located = locate_template( $theme_locations );
		if ( $located ) {
			return $located;
		}

		$fallback = IBB_RENTALS_DIR . 'templates/single-ibb_property.php';
		if ( file_exists( $fallback ) ) {
			return $fallback;
		}
		return $template;
	}
}
