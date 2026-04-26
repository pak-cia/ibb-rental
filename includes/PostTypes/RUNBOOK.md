# PostTypes — Runbook

## Add a new taxonomy

1. Add a constant on `PropertyPostType` (e.g. `TAX_TAG = 'ibb_tag'`).
2. Add a `register_taxonomy()` call inside `register_taxonomies()` with property-specific labels.
3. Include it in the `taxonomies` array on the CPT registration so it shows in the admin sidebar.
4. After deploying, a rewrite flush is required so the term archive URL works. `Setup/Installer::maybe_flush_rewrites` will self-heal on next page load.

## Change the public archive slug from `/properties/`

Update the `rewrite => slug` argument in `register_post_type`. Then bump `Setup/Installer::activate` to set the flush flag, OR delete `wp_options.rewrite_rules` to force a flush.

Note: changing this breaks every existing property URL. Plan a redirect strategy before doing it on a live site.

## Hide the CPT from the front-end without un-registering

`'public' => false` would hide it but also hide the admin UI. Instead, set `'publicly_queryable' => false` and `'has_archive' => false` to make permalinks 404 while keeping admin editing intact. (Use case: launching a site where properties exist but aren't ready to be public.)
