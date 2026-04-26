<?php
/**
 * GET /availability — returns blocked date strings for a date-picker widget.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Rest\Controllers;

use IBB\Rentals\Domain\DateRange;
use IBB\Rentals\Services\AvailabilityService;

defined( 'ABSPATH' ) || exit;

final class AvailabilityController {

	public function __construct(
		private AvailabilityService $availability,
	) {}

	public function register( string $namespace ): void {
		register_rest_route( $namespace, '/availability', [
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'args'                => [
				'property_id' => [ 'required' => true, 'type' => 'integer' ],
				'from'        => [ 'required' => true, 'type' => 'string' ],
				'to'          => [ 'required' => true, 'type' => 'string' ],
			],
			'callback' => [ $this, 'handle' ],
		] );
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$property_id = (int) $request->get_param( 'property_id' );
		try {
			$window = DateRange::from_strings(
				(string) $request->get_param( 'from' ),
				(string) $request->get_param( 'to' )
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'bad_dates', $e->getMessage(), [ 'status' => 400 ] );
		}

		return new \WP_REST_Response( [
			'property_id'    => $property_id,
			'window'         => [ 'from' => $window->checkin_string(), 'to' => $window->checkout_string() ],
			'blocked_dates'  => $this->availability->get_blocked_dates( $property_id, $window ),
		] );
	}
}
