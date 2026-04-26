# Support

Cross-cutting helpers used by every other component. Anything that doesn't have a natural home elsewhere lives here, but resist the urge to make this a junk drawer.

## Files

- `Hooks.php` — string constants for every public action / filter / Action Scheduler hook the plugin emits. Lets integrators `use` the class for IDE autocomplete + a single audit point if names ever change.
- `Logger.php` — thin wrapper around `wc_get_logger()` with a fixed source `ibb-rentals`. Falls back to `error_log()` when WC's logger isn't available (e.g. during early bootstrap).

## Key patterns

- **All hook names live here** — when adding a new `do_action` or `apply_filters` call anywhere in the plugin, add a constant to `Hooks` first and reference it. Lets a grep for "what hooks does this plugin emit?" complete in one file.
- **Slash-style for plugin-namespaced hooks** — `ibb-rentals/booking/created`. Underscore-style for AS hooks (`ibb_rentals_*`) because AS internally normalises hook names.
- **Logger output goes to WC log directory** — view at WooCommerce → Status → Logs, filter by source `ibb-rentals`.

## Connects to

- Used by every other component. No reverse dependencies — this layer doesn't `use` other components.

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
