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

		// Defer if anything else has already taken over `template_include`. Most
		// commonly Elementor Pro's Theme Builder, but the same logic protects any
		// page-builder plugin that runs at a lower priority and has assigned a
		// matching Single template via Display Conditions.
		if ( $this->should_defer_to_external_template( $template ) ) {
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
	 * True when an external page-builder/plugin has already replaced the
	 * `template_include` value, OR when Elementor Pro Theme Builder reports a
	 * matching Single-location document for the current request.
	 *
	 * Two layered checks because either alone is insufficient:
	 *   - **Path check**: most reliable. If `$template` already lives inside a
	 *     non-theme plugin's directory, something has hooked `template_include`
	 *     ahead of us with intent. We back off.
	 *   - **API check** (Elementor Pro only): catches edge cases where Elementor
	 *     hasn't yet flipped `$template` (e.g. it uses a different mechanism or a
	 *     different hook on this site) but has a registered matching document.
	 */
	private function should_defer_to_external_template( string $template ): bool {
		$normalized = wp_normalize_path( $template );

		// Path check — Elementor Pro Theme Builder canvas/header/footer templates
		// live under `/wp-content/plugins/elementor-pro/`.
		if ( strpos( $normalized, '/elementor-pro/' ) !== false ) {
			return true;
		}
		// Free Elementor doesn't ship Theme Builder, but Pro's free counterpart
		// (or third-party theme builders) might still rewrite the path elsewhere.
		// As a heuristic, defer if the template path lives in any plugin folder
		// other than our own — i.e. somebody else explicitly hooked
		// `template_include` to override the theme's default template.
		$plugins_dir = wp_normalize_path( WP_PLUGIN_DIR );
		$our_dir     = wp_normalize_path( IBB_RENTALS_DIR );
		if (
			strpos( $normalized, $plugins_dir ) === 0 &&
			strpos( $normalized, $our_dir )      !== 0
		) {
			return true;
		}

		// API check — Elementor Pro Theme Builder.
		if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
			try {
				$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
				if ( $module && method_exists( $module, 'get_conditions_manager' ) ) {
					$cm = $module->get_conditions_manager();
					if ( $cm && method_exists( $cm, 'get_documents_for_location' ) ) {
						$docs = $cm->get_documents_for_location( 'single' );
						if ( ! empty( $docs ) ) {
							return true;
						}
					}
				}
			} catch ( \Throwable ) {
				// Elementor Pro API shape changed or threw — fall through.
			}
		}

		return false;
	}
}
