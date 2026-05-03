=== IBB Rentals ===
Contributors: ibb
Tags: woocommerce, vacation rental, booking, ical, airbnb, booking.com
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.11.2
WC requires at least: 9.0
WC tested up to: 10.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tourism vacation-rental property management for WooCommerce — direct bookings, iCal calendar sync (Airbnb, Booking.com, Agoda, VRBO), seasonal pricing, and deposit + balance payments.

== Description ==

IBB Rentals turns any WooCommerce store into a vacation-rental booking engine.

* Multi-property: manage many listings on one site, each with its own calendar, rates, and channel feeds.
* iCal calendar sync (in and out) with Airbnb, Booking.com, Agoda, VRBO, and any OTA that supports the iCalendar standard.
* Seasonal + length-of-stay pricing, weekend uplift, cleaning and extra-guest fees.
* Direct bookings on your own website with live availability, instant quotes, and WooCommerce checkout.
* Two payment modes: full payment at booking, or deposit at booking with the balance auto-charged before check-in.
* Gateway-agnostic — works with any WooCommerce gateway (Stripe, Xendit, PayPal, etc.).

== Installation ==

1. Upload the `ibb-rentals` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Visit Rentals → Settings to configure global defaults.
4. Add your first property under Rentals → Properties.

== Changelog ==

= 0.11.2 =
* ClickUp sync now filters by status at the API. Settings → ClickUp → "Sync these statuses only" — comma-separated list of ClickUp status names; default `upcoming, currently staying, checked out, cancelled`. Tasks in any other status (housekeeping subtasks parked in `inquiries`, old auto-archived `Closed` cards, etc.) are skipped at the API level rather than fetched and dropped client-side. On busy lists this drops the per-sync task count by an order of magnitude.

= 0.11.1 =
* Outgoing iCal feeds now include richer event data so the host sees useful info on each OTA's calendar. SUMMARY is "Bob Jones (Agoda)" when guest names are enabled (or "Agoda booking" when not — Airbnb / Booking.com previously rendered just "Synced: theuluhills.com"). DESCRIPTION carries the property title, stay length, source label, optional guest name, and a clickable ClickUp deep-link (https://app.clickup.com/t/<task_id>) when the block came from a ClickUp sync — click straight from Airbnb's calendar event into the booking card.
* Settings → iCal sync → "Guest names in feeds" toggle (default OFF for privacy). When enabled, guest names appear in SUMMARY + DESCRIPTION across every per-OTA feed. ClickUp deep-links are always included, independent of this toggle.

= 0.11.0 =
* Hub-and-spoke calendar sync. The plugin is now the single source of truth for property availability across every OTA. Each OTA gets its own per-property feed URL — `/ical/<property_id>/<ota>.ics?token=…` — and that feed includes every confirmed block from every other source, with a per-OTA loop guard so an OTA never re-imports its own bookings. Paste each URL into the matching OTA (Airbnb, Booking.com, Agoda, VRBO, Expedia) as its inbound calendar feed; bookings made anywhere fan out to everywhere.
* New booking-source split. Plugin/website checkout bookings now use `source='web'`; `source='direct'` is reserved for walk-ins / phone bookings entered manually. Existing website bookings are migrated automatically (any `direct` block linked to a WC order becomes `web`). Calendar gets a teal swatch for walk-ins; website bookings keep the purple. Source-filter dropdown adds a "Walk-in / phone" option.
* ClickUp can auto-create blocks. When a ClickUp task has property + dates + a mapped source but no matching block exists yet, the sync now inserts one. Settings → ClickUp gains a "Create blocks for" allowlist (per source), with sources that already have an iCal feed greyed out so ClickUp can't compete with the OTA's authoritative feed. Solves the Agoda case (no native iCal feed — ClickUp drops `source=agoda` blocks into the system, and Airbnb / Booking.com / VRBO see them via their per-OTA feeds).
* ClickUp cancellation handling. Tasks marked cancelled (custom status named "cancelled" or a "cancelled" tag) flip the matching block (those we previously created via `external_uid='clickup:<id>'`) to `status='cancelled'` so they drop out of every per-OTA feed. iCal-imported blocks owned by other OTAs are never touched.
* Sync-status pill on the Settings page now shows created / updated / cancelled counts, not just "updated" total.
* **Hard URL switch.** The legacy `/ical/<property_id>.ics` feed URL is removed. Re-paste the new per-OTA URLs from each property's iCal tab into your OTAs after upgrading.

