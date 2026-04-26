<?php
/**
 * Result of a pricing calculation — a self-contained snapshot of every line item.
 *
 * Quotes are signed with an HMAC of their JSON form and a 15-minute TTL so
 * the cart can verify the price the user agreed to hasn't been tampered with
 * client-side. The signed token is the only payload that travels to the cart;
 * the cart re-inflates the full Quote from the token at add-to-cart time.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Domain;

defined( 'ABSPATH' ) || exit;

final class Quote {

	/**
	 * @param list<array{date:string,base_rate:float,weekend:bool,applied_rate:float}> $nights
	 * @param array{min_nights:int,pct:float,amount:float}|null                        $los_discount
	 */
	public function __construct(
		public readonly int $property_id,
		public readonly DateRange $range,
		public readonly int $guests,
		public readonly array $nights,
		public readonly float $nightly_subtotal,
		public readonly ?array $los_discount,
		public readonly float $extra_guest_fee,
		public readonly float $cleaning_fee,
		public readonly float $security_deposit,
		public readonly float $total,
		public readonly string $payment_mode,
		public readonly float $deposit_due,
		public readonly float $balance_due,
		public readonly ?string $balance_due_date,
		public readonly string $currency,
		public readonly int $issued_at,
	) {}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return [
			'property_id'      => $this->property_id,
			'checkin'          => $this->range->checkin_string(),
			'checkout'         => $this->range->checkout_string(),
			'nights'           => $this->range->nights(),
			'guests'           => $this->guests,
			'nightly_breakdown'=> $this->nights,
			'nightly_subtotal' => $this->round( $this->nightly_subtotal ),
			'los_discount'     => $this->los_discount,
			'extra_guest_fee'  => $this->round( $this->extra_guest_fee ),
			'cleaning_fee'     => $this->round( $this->cleaning_fee ),
			'security_deposit' => $this->round( $this->security_deposit ),
			'total'            => $this->round( $this->total ),
			'payment_mode'     => $this->payment_mode,
			'deposit_due'      => $this->round( $this->deposit_due ),
			'balance_due'      => $this->round( $this->balance_due ),
			'balance_due_date' => $this->balance_due_date,
			'currency'         => $this->currency,
			'issued_at'        => $this->issued_at,
		];
	}

	/**
	 * Sign the quote with HMAC and return a Base64URL-encoded `payload.signature` token.
	 * The TTL is enforced on verification (caller compares `issued_at` against now).
	 */
	public function sign( string $secret ): string {
		$payload   = wp_json_encode( $this->to_array(), JSON_UNESCAPED_SLASHES );
		$encoded   = self::b64url_encode( $payload );
		$signature = hash_hmac( 'sha256', $encoded, $secret );
		return $encoded . '.' . $signature;
	}

	/**
	 * Verify a token signature and return its decoded payload, or null if invalid/expired.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function verify_token( string $token, string $secret, int $ttl_seconds = 900 ): ?array {
		$parts = explode( '.', $token, 2 );
		if ( count( $parts ) !== 2 ) {
			return null;
		}
		[ $encoded, $signature ] = $parts;

		$expected = hash_hmac( 'sha256', $encoded, $secret );
		if ( ! hash_equals( $expected, $signature ) ) {
			return null;
		}

		$json = self::b64url_decode( $encoded );
		if ( $json === false ) {
			return null;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || ! isset( $data['issued_at'] ) ) {
			return null;
		}

		if ( ( time() - (int) $data['issued_at'] ) > $ttl_seconds ) {
			return null;
		}

		return $data;
	}

	private function round( float $value ): float {
		return round( $value, 2 );
	}

	private static function b64url_encode( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	private static function b64url_decode( string $encoded ): string|false {
		$padded = strtr( $encoded, '-_', '+/' );
		$padded .= str_repeat( '=', ( 4 - strlen( $padded ) % 4 ) % 4 );
		return base64_decode( $padded, true );
	}
}
