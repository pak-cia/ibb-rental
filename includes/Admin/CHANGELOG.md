# Admin — Changelog

## [Unreleased]

### Added
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
