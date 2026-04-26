# CLAUDE.md — IBB Rentals

Working agreement for Claude Code sessions in this repository. Read this first.

## Plugin structure

One directory per component under `includes/`. Each contains:

- `README.md` — what it does, key patterns, what it connects to
- `RUNBOOK.md` — how-tos and procedures
- `TROUBLESHOOTING.md` — known issues and fixes
- `CHANGELOG.md` — change history

Root-level docs cover the plugin as a whole. The repo root [`README.md`](README.md) has a table linking to every component README.

The full setup guide for this system (so it can be replicated on other plugin repos) lives at [`docs/MEMORY_PALACE_SETUP_PLUGIN-DEV.md`](docs/MEMORY_PALACE_SETUP_PLUGIN-DEV.md).

## Knowledge filing rules

**Before starting work on a component:**
- Read its `README.md`, `RUNBOOK.md`, and `TROUBLESHOOTING.md` before making changes.
- Note what is empty or stale.

**During work:**
- Any time a pattern is explained, a workaround is found, or a non-obvious decision is made: check if it is already documented. If not, write it to the appropriate file immediately.
- Update stale content when spotted — if a README describes a pattern that has since changed, correct it now, not later.

**Before ending a session:**
- Look back at what was covered: bugs fixed, patterns explained, procedures run, changes made.
- Verify each one is filed. If knowledge was produced verbally, write it down before closing.
- Don't close a session with unfiled knowledge.

**Filing guide:**
- Bug found and fixed → `TROUBLESHOOTING.md` (symptom, root cause, fix)
- Procedure or command explained → `RUNBOOK.md`
- Code change made to a component → that component's `CHANGELOG.md`
- Cross-cutting / multi-component change → root `CHANGELOG.md`
- Do not wait to be asked.

The `.claude/settings.json` hook auto-commits doc edits locally. Pushes follow normal git workflow.

---

## Plugin context

### What this plugin is

A standalone WordPress plugin that turns a WooCommerce store into a vacation-rental booking engine: multi-property listings, calendar sync via iCal with major OTAs, seasonal/LOS pricing, direct bookings with deposit + balance support.

### Key dependencies

- **WordPress 6.5+** — uses `Requires Plugins` header, `_field_description` taxonomy labels.
- **WooCommerce 9.0+** — HPOS declared compatible. We use WC's product type system, cart/checkout filters, Action Scheduler (bundled with WC), `WC_Payment_Tokens` API, and the WC logger.
- **PHP 8.1+** — uses readonly promoted properties, named arguments, enums-style constants, `match` expressions, `??=`, `str_starts_with`.
- **Composer** with PSR-4 autoload + Mozart for vendor namespacing (`sabre/vobject` → `IBB\Rentals\Vendor\Sabre\VObject`). The plugin works without `composer install` thanks to a hand-rolled `Autoloader.php` for our own classes; the in-house `Ical/Parser.php` covers the iCal dialect every major OTA emits, so sabre/vobject is optional unless you encounter exotic feeds.
- **Optional**: Elementor (Pro) — only if `elementor/loaded` fires; the integration is gated on `class_exists`.

### Architectural patterns

- **Service container** — `IBB\Rentals\Plugin::instance()->boot()` lazy-loads each subsystem. Accessors like `$plugin->availability_service()` cache singletons in `$services[]`. Avoid global state; resolve via the container.
- **Hand-rolled PSR-4** in `includes/Autoloader.php` with optional Composer fallback — so the plugin activates cleanly even before `composer install` is run.
- **Domain / Repository / Service layering**:
  - `Domain/` — immutable value objects, no WP or DB.
  - `Repositories/` — `wpdb` access, one class per custom table.
  - `Services/` — business logic, depends on Repositories and Domain.
