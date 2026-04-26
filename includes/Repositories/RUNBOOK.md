# Repositories — Runbook

## Add a new query method

1. Pick the relevant repo file (or create a new one if you've added a table).
2. Use intent-named methods, not generic ones. `find_active_for_property` beats `find(['active' => true, 'property_id' => $id])`.
3. Always use `$wpdb->prepare()` for any user-derived input.
4. If returning Domain objects, hydrate via `Block::from_row()` (etc).
5. Document the index that backs the query in a code comment if it's hot-path.

## Verify the compound index is being used for overlap detection

```sql
EXPLAIN SELECT 1 FROM wp_ibb_blocks
WHERE property_id = 15 AND status IN ('confirmed','tentative')
  AND start_date < '2026-06-10' AND end_date > '2026-06-05'
LIMIT 1;
```

The `key` column should show `property_dates`. If it shows `NULL` or `PRIMARY`, the index is either missing or being ignored — check `Schema.php` and re-run migrations.

## Inspect a property's full block ledger

```sql
SELECT id, source, status, start_date, end_date, external_uid, summary, order_id
FROM wp_ibb_blocks
WHERE property_id = <ID>
ORDER BY start_date;
```

Sources to expect: `direct` (this site's bookings), `manual` (admin-blocked dates), `airbnb`/`booking`/`agoda`/`vrbo`/`other` (imported), `hold` (transient, cleared by CleanupHoldsJob every 5 min).

## Force-clear all hold blocks

```sql
DELETE FROM wp_ibb_blocks WHERE source = 'hold';
```

The cleanup job clears these every 5 min — manual deletion is only useful when debugging.
