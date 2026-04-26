# Domain — Troubleshooting

## `DateRange::from_strings` throws "Checkout must be after checkin"

This is intentional. Same-day stays (`checkin === checkout`) are not bookable — a stay needs at least one night. If you have a use case for zero-night stays (day-use bookings), introduce a separate `DayUseBooking` flow rather than weakening the invariant.

## Quote `to_array()` shows `total: 0` after pricing a real range

**Likely cause:** the property's `_ibb_base_rate` is unset or 0, AND there are no `wp_ibb_rates` rows covering the requested dates. `PricingService` falls back to `Property::base_rate()` for nights without a rate row; if that's also 0 the total is 0.

Verify: `wp post meta get <property_id> _ibb_base_rate` — should be a positive decimal.

## `Property::galleries()` returns empty array despite galleries existing in the metabox

**Likely cause:** the `_ibb_galleries` postmeta is malformed JSON. The accessor defensively returns `[]` rather than throwing.

Verify: `wp post meta get <property_id> _ibb_galleries` — should be valid JSON like `[{"slug":"main","label":"Main","attachments":[101,102]}]`.

If the value is something else (e.g. a serialised PHP array — happens if older code wrote it without `wp_json_encode`), re-save the property to normalise it through `Admin/PropertyMetaboxes::save()`.
