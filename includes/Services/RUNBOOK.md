# Services — Runbook

## Add a new pricing rule (e.g. tax surcharge, last-minute discount)

1. Add the field to `Domain/Property.php` (postmeta accessor with default).
2. Wire the storage into `Admin/PropertyMetaboxes`.
3. Apply it in `PricingService::get_quote()` — keep it ordered correctly:
   - Per-night rates → weekend uplift → nightly subtotal → LOS discount → fees → total → deposit split.
4. Surface in the `Quote` DTO if it's a separate line, or fold into an existing line.
5. Update the front-end JS in `Frontend/Assets::js()` to render the new line.

## Add a new booking-rule validation

`AvailabilityService::validate_booking_rules` is the single place. Add a new check that returns `WP_Error` on failure or falls through. Keep error codes consistent (e.g. `min_nights`, `blackout`, `unavailable`) — the cart and front-end map codes to messages.

## Manually mark a booking as confirmed (after manual balance payment)

```php
$plugin = IBB\Rentals\Plugin::instance();
$plugin->booking_repo()->update( $booking_id, [
    'status' => IBB\Rentals\Repositories\BookingRepository::STATUS_CONFIRMED,
    'deposit_paid' => /* total */,
    'balance_due' => 0,
] );
```

Then unschedule any pending balance action:
```php
as_unschedule_action( 'ibb_rentals_charge_balance', [ $booking_id ], 'ibb-rentals' );
as_unschedule_action( 'ibb_rentals_send_payment_link', [ $booking_id, 'first' ], 'ibb-rentals' );
as_unschedule_action( 'ibb_rentals_send_payment_link', [ $booking_id, 'reminder' ], 'ibb-rentals' );
```

## Inspect what gateways are token-capable

```php
$plugin = IBB\Rentals\Plugin::instance();
print_r( $plugin->gateway_capabilities()->active_gateway_summary() );
```

Or open any property's edit screen — the Booking-rules tab shows the matrix.
