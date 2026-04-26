# Setup

Activation lifecycle and schema management. Runs once on plugin activation, again on every page load if a migration is pending, and exposes the runtime schema-version state.

## Files

- `Installer.php` — `activate()` / `deactivate()` hooks; queues a rewrite-rule flush via the `ibb_rentals_flush_rewrites` option (it can't flush at activation time, the CPT isn't registered yet); generates the per-site HMAC secret used by iCal export and quote signing; seeds default settings; schedules the recurring `ibb_rentals_cleanup_holds` action.
- `Migrations.php` — versioned, idempotent schema migrations driven by `dbDelta`. Stores the current version in `ibb_rentals_db_version`. Each version is a method (`migrate_to_2`, …); to add a column or table, bump `LATEST_VERSION` and add a method.
- `Schema.php` — the four `CREATE TABLE` statements for `wp_ibb_blocks`, `wp_ibb_rates`, `wp_ibb_bookings`, `wp_ibb_ical_feeds`. Includes the compound indexes the hot-path queries depend on.
- `Requirements.php` — runtime check for PHP / WP / WC versions. Used by the bootstrap before booting `Plugin::instance()`.

## Key patterns

- **Self-healing rewrite flush** — activation sets the `ibb_rentals_flush_rewrites` option flag. On every `init` priority 100, `Installer::maybe_flush_rewrites()` checks the flag *and* the actual `rewrite_rules` option for a `properties/` rule; flushes if either is missing. This handles file-copy installs where the activator never ran.
- **Self-healing migrations** — `Plugin::boot()` checks `Migrations::OPTION_KEY` and runs to latest if behind. So an `wp option delete ibb_rentals_db_version` plus a page load reinstalls the schema without needing deactivate/reactivate.
- **HMAC secret generation** — `Installer::ensure_secret()` writes a 64-hex-char value to `ibb_rentals_token_secret` using `random_bytes(32)`. Falls back to `wp_generate_password` if random_bytes throws.
- **`uninstall.php` is intentionally cautious** — never drops anything unless the admin opted into "Remove all data on uninstall" in Settings. Posts and orders are preserved by default.

## Connects to

- [../Repositories](../Repositories/README.md) — table names come from `Schema::table()`
- [../Plugin.php](../Plugin.php) — boot calls `Migrations::run_to_latest()` and registers the `init` flush hook
- [../PostTypes](../PostTypes/README.md) — activator instantiates `PropertyPostType` so rewrite rules know about the slug

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
