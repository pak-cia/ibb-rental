# Admin — Changelog

## [Unreleased]

### Added
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
