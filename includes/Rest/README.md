# Rest

REST API. Single registrar plus thin per-route controllers. All routes under `/wp-json/ibb-rentals/v1/`.

## Files

- `RouteRegistrar.php` — single point of registration. Hooks `rest_api_init` and instantiates each controller, passing in the Services they need from the `Plugin` container.
- `Controllers/AvailabilityController.php` — `GET /availability?property_id=&from=&to=` → blocked-date strings for the date picker. Public, no auth.
- `Controllers/QuoteController.php` — `POST /quote` → priced quote + signed token. Public + nonce, with a per-IP transient counter (30/min).
- `Controllers/IcalController.php` — `GET /ical/{property_id}.ics?token=...` → signed iCal feed. Emits raw `text/calendar` body (short-circuits WP's default JSON serializer).
- `Controllers/FeedsController.php` — admin-only feed registry CRUD + `POST /feeds/{id}/sync` to trigger immediate import. Cap: `manage_woocommerce`.

## Key patterns

- **Auth per route, not globally** — public reads (availability, quote, ical) are open by design; admin mutations gate on `manage_woocommerce`.
- **404 over 401** for the iCal feed — bad/missing token returns 404 so the endpoint doesn't reveal which property IDs exist.
- **Raw text/calendar body** — `IcalController::handle` short-circuits the REST serializer by emitting headers + body via `status_header` / `header` / `echo` / `exit`. Returning a `WP_REST_Response` with the `.ics` body would JSON-encode it.
- **Transient-based rate limiting** — `QuoteController::rate_limit` increments `ibb_quote_rl_<md5(ip)>` with a 60-second TTL. Cheap and adequate for "stop accidental hammering"; if you need real rate limiting use a CDN-level rule.
- **Schemas described inline** — each route declares its `args` array (required, type, validators). Lets `register_rest_route` validate before reaching the handler.

## Connects to

- [../Services](../Services/README.md) — controllers depend on Services, not Repositories directly
- [../Domain](../Domain/README.md) — `DateRange`, `Property`, `Quote` are the request/response shapes
- [../Ical](../Ical/README.md) — `IcalController` consumes the `Exporter`; `FeedsController` triggers the `Importer`
- [../Plugin.php](../Plugin.php) — `RouteRegistrar` receives the Plugin container and pulls services from it

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
