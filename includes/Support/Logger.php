<?php
/**
 * Thin wrapper around WC's logger.
 *
 * Funnels all plugin logs into the `ibb-rentals` source so admins can filter
 * them under WooCommerce → Status → Logs. Falls back to `error_log()` if WC's
 * logger is unavailable (e.g. during plugin deactivation while WC is still loaded).
 */

declare( strict_types=1 );

namespace IBB\Rentals\Support;

defined( 'ABSPATH' ) || exit;

final class Logger {

	private const SOURCE = 'ibb-rentals';

	public function info( string $message, array $context = [] ): void {
		$this->log( 'info', $message, $context );
	}

	public function warning( string $message, array $context = [] ): void {
		$this->log( 'warning', $message, $context );
	}

	public function error( string $message, array $context = [] ): void {
		$this->log( 'error', $message, $context );
	}

	private function log( string $level, string $message, array $context ): void {
		if ( $context ) {
			$message .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, [ 'source' => self::SOURCE ] );
			return;
		}

		error_log( '[' . self::SOURCE . '][' . $level . '] ' . $message );
	}
}
