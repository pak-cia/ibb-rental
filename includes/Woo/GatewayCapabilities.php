<?php
/**
 * Inspects WC's active payment gateways to decide how a deposit's balance
 * can be collected.
 *
 * Two outcomes:
 *   - `auto-charge`: gateway implements `WC_Payment_Tokens` with off-session
 *     reuse (Stripe, Braintree, Authorize.net CIM). The plugin schedules a
 *     ChargeBalanceJob that runs `process_payment()` against the saved token.
 *   - `payment-link`: anything else (Xendit VAs/e-wallets/QRIS, bank transfer,
 *     COD, etc.). The plugin schedules a SendPaymentLinkJob that emails the
 *     guest the WC pay-for-order URL X days before check-in.
 *
 * The gateway used at deposit time is recorded on the booking, so a property
 * can mix payment methods across bookings and we still pick the right path.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Woo;

defined( 'ABSPATH' ) || exit;

final class GatewayCapabilities {

	public const PATH_AUTO_CHARGE  = 'auto_charge';
	public const PATH_PAYMENT_LINK = 'payment_link';

	/**
	 * Hand-curated list of gateway IDs known to support off-session token reuse.
	 * Filterable so site owners or extension authors can extend it.
	 *
	 * @return list<string>
	 */
	public function token_capable_gateway_ids(): array {
		$ids = [
			'stripe',
			'stripe_cc',
			'woocommerce_payments',
			'authorize_net_cim_credit_card',
			'braintree_credit_card',
			'square_credit_card',
			'ppcp-gateway',
		];
		/** @var list<string> $filtered */
		$filtered = (array) apply_filters( 'ibb-rentals/gateways/token_capable', $ids );
		return $filtered;
	}

	public function path_for_gateway( string $gateway_id ): string {
		if ( $gateway_id === '' ) {
			return self::PATH_PAYMENT_LINK;
		}
		if ( in_array( $gateway_id, $this->token_capable_gateway_ids(), true ) ) {
			return self::PATH_AUTO_CHARGE;
		}
		return self::PATH_PAYMENT_LINK;
	}

	/**
	 * Returns a human-readable description of which path each currently-active
	 * gateway will use, for the property-edit screen capability matrix.
	 *
	 * @return list<array{id:string,title:string,path:string}>
	 */
	public function active_gateway_summary(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways ) {
			return [];
		}
		$summary = [];
		foreach ( WC()->payment_gateways->get_available_payment_gateways() as $gw ) {
			$summary[] = [
				'id'    => (string) $gw->id,
				'title' => (string) $gw->get_title(),
				'path'  => $this->path_for_gateway( (string) $gw->id ),
			];
		}
		return $summary;
	}

	/**
	 * Find the customer's most recent saved token for a given gateway, suitable
	 * for off-session reuse.
	 */
	public function find_reusable_token( int $customer_id, string $gateway_id ): ?\WC_Payment_Token {
		if ( $customer_id <= 0 || $gateway_id === '' ) {
			return null;
		}
		$tokens = \WC_Payment_Tokens::get_customer_tokens( $customer_id, $gateway_id );
		if ( empty( $tokens ) ) {
			return null;
		}
		// Prefer the default token; otherwise the most recently saved.
		foreach ( $tokens as $token ) {
			if ( $token->is_default() ) {
				return $token;
			}
		}
		return array_values( $tokens )[0] ?? null;
	}
}
