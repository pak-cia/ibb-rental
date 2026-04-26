# IBB Rentals

Tourism vacation-rental property management for WordPress + WooCommerce. Direct bookings, iCal calendar sync (Airbnb, Booking.com, Agoda, VRBO), seasonal pricing, and gateway-agnostic deposit + balance payments.

> **Note:** the public-facing description used by the WordPress.org plugin directory lives in [`readme.txt`](readme.txt) (WP-style format). This file is the developer / contributor / Claude entry point.

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
| [Integrations](includes/Integrations/README.md) | Optional third-party glue (Elementor dynamic tag) |
| [PostTypes](includes/PostTypes/README.md) | `ibb_property` CPT + amenity / location / property-type taxonomies |
| [Repositories](includes/Repositories/README.md) | SQL layer over the four custom tables |
| [Rest](includes/Rest/README.md) | REST API: route registrar + thin controllers |
| [Services](includes/Services/README.md) | Business logic: availability, pricing, booking, balance |
| [Setup](includes/Setup/README.md) | Activation lifecycle, schema migrations, requirements check |
| [Support](includes/Support/README.md) | Hook-name constants, logger |
| [Woo](includes/Woo/README.md) | WooCommerce integration: product type, cart, checkout, order observer, gateway capabilities |
| [templates](templates/README.md) | Default theme-overridable front-end templates |

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

Each component directory carries its own four-doc set — see the components table above.
