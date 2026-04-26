# Integrations

Optional third-party glue. Each integration is gated on the host plugin being active — the plugin works fully without any of them.

## Files

- `Elementor.php` — registers an Elementor dynamic-tag in the gallery category. Gated on `elementor/loaded` action and `class_exists('\Elementor\Core\DynamicTags\Data_Tag')`. Provides a SELECT2 of all properties as the picker control plus a free-text gallery slug.
- `Elementor/PropertyGalleryDynamicTag.php` — the actual tag class. Extends `\Elementor\Core\DynamicTags\Data_Tag` (gallery category). Returns Elementor's expected `[{id, url}]` shape via `get_value()`. PSR-4 mapped to `IBB\Rentals\Integrations\Elementor\PropertyGalleryDynamicTag` but `require_once`d at runtime so the parent class exists when the file is parsed.

## Key patterns

- **Conditional loading** — every integration file checks `class_exists('<ParentClass>')` at the top and `return` early if not present. Saves a fatal when the host plugin is deactivated.
- **Lazy class declaration** — Elementor base classes don't exist until Elementor's autoloader has loaded. Don't rely on PSR-4 to resolve the integration class; `require_once` it explicitly inside the registration callback.
- **Stable controls API** — the dynamic tag's controls (Property select, Gallery slug input) are stable across Elementor versions. Avoid Elementor experimental controls.
- **No coupling back into Elementor** — the integration produces data; Elementor's widgets do all the rendering. We don't ship custom widget templates.

## Connects to

- [../Domain](../Domain/README.md) — `Property::from_id` + `gallery($slug)` + `all_attachments()`
- [../PostTypes](../PostTypes/README.md) — `property_options()` queries the `ibb_property` CPT
- [../Plugin.php](../Plugin.php) — boot calls `(new ElementorIntegration())->register()` unconditionally; the integration's own gating handles the "not installed" case

## Adding a new integration

For any new third-party (Beaver Builder, Bricks, Divi, WP Fusion, …):

1. Create `Integrations/<Provider>.php` with a class that has `register()` gated on `class_exists` of the provider's base class.
2. If the integration requires the provider's classes to be loaded first, `require_once` extension files inside the registration callback rather than letting PSR-4 resolve them.
3. Wire it from `Plugin::boot` unconditionally — the inner `class_exists` gate decides whether anything happens.
4. Document the integration here.

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
