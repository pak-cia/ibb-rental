# Architecture decision record — IBB Rentals v0.1.0

This document is the original planning artifact that drove the v0.1.0 build. It captures the boundary choices, technology selections, data model, integration patterns, phasing, and risks that were agreed before any code was written. Subsequent decisions should be filed against this file or as ADR-style addenda below.

> **Looking for current docs?** Component-level READMEs under `includes/<Component>/` describe the system as it stands today. This file is historical context — *why* the system is shaped the way it is.

---

## Context (as of 2026-04-26)

Standalone WP/Woo plugin (installable on any site running WooCommerce) that handles:

- Direct bookings through the website with live availability and quotes.
- Calendar sync with Airbnb, Booking.com, Agoda, VRBO via iCal (.ics) — universal, works with every OTA, no partner-API approval required.
- Multi-property management on one WP install.
- Pricing: base nightly + seasonal overrides + weekend uplift + length-of-stay discounts + min-nights + cleaning/extra-guest/security fees.
- Payments through WooCommerce in two modes: full payment at booking, or deposit at booking + balance auto-charged X days before check-in.
- Gateway-agnostic — works with whatever WC gateway is installed (Xendit, Stripe, PayPal, etc.). Auto-balance works where the gateway supports stored payment tokens; otherwise a payment-link email is sent.

Target host site: fresh WP + WooCommerce 10.7 on Local-by-Flywheel. Greenfield — no existing rental code.

---

## Architecture summary

**Stack**: PHP 8.1+, WordPress 6.5+, WooCommerce 9.0+, Composer (PSR-4 autoload + optional Mozart-prefixed `sabre/vobject`), Action Scheduler (bundled with WC) for all cron, Flatpickr for the front-end date picker.

### Boundary choices (the WHY)

| Choice | Rationale | Trade-off accepted |
|---|---|---|
| **iCal-only sync** in v1 | Works with every OTA, free, no partner approval; covers ~95% of vacation-rental sync needs | ~30-min lag between OTA bookings and our knowledge of them |
| **Multi-property from day 1** | Designed as a CPT so one install can manage many listings | Slightly more admin UI complexity than a single-property build |
| **Custom DB tables** for blocks/rates/bookings/feeds | Date-range overlap is a hot query; postmeta has no compound indexes | Extra schema-migration responsibility vs postmeta's plug-and-play |
| **1:1 hidden WC product** auto-mirrored from each property | Keeps WC's order/coupon/tax/reporting working without exposing rentals in `/shop` | Two-object identity to keep in sync (handled by `Woo/ProductSync`) |
| **Gateway-agnostic deposit flow** via `WC_Payment_Tokens` + payment-link fallback | Works with any WC gateway, including SE-Asia ones (Xendit) where most flows are one-shot | More code than locking to Stripe; payment-link path is less seamless than auto-charge |
| **HPOS-compatible** declared at boot | Forward-compatible with WooCommerce's roadmap | Every order access must go through `wc_get_order()` (lint-enforced) |
| **Half-open date ranges** `[checkin, checkout)` | Turnover days are NOT overlaps; matches iCal `VALUE=DATE` semantics | Mental model differs from inclusive ranges (worth the consistency) |
| **Action Scheduler not WP-Cron** for jobs | Reliable on low-traffic sites, retries, observable in admin | Requires WC (already a hard dep) |

### Phasing (originally agreed)

**v1.0 — shipped (this release)**
- Plugin skeleton, HPOS, custom tables, migrations.
- CPT + 1:1 hidden product mirror.
- Pricing engine: base + season + weekend + LOS + cleaning + extra guest + min/max nights + blackout.
- Direct booking flow end-to-end: search → quote → add-to-cart → checkout → order → block.
- iCal export (direct + manual only, signed tokens, rotatable).
- iCal import for Airbnb/Booking.com/Agoda/VRBO via Action Scheduler with basic RRULE expansion.
- Both payment modes: full, deposit + balance (gateway-agnostic via WC_Payment_Tokens with payment-link fallback).
- Admin: properties, bookings list, feeds page, settings, tabbed property metabox.
- Frontend: shortcodes, single-property template, Flatpickr date picker, built-in lightbox.
- REST API for properties/availability/quote/ical/feeds.

**v1.1 — deferred**
- Refundable security-deposit holds (Stripe manual capture & equivalents).
- Promo / coupon enhancements beyond native WC coupons.
- Multi-language (WPML / Polylang glue).
- Guest review collection.
- Owner / manager roles (multi-author properties).
- In-site guest ↔ host messaging.
- Admin FullCalendar view across properties (currently per-property only).
- PHPUnit + integration test suite (deferred until manual smoke-testing settles).

