# Cron

Action Scheduler job handlers. Each file is a thin wrapper around a Service method — keeping AS hooks separate from business logic so the same logic can be invoked synchronously (tests, admin "run now" buttons).

We use **Action Scheduler** (bundled with WC) for everything, never WP-Cron. Group: `ibb-rentals`.

## Files

- `Jobs/CleanupHoldsJob.php` — recurring every 5 minutes. Calls `AvailabilityRepository::delete_expired_holds`. Hook: `ibb_rentals_cleanup_holds`. Registered on plugin activation by `Setup/Installer::schedule_recurring_jobs`.
- `Jobs/ImportFeedJob.php` — recurring per-feed. Calls `Ical/Importer::import($feed_id)`. Hook: `ibb_rentals_import_ical_feed`. Scheduled by `Ical/FeedScheduler::ensure_recurring`.
- `Jobs/ChargeBalanceJob.php` — one-shot at the booking's `balance_due_date 09:00 site_tz`. Calls `Services/BalanceService::charge($booking_id)`. Hook: `ibb_rentals_charge_balance`. Scheduled by `BalanceService::schedule_for_booking` for token-capable gateways.
- `Jobs/SendPaymentLinkJob.php` — one-shot at `balance_due_date - 3` and `balance_due_date - 1`. Calls `BalanceService::send_link($booking_id, $kind)`. Hook: `ibb_rentals_send_payment_link`. Scheduled by `BalanceService::schedule_for_booking` for non-token-capable gateways or as a fallback after charge retries exhaust.

## Key patterns

- **Idempotent jobs** — every job can be re-fired safely. AS may run an action twice on edge cases (PHP timeout). Per-resource locks via `add_option` (e.g. `ibb_balance_lock_<id>`) prevent concurrent execution.
- **AS hooks use underscores** — `ibb_rentals_*`. Action Scheduler internally normalises hook names; sticking to underscores avoids any normalisation surprises. Group name `ibb-rentals` (with dash) is fine because that's just a string filter.
- **Plugin.php registers the AS hook handlers** — each `Plugin::run_<job>` is a tiny adapter that constructs the Job class with its dependencies and calls `handle()`. Keeps Jobs free of the global container.
- **Don't schedule from tests** — when running PHPUnit, mock `function_exists('as_schedule_*')` or stub AS so tests don't pollute the real scheduler.

## Connects to

- [../Services](../Services/README.md) — `BalanceService`, `BookingService` (indirectly via the BOOKING_CREATED action that triggers scheduling)
- [../Repositories](../Repositories/README.md) — `AvailabilityRepository::delete_expired_holds`
- [../Ical](../Ical/README.md) — `Importer::import`
- [../Plugin.php](../Plugin.php) — Plugin::boot wires `add_action(Hooks::AS_*, ...)` for every AS hook

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
