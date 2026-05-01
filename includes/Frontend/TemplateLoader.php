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

		// If Elementor Pro Theme Builder has a Single template whose Display Conditions
		// match this request (e.g. "Properties / All"), defer to it. Without this guard,
		// our plugin template silently overwrites the admin-assigned Elementor template.
		if ( $this->has_elementor_theme_template_for_single() ) {
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

	/**
	 * True when Elementor Pro's Theme Builder has at least one Single-location document
	 * whose conditions match the current request. Returns false if Elementor Pro isn't
	 * loaded or its API throws — we then fall through to the plugin template.
	 */
	private function has_elementor_theme_template_for_single(): bool {
		if ( ! class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
			return false;
		}
		try {
			$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
			if ( ! $module || ! method_exists( $module, 'get_conditions_manager' ) ) {
				return false;
			}
			$conditions_manager = $module->get_conditions_manager();
			if ( ! $conditions_manager || ! method_exists( $conditions_manager, 'get_documents_for_location' ) ) {
				return false;
			}
			$docs = $conditions_manager->get_documents_for_location( 'single' );
			return ! empty( $docs );
		} catch ( \Throwable ) {
			return false;
		}
	}
}
