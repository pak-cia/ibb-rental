# Rest — Troubleshooting

## `/wp-json/ibb-rentals/v1` returns 404 for everything

**Likely causes:**

1. **REST API itself is disabled.** Some security plugins block REST for non-logged-in users. Whitelist our namespace.
2. **`RouteRegistrar` not booted.** Check `Plugin::boot` — it should call `(new RouteRegistrar($this))->register()` unconditionally. The actual route registration happens on `rest_api_init`.
3. **Permalinks not flushed.** Settings → Permalinks → Save. (Same root cause as the property-page 404 issue.)

## `/quote` returns 422 with code "min_nights" / "blackout" / "max_guests"

These are validation rejections from `Services/AvailabilityService::validate_booking_rules`. The error code maps to a property-config setting:

| code | setting |
|---|---|
| `min_nights` | `_ibb_min_nights` |
| `max_nights` | `_ibb_max_nights` |
| `advance_booking` | `_ibb_advance_booking_days` |
| `too_far_ahead` | `_ibb_max_advance_days` |
| `blackout` | `_ibb_blackout_ranges` |
| `max_guests` | `_ibb_max_guests` |
| `unavailable` | overlap with an existing block |
| `past_date` | check-in is before today |

Fix: either change the property's settings or pick different dates.

## `/ical/{id}.ics` returns 200 but the body is JSON-encoded HTML

**Likely cause:** something prevented `exit` in `IcalController::handle` from running, so WP's REST serializer ran and wrapped the body. Look for fatal errors in the error log or output buffering misbehaviour.

The handler is intentionally written to emit raw output:
```php
$this->emit_headers( 200, $etag );
echo $body;
exit;
```

If you're modifying it, keep the `exit;` — returning a `WP_REST_Response` with the .ics body will not produce a valid feed.

## Rate limit (429) on `/quote` during development

Increase the threshold in `QuoteController::rate_limit` (currently 30/min/IP) or delete the transient:
```bash
wp transient delete --all
```
