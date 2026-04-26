# templates

Default front-end templates used by the plugin's `Frontend/TemplateLoader` when neither the active theme nor a child theme provides an override.

## Files

- `single-ibb_property.php` — default single-property template. Calls `get_header()` / `get_footer()` and renders the `[ibb_property]` shortcode for the current post. Themes can override by placing their own at `theme/ibb-rentals/single-ibb_property.php` or `theme/single-ibb_property.php`.

## Key patterns

- **Theme override priority**: `theme/ibb-rentals/single-ibb_property.php` → `theme/single-ibb_property.php` → plugin fallback. See `Frontend/TemplateLoader::route`.
- **Use shortcodes inside templates**: the default template just delegates to `[ibb_property id=...]`. This keeps the rendering logic in PHP (the shortcode handler) and the template thin.
- **Get_header/footer pulls site chrome from the active theme** — no need to ship a full site shell with the plugin.

## Connects to

- [../includes/Frontend](../includes/Frontend/README.md) — `TemplateLoader` resolves which template runs
- [../includes/Frontend](../includes/Frontend/README.md) — `Shortcodes::render_property` is what the template ultimately renders

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
