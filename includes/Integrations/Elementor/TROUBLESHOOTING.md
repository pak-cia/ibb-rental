# Integrations / Elementor — Troubleshooting

## DON'T hook `elementor/loaded` for tag/widget/control registration

**The most important note in this file.** Elementor fires `elementor/loaded` from its main plugin file **during** `wp-settings.php`'s plugin-load loop — that is, **before** WP's `plugins_loaded` action runs.

Our `Plugin::boot()` runs at `plugins_loaded` priority 20. If `Module::register()` does `add_action('elementor/loaded', …)` from inside it, the handler is added AFTER the action has already fired and never runs. Result: silent failure — no group, no tag, nothing in the dynamic-tag picker, no error in the log.

**Hook directly to the registration action you actually need:**

| Registering | Hook |
|---|---|
| Dynamic tags | `elementor/dynamic_tags/register` |
| Widgets | `elementor/widgets/register` |
| Controls | `elementor/controls/register` |
| Theme-builder hooks | `elementor/theme/register_locations` |

Each of these fires DURING editor / manager init, well after `plugins_loaded`, AND only exists when Elementor itself is loaded — so it doubles as the "is Elementor active?" gate. No need for `elementor/loaded`.

This was the bug that stopped the Property Gallery dynamic tag from appearing after the original integration was written. Fix landed when this entry was added.

## "IBB Rentals → Property Gallery" doesn't appear in the dynamic-tag picker

**Likely causes (after the timing-bug fix above is in place):**

1. **Wrong widget category.** The tag declares `gallery` category. It only appears on widget controls that accept gallery dynamic tags (Pro Gallery, Pro Image Carousel). The free Image widget accepts `image` category dynamic tags, not `gallery` — different category, different list.
2. **Elementor isn't Pro.** Dynamic tags are an Elementor Pro feature. Without Pro, `elementor/dynamic_tags/register` never fires.
3. **The tag class file errored during `require_once`.** Check the WP error log for `IBB\Rentals\Integrations\Elementor\DynamicTags\PropertyGalleryDynamicTag` parse / type errors.
4. **Editor cache.** Hard-refresh the editor browser tab. If still missing, run Elementor → Tools → Regenerate Files & Data.

## Tag returns empty array (no images render)

**Likely causes:**

1. The selected property has no galleries configured. Open the property → Photos tab → add a gallery + images.
2. The selected gallery slug doesn't exist on this property (different properties can have different slugs). Pick "All photos" or one of the slugs that actually exists on the property the tag will render against.
3. **Editor cache.** After picking the dynamic tag and configuring it, hard-refresh the editor tab. Elementor caches dynamic-tag values aggressively; sometimes a fresh render is needed.

The `Current page` selection has a built-in fallback: if the page being rendered is NOT a property post, `resolve_property()` falls back to the first available property. This avoids the silent "no images" trap when previewing the tag on a generic Elementor page during editing. On a real single-property template the current property always wins.

## Gallery dropdown shows slugs from properties I don't expect

**Cause:** `Module::gallery_slug_options()` collects the union of every distinct gallery slug across every property. If two properties both define a `pool` gallery, the dropdown shows one entry; if only Property A has `bedroom-3`, the dropdown still shows `bedroom-3` (and rendering on Property B with that slug will return no images, which is fine — at-render-time the tag looks up the slug on the actual rendering property, doesn't find it, returns empty).

This is intentional: slugs are global to the plugin (a property's "bedroom-1" means the same conceptual room as another property's "bedroom-1"), so editors don't need to per-property-pick the slug. Per-property-conditional slug dropdowns are a v1.1+ feature.

## Adding a property doesn't show up in the SELECT2 immediately

**Cause:** `Module::property_options()` is cached per-request and Elementor caches editor controls aggressively. Reload the editor (full browser refresh) after creating the new property — that's a fresh request, fresh cache.

## Fatal: "Class \\Elementor\\Core\\DynamicTags\\Data_Tag not found"

**Cause:** the tag class file was loaded BEFORE Elementor's autoloader. This happens if you reference the tag class directly (e.g. via `new` or PSR-4 autoload) outside of `Module::register_tags()`.

**Fix:** all references to Elementor base classes must happen inside the `register_tags()` callback (which fires on `elementor/dynamic_tags/register`, after Elementor is fully loaded). The tag file itself has a `class_exists` guard at the top that early-returns if Elementor isn't around — but if you skip that pattern when adding new files, you lose the protection.

## Editor shows "(deprecated)" warning on the tag

**Cause:** Elementor 3.5+ migrated from `register_tag` to `register`. Our `Module::register_tags()` calls the new method first, falls back to `register_tag` only on older Elementor. If you see deprecation warnings, you're on a pre-3.5 Elementor — upgrade.
