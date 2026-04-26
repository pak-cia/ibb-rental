# Integrations / Elementor — Troubleshooting

## "IBB Rentals → Property Gallery" doesn't appear in the dynamic-tag picker

**Likely causes:**

1. **Wrong widget category.** The tag declares `gallery` category. It only appears on widget controls that accept gallery dynamic tags (Pro Gallery, Pro Image Carousel). The free Image widget accepts `image` category dynamic tags, not `gallery` — different category, different list.
2. **Elementor isn't loaded.** Module registers on `elementor/loaded`. Verify under WP admin → Elementor → System Info; if Elementor reports a fatal at boot, our hook never fires.
3. **The tag class file errored during `require_once`.** Check the WP error log for `IBB\Rentals\Integrations\Elementor\DynamicTags\PropertyGalleryDynamicTag` parse / type errors.

## Tag returns empty array (no images render)

**Likely causes:**

1. The `Current page` option is set but you're viewing it on a non-property page (e.g. a generic Elementor template). Pick a specific property in the dropdown.
2. The selected property has no galleries configured. Open the property → Photos tab → add a gallery + images.
3. The gallery slug doesn't exist. Slugs are sanitised on save (lowercase, hyphenated). If you typed `Bedroom 1` into the slug control, it won't match — use `bedroom-1`.

## Adding a property doesn't show up in the SELECT2 immediately

**Cause:** `Module::property_options()` is cached per-request and Elementor caches editor controls aggressively. Reload the editor (full browser refresh) after creating the new property — that's a fresh request, fresh cache.

## Fatal: "Class \\Elementor\\Core\\DynamicTags\\Data_Tag not found"

**Cause:** the tag class file was loaded BEFORE Elementor's autoloader. This happens if you reference the tag class directly (e.g. via `new` or PSR-4 autoload) outside of `Module::register_tags()`.

**Fix:** all references to Elementor base classes must happen inside the `register_tags()` callback (which fires on `elementor/dynamic_tags/register`, after Elementor is fully loaded). The tag file itself has a `class_exists` guard at the top that early-returns if Elementor isn't around — but if you skip that pattern when adding new files, you lose the protection.

## Editor shows "(deprecated)" warning on the tag

**Cause:** Elementor 3.5+ migrated from `register_tag` to `register`. Our `Module::register_tags()` calls the new method first, falls back to `register_tag` only on older Elementor. If you see deprecation warnings, you're on a pre-3.5 Elementor — upgrade.
