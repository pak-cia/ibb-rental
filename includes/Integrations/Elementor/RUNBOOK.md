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

1. Create `DynamicTags/<Name>Tag.php` extending the appropriate Elementor base class (`Data_Tag` for value-returning — galleries, URLs, images; `Tag` for text-returning).
2. Set namespace `IBB\Rentals\Integrations\Elementor\DynamicTags`.
3. Inside the file, gate on `class_exists('\Elementor\Core\DynamicTags\Tag')` (or `Data_Tag`) at the top; `return` early if Elementor isn't loaded.
4. In `Module::register_tags()`, add the class name to the `$tags` array. The base loader requires the file, instantiates the class, and registers it — no per-tag boilerplate needed.
5. The tag's `get_group()` should return `'ibb-rentals'` so it appears in our group; `get_categories()` controls which widget controls expose it.

**For text-field property tags specifically, extend `AbstractPropertyFieldTag` instead** — it provides the shared Property picker control and resolver, so the leaf class only needs `get_name()`, `get_title()`, and `field_value(Property $p)`. See `PropertyTitleTag.php` for the canonical ~10-line example.

## Use IBB property fields in native Elementor widgets

Every property field is exposed as a dynamic tag, so Heading / Text Editor / Button / Image widgets can bind to property data without using our custom widgets. This is how Theme Builder integrators build single-property templates with full layout control.

| Tag | Category | Where it can be bound |
|---|---|---|
| Property Title | text | Heading text, Text Editor, any string control |
| Property Address | text | Heading, Text Editor |
| Max Guests / Bedrooms / Bathrooms / Beds | text | Heading, Text Editor (often paired with an Icon Box) |
| Base Rate (per night) | text | Heading, Text Editor — formatted via `wc_price()` |
| Check-in Time / Check-out Time | text | Heading, Text Editor |
| Property URL | url | Button link, Image link, any URL control |
| Property Image | image | Image widget, Background image, Loop Item image placeholder |
| Property Gallery | gallery | Any GALLERY control with `dynamic.active = true` (see widget compatibility above) |

All tags have a Property control with "Current page" default — on a single-property template that auto-resolves to the post being viewed; on a generic Elementor page it falls back to the first published property (editor-preview convenience).

## Use Theme Builder for the single-property template

`ibb_property` is registered as a public CPT with `has_archive: true` and `show_in_rest: true`. Elementor Pro's Theme Builder auto-detects public CPTs, so:

1. Templates → Theme Builder → Single → Add New.
2. In the dialog, Type = **Single**, "Choose post type" = **Property** (`ibb_property`).
3. Build your layout. Bind Heading widgets to the IBB > Property Title dynamic tag, an Image widget to IBB > Property Image, etc. The Property control on every IBB tag defaults to "Current page" — Theme Builder calls our render in the post's loop context, so the tag auto-resolves to the post being viewed.
4. Display Conditions → Include → All Properties (or pick specific ones).

Same flow for the property archive: Add New → Type = **Archive** → Source = **Properties**.

If "Properties" doesn't appear in the post-type dropdown when creating a Single template, the CPT registration may have lost its `public: true` flag — Elementor's Theme Builder filters by `is_post_type_viewable()`. Check `PostTypes/PropertyPostType::register_post_type()`.

## Build a property archive with Loop Grid

The plugin registers a custom Elementor query under the ID `ibb_properties`. To use it:

1. Drop a **Loop Grid** widget (Elementor Pro).
2. Click "Create Template" and design a single property card with native Elementor widgets — bind a Heading to the **Property Title** tag, an Image widget to **Property Image**, a Button's link to **Property URL**, etc.
3. Back on the Loop Grid: set **Advanced → Query ID** to `ibb_properties`. Elementor will then call our `Module::register_loop_query()` filter on the widget's WP_Query, swapping `post_type` to `ibb_property` and `post_status` to `publish`.
4. The widget's own Query controls (Order, Order By, Posts per page, Taxonomy filters using `ibb_amenity` / `ibb_location` / `ibb_property_type`) layer on top — our handler only sets defaults when the user hasn't.

## Add a custom Elementor widget (when needed)

1. Create `Widgets/<Name>Widget.php` extending `\Elementor\Widget_Base`. Same lazy-require + `class_exists` gating as dynamic tags.
2. Add `Module::register_widgets()` hooked to `elementor/widgets/register`.
3. Inside `register_widgets()`, `require_once` the widget file and call `$widgets_manager->register( new $cls() )`.
4. Wire the new method into `Module::on_elementor_loaded()` so it fires alongside `register_tags`.

## Wire colors / typography to Elementor Global kit slots

Every color / typography control on every IBB widget should adopt the active kit's tokens by default. That way switching kits or recolouring the site palette propagates to our widgets with zero per-widget edits, but the defaults are still overridable inline.

**Color controls** — pass the `global` key:

```php
$this->add_control( 'foo_color', [
    'label'     => __( 'Color', 'ibb-rentals' ),
    'type'      => \Elementor\Controls_Manager::COLOR,
    'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY ],
    'selectors' => [ '{{WRAPPER}} .ibb-foo' => 'color: {{VALUE}};' ],
] );
```

**Typography controls** — same idea on `Group_Control_Typography`:

```php
$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
    'name'     => 'foo_typography',
    'global'   => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY ],
    'selector' => '{{WRAPPER}} .ibb-foo',
] );
```

**Standard slot mapping for IBB widgets** (keep consistent across new widgets):

| Role | Color slot | Typography slot |
|---|---|---|
| Heading / value text | `COLOR_PRIMARY` | `TYPOGRAPHY_PRIMARY` |
| Body / label text | `COLOR_TEXT` | `TYPOGRAPHY_TEXT` |
| Accent fill (CTA bg, active state) | `COLOR_ACCENT` | — |
| CTA button text | — | `TYPOGRAPHY_ACCENT` |

Always scope the selector under `{{WRAPPER}}` so multiple instances on a page style independently.

## Force-refresh the property list inside Elementor's panel

`Module::property_options()` caches per request. Reload the editor (full browser refresh) after adding a new property — the cache is one-request-only, so the next editor load picks up the change.

## Disable the integration without uninstalling Elementor

Filter `elementor/loaded` to return early, OR comment out `( new ElementorModule() )->register()` in `Plugin::boot()`. The module is gated on its own action, so removing the registration is safe.
