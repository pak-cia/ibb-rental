# Support — Troubleshooting

## Logs aren't appearing in WC → Status → Logs

**Likely causes:**

1. **WC logger isn't initialised yet.** During very early bootstrap (e.g. inside `register_activation_hook` callbacks), `wc_get_logger()` may not be available. `Logger::log()` falls back to `error_log()` in that case — check the PHP error log instead.
2. **WC's `logging_level_threshold` filter is set high.** Some sites filter out `info` level. Try logging at `error` level to confirm.
3. **Disk full / log directory permissions.** WC logs to `wp-content/uploads/wc-logs/`. Verify the directory exists and is writable.

## Hook constant referenced but the hook never fires

Check that the call site actually uses the constant rather than re-typing the string. A typo like `'ibb-rentals/booking/created'` vs `Hooks::BOOKING_CREATED` is exactly why the constants exist — grep for the literal string and replace with the constant.
