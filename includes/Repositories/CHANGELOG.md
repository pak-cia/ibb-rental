# Repositories — Changelog

## [0.11.0] — 2026-05-03

### Changed
- **`AvailabilityRepository::find_exportable( int $property_id, string $exclude_source = '' )`** — second arg suppresses blocks whose `source` matches it (loop guard for the per-OTA feed exporter). Always excludes `hold`. When `$exclude_source` is empty, returns every confirmed non-hold block (used by admin previews; no longer surfaced as a published feed). Replaces the v0.10.x behaviour of hard-coding `source IN ('direct','manual')`.

## [0.1.0] — 2026-04-26

### Added
- `AvailabilityRepository` — overlap query, UID upsert, stale-by-source deletion, expired-hold cleanup.
- `RateRepository` — date-window query ordered by priority.
- `BookingRepository` — CRUD + status-filtered queries for the balance scheduler.
- `FeedRepository` — feed registry with success/failure recording.
