# Admin

wp-admin user interface for the plugin: top-level "Rentals" menu, the tabbed property metabox, the bookings list table, and the settings/feeds pages.

## Files

- `Menu.php` — registers submenu pages (Bookings, iCal Feeds, Settings) under the CPT menu, plus the settings save handler. The CPT itself provides the top-level "Rentals" menu and "All Properties / Add New" entries.
- `PropertyMetaboxes.php` — single tabbed metabox on the property edit screen with six tabs: Details, Photos, Rates, Booking rules, Availability, iCal. Owns its own scoped CSS and a footer-emitted JS bundle for the Photos tab (wp.media-driven gallery picker).
- `BookingsListTable.php` — `WP_List_Table` subclass for `wp_ibb_bookings`. Filterable by status, sortable by id/checkin/status, links each row to its WC order.

## Key patterns

- **Footer-emitted JS** — Photos-tab JS is printed in `admin_print_footer_scripts` priority 99 with a polling `init` (retries up to 12s, idempotent via `data-ibb-init` flag). This avoids running the JS mid-metabox-render in Gutenberg.
- **JSON in postmeta for complex configs** — `_ibb_los_discounts`, `_ibb_blackout_ranges`, `_ibb_galleries` are stored as JSON. `Domain/Property` accessors decode and validate.
- **Two save-time UI patterns**:
  - **Native form-array inputs** for simple repeaters: each row is `field_name[index][key]`, the save handler iterates `$_POST['field_name']` and rebuilds the canonical structure. Used for **Length-of-stay discounts**. Works without JS — JS only enhances add/remove.
  - **Hidden serialised state**: a `<textarea hidden>` holds the JSON, JS keeps it in sync as the user manipulates a richer UI (e.g. media-picker thumbnails). Used for **Photos / galleries**. Required when the data isn't naturally form-encodable.
  - Pick form-arrays when possible — graceful degrade is free; pick hidden-state when the row contents are more than scalars.
- **Slug uniqueness on save** — when persisting `_ibb_galleries`, the save handler de-dupes slugs (`bedroom-1`, `bedroom-1-2`, …) so the JS-side dedup isn't load-bearing.

## Connects to

- [../Domain](../Domain/README.md) — the metabox reads `Property` accessors to render existing values
- [../Repositories](../Repositories/README.md) — `BookingsListTable` queries `wp_ibb_bookings` directly via `wpdb`; `PropertyMetaboxes` reads `RateRepository` and `FeedRepository` for the read-only rate / feed displays
- [../Ical](../Ical/README.md) — `Exporter::feed_url()` is rendered in the iCal tab
- [../Woo](../Woo/README.md) — `GatewayCapabilities::active_gateway_summary()` powers the gateway-capability matrix in the Booking-rules tab

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
