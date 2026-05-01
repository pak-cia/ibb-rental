# Woo — Changelog

## [Unreleased]

---

## [0.10.2] — 2026-05-01

### Fixed
- **`ProductSync` now mirrors the property's featured image to the linked WC product** (`set_image_id( get_post_thumbnail_id( $property_id ) )`) in both `sync()` and `create_product()`. Without this the cart line, mini-cart, order edit screen, and customer emails all rendered WC's built-in placeholder. Saving any property post retrofills the thumbnail on its existing product.

---

## [0.10.0] — 2026-05-01

### Changed
- **`CartHandler::apply_prices()` now splits per payment mode.**
  - **Full payment**: line price = accommodation only (`nightly_subtotal − los_discount.amount`). Cleaning + extra-guest fees are added on a new `woocommerce_cart_calculate_fees` callback (`add_fees()`), each via `WC_Cart::add_fee( $name, $amount, $taxable, $tax_class )` with its own tax class pulled from the signed quote payload. WC's tax engine then computes per-rate totals across the line + the two fees, matching the booking-form breakdown.
  - **Deposit mode**: unchanged single-line behaviour, but the line clone is now forced to `tax_status='none'` + `tax_class=''` because the deposit_due figure already includes the proportional share of tax (PricingService now splits `grand_total` rather than pre-tax `total`). This avoids WC double-charging tax on top.
- `render_item_meta()` deposit-mode branch now uses `grand_total` for "Stay total" (was pre-tax `total`) and inserts an "Includes tax" line whenever `tax_total > 0`, so the cart line meta matches the all-in figure the gateway will charge today.

---

## [0.9.0] — 2026-05-01

### Added
- **`ProductSync::apply_tax_settings()`** translates the property's `_ibb_tax_class` postmeta into the linked product's `tax_status` + `tax_class`. Called from both `sync()` and `create_product()`. Empty postmeta → `tax_status='none'`; `'standard'` → standard rate (`tax_class=''`); any other slug passes through verbatim as the WC tax-class slug. WC's cart / checkout pipeline picks up the rest.

---

## [0.8.1] — 2026-04-30

### Fixed
- **`OrderObserver::suppress_for_ibb_order()` fataled when WC's email-settings screen invoked `is_enabled()` with `$order = null`.** Strict `\WC_Order $order` type hint replaced with nullable + early-return-`$enabled` when null. See `TROUBLESHOOTING.md` "WC → Settings → Emails screen throws a critical error".

---

## [0.3.5] — 2026-04-28

### Fixed
- **Booking confirmation email not delivered** — `BookingConfirmationEmail` was never instantiated before `ibb-rentals/booking/created` fired. `Plugin::boot()` now calls `WC()->mailer()->get_emails()` on `woocommerce_init` (priority 1) to force early email-class registration.
- **Generic WC order emails sent alongside IBB confirmation** — `OrderObserver` now hooks `woocommerce_email_enabled_customer_processing_order` and `woocommerce_email_enabled_customer_completed_order`, returning `false` for any order that contains an `_ibb_property_id` line item.

### Added
- **`WebhookTopics`** — registers three WooCommerce webhook topics (`ibb_rentals.booking.created`, `ibb_rentals.booking.cancelled`, `ibb_rentals.balance.charged`) so admins can configure WC webhooks that fire on IBB booking events (WooCommerce → Settings → Advanced → Webhooks). Payload is the full booking row from `BookingRepository::find_by_id()`. Works with n8n, Odoo, Make, Zapier, or any HTTP-capable automation tool.

### Fixed
- `ProductSync` no longer mirrors the property's `post_content` to the WC product's `description`. The mirrored product is a backing object only; the long-form prose belongs on the property page via `[ibb_property]`, not duplicated into the cart.

### Changed
- `ProductSync` now mirrors the new `_ibb_short_description` postmeta (instead of `post_excerpt`) into the product's `short_description`. The Admin Details tab has a dedicated field for this. Using a dedicated meta avoids the Gutenberg-sidebar / metabox-form save race that bites when both surface `post_excerpt`.

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
