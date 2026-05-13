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
	public const LATEST_VERSION = 6;

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

	/**
	 * v5 — split the legacy `direct` source into `web` (plugin-checkout
	 * bookings) and `direct` (walk-in / phone). Existing blocks tied to a
	 * WC order had to have come from the website, so we flip those to
	 * `web` and leave the rest alone (the few admin-entered manual
	 * `direct` blocks that pre-date this split stay labelled `direct`,
	 * which is now reserved for walk-ins anyway).
	 *
	 * No schema change — `source` is already VARCHAR(32) with no CHECK
	 * constraint — only data backfill.
	 */
	private static function migrate_to_5(): void {
		global $wpdb;
		$blocks = $wpdb->prefix . 'ibb_blocks';
		$wpdb->query( $wpdb->prepare(
			"UPDATE `$blocks` SET source = %s WHERE source = %s AND order_id IS NOT NULL",
			'web',
			'direct'
		) );
	}

	/**
	 * v6 — one-off cleanup of duplicate blocks where a ClickUp-sourced
	 * block (`external_uid LIKE 'clickup:%'`) and an iCal-imported block
	 * coexist for the same property on the same date range. This was the
	 * pre-v0.11.5 pattern when the host manually blocked Airbnb's calendar
	 * to prevent overbooking a non-Airbnb booking: our iCal import created
	 * a `source='airbnb'` mirror, and v0.11.0's ClickUp auto-create added
	 * a separate `source=<actual-OTA>` block from the ClickUp task. Both
	 * displayed as overlapping bars on the admin calendar.
	 *
	 * The ClickUp block is canonical (it carries the booking ID, guest
	 * name, and the right source label). The iCal-imported block is the
	 * redundant mirror — delete it. v0.11.5's Importer skip prevents
	 * fresh ones being created on the next poll.
	 *
	 * Half-open overlap predicate: a.end > b.start AND b.end > a.start.
	 * Same-property scope. We only delete the iCal-side row (b), never
	 * the ClickUp row (a).
	 */
	private static function migrate_to_6(): void {
		global $wpdb;
		$blocks = $wpdb->prefix . 'ibb_blocks';
		$wpdb->query(
			"DELETE b FROM `$blocks` AS b
			 INNER JOIN `$blocks` AS a
			    ON a.property_id = b.property_id
			   AND a.start_date < b.end_date
			   AND a.end_date   > b.start_date
			   AND a.id <> b.id
			 WHERE a.external_uid LIKE 'clickup:%'
			   AND a.status = 'confirmed'
			   AND b.external_uid NOT LIKE 'clickup:%'
			   AND b.status = 'confirmed'"
		);
	}
}
