<?php
/**
 * Pulls one OTA iCal feed and reconciles it into `wp_ibb_blocks`.
 *
 * Strategy:
 *   1. HTTP GET with conditional headers (If-None-Match / If-Modified-Since).
 *      304 → record success and exit.
 *   2. Parse the response body via the in-house Parser.
 *   3. For each event, upsert by `(property_id, source, external_uid)`.
 *   4. Delete any rows for `(property_id, source)` whose UID is no longer in
 *      the feed — that's how OTAs communicate cancellations.
 *
 * Failures are recorded on the feed row; after 5 consecutive failures the
 * caller (FeedScheduler) backs off the polling interval.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Ical;

use IBB\Rentals\Domain\Block;
use IBB\Rentals\Domain\DateRange;
use IBB\Rentals\Repositories\AvailabilityRepository;
use IBB\Rentals\Repositories\FeedRepository;
use IBB\Rentals\Support\Hooks;
use IBB\Rentals\Support\Logger;

defined( 'ABSPATH' ) || exit;

final class Importer {

	public function __construct(
		private FeedRepository $feeds,
		private AvailabilityRepository $blocks,
		private Parser $parser,
		private Logger $logger,
	) {}

	public function import( int $feed_id ): bool {
		$feed = $this->feeds->find_by_id( $feed_id );
		if ( ! $feed || ! (int) $feed['enabled'] ) {
			return false;
		}

		$response = wp_safe_remote_get( (string) $feed['url'], [
			'timeout' => 15,
			'headers' => array_filter( [
				'If-None-Match'     => (string) ( $feed['etag'] ?? '' ) ?: null,
				'If-Modified-Since' => (string) ( $feed['last_modified'] ?? '' ) ?: null,
				'User-Agent'        => 'IBB Rentals/' . IBB_RENTALS_VERSION . ' (+' . home_url() . ')',
				'Accept'            => 'text/calendar, text/plain',
			] ),
			'limit_response_size' => 10 * MB_IN_BYTES,
		] );

		if ( is_wp_error( $response ) ) {
			$this->feeds->record_failure( $feed_id, $response->get_error_message() );
			$this->logger->warning( 'iCal fetch failed', [ 'feed' => $feed_id, 'error' => $response->get_error_message() ] );
			return false;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status === 304 ) {
			$this->feeds->record_success( $feed_id, (string) $feed['etag'], (string) $feed['last_modified'] );
			return true;
		}
		if ( $status < 200 || $status >= 300 ) {
			$this->feeds->record_failure( $feed_id, 'HTTP ' . $status );
			return false;
		}

		$body = (string) wp_remote_retrieve_body( $response );
		$etag = (string) wp_remote_retrieve_header( $response, 'etag' );
		$lm   = (string) wp_remote_retrieve_header( $response, 'last-modified' );

		try {
			$events = $this->parser->parse_events( $body );
		} catch ( \Throwable $e ) {
			$this->feeds->record_failure( $feed_id, 'Parse error: ' . $e->getMessage() );
			return false;
		}

		$property_id = (int) $feed['property_id'];
		$source      = (string) $feed['source'];
		$kept        = [];
		$processed   = 0;

		foreach ( $events as $event ) {
			if ( $event['uid'] === '' || $event['start'] === '' || $event['end'] === '' ) {
				continue;
			}
			try {
				$range = DateRange::from_strings( $event['start'], $event['end'] );
			} catch ( \Throwable ) {
				continue;
			}

			$block = new Block(
				id:           null,
				property_id:  $property_id,
				range:        $range,
				source:       $source,
				external_uid: $event['uid'],
				status:       Block::STATUS_CONFIRMED,
				order_id:     null,
				summary:      $event['summary'] !== '' ? $event['summary'] : 'Imported',
			);
			$this->blocks->upsert_by_uid( $block );
			$kept[] = $event['uid'];
			$processed++;
		}

		$this->blocks->delete_stale_by_source( $property_id, $source, $kept );
		$this->feeds->record_success( $feed_id, $etag, $lm );

		do_action( Hooks::ICAL_AFTER_IMPORT, $feed_id, $processed );
		return true;
	}
}
