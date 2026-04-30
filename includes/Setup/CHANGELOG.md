# Setup — Changelog

## [Unreleased]

---

## [0.6.0] — 2026-04-30

### Added
- **Migration v4** — adds `wp_ibb_blocks.source_override VARCHAR(32)` column. Owned by `Services/ClickUpService`; takes display precedence over `source` (which iCal imports keep overwriting), so a manual-blackout block on Airbnb that's actually an Agoda or direct booking per ClickUp shows the right OTA color and label in the calendar.

---

## [0.5.0] — 2026-04-30

### Added
- **Migration v3** — adds `wp_ibb_blocks.clickup_task_id VARCHAR(64)` column. Used for the "View ClickUp task →" deep-link in the calendar detail modal and for the source-override link.

---

## [0.4.0] — 2026-04-30

### Added
- **Migration v2** — adds `wp_ibb_blocks.guest_name VARCHAR(255)` column for guest names sourced from `wp_ibb_bookings` (direct) or ClickUp sync (OTA blocks).

---

## [0.3.5] — 2026-04-28

### Fixed
- `Installer::maybe_flush_rewrites()` now self-heals: detects missing `properties/` rewrite rule on `init` priority 100 and flushes even without the activation flag.

## [0.1.0] — 2026-04-26

### Added
- `Installer` — activate / deactivate hooks; HMAC secret generation; default settings seeding; recurring `ibb_rentals_cleanup_holds` registration.
- `Migrations` — versioned, idempotent `dbDelta` runner with state in `ibb_rentals_db_version`.
- `Schema` — definitions for `ibb_blocks`, `ibb_rates`, `ibb_bookings`, `ibb_ical_feeds` with compound indexes.
- `Requirements` — runtime PHP / WP / WC version gate.
