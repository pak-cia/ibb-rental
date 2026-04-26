# Support — Runbook

## Add a new public hook

1. Add a constant to `Hooks.php` (e.g. `BOOKING_REMINDED = 'ibb-rentals/booking/reminded'`).
2. Reference it via `Hooks::BOOKING_REMINDED` at the call site (`do_action(Hooks::BOOKING_REMINDED, ...)`).
3. Document it in the relevant component's `README.md` "emits" section if integrators are likely to hook into it.

## Find the plugin's logs

WooCommerce → Status → Logs. Filter by source `ibb-rentals`. Or directly:
```bash
ls -la wp-content/uploads/wc-logs/ibb-rentals-*.log
```

## Log a message from anywhere

```php
$logger = IBB\Rentals\Plugin::instance()->logger();
$logger->info( 'Doing the thing', [ 'context' => 'optional array' ] );
$logger->warning( '...' );
$logger->error( '...' );
```

Context arrays are JSON-encoded inline, so they show up in the log alongside the message.
