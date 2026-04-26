# Support — Changelog

## [0.1.0] — 2026-04-26

### Added
- `Hooks` — central constants file for every public action / filter / Action Scheduler hook the plugin emits.
- `Logger` — wraps `wc_get_logger()` with source `ibb-rentals`; falls back to `error_log()` when WC isn't loaded.
