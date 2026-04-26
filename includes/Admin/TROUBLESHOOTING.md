# Admin — Troubleshooting

## "+ Add gallery" button appears to do nothing (autosave AJAX fires instead)

**Symptom:** clicking the Add gallery button on the Photos tab shows an admin-ajax.php POST in the network tab but no gallery card appears.

**Root cause:** the gallery JS was originally rendered as an inline `<script>` inside the metabox HTML. In Gutenberg, the React tree mounts metabox content asynchronously, and the inline script was running too early — the target elements weren't in the DOM yet, so the click handler never attached. Worse, clicks inside the metabox were dirtying Gutenberg's autosave watch, which is why an AJAX request appeared on click.

**Fix:** `PropertyMetaboxes::print_footer_js()` is registered on `admin_print_footer_scripts` priority 99 (runs after Gutenberg has finished rendering metaboxes). The IIFE wraps a polling `init(retries)` that retries every 200ms up to 12s and is idempotent via `root.dataset.ibbInit`. The JSON-script-tag carrying initial gallery state remains inline; only the bootstrapping IIFE moved.

**If it regresses:** check the browser console for `[ibb-rentals]` warnings (we log if `wp.media` isn't available), and verify `enqueue()` calls `wp_enqueue_media()`.

---

## Tabs don't switch when clicked

**Symptom:** clicking the metabox tabs doesn't show/hide their panels.

**Root cause:** the tabs IIFE is inlined immediately after the panels in `render()`. If something in WP / a plugin strips inline `<script>` tags from postbox HTML, the binding is missed.

**Fix:** the tabs JS is short and self-contained. If a security plugin is stripping it, move it out into the same `print_footer_js` flow as the gallery JS.

---

## Gallery picker opens but doesn't return images

**Symptom:** the wp.media frame opens, you select images, click "Add to gallery", and nothing appears in the card.

**Likely cause:** another plugin replaced or extended `wp.media`'s `state().get('selection')` shape. Check `frame.on('select', ...)` — the JS expects `selection.toJSON()` to return objects with `.id` and `.sizes.thumbnail.url`.

If the structure differs, log `selection.toJSON()` to inspect, and adjust the mapping in the gallery JS.

---

## The Bookings list shows nothing despite known bookings existing

**Likely cause:** the orderby column in the URL doesn't match the SQL query. `prepare_items()` whitelists `id`, `checkin`, and `status` — anything else falls back to `id`.

**Or:** the booking row was created against an older schema. Run a fresh `Migrations::run_to_latest()` (delete `ibb_rentals_db_version`).
