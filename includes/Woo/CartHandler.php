<?php
/**
 * Bridges the front-end booking widget to WooCommerce's cart.
 *
 * The flow:
 *   1. Front-end gets a signed quote token from /quote (PricingService produced it).
 *   2. Add-to-cart carries the token in cart-item-data under the `ibb` key.
 *   3. We verify the token, inflate the full quote payload, and from that point
 *      WC treats it as any other line item — but with a custom price set per
 *      cart-line clone (NOT the master product) and dates rendered in the cart.
 *   4. Availability is re-validated at `woocommerce_check_cart_items`, because
 *      an OTA import or another booking may have claimed the dates between
 *      the quote and the checkout.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Woo;

use IBB\Rentals\Domain\DateRange;
use IBB\Rentals\Domain\Quote;
use IBB\Rentals\Repositories\AvailabilityRepository;

defined( 'ABSPATH' ) || exit;

final class CartHandler {

	public const TOKEN_FIELD = 'ibb_quote_token';
	public const ITEM_KEY    = 'ibb';

	public function __construct(
		private AvailabilityRepository $blocks,
	) {}

	public function register(): void {
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'attach_quote' ], 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'rehydrate' ], 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_prices' ], 20 );

		// Cart-page rendering split:
		//
		//   - Classic cart (regular page request): emit our own structured
		//     HTML below the product name via woocommerce_after_cart_item_name.
		//     This bypasses WC's `dl.variation` rendering completely, which
		//     themes (esp. block themes) tend to flatten into inline-flow.
		//   - Block cart (Store API REST request): keep populating
		//     woocommerce_get_item_data, since the block cart React tree
		//     consumes that array via REST and renders one <li> per entry —
		//     which already gives the per-line layout we want.
		add_action( 'woocommerce_after_cart_item_name', [ $this, 'render_after_cart_item_name' ], 10, 2 );
		add_filter( 'woocommerce_get_item_data', [ $this, 'render_item_meta' ], 10, 2 );

		add_filter( 'woocommerce_cart_item_quantity', [ $this, 'lock_quantity' ], 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 10, 3 );
		add_action( 'woocommerce_check_cart_items', [ $this, 'revalidate_cart' ] );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'persist_line_item_meta' ], 10, 4 );

		// Force booking line quantity to 1 even if WC tries to merge duplicates.
		add_filter( 'woocommerce_add_to_cart_quantity', [ $this, 'clamp_quantity' ], 10, 2 );
		add_action( 'woocommerce_add_to_cart', [ $this, 'reset_merged_quantity' ], 20, 6 );
	}

	/**
	 * @param array<string, mixed> $cart_item_data
	 * @return array<string, mixed>
	 */
	public function attach_quote( array $cart_item_data, int $product_id, int $variation_id ): array {
		$token = isset( $_REQUEST[ self::TOKEN_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST[ self::TOKEN_FIELD ] ) ) : '';
		if ( $token === '' && isset( $cart_item_data[ self::ITEM_KEY ]['token'] ) ) {
			$token = (string) $cart_item_data[ self::ITEM_KEY ]['token'];
		}
		if ( $token === '' ) {
			return $cart_item_data;
		}

		$secret  = (string) get_option( 'ibb_rentals_token_secret', '' );
		$payload = Quote::verify_token( $token, $secret );
		if ( ! is_array( $payload ) ) {
			return $cart_item_data;
		}

		// Use the signed token's hash as the dedup key so:
		//  - rapid double-clicks of the same quote merge into one cart line
		//    (instead of producing duplicates), but
		//  - distinct bookings (different dates / properties) remain separate.
		$cart_item_data[ self::ITEM_KEY ] = [
			'token'  => $token,
			'quote'  => $payload,
			'unique' => substr( hash( 'sha256', $token ), 0, 32 ),
		];
		return $cart_item_data;
	}

	/**
	 * @param array<string, mixed> $cart_item
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	public function rehydrate( array $cart_item, array $values ): array {
		if ( ! empty( $values[ self::ITEM_KEY ] ) ) {
			$cart_item[ self::ITEM_KEY ] = $values[ self::ITEM_KEY ];
		}
		return $cart_item;
	}

	public function apply_prices( \WC_Cart $cart ): void {
		if ( did_action( 'woocommerce_before_calculate_totals' ) > 1 ) {
			// Avoid recursion when other plugins re-trigger calculation.
			return;
		}
		foreach ( $cart->get_cart() as $cart_item ) {
			$quote = $cart_item[ self::ITEM_KEY ]['quote'] ?? null;
			if ( ! is_array( $quote ) || empty( $quote['total'] ) ) {
				continue;
			}
			// Deposit mode: charge only the deposit through the cart;
			// the balance is collected later via BalanceService.
			$mode  = (string) ( $quote['payment_mode'] ?? 'full' );
			$price = $mode === 'deposit'
				? (float) ( $quote['deposit_due'] ?? $quote['total'] )
				: (float) $quote['total'];
			$cart_item['data']->set_price( $price );
		}
	}

	/**
	 * @param array<int, array{key:string,value:string}> $item_data
	 * @param array<string, mixed>                       $cart_item
	 * @return array<int, array{key:string,value:string}>
	 */
	/**
	 * Block-cart only: emit booking meta into the Store API cart payload so
	 * the React-rendered Cart block displays it. Classic cart skips this
	 * entirely — see render_after_cart_item_name() below.
	 *
	 * @param array<int, array<string, mixed>> $item_data
	 * @param array<string, mixed>             $cart_item
	 * @return array<int, array<string, mixed>>
	 */
	public function render_item_meta( array $item_data, array $cart_item ): array {
		// Bail out for non-REST contexts (classic cart, mini cart). The block
		// cart fetches via /wc/store/v1/cart which is always a REST request.
		if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $item_data;
		}

		$quote = $cart_item[ self::ITEM_KEY ]['quote'] ?? null;
		if ( ! is_array( $quote ) ) {
			return $item_data;
		}

		$item_data[] = [ 'key' => __( 'Check-in', 'ibb-rentals' ),  'value' => (string) $quote['checkin'] ];
		$item_data[] = [ 'key' => __( 'Check-out', 'ibb-rentals' ), 'value' => (string) $quote['checkout'] ];
		$item_data[] = [ 'key' => __( 'Nights', 'ibb-rentals' ),    'value' => (string) (int) $quote['nights'] ];
		$item_data[] = [ 'key' => __( 'Guests', 'ibb-rentals' ),    'value' => (string) (int) $quote['guests'] ];

		if ( ( $quote['payment_mode'] ?? 'full' ) === 'deposit' ) {
			$item_data[] = [ 'key' => __( 'Stay total', 'ibb-rentals' ),            'display' => wc_price( (float) $quote['total'] ) ];
			$item_data[] = [ 'key' => __( 'Deposit charged today', 'ibb-rentals' ), 'display' => wc_price( (float) $quote['deposit_due'] ) ];
			$item_data[] = [
				'key'     => __( 'Balance due', 'ibb-rentals' ),
				'display' => wc_price( (float) $quote['balance_due'] ) . ' (' . esc_html__( 'on', 'ibb-rentals' ) . ' ' . esc_html( (string) $quote['balance_due_date'] ) . ')',
			];
		}

		if ( ! empty( $quote['security_deposit'] ) && (float) $quote['security_deposit'] > 0 ) {
			$item_data[] = [
				'key'     => __( 'Security deposit', 'ibb-rentals' ),
				'display' => wc_price( (float) $quote['security_deposit'] ) . ' <small>(' . esc_html__( 'refundable, not charged today', 'ibb-rentals' ) . ')</small>',
			];
		}

		return $item_data;
	}

	/**
	 * Classic cart: render booking meta as our own structured HTML directly
	 * below the product name. Each row is a plain <div> so themes can't
	 * collapse them inline (the way they do with WC's <dl class="variation">).
	 *
	 * @param array<string, mixed> $cart_item
	 */
	public function render_after_cart_item_name( array $cart_item, string $cart_item_key ): void {
		$quote = $cart_item[ self::ITEM_KEY ]['quote'] ?? null;
		if ( ! is_array( $quote ) ) {
			return;
		}

		echo '<div class="ibb-booking-meta">';

		$this->render_meta_row( __( 'Check-in', 'ibb-rentals' ),  esc_html( (string) $quote['checkin'] ) );
		$this->render_meta_row( __( 'Check-out', 'ibb-rentals' ), esc_html( (string) $quote['checkout'] ) );
		$this->render_meta_row( __( 'Nights', 'ibb-rentals' ),    (string) (int) $quote['nights'] );
		$this->render_meta_row( __( 'Guests', 'ibb-rentals' ),    (string) (int) $quote['guests'] );

		if ( ( $quote['payment_mode'] ?? 'full' ) === 'deposit' ) {
			echo '<div class="ibb-booking-meta__panel">';
			$this->render_meta_row( __( 'Stay total', 'ibb-rentals' ),            wc_price( (float) $quote['total'] ) );
			$this->render_meta_row( __( 'Deposit charged today', 'ibb-rentals' ), wc_price( (float) $quote['deposit_due'] ) );
			$this->render_meta_row(
				__( 'Balance due', 'ibb-rentals' ),
				wc_price( (float) $quote['balance_due'] ) . ' <small>(' . esc_html__( 'on', 'ibb-rentals' ) . ' ' . esc_html( (string) $quote['balance_due_date'] ) . ')</small>'
			);
			echo '</div>';
		}

		if ( ! empty( $quote['security_deposit'] ) && (float) $quote['security_deposit'] > 0 ) {
			$this->render_meta_row(
				__( 'Security deposit', 'ibb-rentals' ),
				wc_price( (float) $quote['security_deposit'] ) . ' <small>(' . esc_html__( 'refundable, not charged today', 'ibb-rentals' ) . ')</small>',
				'muted'
			);
		}

		echo '</div>';
	}

	private function render_meta_row( string $label, string $value_html, string $modifier = '' ): void {
		$row_class = 'ibb-booking-meta__row' . ( $modifier !== '' ? ' ibb-booking-meta__row--' . $modifier : '' );
		printf(
			'<div class="%s"><span class="ibb-booking-meta__label">%s:</span> <span class="ibb-booking-meta__value">%s</span></div>',
			esc_attr( $row_class ),
			esc_html( $label ),
			$value_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller-controlled HTML (wc_price, escaped strings)
		);
	}

	public function lock_quantity( string $product_quantity, string $cart_item_key, array $cart_item ): string {
		if ( empty( $cart_item[ self::ITEM_KEY ] ) ) {
			return $product_quantity;
		}
		return '<span class="ibb-qty">1</span>';
	}

	/**
	 * Cap the requested add-to-cart quantity at 1 for booking products,
	 * regardless of what the form posted.
	 */
	public function clamp_quantity( int $quantity, int $product_id ): int {
		$product = wc_get_product( $product_id );
		if ( $product && $product->get_type() === 'ibb_booking' ) {
			return 1;
		}
		return $quantity;
	}

	/**
	 * After WC adds the cart item, reset its quantity to 1 if a re-add
	 * caused a merge to push it above 1. This is the silent dedup path:
	 * a duplicate click resolves to "already in cart, no-op" rather than
	 * "cannot add another" or "now you have 2 of these."
	 */
	public function reset_merged_quantity( string $cart_item_key, int $product_id, int $quantity, int $variation_id, array $variation, array $cart_item_data ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$item = WC()->cart->get_cart_item( $cart_item_key );
		if ( ! $item || empty( $item[ self::ITEM_KEY ] ) ) {
			return;
		}
		if ( (int) ( $item['quantity'] ?? 1 ) > 1 ) {
			WC()->cart->set_quantity( $cart_item_key, 1, false );
		}
	}

	public function validate_add_to_cart( bool $passed, int $product_id, int $quantity ): bool {
		$token = isset( $_REQUEST[ self::TOKEN_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST[ self::TOKEN_FIELD ] ) ) : '';
		if ( $token === '' ) {
			return $passed;
		}
		$secret  = (string) get_option( 'ibb_rentals_token_secret', '' );
		$payload = Quote::verify_token( $token, $secret );
		if ( ! is_array( $payload ) ) {
			wc_add_notice( __( 'Your booking quote has expired. Please re-select your dates.', 'ibb-rentals' ), 'error' );
			return false;
		}
		try {
			$range = DateRange::from_strings( (string) $payload['checkin'], (string) $payload['checkout'] );
		} catch ( \Throwable ) {
			wc_add_notice( __( 'Invalid booking dates.', 'ibb-rentals' ), 'error' );
			return false;
		}
		if ( $this->blocks->any_overlap( (int) $payload['property_id'], $range ) ) {
			wc_add_notice( __( 'Sorry — those dates were just booked. Please choose another range.', 'ibb-rentals' ), 'error' );
			return false;
		}
		return $passed;
	}

	public function revalidate_cart(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$quote = $cart_item[ self::ITEM_KEY ]['quote'] ?? null;
			if ( ! is_array( $quote ) ) {
				continue;
			}
			try {
				$range = DateRange::from_strings( (string) $quote['checkin'], (string) $quote['checkout'] );
			} catch ( \Throwable ) {
				wc_add_notice( __( 'A cart item has invalid dates.', 'ibb-rentals' ), 'error' );
				continue;
			}
			if ( $this->blocks->any_overlap( (int) $quote['property_id'], $range ) ) {
				wc_add_notice(
					sprintf(
						/* translators: 1: checkin, 2: checkout */
						__( 'The dates %1$s → %2$s are no longer available. Please remove this booking and re-select dates.', 'ibb-rentals' ),
						esc_html( (string) $quote['checkin'] ),
						esc_html( (string) $quote['checkout'] )
					),
					'error'
				);
			}
		}
	}

	public function persist_line_item_meta( \WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order ): void {
		$quote = $values[ self::ITEM_KEY ]['quote'] ?? null;
		if ( ! is_array( $quote ) ) {
			return;
		}
		$item->add_meta_data( '_ibb_property_id', (int) $quote['property_id'], true );
		$item->add_meta_data( '_ibb_checkin', (string) $quote['checkin'], true );
		$item->add_meta_data( '_ibb_checkout', (string) $quote['checkout'], true );
		$item->add_meta_data( '_ibb_guests', (int) $quote['guests'], true );
		$item->add_meta_data( '_ibb_payment_mode', (string) $quote['payment_mode'], true );
		$item->add_meta_data( '_ibb_total', (float) $quote['total'], true );
		$item->add_meta_data( '_ibb_deposit_due', (float) $quote['deposit_due'], true );
		$item->add_meta_data( '_ibb_balance_due', (float) $quote['balance_due'], true );
		$item->add_meta_data( '_ibb_balance_due_date', (string) ( $quote['balance_due_date'] ?? '' ), true );
		$item->add_meta_data( '_ibb_quote_snapshot', wp_json_encode( $quote ), true );

		// Human-readable display fields (visible on order edit + emails).
		$item->add_meta_data( __( 'Check-in', 'ibb-rentals' ), (string) $quote['checkin'] );
		$item->add_meta_data( __( 'Check-out', 'ibb-rentals' ), (string) $quote['checkout'] );
		$item->add_meta_data( __( 'Guests', 'ibb-rentals' ), (string) (int) $quote['guests'] );
	}
}