**v1.2+ — future**
- Push availability natively to OTAs (channel-manager-style API integrations).
- Dynamic pricing (occupancy-based, days-to-arrival).
- Smart-lock integrations (August, Yale, etc.).

---

## Data model

### Custom post type `ibb_property`

Public CPT with archive at `/properties/`. Taxonomies: `ibb_amenity` (non-hierarchical), `ibb_location` (hierarchical), `ibb_property_type` (hierarchical).

Property-specific config lives in postmeta keys prefixed `_ibb_*`:

`_ibb_max_guests`, `_ibb_bedrooms`, `_ibb_bathrooms`, `_ibb_beds`, `_ibb_address`, `_ibb_lat`, `_ibb_lng`, `_ibb_check_in_time`, `_ibb_check_out_time`, `_ibb_base_rate`, `_ibb_weekend_uplift_pct`, `_ibb_weekend_days`, `_ibb_min_nights`, `_ibb_max_nights`, `_ibb_advance_booking_days`, `_ibb_max_advance_days`, `_ibb_cleaning_fee`, `_ibb_extra_guest_fee`, `_ibb_extra_guest_threshold`, `_ibb_security_deposit`, `_ibb_los_discounts` (JSON), `_ibb_payment_mode` (`full|deposit`), `_ibb_deposit_pct`, `_ibb_balance_due_days_before`, `_ibb_blackout_ranges` (JSON), `_ibb_galleries` (JSON), `_ibb_linked_product_id`, `_ibb_ical_export_token`.

### Custom tables

| Table | Purpose | Key indexes |
|---|---|---|
| `wp_ibb_blocks` | The single source of truth for "this date is unavailable" | `(property_id, start_date, end_date)` for overlap; `UNIQUE (property_id, source, external_uid)` for upsert |
| `wp_ibb_rates` | Seasonal/date-range rate overrides on top of the property's base rate | `(property_id, date_from, date_to)`; ordered by `priority DESC, id DESC` on overlap |
| `wp_ibb_bookings` | First-class booking record (separate from WC order so cancellations/holds remain queryable) | `(property_id, checkin)`, `(order_id)`, `(status, balance_due_date)` |
| `wp_ibb_ical_feeds` | Registry of OTA iCal URLs we poll | `(property_id)` |

For full DDL see `includes/Setup/Schema.php`.

---

## Risks & known limitations

| Risk | Mitigation | Where documented |
|---|---|---|
| iCal sync interval race — a 30-min window where an OTA booking isn't yet in our system | Document; recommend 5-min interval for high-volume properties; perfect sync is impossible without channel-manager APIs | `includes/Ical/TROUBLESHOOTING.md` |
| Off-session balance failure (token gateways) — declined card / SCA | Retry 3× at 24h spacing, then fall back to payment-link email | `includes/Services/TROUBLESHOOTING.md` |
| Xendit-specific: most flows (VA, e-wallet, QRIS) are one-shot, so deposit-mode balance always uses the payment-link path | Documented as expected behaviour, not a bug | `includes/Woo/README.md`, `includes/Services/README.md` |
| DST/timezones | Solved by storing `DATE` only; AS jobs run in site timezone; iCal `VALUE=DATE` events | `includes/Domain/README.md` |
| Theme compatibility | Plugin template fallback + theme override path; CSS scoped under `.ibb-` BEM-style | `includes/Frontend/README.md`, `templates/README.md` |
| HPOS edges — any code path that bypasses `wc_get_order()` will silently break on HPOS sites | Lint check before merging; conventions in CLAUDE.md | `CLAUDE.md`, `includes/Woo/RUNBOOK.md` |
| Currency — WC supports one currency; multi-currency is out of scope | Documented; multi-currency deferred to v1.1+ | This file |
| Page builders (Elementor / Beaver / Bricks) may bypass single-CPT templates | Blocks/widgets and the Elementor dynamic tag are the supported integration paths | `includes/Integrations/README.md` |

---

## External libraries

