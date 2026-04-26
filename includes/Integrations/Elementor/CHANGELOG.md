# Integrations / Elementor — Changelog

## [Unreleased]

### Fixed
- All four widgets and the dynamic tag now route property resolution through a single `Module::resolve_property_for_widget()` helper. Previously each widget called `Property::from_id(get_the_ID())` directly and silently rendered empty when the editor was viewing a non-property page (e.g. "Elementor #36"). The shared helper falls back to the first published property in that case — purely an editor-preview convenience, real single-property templates still resolve to the current property. Removed the dynamic tag's duplicate `resolve_property()` method.

### Added
- **Four Elementor widgets** under `Widgets/`. All grouped in a new "IBB Rentals" widget category (palmtree icon).
  - `BookingFormWidget` — date-picker + quote + add-to-cart for one property. Mirrors the `ibb/booking-form` block.
  - `PropertyDetailsWidget` — property metadata with per-field switchers and grid/compact/list layout.
  - `PropertyGalleryWidget` — static gallery grid with the built-in lightbox.
  - `PropertyCarouselWidget` — Swiper-driven slideshow / carousel populated from a property's photos. Built because Pro's Media Carousel uses a Repeater for slides and can't accept array-returning gallery dynamic tags. **Two layouts**:
    - **Slideshow (default)** — large main image + clickable thumbnail strip below, prev/next arrows on the main. Mirrors Elementor Pro's Media Carousel "slideshow" skin. Two linked Swiper instances (main + thumbs) wired via Swiper's `thumbs` controller.
    - **Carousel** — multi-slide horizontal scroll with slides-per-view per breakpoint, space-between, pagination (none/bullets/fraction/progress).
    - Shared controls across both layouts: navigation arrows, loop, autoplay (with delay + pause-on-hover), transition effect (slide/fade), transition speed.
- `Module::register_widgets()` and `Module::register_widget_category()` — register on `elementor/widgets/register` and `elementor/elements/categories_registered` respectively. Same hook-timing rule as dynamic tags (don't use `elementor/loaded`).
- `Module::carousel_init_js()` — registered as `ibb-rentals-elementor-carousel` script handle. Hooks `frontend/element_ready/ibb_property_carousel.default` so each carousel instance gets its own Swiper, including across editor re-renders.

### Changed
- **Tag return shape simplified to `[{id}]`** (was `[{id, url}]`). Matches Elementor's idiomatic gallery-dynamic-tag shape (WC's Product Gallery, Pro's Featured Image Gallery). Some widgets ignore extra fields but strictly validate the shape, so keeping it minimal maximizes compatibility across Pro Gallery / Image Carousel / Basic Gallery / third-party widgets that use a GALLERY control. Widgets resolve URLs / sizes themselves via their own "Image Size" controls.
- **Gallery control is now a SELECT** (was free-text `slug`). Options are pulled from `Module::gallery_slug_options()` — the union of every distinct gallery slug across every property, with "All photos" pinned at the top as the default. Editors can no longer typo a slug into oblivion.

### Fixed
- `PropertyGalleryDynamicTag::resolve_property()` falls back to the first available property when "Current page" is selected on a non-property page (e.g. a generic Elementor template during editing). This avoids the silent "no images" trap during editor preview. On real single-property templates the current property always wins.

### Fixed
- Property Gallery dynamic tag now actually appears in Elementor's dynamic-tag picker. The original integration hooked `elementor/loaded` to gate registration — but that action fires during `wp-settings.php`'s plugin-load loop, BEFORE `plugins_loaded` runs. Our `Plugin::boot()` runs at `plugins_loaded` priority 20, so by the time `Module::register()` added the handler, the action had already fired and the handler never ran. Silent failure, no error in logs. Hooked directly to `elementor/dynamic_tags/register` instead — that action only exists when Elementor is loaded AND fires after `plugins_loaded`, so it doubles as the "is Elementor active?" gate. Convention captured at the top of TROUBLESHOOTING for any future leaf-class registration (widgets, controls, theme-builder hooks).

### Changed
- Restructured into a self-contained module under `Integrations/Elementor/`. Entry point is `Module.php` (was `Integrations/Elementor.php`); leaf classes live under subdirectories (`DynamicTags/`, future `Widgets/` / `Controls/`). Every integration in the plugin will follow this pattern.

## [0.1.0] — 2026-04-26

### Added
- `Module` (originally `Elementor` at `Integrations/Elementor.php`) — registers a dynamic-tag group + tag classes via `elementor/dynamic_tags/register`. Hosts `property_options()` helper used by tag controls.
- `DynamicTags/PropertyGalleryDynamicTag` — `Data_Tag` returning `[{id, url}]` for the gallery dynamic-tag category. Two controls: Property (SELECT2 with "Current page" default) and Gallery slug.