= 0.10.2 =
* Fix: cart / checkout / order emails showed the WooCommerce placeholder image for booking lines. The mirrored WC product had no thumbnail set; the property's featured image was never copied across. `ProductSync` now mirrors the property's featured image (`get_post_thumbnail_id`) onto the linked product on every save, so re-saving any property in admin populates the missing thumbnail. New properties get the right image automatically.

= 0.10.1 =
* Fix: quote panel showed no tax breakdown despite WooCommerce tax classes being configured. The pricing engine called `WC_Tax::find_rates()` which silently returns empty when invoked from a context with no customer billing country set (the `/quote` REST endpoint runs before checkout). Now uses `WC_Tax::get_base_tax_rates()` which always resolves against the shop base location — same approach WC's product price-suffix uses on the shop page. Cart was already correct, this only affected the booking-form preview.

= 0.10.0 =
* Per-fee tax classes. Booking Rules tab now exposes three independent tax-class dropdowns: accommodation, cleaning fee, and extra-guest fee. Lets you charge e.g. PB1 hotel tax on the stay while keeping cleaning untaxed (or at standard VAT). Security deposit is informational only — never charged today, never taxed. Extra-guest fee defaults to "Same as accommodation".
* Quote panel now displays a per-rate tax breakdown ("PB1 10%: $X.XX") and the post-tax total. The booking-form summary mirrors what WooCommerce's checkout will charge, so guests see the all-in figure before adding to cart.
* Full-payment cart bookings now route the cleaning fee and extra-guest fee through `WC_Cart::add_fee()` with their respective tax classes — the line item is the accommodation only, fees are separate cart-level rows. Deposit-mode bookings continue to charge a single tax-inclusive line today.

= 0.9.0 =
* Per-property tax-class selector. Booking Rules tab now has a "Tax class" dropdown listing every tax class configured under WooCommerce → Settings → Tax (plus "Standard rate" and "Not taxed"). Selection mirrors to the linked WC product's `tax_status` + `tax_class` on save, so cart and checkout apply the right rate. Default is "Not taxed" — preserves existing behaviour.

= 0.8.9 =
* Fix: booking-form date timezone bug. Selected dates from the picker were converted via `Date.toISOString()` which UTC-shifted them — for any UTC+ timezone (e.g. Asia/Makassar +8) Flatpickr's local-midnight Date became the previous calendar day in UTC. The form sent the wrong dates to the backend, causing valid turnover-day check-ins to be rejected as overlapping. Replaced with a local-component formatter (`getFullYear/getMonth/getDate`) in all four spots in `Frontend/Assets.php`.

= 0.8.8 =
* "Selected dates are not available" booking-form error now names the conflicting block — source, guest name, and date range — so admins (and guests, debugging) can see exactly which existing booking is in the way instead of a vague unavailable message. Also returned in the JSON response under `data.overlapping_blocks` for programmatic consumption.

= 0.8.7 =
* Fix: booking form guest stepper +/- buttons would get stuck disabled. After clicking up to max, the down button refused to respond (and vice versa) until the user typed in the input or used keyboard arrows. Cause: stepper click handlers updated the value but never re-ran the disabled-state sync, leaving the stale state from page load. Fix: call syncStepperState() inside each click handler.

= 0.8.6 =
* Fix: Booking Form Elementor widget — Style → Book button defaults now match plugin baseline (white text, 4px border-radius, 10/14px padding) so per-widget Style controls always generate CSS that wins over Site Kit / theme "Button" defaults. Without this, leaving fields blank in the editor let kit Button text-color and padding leak through on the frontend with values different from what the editor preview showed.

= 0.8.5 =
* Fix: Elementor Pro Theme Builder Single templates assigned to Properties still didn't render after 0.8.4. Detection rewritten to use a path-based check (does `$template` already point inside another plugin's directory?) as the primary signal, with the Elementor Pro API call as a secondary signal. Catches edge cases where Elementor's API returns empty even when a template is matched.

