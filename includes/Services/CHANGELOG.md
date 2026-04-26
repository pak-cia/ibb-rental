# Services — Changelog

## [0.1.0] — 2026-04-26

### Added
- `AvailabilityService` — `is_available`, `get_blocked_dates`, `validate_booking_rules` (min/max nights, advance window, max guests, blackouts, availability). Filterable via `ibb-rentals/availability/is_available`.
- `PricingService` — per-night calc with priority-ranked rates, weekend uplift, single-tier LOS discount, fees, security deposit (informational), deposit/balance split with auto-fallback to full payment.
- `BookingService` — `create_from_order_item` (idempotent via UID upsert), `cancel_for_order`, `refund_for_order`. Emits `ibb-rentals/booking/created` and `ibb-rentals/booking/cancelled`.
- `BalanceService` — `schedule_for_booking` picks `auto_charge` vs `payment_link` based on gateway capabilities; `charge` runs the off-session payment with per-booking lock, 3 retries at 24h spacing, fallback to payment-link after retries; `send_link` for the email path.
