<?php
/**
 * Frontend asset registration + enqueueing.
 *
 * Assets are only enqueued on pages that actually need them: singular property
 * pages, or any post containing one of our shortcodes. This avoids loading
 * Flatpickr on every page of every theme.
 *
 * Flatpickr is loaded from a self-hosted vendor copy under `assets/vendor/`
 * with a CDN fallback — same UX, smaller plugin, no build step required.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Frontend;

use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public function register(): void {
		// Always-register on priority 1 so handles exist even when nothing
		// on the page triggers `should_enqueue()`. Elementor widgets declare
		// `ibb-rentals-frontend` / `flatpickr` etc. via get_style_depends()
		// and get_script_depends(); Elementor's renderer auto-enqueues those
		// when the widget is on the page.
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_cart_styles' ] );
	}

	/**
	 * Register every front-end style/script handle the plugin owns. Called
	 * on `wp_enqueue_scripts` priority 1 (before any conditional enqueue).
	 *
	 * Registration is unconditional; enqueuing is gated below and from
	 * Elementor widgets via their `get_*_depends()` methods.
	 */
	public function register_assets(): void {
		wp_register_style(
			'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css',
			[],
			'4.6.13'
		);
		wp_register_script(
			'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
			[],
			'4.6.13',
			true
		);

		wp_register_style( 'ibb-rentals-frontend', false, [], IBB_RENTALS_VERSION );
		wp_add_inline_style( 'ibb-rentals-frontend', $this->css() );

		wp_register_script( 'ibb-rentals-booking', false, [ 'flatpickr' ], IBB_RENTALS_VERSION, true );
		wp_add_inline_script(
			'ibb-rentals-booking',
			sprintf(
				'window.IBBRentals=%s;',
				wp_json_encode( [
					'restUrl'  => esc_url_raw( rest_url( 'ibb-rentals/v1' ) ),
					'cartUrl'  => function_exists( 'wc_get_cart_url' ) ? esc_url_raw( wc_get_cart_url() ) : '',
					'addToCart'=> function_exists( 'wc_get_cart_url' ) ? esc_url_raw( add_query_arg( 'wc-ajax', 'add_to_cart', wc_get_cart_url() ) ) : '',
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'currency' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
					'i18n'     => [
						'pickDates'      => __( 'Select your check-in and check-out dates', 'ibb-rentals' ),
						'unavailable'    => __( 'Selected dates are not available.', 'ibb-rentals' ),
						'loading'        => __( 'Loading…', 'ibb-rentals' ),
						'bookNow'        => __( 'Book now', 'ibb-rentals' ),
						'total'          => __( 'Total', 'ibb-rentals' ),
						'depositDue'     => __( 'Deposit due now', 'ibb-rentals' ),
						'balanceDue'     => __( 'Balance due', 'ibb-rentals' ),
						'balanceDueOn'   => __( 'on', 'ibb-rentals' ),
						'nights'         => __( 'nights', 'ibb-rentals' ),
						'night'          => __( 'night', 'ibb-rentals' ),
						'avgPerNight'    => __( '/ night avg.', 'ibb-rentals' ),
						'subtotal'       => __( 'Subtotal', 'ibb-rentals' ),
						'losDiscount'    => __( 'Long-stay discount', 'ibb-rentals' ),
						'cleaningFee'    => __( 'Cleaning fee', 'ibb-rentals' ),
						'extraGuestFee'  => __( 'Extra guests', 'ibb-rentals' ),
						'securityDeposit'=> __( 'Security deposit (refundable)', 'ibb-rentals' ),
					],
				] )
			),
			'before'
		);
		wp_add_inline_script( 'ibb-rentals-booking', $this->js() );
	}

	/**
	 * Tiny scoped stylesheet for the cart-line-meta labels.
	 *
	 * Why a stylesheet instead of inline style on the <strong> tags: the
	 * WC Cart block's StoreAPI pipeline (or a security plugin filtering
	 * `wp_kses_allowed_html` on some installs) strips the `style` attribute
	 * from <strong> elements in the cart-item-data response. Class
	 * attributes survive every reasonable kses config, so we hang the
	 * bolding off `.ibb-cart-meta-label` and ship the rule here.
	 *
	 * Scoped to our own class name — no theme conflicts, no theme-fighting
	 * selectors targeting `.cart_item` / `dl.variation` / etc.
	 *
	 * Enqueues whenever the cart contains an IBB item, regardless of
	 * which page is rendering. The CSS only matches elements we emit, so
	 * it's a no-op everywhere else.
	 */
	public function maybe_enqueue_cart_styles(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$has_ibb = false;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['ibb'] ) ) {
				$has_ibb = true;
				break;
			}
		}
		if ( ! $has_ibb ) {
			return;
		}
		wp_register_style( 'ibb-rentals-cart', false, [], IBB_RENTALS_VERSION );
		wp_enqueue_style( 'ibb-rentals-cart' );
		// `!important` here defeats the user-agent's `strong { font-weight: bolder }`
		// when the inherited body weight is light (e.g. Twenty Twenty-Five at 300,
		// where `bolder` resolves to 400 and looks effectively non-bold).
		wp_add_inline_style(
			'ibb-rentals-cart',
			'.ibb-cart-meta-label{font-weight:700!important}'
		);
	}

	public function maybe_enqueue(): void {
		if ( ! $this->should_enqueue() ) {
			return;
		}
		// Handles + their inline data are already registered in
		// `register_assets()`. Just enqueue the ones we need.
		wp_enqueue_style( 'flatpickr' );
		wp_enqueue_script( 'flatpickr' );
		wp_enqueue_style( 'ibb-rentals-frontend' );
		wp_enqueue_script( 'ibb-rentals-booking' );
	}

	private function should_enqueue(): bool {
		if ( is_singular( PropertyPostType::POST_TYPE ) || is_post_type_archive( PropertyPostType::POST_TYPE ) ) {
			return true;
		}
		global $post;
		if ( $post instanceof \WP_Post ) {
			// Elementor widgets — stored in `_elementor_data` post meta as JSON,
			// not in post_content, so has_shortcode/has_block won't find them.
			$elementor_data = (string) get_post_meta( $post->ID, '_elementor_data', true );
			if ( $elementor_data !== '' ) {
				foreach ( [ 'ibb_booking_form', 'ibb_property_details', 'ibb_property_gallery', 'ibb_property_carousel' ] as $widget ) {
					if ( strpos( $elementor_data, '"widgetType":"' . $widget . '"' ) !== false ) {
						return true;
					}
				}
			}
		}
		if ( $post instanceof \WP_Post && $post->post_content ) {
			foreach ( [ 'ibb/booking-form', 'ibb/gallery', 'ibb/property-details' ] as $block ) {
				if ( has_block( $block, $post ) ) {
					return true;
				}
			}
			foreach ( [ 'ibb_property', 'ibb_search', 'ibb_calendar', 'ibb_booking_form', 'ibb_gallery', 'ibb_property_details' ] as $sc ) {
				if ( has_shortcode( $post->post_content, $sc ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private function css(): string {
		return <<<CSS
.ibb-booking { border:1px solid #e1e5e9; border-radius:8px; padding:20px; max-width:380px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.04); }
.ibb-booking__title { font-size:1.1em; margin:0 0 12px; }
.ibb-booking__field { display:flex; flex-direction:column; margin-bottom:12px; }
.ibb-booking__field label { font-size:.85em; margin-bottom:4px; color:#475569; }
.ibb-booking__field > input { padding:8px 10px; border:1px solid #cbd5e1; border-radius:4px; font-size:.95em; }
.ibb-booking__hint { color:#64748b; font-size:.78em; margin-top:4px; }
.ibb-booking__stepper { display:flex; align-items:stretch; border:1px solid #cbd5e1; border-radius:4px; overflow:hidden; max-width:160px; }
.ibb-booking__stepper input { flex:1; min-width:0; border:0; text-align:center; font-size:1em; font-weight:600; padding:8px 4px; -moz-appearance:textfield; background:#fff; }
.ibb-booking__stepper input::-webkit-outer-spin-button,
.ibb-booking__stepper input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.ibb-booking__step { width:38px; border:0; background:#f1f5f9; color:#0f172a; font-size:1.2em; font-weight:600; cursor:pointer; transition:background .15s; }
.ibb-booking__step:hover:not([disabled]) { background:#e2e8f0; }
.ibb-booking__step[disabled] { opacity:.4; cursor:not-allowed; }
.ibb-booking__step--down { border-right:1px solid #cbd5e1; }
.ibb-booking__step--up { border-left:1px solid #cbd5e1; }
.ibb-booking__quote { background:#f8fafc; border-radius:6px; padding:14px; margin:12px 0; font-size:.92em; min-height:1px; }
.ibb-booking__quote:empty { padding:0; margin:0; }
.ibb-booking__quote-section { padding:6px 0; }
.ibb-booking__quote-section + .ibb-booking__quote-section { border-top:1px solid #e2e8f0; }
.ibb-booking__quote-row { display:flex; justify-content:space-between; align-items:baseline; padding:3px 0; }
.ibb-booking__quote-row--muted { color:#64748b; font-size:.88em; }
.ibb-booking__quote-row--discount { color:#15803d; }
.ibb-booking__quote-total { font-weight:700; font-size:1.05em; padding:8px 0 4px; border-top:2px solid #0f172a; margin-top:6px; }
.ibb-booking__quote-payment { background:#fffbeb; border:1px solid #fde68a; border-radius:4px; padding:8px 10px; margin-top:10px; font-size:.88em; }
.ibb-booking__quote-payment .ibb-booking__quote-row { padding:2px 0; }
.ibb-booking__quote-payment-label { font-weight:600; color:#92400e; margin-bottom:4px; }
.ibb-booking__submit { width:100%; padding:10px 14px; border:0; border-radius:4px; background:#2563eb; color:#fff; font-weight:600; cursor:pointer; }
.ibb-booking__submit[disabled] { opacity:.5; cursor:not-allowed; }
.ibb-booking__error { color:#b91c1c; font-size:.9em; margin:8px 0; }
.ibb-booking__loading { color:#64748b; font-size:.9em; padding:8px 0; }

/* Booking form skins (Elementor only). The form markup is identical
   across skins; the wrapper class flips the layout. */
.ibb-booking-skin--horizontal .ibb-booking { max-width:none; }
.ibb-booking-skin--horizontal form.ibb-booking { display:flex; flex-wrap:wrap; align-items:flex-end; gap:12px; }
.ibb-booking-skin--horizontal .ibb-booking__field { flex:1 1 200px; min-width:200px; margin:0; }
.ibb-booking-skin--horizontal .ibb-booking__title { flex:0 0 100%; margin-bottom:4px; }
.ibb-booking-skin--horizontal .ibb-booking__quote,
.ibb-booking-skin--horizontal .ibb-booking__error { flex:0 0 100%; }
.ibb-booking-skin--horizontal .ibb-booking__submit { flex:0 0 auto; min-width:140px; }

.ibb-booking-skin--inline .ibb-booking { max-width:none; padding:14px 16px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
.ibb-booking-skin--inline form.ibb-booking { display:flex; flex-wrap:wrap; align-items:end; gap:10px; }
.ibb-booking-skin--inline .ibb-booking__title { display:none; }
.ibb-booking-skin--inline .ibb-booking__field { flex:1 1 180px; margin:0; }
.ibb-booking-skin--inline .ibb-booking__field label { font-size:.75em; }
.ibb-booking-skin--inline .ibb-booking__field > input,
.ibb-booking-skin--inline .ibb-booking__stepper { padding:6px 8px; }
.ibb-booking-skin--inline .ibb-booking__hint { display:none; }
.ibb-booking-skin--inline .ibb-booking__quote,
.ibb-booking-skin--inline .ibb-booking__error { flex:0 0 100%; }
.ibb-booking-skin--inline .ibb-booking__submit { flex:0 0 auto; padding:10px 18px; height:38px; align-self:end; }
@media (max-width:640px) {
  .ibb-booking-skin--horizontal form.ibb-booking,
  .ibb-booking-skin--inline form.ibb-booking { flex-direction:column; align-items:stretch; }
  .ibb-booking-skin--inline .ibb-booking__title { display:block; }
}

.ibb-booking-preview-hint { margin-bottom:12px; }

.ibb-property-carousel { width:100%; max-width:100%; position:relative; min-height:120px; box-sizing:border-box; }
.ibb-property-carousel * { box-sizing:border-box; }
.ibb-property-carousel .swiper { width:100%; max-width:100%; overflow:hidden; }
/* Pre-init fallback: keep slides visible (and reasonably sized) while
   Swiper hasn't initialised yet — without this the editor preview shows
   an empty grey rectangle on Elementor 4.x atomic-widgets builds. */
.ibb-property-carousel .swiper:not(.swiper-initialized) .swiper-wrapper { display:flex; gap:8px; flex-wrap:wrap; }
.ibb-property-carousel .swiper:not(.swiper-initialized) .swiper-slide { width:auto; max-width:100%; flex:0 0 auto; }
.ibb-property-carousel .swiper-slide { display:flex; align-items:center; justify-content:center; max-width:100%; }
.ibb-property-carousel__image { width:100%; max-width:100%; height:auto; display:block; border-radius:6px; }
.ibb-property-carousel-placeholder { padding:14px 16px; background:#fef3c7; border:1px dashed #d97706; border-radius:6px; color:#78350f; font-size:.9em; line-height:1.4; }
.ibb-property-carousel .swiper-button-prev,
.ibb-property-carousel .swiper-button-next { color:#fff; background:rgba(0,0,0,.45); width:36px; height:36px; border-radius:50%; --swiper-navigation-size: 16px; backdrop-filter:blur(4px); }
.ibb-property-carousel .swiper-button-prev:hover,
.ibb-property-carousel .swiper-button-next:hover { background:rgba(0,0,0,.7); }
.ibb-property-carousel .swiper-pagination-bullet { background:#fff; opacity:.7; }
.ibb-property-carousel .swiper-pagination-bullet-active { opacity:1; }
.ibb-property-carousel--carousel { overflow:hidden; }

/* Slideshow layout: large main image + thumbnail strip below */
.ibb-property-carousel--slideshow { display:flex; flex-direction:column; gap:10px; }
.ibb-property-carousel--slideshow .ibb-property-carousel__main { position:relative; overflow:hidden; border-radius:6px; }
.ibb-property-carousel--slideshow .ibb-property-carousel__thumbs { overflow:hidden; }
.ibb-property-carousel__thumb { cursor:pointer; opacity:.55; border-radius:4px; overflow:hidden; transition:opacity .2s, outline-color .2s; outline:2px solid transparent; outline-offset:0; }
.ibb-property-carousel__thumb:hover { opacity:.85; }
.ibb-property-carousel__thumb.swiper-slide-thumb-active { opacity:1; outline-color:#2563eb; }
.ibb-property-carousel__thumb-image { width:100%; height:100%; object-fit:cover; aspect-ratio:1.4; display:block; }

.ibb-details--grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(110px, 1fr)); gap:12px; padding:14px 0; }
.ibb-details--grid .ibb-details__item { display:flex; flex-direction:column; align-items:flex-start; padding:10px 12px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; }
.ibb-details--grid .ibb-details__value { font-size:1.4em; font-weight:700; line-height:1.1; color:#0f172a; }
.ibb-details--grid .ibb-details__label { font-size:.82em; color:#64748b; margin-top:2px; }
.ibb-details--compact { font-size:.95em; color:#475569; }
.ibb-details--compact strong { color:#0f172a; font-weight:700; }
.ibb-details--list { display:grid; grid-template-columns:max-content 1fr; gap:6px 16px; margin:0; padding:0; }
.ibb-details--list dt { font-weight:600; color:#475569; }
.ibb-details--list dd { margin:0; color:#0f172a; }
.ibb-details__icon { display:inline-flex; align-items:center; justify-content:center; line-height:1; vertical-align:middle; }
.ibb-details__icon i { line-height:1; }
.ibb-details__icon svg { display:block; }
.ibb-details--grid .ibb-details__item .ibb-details__icon { margin-bottom:4px; margin-right:0; }

.ibb-gallery-display { display:grid; gap:8px; }
.ibb-gallery-display--cols-1 { grid-template-columns:1fr; }
.ibb-gallery-display--cols-2 { grid-template-columns:repeat(2, 1fr); }
.ibb-gallery-display--cols-3 { grid-template-columns:repeat(3, 1fr); }
.ibb-gallery-display--cols-4 { grid-template-columns:repeat(4, 1fr); }
.ibb-gallery-display--cols-5 { grid-template-columns:repeat(5, 1fr); }
.ibb-gallery-display--cols-6 { grid-template-columns:repeat(6, 1fr); }
.ibb-gallery-display__item { display:block; aspect-ratio:1; overflow:hidden; border-radius:6px; cursor:zoom-in; }
.ibb-gallery-display__image { width:100%; height:100%; object-fit:cover; display:block; transition:transform .2s; }
.ibb-gallery-display__item:hover .ibb-gallery-display__image { transform:scale(1.03); }
@media (max-width:640px) {
  .ibb-gallery-display--cols-3,
  .ibb-gallery-display--cols-4,
  .ibb-gallery-display--cols-5,
  .ibb-gallery-display--cols-6 { grid-template-columns:repeat(2, 1fr); }
}

.ibb-lightbox { position:fixed; inset:0; background:rgba(0,0,0,.92); z-index:99999; display:none; align-items:center; justify-content:center; opacity:0; transition:opacity .15s ease-out; }
.ibb-lightbox.is-open { display:flex; opacity:1; }
.ibb-lightbox__stage { position:relative; max-width:96vw; max-height:88vh; display:flex; align-items:center; justify-content:center; }
.ibb-lightbox__image { max-width:96vw; max-height:88vh; width:auto; height:auto; object-fit:contain; box-shadow:0 8px 40px rgba(0,0,0,.4); border-radius:4px; user-select:none; -webkit-user-drag:none; }
.ibb-lightbox__image.is-loading { opacity:.3; }
.ibb-lightbox__spinner { position:absolute; top:50%; left:50%; width:42px; height:42px; margin:-21px 0 0 -21px; border:3px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:ibb-spin .9s linear infinite; display:none; }
.ibb-lightbox.is-loading .ibb-lightbox__spinner { display:block; }
@keyframes ibb-spin { to { transform:rotate(360deg); } }
.ibb-lightbox__btn { position:absolute; background:rgba(255,255,255,.12); color:#fff; border:0; width:44px; height:44px; border-radius:50%; font-size:22px; line-height:1; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .15s; backdrop-filter:blur(4px); }
.ibb-lightbox__btn:hover:not([disabled]) { background:rgba(255,255,255,.22); }
.ibb-lightbox__btn[disabled] { opacity:.3; cursor:not-allowed; }
.ibb-lightbox__close { top:20px; right:20px; }
.ibb-lightbox__prev { top:50%; left:20px; transform:translateY(-50%); }
.ibb-lightbox__next { top:50%; right:20px; transform:translateY(-50%); }
.ibb-lightbox__counter { position:absolute; bottom:20px; left:50%; transform:translateX(-50%); color:rgba(255,255,255,.85); font-size:.9em; font-feature-settings:'tnum'; padding:6px 12px; background:rgba(0,0,0,.45); border-radius:14px; backdrop-filter:blur(4px); }
.ibb-lightbox__counter:empty { display:none; }
@media (max-width:640px) {
  .ibb-lightbox__btn { width:38px; height:38px; }
  .ibb-lightbox__close { top:12px; right:12px; }
  .ibb-lightbox__prev { left:8px; }
  .ibb-lightbox__next { right:8px; }
}
body.ibb-lightbox-open { overflow:hidden; }

/* Inline availability calendar ([ibb_calendar])
   Colours cascade through three layers:
   1. var(--e-global-color-*) — Elementor kit (set on :root when Elementor is active)
   2. var(--wp--preset--color--*) — WordPress theme.json palette (block themes / FSE)
   3. Hardcoded fallback — plain WP installs with neither
   Elementor widget controls apply {{WRAPPER}}-scoped CSS (higher specificity)
   and win over every layer here when the user customises a widget. */
.ibb-calendar { display:inline-block; line-height:1; }
/* Flatpickr rewrites type="hidden" to type="text" — keep the anchor input invisible */
.ibb-calendar .flatpickr-input { display:none!important; }
.ibb-calendar__loading { color:var(--e-global-color-text,var(--wp--preset--color--contrast,#64748b)); font-size:.85em; padding:6px 0; }
/* Force the Flatpickr popup to render as a static block inside our container */
.ibb-calendar .flatpickr-calendar { position:relative!important; top:auto!important; left:auto!important; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.10); border:1px solid rgba(0,0,0,.08); }
/* Month header — primary background, white text/arrows */
.ibb-calendar .flatpickr-months { background:var(--e-global-color-primary,var(--wp--preset--color--primary,#1e293b)); border-radius:6px 6px 0 0; }
.ibb-calendar .flatpickr-month,
.ibb-calendar .flatpickr-current-month,
.ibb-calendar .cur-month,
.ibb-calendar .cur-year { color:#fff; }
.ibb-calendar .flatpickr-prev-month svg,
.ibb-calendar .flatpickr-next-month svg { fill:#fff; }
/* Remove the bottom padding Flatpickr adds when used as a picker */
.ibb-calendar .flatpickr-calendar.inline { margin-bottom:0; }
/* Available day text follows theme/kit text colour */
.ibb-calendar .flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay):not(.selected) { color:var(--e-global-color-text,var(--wp--preset--color--contrast,#1e293b)); cursor:default; }
.ibb-calendar .flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay):hover { background:rgba(0,0,0,.04); border-color:transparent; }
/* Unavailable days: strikethrough + muted colour */
.ibb-calendar .flatpickr-day.flatpickr-disabled,
.ibb-calendar .flatpickr-day.flatpickr-disabled:hover { background:rgba(0,0,0,.04); color:rgba(0,0,0,.30); border-color:transparent; text-decoration:line-through; cursor:not-allowed; }
/* Kill the "selected" highlight immediately — onChange clears it but briefly flashes */
.ibb-calendar .flatpickr-day.selected,
.ibb-calendar .flatpickr-day.selected:hover { background:var(--e-global-color-accent,var(--wp--preset--color--primary,#2563eb)); border-color:var(--e-global-color-accent,var(--wp--preset--color--primary,#2563eb)); }
/* Legend */
.ibb-calendar__legend { display:flex; gap:16px; margin-top:8px; font-size:.82em; color:var(--e-global-color-text,var(--wp--preset--color--contrast,#475569)); }
.ibb-calendar__legend-item { display:flex; align-items:center; gap:6px; }
.ibb-calendar__legend-item::before { content:''; display:inline-block; width:14px; height:14px; border-radius:3px; border:1px solid rgba(0,0,0,.12); }
.ibb-calendar__legend-item--available::before { background:#fff; }
.ibb-calendar__legend-item--unavailable::before { background:rgba(0,0,0,.04); }
/* mobile: full width */
@media (max-width:640px) {
  .ibb-calendar { display:block; }
  .ibb-calendar .flatpickr-calendar { width:100%!important; }
}
CSS;
	}

	private function js(): string {
		return <<<'JS'
(function(){
  var forms = document.querySelectorAll('.ibb-booking');
  var i18n = (window.IBBRentals && window.IBBRentals.i18n) || {};
  var symbol = (window.IBBRentals && window.IBBRentals.currency) || '$';

  function fmt(n){ return symbol + Number(n).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }

  function escapeHtml(str){
    return String(str).replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];
    });
  }

  function row(label, value, modifier){
    var cls = 'ibb-booking__quote-row' + (modifier ? ' ibb-booking__quote-row--' + modifier : '');
    return '<div class="' + cls + '"><span>' + escapeHtml(label) + '</span><span>' + value + '</span></div>';
  }

  forms.forEach(function(form){
    var pid = parseInt(form.dataset.propertyId, 10);
    if (!pid) return;

    var dateInput = form.querySelector('.ibb-booking__dates');
    var guestsInput = form.querySelector('.ibb-booking__guests');
    var stepper = form.querySelector('.ibb-booking__stepper');
    var quoteEl = form.querySelector('.ibb-booking__quote');
    var errorEl = form.querySelector('.ibb-booking__error');
    var submit = form.querySelector('.ibb-booking__submit');
    var token = '';

    function setError(msg){ errorEl.textContent = msg || ''; }
    function setLoading(on){ submit.disabled = on; quoteEl.classList.toggle('is-loading', !!on); }

    // Stepper +/- wiring
    if (stepper) {
      var min = parseInt(stepper.dataset.min, 10) || 1;
      var max = parseInt(stepper.dataset.max, 10) || 99;
      var down = stepper.querySelector('.ibb-booking__step--down');
      var up = stepper.querySelector('.ibb-booking__step--up');

      function syncStepperState(){
        var v = parseInt(guestsInput.value, 10) || min;
        down.disabled = v <= min;
        up.disabled = v >= max;
      }

      down.addEventListener('click', function(){
        var v = parseInt(guestsInput.value, 10) || min;
        if (v > min) {
          guestsInput.value = v - 1;
          guestsInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
      up.addEventListener('click', function(){
        var v = parseInt(guestsInput.value, 10) || min;
        if (v < max) {
          guestsInput.value = v + 1;
          guestsInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
      guestsInput.addEventListener('input', function(){
        var v = parseInt(guestsInput.value, 10);
        if (isNaN(v) || v < min) guestsInput.value = min;
        else if (v > max) guestsInput.value = max;
        syncStepperState();
      });
      syncStepperState();
    }

    var today = new Date();
    var horizon = new Date(); horizon.setMonth(horizon.getMonth()+18);

    fetch(window.IBBRentals.restUrl + '/availability?property_id=' + pid + '&from=' + today.toISOString().slice(0,10) + '&to=' + horizon.toISOString().slice(0,10))
      .then(function(r){ return r.json(); })
      .then(function(data){
        var blocked = (data && data.blocked_dates) || [];
        flatpickr(dateInput, {
          mode: 'range',
          dateFormat: 'Y-m-d',
          minDate: 'today',
          disable: blocked,
          onChange: function(selected){
            if (selected.length === 2) requestQuote();
          }
        });
      })
      .catch(function(){ setError(i18n.unavailable); });

    guestsInput.addEventListener('change', function(){
      if (dateInput._flatpickr && dateInput._flatpickr.selectedDates.length === 2) requestQuote();
    });

    function requestQuote(){
      var dates = dateInput._flatpickr.selectedDates;
      if (dates.length !== 2) return;
      var fmtd = function(d){ return d.toISOString().slice(0,10); };
      setError(''); setLoading(true);
      fetch(window.IBBRentals.restUrl + '/quote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.IBBRentals.nonce },
        body: JSON.stringify({
          property_id: pid,
          checkin: fmtd(dates[0]),
          checkout: fmtd(dates[1]),
          guests: parseInt(guestsInput.value, 10) || 1
        })
      })
      .then(function(r){ return r.json().then(function(j){ return { ok: r.ok, data: j }; }); })
      .then(function(res){
        setLoading(false);
        if (!res.ok) {
          setError(res.data && res.data.message ? res.data.message : i18n.unavailable);
          renderQuote(null);
          return;
        }
        token = res.data.token;
        renderQuote(res.data.quote);
      })
      .catch(function(){ setLoading(false); setError(i18n.unavailable); });
    }

    function renderQuote(q){
      if (!q) { quoteEl.innerHTML = ''; submit.disabled = true; return; }

      var nightsLabel = q.nights + ' ' + (q.nights === 1 ? i18n.night : i18n.nights);
      var perNight = q.nightly_subtotal / q.nights;

      // Section 1: nights breakdown
      var nightsSection = '<div class="ibb-booking__quote-section">';
      nightsSection += row(nightsLabel + ' × ' + fmt(perNight) + ' ' + i18n.avgPerNight, fmt(q.nightly_subtotal));
      if (q.los_discount) {
        var pctLabel = i18n.losDiscount + ' (' + q.los_discount.pct + '%)';
        nightsSection += row(pctLabel, '−' + fmt(q.los_discount.amount), 'discount');
      }
      nightsSection += '</div>';

      // Section 2: fees
      var feesHtml = '';
      if (q.cleaning_fee > 0) feesHtml += row(i18n.cleaningFee, fmt(q.cleaning_fee));
      if (q.extra_guest_fee > 0) feesHtml += row(i18n.extraGuestFee, fmt(q.extra_guest_fee));
      var feesSection = feesHtml ? '<div class="ibb-booking__quote-section">' + feesHtml + '</div>' : '';

      // Total
      var totalSection = '<div class="ibb-booking__quote-section">';
      totalSection += '<div class="ibb-booking__quote-row ibb-booking__quote-total"><span>' + escapeHtml(i18n.total) + '</span><span>' + fmt(q.total) + '</span></div>';
      if (q.security_deposit > 0) {
        totalSection += row(i18n.securityDeposit, fmt(q.security_deposit), 'muted');
      }
      totalSection += '</div>';

      // Payment-mode panel (deposit only)
      var paymentSection = '';
      if (q.payment_mode === 'deposit') {
        paymentSection = '<div class="ibb-booking__quote-payment">';
        paymentSection += '<div class="ibb-booking__quote-payment-label">' + escapeHtml(i18n.depositDue) + '</div>';
        paymentSection += row(i18n.depositDue, fmt(q.deposit_due));
        paymentSection += row(i18n.balanceDue + ' ' + i18n.balanceDueOn + ' ' + q.balance_due_date, fmt(q.balance_due), 'muted');
        paymentSection += '</div>';
      }

      quoteEl.innerHTML = nightsSection + feesSection + totalSection + paymentSection;
      submit.disabled = false;
    }

    var submitting = false;
    form.addEventListener('submit', function(e){
      e.preventDefault();
      if (!token || submitting) return;
      submitting = true;
      submit.disabled = true;

      var data = new FormData();
      data.append('product_id', form.dataset.productId);
      data.append('add-to-cart', form.dataset.productId);
      data.append('quantity', '1');
      data.append('ibb_quote_token', token);
      fetch(window.IBBRentals.addToCart, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function(r){ return r.json().catch(function(){ return {}; }); })
        .then(function(){ window.location = window.IBBRentals.cartUrl; })
        .catch(function(){ window.location = window.IBBRentals.cartUrl; });
    });
  });

  // ---------------------------------------------------------------
  // Built-in lightbox for [ibb_gallery] grids.
  // Delegates click on .ibb-gallery-display__item, opens a modal
  // with prev/next navigation across the items in the same grid.
  // Add `class="ibb-no-lightbox"` to the gallery wrapper to opt out
  // (e.g. when a theme already wraps gallery images in its own
  // lightbox plugin).
  // ---------------------------------------------------------------
  var lb = null, lbItems = [], lbIndex = 0;

  function buildLightbox(){
    if (lb) return lb;
    lb = document.createElement('div');
    lb.className = 'ibb-lightbox';
    lb.innerHTML =
      '<button type="button" class="ibb-lightbox__btn ibb-lightbox__close" aria-label="Close">×</button>' +
      '<button type="button" class="ibb-lightbox__btn ibb-lightbox__prev" aria-label="Previous">‹</button>' +
      '<div class="ibb-lightbox__stage">' +
        '<div class="ibb-lightbox__spinner" aria-hidden="true"></div>' +
        '<img class="ibb-lightbox__image" alt="">' +
      '</div>' +
      '<button type="button" class="ibb-lightbox__btn ibb-lightbox__next" aria-label="Next">›</button>' +
      '<div class="ibb-lightbox__counter" aria-live="polite"></div>';
    document.body.appendChild(lb);

    lb.addEventListener('click', function(e){
      if (e.target === lb || e.target.classList.contains('ibb-lightbox__close')) {
        closeLightbox();
      } else if (e.target.classList.contains('ibb-lightbox__prev')) {
        navLightbox(-1);
      } else if (e.target.classList.contains('ibb-lightbox__next')) {
        navLightbox(1);
      }
    });

    document.addEventListener('keydown', function(e){
      if (!lb.classList.contains('is-open')) return;
      if (e.key === 'Escape') closeLightbox();
      else if (e.key === 'ArrowLeft') navLightbox(-1);
      else if (e.key === 'ArrowRight') navLightbox(1);
    });

    // Touch swipe (mobile)
    var touchStartX = 0;
    lb.addEventListener('touchstart', function(e){ touchStartX = e.changedTouches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function(e){
      var dx = e.changedTouches[0].clientX - touchStartX;
      if (Math.abs(dx) > 60) navLightbox(dx < 0 ? 1 : -1);
    });

    return lb;
  }

  function showCurrent(){
    var img = lb.querySelector('.ibb-lightbox__image');
    var counter = lb.querySelector('.ibb-lightbox__counter');
    var prev = lb.querySelector('.ibb-lightbox__prev');
    var next = lb.querySelector('.ibb-lightbox__next');

    var item = lbItems[lbIndex];
    var url = item.getAttribute('href');
    var thumb = item.querySelector('img');

    img.classList.add('is-loading');
    lb.classList.add('is-loading');
    img.alt = thumb ? (thumb.alt || '') : '';
    img.src = url;

    img.onload = function(){
      img.classList.remove('is-loading');
      lb.classList.remove('is-loading');
    };
    img.onerror = function(){
      img.classList.remove('is-loading');
      lb.classList.remove('is-loading');
    };

    if (lbItems.length > 1) {
      counter.textContent = (lbIndex + 1) + ' / ' + lbItems.length;
      prev.style.display = '';
      next.style.display = '';
      prev.disabled = false;
      next.disabled = false;
    } else {
      counter.textContent = '';
      prev.style.display = 'none';
      next.style.display = 'none';
    }
  }

  function navLightbox(delta){
    if (!lbItems.length) return;
    lbIndex = (lbIndex + delta + lbItems.length) % lbItems.length;
    showCurrent();
  }

  function openLightbox(items, startIndex){
    buildLightbox();
    lbItems = items;
    lbIndex = startIndex;
    showCurrent();
    lb.classList.add('is-open');
    document.body.classList.add('ibb-lightbox-open');
  }

  function closeLightbox(){
    if (!lb) return;
    lb.classList.remove('is-open');
    document.body.classList.remove('ibb-lightbox-open');
    var img = lb.querySelector('.ibb-lightbox__image');
    if (img) { img.src = ''; img.alt = ''; }
  }

  document.addEventListener('click', function(e){
    var link = e.target.closest('.ibb-gallery-display__item');
    if (!link) return;
    var grid = link.closest('.ibb-gallery-display');
    if (!grid || grid.classList.contains('ibb-no-lightbox')) return;
    if (!link.getAttribute('href') || link.getAttribute('href') === '#') return;
    e.preventDefault();
    var items = Array.prototype.slice.call(grid.querySelectorAll('.ibb-gallery-display__item'));
    var index = items.indexOf(link);
    openLightbox(items, index >= 0 ? index : 0);
  });

  // ---------------------------------------------------------------
  // Inline availability calendars ([ibb_calendar] shortcode).
  // Flatpickr in inline/display-only mode — no booking, just shows
  // which dates are blocked. The container div holds data-property-id
  // and data-months; we replace the loading text with the calendar.
  // ---------------------------------------------------------------
  var calEls = document.querySelectorAll('.ibb-calendar[data-property-id]');
  calEls.forEach(function(container){
    var pid    = parseInt(container.dataset.propertyId, 10);
    var months = Math.max(1, Math.min(3, parseInt(container.dataset.months, 10) || 2));
    if (!pid) return;

    // Fetch 18 months of availability (enough for showMonths up to 3).
    var today   = new Date();
    var horizon = new Date(); horizon.setMonth(horizon.getMonth() + 18);
    var from    = today.toISOString().slice(0, 10);
    var to      = horizon.toISOString().slice(0, 10);

    fetch(window.IBBRentals.restUrl + '/availability?property_id=' + pid + '&from=' + from + '&to=' + to)
      .then(function(r){ return r.json(); })
      .then(function(data){
        var blocked = (data && data.blocked_dates) || [];

        // Clear loading indicator and place a hidden input that Flatpickr
        // attaches to. appendTo: container keeps the calendar DOM inside
        // our wrapper div instead of being teleported to <body>.
        container.innerHTML = '';
        var input = document.createElement('input');
        input.type = 'hidden';
        container.appendChild(input);

        flatpickr(input, {
          inline:     true,
          appendTo:   container,
          showMonths: months,
          minDate:    'today',
          disable:    blocked,
          // Display-only: immediately clear any accidental selection.
          onChange: function(dates, dateStr, fp){
            if (dates.length) { fp.clear(); }
          }
        });
      })
      .catch(function(){
        container.innerHTML = '<p style="color:#94a3b8;font-size:.85em">' + (i18n.unavailable || 'Could not load availability.') + '</p>';
      });
  });
})();
JS;
	}
}
