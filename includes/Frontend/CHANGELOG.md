# Frontend — Changelog

## [Unreleased]

### Added
- **`[ibb_calendar]` shortcode** — read-only inline availability calendar using Flatpickr's inline mode. Fetches blocked dates from `/availability` and renders a static month grid (1–3 months, configurable via `months=` attribute). Blocked dates are greyed-out with strikethrough; available dates are plain white. `legend=no` hides the Available/Unavailable legend. Selection is immediately cleared on `onChange` so the calendar stays display-only. No new JS dependency — reuses the Flatpickr handle already loaded by the booking form.

### Fixed
- `Assets::should_enqueue()` now also detects blocks via `has_block()`. Pages using only the IBB Gutenberg blocks (no shortcodes) no longer ship without Flatpickr, the lightbox JS, or the details-grid CSS.
- Cart / checkout line-item meta — final, theme-immune approach: `CartHandler::render_item_meta()` emits a **single** `woocommerce_get_item_data` entry. The first field (Check-in) is the entry's `key` so the cart shows "Check-in: …" naturally without an extra "Booking:" prefix; remaining fields go in the `display` value as `<br>`-separated lines with inline `font-weight: 600` on each label (defeats themes that strip bold from `<strong>`). Works identically in classic cart, the WC Cart block, mini cart, and order confirmation. No CSS, no `!important`, no theme-fighting. (Earlier attempts in this Unreleased cycle that didn't survive testing — theme-fighting `dl.variation` CSS, then a split classic/block path with `REST_REQUEST` gating, then a `woocommerce_after_cart_item_name` handler that didn't fire on the Cart block — are now gone.)

### Added
- `Blocks.php` — three server-rendered Gutenberg blocks for page-builder use:
  - `ibb/booking-form` — the booking widget for one property.
  - `ibb/gallery` — full property photos or a single named sub-gallery; reactive gallery-slug dropdown that repopulates when the property changes; columns / image size / lightbox controls.
  - `ibb/property-details` — property metadata with per-field checkboxes and grid / compact / list layouts.
  - All three delegate to the matching shortcode handler via `render_callback`. Edit-time previews use `ServerSideRender`. Custom `IBB Rentals` block category. No build step.
- `[ibb_property_details]` shortcode — standalone property metadata renderer (was previously only available as part of the composite `[ibb_property]`).
- CSS for `.ibb-details--grid` / `--compact` / `--list` layouts.

### Added (earlier this cycle)
- `[ibb_gallery]` shortcode with built-in lightbox (vanilla JS, prev/next, swipe, ESC, counter, loading spinner).
- Guest-count stepper UI with `−` / `+` buttons (replaced the plain `<select>`).

### Fixed
- Shortcodes inside property descriptions now render — `Shortcodes::render_property` runs `apply_filters('the_content', ...)` instead of `wp_kses_post(wpautop(...))`.
- Quote breakdown rewritten with sectioned line items (per-night avg, LOS discount, fees, total, deposit panel).

## [0.1.0] — 2026-04-26

### Added
- `Shortcodes` — `[ibb_booking_form]`, `[ibb_property]`, `[ibb_search]`, `[ibb_calendar]` (placeholder).
- `Assets` — Flatpickr CDN enqueue, inline booking-widget JS/CSS, conditional load via `should_enqueue`.
- `TemplateLoader` — single-property template chain with theme override.
- Default `templates/single-ibb_property.php`.