= 0.8.4 =
* Fix: Elementor Pro Theme Builder Single templates assigned to the Properties post type via Display Conditions now render. Previously the plugin's `template_include` filter ran at priority 99 and overrode whatever Elementor had set, silently discarding the admin-assigned template. The plugin now defers to Elementor when its Theme Builder has a matching Single document for the current request, falling through to the plugin template otherwise.

= 0.8.3 =
* Calendar Month / Week views: visible vertical gap between event bars. Adjacent same-color bookings (e.g. two consecutive Airbnb stays) now have a 2px margin and a faint inner outline so they no longer bleed into one continuous block.

= 0.8.2 =
* Gallery picker auto-engages WP's Bulk Select mode on open, so each thumbnail click toggles selection without needing Ctrl/Cmd. Accessibility fix for users with limited modifier-key access.
* Currency / rate input fields on the Rates and Booking Rules tabs widened to 140px to fit IDR-scale values (7+ digit prices).
* iCal feed sync interval default lowered from 1800s (30 min) to 900s (15 min) for fresher OTA imports.

= 0.8.1 =
* Fix: critical error opening WooCommerce → Settings → Emails. `OrderObserver::suppress_for_ibb_order()` filter callback was strictly typed `WC_Order $order` but WC's settings page invokes `is_enabled()` with `$order = null`. Now accepts nullable order and falls through to the original $enabled when no order context is present.

= 0.8.0 =
* The "Additional content" field on each IBB email setting is now a full rich-text editor (TinyMCE with Add Media, formatting, lists, links). No code edits needed for typical email customizations — for visual control over the entire email layout, install Kadence WooCommerce Email Designer (or similar) which automatically picks up our emails.

= 0.7.0 =
* Editable email settings (Settings → Emails → IBB Booking Confirmation / IBB Pre-arrival Reminder): Subject, Heading, Additional content textarea, Reply-To address, Email type (HTML/plain/multipart). No code edits required for typical customizations.
* Reply-To address now configurable per email — set `hello@yourdomain.com` so guest replies bypass the WC store admin inbox.
* Theme-overridable email templates: drop a copy of `templates/emails/booking-confirmation.php` (or `booking-reminder.php`) into `your-theme/ibb-rentals/emails/` to customize markup wholesale.

= 0.6.0 =
* ClickUp source override: when a manual-blackout block on Airbnb is actually a direct or non-Airbnb OTA booking per ClickUp, the calendar now displays the correct OTA color and label (purple for direct, orange for Agoda, etc.) instead of red Airbnb. Implemented as a separate `source_override` column owned by ClickUp; iCal imports never touch it.

= 0.5.0 =
* ClickUp settings: cascading workspace → space → folder → list dropdowns (replaces guessing list IDs).
* Per-property unit-code mapper (e.g. `v1, 1` → Villa 1) for property-scoped guest-name matching.
* "View ClickUp task →" deep-link in the calendar detail modal for matched bookings.
* Sync-status pill on Settings page (last run time + counts, or red error message).
* Booking-ID match strategy via task description + `external_uid` (with date-tuple fallback for OTAs that scramble UIDs like Airbnb).
* Match no longer requires source equality when the property is identified — handles the workflow where direct/Agoda bookings are manually blackouted on Airbnb.
* Fix: ClickUp date conversion now uses site timezone (was UTC) so dates match iCal-imported blocks.
* Fix: timeline view no longer clips trailing day cells on narrow viewports.

= 0.4.0 =
* Admin calendar timeline view (Month / Week / Timeline) with one row per property and colored bars per booking.
* Calendar bars now show guest names instead of OTA labels; detail modal links to the WooCommerce order.
* Added Expedia as a supported source.
* ClickUp integration: sync guest names from a Bookings list into the calendar with a recurring background job. Settings page has cascading workspace → space → folder → list pickers and a per-property unit-code mapper.
* DB migration v2: adds `guest_name` column to `wp_ibb_blocks`.

= 0.3.5 =
* Booking confirmation email and OTA-block / blackout / past-date display fixes.

= 0.1.0 =
* Initial scaffold: plugin skeleton, custom post type, custom database schema, activation lifecycle.
