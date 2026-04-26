# Repositories

SQL layer over the four custom tables. One class per table, each with a thin set of intent-named methods. The only place in the plugin that calls `$wpdb` directly.

## Files

- `AvailabilityRepository.php` — `wp_ibb_blocks`. Hot-path `any_overlap()` runs on every quote, add-to-cart, and checkout submission — uses the `(property_id, start_date, end_date)` compound index. Also: `find_overlapping`, `find_in_window`, `find_exportable` (direct + manual only), UID upsert, stale-by-source deletion, expired-hold cleanup.
- `RateRepository.php` — `wp_ibb_rates`. `find_for_window()` returns rate rows ordered by priority desc — caller picks first match per night.
- `BookingRepository.php` — `wp_ibb_bookings`. CRUD + status-based queries (e.g. `find_by_status('balance_pending', $on_or_before)` for the balance scheduler).
- `FeedRepository.php` — `wp_ibb_ical_feeds`. `record_success()` / `record_failure()` track polling state.

## Key patterns

- **Intent-named queries** — methods read like English (`any_overlap`, `find_exportable`, `delete_stale_by_source`). Avoid generic `find($criteria)` — it hides the actual SQL the system runs in production.
- **Half-open overlap query** — uses `start_date < %s AND end_date > %s` against the requested checkout/checkin. This matches the `DateRange` semantics and lets turnover days be allowed.
- **UID upsert** — `AvailabilityRepository::upsert_by_uid` does a SELECT-then-INSERT-or-UPDATE keyed on `(property_id, source, external_uid)`. There's a unique index backing the dedup; collisions are race-safe.
- **Domain shape on output** — `find_*` methods that return blocks hydrate via `Block::from_row()`. Methods returning rates/bookings/feeds return raw arrays (no Domain class for them yet — easy to introduce if/when needed).
- **`wpdb` injection for testability** — every repo accepts an optional `$wpdb` in the constructor, defaulting to the global. Tests can swap in a stub.

## Connects to

- [../Setup](../Setup/README.md) — table names come from `Schema::table()`
- [../Domain](../Domain/README.md) — `Block` round-trip via `from_row` / `to_row`
- [../Services](../Services/README.md) — Services depend on Repositories, never the other way round
- [../Cron](../Cron/README.md) — `CleanupHoldsJob` calls `AvailabilityRepository::delete_expired_holds`

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
