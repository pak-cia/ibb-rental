# IBB Rentals

Tourism vacation-rental property management for WordPress + WooCommerce. Direct bookings, iCal calendar sync (Airbnb, Booking.com, Agoda, VRBO), seasonal pricing, and gateway-agnostic deposit + balance payments.

> **Note:** the public-facing description used by the WordPress.org plugin directory lives in [`readme.txt`](readme.txt) (WP-style format). This file is the developer / contributor / Claude entry point.

---

## Status

**v0.8.0 — current.** v1 booking flow + admin calendar timeline + ClickUp integration + customizable email settings, all running on the staging site. See [CHANGELOG.md](CHANGELOG.md) for the per-version detail since 0.3.5.

**New since 0.3.5 (today's session):**
- **0.4.0** — Admin calendar timeline view (multi-property Gantt grid); Expedia added as supported source; calendar bars show guest names; ClickUp integration (sync guest names from a Bookings list into the calendar via a recurring background job). Migration v2: `wp_ibb_blocks.guest_name`.
- **0.5.0** — Cascading workspace → space → folder → list dropdowns on the ClickUp Settings page; per-property unit-code → property mapper; "View ClickUp task →" deep-link in the calendar detail modal; sync-status pill on Settings; Booking-ID match strategy (with date-tuple fallback). Migration v3: `clickup_task_id`. Fixes: timezone-correct date conversion, timeline view no longer clips on narrow viewports.
- **0.6.0** — ClickUp source override: when ClickUp says a manual-blackout block on Airbnb is actually a direct or non-Airbnb OTA booking, the calendar paints it the right color (purple for direct, orange for Agoda, etc.) instead of red Airbnb. Migration v4: `source_override`.
- **0.7.0** — Editable email settings via WC's standard admin UI (subject, heading, additional content, reply-to, email type) for both `BookingConfirmationEmail` and `BookingReminderEmail`. Per-email Reply-To override. Namespaced theme override path `your-theme/ibb-rentals/emails/...`.
- **0.8.0** — Rich-text editor (TinyMCE with media library) for the Additional content field on each IBB email setting. New `WpEditorFieldTrait`.

**Confirmed working (staging site, Xendit gateway):**
- iCal import from Airbnb ✓
- End-to-end booking flow: quote → cart → Xendit checkout → order received ✓
- `OrderObserver` creates `wp_ibb_bookings` row on `wc-processing` status ✓
- Order cancellation flips booking → Cancelled and releases dates in availability API ✓
- ClickUp guest-name sync → calendar bars show actual guest names matched on (date, source) and unit-code → property mapping ✓
- Customer email arrives with IBB template + admin-configured Reply-To ✓

**Pending verification:**
- Balance scheduling (Xendit = payment-link path; no token-capable gateway in use — requires a real deposit-mode booking on a live order)

**Note (Xendit test mode):** In Xendit TEST MODE on staging, the webhook may not fire back to the site — order stays "Pending payment" after the hosted invoice is paid. Manually advancing to "Processing" in wp-admin triggers `OrderObserver` and creates the booking row correctly. On production with a real Xendit account the webhook fires automatically. See RUNBOOK — "Xendit test-mode webhook" for details.

**Deferred from v1.0 (see [Roadmap](#roadmap) below):** PHPUnit suite, multi-language, reviews, messaging, inquiry/quote flow, admin FullCalendar across properties.

---

## What this plugin does

- **Multi-property**: manage many listings on one site, each with its own calendar, rates, and channel feeds.
- **iCal calendar sync (in & out)** with any OTA that supports the iCalendar standard — Airbnb, Booking.com, Agoda, VRBO. Polled in the background via Action Scheduler.
- **Seasonal pricing engine**: base nightly rate, date-range overrides with priority, weekend uplift, length-of-stay (LOS) discounts, cleaning fee, extra-guest fee, security deposit (informational).
- **Direct bookings**: live availability date picker (Flatpickr), signed quote tokens, WooCommerce checkout, custom `ibb_booking` product type auto-mirrored 1:1 from each property.
- **Two payment modes per property**: full payment at booking, or deposit-now-balance-later. Balance flow is gateway-agnostic — auto-charges saved cards via `WC_Payment_Tokens` where supported (Stripe etc.), falls back to a scheduled payment-link email for everything else (Xendit VAs/QRIS/e-wallets, bank transfer, COD, …).
- **Photo galleries**: named sub-galleries per property (e.g. *Bedroom 1*, *Pool*) with `[ibb_gallery]` shortcode, built-in lightbox, and an Elementor dynamic tag in the gallery category.

---

## Architecture at a glance

```
plugins/ibb-rentals/
  ibb-rentals.php           # bootstrap (plugin header, autoloader, HPOS, requirements gate)
  uninstall.php             # opt-in data purge
  composer.json             # PSR-4 + sabre/vobject (mozart-prefixed)
  readme.txt                # WordPress.org plugin directory readme
  CLAUDE.md                 # working agreement for Claude sessions
  README.md / RUNBOOK.md / TROUBLESHOOTING.md / CHANGELOG.md   # this doc set
  .claude/settings.json     # auto-commit hook for doc edits (committed)
  .gitattributes / .gitignore / .distignore
  includes/                 # PSR-4 root: namespace IBB\Rentals
    Plugin.php              # service container + boot()
    Autoloader.php          # hand-rolled PSR-4 (works without composer install)
    Admin/                  # wp-admin UI
    Cron/                   # Action Scheduler job handlers
    Domain/                 # immutable value objects (no WP/DB dependency)
    Frontend/               # public rendering: shortcodes, assets, template loader
    Ical/                   # iCal exporter, importer, parser, scheduler
    Integrations/           # third-party glue (currently Elementor)
    PostTypes/              # CPT + taxonomies registration
    Repositories/           # SQL layer over the four custom tables
    Rest/                   # REST API: registrar + controllers
    Services/               # business logic (availability, pricing, booking, balance)
    Setup/                  # activation lifecycle + schema migrations
    Support/                # cross-cutting helpers: hooks, logger
    Woo/                    # WooCommerce integration: product type, cart, order observer
  templates/                # default front-end templates (theme-overridable)
  assets/src/               # webpack source (build/ is gitignored)
  tests/                    # PHPUnit suite (deferred until manual smoke-testing settles)
```

**Key conventions:**
- Namespace `IBB\Rentals\` (PSR-4 → `includes/`); vendor namespace `IBB\Rentals\Vendor\` (Mozart-prefixed).
- DB tables `{wp_prefix}ibb_blocks` / `_rates` / `_bookings` / `_ical_feeds`.
- Postmeta keys `_ibb_*`. Options `ibb_rentals_*`. Hook names `ibb-rentals/*` (slashes, à la Gutenberg).
- Action Scheduler hooks use underscores (`ibb_rentals_*`) under group `ibb-rentals`.
- HPOS-safe: every order access goes through `wc_get_order()` / `$order->get_meta()` — never `get_post_meta` on order IDs.
- Money is stored as `DECIMAL(12,2)`; computations are floats with `round($v, 2)` at boundaries (matches WC).

---

## Components

| Component | What it does |
|-----------|--------------|
| [Admin](includes/Admin/README.md) | wp-admin menu, property metabox tabs, bookings list table, settings page |
| [Cron](includes/Cron/README.md) | Action Scheduler job handlers (cleanup holds, balance charge, payment link, iCal import) |
| [Domain](includes/Domain/README.md) | Immutable value objects: `DateRange`, `Block`, `Property`, `Quote` |
| [Frontend](includes/Frontend/README.md) | Shortcodes, asset enqueueing, single-property template, lightbox |
| [Ical](includes/Ical/README.md) | iCal export feed, in-house parser, importer, feed scheduler |
| [Integrations](includes/Integrations/README.md) | Self-contained third-party modules — Elementor (gallery dynamic tag), future WPML / Bricks / etc. |
| [PostTypes](includes/PostTypes/README.md) | `ibb_property` CPT + amenity / location / property-type taxonomies |
| [Repositories](includes/Repositories/README.md) | SQL layer over the four custom tables |
| [Rest](includes/Rest/README.md) | REST API: route registrar + thin controllers |
| [Services](includes/Services/README.md) | Business logic: availability, pricing, booking, balance |
| [Setup](includes/Setup/README.md) | Activation lifecycle, schema migrations, requirements check |
| [Support](includes/Support/README.md) | Hook-name constants, logger |
| [Woo](includes/Woo/README.md) | WooCommerce integration: product type, cart, checkout, order observer, gateway capabilities |
| [templates](templates/README.md) | Default theme-overridable front-end templates |

---

## Boundary choices

The architectural decisions driving v1.0 — see [docs/architecture.md](docs/architecture.md) for the full ADR including trade-offs accepted.

- **iCal-only sync** in v1 — works with every OTA, no partner approval required. Channel-manager APIs (deeper push of rates/inventory) is a v1.2+ item.
- **Multi-property from day 1** — modeled as a custom post type, never single-property as a special case.
- **Custom DB tables** for blocks / rates / bookings / feeds — date-range overlap is the hot query and needs proper compound indexes; postmeta would be a scan.
- **1:1 hidden WC product mirror** — auto-managed per property by `Woo/ProductSync`. Keeps WC's order, coupon, tax, and reporting pipelines working without exposing rentals in `/shop`.
- **Gateway-agnostic deposit flow** — uses WC's `WC_Payment_Tokens` API where the active gateway supports off-session reuse (Stripe, Braintree, etc.); falls back to a scheduled payment-link email for everything else (Xendit VAs/QRIS/e-wallets, bank transfer, COD).
- **HPOS-compatible** — declared at boot; every order access goes through `wc_get_order()`.
- **Half-open date ranges** `[checkin, checkout)` — turnover days are NOT overlaps. Matches iCal `VALUE=DATE` semantics.
- **Action Scheduler not WP-Cron** for all background jobs — group `ibb-rentals`, hook prefix `ibb_rentals_*`.

---

## Roadmap

### v1.0 — shipped
End-to-end direct booking flow. See [CHANGELOG.md](CHANGELOG.md) for the full feature list.

### v1.1 — deferred (priority order)
1. **Admin timeline view** ✓ — multi-property timeline added to the Availability Calendar page (Month / Week / Timeline toolbar). Each property is a horizontal row; blocks render as colored bars spanning their date range.
2. **iCal hub-and-spoke** — make the plugin the central iCal source of truth. Today the exporter only re-exports `direct` + `manual` blocks to avoid OTA-to-OTA loops; instead, export ALL blocks with per-OTA filtered feed URLs (`exclude=airbnb`, `exclude=booking`, etc.) so each OTA's calendar shows a unified view of every other OTA's bookings + ClickUp guest data. Outgoing `SUMMARY` becomes `{guest_name} ({Source})` (from ClickUp sync) instead of hardcoded "Reserved", with a Settings toggle to fall back to the privacy-safe form. Migration step: user removes OTA-to-OTA cross-subscriptions, subscribes each OTA only to its filtered plugin feed.
3. **PHPUnit + integration test suite** — covering range overlap, pricing combinatorics, iCal round-trip, cancel/release lifecycle, HPOS read/write.
4. **Guest review aggregation** — pull reviews from multiple OTA sources (Airbnb, Booking.com, etc.), normalise to a standard internal format, output as a single Gutenberg block / Elementor widget.
5. **Owner / manager roles** — multi-author properties for agencies managing fleets.
6. **Inquiry / quote-request flow** — potential bookers can submit a date enquiry or countered quote; host can accept, counter-offer, or decline. Separate from the instant-book flow.
7. **Multi-language** — WPML / Polylang glue.

### v1.2+ — future
- **Channel-manager-style API integrations** — push availability natively to OTAs (not just iCal).
- **Dynamic pricing** — occupancy-based, days-to-arrival, integrations with PriceLabs / Beyond / Wheelhouse.
- **Smart-lock integrations** (August, Yale, etc.).
- **Refundable security-deposit holds** — Stripe manual-capture (low priority; current workflow uses a separate QR-code collection process).
- **In-site guest ↔ host messaging** — secure thread per booking.
- **Promo / coupon enhancements** beyond what native WC supports.

Full ADR with trade-offs and rationale: [docs/architecture.md](docs/architecture.md).

---

## Risks & known limitations

| Risk | Mitigation |
|---|---|
| **iCal sync interval race** — 30-min window where an OTA booking isn't yet visible to us | Document; recommend 5-min intervals for high-volume properties; perfect sync is impossible without channel-manager APIs (v1.2+) |
| **Off-session balance failure** (token-capable gateways) — declined card / SCA | 3 retries at 24h spacing, then fall back to payment-link email |
| **Xendit-specific** — most flows (VA, e-wallet, QRIS) are one-shot; deposit-mode balance always uses the payment-link path on this gateway | Expected behaviour, not a bug; documented in Woo/TROUBLESHOOTING |
| **Security deposit** — no in-plugin refundable-hold mechanism yet | Handled externally via QR-code collection; in-plugin holds deferred to v1.2+ |
| **Currency** — WC supports one currency; multi-currency is out of scope | Documented; multi-currency deferred indefinitely |
| **Theme compatibility** | Plugin template fallback + theme override path; CSS scoped under `.ibb-` BEM-style |
| **HPOS edges** — any code path bypassing `wc_get_order()` silently breaks on HPOS sites | Lint check before merging; conventions in [CLAUDE.md](CLAUDE.md) |
| **Page builders** (Elementor / Beaver / Bricks) may bypass single-CPT templates | Blocks/widgets and the Elementor dynamic tag are the supported integration paths |

Full risk register: [docs/architecture.md#risks--known-limitations](docs/architecture.md#risks--known-limitations).

---

## External libraries

- **sabre/vobject ^4.5** — *optional* iCal parser. Mozart-prefixed to `IBB\Rentals\Vendor\Sabre\VObject` to avoid colliding with other plugins. The in-house [`Ical/Parser.php`](includes/Ical/README.md) covers the dialect every major OTA actually emits, so this dep is only needed for exotic feeds.
- **Flatpickr 4.6** — front-end date picker, loaded from CDN.
- **Action Scheduler** — already shipped with WC, not vendored.
- **FullCalendar** — planned for v1.1 admin calendar (not yet integrated).

---

## Public hooks

Integrators can hook the actions and filters listed in [`includes/Support/Hooks.php`](includes/Support/Hooks.php). Quick reference:

- **Actions**: `ibb-rentals/booted`, `…/booking/created`, `…/booking/cancelled`, `…/quote/computed`, `…/ical/before_export`, `…/ical/after_import`, `…/balance/charged`, `…/balance/failed`.
- **Filters**: `ibb-rentals/quote/breakdown`, `…/availability/is_available`, `…/ical/export_summary`, `…/gateways/token_capable`.

Full table with args and timing: [docs/architecture.md#public-hooks-contract](docs/architecture.md#public-hooks-contract).

---

## Testing

**Manual smoke tests** — see [RUNBOOK.md "Manual smoke-test checklist"](RUNBOOK.md#manual-smoke-test-checklist-when-no-phpunit-yet) for the 9-step end-to-end procedure (activate → property → quote → cart → order → balance → cancel → uninstall).

**Automated** — deferred to v1.1. Will use PHPUnit + `wp-phpunit/wp-phpunit` + WC test helpers + `brain/monkey`. Test scenarios are listed in [docs/architecture.md#testing-strategy](docs/architecture.md#testing-strategy).

---

## Installation (for development)

1. Clone this repo into `wp-content/plugins/ibb-rentals/` of a WP install with WooCommerce 9.0+ active.
2. (Optional, for iCal vendor lib in production) `composer install --no-dev && composer mozart-compose`.
3. Activate via wp-admin. The activator runs DB migrations, generates an HMAC secret, seeds default settings, and queues a rewrite-rule flush on the next `init`.
4. Visit any wp-admin page once after activation so the queued rewrite flush fires.
5. **Rentals → Properties → Add New** to create your first property.
6. Configure rates, rules, photos, and iCal feeds via the tabbed metabox.

For end-user install instructions see [readme.txt](readme.txt).

---

## Docs

| | |
|--|--|
| [CLAUDE.md](CLAUDE.md) | Working agreement for Claude Code sessions: knowledge filing rules, plugin context |
| [RUNBOOK.md](RUNBOOK.md) | Project-level procedures and how-tos |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Plugin-wide known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Plugin-wide change history |
| [docs/architecture.md](docs/architecture.md) | Architecture decision record — original v0.1.0 plan, boundary choices, full risk register, public hooks contract, testing strategy |
| [docs/MEMORY_PALACE_SETUP_PLUGIN-DEV.md](docs/MEMORY_PALACE_SETUP_PLUGIN-DEV.md) | Setup guide for the four-doc-per-component system this repo follows. Already in place here; preserved for replicating it on other plugin repos. |

Each component directory carries its own four-doc set — see the components table above.
