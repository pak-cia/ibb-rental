# Frontend — Changelog

## [Unreleased]

---

## [0.8.7] — 2026-05-01

### Fixed
- **Guest-stepper +/− buttons stuck after reaching min/max.** `Assets.php` booking-form JS: stepper click handlers programmatically set `guestsInput.value` and dispatched `'change'` — but `'input'` events don't fire on programmatic value changes, and `syncStepperState()` was only listening to `'input'`. Result: disabled flags became permanently stale until the user typed/used keyboard arrows. Fix: call `syncStepperState()` directly inside both click handlers.

---

## [0.8.5] — 2026-05-01

### Fixed
- **`TemplateLoader` deference rewritten with path-based primary check.** 0.8.4's API-only detection still returned empty for matched Theme Builder templates on some sites. New `should_defer_to_external_template()` first inspects whether `$template` already lives inside another plugin's directory (most reliable signal — directly observes `template_include`'s current value). Falls back to Elementor Pro's API check. Either signal triggers deference.

---

## [0.8.4] — 2026-05-01

### Fixed
- **`TemplateLoader::route()` now defers to Elementor Pro Theme Builder.** Previously the priority-99 `template_include` filter unconditionally overrode whatever Elementor had set. Now it returns the incoming `$template` unchanged when `\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_documents_for_location('single')` reports any matching document. Without Elementor Pro the existing fallback chain (theme override → plugin template) still applies. See `TROUBLESHOOTING.md`.

---

## [0.3.5] — 2026-04-28

### Fixed
- **Availability calendar 7-per-row layout** — `.ibb-calendar .flatpickr-day` restored to `flex: 0 0 14.28571%; max-width: 14.28571%`. Removing the max-width caused all days to collapse onto one/two rows in flex containers.
- **Past dates showing strikethrough** — `text-decoration:line-through` removed from `flatpickr-disabled`; only `.ibb-booked` (future blocked dates, marked via `onDayCreate`) gets strikethrough.
- **Blackout dates not shown in calendar** — `AvailabilityService::get_blocked_dates()` now expands `_ibb_blackout_ranges` into the blocked-dates array (was only checked at quote time). `Assets.php` `onDayCreate` callback therefore now greys/strikes blackout dates correctly alongside DB blocks.

### Added
- **`ibb/property-description` block** — server-rendered Gutenberg block that outputs the property's `post_content` through `the_content` filters. Single Property picker control in the inspector; edit-time preview via `ServerSideRender`. Wrapper `div.ibb-property-description.entry-content` inherits theme typography for free.

### Fixed
- **Calendar container width** — `.ibb-calendar` switched from `display:inline-block` to `display:block; width:100%`. Added fluid CSS overrides for Flatpickr's inline-mode internal elements (`.flatpickr-calendar`, `.flatpickr-days`, `.dayContainer`, `.flatpickr-day`) so the calendar fills its container regardless of the parent element's width.
- **Calendar responsive month count** — calendars configured for multiple months now reduce their month count to fit the available width via `ResizeObserver` + Flatpickr re-init (≈1 month per 280 px). Previously only CSS flex-wrapping applied, which stacked months rather than reducing them.

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
