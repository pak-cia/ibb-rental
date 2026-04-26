<?php
/**
 * POST /quote — validates a date range against booking rules, returns a fully
 * priced quote plus an HMAC-signed token the cart can verify.
 *
 * Rate-limited per IP via a transient counter (30 requests / minute).
 */

declare( strict_types=1 );

namespace IBB\Rentals\Rest\Controllers;

use IBB\Rentals\Domain\DateRange;
use IBB\Rentals\Domain\Property;
use IBB\Rentals\Services\AvailabilityService;
use IBB\Rentals\Services\PricingService;

defined( 'ABSPATH' ) || exit;

final class QuoteController {

	public function __construct(
		private AvailabilityService $availability,
		private PricingService $pricing,
	) {}

	public function register( string $namespace ): void {
		register_rest_route( $namespace, '/quote', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => [ $this, 'rate_limit' ],
			'args'                => [
				'property_id' => [ 'required' => true, 'type' => 'integer' ],
				'checkin'     => [ 'required' => true, 'type' => 'string' ],
				'checkout'    => [ 'required' => true, 'type' => 'string' ],
				'guests'      => [ 'required' => true, 'type' => 'integer', 'minimum' => 1 ],
			],
			'callback' => [ $this, 'handle' ],
		] );
	}

	public function rate_limit(): bool|\WP_Error {
		$ip  = $this->client_ip();
		$key = 'ibb_quote_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 30 ) {
			return new \WP_Error( 'rate_limited', __( 'Too many requests. Please slow down.', 'ibb-rentals' ), [ 'status' => 429 ] );
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
		return true;
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$property_id = (int) $request->get_param( 'property_id' );
		$guests      = (int) $request->get_param( 'guests' );

		$property = Property::from_id( $property_id );
		if ( ! $property ) {
			return new \WP_Error( 'not_found', __( 'Property not found.', 'ibb-rentals' ), [ 'status' => 404 ] );
		}

		try {
			$range = DateRange::from_strings(
				(string) $request->get_param( 'checkin' ),
				(string) $request->get_param( 'checkout' )
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'bad_dates', $e->getMessage(), [ 'status' => 400 ] );
		}

		$rules = $this->availability->validate_booking_rules( $property, $range, $guests );
		if ( $rules instanceof \WP_Error ) {
			return new \WP_Error(
				$rules->get_error_code(),
				$rules->get_error_message(),
				[ 'status' => 422 ]
			);
		}

		$quote  = $this->pricing->get_quote( $property, $range, $guests );
		$secret = (string) get_option( 'ibb_rentals_token_secret', '' );

		return new \WP_REST_Response( [
			'quote' => $quote->to_array(),
			'token' => $quote->sign( $secret ),
		] );
	}

	private function client_ip(): string {
		$candidates = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
		foreach ( $candidates as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = explode( ',', (string) $_SERVER[ $key ] )[0];
				return trim( $ip );
			}
		}
		return '0.0.0.0';
	}
}
