# Domain — Changelog

## [Unreleased]

### Added
- `Property::short_description()` — accessor over the `_ibb_short_description` postmeta. Used as the cart-line blurb / search-card summary.
- `Property::galleries()` / `gallery($slug)` / `all_attachments()` — accessors over the `_ibb_galleries` JSON postmeta.

## [0.10.0] — 2026-05-01

### Added
- **`Property::tax_class()`** — accessor over `_ibb_tax_class`. Returns the IBB tax-class slug (`''` = not taxed, `'standard'` = WC standard rate, otherwise a WC user-defined class slug).
- **`Property::cleaning_tax_class()`** — accessor over `_ibb_cleaning_tax_class`. Defaults to `''` (not taxed) so existing properties don't suddenly start taxing cleaning.
- **`Property::extra_guest_tax_class()`** — accessor over `_ibb_extra_guest_tax_class`. Resolves the `__inherit__` sentinel ("Same as accommodation", default for new properties) by returning `tax_class()`.
- **`Quote` readonly fields**: `tax_breakdown` (`list<{label,rate_id,amount}>`), `tax_total`, `grand_total`, `accommodation_tax_class`, `cleaning_tax_class`, `extra_guest_tax_class`. `to_array()` exposes all six on the signed payload so the cart can re-route fees through `WC_Cart::add_fee()` with the correct tax classes without re-reading postmeta.

## [0.1.0] — 2026-04-26

### Added
- `DateRange` — half-open immutable range with overlap, contains, each_night, equals.
- `Block` — DB-row wrapper with source/status enum constants and `from_row` / `to_row`.
- `Property` — typed accessors over postmeta with defaults; HMAC-derived per-property iCal export token.
- `Quote` — `to_array()` + HMAC `sign()` / `verify_token()` for cart hand-off.
