# Domain

Immutable value objects with no WordPress or database dependency. The "shape of data" layer that everything else passes around.

## Files

- `DateRange.php` — half-open `[checkin, checkout)` range. Constructed via `from_strings()` or `from_dates()`. Provides `nights()`, `overlaps()`, `contains()`, `each_night()` (generator). All instances normalised to UTC midnight; stays are tracked as calendar dates so DST is a non-issue.
- `Block.php` — one row from `wp_ibb_blocks`. Source enum constants: `direct`, `manual`, `hold`, `airbnb`, `booking`, `agoda`, `vrbo`, `other`. Status enum: `confirmed`, `tentative`, `cancelled`. Factories: `from_row()` / `to_row()` for repository round-trip.
- `Property.php` — wrapper around an `ibb_property` `WP_Post` with typed accessors for every postmeta field. Defaults are baked in so a freshly-published property without configured rates/rules still produces sensible behaviour. Includes `galleries()`, `gallery($slug)`, `all_attachments()` for the photo gallery system.
- `Quote.php` — output of `PricingService::get_quote()`. Holds the per-night breakdown, fees, deposit split, and serialises to JSON. Provides `sign($secret)` (HMAC) and `verify_token($token, $secret, $ttl)` for cart hand-off.

## Key patterns

- **Half-open intervals** — turnover days are NOT overlaps. Same-day check-in == previous check-out is allowed.
- **No WP/DB calls** — `Property` reads postmeta on demand via `get_post_meta`, but it's the only one that touches WP, and it's read-only. `DateRange`, `Block`, `Quote` are pure values.
- **Readonly promoted properties** — PHP 8.1 `public readonly` constructor promotion across all four classes.
- **Defensive JSON decoding** — `Property::los_discounts()` / `blackout_ranges()` / `galleries()` defensively handle malformed JSON or empty strings; never throw.
- **Money as floats with explicit rounding** — `Quote::to_array()` uses `round($v, 2)` at the boundary. Internal arithmetic uses raw floats. Matches WC's own conventions.

## Connects to

- [../Repositories](../Repositories/README.md) — `Block::from_row()` / `to_row()` are the round-trip with `AvailabilityRepository`
- [../Services](../Services/README.md) — `Pricing`, `Availability`, `Booking`, `Balance` services consume Domain types
- [../Rest](../Rest/README.md) — controllers serialise `Quote` and the Domain types
- [../Frontend](../Frontend/README.md) — `Shortcodes::render_*` reads `Property`

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
