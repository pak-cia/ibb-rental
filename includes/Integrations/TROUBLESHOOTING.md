# Integrations — Troubleshooting

## "IBB Rentals → Property Gallery" doesn't appear in Elementor's dynamic-tag picker

**Likely causes:**

1. **The category isn't `gallery`.** The tag declares `Module::GALLERY_CATEGORY` (or fallback string `'gallery'`). It only appears on widget controls that accept gallery dynamic tags (Pro Gallery, Pro Image Carousel). The free Image widget accepts `image` category dynamic tags, not `gallery` — different category.
2. **Elementor isn't loaded yet.** The integration registers on the `elementor/loaded` action; verify it fired. Check the WP admin → Elementor → System Info for any "plugin not loaded" complaints.
3. **The tag class file errored during `require_once`.** Check the error log for `IBB\Rentals\Integrations\Elementor\PropertyGalleryDynamicTag` parse / type errors.

## Tag returns empty array

**Likely causes:**

1. The "Current page" option is set but you're viewing it on a non-property page (e.g. a generic Elementor template). Pick a specific property in the dropdown.
2. The selected property has no galleries configured. Check the Photos tab on the property edit screen.
3. The gallery slug doesn't exist. The slug is sanitised on save (lowercase, hyphenated). If you typed `Bedroom 1` into the slug control, it won't match — use `bedroom-1`.

## Adding a property doesn't show up in the SELECT2 immediately

**Likely cause:** `property_options()` is cached per-request and Elementor caches editor controls aggressively. Reload the editor (full browser refresh) after creating the new property.
