# Ical

iCal calendar sync — both directions. Each property has a signed export feed URL that OTAs poll, and admins can register inbound feeds from each OTA per property.

## Files

- `Exporter.php` — builds RFC 5545 `BEGIN:VCALENDAR…END:VCALENDAR` for one property. Hand-rolled (the dialect we emit is small and well-defined). Verifies HMAC-signed token before serving. Handles RFC 5545 line folding (75-octet limit).
- `Parser.php` — in-house iCalendar parser covering the dialect every major OTA actually emits: `VEVENT` blocks with DTSTART/DTEND in DATE or DATE-TIME, UID, SUMMARY, basic RRULE (FREQ=DAILY/WEEKLY with COUNT/UNTIL/INTERVAL). Handles line unfolding and timezone normalisation. The `Importer` is built so this parser can be swapped for `sabre/vobject` without touching surrounding code.
- `Importer.php` — pulls one feed: conditional GET via ETag/If-Modified-Since, parses, transactionally upserts by `(property_id, source, external_uid)`, deletes stale UIDs (= cancellations from the OTA's side), records success/failure on the feed row.
- `FeedScheduler.php` — keeps Action Scheduler in sync with `wp_ibb_ical_feeds`. On `init` priority 99, ensures every enabled feed has exactly one recurring `ibb_rentals_import_ical_feed` action with the configured interval.

## Key patterns

- **Export only direct + manual blocks** — never re-export imported events. Two-way looping is the classic vacation-rental footgun (Airbnb pulls our feed, sees a Booking.com block re-exported, double-blocks on UID rewrite). Each OTA maintains its own calendar.
- **Privacy by default** — exported `SUMMARY` is always `Reserved`; never guest names. Filterable via the `ibb-rentals/ical/export_summary` filter for integrators who want richer labels.
- **Signed export URLs** — `Exporter::token_for($id)` is `hash_hmac('sha256', "ical:{id}", $secret)`. Bad/missing token → 404 (not 401), so the endpoint doesn't leak which property IDs exist.
- **Conditional GET on import** — `Importer::import` sends `If-None-Match` / `If-Modified-Since` on every poll. 304 is a success path, no body parsing.
- **Transactional UID upsert** — `AvailabilityRepository::upsert_by_uid` ensures imports are idempotent. Stale UIDs (no longer in the feed) are deleted via `delete_stale_by_source`.
- **Failure backoff** — `FeedRepository::record_failure` increments a counter; after consecutive failures the polling interval can be widened (currently a TODO — backoff logic lives at the FeedScheduler layer).

## Connects to

- [../Repositories](../Repositories/README.md) — `AvailabilityRepository` (upsert/delete blocks); `FeedRepository` (per-feed state)
- [../Cron](../Cron/README.md) — `ImportFeedJob` is the AS-fired wrapper around `Importer::import`
- [../Rest](../Rest/README.md) — `IcalController` serves the export feed; `FeedsController` does feed CRUD + sync-now
- [../Admin](../Admin/README.md) — Property iCal tab shows export URL and import-feeds list

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
