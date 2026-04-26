# Services — Troubleshooting

## Quote returns `payment_mode: full` despite property being set to deposit mode

**Likely cause:** `PricingService::split_payment` auto-falls-back to `full` when the balance due date is in the past or less than 2 days away. This is intentional — you can't schedule a balance charge for a date that's already gone.

**Verify:** check the property's `_ibb_balance_due_days_before` setting. If it's 14 and the check-in is only 10 days out, the balance would be due 4 days ago → fall-back kicks in.

**Workaround:** lower the balance lead time, or push the check-in date out, or manually keep payment mode on `full` for short-notice bookings.

## Booking row created but block status stays `cancelled`

**Likely cause:** the order was processed → cancelled → reprocessed in quick succession. `BookingService::cancel_for_order` flips block status to `cancelled` but leaves the row (audit trail). On `on_paid` re-fire, `upsert_by_uid` updates the block status back to `confirmed` — but only if the unique-key match works. If the `external_uid` shape changed between attempts, you'd get a duplicate.

**Verify:** `external_uid` should be `order:<id>:item:<item-id>`. Same value across both runs.

## Balance charge succeeds but booking still shows `balance_pending`

**Likely cause:** `BalanceService::charge` updated WC's order but the hook to update our booking row didn't fire. Check the WC log (`source: ibb-rentals`) for the `Balance charged` entry.

**Manual fix:**
```php
$plugin->booking_repo()->update( $booking_id, [
    'status' => IBB\Rentals\Repositories\BookingRepository::STATUS_CONFIRMED,
    'balance_due' => 0,
] );
```

## `as_unschedule_action` doesn't cancel the scheduled balance

**Likely cause:** the args don't match exactly. `as_unschedule_action` matches on `(hook, args, group)` together. If the originally scheduled args were `[123]` and you call `as_unschedule_action('...', [123, 'extra'], 'ibb-rentals')` it won't match.

The args used when scheduling:
- `ibb_rentals_charge_balance` → `[ $booking_id ]`
- `ibb_rentals_send_payment_link` → `[ $booking_id, $kind ]` where kind is `'first'`, `'reminder'`, or `'fallback'`

Pass them exactly as scheduled, or use `as_unschedule_all_actions($hook, [], $group)` to clear all instances.
