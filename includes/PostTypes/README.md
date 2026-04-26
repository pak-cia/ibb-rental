# PostTypes

Custom post type and taxonomy registration for properties.

## Files

- `PropertyPostType.php` — registers `ibb_property` CPT and three taxonomies: `ibb_amenity` (non-hierarchical), `ibb_location` (hierarchical), `ibb_property_type` (hierarchical). Property-specific UI labels throughout — including `*_field_description` strings on the term-edit screen.

## Key patterns

- **Slug `properties/`** — front-end archive at `/properties/`, single posts at `/properties/<slug>/`. `Setup/Installer::maybe_flush_rewrites` self-heals if these rules go missing.
- **REST-enabled** — `'show_in_rest' => true` so the CPT works in Gutenberg and is queryable via the WP REST API. `rest_base` is `ibb-properties` (plural).
- **Property-specific taxonomy labels** — replaces WP's default Jazz/Bebop example phrasing with property-relevant copy. Uses the `*_field_description` labels (added in WP 6.6+) — older WP versions silently fall back to WP defaults.
- **Menu position 26** — directly below "Comments" in the admin sidebar. Customize via the `register_post_type` `menu_position` argument.

## Connects to

- [../Setup](../Setup/README.md) — activator instantiates this so rewrite rules pick up the slug
- [../Plugin.php](../Plugin.php) — boot calls `(new PropertyPostType())->register()`
- [../Domain](../Domain/README.md) — `Property::POST_TYPE` constant matches `PropertyPostType::POST_TYPE`
- [../Woo](../Woo/README.md) — `ProductSync` listens to `save_post_ibb_property`

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
