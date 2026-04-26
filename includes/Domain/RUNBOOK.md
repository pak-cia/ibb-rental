# Domain — Runbook

## Add a new postmeta field to `Property`

1. Add an accessor in `Property.php` with a default fallback:
   ```php
   public function my_field(): float { return (float) $this->meta( '_ibb_my_field', 0 ); }
   ```
2. Add the storage to the metabox via `Admin/PropertyMetaboxes::render_*` and the `save()` whitelist.
3. (If used by pricing or availability) consume it from the relevant Service.

## Add a new field to `Quote`

1. Add a constructor parameter (readonly).
2. Add it to `to_array()` so REST/cart hand-off carries it.
3. Update `PricingService::get_quote()` to populate it.
4. Bump anyone consuming the JSON shape (cart handler line meta, frontend JS).

## Verify a quote token outside the request lifecycle

```php
$secret = (string) get_option( 'ibb_rentals_token_secret', '' );
$payload = IBB\Rentals\Domain\Quote::verify_token( $token, $secret, 900 );
// $payload === null if invalid or expired
```

## Iterate the nights in a range

```php
$range = IBB\Rentals\Domain\DateRange::from_strings( '2026-06-01', '2026-06-05' );
foreach ( $range->each_night() as $date ) {
    echo $date->format( 'Y-m-d' ), "\n";
}
// 2026-06-01, 2026-06-02, 2026-06-03, 2026-06-04
```

Note: yields the night (inclusive of checkin, exclusive of checkout), not the dates including checkout.
