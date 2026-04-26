<?php
/**
 * PSR-4 autoloader for the IBB\Rentals namespace.
 *
 * Independent of Composer so the plugin can activate before `composer install`
 * has been run. Composer's autoloader (when present) is loaded first by the
 * bootstrap file and handles vendor classes; this loader handles only our own
 * IBB\Rentals\* classes.
 */

declare( strict_types=1 );

namespace IBB\Rentals;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	private const PREFIX  = 'IBB\\Rentals\\';
	private const BASEDIR = __DIR__ . '/';

	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	public static function load( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$path     = self::BASEDIR . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_file( $path ) ) {
			require $path;
		}
	}
}
