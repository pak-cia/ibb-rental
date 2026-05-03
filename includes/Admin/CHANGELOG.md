# Admin — Changelog

## [Unreleased]

---

## [0.11.1] — 2026-05-03

### Added
- **Settings → iCal sync — "Guest names in feeds" checkbox** (`ical_include_guest_names`, default false). Toggles whether outgoing iCal SUMMARY / DESCRIPTION includes the guest name. Save handler persists as a strict bool. ClickUp deep-links in DESCRIPTION are unaffected by the toggle.

---

## [0.11.3] — 2026-05-03

### Fixed
- **`handle_sync_clickup()` redirects to the Settings page on success, not back via `wp_get_referer()`.** The Sync-now link's pre-baked `_wp_http_referer` was carrying stale state (whichever page loaded Settings — typically Plugins → Add New after a fresh upload), and `wp_get_referer()` then sent the user there instead. The button's link no longer includes `_wp_http_referer`, and the handler hard-codes the post-action URL to the Settings page.

---

## [0.11.2] — 2026-05-03

### Added
- **Settings → ClickUp → "Sync these statuses only" text input** (`clickup_sync_statuses` setting). Comma-separated whitelist of ClickUp status names; empty = no filter. Default `upcoming, currently staying, checked out, cancelled` matches the user's workflow and excludes housekeeping subtasks (`inquiries`) and the auto-archived `Closed` bucket — biggest source of sync bloat. Save handler stores via `sanitize_text_field`.

---

## [0.11.0] — 2026-05-03

### Added
- **Property iCal tab — per-OTA feed-URL table.** Replaced the single combined feed URL with a five-row table (Airbnb / Booking.com / Agoda / VRBO / Expedia), each cell a click-to-select read-only input. Built off `Exporter::feed_urls( $property_id )`.
- **Settings → ClickUp → "Create blocks for"** — multi-checkbox allowlist UI (`clickup_create_sources` setting, JSON array). Lists `web` / `direct` / `manual` and the five OTA slugs; sources that already have an enabled iCal feed (`FeedRepository::find_enabled()`) render disabled + greyed so ClickUp can't compete with the OTA's authoritative feed. Server-side save handler re-validates the same way (defence-in-depth — a feed-backed source POSTed via tampered form is dropped).

### Changed
- **`AdminCalendar::SOURCE_COLOURS`** — added `web => purple` (the colour previously assigned to `direct`); `direct` flipped to `teal #0d9488` so walk-in / phone bookings are visually distinct from website bookings on the calendar timeline.
- **Source-filter dropdown on the calendar page** — added "Website bookings" and "Walk-in / phone" entries; the prior single "Direct bookings" entry is gone.
- **ClickUp settings — sync-status pill** now reads "✓ Last sync N ago — X created, Y updated, Z cancelled (from N tasks)" instead of just "updated N blocks from N tasks".
- **Default tag→source map** gains `"web":"web"` so admins who use a `web` ClickUp tag for plugin-generated bookings get the correct slug.

---

## [0.10.0] — 2026-05-01

### Added
- **Two new tax-class dropdowns on the Booking Rules tab**, alongside the v0.9.0 accommodation selector: **Tax class — cleaning fee** (`_ibb_cleaning_tax_class`) and **Tax class — extra-guest fee** (`_ibb_extra_guest_tax_class`). Extra-guest exposes an extra "Same as accommodation" option (sentinel `__inherit__`, default) so it tracks the stay class without an explicit re-pick. Cleaning defaults to "Not taxed". Three selectors share a private `tax_class_options()` builder + `render_tax_class_select()` helper so the option list stays in sync. Save handler validates all three keys against the live `WC_Tax::get_tax_classes()` list; unknown slugs are coerced to safe defaults.
- An informational helper row at the bottom of the tax block clarifies that the security deposit is never charged today and so has no tax class.

---

## [0.9.0] — 2026-05-01

### Added
- **Tax class dropdown** on Booking Rules tab. Lists "Not taxed" (default), "Standard rate", and every user-defined class from WC → Settings → Tax. Saved to `_ibb_tax_class`; mirrored by `Woo/ProductSync::apply_tax_settings()` to the linked product's `tax_status` + `tax_class`.

---

## [0.8.3] — 2026-05-01

### Changed
- **`AdminCalendar` Month / Week views: visible spacing between event bars.** Inline `<style>` block in `render()` adds `margin: 1px 1px 2px !important` to `.fc-daygrid-event` / `.fc-timegrid-event` and a translucent inset box-shadow to `.fc-event` so adjacent same-color bookings no longer bleed together. No JS or layout-math changes — purely visual.

---

## [0.8.2] — 2026-05-01

### Changed
- **Gallery picker auto-engages Bulk Select mode on open** (`PropertyMetaboxes.php`). Eliminates Ctrl/Cmd-click requirement for multi-image selection — accessibility fix.
- **Currency input fields widened** on Rates and Booking Rules tabs to fit IDR 7-digit values. Inline CSS in the metabox stylesheet plus inline-style bumps on the seasonal-rate-row `nightly_rate` and `weekend_uplift` inputs.
- **Add iCal Feed form** default sync interval lowered from 1800 → 900 seconds.

