# Services — Changelog

## [Unreleased]

---

## [0.11.4] — 2026-05-03

### Added
- **`AvailabilityService::blackout_to_range()`** — private helper that converts an inclusive `{start, end}` blackout postmeta entry into a half-open `DateRange` by adding one day to the stored end. Lets the existing `overlaps()` / `each_night()` machinery work against the inclusive admin-input convention without introducing a second range type. Returns `null` on invalid date strings (silent skip — same behaviour as the previous inline try/catch).

### Changed
- **Both blackout consumption sites** (`get_blocked_dates()` and `validate_booking_rules()`) now go through `blackout_to_range()` instead of constructing `DateRange::from_strings($start, $end)` directly. Net effect: a blackout entered as "May 1 → May 7" blocks May 1, 2, 3, 4, 5, 6, 7 — was May 1–6 under the old half-open interpretation.
- **`PricingService::rate_for_night()`** unchanged — was already inclusive (`date_from <= ymd && date_to >= ymd`) so storage and consumption already matched. Only the admin UI label needed clarifying.

---

## [0.11.6] — 2026-05-14

### Fixed
- **`ClickUpService::sync()` gained a phase 0 self-heal** at the top of each task iteration. Unconditionally `UPDATE`s any existing `clickup:<task_id>` row with current ClickUp `start_date` / `end_date` / `guest_name`. Decouples the canonical row's freshness from whether strategies 1/2/3 happen to match an enrichment target — strategy 2's `continue` no longer locks out the only path that previously kept these dates current.
- **Post-sync cleanup pass**: `DELETE` joined on `clickup_task_id` runs once after the main loop. Drops iCal-side rows that share a non-empty `clickup_task_id` with the canonical `clickup:%` row, regardless of date drift. Counter is added to the log line and exposed via the sync-status pill.

---

## [0.11.2] — 2026-05-03

### Added
- **`ClickUpService::__construct()` accepts `array $sync_statuses = []`** — list of ClickUp status names to whitelist on fetch. Empty list = no filter. `fetch_all_tasks()` adds the values as `statuses[]` query params to `GET /list/<id>/task`, so non-matching tasks are skipped at the API level rather than paged and dropped client-side. Default values come from the `clickup_sync_statuses` setting and target the user's workflow (`upcoming, currently staying, checked out, cancelled`).

---

## [0.11.0] — 2026-05-03

### Changed
- **`BookingService::create_from_order_item()` writes `Block::SOURCE_WEB`** (was `SOURCE_DIRECT`). `direct` is now reserved for walk-in / phone bookings entered manually.
- **`ClickUpService` constructor accepts `array $create_sources = []`** — list of source slugs for which the sync may auto-INSERT a block when no existing match is found. Empty list = enrichment-only (v0.10.x behaviour preserved when no allowlist is configured).
- **`ClickUpService::sync()` now has three strategies**: (1) match by Booking ID against `external_uid` (existing); (2) date-tuple fallback with property + source disambiguation (existing); (3) **new** — auto-create a block when no match exists, the source is in `$create_sources`, and we have property + dates + source + task_id. Inserts use `external_uid='clickup:<task_id>'`. Idempotent across AS retries and re-runs (existing rows are updated in place; new INSERTs are guarded by an existence check).
- **`ClickUpService::task_is_cancelled()`** — reads `task.status.status` and tag list; matched ClickUp-created blocks (those with `external_uid='clickup:<id>'`) flip to `status='cancelled'` so the per-OTA feed exporter drops them. Never touches iCal-imported blocks owned by other OTAs.
- **`ClickUpService::record_status()`** gained `created` and `cancelled` counts so the Settings sync-status pill can show "X created, Y updated, Z cancelled" instead of a single updated total.

---

## [0.10.1] — 2026-05-01

### Fixed
- **`PricingService::compute_tax()` returned empty `tax_breakdown`** when called from the public `/quote` REST endpoint despite tax classes being correctly configured. `WC_Tax::find_rates([ 'tax_class' => ... ])` requires at minimum a country code; without an active customer session it resolves to empty defaults and returns no rates. Switched the call to `WC_Tax::get_base_tax_rates( $wc_class )` which uses `wc_get_base_location()` — guaranteed to return a country in any context.

---

## [0.10.0] — 2026-05-01

### Added
- **`PricingService::compute_tax()`** — resolves each component's IBB tax class (`''` / `'standard'` / slug) to the matching WC tax class, looks up rates via `WC_Tax::find_rates([ 'tax_class' => ... ])`, computes per-rate amounts via `WC_Tax::calc_tax( $amount, $rates, false )`, and aggregates results bucketed by rate-id. Returns `[ list<{label,rate_id,amount}>, total_tax_float ]`. Components with empty tax-class slugs or zero amounts are skipped (no work, no rate lookup). Falls back to `[ [], 0.0 ]` when `WC_Tax` is unavailable, so quotes still compute cleanly on a non-WC test harness.

