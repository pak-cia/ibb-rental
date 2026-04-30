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

`CartHandler::render_item_meta()` (hooked to `woocommerce_get_item_data`) emits a **single** entry with an **empty `key`**. ALL meta — including the first field — lives inside the `display` value. Each label is wrapped in `<strong class="ibb-cart-meta-label">` (no inline style — see below for why) and `Frontend\Assets::maybe_enqueue_cart_styles()` ships a tiny stylesheet `.ibb-cart-meta-label{font-weight:700!important}`.

```php
$item_data[] = [
    'key'     => '',
    'display' => '<strong class="ibb-cart-meta-label">Check-in:</strong> 2026-06-20<br>'
               . '<strong class="ibb-cart-meta-label">Check-out:</strong> 2026-07-01<br>...',
];
```

**Three failure modes we've exhausted to land on this:**

1. **Multiple entries (one per field).** WC's classic cart wraps each entry in `<dl class="variation">`; modern themes flatten these inline-flow. Multiple wrappers + theme CSS = mashed-together text.
2. **Single entry with `key='Booking'` + bold styling in display.** The Cart block's React component renders the `key` (it calls it `name`) inside `<span class="wc-block-components-product-details__name">`, which inherits the theme's body font-weight. The StoreAPI strips HTML from the name field via `wp_strip_all_tags`, so there's no way to bold it from the data side. Result: an inconsistent first label vs the rest.
3. **Single entry with empty `key` + inline `style="font-weight:700!important"` on each `<strong>` in display.** Looks correct on paper — `wp_kses_post` allows `style` on strong via global attributes. But the WC Cart block's StoreAPI `display` field arrives at React's RawHTML with the `style` attribute **stripped**. We don't know exactly which step does it (could be a security plugin filtering `wp_kses_allowed_html`, could be a WC StoreAPI quirk, could be the way React's RawHTML interprets the JSON), but DevTools confirms: the rendered `<strong>` has no `style` attribute, only the user-agent default of `font-weight: bolder` which resolves to 400 against a body weight of 300.

**Why class survives:** the `class` attribute is on the global allowlist for *every* tag in `wp_kses_allowed_html('post')`. No security plugin or WC pipeline I've seen strips it. So we put the bold-styling rule on the class via a tiny stylesheet enqueued from `Frontend\Assets`. Class selector specificity (0,1,0) beats body/element rules (0,0,1), and `!important` defeats the user-agent's `strong { font-weight: bolder }` and any theme cascade involving `!important` on body weight.

**Scope:** the stylesheet is two lines long and only matches our own `.ibb-cart-meta-label` class. No theme conflicts. Enqueued only when the cart actually contains an IBB item.

**Test checklist for any future cart-meta change:**
- [ ] Classic cart (`[woocommerce_cart]` shortcode) on the active theme
- [ ] Cart block (block-theme default) on the active theme
- [ ] Mini cart widget (if the theme uses one)
- [ ] Order confirmation page right after checkout
- [ ] Both deposit-mode and full-payment-mode bookings

---

## Availability calendar shows more than 7 days per row / dates don't align to day-of-week headers

**Symptom:** the inline availability calendar (Flatpickr) shows all dates for a month crammed onto one or two rows, or day numbers don't sit under the correct weekday header.

**Root cause:** `Assets.php` was setting `.ibb-calendar .flatpickr-day { flex:1; max-width:none; }`. Removing the Flatpickr-native `max-width: 14.28571%` means the flex container no longer enforces 7 items per row; all ~30 days collapse onto a single row before `flex-wrap` triggers.

**Fix:** restore `flex: 0 0 14.28571%; max-width: 14.28571%` on `.ibb-calendar .flatpickr-day`. The `flex-basis` keeps each cell exactly 1/7 wide while `max-width` is the hard cap Flatpickr itself relies on.

**If this regresses:** check whether any responsive override or "make calendar fill container" change also nukes `max-width`. `flex: 0 0 14.28571%` alone isn't enough — `max-width` must match.

---

## Past dates show strikethrough in the availability calendar

**Symptom:** days before today appear with a line through them, as if they are booked/blocked rather than simply in the past.

**Root cause:** `flatpickr-disabled` is applied by Flatpickr to *both* past dates (via `minDate: 'today'`) and blocked future dates. The original CSS applied `text-decoration:line-through` to the whole class, so past dates got it too.

**Fix:** `flatpickr-disabled` styling no longer includes `text-decoration`. Only the custom `.ibb-booked` class gets strikethrough. An `onDayCreate` callback compares each disabled cell's date against the `blocked[]` array; if it's a future blocked date, it adds `ibb-booked`. Past dates are disabled but not marked `ibb-booked`.

---

## Blackout dates not greyed/struck-through in availability calendar (still selectable visually)

**Symptom:** property has blackout ranges configured in the Booking Rules tab (stored as `_ibb_blackout_ranges` postmeta) but those dates appear as normal/bookable in the front-end Flatpickr calendar.

**Root cause:** `AvailabilityService::get_blocked_dates()` only queried `wp_ibb_blocks` (direct bookings + iCal imports). Blackout ranges from property meta were only checked inside `validate_booking_rules()` at quote-request time — they never reached the date-picker's blocked-dates array.

**Fix:** `get_blocked_dates()` now loads the property via `Property::from_id()` and expands each `_ibb_blackout_ranges` entry (using `DateRange::from_strings` / `each_night`) into the returned array, filtered to the requested window. Validated at quote time AND shown in the picker.

---

## Browser downloads `.ics` instead of displaying it

Not a bug. `Content-Type: text/calendar` is correctly handled by browsers as a calendar feed. OTAs fetch via HTTP and consume the body; they don't care about browser display.

To inspect the body: open the downloaded file in a text editor, or `curl -i <feed-url>`.
