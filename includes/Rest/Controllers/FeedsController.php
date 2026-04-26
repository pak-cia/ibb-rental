<?php
/**
 * Feed-registry CRUD plus an admin-only "sync now" trigger.
 *
 * All endpoints require `manage_woocommerce` — the registry is admin-only;
 * the public-facing endpoint is `/ical/{id}.ics`.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Rest\Controllers;

use IBB\Rentals\Ical\Importer;
use IBB\Rentals\Repositories\FeedRepository;

defined( 'ABSPATH' ) || exit;

final class FeedsController {

	public function __construct(
		private FeedRepository $feeds,
		private Importer $importer,
	) {}

	public function register( string $namespace ): void {
		register_rest_route( $namespace, '/feeds', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'authorize' ],
				'callback'            => [ $this, 'list_feeds' ],
				'args'                => [
					'property_id' => [ 'type' => 'integer' ],
				],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => [ $this, 'authorize' ],
				'callback'            => [ $this, 'create_feed' ],
				'args'                => [
					'property_id'   => [ 'required' => true, 'type' => 'integer' ],
					'url'           => [ 'required' => true, 'type' => 'string', 'format' => 'uri' ],
					'label'         => [ 'required' => true, 'type' => 'string' ],
					'source'        => [ 'required' => true, 'type' => 'string' ],
					'sync_interval' => [ 'type' => 'integer' ],
				],
			],
		] );

		register_rest_route( $namespace, '/feeds/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'permission_callback' => [ $this, 'authorize' ],
				'callback'            => [ $this, 'delete_feed' ],
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'permission_callback' => [ $this, 'authorize' ],
				'callback'            => [ $this, 'update_feed' ],
			],
		] );

		register_rest_route( $namespace, '/feeds/(?P<id>\d+)/sync', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => [ $this, 'authorize' ],
			'callback'            => [ $this, 'sync_now' ],
		] );
	}

	public function authorize(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public function list_feeds( \WP_REST_Request $request ): \WP_REST_Response {
		$property_id = (int) $request->get_param( 'property_id' );
		$rows = $property_id > 0
			? $this->feeds->find_for_property( $property_id )
			: $this->feeds->find_enabled();
		return new \WP_REST_Response( $rows );
	}

	public function create_feed( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = $this->feeds->insert( [
			'property_id'   => (int) $request->get_param( 'property_id' ),
			'url'           => esc_url_raw( (string) $request->get_param( 'url' ) ),
			'label'         => sanitize_text_field( (string) $request->get_param( 'label' ) ),
			'source'        => sanitize_key( (string) $request->get_param( 'source' ) ),
			'sync_interval' => max( 300, (int) ( $request->get_param( 'sync_interval' ) ?: 1800 ) ),
		] );
		return new \WP_REST_Response( $this->feeds->find_by_id( $id ), 201 );
	}

	public function update_feed( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );
		$existing = $this->feeds->find_by_id( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', 'Feed not found', [ 'status' => 404 ] );
		}
		$updates = [];
		foreach ( [ 'url', 'label', 'source', 'sync_interval', 'enabled' ] as $field ) {
			$value = $request->get_param( $field );
			if ( $value === null ) {
				continue;
			}
			$updates[ $field ] = match ( $field ) {
				'url'           => esc_url_raw( (string) $value ),
				'label'         => sanitize_text_field( (string) $value ),
				'source'        => sanitize_key( (string) $value ),
				'sync_interval' => max( 300, (int) $value ),
				'enabled'       => (int) (bool) $value,
				default         => $value,
			};
		}
		$this->feeds->update( $id, $updates );
		return new \WP_REST_Response( $this->feeds->find_by_id( $id ) );
	}

	public function delete_feed( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		$this->feeds->delete( $id );
		return new \WP_REST_Response( [ 'deleted' => $id ] );
	}

	public function sync_now( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		$ok = $this->importer->import( $id );
		return new \WP_REST_Response( [ 'synced' => $ok, 'feed' => $this->feeds->find_by_id( $id ) ] );
	}
}
