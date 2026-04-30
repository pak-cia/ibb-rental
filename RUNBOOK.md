# IBB Rentals — Runbook

Plugin-wide procedures and how-tos. Component-specific procedures live in each component's `RUNBOOK.md`.

---

## Local development setup

1. Clone into a WP install's `wp-content/plugins/ibb-rentals/`. WP 6.5+, WC 9.0+, PHP 8.1+.
2. (Optional) `composer install` if you want sabre/vobject as the iCal parser. The plugin works without — `Ical/Parser.php` covers the dialect every major OTA actually emits.
3. Activate via wp-admin. Activator runs DB migrations, generates `ibb_rentals_token_secret`, seeds defaults, queues a rewrite-rule flush.
4. Visit any wp-admin page once after activation so the queued flush fires (or hit Settings → Permalinks → Save).

## First property — quickest path to "live"

1. **Rentals → Add New Property**, set title + excerpt, publish.
2. In the metabox: **Details** tab → set Max guests, Bedrooms, Bathrooms.
3. **Rates** tab → set Base nightly rate.
4. **Booking rules** tab → set Min nights. (Defaults are sensible for the rest.)
5. **Photos** tab → add a gallery, click "+ Add images".
6. Visit the property's permalink — booking widget renders, dates can be picked.

## Activate the deposit + balance flow

1. On the property's **Booking rules** tab: switch Payment mode to "Deposit + balance later", set Deposit % and Balance lead time (days before check-in).
2. Make sure check-in is at least `lead_days + 2` in the future (the engine auto-falls-back to full payment otherwise — see `PricingService::split_payment`).
3. The Booking rules tab shows a live "Gateway capabilities" panel: each active WC gateway is classified as `auto_charge` (saved-card off-session) or `payment_link` (scheduled email).
4. Bookings made in deposit mode result in:
   - WC order total = deposit amount only (cart price set in `Woo/CartHandler::apply_prices`).
   - A `vrp_bookings` row with `status='balance_pending'`, `balance_due_date` set.
   - An Action Scheduler job: `ibb_rentals_charge_balance` (token-capable gateway) or `ibb_rentals_send_payment_link` (everything else).

## Configure ClickUp guest-name sync

The plugin can pull guest names from a ClickUp Bookings list and display them on the admin calendar.

**One-time setup:**
1. Get your ClickUp personal API token: ClickUp avatar → Settings → Apps → "Generate" under Personal API Token.
2. Go to **Rentals → Settings → ClickUp integration** and paste the API token.
3. Click **Connect / refresh**. The Workspace dropdown populates from the API.
4. Pick the workspace → the Space dropdown populates → pick the space → Folder dropdown populates → pick the folder → Bookings list dropdown populates → pick the list. The plugin records the workspace ID and list ID for sync calls.
5. Adjust the tag→source map if your ClickUp tags use different names (defaults: `abnb→airbnb, agoda→agoda, booking→booking, vrbo→vrbo, expedia→expedia`).
6. Fill in the **Unit code → property** table: for each IBB property, enter the unit identifier(s) you use as the prefix in ClickUp task titles (`v1 - Bob Jones` → `v1`). Multiple codes per property are allowed, comma-separated. Codes are case-insensitive.
7. Save, then click **Sync now**.

**Per-task data the sync reads:** task title is split on " - " — the prefix is looked up in the unit-code map (resolves to a property ID) and the suffix becomes the guest name. `start_date` and `due_date` (ms timestamps) become check-in / check-out. `tags[].name` is matched against the tag→source map. No ClickUp custom fields are required.

**Match strategy:** `UPDATE wp_ibb_blocks SET guest_name = ? WHERE start_date = ? AND end_date = ? AND source = ? [AND property_id = ?]` — the `property_id` clause is added only when the task title's unit-code prefix is mapped. Unmapped tasks fall back to the 3-tuple match (and so can paint the same guest_name onto blocks at multiple properties if dates + OTA collide; map every property to avoid that).

**Tag → source map defaults:**
```json
{"abnb":"airbnb","airbnb":"airbnb","agoda":"agoda","booking":"booking","vrbo":"vrbo","expedia":"expedia"}
```

**How matching works:** For each ClickUp task the job extracts check-in date, check-out date, and OTA source (from tag). It then runs `UPDATE wp_ibb_blocks SET guest_name = ? WHERE start_date = ? AND end_date = ? AND source = ?`. Once updated, the timeline bars and detail modals show the guest name instead of just "Airbnb"/"Agoda"/etc.

**Scheduled action:** `ibb_rentals_sync_clickup` (recurring, default 1 h). Visible under WooCommerce → Status → Scheduled Actions, group `ibb-rentals`.

## Inspect / cancel scheduled actions

