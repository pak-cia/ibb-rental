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

## Property's prose leaks into the cart line ("content" or post excerpt visible below the product name)

**Symptom:** the cart row shows the property's `post_content` or `post_excerpt` text below the product name and price, before the booking meta. Looks like a stray descriptive blurb the user didn't expect to surface in a transactional context.

**Root cause:** `ProductSync::sync()` and `::create_product()` were mirroring the property's `post_excerpt` → product `short_description` and `post_content` → product `description`. The WC Cart block (Twenty Twenty-Five default) renders `short_description` (and sometimes `description`) inside the product-name cell automatically.

**Fix:** the mirrored product is a backing object only (used for cart/order/payment plumbing), it doesn't need its own descriptions. Both `set_short_description('')` and `set_description('')` in both the create and update paths. The property's actual prose stays on the property's own page via `[ibb_property]` (which renders `post_content` through `apply_filters('the_content', …)`) — no round-trip via the product is needed.

**If you ever want to opt back in:** add a property-level setting (e.g. "Include description in cart line") and conditionally populate the product fields. Don't blanket-restore — most people will never want the property's full prose duplicated in the cart.

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

---

## Booking confirmation email not received after successful payment

**Symptom:** guest completes checkout and receives a WooCommerce "Customer completed order" email but the IBB booking confirmation email never arrives.

**Root cause:** `BookingConfirmationEmail` registers its `add_action(BOOKING_CREATED, ...)` inside its constructor. The constructor only runs when `WC_Emails::get_emails()` is called (WC's lazy email loader, triggered by the `woocommerce_email_classes` filter). If `get_emails()` hasn't been called yet when `ibb-rentals/booking/created` fires (during `woocommerce_order_status_processing`), the hook is not registered for that request and the email never sends.

**Fix (in `Plugin::boot()`):** add an early forced call to `WC()->mailer()->get_emails()` on `woocommerce_init` (priority 1). This ensures all email classes are instantiated and their hooks registered before any payment webhook or order-status transition fires.

**Symptom 2 (concurrent):** guest receives *two* emails — the IBB confirmation AND a generic WC "Good things are heading your way!" / "Order complete" email.

**Root cause 2:** WC fires `customer_processing_order` and `customer_completed_order` for every order, including IBB bookings. These are the standard WC emails — they know nothing about our booking context.

**Fix 2:** `OrderObserver::suppress_for_ibb_order()` gates on `woocommerce_email_enabled_customer_processing_order` and `woocommerce_email_enabled_customer_completed_order` filters, returning `false` when any order line item has `_ibb_property_id` meta set.

---

## Balance auto-charge retries silently not incrementing (HPOS sites)

**Symptom:** a declined off-session payment logs the error, but the retry counter on the order never advances, so the job reschedules indefinitely at the wrong count.

**Root cause:** `BalanceService::charge()` was using `get_post_meta(order_id)` / `update_post_meta(order_id)` in its `catch` block to read/write `_ibb_balance_retries`. On HPOS sites the order data lives in custom tables, not postmeta, so these reads/writes are no-ops.

**Fix (already applied):** the catch block now calls `wc_get_order(order_id)` and uses `$order->get_meta()` / `$order->update_meta_data()` + `$order->save()`. Deployed in `6f3c4fc`.

**How to verify:** trigger a failed charge (e.g. temporarily set the gateway to return non-success). Reload the order in wp-admin and confirm `_ibb_balance_retries` meta increments.
