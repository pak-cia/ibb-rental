# IBB Rentals — Changelog

All notable changes to this plugin are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

For component-level change history, see each component's `CHANGELOG.md` (linked from the [components table](README.md#components)).

---

## [Unreleased]

---

## [0.8.1] — 2026-04-30

### Fixed
- **Critical error opening WooCommerce → Settings → Emails.** `OrderObserver::suppress_for_ibb_order()` was strictly typed `\WC_Order $order` (non-nullable). WC's settings screen iterates every registered email and calls `WC_Email::is_enabled()`, which runs the `woocommerce_email_enabled_<id>` filter chain with `$order = null` (no order context). PHP fataled with `TypeError: Argument #2 ($order) must be of type WC_Order, null given`. Callback now accepts a nullable `$order` and short-circuits returning the original `$enabled` when null — the function only inspects order line items, so with no order it cannot possibly contain `_ibb_property_id` and the WC default behaviour is correct. Bug existed in 0.4.0+ but only surfaced when someone opened the settings page; no impact on actual order-status email suppression.

---

## [0.8.0] — 2026-04-30

### Added
- **Rich-text editor for the email "Additional content" field.** New `WpEditorFieldTrait` registers a custom `'type' => 'wp_editor'` form-field handler on `WC_Email` subclasses that use it. `BookingConfirmationEmail` and `BookingReminderEmail` now apply the trait, replacing their plain textarea with a full TinyMCE editor (Add Media button, formatting, lists, links, alignment, Visual/Code tabs). Saved values pass through `wp_kses_post`, the same sanitisation WP applies to post content.
- **Trait location:** `includes/Emails/WpEditorFieldTrait.php`. Add `use WpEditorFieldTrait;` to any future `WC_Email` subclass to inherit the same capability.

### Notes
- For full visual control over the entire email layout (logo, header colors, footer styling), install Kadence WooCommerce Email Designer (free) or similar. Such plugins detect any email registered via `woocommerce_email_classes` and wrap it with their own customizer — our IBB emails appear in their picker automatically without further work.

---

## [0.7.0] — 2026-04-30

### Added
- **Editable email settings via WC's standard admin UI** for both `BookingConfirmationEmail` and `BookingReminderEmail`. Each class now overrides `init_form_fields()` to expose: `enabled` (checkbox), `subject` (with placeholder hint), `heading`, `additional_content` (textarea — appended before the email footer, replaces the previously-hardcoded "we look forward to welcoming you" line), `reply_to_email`, and `email_type` (HTML / plain / multipart). Site owners can now customize subject / heading / closing copy at **Settings → Emails → IBB Booking Confirmation** without touching PHP.
- **Per-email Reply-To override.** Both email classes override `WC_Email::get_headers()` to use the configured `reply_to_email` setting when set (falls back to WC default otherwise). Set `hello@yourdomain.com` so guest replies route to a customer-service inbox instead of the WooCommerce store admin email. WC's default behaviour set Reply-To to the admin email because our `$this->object` is a booking array, not a `WC_Order` — the override fixes that.
- **Namespaced theme override path.** Both email classes now pass `template_path = 'ibb-rentals/'` to `wc_get_template_html()`, so a theme can ship a customized template at `your-theme/ibb-rentals/emails/booking-confirmation.php` (or `booking-reminder.php`) without colliding with WooCommerce's own `your-theme/woocommerce/emails/...` overrides.

### Changed
- **Templates render the admin-editable Additional content block.** Both HTML and plain-text templates now print `$email->get_additional_content()` (with a sensible default fallback) where the hardcoded paragraph used to live. The default text matches the previous wording for behaviour parity on first install.

---

## [0.6.0] — 2026-04-30

### Added
- **ClickUp source override.** New `wp_ibb_blocks.source_override VARCHAR(32)` column (migration v4) owned by the ClickUp sync. `ClickUpService::sync()` writes the source derived from the task's tag onto every match (both Booking-ID and date-tuple strategies). `Block::effective_source()` returns `source_override ?: source` and is used by `AdminCalendar::ajax_events()` for the bar color, source label, source filter, and the `source` field in `extendedProps`. The raw iCal-import source is still exposed as `extendedProps.raw_source` for diagnostics.

  **Why this matters:** when the operator manually blocks dates on Airbnb to prevent double-booking for a direct or non-Airbnb OTA reservation, Airbnb's iCal feed re-imports the block as `source='airbnb'` "Airbnb (Not available)". Pre-0.6.0 the calendar painted those blocks red (Airbnb) even though ClickUp correctly tagged them `direct` / `agoda` / etc. Now the calendar follows ClickUp.

  iCal imports never touch `source_override`, so this column is stable across iCal poll cycles. `Block::source` still reflects the iCal-import truth (used by exporter, repository, etc.); only display uses `effective_source()`.