### Changed
- **`PricingService::get_quote()` now computes tax per component** (accommodation, cleaning, extra-guest), each with its own IBB tax class. `Quote` is constructed with the new `tax_breakdown`, `tax_total`, `grand_total`, `accommodation_tax_class`, `cleaning_tax_class`, and `extra_guest_tax_class` fields populated.
- **`split_payment()` now operates on `grand_total` (post-tax) rather than `total` (pre-tax).** Deposit and balance amounts therefore include their proportional share of tax, so the gateway's charge-today figure matches the all-in figure the guest agreed to. For untaxed properties this is a no-op (grand_total === total).

---

## [0.6.0] — 2026-04-30

### Added
- **`ClickUpService` writes `source_override`** alongside `guest_name` and `clickup_task_id` on every match (both Booking-ID and date-tuple strategies). `Block::effective_source()` reads this column and is the canonical source for calendar display, so a manual-blackout block on Airbnb that ClickUp says is actually `agoda` shows orange (Agoda) instead of red (Airbnb) on the calendar timeline.

### Fixed
- **Match strategy no longer requires source equality when the property is identified.** When a task's unit code maps to an IBB property, the date-tuple fallback drops the `source` column from its WHERE clause: a property can't have two simultaneous bookings, so `(property_id, start_date, end_date)` is unique. Handles the workflow where a non-Airbnb booking (Agoda direct, true direct, etc.) is manually blocked on Airbnb and the Airbnb iCal feed re-imports it as `source='airbnb'`. With unit-code mapping configured, those blocks now correctly receive their guest names.

---

## [0.5.0] — 2026-04-30

### Added
- **`ClickUpService` Booking-ID match strategy** — primary match by parsing `Booking ID` from the task description's `[table-embed:row:col]` format and matching `wp_ibb_blocks.external_uid LIKE %code%`. More durable than dates: survives stay extensions, source typos, cross-property collisions. Falls back to date-tuple match when no booking ID is found or no UID matches. Sync log emits the breakdown: `(uid match: N, date-tuple fallback: M)`.
- **`ClickUpService` last-sync status persistence** — writes `ibb_rentals_clickup_status` option (`last_sync_at`, `updated`, `total_tasks`, `error`) at end of every run. Read by Settings page to render a status pill ("Last sync 12 minutes ago — updated 9 of 370 tasks").
- **`ClickUpService` hierarchy fetchers** — `fetch_workspaces()`, `fetch_spaces($workspace_id)`, `fetch_folders_and_lists($space_id)` for the cascading-dropdown settings UI. Each call hits ClickUp REST API v2 directly with the configured (or AJAX-overridden) token.
- **Per-task unit-code → property scoping.** `extract_property_id()` parses the prefix of the task title (everything before " - ") and looks it up in the configured `clickup_unit_property_map` (built from per-property text inputs in Settings). When matched, the sync UPDATE is scoped to that property — eliminates cross-property collisions.

---

## [0.4.0] — 2026-04-30

### Added
- **New: `ClickUpService`** — syncs guest names from a ClickUp Bookings list into `wp_ibb_blocks.guest_name`. Fetches all tasks via ClickUp API v2 (paginated), reads `task.start_date` / `task.due_date` for check-in/check-out, `task.tags[]` for OTA source, parses `task.name` ("UnitCode - Guest Name") for the guest. Uses `wp_timezone()` (not `gmdate`) for date conversion so it agrees with iCal-imported block dates in the property-local timezone. Instantiated fresh each run; the AJAX endpoints pass an `api_token_override` so the Settings cascading-dropdown UI can do hierarchy lookups before the token is saved.

---

## [0.3.5] — 2026-04-28

### Fixed
- **`get_blocked_dates()` missing blackout ranges** — `AvailabilityService::get_blocked_dates()` now loads the property and expands `_ibb_blackout_ranges` into the returned blocked-dates array. Previously blackout ranges were only validated at quote time (in `validate_booking_rules()`), so the front-end date picker never greyed them out.
- **HPOS violation in `BalanceService::charge()`** — retry-counter reads/writes in the `catch` block used `get_post_meta`/`update_post_meta` on the order ID. Replaced with `wc_get_order()` + `$order->get_meta()` / `$order->update_meta_data()` + `$order->save()`.

## [0.1.0] — 2026-04-26

### Added
- `AvailabilityService` — `is_available`, `get_blocked_dates`, `validate_booking_rules` (min/max nights, advance window, max guests, blackouts, availability). Filterable via `ibb-rentals/availability/is_available`.
- `PricingService` — per-night calc with priority-ranked rates, weekend uplift, single-tier LOS discount, fees, security deposit (informational), deposit/balance split with auto-fallback to full payment.
- `BookingService` — `create_from_order_item` (idempotent via UID upsert), `cancel_for_order`, `refund_for_order`. Emits `ibb-rentals/booking/created` and `ibb-rentals/booking/cancelled`.
- `BalanceService` — `schedule_for_booking` picks `auto_charge` vs `payment_link` based on gateway capabilities; `charge` runs the off-session payment with per-booking lock, 3 retries at 24h spacing, fallback to payment-link after retries; `send_link` for the email path.
