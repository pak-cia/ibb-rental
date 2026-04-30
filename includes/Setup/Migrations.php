<?php
/**
 * Versioned, idempotent schema migrations.
 *
 * Tracks the installed schema version in the `ibb_rentals_db_version` option
 * and runs each pending migration method in order. dbDelta is used so re-running
 * a migration on an already-applied schema is a no-op.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Setup;

defined( 'ABSPATH' ) || exit;

final class Migrations {

	public const OPTION_KEY     = 'ibb_rentals_db_version';
	public const LATEST_VERSION = 4;

	public static function run_to_latest(): void {
		$current = (int) get_option( self::OPTION_KEY, 0 );

		if ( $current >= self::LATEST_VERSION ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		for ( $v = $current + 1; $v <= self::LATEST_VERSION; $v++ ) {
			$method = "migrate_to_{$v}";
			if ( method_exists( self::class, $method ) ) {
				self::$method();
				update_option( self::OPTION_KEY, $v, false );
			}
		}
	}

	private static function migrate_to_1(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		foreach ( Schema::all_sql( $charset_collate ) as $sql ) {
			dbDelta( $sql );
		}
	}

	private static function migrate_to_2(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		// Adds guest_name VARCHAR(255) column to wp_ibb_blocks.
		// dbDelta detects the missing column from the updated CREATE TABLE statement and ALTERs the table.
		dbDelta( Schema::blocks_sql( $charset_collate ) );
	}

	private static function migrate_to_3(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		// Adds clickup_task_id VARCHAR(64) column to wp_ibb_blocks (used for the
		// "View ClickUp task →" deep-link in the calendar detail modal).
		dbDelta( Schema::blocks_sql( $charset_collate ) );
	}

	private static function migrate_to_4(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		// Adds source_override VARCHAR(32) column. Owned by the ClickUp sync;
		// takes display precedence over `source` (which iCal imports keep overwriting),
		// so a manual-blackout block on Airbnb that's actually an Agoda or direct
		// booking per ClickUp shows the right OTA color and label in the calendar.
		dbDelta( Schema::blocks_sql( $charset_collate ) );
	}
}