- **Custom DB tables for hot-path queries** — date-range overlap (`Repositories/AvailabilityRepository::any_overlap`) hits a compound index `(property_id, start_date, end_date)`. Postmeta would be a scan.
- **HPOS-safe** — every order access uses `wc_get_order()` / `$order->get_meta()` / `$item->add_meta_data()`. Never `get_post_meta` on order IDs.
- **Action Scheduler for all background jobs** — never WP-Cron. Group: `ibb-rentals`. Hooks: `ibb_rentals_*` (underscores; AS doesn't accept slashes). All jobs are idempotent and take per-resource locks via `add_option`.
- **Half-open date ranges** — every booking range is `[checkin, checkout)`. Turnover days are not overlaps.
- **Signed quote tokens** — `Domain/Quote::sign($secret)` produces an HMAC-signed token the cart can verify. Token TTL 15 min.
- **Slash-namespaced public hooks** — `ibb-rentals/booking/created`, `ibb-rentals/quote/computed`, etc. Constants live in [`includes/Support/Hooks.php`](includes/Support/Hooks.php).

### Naming conventions (locked, do not rename without coordinated migration)

| | |
|--|--|
| Plugin slug | `ibb-rentals` |
| Namespace | `IBB\Rentals` (vendor: `IBB\Rentals\Vendor\`) |
| Text domain | `ibb-rentals` |
| Custom tables | `{$wpdb->prefix}ibb_blocks`, `_rates`, `_bookings`, `_ical_feeds` |
| Options | `ibb_rentals_*` (e.g. `ibb_rentals_db_version`, `ibb_rentals_token_secret`, `ibb_rentals_settings`) |
| Postmeta | `_ibb_*` (e.g. `_ibb_max_guests`, `_ibb_los_discounts`, `_ibb_galleries`, `_ibb_linked_product_id`) |
| CPT | `ibb_property` |
| Taxonomies | `ibb_amenity`, `ibb_location`, `ibb_property_type` |
| Custom product type | `ibb_booking` (global class `WC_Product_IBB_Booking`) |
| Hooks (filters/actions) | `ibb-rentals/...` (slash style) |
| Action Scheduler hooks | `ibb_rentals_*` (underscores), group `ibb-rentals` |
| REST namespace | `ibb-rentals/v1` |
| CSS prefix | `.ibb-` (BEM-style) |

### Workflow conventions

- **One commit per logical change.** Doc edits auto-commit via the `.claude/settings.json` hook; code changes commit explicitly.
- **Don't push without being asked.** Pushes follow normal PR workflow.
- **HPOS lint**: any `get_post_meta`/`update_post_meta` call against an order ID is a bug. Run a grep before merging.
- **Match-style switches** are preferred over long `if/elseif` chains in domain logic.
- **Comments only when WHY is non-obvious.** File-top docblocks describe the role; inline code is mostly comment-free except where it documents a non-obvious workaround or constraint.

### What lives where (cheatsheet)

| Need to… | Look in |
|---|---|
| Add a new pricing rule | `Services/PricingService.php` |
| Add a booking-rule validation | `Services/AvailabilityService::validate_booking_rules` |
| Add a REST endpoint | `Rest/RouteRegistrar.php` + new controller in `Rest/Controllers/` |
| Add a custom DB column | `Setup/Schema.php` + bump `Migrations::LATEST_VERSION` and add `migrate_to_N` method |
| Add a public hook | `Support/Hooks.php` constant + emit / `apply_filters` at the call site |
| Add a scheduled job | `Cron/Jobs/<Job>.php` + register in `Plugin::boot()` + schedule via `as_schedule_*_action` |
| Add a property meta field | `Domain/Property.php` accessor + `Admin/PropertyMetaboxes.php` UI/save |
| Add an OTA-style integration | `Integrations/<Provider>/...` gated on the provider being loaded |

---

## Monthly compile pass

First of each month: read every component README, RUNBOOK, and TROUBLESHOOTING. Identify gaps, stale claims, and undocumented decisions made over the month. Fill what you can. Don't skip — the system rots without it.
