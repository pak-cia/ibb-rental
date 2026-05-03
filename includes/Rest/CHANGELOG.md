# Rest — Changelog

## [0.11.0] — 2026-05-03

### Changed
- **`IcalController` route is now `/ical/(?P<property_id>\d+)/(?P<for_ota>[a-z]+)\.ics`.** The `for_ota` segment must be one of `Block::OTA_SOURCES` — anything else returns 404 (also hides the existence of the legacy URL shape from probes). Token verification now requires the matching `(property_id, for_ota)` pair; v0.10.x tokens (computed against `ical:<id>`) are no longer accepted — hard switch.

### Removed
- The legacy combined route `/ical/<property_id>.ics` is no longer registered.

## [0.1.0] — 2026-04-26

### Added
- `RouteRegistrar` with all routes under `/wp-json/ibb-rentals/v1/`.
- `AvailabilityController` — `GET /availability?property_id=&from=&to=`, returns `blocked_dates[]`.
- `QuoteController` — `POST /quote` with per-IP transient rate limiting (30/min), returns `{quote, token}`.
- `IcalController` — `GET /ical/{id}.ics?token=...`, raw `text/calendar` body with ETag/Last-Modified, 404 on bad token.
- `FeedsController` — admin-only CRUD for feed registry, plus `POST /feeds/{id}/sync` to trigger immediate import.
