# templates — Runbook

## Override the single-property template in a theme

Place either of these in your active theme:
- `your-theme/ibb-rentals/single-ibb_property.php` (preferred — keeps the override scoped to this plugin)
- `your-theme/single-ibb_property.php` (works but pollutes the theme's namespace)

`Frontend/TemplateLoader::route` picks the first match. Copy the plugin's `single-ibb_property.php` as a starting point.

## Add a new template (e.g. archive)

1. Create `templates/archive-ibb_property.php` (the default plugin file).
2. Add a route in `Frontend/TemplateLoader::route` for `is_post_type_archive(POST_TYPE)`.
3. Lookup theme overrides: `theme/ibb-rentals/archive-ibb_property.php` → `theme/archive-ibb_property.php` → plugin fallback.

## Use a different shortcode in the default template

The default just calls `[ibb_property id=...]`. To customise (e.g. split the booking form out from the property details), create your own shortcode in `Frontend/Shortcodes.php` and reference it instead.
