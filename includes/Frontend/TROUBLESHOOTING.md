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

**Root cause:** WC has two completely different cart renderings — the classic shortcode-based cart (`<dl class="variation">` per entry, dt/dd pairs) and the **Cart block** (Twenty Twenty-Five default; `<li class="…__item">` per entry, name/value spans). Modern block themes flatten `dl.variation` inline; the Cart block doesn't fire the classic-cart action hooks; and gating on `REST_REQUEST` to differentiate the two silently breaks the page-render path of the Cart block.

**What didn't work (and why):**

1. **Theme-fighting CSS with `!important`** targeting `.cart_item dl.variation { display: grid; }` — fragile, every new theme is a new battle, and at best gives a cramped 2-column layout.
2. **`woocommerce_after_cart_item_name` action handler** to bypass `dl.variation` and emit our own `<div>` markup — works in classic cart, but the action **doesn't fire on the Cart block**, so users on Twenty Twenty-Five (which uses the Cart block) saw nothing.
3. **`REST_REQUEST` gate on `woocommerce_get_item_data`** to make the Cart block work via its StoreAPI fetch — silently broke any non-REST cart rendering, leaving the meta missing entirely.

**What works:**

`CartHandler::render_item_meta()` (hooked to `woocommerce_get_item_data`) emits a **single** entry with an **empty `key`**. ALL meta — including the first field — lives inside the `display` value, with each label wrapped in `<strong style="font-weight:700!important">`:

```php
$item_data[] = [
    'key'     => '',
    'display' => '<strong style="font-weight:700!important">Check-in:</strong> 2026-06-20<br>'
               . '<strong style="font-weight:700!important">Check-out:</strong> 2026-07-01<br>'
               . '<strong style="font-weight:700!important">Nights:</strong> 11<br>...',
];
```

Why empty key (and not "Check-in" / "Booking" / etc.) — discovered by testing on Twenty Twenty-Five with the Cart block:

- The Cart block's React component renders the entry's `name` (= our `key`) inside `<span class="wc-block-components-product-details__name">`. That span inherits theme styles, and Twenty Twenty-Five's body declares `font-weight: 300`. There's no way to make this span bold from the data side: the StoreAPI strips HTML from the name field via `wp_strip_all_tags`. So whatever you put in `key`, the cart-block-rendered label uses the theme's font-weight — light/300 in Twenty Twenty-Five.
- Putting "Check-in" as the key meant the first label was always lighter than the rest (which DID have my bold-styled `<strong>`). Visually inconsistent.
- An empty `key` makes the cart block skip the `__name` span entirely, and the classic cart's `<dt>` collapses to just `:`. Either is acceptable. ALL labels — including Check-in — now come from inside `display`, where my `<strong style="font-weight:700!important">` markup fully controls the styling.

Theme-immune by construction:

- `<br>` line breaks render identically regardless of `display: block` vs `display: inline` on the surrounding wrapper.
- Inline `font-weight: 700 !important` on the strong tags. The `!important` is needed because many block themes (Twenty Twenty-Five) declare `body { font-weight: 300 }` and rely on cascade — once any `!important` declaration is in play for any property in the inheritance chain, inline non-`!important` declarations lose. Inline `!important` is the right tool here: tightly scoped to our own elements, no cross-element specificity arms race.
- Works in classic cart, Cart block, mini cart, and order-confirmation page from the same code. No CSS file, no per-context branching.

**Test checklist for any future cart-meta change:**
- [ ] Classic cart (`[woocommerce_cart]` shortcode) on the active theme
- [ ] Cart block (block-theme default) on the active theme
- [ ] Mini cart widget (if the theme uses one)
- [ ] Order confirmation page right after checkout
- [ ] Both deposit-mode and full-payment-mode bookings

---

## Browser downloads `.ics` instead of displaying it

Not a bug. `Content-Type: text/calendar` is correctly handled by browsers as a calendar feed. OTAs fetch via HTTP and consume the body; they don't care about browser display.

To inspect the body: open the downloaded file in a text editor, or `curl -i <feed-url>`.
