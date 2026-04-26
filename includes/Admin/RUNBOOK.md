# Admin — Runbook

## Add a new field to the property metabox

1. Add a `Domain/Property::accessor()` (with default fallback) for the new field.
2. Pick the right tab in `PropertyMetaboxes::render_*` and add a row using the `row()` + `number()` / `text()` / `time()` helpers.
3. Add the postmeta key to either the `$numeric_keys` or `$text_keys` list inside `save()` so it gets sanitized and persisted.
4. If the field is JSON-shaped, follow the `_ibb_los_discounts` / `_ibb_blackout_ranges` pattern: validate-on-save with `json_decode` and re-encode.

## Add a new admin page under "Rentals"

1. In `Menu::add_pages`, add another `add_submenu_page` call against `Menu::PARENT`.
2. Register a `render_<page>()` callback. Use a `wp_nonce_field` + `maybe_save_<page>()` if you need a form.

## Add a new gallery without manually re-saving

The Photos tab serializes gallery state to a hidden `<textarea>` on every change. As long as the post is then saved (Update button), the new gallery persists. There is no separate save action.

## Inspect what gallery JSON is stored

```sql
SELECT meta_value FROM wp_postmeta WHERE post_id = <PROPERTY_ID> AND meta_key = '_ibb_galleries';
```

Or in PHP:
```php
$galleries = (new IBB\Rentals\Domain\Property( $id, get_post( $id ) ))->galleries();
```
