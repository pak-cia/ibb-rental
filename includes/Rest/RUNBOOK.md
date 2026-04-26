# Rest — Runbook

## Add a new endpoint

1. Add a controller under `Rest/Controllers/<Name>Controller.php` with `register( string $namespace ): void` and one or more handler methods.
2. Wire it in `RouteRegistrar::register_routes`, passing whatever Services it needs.
3. Declare `args` (with types and required flags) on `register_rest_route` so WP validates inputs before the handler runs.
4. Add a `permission_callback` — never `'__return_true'` for mutation endpoints.

## Test endpoints from the command line

```bash
# Availability (public)
curl -s "https://site.test/wp-json/ibb-rentals/v1/availability?property_id=15&from=2026-06-01&to=2026-12-01" | jq

# Quote (public + nonce)
curl -X POST "https://site.test/wp-json/ibb-rentals/v1/quote" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $(wp eval 'echo wp_create_nonce("wp_rest");')" \
  -d '{"property_id":15,"checkin":"2026-06-15","checkout":"2026-06-22","guests":2}' | jq

# Feeds list (admin)
curl -s -u admin:password "https://site.test/wp-json/ibb-rentals/v1/feeds" | jq

# iCal feed (signed)
curl -i "https://site.test/wp-json/ibb-rentals/v1/ical/15.ics?token=$(wp eval 'echo IBB\\Rentals\\Plugin::instance()->ical_exporter()->token_for(15);')"
```

## Bypass rate limiting in development

Delete the transient via wp-cli:
```bash
wp transient delete --all
```

Or set `WP_DEBUG` and call directly without going through HTTP.
