# Woo

WooCommerce integration: custom product type, property↔product mirroring, cart hand-off, order observer, gateway-capability dispatcher. Nothing else in the plugin should call WC functions directly — funnel everything through here so HPOS-correctness and product-type expectations are localised.

## Files

- `WC_Product_IBB_Booking.php` — **global-namespace** `WC_Product` subclass for the `ibb_booking` product type. Lives outside the namespace because PHP doesn't allow declaring a fully-qualified-name class inline from a namespaced file, and WC's `woocommerce_product_class` filter expects a top-level class name.
- `BookingProductType.php` — registers the type via `product_type_selector` + `woocommerce_product_class`. `require_once`s the global class file at runtime so it loads only after `WC_Product` exists.
- `ProductSync.php` — mirrors each `ibb_property` post 1:1 to a hidden `ibb_booking` product. Also locks the mirrored product against direct edits via `user_has_cap` and shows an admin warning notice with a deep-link back to the property.
- `CartHandler.php` — quote-token verification, cart-item meta inflation, deposit-aware pricing (`apply_prices`), revalidation on `check_cart_items`, line-item meta persistence on order creation, qty clamping to 1, silent dedup of duplicate adds.
- `OrderObserver.php` — listens to `woocommerce_order_status_processing` / `_completed` / `_cancelled` / `_failed` / `woocommerce_order_refunded` and drives `BookingService` accordingly.
- `GatewayCapabilities.php` — classifies each active WC gateway as `auto_charge` (saved-card off-session reuse) or `payment_link` (everything else). Powers the gateway-capability matrix shown in the property edit screen and the routing decision in `BalanceService::schedule_for_booking`.

## Key patterns

- **HPOS-safe everywhere** — `wc_get_order()` for reads, `$order->get_meta()` / `$order->update_meta_data()` / `$item->add_meta_data()` for meta. Never `get_post_meta` on order IDs.
- **Cart-item dedup via signed-token hash** — `CartHandler::attach_quote` derives the cart-item-data `unique` key from `substr( hash('sha256', $token), 0, 32 )`. Two clicks on the same quote produce identical dedup keys → WC merges them. Different bookings produce different tokens → different keys → separate lines.
- **Deposit-aware cart price** — `apply_prices()` reads `$cart_item['ibb']['quote']['payment_mode']`. In deposit mode the cart line price is `deposit_due`, not `total`. The full quote breakdown is still persisted on the order line item meta so `BookingService` and `BalanceService` can compute the balance correctly.
- **`is_sold_individually() = false`** — set this way so WC doesn't throw the "cannot add another" exception. We enforce qty=1 ourselves via `clamp_quantity` (filter) + `reset_merged_quantity` (action after add).
- **Linked-product locking** — `ProductSync::block_direct_edits` strips `edit_post`/`delete_post` caps for any product carrying `_ibb_property_id` meta. Bulk actions, direct URL edits, and the row-action menu all respect this.
- **Booking-product visibility = hidden** — mirrored products never appear in `/shop` loops. Discovery happens on the property archive (`/properties/`) and via `[ibb_search]`.

## Connects to

- [../Services](../Services/README.md) — `OrderObserver` calls `BookingService` for create/cancel/refund; `BalanceService` reads `GatewayCapabilities`
- [../Domain](../Domain/README.md) — verifies `Quote` tokens, hydrates payload into cart-item-data
- [../Repositories](../Repositories/README.md) — `CartHandler::validate_add_to_cart` calls `AvailabilityRepository::any_overlap` for race-time validation
- [../PostTypes](../PostTypes/README.md) — `ProductSync` watches `save_post_ibb_property`

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