---

## [0.7.0] — 2026-04-30

### Changed
- **WC Settings → Emails screens for IBB Booking Confirmation and IBB Pre-arrival Reminder** now expose `enabled`, `subject`, `heading`, `additional_content`, `reply_to_email`, and `email_type` fields. Previously inherited the empty `WC_Settings_API` defaults, so admins had no way to edit subject / heading / closing copy without code. (See `Emails/CHANGELOG.md` for the email-class-side detail.)

---

## [0.4.0] — 2026-04-30

### Added
- **`AdminCalendar` timeline view** — third view alongside Month and Week. A custom div-grid with one row per property and one column per day: property name column (sticky left), day-number header (today highlighted in blue, weekends in light grey), and colored bars for each block spanning their date range. View is toggled via Month / Week / Timeline buttons added to the existing toolbar. The timeline fetches from the same `wp_ajax_ibb_rentals_calendar_events` endpoint; no new AJAX handlers were needed. Clicking a bar opens the existing detail/delete modal. The "+ Block dates" button in the timeline header opens the existing create-block modal (pre-selects the active property filter if set). Prev / Today / Next navigate month-by-month.
- **`AdminCalendar` calendar bars now render the guest name** (with "View booking →" / "View ClickUp task →" links in the detail modal when matched). Source filter and bar color reflect `Block::effective_source()` so ClickUp's source override wins over the iCal-import source.
- **ClickUp settings section** on the Settings page (API token, workspace, space, folder, Bookings list, unit-code → property mapper, tag→source map, sync interval, Sync now button, last-sync status pill). Workspace → space → folder → list selectors cascade via 3 AJAX endpoints (`ajax_clickup_workspaces` / `_spaces` / `_folders`) backed by `Services/ClickUpService::fetch_*`. All four levels (`workspace_id`, `space_id`, `folder_id`, `list_id`) are persisted so the cascade restores cleanly on reload. Reschedules the recurring sync action whenever settings are saved.
- **Unit-code → property mapper UI.** Row-per-property table where the admin enters one or more comma-separated unit codes for each IBB property (e.g. `v1, villa1`). Save handler walks the posted `clickup_unit_codes[<pid>]` inputs, splits / lowercases each code, and stores the result as a JSON object under `clickup_unit_property_map`. Read by `ClickUpService` to scope sync UPDATEs to a single property when the task title's prefix matches.
- **iCal Feeds CRUD admin page.** Feeds page now has an "Add feed" form (property select, label, source, URL, sync interval) and per-row "Sync now" / "Delete" actions. Previously the page was read-only; feeds could only be added via the REST API.

---

## [0.3.5] — 2026-04-28

### Changed
- **Blackout ranges editor.** Replaced the raw JSON textarea on the Availability tab with a row-based table editor: each range is a (From, To) date-input pair with a remove button, plus an "Add blackout range" action. Native form-array names (`_ibb_blackout_rows[N][start]` / `[end]`) so saves work without JS. Save handler validates both dates, requires `end > start`, dedupes nothing (overlapping ranges are allowed), sorts by start date, and writes canonical JSON to `_ibb_blackout_ranges`. Storage shape and `Domain/Property::blackout_ranges()` unchanged.
- **Length-of-stay discounts editor.** Replaced the raw JSON textarea on the Rates tab with a row-based table editor: each tier is a (Min nights, % off) pair with a remove button, plus an "Add discount tier" action. Indices are native form-array names (`_ibb_los_discount_rows[N][min_nights]` etc.) so saves work without JS too. Save handler validates each pair, drops empty / invalid / duplicate-min_nights rows, sorts canonically descending by min_nights, and writes the result to `_ibb_los_discounts` — same storage shape as before, so existing data round-trips through the new UI cleanly. Postmeta key unchanged; `Domain/Property::los_discounts()` not touched.

### Added
- Details tab: **Short description** field at the top, backed by `_ibb_short_description` postmeta. Two-row textarea with placeholder + helper text. Mirrored to the linked WC product's `short_description` and surfaces in cart line items.
- Photos tab: named sub-galleries per property (e.g. *Bedroom 1*, *Pool*) backed by `wp.media`. Gallery state serialises to `_ibb_galleries` postmeta as JSON.
- Per-gallery shortcode hint (one-click copyable `[ibb_gallery gallery="..."]` snippet).

### Fixed
- "Add gallery" button no-op in Gutenberg — JS moved to `admin_print_footer_scripts` priority 99 with polling init.
- Tab labels and field descriptions throughout the metabox now use property-specific phrasing instead of WP defaults.

## [0.1.0] — 2026-04-26

### Added
- `Menu` — submenu pages for Bookings, iCal Feeds, Settings under the CPT top-level menu.
- `PropertyMetaboxes` — tabbed metabox with Details / Rates / Booking rules / Availability / iCal panels; gateway-capability matrix; iCal export-URL display.
- `BookingsListTable` — `WP_List_Table` over `wp_ibb_bookings` with filters, sorting, and links to WC orders.
