# IBB Rentals — Troubleshooting

Plugin-wide known issues. Component-specific issues live in each component's `TROUBLESHOOTING.md`. The cross-references below point to where the actual fix lives.

---

## Activation succeeded but `/properties/<slug>/` returns 404

**Symptom:** the CPT registers and the menu appears, but visiting a property's public permalink returns WordPress's default 404 page.

**Root cause:** WP rewrite rules were flushed before the CPT was registered (or never flushed at all on a file-copy install).

**Fix:** [`Setup/Installer::maybe_flush_rewrites()`](includes/Setup/Installer.php) now runs on `init` priority 100 and self-heals — checks `get_option('rewrite_rules')` for a `properties/` rule and flushes if missing. Visit any wp-admin page once after activation; the flush fires on the next page load.

See [includes/Setup/TROUBLESHOOTING.md](includes/Setup/TROUBLESHOOTING.md).

---

## Cart shows "You cannot add another \"<property>\" to your cart"

**Symptom:** clicking Book now twice (or fast double-click) produces a red WC error notice on /cart/.

**Root cause:** WC throws this from `WC_Cart::add_to_cart()` whenever `is_sold_individually()` returns true for an already-cart-resident product.

**Fix:** the global [`WC_Product_IBB_Booking::is_sold_individually()`](includes/Woo/WC_Product_IBB_Booking.php) returns `false`. Quantity is enforced to 1 via the `woocommerce_add_to_cart_quantity` filter in [`Woo/CartHandler::clamp_quantity`](includes/Woo/CartHandler.php), and the `woocommerce_add_to_cart` action handler `reset_merged_quantity` resets to 1 if a duplicate cart-id merge bumps it above 1. Result: identical re-clicks merge silently into the same line.

See [includes/Woo/TROUBLESHOOTING.md](includes/Woo/TROUBLESHOOTING.md).

---

## Deposit-mode order charges full stay total instead of deposit

**Symptom:** property is set to "Deposit + balance later" with 30% deposit, but checkout charges 100% of the stay.

**Root cause:** `CartHandler::apply_prices()` was setting cart line price to `$quote['total']` regardless of payment mode.

**Fix:** [`Woo/CartHandler::apply_prices`](includes/Woo/CartHandler.php) now reads `$quote['payment_mode']` and uses `deposit_due` when mode is `deposit`. The full quote breakdown is still persisted on the order line item meta so `BookingService` and `BalanceService` can compute the balance correctly.

See [includes/Woo/TROUBLESHOOTING.md](includes/Woo/TROUBLESHOOTING.md).

---

## Shortcodes inside the property description render as raw text

**Symptom:** `[ibb_gallery gallery="bed-1"]` typed into the property's main editor content shows on the front-end as literal `[ibb_gallery gallery="bed-1"]`, not as an image grid.

**Root cause:** `Frontend/Shortcodes::render_property` was emitting content via `wp_kses_post(wpautop($content))`, which doesn't run shortcodes.

**Fix:** [`Frontend/Shortcodes::render_property`](includes/Frontend/Shortcodes.php) now runs `apply_filters('the_content', $content)` — the canonical WP filter chain that includes shortcode processing, autop, oEmbeds, etc.

See [includes/Frontend/TROUBLESHOOTING.md](includes/Frontend/TROUBLESHOOTING.md).

---

## "Add gallery" button appears to do nothing (Gutenberg)

**Symptom:** clicking + Add gallery in the Photos tab fires an autosave AJAX in the network tab but doesn't add a gallery card.

**Root cause:** the inline `<script>` that wired the button was rendered inside the metabox HTML, executing too early in Gutenberg's mount sequence — the DOM elements weren't yet attached.

**Fix:** [`Admin/PropertyMetaboxes::print_footer_js`](includes/Admin/PropertyMetaboxes.php) prints the JS in `admin_print_footer_scripts` priority 99, with a polling init that retries up to 12s and is idempotent via a `data-ibb-init` flag.

See [includes/Admin/TROUBLESHOOTING.md](includes/Admin/TROUBLESHOOTING.md).

---

## Browser downloads `.ics` instead of displaying it

**Symptom:** opening the iCal export feed URL in a browser triggers a file download (e.g. opens in Outlook/Calendar) instead of showing text.

**Root cause:** not a bug. `Content-Type: text/calendar` is correctly handled by browsers as a calendar feed; some open it in the OS calendar app, others download it. Airbnb / Booking.com / Agoda fetch via HTTP and consume the body directly — they don't care about browser display.

**Verify by:** opening the downloaded file in a text editor, or `curl -i <feed-url>` to see headers + body.

See [includes/Ical/TROUBLESHOOTING.md](includes/Ical/TROUBLESHOOTING.md).

---

## How to add a new entry to this file

When you fix a plugin-wide issue (one that crosses two or more components, or that's the first thing a new developer would hit), add a section here with: **Symptom**, **Root cause**, **Fix** (with file links), and a pointer to the relevant component's `TROUBLESHOOTING.md` for deeper detail.

If the issue is contained to one component, add it to that component's `TROUBLESHOOTING.md` instead.
