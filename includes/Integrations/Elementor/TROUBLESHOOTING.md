# Integrations / Elementor — Troubleshooting

## PropertyAvailabilityWidget — three known bugs (fix pending)

### 1. "Show legend" toggle has no effect — legend always displays

**Symptom:** toggling the "Show legend" switcher off in the widget panel has no effect; the legend renders regardless.

**Root cause:** Elementor's `SWITCHER` control returns `'yes'` when on and `''` (empty string) when off. `PropertyAvailabilityWidget::render()` passes `$settings['legend']` directly to `Shortcodes::render_calendar()`, which checks `!== 'no'`. An empty string is not `'no'`, so `$legend` evaluates to `true` and the legend always renders.

**Fix needed:** in `PropertyAvailabilityWidget::render()`, translate the switcher value before passing it:
```php
'legend' => ( $settings['legend'] ?? 'yes' ) === 'yes' ? 'yes' : 'no',
```

### 2. Calendar width locked — does not fill parent container

**Symptom:** the calendar renders at a fixed pixel width (Flatpickr's own hard-coded size) rather than stretching to fill the Elementor column or container it sits in.

**Root cause:** Flatpickr's inline calendar element has `width` and `display` hard-coded by its own stylesheet. No overriding CSS is applied by the plugin to let `.ibb-calendar` or `.flatpickr-calendar` take `100%` of the parent.

**Fix needed:** add to the plugin's frontend stylesheet:
```css
.ibb-calendar { width: 100%; }
.ibb-calendar .flatpickr-calendar { width: 100% !important; }
.ibb-calendar .flatpickr-days,
.ibb-calendar .dayContainer { width: 100% !important; max-width: 100% !important; min-width: 0 !important; }
.ibb-calendar .flatpickr-day { flex: 1; max-width: none; }
```

### 3. Multi-month view does not collapse on narrow containers

**Symptom:** with "Months to show" set to 2 (or 3), months sit side-by-side regardless of available width, causing overflow or a squashed layout on mobile or narrow columns.

**Root cause:** Flatpickr renders all months as siblings in a fixed-width row. There is no CSS breakpoint or JS logic to reduce `showMonths` when the container is too narrow.

**Fix needed:** `ResizeObserver` on `.ibb-calendar` — when the container width drops below the threshold for the current month count (~560 px for 2 months, ~840 px for 3), destroy and re-init Flatpickr with `showMonths` reduced accordingly. Restore the original count when the container widens again. The threshold should be based on Flatpickr's per-month minimum width (~280 px) × the configured month count.

---

## BookingFormWidget — stepper and Book button borders not controllable

**Symptom:** the `−` / `+` stepper divider borders and the Book Now button border have colours that cannot be changed from the Elementor widget panel.

**Root cause — stepper inner borders:** `input_border_color` targets `.ibb-booking__stepper` (the outer wrapper) and `.ibb-booking__field > input`, but the internal dividers are separate rules with hardcoded `#cbd5e1`:
```css
.ibb-booking__step--down { border-right: 1px solid #cbd5e1; }
.ibb-booking__step--up   { border-left:  1px solid #cbd5e1; }
```
These are not included in the control's `selectors` map.

**Root cause — Book button border:** the submit button CSS sets `border: 0`, but no `Group_Control_Border` is registered in `section_style_button`, so any theme or browser override cannot be corrected from the panel.

**Fix needed:**

1. In `BookingFormWidget::register_style_controls()`, extend the `input_border_color` control's `selectors` to include the inner dividers:
```php
'selectors' => [
    '{{WRAPPER}} .ibb-booking__field > input'  => 'border-color: {{VALUE}};',
    '{{WRAPPER}} .ibb-booking__stepper'         => 'border-color: {{VALUE}};',
    '{{WRAPPER}} .ibb-booking__step--down'      => 'border-right-color: {{VALUE}};',
    '{{WRAPPER}} .ibb-booking__step--up'        => 'border-left-color: {{VALUE}};',
],
```

2. Add a `Group_Control_Border` to `section_style_button` (inside the Normal tab, after `button_bg`):
```php
$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
    'name'     => 'button_border',
    'selector' => '{{WRAPPER}} .ibb-booking__submit',
] );
```

---

## DON'T hook `elementor/loaded` for tag/widget/control registration

**The most important note in this file.** Elementor fires `elementor/loaded` from its main plugin file **during** `wp-settings.php`'s plugin-load loop — that is, **before** WP's `plugins_loaded` action runs.

Our `Plugin::boot()` runs at `plugins_loaded` priority 20. If `Module::register()` does `add_action('elementor/loaded', …)` from inside it, the handler is added AFTER the action has already fired and never runs. Result: silent failure — no group, no tag, nothing in the dynamic-tag picker, no error in the log.

**Hook directly to the registration action you actually need:**

| Registering | Hook |
|---|---|
| Dynamic tags | `elementor/dynamic_tags/register` |
| Widgets | `elementor/widgets/register` |
| Controls | `elementor/controls/register` |
| Theme-builder hooks | `elementor/theme/register_locations` |

Each of these fires DURING editor / manager init, well after `plugins_loaded`, AND only exists when Elementor itself is loaded — so it doubles as the "is Elementor active?" gate. No need for `elementor/loaded`.

This was the bug that stopped the Property Gallery dynamic tag from appearing after the original integration was written. Fix landed when this entry was added.

## "IBB Rentals → Property Gallery" doesn't appear in the dynamic-tag picker

**Likely causes (after the timing-bug fix above is in place):**

1. **The widget structurally can't accept a gallery dynamic tag.** Widgets that use a `Repeater` control for slides (Pro's Media Carousel, Pro Slider, Slides widget) cannot consume array-returning gallery tags — each slide is its own item with its own single-image control. See [RUNBOOK.md → widget compatibility](RUNBOOK.md#which-elementor-widgets-is-the-dynamic-tag-compatible-with) for the full table. **Diagnostic:** if WC's "Product Gallery" or Elementor's "Featured Image Gallery" doesn't appear in this widget either, then no gallery dynamic tag will — it's a widget-design constraint, not a bug.
2. **Elementor isn't Pro.** Dynamic tags are an Elementor Pro feature. Without Pro, `elementor/dynamic_tags/register` never fires.
3. **The tag class file errored during `require_once`.** Check the WP error log for `IBB\Rentals\Integrations\Elementor\DynamicTags\PropertyGalleryDynamicTag` parse / type errors.
4. **Editor cache.** Hard-refresh the editor browser tab. If still missing, run Elementor → Tools → Regenerate Files & Data.

## Widget renders as an empty grey block in the editor

**Symptom:** drop a widget (Property Carousel, Property Gallery, Booking Form, Property Details) on a generic Elementor page (e.g. "Elementor #36" — not an `ibb_property` post). The widget shows in the structure panel but renders as an empty grey area in the preview.

**First, check the editor for a yellow warning box.** All widgets emit an `ibb-property-carousel-placeholder`-style box (yellow with a dashed border) in editor / preview mode when `render()` would otherwise exit empty. The text tells you which path was hit:

| Placeholder text | Meaning |
|---|---|
| "No property could be resolved…" | `Module::resolve_property_for_widget()` returned null. Property dropdown is set to a value that doesn't exist, or the resolver's first-property fallback also fails (no published properties). |
| "Property X has no images in Y…" | Property resolved fine; the chosen gallery slug is empty (or the property has zero attachments across galleries). Open the property → Photos tab. |

If neither placeholder is showing AND the area is still grey, the widget is rendering markup but Swiper isn't initialising. See "Carousel renders empty grey rectangle in Elementor 4.x editor" below.

**Root cause for the resolver case:** the widget defaults Property to "Current page" → `get_the_ID()` returns the page's own ID → that's not an `ibb_property` post → `Property::from_id()` returns null → `render()` exits with no markup.

**Fix:** `Module::resolve_property_for_widget()` is the single resolver used by all four widgets and the dynamic tag. Order:

1. If a specific property is picked, use it.
2. If "Current page" is picked: use the current post if it's an `ibb_property`.
3. Otherwise fall back to the **first published property**.

The fallback is an editor-preview convenience so widgets show *something* while configuring them on a non-property page. On a real single-property template the current property always wins, so production rendering is unaffected.

**If you specifically want a non-property page to render the widget empty when no property is in scope,** pick the property explicitly in the control (don't rely on "Current page").

## Carousel renders huge / empty grey rectangle in Elementor 4.x editor

**Symptom:** Property Carousel renders an empty grey rectangle, OR a single image scaled to absurd dimensions (e.g. 33,554,400 × 33,554,400 px), inside the Elementor editor preview iframe. Saved + viewed on the front-end, it renders fine.

**Diagnostic:** with the preview iframe's devtools open, inspect a `.swiper-slide` — if its inline style shows `width: 3.355e+07px`, that's Swiper computing slide width from a container that had width 0 at init time and locking in `slidesPerView: 1` math against it.

**Root causes (compound):**

1. Elementor 4.x atomic-widgets pipeline doesn't always carry `get_script_depends()` into the preview iframe → Swiper handle wasn't loading inside the editor.
2. Even when Swiper loads, the editor preview iframe sometimes inits Swiper *before* the parent flex container has a measured width — Swiper sees 0 width and produces a useless layout.
3. With `loop: true`, Swiper duplicates slides; combined with the absurd width, the duplicates push the wrapper to `transform: translate3d(-3.355e+07px, …)`.

**Fix in place (four layers):**

- **Script availability**: defensive `swiper` handle registration in `Module::register_widget_scripts()` (jsDelivr fallback only when no one else has claimed the handle), and force-enqueue inside the preview iframe in `Module::enqueue_widget_scripts_for_preview()` (hooked to `elementor/preview/enqueue_scripts`).
- **Layout rebinding**: `rebindLayout()` in `carousel_init_js()` runs after each Swiper instance is constructed. It calls `swiper.update()` once each `<img>` finishes loading, again whenever the container's `ResizeObserver` fires, and twice on a setTimeout backstop (100ms + 500ms) for cached images + browsers without ResizeObserver. Recomputes slide widths against the now-real container width.
- **CSS guards**: `box-sizing: border-box` on every descendant; `max-width: 100%` on the carousel root, the `.swiper`, every slide, and the `<img>` so even a transient miscalculation by Swiper can't escape the container.
- **Pre-init flex fallback**: `.swiper:not(.swiper-initialized) .swiper-wrapper { display:flex; flex-wrap:wrap; gap:8px; }` so a never-inited Swiper still shows the slides visibly.

**Diagnostic flowchart if the issue returns:**

1. Inspect a `.swiper-slide` in devtools.
   - Width is `3355…px` → layout-rebinding broke; check the console for "swiper.update is not a function" or that the `rebindLayout` `setTimeout`s actually fire.
   - Width is reasonable but the slide is invisible → CSS broke; check `display:flex` on `.swiper-wrapper`.
2. Run `typeof window.Swiper` in the preview iframe's console.
   - `'undefined'` → Swiper script didn't load; check Network for the jsDelivr URL.
   - `'function'` → Swiper loaded; init-time bug.
3. Run `document.querySelector('.ibb-property-carousel__main').swiper` in the iframe console — should return a Swiper instance with `params`, `slides`, etc. If null, the `frontend/element_ready/ibb_property_carousel.default` hook never fired.

## Editor placeholder pattern (for new widgets)

When adding a new widget under `Widgets/`, emit a visible diagnostic placeholder in editor / preview mode whenever `render()` would exit silently. The pattern (copy from `PropertyCarouselWidget::editor_placeholder()`):

```php
private function editor_placeholder( string $message ): void {
    if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
        return;
    }
    $is_editor  = \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode();
    $is_preview = \Elementor\Plugin::$instance->preview && \Elementor\Plugin::$instance->preview->is_preview_mode();
    if ( ! $is_editor && ! $is_preview ) {
        return;
    }
    echo '<div class="ibb-property-carousel-placeholder">' . esc_html( $message ) . '</div>';
}
```

Front-end stays silent (returns nothing) when there's nothing to render; editor authors get a clear "why doesn't this work" hint. The shared `.ibb-property-carousel-placeholder` CSS class (in `Frontend/Assets.php`) styles it as a yellow warning box.

## Tag returns empty array (no images render)

**Likely causes:**

1. The selected property has no galleries configured. Open the property → Photos tab → add a gallery + images.
2. The selected gallery slug doesn't exist on this property (different properties can have different slugs). Pick "All photos" or one of the slugs that actually exists on the property the tag will render against.
3. **Editor cache.** After picking the dynamic tag and configuring it, hard-refresh the editor tab. Elementor caches dynamic-tag values aggressively; sometimes a fresh render is needed.

The `Current page` selection has a built-in fallback: if the page being rendered is NOT a property post, `resolve_property()` falls back to the first available property. This avoids the silent "no images" trap when previewing the tag on a generic Elementor page during editing. On a real single-property template the current property always wins.

## Gallery dropdown shows slugs from properties I don't expect

**Cause:** `Module::gallery_slug_options()` collects the union of every distinct gallery slug across every property. If two properties both define a `pool` gallery, the dropdown shows one entry; if only Property A has `bedroom-3`, the dropdown still shows `bedroom-3` (and rendering on Property B with that slug will return no images, which is fine — at-render-time the tag looks up the slug on the actual rendering property, doesn't find it, returns empty).

This is intentional: slugs are global to the plugin (a property's "bedroom-1" means the same conceptual room as another property's "bedroom-1"), so editors don't need to per-property-pick the slug. Per-property-conditional slug dropdowns are a v1.1+ feature.

## Adding a property doesn't show up in the SELECT2 immediately

**Cause:** `Module::property_options()` is cached per-request and Elementor caches editor controls aggressively. Reload the editor (full browser refresh) after creating the new property — that's a fresh request, fresh cache.

## Fatal: "Class \\Elementor\\Core\\DynamicTags\\Data_Tag not found"

**Cause:** the tag class file was loaded BEFORE Elementor's autoloader. This happens if you reference the tag class directly (e.g. via `new` or PSR-4 autoload) outside of `Module::register_tags()`.

**Fix:** all references to Elementor base classes must happen inside the `register_tags()` callback (which fires on `elementor/dynamic_tags/register`, after Elementor is fully loaded). The tag file itself has a `class_exists` guard at the top that early-returns if Elementor isn't around — but if you skip that pattern when adding new files, you lose the protection.

## Editor shows "(deprecated)" warning on the tag

**Cause:** Elementor 3.5+ migrated from `register_tag` to `register`. Our `Module::register_tags()` calls the new method first, falls back to `register_tag` only on older Elementor. If you see deprecation warnings, you're on a pre-3.5 Elementor — upgrade.