- **sabre/vobject ^4.5** — optional iCal parser. Mozart-prefixed to `IBB\Rentals\Vendor\Sabre\VObject` to avoid colliding with other plugins shipping the same library. The in-house `Ical/Parser.php` covers the dialect every major OTA actually emits, so this dependency is only needed for exotic feeds.
- **Flatpickr 4.6** — front-end date picker, loaded from CDN (`cdn.jsdelivr.net`). Self-host if your CSP forbids external scripts.
- **Action Scheduler** — already shipped with WC, do not vendor.
- **FullCalendar** (planned for v1.1 admin calendar — not yet integrated).

---

## Testing strategy

**Manual smoke testing (now)** — see `RUNBOOK.md` for the 9-step end-to-end checklist (activate → property → quote → cart → order → balance → cancel).

**Automated (deferred to v1.1)** — PHPUnit + `wp-phpunit` + WC test helpers + `brain/monkey`. Critical scenarios planned:

1. Range overlap (incl. turnover-day allowed)
2. Pricing combinatorics (LOS × weekend × season interactions)
3. iCal round-trip (export then re-import same UID = no double-block)
4. Cancellation releases blocks + unschedules balance
5. `add_to_cart` race (second wins via FOR UPDATE / hold rows)
6. VRBO RRULE expansion
7. HPOS order meta read/write

Fixtures planned in `tests/fixtures/ical/` covering Airbnb, Booking.com, VRBO with RRULE, edge-DST.

---

## Public hooks contract

Integrators can rely on the following actions and filters. All names are constants in `includes/Support/Hooks.php`.

### Actions

| Constant | Hook name | Args | When |
|---|---|---|---|
| `BOOTED` | `ibb-rentals/booted` | `Plugin $plugin` | After `Plugin::boot()` finishes |
| `BOOKING_CREATED` | `ibb-rentals/booking/created` | `int $booking_id, WC_Order $order, WC_Order_Item_Product $item, string $payment_mode` | After a paid order produces a booking |
| `BOOKING_CANCELLED` | `ibb-rentals/booking/cancelled` | `int $booking_id, WC_Order $order, string $reason` | After cancellation/refund/failure |
| `QUOTE_COMPUTED` | `ibb-rentals/quote/computed` | `Quote $quote, Property $property, DateRange $range` | At the end of `PricingService::get_quote` |
| `ICAL_BEFORE_EXPORT` | `ibb-rentals/ical/before_export` | `array $events, int $property_id` | Before serialising the export feed body |
| `ICAL_AFTER_IMPORT` | `ibb-rentals/ical/after_import` | `int $feed_id, int $events_processed` | After a feed import succeeds |
| `BALANCE_CHARGED` | `ibb-rentals/balance/charged` | `int $booking_id, WC_Order $balance_order` | After a successful balance auto-charge |
| `BALANCE_FAILED` | `ibb-rentals/balance/failed` | `int $booking_id, string $error` | After a failed charge attempt |

### Filters

| Constant | Hook name | Args |
|---|---|---|
| `FILTER_QUOTE_BREAKDOWN` | `ibb-rentals/quote/breakdown` | `array $breakdown, Property, DateRange, int $guests` |
| `FILTER_IS_AVAILABLE` | `ibb-rentals/availability/is_available` | `bool $available, int $property_id, DateRange $range` |
| `FILTER_ICAL_EXPORT_SUMMARY` | `ibb-rentals/ical/export_summary` | `string $summary, Block $block` |

Plus `ibb-rentals/gateways/token_capable` (filter `array $gateway_ids`) for adding to the auto-charge allowlist.

---

## Critical files (the 12 that anchor the system)

If you have to grok the plugin in an hour, read these in order:

1. `ibb-rentals.php` — bootstrap, HPOS declaration, WC dependency check
2. `includes/Plugin.php` — service container
3. `includes/Setup/Installer.php` — activate / deactivate, dbDelta
4. `includes/Repositories/AvailabilityRepository.php` — overlap SQL
5. `includes/Services/AvailabilityService.php` — booking-rule validation
6. `includes/Services/PricingService.php` — quote engine
7. `includes/Woo/BookingProductType.php` + `WC_Product_IBB_Booking.php` — custom product type
8. `includes/Woo/CartHandler.php` — quote-token flow
9. `includes/Woo/OrderObserver.php` — order ↔ booking lifecycle
10. `includes/Woo/GatewayCapabilities.php` — gateway-agnostic dispatcher
11. `includes/Ical/Importer.php` + `Exporter.php` — sync engine
12. `includes/Cron/Jobs/ChargeBalanceJob.php` — scheduled balance

---

*This file is reverse-chronological — append future architectural changes below.*

## ADR additions since v0.1.0

*(none yet)*
