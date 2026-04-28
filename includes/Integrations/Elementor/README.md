# Integrations / Elementor

Self-contained Elementor module: dynamic tags for every property field, four custom widgets with full Style tabs, and a Loop Grid query so users can build property archives with native Elementor templating.

## Files

- `Module.php` — entry point. Loaded unconditionally by `Plugin::boot()`. Hooks directly to `elementor/dynamic_tags/register`, `elementor/widgets/register`, `elementor/elements/categories_registered`, `elementor/frontend/after_register_scripts`, `elementor/preview/enqueue_scripts`, and `elementor/query/ibb_properties` — these only fire when Elementor is active so the module is a no-op when it isn't. Registers our dynamic tags, widgets, the "IBB Rentals" widget category, the carousel-init script, and the Loop Grid query handler. Hosts the shared `property_options()`, `gallery_slug_options()`, and `resolve_property_for_widget()` helpers used by every leaf class. **Do not switch any of these to `elementor/loaded`** — that action fires during plugin-loading, before our `Plugin::boot()` runs, so the handlers would never fire (see TROUBLESHOOTING).
- `DynamicTags/AbstractPropertyFieldTag.php` — base class for text-returning property-field tags. Provides the shared Property picker control + resolver. Each leaf text tag is ~10 lines.
- `DynamicTags/PropertyTitleTag.php`, `PropertyAddressTag.php`, `PropertyMaxGuestsTag.php`, `PropertyBedroomsTag.php`, `PropertyBathroomsTag.php`, `PropertyBedsTag.php`, `PropertyBaseRateTag.php`, `PropertyCheckInTimeTag.php`, `PropertyCheckOutTimeTag.php` — text tags extending `AbstractPropertyFieldTag`. Bindable to any widget control with `dynamic.active = true`.
- `DynamicTags/PropertyUrlTag.php` — `Data_Tag` returning the property's permalink. Bindable to URL controls (Button link, Image link, etc.).
- `DynamicTags/PropertyImageTag.php` — `Data_Tag` returning `[id, url]` for the property's primary image (first attachment from the chosen gallery, falling back to the WP featured image). Bindable to Image controls.
- `DynamicTags/PropertyGalleryDynamicTag.php` — `Data_Tag` returning `[{id}]` for the `gallery` dynamic-tag category. Two controls: Property (SELECT2 with "Current page" default) and Gallery (SELECT of available slugs).
- `Widgets/BookingFormWidget.php` — date-picker + quote + add-to-cart for one property. Delegates to `[ibb_booking_form]` shortcode. Full Style tab.
- `Widgets/PropertyDetailsWidget.php` — property metadata in grid/compact/list layout. Per-field switchers for which fields to render. Delegates to `[ibb_property_details]` shortcode. Full Style tab.
- `Widgets/PropertyGalleryWidget.php` — static gallery grid with built-in lightbox. Delegates to `[ibb_gallery]` shortcode. Full Style tab.
- `Widgets/PropertyCarouselWidget.php` — Swiper-driven slide carousel. Self-renders Swiper-compatible HTML; init JS lives in `Module::carousel_init_js()` and is hooked via Elementor's `frontend/element_ready/ibb_property_carousel.default` so each carousel instance gets its own Swiper, including across editor re-renders. Built specifically for the use case Pro's Media Carousel can't cover (Media Carousel uses a Repeater for slides and can't accept array-returning gallery tags). Full Style tab.

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

## Planned / deferred

- **`PropertyCarouselWidget` — video slide support**: currently only image attachments render; video MIME types are silently skipped by `wp_get_attachment_image()`. Adding video requires detecting the MIME type per attachment and rendering a `<video>` tag (or poster + play-button overlay) instead. Mixed image/video slides also need Swiper config adjustments (no fade effect on video slides, autoplay pause on video play). Scope as a standalone feature when ready.

## Adding to this module

- **New text-field property tag**: extend `AbstractPropertyFieldTag` — the leaf class only needs `get_name()`, `get_title()`, and `field_value(Property $p)`. Add the class name to `Module::register_tags()`'s `$tags` array. ~10 lines total.
- **New gallery / URL / image tag**: extend `\Elementor\Core\DynamicTags\Data_Tag` directly, set `get_categories()` to the matching category constant, return the appropriate shape from `get_value()`. Add to `$tags` array.
- **Custom widget**: create `Widgets/<Name>Widget.php` extending `\Elementor\Widget_Base`, add the class name to `Module::register_widgets()`'s `$widget_files` array. Use `{{WRAPPER}}`-scoped selectors and Global kit slots for color/typography defaults — see RUNBOOK.
- **Custom controls** (future): same pattern, `Controls/` subdirectory + a `Module::register_controls()` callback.

Each new file keeps the **lazy-require** pattern; never reference Elementor base classes at PSR-4 autoload time.

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
