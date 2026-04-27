<?php
/**
 * REST controller: /ibb-rentals/v1/properties
 *
 * Public read endpoints (list + single). Write operations are intentionally
 * left out of v1 — properties are managed through the WP admin UI.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Rest\Controllers;

use IBB\Rentals\Domain\Property;
use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class PropertiesController {

	public function register( string $namespace ): void {
		register_rest_route( $namespace, '/properties', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_properties' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
					'page'     => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
					'search'   => [ 'type' => 'string',  'default' => '' ],
				],
			],
		] );

		register_rest_route( $namespace, '/properties/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_property' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
				],
			],
		] );
	}

	public function list_properties( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$search   = (string) $request->get_param( 'search' );

		$query_args = [
			'post_type'      => PropertyPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];
		if ( $search !== '' ) {
			$query_args['s'] = $search;
		}

		$query = new \WP_Query( $query_args );
		$items = [];
		foreach ( $query->posts as $post ) {
			$property = Property::from_id( (int) $post->ID );
			if ( $property ) {
				$items[] = $this->property_to_array( $property, false );
			}
		}

		$response = new \WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total',      (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );
		return $response;
	}

	public function get_property( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id       = (int) $request->get_param( 'id' );
		$property = Property::from_id( $id );

		if ( ! $property ) {
			return new \WP_Error( 'ibb_not_found', __( 'Property not found.', 'ibb-rentals' ), [ 'status' => 404 ] );
		}
		if ( get_post_status( $id ) !== 'publish' ) {
			return new \WP_Error( 'ibb_not_found', __( 'Property not found.', 'ibb-rentals' ), [ 'status' => 404 ] );
		}

		return new \WP_REST_Response( $this->property_to_array( $property, true ), 200 );
	}

	/** @return array<string, mixed> */
	private function property_to_array( Property $p, bool $full ): array {
		$data = [
			'id'              => $p->id,
			'title'           => $p->title(),
			'slug'            => get_post_field( 'post_name', $p->id ),
			'url'             => get_permalink( $p->id ),
			'thumbnail'       => get_the_post_thumbnail_url( $p->id, 'medium' ) ?: null,
			'max_guests'      => $p->max_guests(),
			'bedrooms'        => $p->bedrooms(),
			'bathrooms'       => $p->bathrooms(),
			'beds'            => $p->beds(),
			'base_rate'       => $p->base_rate(),
			'currency'        => get_woocommerce_currency(),
			'check_in_time'   => $p->check_in_time(),
			'check_out_time'  => $p->check_out_time(),
			'min_nights'      => $p->min_nights(),
		];

		if ( $full ) {
			$data['short_description'] = $p->short_description();
			$data['max_nights']        = $p->max_nights();
			$data['weekend_uplift_pct']= $p->weekend_uplift_pct();
			$data['cleaning_fee']      = $p->cleaning_fee();
			$data['extra_guest_fee']   = $p->extra_guest_fee();
			$data['security_deposit']  = $p->security_deposit();
			$data['payment_mode']      = $p->payment_mode();
			$data['los_discounts']     = $p->los_discounts();
		}

		return $data;
	}
}
