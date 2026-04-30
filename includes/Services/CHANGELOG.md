# Services — Changelog

## [Unreleased]

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
