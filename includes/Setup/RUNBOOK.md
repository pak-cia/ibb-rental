# Setup — Runbook

## Add a new DB column or table

1. Add the SQL to `Schema.php` (a new method or extending an existing one). Match `dbDelta` formatting precisely: two spaces between `PRIMARY KEY` and the column, `KEY` before `UNIQUE KEY`, etc.
2. Bump `Migrations::LATEST_VERSION`.
3. Add a `migrate_to_<N>()` method that calls `dbDelta()` with the new SQL. Make it idempotent — safe to run twice.
4. Reload any page on a site running an older `ibb_rentals_db_version` to apply.

## Force re-run all migrations from scratch

```
wp option delete ibb_rentals_db_version
```

Then load any page. Plugin::boot's self-heal calls `Migrations::run_to_latest()`. Existing tables aren't dropped — `dbDelta` is idempotent; it adds missing columns, leaves extras alone.

## Rotate the HMAC secret (invalidates all iCal export feeds)

```
wp option delete ibb_rentals_token_secret
```

Plugin::boot regenerates on next load. Every property's iCal export URL changes; you'll need to re-paste them into Airbnb / Booking.com / etc.

## Reset to a clean state for testing

```
wp option delete ibb_rentals_db_version ibb_rentals_token_secret ibb_rentals_settings ibb_rentals_flush_rewrites
wp db query "DROP TABLE IF EXISTS {$wpdb->prefix}ibb_blocks, {$wpdb->prefix}ibb_rates, {$wpdb->prefix}ibb_bookings, {$wpdb->prefix}ibb_ical_feeds"
```

(Substitute `$wpdb->prefix` with `wp_` etc.) Then reactivate.
