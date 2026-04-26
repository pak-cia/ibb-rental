# Woo — Runbook

## Add a new gateway to the auto-charge allowlist

`GatewayCapabilities::token_capable_gateway_ids()` returns the curated list. Add the gateway's WC ID (the `id` property on its `WC_Payment_Gateway` subclass — e.g. `'stripe'`, `'woocommerce_payments'`, `'ppcp-gateway'`).

To add at runtime without editing source:
```php
add_filter( 'ibb-rentals/gateways/token_capable', function( $ids ) {
    $ids[] = 'my-gateway-id';
    return $ids;
} );
```

## Inspect what cart-item meta a booking line carries

```php
add_action( 'woocommerce_check_cart_items', function() {
    foreach ( WC()->cart->get_cart() as $item ) {
        if ( ! empty( $item['ibb'] ) ) {
            error_log( 'IBB cart item: ' . print_r( $item['ibb'], true ) );
        }
    }
} );
```

Or check the order's line-item meta after checkout via the order edit screen — the `_ibb_*` keys are stored as hidden line-item meta plus a few human-readable Check-in / Check-out / Guests entries.

## Verify HPOS compatibility

```bash
grep -rn 'get_post_meta\|update_post_meta' includes/Woo/
```

Should return zero matches. All order-meta reads/writes go through the order or order-item objects.

## Force resync of a property's mirrored product

Touch the property's title or any other field and Update — `ProductSync::sync` runs on every `save_post_ibb_property` and reapplies the mirror. To recreate from scratch: delete the `_ibb_linked_product_id` postmeta and save the property again; `sync` creates a fresh product.

## Trigger a balance charge attempt manually

```php
$plugin = IBB\Rentals\Plugin::instance();
$plugin->balance_service()->charge( $booking_id );
```

The lock is per-booking via `wp_options.ibb_balance_lock_<id>`; if a worker is mid-flight this returns immediately.
