<?php
/**
 * Custom DB schema definitions.
 *
 * Each method returns a single CREATE TABLE statement formatted exactly the way
 * `dbDelta()` requires (two spaces between PRIMARY KEY and column, KEY before
 * UNIQUE KEY, etc.). dbDelta is used by Migrations::run_to_latest().
 */

declare( strict_types=1 );

namespace IBB\Rentals\Setup;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'ibb_' . $name;
	}

	public static function blocks_sql( string $charset_collate ): string {
		$table = self::table( 'blocks' );
		return "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			property_id BIGINT(20) UNSIGNED NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NOT NULL,
			source VARCHAR(32) NOT NULL DEFAULT 'manual',
			external_uid VARCHAR(255) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
			order_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			summary VARCHAR(255) NOT NULL DEFAULT '',
			guest_name VARCHAR(255) NOT NULL DEFAULT '',
			clickup_task_id VARCHAR(64) NOT NULL DEFAULT '',
			source_override VARCHAR(32) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY property_dates (property_id, start_date, end_date),
			KEY property_end (property_id, end_date),
			KEY status_dates (status, start_date),
			UNIQUE KEY source_uid (property_id, source, external_uid)
		) {$charset_collate};";
	}

	public static function rates_sql( string $charset_collate ): string {
		$table = self::table( 'rates' );
		return "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			property_id BIGINT(20) UNSIGNED NOT NULL,
			date_from DATE NOT NULL,
			date_to DATE NOT NULL,
			nightly_rate DECIMAL(12,2) NOT NULL,
			weekend_uplift DECIMAL(12,2) NULL DEFAULT NULL,
			uplift_type VARCHAR(10) NOT NULL DEFAULT 'pct',
			min_stay SMALLINT UNSIGNED NULL DEFAULT NULL,
			priority SMALLINT NOT NULL DEFAULT 10,
			label VARCHAR(100) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY property_dates (property_id, date_from, date_to)
		) {$charset_collate};";
	}

	public static function bookings_sql( string $charset_collate ): string {
		$table = self::table( 'bookings' );
		return "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			property_id BIGINT(20) UNSIGNED NOT NULL,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			order_item_id BIGINT(20) UNSIGNED NOT NULL,
			block_id BIGINT(20) UNSIGNED NOT NULL,
			checkin DATE NOT NULL,
			checkout DATE NOT NULL,
			guests SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			guest_email VARCHAR(190) NOT NULL DEFAULT '',
			guest_name VARCHAR(190) NOT NULL DEFAULT '',
			total DECIMAL(12,2) NOT NULL DEFAULT 0,
			deposit_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
			balance_due DECIMAL(12,2) NOT NULL DEFAULT 0,
			balance_due_date DATE NULL DEFAULT NULL,
			balance_charge_action_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			payment_token_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			payment_method VARCHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			quote_snapshot LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY property_checkin (property_id, checkin),
			KEY order_id (order_id),
			KEY status_balance (status, balance_due_date)
		) {$charset_collate};";
	}

	public static function ical_feeds_sql( string $charset_collate ): string {
		$table = self::table( 'ical_feeds' );
		return "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			property_id BIGINT(20) UNSIGNED NOT NULL,
			url TEXT NOT NULL,
			label VARCHAR(100) NOT NULL DEFAULT '',
			source VARCHAR(32) NOT NULL DEFAULT 'other',
			last_synced_at DATETIME NULL DEFAULT NULL,
			last_status VARCHAR(20) NOT NULL DEFAULT '',
			last_error TEXT NULL,
			etag VARCHAR(190) NOT NULL DEFAULT '',
			last_modified VARCHAR(190) NOT NULL DEFAULT '',
			sync_interval INT NOT NULL DEFAULT 1800,
			failure_count INT NOT NULL DEFAULT 0,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY property_id (property_id)
		) {$charset_collate};";
	}

	/** @return list<string> */
	public static function all_sql( string $charset_collate ): array {
		return [
			self::blocks_sql( $charset_collate ),
			self::rates_sql( $charset_collate ),
			self::bookings_sql( $charset_collate ),
			self::ical_feeds_sql( $charset_collate ),
		];
	}
}
