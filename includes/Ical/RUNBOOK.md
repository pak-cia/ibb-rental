# Ical — Runbook

## Get a property's iCal export feed URL

Property edit screen → iCal tab. Copy the URL. Paste into Airbnb's "Sync calendars" / Booking.com's "Calendar import" / Agoda's iCal import / VRBO's calendar import.

Or programmatically:
```php
$plugin = IBB\Rentals\Plugin::instance();
$url = $plugin->ical_exporter()->feed_url( $property_id );
```

## Add an inbound feed (admin-side, no UI yet)

UI is pending — for now use the REST endpoint:

```bash
curl -X POST "https://your-site.test/wp-json/ibb-rentals/v1/feeds" \
  -H "X-WP-Nonce: <nonce>" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"property_id":15,"url":"https://www.airbnb.com/calendar/ical/123.ics?s=ABC","label":"Airbnb","source":"airbnb","sync_interval":1800}'
```

Or insert directly into `wp_ibb_ical_feeds` for testing.

## Force a feed to sync now

```php
$plugin = IBB\Rentals\Plugin::instance();
$plugin->ical_importer()->import( $feed_id );
```

Or via REST: `POST /wp-json/ibb-rentals/v1/feeds/{id}/sync`. Or via the per-feed "Sync now" button on the Feeds page (when added).

## Inspect what's in the export feed

```bash
curl -s "https://your-site.test/wp-json/ibb-rentals/v1/ical/15.ics?token=<token>" | head -40
```

Should show `BEGIN:VCALENDAR…END:VCALENDAR` with one VEVENT per direct booking and manual block.

## Test the parser against a real OTA feed

```php
$plugin = IBB\Rentals\Plugin::instance();
$body = file_get_contents( 'path/to/airbnb-feed.ics' );
$events = $plugin->ical_parser()->parse_events( $body );
var_dump( $events );
```

The parser returns `[ ['uid' => ..., 'start' => 'Y-m-d', 'end' => 'Y-m-d', 'summary' => ...], ... ]`. Recurrence (RRULE) is expanded inline up to a 24-month horizon.

## Switch to `sabre/vobject` for richer parsing

1. `composer require sabre/vobject:^4.5` and run `composer mozart-compose` (Mozart-prefixes it to `IBB\Rentals\Vendor\Sabre\VObject`).
2. Build a new `Parser` implementation that wraps sabre — same return shape.
3. Inject it into `Plugin::ical_parser()` (or swap inside `Importer`). The current in-house `Parser` is fine for ~95% of OTA feeds; only switch if you hit a feed it can't parse.
