<?php
/**
 * REST controller: /ibb-rentals/v1/bookings
 *
 * Admin-only read endpoints. Guest ownership access (via nonce) is deferred
 * to v1.1 — for now these require manage_woocommerce so external tools and
 * webhooks can query booking state.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Rest\Controllers;

use IBB\Rentals\Repositories\BookingRepository;

defined( 'ABSPATH' ) || exit;

final class BookingsController {

	public function __construct(
		private BookingRepository $bookings,
	) {}

	public function register( string $namespace ): void {
		register_rest_route( $namespace, '/bookings', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_bookings' ],
				'permission_callback' => [ $this, 'admin_permission' ],
				'args'                => [
					'property_id' => [ 'type' => 'integer', 'default' => 0 ],
					'status'      => [ 'type' => 'string',  'default' => '' ],
					'from'        => [ 'type' => 'string',  'default' => '' ],
					'to'          => [ 'type' => 'string',  'default' => '' ],
					'per_page'    => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
					'page'        => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
				],
			],
		] );

		register_rest_route( $namespace, '/bookings/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_booking' ],
				'permission_callback' => [ $this, 'admin_permission' ],
				'args'                => [
					'id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
				],
			],
		] );
	}

	public function admin_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public function list_bookings( \WP_REST_Request $request ): \WP_REST_Response {
		$property_id = (int) $request->get_param( 'property_id' );
		$status      = (string) $request->get_param( 'status' );
		$from        = (string) $request->get_param( 'from' );
		$to          = (string) $request->get_param( 'to' );
		$per_page    = (int) $request->get_param( 'per_page' );
		$page        = (int) $request->get_param( 'page' );

		$all    = $this->bookings->find_filtered( $property_id ?: null, $status ?: null, $from ?: null, $to ?: null );
		$total  = count( $all );
		$offset = ( $page - 1 ) * $per_page;
		$items  = array_slice( $all, $offset, $per_page );

		$response = new \WP_REST_Response( array_map( [ $this, 'booking_to_array' ], $items ), 200 );
		$response->header( 'X-WP-Total',      (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $per_page ) );
		return $response;
	}

	public function get_booking( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$row = $this->bookings->find_by_id( (int) $request->get_param( 'id' ) );
		if ( ! $row ) {
			return new \WP_Error( 'ibb_not_found', __( 'Booking not found.', 'ibb-rentals' ), [ 'status' => 404 ] );
		}
		return new \WP_REST_Response( $this->booking_to_array( $row ), 200 );
	}

	/** @param array<string, mixed> $row @return array<string, mixed> */
	private function booking_to_array( array $row ): array {
		return [
			'id'               => (int) $row['id'],
			'property_id'      => (int) $row['property_id'],
			'property_title'   => get_the_title( (int) $row['property_id'] ),
			'order_id'         => (int) $row['order_id'],
			'checkin'          => $row['checkin'],
			'checkout'         => $row['checkout'],
			'guests'           => (int) $row['guests'],
			'guest_name'       => $row['guest_name'],
			'guest_email'      => $row['guest_email'],
			'total'            => (float) $row['total'],
			'deposit_paid'     => (float) $row['deposit_paid'],
			'balance_due'      => (float) $row['balance_due'],
			'balance_due_date' => $row['balance_due_date'],
			'payment_method'   => $row['payment_method'],
			'status'           => $row['status'],
			'created_at'       => $row['created_at'],
		];
	}
}
