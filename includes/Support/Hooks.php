<?php
/**
 * Centralised hook name constants.
 *
 * All public action/filter names live here so integrators can `use` the class
 * and get IDE autocompletion + a single audit point. Slash-style names follow
 * the convention used by Gutenberg / ACF / WC Blocks for plugin-namespaced hooks.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Support;

defined( 'ABSPATH' ) || exit;

final class Hooks {

	public const BOOTED              = 'ibb-rentals/booted';
	public const BOOKING_CREATED     = 'ibb-rentals/booking/created';
	public const BOOKING_CANCELLED   = 'ibb-rentals/booking/cancelled';
	public const QUOTE_COMPUTED      = 'ibb-rentals/quote/computed';
	public const ICAL_BEFORE_EXPORT  = 'ibb-rentals/ical/before_export';
	public const ICAL_AFTER_IMPORT   = 'ibb-rentals/ical/after_import';
	public const CONFLICT_DETECTED   = 'ibb-rentals/conflict/detected';
	public const BALANCE_CHARGED     = 'ibb-rentals/balance/charged';
	public const BALANCE_FAILED      = 'ibb-rentals/balance/failed';

	public const FILTER_QUOTE_BREAKDOWN     = 'ibb-rentals/quote/breakdown';
	public const FILTER_IS_AVAILABLE        = 'ibb-rentals/availability/is_available';
	public const FILTER_ICAL_EXPORT_SUMMARY = 'ibb-rentals/ical/export_summary';

	public const AS_GROUP            = 'ibb-rentals';
	public const AS_IMPORT_FEED      = 'ibb_rentals_import_ical_feed';
	public const AS_CHARGE_BALANCE   = 'ibb_rentals_charge_balance';
	public const AS_SEND_PAYMENT_LINK= 'ibb_rentals_send_payment_link';
	public const AS_SEND_REMINDER    = 'ibb_rentals_send_reminder';
	public const AS_CLEANUP_HOLDS    = 'ibb_rentals_cleanup_holds';
	public const AS_SYNC_CLICKUP     = 'ibb_rentals_sync_clickup';
}
