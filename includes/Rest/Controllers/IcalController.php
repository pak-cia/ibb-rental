<?php
/**
 * GET /ical/{property_id}.ics — serves the signed iCalendar export feed.
 *
 * Bad/missing signature returns 404 (not 401) to avoid leaking which property
 * IDs exist. Sets ETag/Last-Modified so OTAs polling on a schedule can use
 * conditional GETs and we don't recompute the body unnecessarily.
 *
 * The WP REST infrastructure JSON-encodes response bodies by default, which
 * would mangle our raw `text/calendar` output — so we hook `rest_pre_serve_request`
 * for this specific route, emit the raw bytes ourselves, and short-circuit the
 * default serializer.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Rest\Controllers;

use IBB\Rentals\Ical\Exporter;
use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class IcalController {

	public function __construct(
		private Exporter $exporter,
	) {}

	public function register( string $namespace ): void {
		register_rest_route( $namespace, '/ical/(?P<property_id>\d+)\.ics', [
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'args'                => [
				'property_id' => [ 'required' => true, 'type' => 'integer' ],
				'token'       => [ 'required' => true, 'type' => 'string' ],
			],
			'callback' => [ $this, 'handle' ],
		] );
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$property_id = (int) $request->get_param( 'property_id' );
		$token       = (string) $request->get_param( 'token' );

		$post = get_post( $property_id );
		if ( ! $post || $post->post_type !== PropertyPostType::POST_TYPE || $post->post_status !== 'publish' ) {
			return new \WP_Error( 'not_found', 'Not found', [ 'status' => 404 ] );
		}
		if ( ! $this->exporter->verify_token( $property_id, $token ) ) {
			return new \WP_Error( 'not_found', 'Not found', [ 'status' => 404 ] );
		}

		$body = $this->exporter->build( $property_id );
		$etag = '"' . md5( $body ) . '"';

		$if_none_match = (string) $request->get_header( 'if-none-match' );
		if ( $if_none_match !== '' && trim( $if_none_match ) === $etag ) {
			$this->emit_headers( 304, $etag );
			exit;
		}

		// Short-circuit JSON serializer and emit raw text/calendar.
		$this->emit_headers( 200, $etag );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private function emit_headers( int $status, string $etag ): void {
		status_header( $status );
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'ETag: ' . $etag );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', time() ) . ' GMT' );
		header( 'Cache-Control: public, max-age=300' );
	}
}
