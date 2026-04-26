# Setup — Troubleshooting

## Property URLs return 404 right after activation

**Symptom:** the CPT is registered (Rentals menu visible, /wp-admin works), but `/properties/<slug>/` returns 404.

**Root cause:** WP rewrite rules need to be flushed AFTER the CPT registers. `register_activation_hook` runs before WP's normal `init` cycle, so flushing inside it would flush the wrong ruleset. We can't reliably flush during activation.

**Fix:** `Installer::activate()` writes the `ibb_rentals_flush_rewrites` option. `Plugin::boot()` adds an `init` priority-100 callback (`Installer::maybe_flush_rewrites`) that:
1. Checks the flag — flushes and clears it if set.
2. **Self-heal**: even with no flag, scans `get_option('rewrite_rules')` for a `properties/` pattern; flushes if missing. This catches the file-copy case where the activator never ran.

The flush only fires once a wp-admin page is loaded post-activation. If the user activated and immediately tested the front-end, they'd see the 404 once.

**Manual bypass:** Settings → Permalinks → Save (no edits required) forces a flush from WP's UI.

---

## Activation throws `Class \WC_Product not found`

**Symptom:** activation fatal because `BookingProductType::register()` triggered too early (before WC was loaded).

**Root cause:** activator must not load anything that depends on WC classes. The product class is global-namespace and lives in `Woo/WC_Product_IBB_Booking.php`; loading it from `Setup/Installer` would fail on any site with WC inactive when the activator runs.

**Fix:** the activator only registers the CPT and does DB work — no WC classes. The product type registers later in `Plugin::boot()`, which runs at `plugins_loaded` priority 20 (after WC).

If you see this fatal, check that `Plugin::boot()` is the only place instantiating `BookingProductType` — and that it's gated by the `Requirements::are_met()` check in the bootstrap.

---

## Migrations don't run after pulling a new version

**Symptom:** new column or table missing despite the latest code being installed.

**Root cause:** `Migrations::run_to_latest()` only runs if `ibb_rentals_db_version < LATEST_VERSION`. If you forgot to bump `LATEST_VERSION` after adding a `migrate_to_N` method, nothing runs.

**Fix:** check `Migrations.php`. `LATEST_VERSION` should equal the highest `migrate_to_N` method number. Bump it, then either reload a page (self-heal) or delete `ibb_rentals_db_version` to force a full re-run.
