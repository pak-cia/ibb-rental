<?php
/**
 * Builds an RFC 5545 iCalendar feed for a single property.
 *
 * Exports ONLY direct bookings + manual block-outs — never re-exports events
 * imported from other channels. This avoids the classic two-way feedback loop
 * where Airbnb pulls our feed, sees a Booking.com block re-exported, and
 * (after UID rewrites) double-blocks the date.
 *
 * SUMMARY is always "Reserved" — guest names never appear in exports for
 * privacy and OTA-policy compliance reasons.
 *
 * Hand-rolled rather than depending on sabre/vobject for the export path —
 * the format we generate is small and well-defined; importing third-party
 * .ics files (where edge-case parsing matters) is where we need the library.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Ical;

use IBB\Rentals\Domain\Block;
use IBB\Rentals\Repositories\AvailabilityRepository;
use IBB\Rentals\Support\Hooks;

defined( 'ABSPATH' ) || exit;

final class Exporter {

	private const PRODID = '-//IBB Rentals//ibb-rentals 0.1//EN';
	private const LINE_LENGTH = 75;

	public function __construct(
		private AvailabilityRepository $blocks,
	) {}

	public function build( int $property_id ): string {
		$events = $this->blocks->find_exportable( $property_id );
		$events = (array) apply_filters( Hooks::ICAL_BEFORE_EXPORT, $events, $property_id );

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'localhost';

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:' . self::PRODID,
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . $this->escape( get_the_title( $property_id ) ?: 'Property ' . $property_id ),
		];

		$now_utc = gmdate( 'Ymd\THis\Z' );

		foreach ( $events as $block ) {
			if ( ! $block instanceof Block ) {
				continue;
			}
			$summary = (string) apply_filters( Hooks::FILTER_ICAL_EXPORT_SUMMARY, 'Reserved', $block );

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:ibb-' . $block->id . '@' . $site_host;
			$lines[] = 'DTSTAMP:' . $now_utc;
			$lines[] = 'DTSTART;VALUE=DATE:' . $this->compact_date( $block->range->checkin_string() );
			$lines[] = 'DTEND;VALUE=DATE:'   . $this->compact_date( $block->range->checkout_string() );
			$lines[] = 'SUMMARY:' . $this->escape( $summary );
			$lines[] = 'TRANSP:OPAQUE';
			$lines[] = 'STATUS:CONFIRMED';
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';

		return $this->fold( $lines );
	}

	public function compute_etag( int $property_id ): string {
		return md5( $this->build( $property_id ) );
	}

	public function verify_token( int $property_id, string $token ): bool {
		if ( $token === '' ) {
			return false;
		}
		$secret = (string) get_option( 'ibb_rentals_token_secret', '' );
		if ( $secret === '' ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', 'ical:' . $property_id, $secret );
		return hash_equals( $expected, $token );
	}

	public function token_for( int $property_id ): string {
		$secret = (string) get_option( 'ibb_rentals_token_secret', '' );
		return hash_hmac( 'sha256', 'ical:' . $property_id, $secret );
	}

	public function feed_url( int $property_id ): string {
		return add_query_arg(
			'token',
			$this->token_for( $property_id ),
			rest_url( 'ibb-rentals/v1/ical/' . $property_id . '.ics' )
		);
	}

	private function compact_date( string $ymd ): string {
		return str_replace( '-', '', $ymd );
	}

	private function escape( string $value ): string {
		$value = str_replace( [ '\\', "\n", "\r", ',', ';' ], [ '\\\\', '\\n', '', '\\,', '\\;' ], $value );
		return $value;
	}

	/**
	 * RFC 5545 line folding: lines longer than 75 octets must be split,
	 * with continuation lines beginning with a single space.
	 *
	 * @param list<string> $lines
	 */
	private function fold( array $lines ): string {
		$out = [];
		foreach ( $lines as $line ) {
			while ( strlen( $line ) > self::LINE_LENGTH ) {
				$out[] = substr( $line, 0, self::LINE_LENGTH );
				$line  = ' ' . substr( $line, self::LINE_LENGTH );
			}
			$out[] = $line;
		}
		return implode( "\r\n", $out ) . "\r\n";
	}
}
