<?php
/**
 * Runtime checks for PHP / WordPress / WooCommerce versions.
 *
 * The plugin header's `Requires Plugins: woocommerce` line blocks activation
 * when WC is missing on WP 6.5+, but we still verify at runtime: the user may
 * have deactivated WC after activating us, and we want a clear admin notice
 * rather than fatal errors from missing classes.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Setup;

defined( 'ABSPATH' ) || exit;

final class Requirements {

	/** @var list<string> */
	private array $failures = [];

	public function are_met(): bool {
		$this->failures = [];

		if ( version_compare( PHP_VERSION, IBB_RENTALS_MIN_PHP, '<' ) ) {
			$this->failures[] = sprintf(
				/* translators: 1: required PHP version, 2: detected version */
				__( 'IBB Rentals requires PHP %1$s or higher. You are running PHP %2$s.', 'ibb-rentals' ),
				IBB_RENTALS_MIN_PHP,
				PHP_VERSION
			);
		}

		global $wp_version;
		if ( version_compare( (string) $wp_version, IBB_RENTALS_MIN_WP, '<' ) ) {
			$this->failures[] = sprintf(
				/* translators: 1: required WP version, 2: detected version */
				__( 'IBB Rentals requires WordPress %1$s or higher. You are running %2$s.', 'ibb-rentals' ),
				IBB_RENTALS_MIN_WP,
				$wp_version
			);
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->failures[] = __( 'IBB Rentals requires WooCommerce to be installed and active.', 'ibb-rentals' );
		} elseif ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, IBB_RENTALS_MIN_WC, '<' ) ) {
			$this->failures[] = sprintf(
				/* translators: 1: required WooCommerce version, 2: detected version */
				__( 'IBB Rentals requires WooCommerce %1$s or higher. You are running %2$s.', 'ibb-rentals' ),
				IBB_RENTALS_MIN_WC,
				WC_VERSION
			);
		}

		return empty( $this->failures );
	}

	public function render_failure_notice(): void {
		if ( empty( $this->failures ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>IBB Rentals</strong></p><ul style="list-style:disc;margin-left:20px">';
		foreach ( $this->failures as $msg ) {
			echo '<li>' . esc_html( $msg ) . '</li>';
		}
		echo '</ul></div>';
	}
}