---

## [0.5.0] — 2026-04-30

### Added
- **Cascading workspace → space → folder → list dropdowns on the Settings page.** Replaces the bare "list ID" text input with 4-level pickers driven by 3 AJAX hierarchy endpoints (`ibb_rentals_clickup_workspaces` / `_spaces` / `_folders`). Multi-workspace tokens are supported: chosen workspace_id, space_id, folder_id, and list_id are all persisted so the cascade restores cleanly on reload.
- **Per-property unit-code mapper.** A row-per-property table on the Settings page where the admin enters comma-separated unit codes per IBB property (e.g. `v1, 1` → Villa 1). The sync parses the prefix of each ClickUp task title (everything before " - ") and, when mapped, scopes the guest-name UPDATE to that property only — eliminates cross-property collisions, and is what makes the "match across source mismatches" case below work.
- **"View ClickUp task →" button in the calendar detail modal.** The ClickUp sync now records the ClickUp task ID alongside the guest name (`wp_ibb_blocks.clickup_task_id` column, added by migration v3). When you click a calendar bar that's been matched to a ClickUp task, the detail modal renders a button that opens the task in a new tab (`https://app.clickup.com/t/{task_id}`). Hidden for blocks with no ClickUp match.
- **ClickUp sync status pill on the Settings page.** Each sync run now writes outcome metadata to the `ibb_rentals_clickup_status` option (last_sync_at, updated, total_tasks, error). The Settings page renders it next to the "Sync now" button: green for success ("✓ Last sync 12 minutes ago — updated 9 blocks from 370 tasks"), red for failure ("✗ Last sync failed 3 minutes ago: HTTP 401"). Saves a trip through WC → Status → Logs / Scheduled Actions to confirm the sync is healthy.
- **Booking-ID match strategy added to `ClickUpService::sync()` (with date-tuple fallback).** The sync now tries to match each task by its `Booking ID` (parsed from the description's `[table-embed:row:col]` format) against `wp_ibb_blocks.external_uid LIKE %code%` — durable across stay extensions, source typos, and cross-property collisions. Falls back to the (start_date, end_date [+ property + source]) match when no booking ID is found or no UID matches. The sync log now shows the breakdown: `(uid match: N, date-tuple fallback: M)`. Note: Airbnb deliberately scrambles iCal UIDs (`hash@airbnb.com`), so this strategy never matches Airbnb tasks; the fallback handles them. Booking.com / Agoda / VRBO / Expedia typically embed the reservation ID and benefit.

### Changed
- **Match strategy no longer requires source equality when the property is identified.** When a task's unit code maps to an IBB property, the date-tuple fallback drops the `source` column from its WHERE clause: a property can't have two simultaneous bookings, so `(property_id, start_date, end_date)` is unique. This handles the operator workflow where a non-Airbnb booking (Agoda direct, true direct, etc.) is manually blocked on Airbnb to prevent double-booking, and the Airbnb iCal feed re-imports it back as `source='airbnb'` "Airbnb (Not available)". With unit-code mapping configured, those blocks now correctly receive their guest names from the matching ClickUp task. When the unit code isn't mapped, source is still required as a disambiguator.

### Fixed
- **ClickUp sync matched 0 blocks despite running and pulling tasks correctly.** `ms_to_date()` was using `gmdate()` (UTC), but ClickUp stores task dates at the user-entered moment in the workspace's local timezone — for a UTC+8 site, "Apr 30" comes back as `Apr 29 20:00 UTC` and rolled back a day. Now converts via `wp_timezone()` so the resulting `Y-m-d` matches the iCal-imported block dates (which are already in property-local time).
- **Timeline view clipped the right-side day cells on narrow viewports.** The cell container used `flex:1`, so when the row's available width fell below `daysInMonth * 32px`, the container shrank and `overflow:hidden` cut off the trailing day cells (and their bars). Now the cell container has explicit `width = daysInMonth * DAY_W` with `flex-shrink:0`, so it always renders the full month and the outer wrapper's `overflow-x:auto` provides a horizontal scrollbar instead.

---

## [0.4.0] — 2026-04-30

### Added
- **ClickUp integration** — `Services/ClickUpService` + `Cron/Jobs/SyncClickUpJob` sync guest names from a ClickUp Bookings list into `wp_ibb_blocks.guest_name`. Matching is by (check-in date, check-out date, OTA source tag). Guest names then appear on timeline bars and in detail modals instead of just the OTA label. DB migration v2 adds `guest_name VARCHAR(255)` column to `wp_ibb_blocks`.
- **ClickUp cascading-dropdown settings UI** — settings page now drives a workspace → space → folder → list selector via 3 AJAX hierarchy endpoints (`ibb_rentals_clickup_workspaces` / `_spaces` / `_folders`). Multi-workspace tokens are supported: the chosen `workspace_id` is persisted alongside the list ID. Replaces the previous bare "list ID" text input plus three custom-field-name inputs (no longer needed — see "Changed" below).
- **ClickUp unit-code → property mapper.** Settings page now lists every IBB property with a "ClickUp unit code(s)" text input next to it (comma-separated, case-insensitive). The sync parses the prefix of the task title (everything before " - ", e.g. `v1` from `v1 - Bob Jones`) and, when mapped, scopes the guest-name UPDATE to that property — eliminates cross-property collisions when two villas share the same OTA + dates. Falls back to the (start_date, end_date, source) match alone when no mapping is configured for a task's unit code.

### Changed
- **ClickUp data extraction now uses task-level fields directly.** Earlier draft expected `Guest Name` / `Check-in Date` / `Check-out Date` ClickUp custom fields. Real Bookings tasks store these on the task itself: `task.start_date` and `task.due_date` (ms timestamps) for check-in/out, `task.tags` for OTA source, and `task.name` ("RoomCode - Guest Name") for the guest. `ClickUpService::extract_*` rewritten accordingly; the three field-name settings (`clickup_guest_name_field` / `_checkin_field` / `_checkout_field`) are dropped on save.
- **Admin calendar timeline view** — Month / Week / Timeline toolbar buttons on the Availability Calendar page. Timeline renders a custom div-grid (one property per row, one day per column) with colored booking bars, sticky property-name column, today/weekend highlights, Prev / Today / Next month navigation, and "+ Block dates" button. Reuses the existing `ibb_rentals_calendar_events` AJAX endpoint and the existing create/delete/detail modals; no new server-side code. Addresses v1.1 roadmap item "Admin FullCalendar view — multi-property timeline-style calendar".

### Verified
- **End-to-end booking smoke test on staging (2026-04-30)** — confirmed on staging.theuluhills.com with Xendit gateway in TEST MODE:
  - Quote → cart → Xendit hosted invoice → order-received page: works end-to-end.
  - `OrderObserver` fires on `wc-processing` status transition: `wp_ibb_bookings` row created with correct property ID, dates, guests, total, and status=Confirmed.
  - Order cancellation: booking status flips to Cancelled; `GET /wp-json/ibb-rentals/v1/availability` no longer returns the cancelled dates as blocked — calendar is released immediately.
  - Known caveat: in Xendit TEST MODE the webhook callback may not reach the staging URL, so the order stays "Pending payment" until manually advanced. See RUNBOOK — "Xendit test-mode webhook". Production behaviour (real account + webhook) is automatic.

---

## [0.3.5] — 2026-04-28

### Fixed
- **Booking confirmation email not delivered** — `BookingConfirmationEmail` registers its `ibb-rentals/booking/created` hook inside its constructor, which only runs when `WC_Emails::get_emails()` is called. On payment-webhook requests that flow was lazy and the hook was never registered before the action fired. Added a `woocommerce_init` (priority 1) call to `WC()->mailer()->get_emails()` in `Plugin::boot()` so email classes are always initialised before any order-status transition can fire.
- **Generic WC order emails sent to guests for IBB bookings** — `customer_processing_order` and `customer_completed_order` are now suppressed for any order containing an `_ibb_property_id` line item, so guests only receive the IBB booking confirmation.
- **Availability calendar layout** — day cells restored to `flex: 0 0 14.28571%; max-width: 14.28571%`; removing the Flatpickr-native max-width caused all days to collapse onto one or two rows.
- **Blackout dates not shown in availability calendar** — `AvailabilityService::get_blocked_dates()` now expands `_ibb_blackout_ranges` property meta into the blocked-dates array returned to the date picker, so blackout periods are greyed/struck through alongside DB blocks.
- **Past dates showing strikethrough in calendar** — `flatpickr-disabled` no longer gets `text-decoration:line-through`; only `ibb-booked` (future blocked dates) gets strikethrough, set via an `onDayCreate` callback.

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
