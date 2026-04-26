# Repositories — Changelog

## [0.1.0] — 2026-04-26

### Added
- `AvailabilityRepository` — overlap query, UID upsert, stale-by-source deletion, expired-hold cleanup.
- `RateRepository` — date-window query ordered by priority.
- `BookingRepository` — CRUD + status-filtered queries for the balance scheduler.
- `FeedRepository` — feed registry with success/failure recording.
