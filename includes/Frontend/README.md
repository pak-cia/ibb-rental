# Frontend

Public-facing rendering: shortcodes, asset enqueueing (Flatpickr + lightbox), single-property template override.

## Files

- `Shortcodes.php` — `[ibb_booking_form]`, `[ibb_property]`, `[ibb_search]`, `[ibb_calendar]`, `[ibb_gallery]`. Each is also wrapped with a Gutenberg-block-friendly render path (single source of truth for shortcode + block).
- `Assets.php` — conditionally enqueues Flatpickr (CDN), the booking-widget JS, and the `[ibb_gallery]` lightbox. Detects relevance via `is_singular( ibb_property )` / `is_post_type_archive` / `has_shortcode` for any of our shortcodes. Inline CSS + JS, no build step required.
- `TemplateLoader.php` — `template_include` filter. For singular `ibb_property`: looks for `theme/ibb-rentals/single-ibb_property.php` → `theme/single-ibb_property.php` → falls back to the plugin's `templates/single-ibb_property.php`.

## Key patterns

- **`apply_filters('the_content', ...)` for property descriptions** — runs the full WP filter chain (shortcodes, autop, oEmbeds) inside the property's main content. Required for nested shortcodes like `[ibb_gallery]` typed into the editor to resolve.
- **Signed-token cart hand-off** — booking form posts to `/quote`, gets back a quote + signed token, then submits a normal `?wc-ajax=add_to_cart` with `ibb_quote_token` in the form data. Cart layer verifies the token before pricing.
- **Rate-limit-safe public REST** — `/availability` is open; `/quote` enforces a per-IP transient counter (30/min).
- **Built-in lightbox** — `Assets::js()` includes a self-contained vanilla JS lightbox that delegates clicks on `.ibb-gallery-display__item` per gallery container. Each grid is its own navigation set. Opt out via `class="ibb-no-lightbox"` on the gallery wrapper.
- **Stepper for guests** — number input flanked by `−` / `+` buttons. The stepper clamps to `[1, max_guests]` from the property meta.
- **CSS-variables theme tinting** — main accent variable is `--ibb-accent`; class prefix is `.ibb-` BEM-style throughout.

## Connects to

- [../Domain](../Domain/README.md) — `Property::from_id` for shortcode rendering; `DateRange` indirectly via the booking-form JS hitting REST endpoints
- [../Rest](../Rest/README.md) — JS calls `/availability` and `/quote`; cart submission goes through WC's own AJAX add_to_cart
- [../Woo](../Woo/README.md) — cart hand-off lands here; `CartHandler::attach_quote` consumes the signed token

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
