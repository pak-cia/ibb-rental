# Services

Business logic. Depends on Repositories and Domain; depended on by Rest, Woo, and Cron.

## Files

- `AvailabilityService.php` — `is_available()`, `get_blocked_dates()` (feeds the front-end date picker), `validate_booking_rules()` (min/max nights, advance window, blackout, max guests). The single chokepoint everything else consults before allowing a booking.
- `PricingService.php` — `get_quote()`. Walks each night, picks the highest-priority rate row that covers it (falling back to the property's base rate), applies weekend uplift, then a single LOS discount tier (the longest the stay qualifies for) to the nightly subtotal. Cleaning + extra-guest fees added separately. Splits into deposit/balance for deposit-mode properties.
- `BookingService.php` — turns a paid order into a confirmed booking + block (`create_from_order_item`); tears them down on cancellation/refund. Idempotent via the unique key on `(property_id, source, external_uid)`.
- `BalanceService.php` — schedules and executes the second-instalment charge for deposit-mode bookings. Picks `auto_charge` (saved-card off-session) or `payment_link` (scheduled email) based on `GatewayCapabilities`. Per-booking lock, retry-with-backoff on failures, fallback to payment-link after 3 retries.
- `ClickUpService.php` — syncs guest names from a ClickUp Bookings list into `wp_ibb_blocks.guest_name`. Fetches all tasks via ClickUp API v2 (paginated), reads `task.start_date` / `task.due_date` (ms timestamps) for check-in/check-out, `task.tags[]` for the OTA source, and parses `task.name` ("UnitCode - Guest Name") for both the guest name AND the unit code. Matches to blocks by (start_date, end_date, source) — and additionally scopes the UPDATE by `property_id` when the unit code is mapped via the `clickup_unit_property_map` setting. Also exposes `fetch_workspaces()` / `fetch_spaces()` / `fetch_folders_and_lists()` used by the cascading-dropdown settings UI to let the admin pick a Bookings list without copying IDs out of ClickUp URLs. Instantiated fresh each run; the AJAX endpoints pass an `api_token_override` so the lookup can use a token the user has typed but not yet saved.

## Key patterns

- **Layered dependencies (services depend down, never sideways)** — Services depend on Repositories and Domain. Services don't call other Services directly except where the call is genuinely composing a workflow (e.g. `BalanceService` consults `GatewayCapabilities`, which lives in `Woo/` not `Services/`).
- **Single source of truth for "can I book?"** — every booking path eventually calls `AvailabilityService::is_available`. Don't re-implement overlap detection elsewhere.
- **Fall-back to full payment** — `PricingService::split_payment` forces full payment when the balance due date would be in the past or less than 2 days away. Keeps the system from scheduling impossible balance charges.
- **Idempotent booking creation** — `BookingService::create_from_order_item` short-circuits if a booking row for the same `order_id + order_item_id` already exists. Safe to retry.
- **Per-booking lock for balance flow** — `BalanceService::charge` uses `add_option('ibb_balance_lock_<id>', ..., '', false)` as a poor-man's mutex; if a worker is mid-flight the call returns immediately.
- **HMAC-signed quotes** — the cart can re-verify that the quote it's receiving wasn't tampered with client-side. Token TTL 15 min.
- **Filterable side effects** — emits actions like `ibb-rentals/booking/created`, `ibb-rentals/balance/charged`, `ibb-rentals/balance/failed` so integrators can hook in.

## Connects to

- [../Repositories](../Repositories/README.md) — read/write
- [../Domain](../Domain/README.md) — `DateRange`, `Property`, `Quote`, `Block`
- [../Woo](../Woo/README.md) — `BalanceService` consults `GatewayCapabilities`; `BookingService` is the action-handler-facing surface
- [../Cron](../Cron/README.md) — `ChargeBalanceJob` and `SendPaymentLinkJob` are thin wrappers around `BalanceService` methods

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
