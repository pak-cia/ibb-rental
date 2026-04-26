# Setup — Changelog

## [Unreleased]

### Fixed
- `Installer::maybe_flush_rewrites()` now self-heals: detects missing `properties/` rewrite rule on `init` priority 100 and flushes even without the activation flag.

## [0.1.0] — 2026-04-26

### Added
- `Installer` — activate / deactivate hooks; HMAC secret generation; default settings seeding; recurring `ibb_rentals_cleanup_holds` registration.
- `Migrations` — versioned, idempotent `dbDelta` runner with state in `ibb_rentals_db_version`.
- `Schema` — definitions for `ibb_blocks`, `ibb_rates`, `ibb_bookings`, `ibb_ical_feeds` with compound indexes.
- `Requirements` — runtime PHP / WP / WC version gate.
