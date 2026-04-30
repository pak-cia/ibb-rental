# Admin — Changelog

## [Unreleased]

---

## [0.4.0] — 2026-04-30

### Added
- **ClickUp settings section** — new "ClickUp integration" section on the Settings page (API token, workspace, space, folder, Bookings list, unit-code → property mapper, tag→source map, sync interval). The workspace → space → folder → list selectors cascade via 3 AJAX endpoints (`ajax_clickup_workspaces` / `_spaces` / `_folders`) backed by `ClickUpService::fetch_*` methods. The hidden `clickup_list_id` input is what gets saved; the dropdowns just drive it. Includes a "Sync now" button that dispatches an immediate Action Scheduler job. Reschedules the recurring sync action whenever settings are saved.
- **Unit-code → property mapper UI.** A row-per-property table where the admin enters one or more comma-separated unit codes for each IBB property (e.g. `v1, villa1`). Save handler walks the posted `clickup_unit_codes[<pid>]` inputs, splits / lowercases each code, and stores the result as a JSON object under `clickup_unit_property_map`. The map is read by `ClickUpService` to scope sync UPDATEs to a single property when the task title's prefix matches.

### Changed
- **ClickUp settings now drop custom-field-name inputs.** The earlier draft asked the admin for "Guest Name field", "Check-in Date field", "Check-out Date field" custom-field labels. Real Bookings tasks don't use custom fields for these — the data lives on the task itself. Save handler explicitly unsets the obsolete keys so existing options don't accumulate cruft.
- **`AdminCalendar` timeline view** — third view alongside Month and Week. A custom div-grid with one row per property and one column per day: property name column (sticky left), day-number header (today highlighted in blue, weekends in light grey), and colored bars for each block spanning their date range. View is toggled via Month / Week / Timeline buttons added to the existing toolbar. The timeline fetches from the same `wp_ajax_ibb_rentals_calendar_events` endpoint; no new AJAX handlers were needed. Clicking a bar opens the existing detail/delete modal. The "+ Block dates" button in the timeline header opens the existing create-block modal (pre-selects the active property filter if set). Prev / Today / Next navigate month-by-month. Creating or deleting a block refreshes whichever view is active.
- **iCal Feeds CRUD admin page.** Feeds page now has an "Add feed" form (property select, label, source, URL, sync interval) and per-row "Sync now" / "Delete" actions. Previously the page was read-only; feeds could only be added via the REST API.

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
