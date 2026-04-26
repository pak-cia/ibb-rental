# Frontend

Public-facing rendering: shortcodes, asset enqueueing (Flatpickr + lightbox), single-property template override.

## Files

- `Shortcodes.php` — `[ibb_booking_form]`, `[ibb_property]`, `[ibb_property_details]`, `[ibb_search]`, `[ibb_calendar]`, `[ibb_gallery]`. Each shortcode is the single source of truth — the Gutenberg blocks in `Blocks.php` and the Elementor dynamic tag both render through these handlers.
- `Blocks.php` — three server-rendered Gutenberg blocks (`ibb/booking-form`, `ibb/gallery`, `ibb/property-details`) plus a custom `IBB Rentals` block category. Each block's `render_callback` delegates to the matching shortcode handler, and the editor preview uses `ServerSideRender` so the edit-time view matches the front-end. No build step: editor JS is inline on a no-source handle.
- `Assets.php` — conditionally enqueues Flatpickr (CDN), the booking-widget JS, and the `[ibb_gallery]` lightbox. Detects relevance via `is_singular( ibb_property )` / `is_post_type_archive` / `has_shortcode` for any of our shortcodes. Inline CSS + JS, no build step required.
- `TemplateLoader.php` — `template_include` filter. For singular `ibb_property`: looks for `theme/ibb-rentals/single-ibb_property.php` → `theme/single-ibb_property.php` → falls back to the plugin's `templates/single-ibb_property.php`.

## Key patterns

- **`apply_filters('the_content', ...)` for property descriptions** — runs the full WP filter chain (shortcodes, autop, oEmbeds) inside the property's main content. Required for nested shortcodes like `[ibb_gallery]` typed into the editor to resolve.
- **Shortcodes are the single source of truth** — Gutenberg blocks in `Blocks.php` and the Elementor dynamic tag in `../Integrations/` both delegate to the same shortcode handlers. Adding a new render path = add the shortcode first, then thin wrappers above it. Never duplicate render logic.
- **Server-rendered blocks via `ServerSideRender`** — block edit-time previews call back to PHP through `wp.serverSideRender`, which hits `/wp/v2/block-renderer/<name>` and runs our `render_callback`. WP's block-renderer endpoint sets up post context from the `post_id` query arg, so `get_the_ID()` in the render callback resolves correctly during preview. No build step: editor JS is registered inline against `wp_register_script(handle, '', deps)` and emitted via `wp_add_inline_script`.
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
