# Frontend — Troubleshooting

## Shortcodes inside a property's description render as raw text

**Symptom:** `[ibb_gallery gallery="bedroom-1"]` typed into the property's main content shows on the front-end as literal `[ibb_gallery gallery="bedroom-1"]`.

**Root cause:** `Shortcodes::render_property` was emitting content via `wp_kses_post(wpautop($post->post_content))`, which doesn't run shortcodes.

**Fix:** the renderer now uses `apply_filters('the_content', $post->post_content)` — the canonical WP filter chain that includes shortcode processing + autop + oEmbeds + responsive images.

**Caveat:** running `the_content` from inside a shortcode handler can cause infinite recursion if the content contains the same shortcode. `[ibb_property]` rendering content that contains `[ibb_property]` would loop. We don't currently nest property shortcodes, but if you add one that could be self-referential, gate it.

---

## Gallery thumbnail clicks navigate to the image file with no return

**Symptom:** clicking a gallery image opens the full-size image in the same tab; the back button works but the UX is poor.

**Fix:** built-in vanilla lightbox in `Assets::js()`. Click delegation on `.ibb-gallery-display__item` opens a full-screen overlay with prev/next navigation, ESC to close, swipe support. No external library.

**Opt out per-gallery:** `[ibb_gallery class="ibb-no-lightbox"]`.

---

## Booking widget doesn't load Flatpickr

**Symptom:** the dates input shows as a plain textbox instead of opening a calendar dropdown. JS console may say `flatpickr is not defined`.

**Likely causes:**

1. **CDN blocked.** Flatpickr loads from `cdn.jsdelivr.net`. Pages behind a CSP that disallows third-party scripts will fail. Self-host Flatpickr by overriding the enqueue URLs in `Assets::maybe_enqueue`.
2. **Asset not enqueued because shortcode detection missed it.** Check `Assets::should_enqueue` — the page must be a singular `ibb_property`, an archive, or contain a recognised shortcode (`ibb_property`, `ibb_search`, `ibb_calendar`, `ibb_booking_form`, `ibb_gallery`). Pages containing the booking widget via Elementor/page-builder may need the asset enqueue forced.

To force-enqueue on a custom template:
```php
add_action( 'wp_enqueue_scripts', function() {
    if ( /* your detection */ ) {
        IBB\Rentals\Plugin::instance()->set( 'frontend_assets_force', true );
    }
} );
```

(That hook isn't wired yet — easier path is to add your shortcode tag to the `Assets::should_enqueue` list.)

---

## Quote panel says "Selected dates are not available" but you know they should be

**Likely causes:**

1. The quote endpoint returned a 422 — the `validate_booking_rules` chain rejected the request (min nights, advance window, blackout, max guests). Check the response body in the network tab for the specific `code`.
2. The quote endpoint returned a 200 quote but `is_available` is false. Check `wp_ibb_blocks` — there may be a stale `hold` row from an abandoned cart, or an iCal-imported block from an OTA. `CleanupHoldsJob` runs every 5 min; stale holds clear themselves.

---

## Blocks render but Flatpickr / lightbox / details-grid CSS are missing

**Symptom:** a page using one of the IBB blocks shows the underlying markup but the date input is a plain textbox (no Flatpickr calendar), gallery thumbnails open the image file directly (no lightbox), and `[ibb_property_details]` renders as plain stacked text instead of the styled grid.

**Root cause:** `Assets::should_enqueue()` was only checking `has_shortcode()` against the post content. Blocks aren't shortcodes; `has_shortcode` returns false for them. So pages using only blocks (no shortcodes) didn't enqueue the frontend CSS / JS.

**Fix:** `should_enqueue()` now also calls `has_block( 'ibb/booking-form' | 'ibb/gallery' | 'ibb/property-details', $post )`. The block check runs before the shortcode loop because most modern pages will use blocks first.

**If you regress this:** any new IBB block added in `Blocks.php` must also be added to the `has_block` allowlist in `Assets::should_enqueue`. The `has_block` and `has_shortcode` lists are the same data twice — if you find yourself updating one without the other, that's the bug.

---

## Cart / checkout line-item meta renders as one long inline string

**Symptom:** in the cart row, all the booking meta (Check-in, Check-out, Nights, Guests, Stay total, Deposit charged today, Balance due, Security deposit) appears as one mashed-together line.

**Root cause:** WC's classic cart renders cart-item meta as `<dl class="variation">` with dt/dd pairs. Modern themes (block themes especially, including Twenty Twenty-Five) style `dl.variation > *` as inline-flow, mashing all the dt/dd pairs onto one line. Earlier attempts at fixing this with theme-fighting CSS (overriding `dl.variation` with `!important`) were fragile — every new theme was a new battle.

**Fix:** **don't fight WC's variation rendering for the classic cart at all.** Instead:

1. `CartHandler::render_after_cart_item_name()` (hooked to `woocommerce_after_cart_item_name`) emits our own structured HTML below the product name: a `<div class="ibb-booking-meta">` containing `<div class="ibb-booking-meta__row">` per field. Plain divs are block-level by default; themes don't have CSS that flattens them.
2. `CartHandler::render_item_meta()` (hooked to `woocommerce_get_item_data`) **only adds entries during REST requests** (`defined('REST_REQUEST') && REST_REQUEST`). The Block Cart fetches via `/wc/store/v1/cart` (always a REST request) and renders one `<li>` per entry — already line-per-row, no dl.variation hellscape involved.
3. `Assets::cart_css()` is now a small cosmetic stylesheet that ONLY targets `.ibb-booking-meta*` classes — no `!important`, no theme-fragile selectors against `.cart_item` or `dl.variation`. Themes can override our styling cleanly if they want different colours / spacing.

**Trade-offs accepted:**
- The mini-cart widget (`?wc-ajax=get_refreshed_fragments` etc.) won't show booking meta. Mini cart is supposed to be compact (product / qty / price), so this is fine.
- Order-confirmation page meta comes from order-line-item meta (`_ibb_*` fields persisted via `persist_line_item_meta`), not from `woocommerce_get_item_data`, so order screens are unaffected.

**If a future theme still inlines our markup:** check that the theme isn't styling `<div>` as `display: inline` (extremely unlikely). Verify our HTML is actually being emitted by viewing source on the cart page — look for `<div class="ibb-booking-meta">`.

---

## Browser downloads `.ics` instead of displaying it

Not a bug. `Content-Type: text/calendar` is correctly handled by browsers as a calendar feed. OTAs fetch via HTTP and consume the body; they don't care about browser display.

To inspect the body: open the downloaded file in a text editor, or `curl -i <feed-url>`.
