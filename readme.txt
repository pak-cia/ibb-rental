=== IBB Rentals ===
Contributors: ibb
Tags: woocommerce, vacation rental, booking, ical, airbnb, booking.com
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.8.3
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
