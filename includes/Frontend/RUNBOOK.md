# Frontend — Runbook

## Override the single-property template in a theme

Place either of these in your active theme:
- `your-theme/ibb-rentals/single-ibb_property.php` (preferred — keeps the override scoped)
- `your-theme/single-ibb_property.php`

`TemplateLoader::route` picks the first match. The plugin's `templates/single-ibb_property.php` is a small wrapper around `[ibb_property]` — feel free to copy-paste and customise.

## Add a new shortcode

1. Add the handler method to `Shortcodes.php`.
2. Register it in `Shortcodes::register()`.
3. If the shortcode produces UI that needs assets, add its tag to the `should_enqueue` shortcode list in `Assets.php`.

## Customise the booking widget colours

```css
.ibb-booking { --ibb-accent: #16a34a; }
.ibb-booking__submit { background: var(--ibb-accent); }
```

CSS prefix is `.ibb-`; everything is namespaced.

## Disable the built-in lightbox for a specific gallery

```
[ibb_gallery gallery="bedroom-1" class="ibb-no-lightbox"]
```

The lightbox JS skips any container with the `ibb-no-lightbox` class. Useful when a theme/page-builder already wraps gallery images in its own lightbox plugin.

## Add a new Gutenberg block (mirroring an existing shortcode)

1. Make sure the shortcode it'll wrap exists in `Shortcodes.php`. The block is a thin layer over the shortcode — never duplicate render logic.
2. In `Blocks.php`'s `register_blocks()`, call `register_block_type('ibb/<name>', [...])` with `attributes`, `render_callback`, `category => 'ibb-rentals'`, an `icon` (Dashicons name), and `supports`.
3. Add a `render_<name>_block(array $attrs)` method that maps block attributes into shortcode attributes and calls the shortcode handler. Use `wrap_with_align()` if the block supports `wide` / `full` alignment.
4. In `editor_js()`, add a `registerBlockType('ibb/<name>', { edit, save })` entry. `save` returns `null` (server-rendered). `edit` should return an `InspectorControls` panel and a `ServerSideRender` for the preview — use the `previewOrPlaceholder()` helper for the empty-response fallback.
5. If the block needs reactive option data (e.g. property → gallery slug list), extend `editor_data()` to emit it on `window.IBBRentalsBlocks` at editor load.
6. If the block introduces a new shortcode tag, add the tag to the `Assets::should_enqueue` allowlist so frontend assets load on pages that use it.

## Add a new field to the property-details block

1. Add the accessor to `Domain/Property.php` if it isn't there yet.
2. In `Shortcodes.php` → `render_property_details()`, add an entry to the `$available` array (or the taxonomy `$tax_map` for term-based fields).
3. In `Blocks.php` → `editor_data()`, add the field to the `detailFields` array — that's what populates the inspector checkboxes.
4. The block's order is determined by the `detailFields` declaration order in `editor_data()`. Insert the new entry at the position you want it to appear.

## Replace Flatpickr with another date picker

`Assets::maybe_enqueue` enqueues `flatpickr` from CDN. To swap, deregister `flatpickr` and enqueue your library; ensure your replacement honours the `lockDays`-style API the booking JS uses, OR rewrite the `flatpickr(...)` call in `Assets::js()`.
