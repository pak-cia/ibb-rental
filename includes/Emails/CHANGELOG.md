# Emails — Changelog

## [Unreleased]

---

## [0.8.0] — 2026-04-30

### Added
- **`WpEditorFieldTrait`** — registers a custom `'type' => 'wp_editor'` form-field handler on `WC_Email` subclasses. Drops `use WpEditorFieldTrait;` into a class to enable a TinyMCE rich-text editor (Add Media, formatting, lists, links, alignment, Visual/Code tabs) for any field declared with `'type' => 'wp_editor'`. Saved values pass through `wp_kses_post`.
- **Both email classes use the trait for `additional_content`** — replaces the plain textarea with the full editor, so admins get rich text for the closing block of each email without touching template code.

---

## [0.7.0] — 2026-04-30

### Added
- **`init_form_fields()` overrides on both email classes** expose `enabled`, `subject`, `heading`, `additional_content`, `reply_to_email`, and `email_type` settings in WooCommerce → Settings → Emails. Brings IBB emails to feature parity with WC's standard customer emails (which previously had editable subject/heading while ours did not).
- **Per-email Reply-To override** — both classes override `WC_Email::get_headers()` to use the configured `reply_to_email` setting when set, falling back to WC's default. Fixes the issue where Reply-To silently defaulted to the WC store admin email (because our `$this->object` is a booking array, not a `WC_Order`, so WC's default Reply-To logic skipped). Set `hello@example.com` so guest replies go to a customer-service inbox instead of the admin.
- **Namespaced template path** — both `get_content_html()` / `get_content_plain()` pass `template_path = 'ibb-rentals/'` to `wc_get_template_html()`. Theme overrides at `your-theme/ibb-rentals/emails/booking-confirmation.php` (or `booking-reminder.php`) without colliding with WooCommerce's own `your-theme/woocommerce/emails/...` templates.

### Changed
- **Templates now render the admin-editable Additional content block.** Both HTML and plain-text templates print `$email->get_additional_content()` (with a sensible default fallback) where the hardcoded paragraph used to live. The previous wording is preserved as the default for behaviour parity on first install.

---

## [0.4.0] — 2026-04-30

### Added
- **`BookingConfirmationEmail`** — guest-facing booking confirmation, triggered on `ibb-rentals/booking/created`. Uses `templates/emails/booking-confirmation.php` for HTML and `…-plain.php` for plain-text. Subject placeholders: `{property_title}`, `{checkin}`, `{checkout}`. (Originally added in 0.1.0; recorded here as Emails component was previously undocumented.)
- **`BookingReminderEmail`** — pre-arrival reminder, triggered by `Cron/Jobs/ReminderEmailJob` (one-shot scheduled 3 days before check-in). (Originally added in 0.1.0; recorded here as Emails component was previously undocumented.)
