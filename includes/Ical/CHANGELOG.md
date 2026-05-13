# Ical — Changelog

## [Unreleased]

### Fixed
- `Exporter::feed_url` uses `add_query_arg()` so the URL is correctly formed on plain-permalink sites.

## [0.11.1] — 2026-05-03

### Added
- **`Exporter::build()` composes SUMMARY + DESCRIPTION per block from real data.** SUMMARY: `"<guest_name> (<Source>)"` when names are enabled and present, otherwise `"<Source> booking"`. DESCRIPTION: property title, nights, source, optional guest name, and a ClickUp deep-link (`https://app.clickup.com/t/<task_id>`) whenever the block has a `clickup_task_id`. Replaces v0.10's static `"Reserved"`.
- **`Exporter::source_label( string $slug )`** — branded labels for the source slug (e.g. `booking → "Booking.com"`, `vrbo → "VRBO"`). `match` expression with explicit cases so casing/branding is centralised.

### Changed
- `Hooks::FILTER_ICAL_EXPORT_SUMMARY` is now applied **after** the v0.11.1 default summary is composed — same hook contract for integrators, but the default is no longer `'Reserved'`.

---

## [0.11.5] — 2026-05-14

### Fixed
- **`Importer::import()` skips events whose dates overlap an existing ClickUp-sourced block** on the same property (via `AvailabilityRepository::has_clickup_overlap()`). Stops the iCal importer from re-creating the host's pre-v0.11.0 manual-blackout-on-Airbnb mirror once ClickUp owns the truth for the booking. Skipped events are tallied and logged once per import.

---

## [0.11.0] — 2026-05-03

### Changed
- **Hub-and-spoke export topology.** `Exporter::build( $property_id, $for_ota = '' )` now scopes the feed to a specific OTA — `$for_ota` is one of `Block::OTA_SOURCES`. The exporter calls `AvailabilityRepository::find_exportable( $property_id, $for_ota )` which returns every confirmed block except those whose `source` matches `$for_ota` (loop guard) and except `hold`. Every other source — including ClickUp-created `agoda` / `vrbo` blocks for OTAs without iCal feeds — flows through.
- `feed_url( $property_id, $for_ota )`, `token_for( $property_id, $for_ota )`, `verify_token( $property_id, $for_ota, $token )` — all gained the per-OTA arg. Tokens are namespaced `ical:<id>:<ota>` so rotating one OTA's feed doesn't invalidate the others.
- New `feed_urls( int $property_id ): array<string,string>` returning an OTA-keyed map of feed URLs — used by `Admin/PropertyMetaboxes` to render the iCal tab's one-row-per-OTA table.
- `compute_etag( $property_id, $for_ota = '' )` — etag varies per feed because their bodies differ.

### Removed
- The legacy combined feed URL `/ical/<property_id>.ics?token=…` is gone — `Rest/Controllers/IcalController` only registers `/ical/<id>/<ota>.ics` now. Hard switch.

## [0.1.0] — 2026-04-26

### Added
- `Exporter` — RFC 5545 calendar emission with HMAC-signed token, ETag/Last-Modified headers, line folding, `SUMMARY: Reserved` privacy default + `ibb-rentals/ical/export_summary` filter.
- `Parser` — in-house iCal parser supporting DTSTART/DTEND in DATE or DATE-TIME with TZID, UID, SUMMARY, line unfolding, RRULE expansion (FREQ=DAILY/WEEKLY with COUNT/UNTIL/INTERVAL).
- `Importer` — conditional-GET feed polling, transactional UID upsert via `AvailabilityRepository`, stale UID deletion, failure recording.
- `FeedScheduler` — keeps Action Scheduler in sync with the feed registry on `init` priority 99.
