<?php
/**
 * Activation / deactivation lifecycle.
 *
 * Activation runs DB migrations, registers the CPT (so rewrite rules pick up
 * the slug), flushes rewrites, generates a per-site HMAC secret used for
 * signing iCal export URLs, and schedules cleanup jobs.
 *
 * Uninstall (full data purge) lives in the root `uninstall.php`.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Setup;

use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class Installer {

	public const SECRET_OPTION   = 'ibb_rentals_token_secret';
	public const SETTINGS_OPTION = 'ibb_rentals_settings';

	public static function activate(): void {
		Migrations::run_to_latest();

		// Register the CPT immediately so its rewrite rules are present in the
		// rules array, then schedule a flush on the next `init` (when WP has
		// finished registering everything else).
		( new PropertyPostType() )->register_post_type();
		( new PropertyPostType() )->register_taxonomies();
		update_option( 'ibb_rentals_flush_rewrites', 1, false );

		self::ensure_secret();
		self::seed_default_settings();
		self::schedule_recurring_jobs();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
		self::unschedule_recurring_jobs();
	}

	public static function maybe_flush_rewrites(): void {
		if ( get_option( 'ibb_rentals_flush_rewrites' ) ) {
			delete_option( 'ibb_rentals_flush_rewrites' );
			flush_rewrite_rules();
			return;
		}

		// Self-heal: if the stored rewrite rules don't include a rule for our
		// CPT slug, flush. This covers the case where the activator ran with
		// older code (or didn't run at all on a file-copy install).
		$rules = get_option( 'rewrite_rules' );
		if ( ! is_array( $rules ) ) {
			flush_rewrite_rules();
			return;
		}
		$has_property_rule = false;
		foreach ( array_keys( $rules ) as $pattern ) {
			if ( strpos( (string) $pattern, 'properties/' ) !== false ) {
				$has_property_rule = true;
				break;
			}
		}
		if ( ! $has_property_rule ) {
			flush_rewrite_rules();
		}
	}

	private static function ensure_secret(): void {
		if ( get_option( self::SECRET_OPTION ) ) {
			return;
		}
		try {
			$secret = bin2hex( random_bytes( 32 ) );
		} catch ( \Throwable $e ) {
			$secret = wp_generate_password( 64, true, true );
		}
		add_option( self::SECRET_OPTION, $secret, '', false );
	}

	private static function seed_default_settings(): void {
		$existing = get_option( self::SETTINGS_OPTION );
		if ( is_array( $existing ) ) {
			return;
		}
		add_option( self::SETTINGS_OPTION, [
			'default_sync_interval'    => 1800,
			'default_check_in_time'    => '15:00',
			'default_check_out_time'   => '11:00',
			'default_payment_mode'     => 'full',
			'default_deposit_pct'      => 30,
			'default_balance_lead_days'=> 14,
			'cart_hold_minutes'        => 15,
			'log_retention_days'       => 30,
			'uninstall_purge_data'     => false,
		], '', false );
	}

	private static function schedule_recurring_jobs(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		if ( false === as_next_scheduled_action( 'ibb_rentals_cleanup_holds', [], 'ibb-rentals' ) ) {
			as_schedule_recurring_action( time() + 60, 5 * MINUTE_IN_SECONDS, 'ibb_rentals_cleanup_holds', [], 'ibb-rentals' );
		}
	}

	private static function unschedule_recurring_jobs(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( 'ibb_rentals_cleanup_holds', [], 'ibb-rentals' );
	}
}
