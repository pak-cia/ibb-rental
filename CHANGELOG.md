# IBB Rentals — Changelog

All notable changes to this plugin are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

For component-level change history, see each component's `CHANGELOG.md` (linked from the [components table](README.md#components)).

---

## [Unreleased]

---

## [0.3.0] — 2026-04-28

### Added
- **`ibb/property-description` Gutenberg block** — renders the property's `post_content` through `the_content` filters; Property picker in the inspector; `ServerSideRender` edit preview.
- **`PropertyDescriptionTag` Elementor dynamic tag** — IBB › Property Description; bindable to Text Editor and any `text`-category control.
- **Seasonal rates CRUD editor** — Rates tab in the property edit screen now has a full add/edit/delete table for `wp_ibb_rates` rows (From, To, Rate/night, Label, Priority, Weekend uplift, Min stay). Delete-and-reinsert save pattern; form-array inputs consistent with the blackout-ranges editor.
- **`WebhookTopics`** — three WC webhook topics (`ibb_rentals.booking.created`, `.booking.cancelled`, `.balance.charged`) for n8n / Odoo / Make integration via WC's native webhooks screen.

### Fixed
- **`PropertyAvailabilityWidget` legend toggle** — Elementor Switcher returns `''` not `'no'` when off; legend now always respected.
- **Availability calendar width** — calendar now fills its container (`display:block; width:100%`) with fluid Flatpickr internal overrides.
- **Availability calendar month collapse** — `ResizeObserver` + Flatpickr re-init reduces month count to fit container width instead of relying on CSS stacking.
- **`BookingFormWidget` stepper/button border controls** — stepper inner-divider borders and submit-button border are now fully controllable from the Elementor panel.
- **`PropertyCarouselWidget` arrow border** — added `Group_Control_Border` for nav arrow buttons; border type/width/colour now controllable from the Elementor panel.

---

## [0.2.0] — 2026-04-28

### Added

- **Three Gutenberg blocks for page-builder use** — `ibb/booking-form`, `ibb/gallery`, `ibb/property-details`. Server-rendered, share render path with the matching shortcodes, custom *IBB Rentals* block category, edit-time preview via `ServerSideRender`. Inspector controls cover property selection, reactive gallery-slug pickers, columns / image size / lightbox toggle, per-field checkboxes for property details, layout switcher (grid / compact / list).
- **`[ibb_property_details]` shortcode** — standalone property metadata renderer; was previously only available as part of `[ibb_property]`.
- **Distribution build** — `build.sh` produces a clean dist zip via `git archive` (default) or `rsync --exclude-from=.distignore` (`--working` flag). `.distignore` and `.gitattributes` mirror each other to exclude all Memory Palace dev docs, `.claude/`, `docs/`, tests, and dev tooling.
- **Memory Palace docs** — `CLAUDE.md`, root `RUNBOOK.md` / `TROUBLESHOOTING.md`, four-doc set per component under `includes/`, architecture ADR at `docs/architecture.md`. Auto-commit hook in `.claude/settings.json`.

### Changed

- **iCal Feeds admin page** now has a full CRUD form (add + delete + sync-now) instead of a read-only list.
- **Blackout ranges** on the Availability tab replaced the raw JSON textarea with a row-based date-input editor (same native form-array pattern as LOS discounts).
- **LOS discounts** `render_los_row` signature cleaned up — `$is_blank` parameter was unused and removed.

### Fixed

- **Admin calendar AJAX 400** — FullCalendar sends ISO 8601 datetime strings (`2026-03-29T00:00:00+08:00`) but `DateRange::from_strings()` requires `Y-m-d`. Strip to first 10 chars before validation.
- **HPOS violation in `BalanceService`** — retry counter in the balance charge `catch` block used `get_post_meta`/`update_post_meta` on the order ID. Now HPOS-safe via `wc_get_order()` + meta object API.
- **Activation 404s** on property permalinks — `Setup/Installer::maybe_flush_rewrites` now self-heals on `init` if the `properties/` rewrite rule is missing.
- **WC "cannot add another" error** on duplicate add-to-cart — `WC_Product_IBB_Booking::is_sold_individually` returns `false`; quantity is clamped to 1 via filter and reset on merge.
- **Deposit-mode cart price** — `Woo/CartHandler::apply_prices` now uses `deposit_due` when payment mode is `deposit`, instead of the full stay total.
- **Shortcodes in property descriptions** — `Frontend/Shortcodes::render_property` runs `apply_filters('the_content', …)` so embedded `[ibb_gallery]` etc. resolve.
- **Gallery button no-op in Gutenberg** — Photos-tab JS moved to `admin_print_footer_scripts` with polling init.
- **Photos tab CPT field labels** — replaced WP's default Jazz/Bebop example phrasing with property-specific labels.
- **Parse error from inline-class-in-namespaced-file** — `WC_Product_IBB_Booking` lives in its own global-namespace file.
- **iCal feed URL on plain permalinks** — `Ical/Exporter::feed_url` uses `add_query_arg` instead of naive `?token=` concatenation.

