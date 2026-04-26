# Ical — Troubleshooting

## Browser downloads `.ics` instead of displaying it

**Not a bug.** `Content-Type: text/calendar` is correctly handled by browsers as a calendar feed: some open it in the OS calendar app, others trigger a download. Airbnb / Booking.com / Agoda fetch via HTTP and consume the body directly — they don't care about browser display.

**To inspect the body:** open the downloaded file in a text editor, or `curl -i <feed-url>`.

---

## Feed URL returns 404

**Likely causes:**

1. **Property is not published.** `IcalController::handle` rejects non-`publish` posts. Drafts and private posts return 404 by design.
2. **Token mismatch.** Bad/missing token returns 404 (not 401) deliberately, so the endpoint doesn't reveal which property IDs exist. Regenerate via the property's iCal tab.
3. **REST routes missing.** Check `/wp-json/ibb-rentals/v1` — if that 404s, REST itself isn't loading. Check that `RouteRegistrar` is being instantiated in `Plugin::boot()`.

---

## Feed URL has weird `?rest_route=…?token=…` shape on plain permalinks

**Symptom:** the URL renders as `https://site.test/?rest_route=/ibb-rentals/v1/ical/15.ics?token=abc` and OTAs reject it.

**Root cause:** `rest_url()` returns `?rest_route=...` on sites with plain permalinks. Naively appending `&token=...` (or `?token=...`) breaks the query string.

**Fix:** `Exporter::feed_url` uses `add_query_arg()` which handles both pretty and plain permalink shapes correctly.

---

## Imported events double-up after iCal sync

**Likely cause:** the same OTA UID is being seen with two different `source` values (e.g. once as `airbnb`, once as `other`). The unique key on `(property_id, source, external_uid)` doesn't dedupe across `source` values.

**Fix:** make sure each registered feed has the right `source` value. The known sources are `airbnb`, `booking`, `agoda`, `vrbo`, `other`. If you've registered a feed twice with different labels, delete one.

---

## Imported feed silently drops events

**Likely causes:**

1. **Body too large.** `wp_safe_remote_get` is capped at `limit_response_size: 10 * MB_IN_BYTES`. Most OTA feeds are far below this; if you're importing a multi-year feed for a high-volume property, raise the cap.
2. **`wp_safe_remote_get` blocks the host.** It refuses internal IPs and a few other patterns. If you're testing against a local mock OTA on `127.0.0.1`, you'll need to use `wp_remote_get` directly (and re-evaluate the security implications).
3. **VEVENT format the parser doesn't understand.** Open the feed manually and grep for unusual structures (`EXDATE`, `RRULE` with BYDAY/BYMONTHDAY, `RECURRENCE-ID`). The in-house parser covers the common dialects — for richer needs, switch to `sabre/vobject` (see RUNBOOK).

---

## Import "succeeds" but `wp_ibb_blocks` doesn't change

**Likely cause:** the feed returned 304 Not Modified — `Importer::import` returns true and updates `last_synced_at` but doesn't reparse the body. This is correct behaviour. If you've manually changed something on the OTA side, wait for the OTA to update its `Last-Modified` header (a few minutes) and retry.

To force a fresh parse, clear the feed's `etag` and `last_modified` columns:
```sql
UPDATE wp_ibb_ical_feeds SET etag = '', last_modified = '' WHERE id = <FEED_ID>;
```
