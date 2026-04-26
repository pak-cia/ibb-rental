# Ical — Changelog

## [Unreleased]

### Fixed
- `Exporter::feed_url` uses `add_query_arg()` so the URL is correctly formed on plain-permalink sites.

## [0.1.0] — 2026-04-26

### Added
- `Exporter` — RFC 5545 calendar emission with HMAC-signed token, ETag/Last-Modified headers, line folding, `SUMMARY: Reserved` privacy default + `ibb-rentals/ical/export_summary` filter.
- `Parser` — in-house iCal parser supporting DTSTART/DTEND in DATE or DATE-TIME with TZID, UID, SUMMARY, line unfolding, RRULE expansion (FREQ=DAILY/WEEKLY with COUNT/UNTIL/INTERVAL).
- `Importer` — conditional-GET feed polling, transactional UID upsert via `AvailabilityRepository`, stale UID deletion, failure recording.
- `FeedScheduler` — keeps Action Scheduler in sync with the feed registry on `init` priority 99.