---

## [0.1.0] — 2026-04-26

Initial release. Full v1 vacation-rental booking flow.

### Added

- **Plugin skeleton**: bootstrap (`ibb-rentals.php`), hand-rolled PSR-4 autoloader, service container (`Plugin.php`), HPOS + Cart/Checkout-Blocks compat declarations, WC dependency gate.
- **Setup**: `Installer`, `Migrations`, `Requirements`, `Schema`, `uninstall.php` with opt-in data purge.
- **Custom DB schema** (via `dbDelta`): `wp_ibb_blocks`, `wp_ibb_rates`, `wp_ibb_bookings`, `wp_ibb_ical_feeds` with the indexes the overlap and upsert queries need.
- **Custom post type** `ibb_property` + taxonomies (`ibb_amenity`, `ibb_location`, `ibb_property_type`) with property-specific UI labels.
- **Domain layer**: `DateRange` (immutable, half-open, turnover-day-safe), `Block`, `Property`, `Quote` (HMAC-signed cart-handoff token).
- **Repositories**: `AvailabilityRepository` (overlap SQL + UID upsert + stale deletion for iCal sync), `RateRepository`, `BookingRepository`, `FeedRepository`.
- **Services**: `AvailabilityService` (overlap + booking-rule validation), `PricingService` (per-night calc with priority-ranked rate rows, weekend uplift, single-tier LOS, deposit split), `BookingService`, `BalanceService` (gateway-agnostic balance flow).
- **WooCommerce integration**: custom product type `ibb_booking` (global class `WC_Product_IBB_Booking`), `ProductSync` (1:1 hidden mirror, locked against direct edits), `CartHandler` (signed-token quote handoff, deposit-aware pricing, race-safe revalidation), `OrderObserver` (HPOS-safe lifecycle), `GatewayCapabilities` (token-capable vs payment-link routing).
- **Gateway-agnostic deposit + balance flow**: `BalanceService` schedules either `ChargeBalanceJob` (saved-card off-session via `WC_Payment_Tokens`) or `SendPaymentLinkJob` (scheduled email with WC pay-for-order URL) depending on the gateway's capabilities.
- **iCal**: signed export feed (direct + manual blocks only — never re-exports imported events), in-house RFC 5545 `Parser` (DTSTART/DTEND in DATE or DATE-TIME, basic RRULE expansion for DAILY/WEEKLY), `Importer` (conditional GET, transactional upsert by UID, stale deletion), `FeedScheduler`.
- **REST API**: `RouteRegistrar` + thin controllers (`AvailabilityController`, `QuoteController`, `IcalController`, `FeedsController`).
- **Admin**: top-level Rentals menu, tabbed property metabox (Details / Photos / Rates / Booking rules / Availability / iCal), `BookingsListTable`, Settings page, Feeds page.
- **Frontend**: shortcodes (`[ibb_property]`, `[ibb_booking_form]`, `[ibb_gallery]`, `[ibb_search]`, `[ibb_calendar]`), single-property template loader with theme override, Flatpickr date picker, signed-quote booking flow, built-in lightbox.
- **Property galleries**: named sub-galleries per property (e.g. *Bedroom 1*, *Pool*) backed by `wp.media`, `[ibb_gallery]` shortcode (full property or single named gallery).
- **Elementor integration**: gateway-aware dynamic tag in the gallery category for the Gallery widget; gated on `elementor/loaded`.
- **Background jobs** via Action Scheduler (group `ibb-rentals`): `cleanup_holds` (recurring 5m), `import_ical_feed` (per-feed recurring), `charge_balance` and `send_payment_link` (one-shot).
- **Logger** wrapping `wc_get_logger()` with source `ibb-rentals`. Centralised hook-name constants in `Support/Hooks.php`.

---

## Docs

| | |
|--|--|
| [README.md](README.md) | Overview + components table |
| [CLAUDE.md](CLAUDE.md) | Working agreement for Claude Code sessions |
| [RUNBOOK.md](RUNBOOK.md) | Project-level procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Plugin-wide known issues |
