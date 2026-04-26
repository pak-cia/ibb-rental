# Woo — Changelog

## [Unreleased]

### Fixed
- `ProductSync` no longer mirrors the property's `post_excerpt` / `post_content` to the WC product's short_description / description. The mirrored product is a backing object only; the Cart block was surfacing the property's prose ("content" in dev, marketing copy in production) inside the cart line. Property descriptions stay on the property page via `[ibb_property]`.

### Fixed (earlier)
- `is_sold_individually()` returns `false`; qty enforced to 1 via `clamp_quantity` filter + `reset_merged_quantity` action — eliminates "cannot add another" red notice on duplicate add-to-cart.
- `CartHandler::apply_prices` now uses `deposit_due` for deposit-mode cart lines instead of the full stay total.
- Cart-item dedup key derived from the signed quote-token hash so re-clicks merge while distinct bookings stay separate.
- `WC_Product_IBB_Booking` split into its own global-namespace file to avoid the inline-class parse error.
- Cart line meta enriched: stay total / deposit charged today / balance due (with date) / refundable security deposit.

### Added
- `ProductSync::block_direct_edits` — strips edit/delete caps for mirrored products and shows a yellow admin warning with a deep-link back to the property.
- Row-actions on Products list filtered down to a single "Edit property" link for mirrored products.

## [0.1.0] — 2026-04-26

### Added
- `WC_Product_IBB_Booking` (global namespace) — virtual, hidden, "from $X / night" price label, "Check availability" add-to-cart label.
- `BookingProductType` — type registration via `product_type_selector` + `woocommerce_product_class`.
- `ProductSync` — 1:1 mirroring on `save_post_ibb_property`; trash/untrash cascades.
- `CartHandler` — signed-token verification (`Quote::verify_token`), cart-item-data inflation, line-item meta persistence on `woocommerce_checkout_create_order_line_item`, race-time revalidation on `woocommerce_check_cart_items`.
- `OrderObserver` — HPOS-safe lifecycle: paid → `BookingService::create_from_order_item`; cancelled/refunded/failed → cancel.
- `GatewayCapabilities` — token-capable allowlist with `ibb-rentals/gateways/token_capable` filter; `find_reusable_token` for `WC_Payment_Tokens` lookup.
