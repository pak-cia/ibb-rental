# Integrations / Elementor — Changelog

## [Unreleased]

### Added
- **Eleven new dynamic tags** so property data is first-class in Elementor's templating system. Editors can build single-property templates and Loop Grid cards with native Heading / Text / Button / Image widgets — no need for our custom widgets when more layout flexibility is wanted.
  - **Text** (category: `text`): Property Title, Property Address, Max Guests, Bedrooms, Bathrooms, Beds, Base Rate (per night, `wc_price()`-formatted), Check-in Time, Check-out Time.
  - **URL** (category: `url`): Property URL (permalink) — bindable to Button links, Image links, etc.
  - **Image** (category: `image`): Property Image — first attachment from the chosen gallery (or any gallery), falling back to the WP featured image. Bindable to Image widget, Background images, Loop Item image placeholders.
  - All text tags share `AbstractPropertyFieldTag` — common Property picker control + resolver. Adding another text field is ~10 lines (see `PropertyTitleTag.php`).
  - All tags have a Property control with "Current page" default + first-property fallback (same `Module::resolve_property_for_widget()` as the widgets), so they work in editor preview on non-property pages.
- **Loop Grid query support** via `elementor/query/ibb_properties`. Editors set Advanced → Query ID to `ibb_properties` on a Loop Grid / Posts widget; our handler sets `post_type = ibb_property` and `post_status = publish`. Order / orderby / per-page / taxonomy filters from the widget's own Query panel still apply on top.
- **Tag registration loop** in `Module::register_tags()` — replaces the per-tag boilerplate. Adding a tag now = drop a file under `DynamicTags/` + add its class name to the `$tags` array.

### Added
- **Style tabs on every Elementor widget** (`BookingFormWidget`, `PropertyDetailsWidget`, `PropertyGalleryWidget`, `PropertyCarouselWidget`). All color + typography controls are wired to Elementor's **Global Colors** / **Global Typography** kit slots so the widgets adopt the active theme/kit's design tokens by default and can be overridden inline. Mapping:
  - Headings + value text → `Global_Colors::COLOR_PRIMARY` + `Global_Typography::TYPOGRAPHY_PRIMARY`.
  - Body / labels → `Global_Colors::COLOR_TEXT` + `Global_Typography::TYPOGRAPHY_TEXT`.
  - Accent fills (Book button, active pagination dot, active thumbnail outline) → `Global_Colors::COLOR_ACCENT`.
  - Button typography → `Global_Typography::TYPOGRAPHY_ACCENT`.
- `BookingFormWidget` Style tab: Box (background, border, radius, padding, max-width, shadow), Title (color + typography), Fields (label color + typography, input color/bg/border/radius, stepper button bg + color), Quote panel (bg, text color, typography, total color), Book button (normal + hover tabs for color and bg, typography, radius, padding).
- `PropertyDetailsWidget` Style tab: Grid items (min column width, gap, item bg/border/radius/padding — conditional on grid layout), Value (color + typography), Label (color + typography), Alignment (responsive choose for grid `align-items`, separate text-align for compact/list).
- `PropertyGalleryWidget` Style tab: Grid (gap), Image (aspect ratio, object-fit, border-radius, border, box-shadow, hover zoom, hover overlay color).
- All style selectors scoped under `{{WRAPPER}}` so multiple instances of the same widget on a page style independently. No `!important`; selector specificity beats the base stylesheet cleanly.
- **Style tab on `PropertyCarouselWidget`** (initial pass that this expands on): Image / Arrows / Pagination / Thumbnails sections with the standard Elementor border + radius + shadow + color + size controls.

### Fixed
- **Property Carousel renders 33,554,400px-wide slides in Elementor 4.x editor preview.** Swiper was inits'ing before the parent flex container had a measured width — saw 0px, computed `slidesPerView: 1` math against that, locked the absurd value in. Combined with `loop: true` slide duplication, the wrapper translated to `-3.355e+07px`. Earlier fix had loaded Swiper into the preview but didn't address the timing-of-layout bug. Four-layer fix in place now:
  - `Module::register_widget_scripts()` registers a fallback `swiper` handle (jsDelivr-hosted Swiper 8.4.5) only if no other plugin has claimed it first.
  - `Module::enqueue_widget_scripts_for_preview()` (hooked to `elementor/preview/enqueue_scripts`) force-enqueues `swiper` + `ibb-rentals-elementor-carousel` inside the preview iframe.
  - `rebindLayout()` JS helper calls `swiper.update()` after every `<img>` load, on container `ResizeObserver` events, and on 100ms + 500ms `setTimeout` backstops — recomputes slide widths once the container actually has a measured width.
  - CSS guards: `box-sizing: border-box` on all descendants + `max-width: 100%` on the root, `.swiper`, slides, and images so a transient miscalculation can't escape the container.
  - Plus a pre-init flex fallback: `.swiper:not(.swiper-initialized) .swiper-wrapper` flexes slides so they remain visible even if Swiper never inits.
- **Diagnostic editor placeholder** on `PropertyCarouselWidget`. When `render()` would exit silently (no property resolved, or resolved property has no images in the chosen gallery), the widget now emits an `.ibb-property-carousel-placeholder` warning box explaining which path was hit — but only inside Elementor's editor / preview mode. Front-end stays silent. Pattern documented in `TROUBLESHOOTING.md` for any new leaf widget.
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
