# PostTypes — Troubleshooting

## Taxonomy edit screen shows "Jazz / Bebop" example phrasing

**Symptom:** the Locations or Property Types term-edit form shows WordPress's default example like "Jazz... would be the parent of Bebop and Big Band".

**Root cause:** `*_field_description` labels weren't set, so WP fell back to its own default.

**Fix:** custom `parent_field_description`, `slug_field_description`, `name_field_description`, `desc_field_description` are set per taxonomy in `register_taxonomy()` calls. The example for Locations references "Bali → Seminyak / Ubud"; for Property Types "Villa → Beach Villa / Garden Villa"; for Amenities the comma-separated example "Pool, Wi-Fi, Air Conditioning".

**Note:** `*_field_description` labels were added in WP 6.6. On older WP versions, the labels silently fall back to WP defaults — that's expected.

---

## Property archive URL `/properties/` returns 404

See [Setup/TROUBLESHOOTING.md](../Setup/TROUBLESHOOTING.md#property-urls-return-404-right-after-activation). Cause is in the rewrite-flush flow, not in the CPT registration itself.

---

## CPT doesn't appear in Elementor's "Posts" / dynamic-tag pickers

**Likely cause:** `'show_in_rest' => true` is required for Gutenberg AND for many Elementor integrations. Verify it's set on `register_post_type()`. (We have it set; if it's missing in a fork, add it back.)
