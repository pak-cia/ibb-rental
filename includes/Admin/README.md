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

## Not yet built — seasonal rates editor

The **Rates tab** in the property metabox currently shows existing `ibb_rates` rows read-only and tells the user to manage them via the REST API. The full row-based CRUD editor was never built.

**What needs building** (follow the LOS-discounts / blackout-ranges pattern — native form-arrays, no hidden JSON):

Each row in the `ibb_rates` table has these user-facing fields:

| Field | Type | Notes |
|---|---|---|
| `date_from` | `DATE` | Inclusive start of the rate period |
| `date_to` | `DATE` | Inclusive end |
| `nightly_rate` | `DECIMAL(12,2)` | Required |
| `label` | `VARCHAR(100)` | e.g. "High season", "Christmas" |
| `priority` | `SMALLINT` | Default 10; higher wins on overlap |
| `weekend_uplift` | `DECIMAL(12,2)` | Optional override of the property-level uplift |
| `uplift_type` | `pct` \| `abs` | Default `pct` |
| `min_stay` | `SMALLINT` | Optional per-season minimum nights |

**Row add/delete UI** should match the blackout-ranges editor (date inputs + text/number fields, JS add/remove row buttons, no blank trailing row on load).

**Save handler** in `PropertyMetaboxes::save()` should: iterate `$_POST['_ibb_rates']`, validate each row (date_from < date_to, nightly_rate > 0), call `RateRepository::delete_for_property()` then re-insert all rows. Deleting and re-inserting is simpler than diffing, since the table has no foreign-key dependents.

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
