# Repositories — Troubleshooting

## Overlap detection is slow on large property catalogs

**Symptom:** `/quote` takes >100ms; `EXPLAIN` shows a table scan on `wp_ibb_blocks`.

**Likely causes:**
- The `property_dates` compound index is missing. Check `SHOW INDEXES FROM wp_ibb_blocks` — should list `(property_id, start_date, end_date)`.
- Migrations haven't run. Delete `ibb_rentals_db_version` and reload to force a fresh `dbDelta`.

## `upsert_by_uid` produces duplicate rows

**Likely cause:** the `source_uid` unique key doesn't match the values being inserted. The unique key is on `(property_id, source, external_uid)`. If you've changed an iCal feed's `source` value at the registry level without cleaning up old blocks, you'll get duplicates with different sources.

**Fix:** before changing a feed's source, run:
```sql
DELETE FROM wp_ibb_blocks WHERE property_id = <ID> AND source = '<OLD_SOURCE>';
```

Then change the registry, then trigger a sync.

## `delete_stale_by_source` deletes everything

**Likely cause:** an empty `$keep_uids` argument means "delete all rows for this `(property_id, source)` pair" — that's the documented behaviour for "the feed is empty / no events." If a feed legitimately empties (the OTA cleared the calendar), this is correct.

If you're seeing this at unexpected times, log `count($events)` inside `Importer::import()` to see what the parser produced. A parse failure that returns 0 events would trigger the same wipe — but the importer only calls `delete_stale_by_source` after a successful HTTP fetch + parse, so this should be rare.
