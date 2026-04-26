# Integrations / Elementor

Self-contained module providing Elementor compatibility — currently a single dynamic tag for the `gallery` category, expandable to widgets, controls, and theme-builder hooks as needed.

## Files

- `Module.php` — entry point. Loaded unconditionally by `Plugin::boot()`. Hooks directly to `elementor/dynamic_tags/register`, `elementor/widgets/register`, `elementor/elements/categories_registered`, and `elementor/frontend/after_register_scripts` — these only fire when Elementor is active so the module is a no-op when it isn't. Registers our dynamic tags, widgets, the "IBB Rentals" widget category, and the carousel-init script. Hosts the shared `property_options()` and `gallery_slug_options()` helpers used by control schemas. **Do not switch any of these to `elementor/loaded`** — that action fires during plugin-loading, before our `Plugin::boot()` runs, so the handlers would never fire (see TROUBLESHOOTING).
- `DynamicTags/PropertyGalleryDynamicTag.php` — `Data_Tag` subclass returning `[{id}]` for the `gallery` dynamic-tag category. Two controls: Property (SELECT2 with "Current page" default) and Gallery (SELECT of available slugs).
- `Widgets/BookingFormWidget.php` — date-picker + quote + add-to-cart for one property. Delegates to `[ibb_booking_form]` shortcode.
- `Widgets/PropertyDetailsWidget.php` — property metadata in grid/compact/list layout. Per-field switchers for which fields to render. Delegates to `[ibb_property_details]` shortcode.
- `Widgets/PropertyGalleryWidget.php` — static gallery grid with built-in lightbox. Delegates to `[ibb_gallery]` shortcode.
- `Widgets/PropertyCarouselWidget.php` — Swiper-driven slide carousel. Self-renders Swiper-compatible HTML; init JS lives in `Module::carousel_init_js()` and is hooked via Elementor's `frontend/element_ready/ibb_property_carousel.default` so each carousel instance gets its own Swiper, including across editor re-renders. Built specifically for the use case Pro's Media Carousel can't cover (Media Carousel uses a Repeater for slides and can't accept array-returning gallery tags).

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
