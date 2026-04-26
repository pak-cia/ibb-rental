# Domain — Changelog

## [Unreleased]

### Added
- `Property::galleries()` / `gallery($slug)` / `all_attachments()` — accessors over the `_ibb_galleries` JSON postmeta.

## [0.1.0] — 2026-04-26

### Added
- `DateRange` — half-open immutable range with overlap, contains, each_night, equals.
- `Block` — DB-row wrapper with source/status enum constants and `from_row` / `to_row`.
- `Property` — typed accessors over postmeta with defaults; HMAC-derived per-property iCal export token.
- `Quote` — `to_array()` + HMAC `sign()` / `verify_token()` for cart hand-off.
