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

## Replace Flatpickr with another date picker

`Assets::maybe_enqueue` enqueues `flatpickr` from CDN. To swap, deregister `flatpickr` and enqueue your library; ensure your replacement honours the `lockDays`-style API the booking JS uses, OR rewrite the `flatpickr(...)` call in `Assets::js()`.
