# Frontend — Changelog

## [Unreleased]

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