WooCommerce → Status → Scheduled Actions. Filter by group `ibb-rentals`. You'll see:
- `ibb_rentals_cleanup_holds` (recurring 5 min) — sweeps expired cart holds
- `ibb_rentals_import_ical_feed` (recurring per-feed) — polls each registered iCal URL
- `ibb_rentals_charge_balance` / `ibb_rentals_send_payment_link` (one-shot per booking)

To cancel a scheduled balance charge, click "Cancel" on its row — the booking will fall back to the payment-link path next time it's due.

## Rotate the iCal export-feed secret

Delete the option `ibb_rentals_token_secret` (e.g. via Tools → wp-cli `wp option delete ibb_rentals_token_secret` or directly in `wp_options`). On the next activation or page load, `Setup\Installer::ensure_secret()` will generate a fresh one. **Note**: this invalidates EVERY property's export feed URL globally. To rotate just one property, delete its `_ibb_ical_export_token` postmeta — `Domain\Property::ical_export_token()` regenerates it.

## Build a distribution ZIP

A one-command build is provided. From the plugin root:

```bash
./build.sh                # default — packages HEAD via `git archive`
./build.sh --working      # packages your working tree (uncommitted changes too)
```

Output lands in `dist/ibb-rentals-<version>.zip`. The `dist/` folder is gitignored.

### What gets stripped

The Memory Palace dev docs (`README.md`, `CLAUDE.md`, `RUNBOOK.md`, `TROUBLESHOOTING.md`, `CHANGELOG.md` at every level — including all `includes/*/*.md` and `templates/*.md`), the `docs/` folder, the `.claude/` folder, `tests/`, `composer.json` / `package.json` / lockfiles, `webpack.config.js`, `build.sh` itself, `.gitignore` / `.gitattributes` / `.distignore`, and OS junk (`.DS_Store`, `Thumbs.db`).

The user-facing `readme.txt` (WordPress.org plugin-directory format) **does** ship. So does everything under `includes/` (PHP only, no markdown), `templates/single-ibb_property.php`, the main bootstrap `ibb-rentals.php`, `uninstall.php`, and (if present) `vendor/` from `composer install --no-dev`.

### Three ways to package

The build script defaults to `git archive` because it works without extra tools. Three exclusion mechanisms are kept in lock-step so any of them produce the same result:

| Tool | Reads | Use case |
|---|---|---|
| `./build.sh` (default) | `.gitattributes` `export-ignore` | Local dev builds, CI, anyone with git |
| `./build.sh --working` | `.distignore` (via rsync) | Working tree with uncommitted changes |
| `wp dist-archive .` (wp-cli) | `.distignore` | If you already have wp-cli in the path |
| 10up/action-wordpress-plugin-deploy (GitHub Actions) | `.distignore` | Auto-deploy to WordPress.org plugin SVN |

If you change exclusion rules, update **both** `.distignore` and `.gitattributes` so all three paths agree.

### Verify what's in the zip

```bash
unzip -l dist/ibb-rentals-<version>.zip
```

Quick check that no dev files leaked through:

```bash
unzip -l dist/ibb-rentals-<version>.zip | grep -iE 'README\.md|RUNBOOK|TROUBLESH|CHANGELOG|CLAUDE|\.claude|docs/|tests/|composer\.json|build\.sh|\.gitattributes|\.gitignore|\.distignore'
```

Should match nothing (the user-facing `readme.txt` does NOT match this pattern).

## Cloudflare / WAF bypass for server-to-server endpoints

Two plugin endpoints receive requests from external servers (not browsers), so they are challenged or blocked by Cloudflare's Bot Fight Mode and equivalent WAF features on other providers.

| Endpoint | Caller | Why it gets blocked |
|---|---|---|
| `/wp-json/ibb-rentals/v1/ical/{id}.ics` | Airbnb, Booking.com, VRBO, Agoda — automated polling bots | Bot Fight Mode sees a non-browser user-agent and challenges the request |
| `/?wc-api=WC_Xendit_Invoice` (or `WC_Xendit_Callback`) | Xendit payment servers — server-to-server POST after customer pays | Same — no browser fingerprint |

All other plugin endpoints (`/wp-json/ibb-rentals/v1/availability`, `/quote`, `/properties`, `/bookings`, etc.) are called by the guest's browser, so they carry a real browser fingerprint and are never challenged.

### Cloudflare — one rule, free plan

On Cloudflare's free plan you have 5 custom rules. Only **one rule** is needed for this plugin.

**Rule expression** (paste into Cloudflare → Security → WAF → Custom Rules → expression editor):

```
(http.request.uri.path contains "/wp-json/ibb-rentals/v1/ical") or (http.request.uri.query contains "wc-api")
```

**Action**: Skip

**WAF components to skip**: Check **All Super Bot Fight Mode Rules** from the main list.

