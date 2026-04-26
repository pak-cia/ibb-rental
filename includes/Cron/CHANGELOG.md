# Cron — Changelog

## [0.1.0] — 2026-04-26

### Added
- `Jobs/CleanupHoldsJob` — recurring 5m, sweeps expired `source='hold'` blocks.
- `Jobs/ImportFeedJob` — per-feed recurring, wraps `Ical/Importer::import`.
- `Jobs/ChargeBalanceJob` — one-shot at `balance_due_date 09:00`, wraps `BalanceService::charge`.
- `Jobs/SendPaymentLinkJob` — one-shot 3d and 1d before `balance_due_date`, wraps `BalanceService::send_link`.
