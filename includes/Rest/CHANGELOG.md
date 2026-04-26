# Rest — Changelog

## [0.1.0] — 2026-04-26

### Added
- `RouteRegistrar` with all routes under `/wp-json/ibb-rentals/v1/`.
- `AvailabilityController` — `GET /availability?property_id=&from=&to=`, returns `blocked_dates[]`.
- `QuoteController` — `POST /quote` with per-IP transient rate limiting (30/min), returns `{quote, token}`.
- `IcalController` — `GET /ical/{id}.ics?token=...`, raw `text/calendar` body with ETag/Last-Modified, 404 on bad token.
- `FeedsController` — admin-only CRUD for feed registry, plus `POST /feeds/{id}/sync` to trigger immediate import.
