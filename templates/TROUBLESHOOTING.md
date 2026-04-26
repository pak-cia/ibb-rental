# templates — Troubleshooting

## Theme override isn't being picked up

**Likely causes:**

1. **File name mismatch.** Must be `single-ibb_property.php` (with underscore), not `single-ibb-property.php` (hyphen). The CPT slug uses underscores internally.
2. **Theme-override path wrong.** `Frontend/TemplateLoader::route` looks at `theme/ibb-rentals/single-ibb_property.php` (note: subdirectory is `ibb-rentals` with hyphen — that's the plugin's text-domain) before `theme/single-ibb_property.php`.
3. **Active theme has its own `template_include` filter at higher priority.** Our filter is registered at priority 99; if another plugin/theme uses 100 or higher and returns its own value, ours is overridden.

## Page builder (Elementor / Beaver) doesn't respect the template

This is by design — page builders inject their own template loader. Use the page builder's "Theme Builder" / "Single Template" feature to design the property single page using the plugin's shortcodes (`[ibb_property]`, `[ibb_gallery]`, `[ibb_booking_form]`) or, in Elementor, the dynamic-tag for galleries.
