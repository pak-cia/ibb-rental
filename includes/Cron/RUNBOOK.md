# Cron — Runbook

## Find what's scheduled

WooCommerce → Status → Scheduled Actions → filter group `ibb-rentals`. Or via wp-cli:

```bash
wp action-scheduler list --group=ibb-rentals --status=pending
```

## Run a job manually

For an admin-triggered run, use AS's own runner:

```bash
wp action-scheduler run --hooks=ibb_rentals_cleanup_holds
```

For programmatic dispatch:

```php
$plugin = IBB\Rentals\Plugin::instance();
$plugin->run_cleanup_holds();             // recurring 5m job, fired manually
$plugin->run_import_feed( $feed_id );     // import one feed now
$plugin->run_charge_balance( $booking_id );
$plugin->run_send_payment_link( $booking_id, 'first' );
```

## Add a new job

1. Add a class under `Cron/Jobs/<JobName>.php` with constructor injecting the Services it needs and a `handle(...)` method.
2. Add a hook constant in `Support/Hooks.php` (e.g. `AS_MY_JOB = 'ibb_rentals_my_job'`).
3. In `Plugin::boot`, `add_action( Hooks::AS_MY_JOB, [ $this, 'run_my_job' ] );` and add the dispatcher method.
4. Schedule it from wherever the trigger lives — `as_schedule_single_action` for one-shot, `as_schedule_recurring_action` for recurring.

## Cancel a scheduled action

```php
as_unschedule_action( 'ibb_rentals_charge_balance', [ $booking_id ], 'ibb-rentals' );
```

The args must match exactly what was passed when scheduling. To clear all instances of a hook:

```php
as_unschedule_all_actions( 'ibb_rentals_charge_balance', [], 'ibb-rentals' );
```
