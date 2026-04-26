# Cron — Troubleshooting

## Recurring job not firing

**Likely causes:**

1. **Action Scheduler hasn't been triggered.** AS runs piggybacked on real traffic via WP heartbeat by default. On a low-traffic dev site, install a real cron entry (`* * * * * curl https://your-site.test/wp-cron.php`) or use wp-cli `wp action-scheduler run`.
2. **The recurring action wasn't scheduled.** Activation runs `Setup/Installer::schedule_recurring_jobs` only on activate. If the plugin was file-copied, deactivate/activate to re-schedule.
3. **The action was unscheduled by us on deactivate but never re-scheduled.** `Setup/Installer::deactivate` calls `unschedule_recurring_jobs` — re-activate.

## Job runs but does nothing

**Likely cause:** the per-resource lock from a previous failed/timed-out run is still in place. e.g. for balance charges:
```sql
SELECT * FROM wp_options WHERE option_name = 'ibb_balance_lock_<booking_id>';
```

If a row exists with a stale timestamp, delete it. The lock is supposed to be released in a `finally` block but a fatal error before that point would leak the lock.

## Job fires twice for the same booking

**Likely cause:** AS legitimately re-fired due to a PHP timeout. The per-resource lock should make the second fire a no-op. If you're seeing real duplicate side effects (two balance charges), check that the lock acquisition (`add_option('ibb_balance_lock_<id>', ...)`) is the very first thing in the handler — adding it after any side-effect-having work is the bug.

## Send-payment-link emails go to spam / aren't received

This is the classic WP `wp_mail` problem, not an Action Scheduler one. Install a transactional email plugin (WP Mail SMTP, FluentSMTP) to deliver via SES/SendGrid/Postmark/etc. Verify by checking the WC log (`source: ibb-rentals`) — if we logged "Balance payment link sent" then `wp_mail` returned true; the issue is downstream of us.
