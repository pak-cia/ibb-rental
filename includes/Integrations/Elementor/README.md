# Integrations / Elementor

Self-contained module providing Elementor compatibility — currently a single dynamic tag for the `gallery` category, expandable to widgets, controls, and theme-builder hooks as needed.

## Files

- `Module.php` — entry point. Loaded unconditionally by `Plugin::boot()`; hooks directly to `elementor/dynamic_tags/register` (which only fires when Elementor is active, so it's a no-op when Elementor isn't installed). Registers the IBB Rentals dynamic-tag group + tag classes there. Hosts the shared `property_options()` helper used by tag controls. **Do not switch this to `elementor/loaded`** — that action fires during plugin-loading, before our `Plugin::boot()` runs, so the handler would never fire (see TROUBLESHOOTING).
- `DynamicTags/PropertyGalleryDynamicTag.php` — `Data_Tag` subclass returning `[{id, url}]` for the `gallery` dynamic-tag category. Two controls: Property (SELECT2 with "Current page" default) and Gallery slug (optional sub-gallery).

## Key patterns

- **Module entry point convention** — every integration in `Integrations/<Provider>/` exposes a single `Module` class as its entry point, instantiated in `Plugin::boot()` via `( new Module() )->register()`. Subdirectories (`DynamicTags/`, `Widgets/`, `Controls/` etc.) hold leaf classes.
- **Lazy class loading via `require_once`** — Elementor base classes don't exist until Elementor's autoloader has loaded, so we DON'T rely on PSR-4 to autoload tag classes. `Module::register_tags()` `require_once`s the tag file at the moment Elementor calls our registration callback. Trying to autoload would trigger a parse-time fatal on the parent class reference.
- **Conditional registration** — every external-class reference is gated on either `class_exists('\Elementor\…')` (for runtime decisions) or `elementor/loaded` action (for hook-time decisions). The plugin works fully without Elementor active; nothing fails at boot.
- **Stable controls API only** — Elementor's experimental controls change between releases. Only standard `Controls_Manager` types (SELECT2, TEXT, SWITCHER, etc.) here.
- **Static request-cached helpers** — `Module::property_options()` caches its query result in a `static` so the editor's repeated panel renders don't re-query. Cache lifetime is one request, which is fine: editor reloads pick up new properties.

## Connects to

- [../../Domain](../../Domain/README.md) — `Property::from_id` + `gallery($slug)` + `all_attachments()` for resolving the gallery payload.
- [../../PostTypes](../../PostTypes/README.md) — `property_options()` queries the `ibb_property` CPT.
- [../../Plugin.php](../../Plugin.php) — boot calls `(new Module())->register()` unconditionally; the module's own `class_exists` / `elementor/loaded` gating handles the "not installed" case.

## Adding to this module

- **New dynamic tag**: add a class under `DynamicTags/<Name>DynamicTag.php`, mirror the existing tag's namespace declaration, then `require_once` it from `Module::register_tags()` and call `$manager->register(new $cls())`.
- **Custom widget** (future): create `Widgets/<Name>Widget.php` extending `\Elementor\Widget_Base`, gate the `require_once` inside a `Module::register_widgets()` callback hooked to `elementor/widgets/register`.
- **Custom controls** (future): same pattern, `Controls/` subdirectory + a `Module::register_controls()` callback.

Each new file keeps the **lazy-require** pattern; never reference Elementor base classes at PSR-4 autoload time.

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