> **Free-plan note**: Cloudflare does not expose "Bot Fight Mode" as a skippable component on the free plan — that granular control requires Pro+. "All Super Bot Fight Mode Rules" is the closest available checkbox and may cover the free-tier Bot Fight Mode internally. If OTA polling or Xendit callbacks are still challenged after go-live, fall back to **Option B** below.

**Option B — disable Bot Fight Mode globally** (free-plan fallback): Security → Bots → toggle Bot Fight Mode off. This is a blunt instrument but still leaves all WAF custom rules and managed rules running. Acceptable for most small rental sites.

**Place at**: First (so the skip fires before any blocking rule).

This covers every iCal export URL for every property and every Xendit callback variant in a single rule.

### Other WAF / firewall providers

The same two path patterns apply regardless of provider:

- **Sucuri / Wordfence**: whitelist the path `/wp-json/ibb-rentals/v1/ical*` and the query string parameter `wc-api` for the IP ranges published by Xendit and any OTA you use.
- **nginx / Apache `mod_security`**: create a location-based exception for those paths before the WAF `SecRule` set loads.
- **Server-level IP allowlist**: Xendit publishes their webhook IP range in their developer docs. OTAs rotate IPs frequently, so path-based rules are more reliable than IP allowlists for iCal.

### After go-live: verify nothing is silently blocked

1. Cloudflare → Security → Security Events: filter last 24h for "Bot Fight Mode" and "Managed Challenge". Any blocked requests to the two paths above indicate the rule wasn't saved correctly.
2. In Xendit dashboard → Webhooks: check the delivery log for the first test payment. A non-200 response means the callback is still being blocked.
3. For iCal: add the export URL of one property to a test calendar app (e.g. Google Calendar) — it will poll every few hours. Check Security Events for any blocks on that URL.

### Local development note

Xendit's webhook server cannot reach `.local` domains, so **orders stay "Pending payment" indefinitely** in local dev even after a successful hosted-invoice payment. This is expected — the `OrderObserver` (and therefore block/booking creation) never fires automatically on local.

**Workaround for local testing**: after completing payment on the hosted invoice, go to WooCommerce → Orders, open the order, and manually set the status to "Processing" or "Completed". The `OrderObserver` fires immediately and creates the booking and block records.

On production, the Xendit webhook fires automatically and the order transitions without any manual step.

### Xendit test-mode webhook (staging)

On staging with Xendit in **TEST MODE**, the same symptom appears: the hosted invoice payment succeeds (customer is redirected back to the order-received page, the `Xendit_invoice` order meta is saved), but the order stays "Pending payment" because Xendit's test environment may not POST the webhook callback to the staging URL (Cloudflare or DNS may block it, or the test-mode webhook isn't configured in the Xendit dashboard).

**Workaround on staging**: same as local — manually set the order to "Processing" in wp-admin. The `OrderObserver` fires, booking and block records are created normally.

To confirm whether the webhook fired at all: Xendit Dashboard → Webhooks → Delivery Log. A non-200 response or a missing entry means the callback never reached the site. Check the Cloudflare WAF rule (see "Cloudflare / WAF bypass" above) is in place before go-live.

---

## Push to GitHub

The repo is `https://github.com/pak-cia/ibb-rental` on `main`. Standard flow:

```bash
git add <files>
git commit -m "<concise message>"
git push
```

Doc edits auto-commit locally via the `.claude/settings.json` hook. They still need a manual `git push`.

## Manual smoke-test checklist (when no PHPUnit yet)

1. Activate plugin → no fatal, "Rentals" menu appears, four custom tables exist.
2. Create property, set base rate, save → hidden WC product mirrored.
3. Visit property permalink → booking widget renders, dates pick up Flatpickr.
4. Pick a 2-night range → quote panel renders breakdown.
5. Click Book now → cart line shows correct price (deposit-only in deposit mode).
6. Complete checkout → land on order-received page.
7. Advance order to "Processing" (or wait for gateway webhook in production) → Rentals → Bookings shows new confirmed row with correct property/dates/guests/total.
8. Cancel the order → booking status flips to Cancelled; `GET /wp-json/ibb-rentals/v1/availability?property_id=…` no longer returns those dates as blocked.
9. Check `wp_options.ibb_rentals_settings` matches what you set in Rentals → Settings.
10. iCal export URL returns valid `BEGIN:VCALENDAR…END:VCALENDAR` body.
11. Delete plugin (with "purge data" off) → posts and tables remain.
12. Delete plugin (with "purge data" on) → tables dropped, options gone.

Steps 6–8 confirmed on staging.theuluhills.com with Xendit gateway (2026-04-30). See "Xendit test-mode webhook" note below.

---

## Monthly compile pass

On the 1st of each month, read every component's docs and:
- Fill stale READMEs with new patterns introduced that month.
- Move newly-recurring issues from inline knowledge into TROUBLESHOOTING.
- Roll up component CHANGELOGs into the root CHANGELOG.
