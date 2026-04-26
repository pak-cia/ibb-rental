# Woo — Troubleshooting

## "You cannot add another \"<property>\" to your cart" error notice

**Symptom:** clicking Book now twice (or fast double-click) produces a red WC error notice on /cart/.

**Root cause:** WC throws this from `WC_Cart::add_to_cart()` whenever `is_sold_individually()` returns true and the product is already in the cart.

**Fix:** the global `WC_Product_IBB_Booking::is_sold_individually()` returns `false`. We enforce qty=1 ourselves:
- `CartHandler::clamp_quantity` (filter `woocommerce_add_to_cart_quantity`) — caps the requested qty at 1.
- `CartHandler::reset_merged_quantity` (action `woocommerce_add_to_cart`) — resets to 1 if a duplicate cart-id merge bumped it above 1.

Result: identical re-clicks merge silently into the existing line; different bookings stay separate (different signed token → different dedup hash).

---

## Cart line shows full stay total in deposit mode

**Symptom:** property is set to "Deposit + balance later" with 30% deposit, but the cart subtotal shows 100% of the stay.

**Root cause:** `CartHandler::apply_prices()` was setting price to `$quote['total']` regardless of mode.

**Fix:** `apply_prices()` now reads `$quote['payment_mode']` and uses `deposit_due` when it's `deposit`. The full quote (total, balance_due, balance_due_date) is preserved as cart-item meta and persisted onto the order line item meta on checkout.

**If you see this regress:** check the cart-item meta in `WC()->cart->get_cart()` includes `quote.payment_mode = 'deposit'`. If it's missing, the issue is upstream in quote signing/verification.

---

## "Parse error: syntax error, unexpected fully qualified name \"\\WC_Product_IBB_Booking\""

**Symptom:** activation fatal with the above parse error pointing at `BookingProductType.php`.

**Root cause:** PHP doesn't allow declaring a non-namespaced class inline from a namespaced file. The original `BookingProductType.php` had `class \WC_Product_IBB_Booking extends \WC_Product { ... }` inside the `IBB\Rentals\Woo` namespace block.

**Fix:** `WC_Product_IBB_Booking` lives in its own file (`includes/Woo/WC_Product_IBB_Booking.php`) declared at the global namespace. `BookingProductType::register()` does `require_once __DIR__ . '/WC_Product_IBB_Booking.php'` after checking `class_exists('WC_Product')`. The PSR-4 autoloader doesn't try to load it because the namespaced "class" `IBB\Rentals\Woo\WC_Product_IBB_Booking` is never referenced.

**If you need to add another global-namespace class:** follow the same pattern. Don't try to declare it inline.

---

## Mirrored product disappears from edit screen

**Likely cause:** the user trashed the property without realising it cascades. `ProductSync::on_trash` trashes the linked product. Untrash via Properties → Trash → restore the property; `on_untrash` restores the product.

If the product was deleted directly through SQL or another plugin, save the property again — `ProductSync::sync` recreates a fresh mirror because `_ibb_linked_product_id` no longer points at a valid product.

---

## Booking row not created after successful checkout

**Likely causes:**

1. The order didn't reach `processing` or `completed` status (e.g. it stayed `on-hold`). `OrderObserver` only fires on those two statuses.
2. The order line item is missing `_ibb_property_id` meta, which means `CartHandler::persist_line_item_meta` didn't run — usually because `cart_item_data['ibb']` was missing at checkout (expired quote token).
3. HPOS is enabled and code somewhere is using `get_post_meta` on the order. Check the WC log (`source: ibb-rentals`).

To inspect: open the order in wp-admin and look for `_ibb_property_id` in the hidden order-item meta panel.
