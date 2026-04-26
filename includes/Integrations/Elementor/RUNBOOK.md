# Integrations / Elementor — Runbook

## Use the Property Gallery dynamic tag

In Elementor's editor:

1. Add any widget that uses Elementor's **GALLERY** control (see widget compatibility below).
2. Click the Images control's dynamic-tag (database) icon.
3. Pick **IBB Rentals → Property Gallery**.
4. Set:
   - **Property** → `Current page` (default; auto-resolves from `get_the_ID()` on a property's single template) or a specific property by name.
   - **Gallery** → "All photos" (default — combines every gallery) or a specific named gallery slug.
5. Save the layout.

The tag re-evaluates on every render — switching properties (e.g. on a single-property template viewing different posts) automatically picks up the right images.

## Which Elementor widgets is the dynamic tag compatible with?

The tag declares the `gallery` dynamic-tag category and returns an array of `{id}` items. It works in **any widget that uses an `Elementor\Controls_Manager::GALLERY` control** with `dynamic.active = true`.

**Compatible widgets** (verified):

| Widget | Source | Control type |
|---|---|---|
| Basic Gallery (Image Gallery) | Elementor Free | GALLERY |
| Image Carousel | Elementor Free | GALLERY |
| Pro Gallery | Elementor Pro | GALLERY |
| Any third-party widget that uses a GALLERY control with dynamic active | — | GALLERY |

**Not compatible** (structural mismatch — these widgets expect single images per slide via a Repeater control, not an array):

| Widget | Source | Control type | Why |
|---|---|---|---|
| Media Carousel | Elementor Pro | REPEATER (slides) | Each slide is its own item with its own IMAGE control; can't be populated from an array-returning gallery tag |
| Slides | Elementor Pro | REPEATER (slides) | Same |
| Pro Slider | Elementor Pro | REPEATER (slides) | Same |

**Workaround for incompatible widgets:** drop our `[ibb_gallery]` shortcode (or the `IBB · Property gallery` Gutenberg block) into a Shortcode widget / HTML widget instead. The shortcode renders a full lightbox-enabled grid (your slider's animated layout isn't preserved, but the photos render).

## Add a new dynamic tag

1. Create `DynamicTags/<Name>DynamicTag.php` extending the appropriate Elementor base class (`Data_Tag` for value-returning, `Tag` for text-returning).
2. Set namespace `IBB\Rentals\Integrations\Elementor\DynamicTags`.
3. Inside the file, gate on `class_exists('\Elementor\Core\DynamicTags\Data_Tag')` at the top; `return` early if Elementor isn't loaded.
4. In `Module::register_tags()`, add a matching `require_once __DIR__ . '/DynamicTags/<Name>DynamicTag.php';` followed by `$manager->register( new \IBB\Rentals\Integrations\Elementor\DynamicTags\<Name>DynamicTag() );`.
5. The tag's `get_group()` should return `'ibb-rentals'` so it appears in our group; `get_categories()` controls which widget controls expose it.

## Add a custom Elementor widget (when needed)

1. Create `Widgets/<Name>Widget.php` extending `\Elementor\Widget_Base`. Same lazy-require + `class_exists` gating as dynamic tags.
2. Add `Module::register_widgets()` hooked to `elementor/widgets/register`.
3. Inside `register_widgets()`, `require_once` the widget file and call `$widgets_manager->register( new $cls() )`.
4. Wire the new method into `Module::on_elementor_loaded()` so it fires alongside `register_tags`.

## Force-refresh the property list inside Elementor's panel

`Module::property_options()` caches per request. Reload the editor (full browser refresh) after adding a new property — the cache is one-request-only, so the next editor load picks up the change.

## Disable the integration without uninstalling Elementor

Filter `elementor/loaded` to return early, OR comment out `( new ElementorModule() )->register()` in `Plugin::boot()`. The module is gated on its own action, so removing the registration is safe.
