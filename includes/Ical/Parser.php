<?php
/**
 * Minimal iCalendar parser for OTA feeds.
 *
 * Covers the dialect every major OTA actually emits: VEVENT blocks with
 * DTSTART / DTEND in either VALUE=DATE or DATE-TIME form, UID, SUMMARY, and
 * (rare in OTA feeds) RRULE for blackout patterns. Handles RFC 5545 line
 * unfolding (continuation lines starting with whitespace) and basic DATE-TIME
 * timezones.
 *
 * For richer features (TZID lookup, complex RRULE with BYDAY/BYMONTHDAY/etc,
 * VALARM, EXDATE) we'd vendor sabre/vobject; the importer is built to swap
 * this parser out without touching the surrounding code.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Ical;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

final class Parser {

	/**
	 * @return list<array{uid:string,start:string,end:string,summary:string}>
	 */
	public function parse_events( string $ics ): array {
		$ics  = $this->unfold( $ics );
		$lines = preg_split( "/\r\n|\n|\r/", $ics ) ?: [];

		$events  = [];
		$current = null;

		foreach ( $lines as $line ) {
			if ( $line === '' ) {
				continue;
			}
			if ( $line === 'BEGIN:VEVENT' ) {
				$current = [ 'uid' => '', 'start' => '', 'end' => '', 'summary' => '', 'rrule' => '' ];
				continue;
			}
			if ( $line === 'END:VEVENT' ) {
				if ( is_array( $current ) && $current['start'] !== '' && $current['end'] !== '' ) {
					$expanded = $this->expand_recurrence( $current );
					foreach ( $expanded as $event ) {
						$events[] = $event;
					}
				}
				$current = null;
				continue;
			}
			if ( ! is_array( $current ) ) {
				continue;
			}

			$colon = strpos( $line, ':' );
			if ( $colon === false ) {
				continue;
			}

			$prefix = substr( $line, 0, $colon );
			$value  = substr( $line, $colon + 1 );

			$key = strtoupper( strtok( $prefix, ';' ) ?: '' );
			$params = [];
			while ( ( $param = strtok( ';' ) ) !== false ) {
				$eq = strpos( $param, '=' );
				if ( $eq !== false ) {
					$params[ strtoupper( substr( $param, 0, $eq ) ) ] = substr( $param, $eq + 1 );
				}
			}

			switch ( $key ) {
				case 'UID':
					$current['uid'] = trim( $value );
					break;
				case 'SUMMARY':
					$current['summary'] = $this->unescape( $value );
					break;
				case 'DTSTART':
					$current['start'] = $this->normalize_date( $value, $params, true );
					break;
				case 'DTEND':
					$current['end'] = $this->normalize_date( $value, $params, false );
					break;
				case 'RRULE':
					$current['rrule'] = $value;
					break;
			}
		}

		return $events;
	}

	private function unfold( string $body ): string {
		// Continuation: a CRLF (or LF) followed by a single space or tab continues the previous line.
		return (string) preg_replace( "/\r?\n[ \t]/", '', $body );
	}

	private function unescape( string $value ): string {
		return str_replace( [ '\\n', '\\N', '\\,', '\\;', '\\\\' ], [ "\n", "\n", ',', ';', '\\' ], $value );
	}

	/**
	 * Returns a date in `Y-m-d` form. For DATE-TIME values we take the date
	 * part in the original timezone (or UTC for trailing Z); for VALUE=DATE,
	 * the date is already given.
	 *
	 * @param array<string, string> $params
	 */
	private function normalize_date( string $raw, array $params, bool $is_start ): string {
		$raw = trim( $raw );

		if ( ( $params['VALUE'] ?? '' ) === 'DATE' || strlen( $raw ) === 8 ) {
			return substr( $raw, 0, 4 ) . '-' . substr( $raw, 4, 2 ) . '-' . substr( $raw, 6, 2 );
		}

		$tz = isset( $params['TZID'] ) ? $this->safe_timezone( $params['TZID'] ) : new DateTimeZone( 'UTC' );

		try {
			$dt = new DateTimeImmutable( $raw, $tz );
		} catch ( \Throwable ) {
			return '';
		}
		return $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d' );
	}

	private function safe_timezone( string $tzid ): DateTimeZone {
		try {
			return new DateTimeZone( $tzid );
		} catch ( \Throwable ) {
			return new DateTimeZone( 'UTC' );
		}
	}

	/**
	 * Expand a VEVENT into one or more concrete events.
	 *
	 * Supports FREQ=DAILY/WEEKLY with COUNT or UNTIL. Anything more exotic
	 * passes through unexpanded — the original event still applies.
	 *
	 * @param array{uid:string,start:string,end:string,summary:string,rrule:string} $event
	 * @return list<array{uid:string,start:string,end:string,summary:string}>
	 */
	private function expand_recurrence( array $event ): array {
		$base = [ 'uid' => $event['uid'], 'start' => $event['start'], 'end' => $event['end'], 'summary' => $event['summary'] ];

		if ( $event['rrule'] === '' ) {
			return [ $base ];
		}

		$rrule = $this->parse_rrule( $event['rrule'] );
		$freq  = strtoupper( $rrule['FREQ'] ?? '' );
		if ( ! in_array( $freq, [ 'DAILY', 'WEEKLY' ], true ) ) {
			return [ $base ];
		}

		try {
			$start = new DateTimeImmutable( $event['start'], new DateTimeZone( 'UTC' ) );
			$end   = new DateTimeImmutable( $event['end'], new DateTimeZone( 'UTC' ) );
		} catch ( \Throwable ) {
			return [ $base ];
		}

		$duration = $start->diff( $end );
		$step     = $freq === 'WEEKLY' ? 'P7D' : 'P1D';
		$interval = max( 1, (int) ( $rrule['INTERVAL'] ?? 1 ) );
		$count    = isset( $rrule['COUNT'] ) ? (int) $rrule['COUNT'] : 0;
		$until    = isset( $rrule['UNTIL'] ) ? $this->normalize_date( $rrule['UNTIL'], [], true ) : '';

		$horizon = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->modify( '+24 months' );
		$cursor  = $start;

		$out = [];
		$i   = 0;
		while ( $cursor <= $horizon ) {
			$out[] = [
				'uid'     => $event['uid'] . '#' . $cursor->format( 'Ymd' ),
				'start'   => $cursor->format( 'Y-m-d' ),
				'end'     => $cursor->add( $duration )->format( 'Y-m-d' ),
				'summary' => $event['summary'],
			];
			$i++;
			if ( $count > 0 && $i >= $count ) {
				break;
			}
			$cursor = $cursor->add( new \DateInterval( $step ) );
			if ( $interval > 1 ) {
				for ( $k = 1; $k < $interval; $k++ ) {
					$cursor = $cursor->add( new \DateInterval( $step ) );
				}
			}
			if ( $until !== '' && $cursor->format( 'Y-m-d' ) > $until ) {
				break;
			}
			if ( count( $out ) > 1000 ) {
				break; // safety
			}
		}
		return $out;
	}

	/** @return array<string, string> */
	private function parse_rrule( string $value ): array {
		$out = [];
		foreach ( explode( ';', $value ) as $piece ) {
			$eq = strpos( $piece, '=' );
			if ( $eq !== false ) {
				$out[ strtoupper( substr( $piece, 0, $eq ) ) ] = substr( $piece, $eq + 1 );
			}
		}
		return $out;
	}
}
