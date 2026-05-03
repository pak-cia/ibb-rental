<?php
/**
 * Builds an RFC 5545 iCalendar feed for a single property, scoped to a
 * specific OTA.
 *
 * Hub-and-spoke topology (v0.11): the plugin is the central availability
 * source-of-truth. Each OTA points its inbound calendar at our **per-OTA**
 * feed URL — `/ical/<property_id>/<for_ota>.ics?token=…`. The feed served to
 * a given OTA includes every confirmed block from every other source — its
 * own iCal-imported blocks are suppressed (loop-guard) but blocks from other
 * OTAs and from this site (web / direct / manual) are included. ClickUp's
 * Agoda-via-task-sync (also v0.11) drops blocks with `source='agoda'` into
 * the same store, so Airbnb's feed sees them too without any extra plumbing.
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

	/**
	 * @param string $for_ota One of Block::OTA_SOURCES (airbnb / booking /
	 *                        agoda / vrbo / expedia). Empty string means
	 *                        "include everything except holds" — used by
	 *                        admin previews, not exposed as a published feed.
	 */
	public function build( int $property_id, string $for_ota = '' ): string {
		$events = $this->blocks->find_exportable( $property_id, $for_ota );
		$events = (array) apply_filters( Hooks::ICAL_BEFORE_EXPORT, $events, $property_id, $for_ota );

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'localhost';

		$calname = (string) ( get_the_title( $property_id ) ?: 'Property ' . $property_id );
		if ( $for_ota !== '' ) {
			$calname .= ' — for ' . ucfirst( $for_ota );
		}

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:' . self::PRODID,
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . $this->escape( $calname ),
		];

		$now_utc        = gmdate( 'Ymd\THis\Z' );
		$property_title = (string) ( get_the_title( $property_id ) ?: '' );
		$settings       = (array) get_option( 'ibb_rentals_settings', [] );
		$include_names  = ! empty( $settings['ical_include_guest_names'] );

		foreach ( $events as $block ) {
			if ( ! $block instanceof Block ) {
				continue;
			}

			$source_label = $this->source_label( $block->effective_source() );
			$nights       = $block->range->nights();

			// SUMMARY: what Airbnb / Booking.com render in their calendar
			// tooltip and event-list. When guest names are opted in and we
			// have one (set by the ClickUp sync, or by direct/web bookings
			// in the future), we surface "Bob Jones (Agoda)" so the host
			// sees who's staying when. Otherwise fall back to a richer
			// label than the v0.10 "Reserved" — at least the OTA name.
			if ( $include_names && $block->guest_name !== '' ) {
				$summary = sprintf( '%s (%s)', $block->guest_name, $source_label );
			} elseif ( $source_label !== '' ) {
				$summary = sprintf( '%s booking', $source_label );
			} else {
				$summary = 'Reserved';
			}
			$summary = (string) apply_filters( Hooks::FILTER_ICAL_EXPORT_SUMMARY, $summary, $block );

			// DESCRIPTION: visible when the OTA expands the event card.
			// Includes the property title, stay length, source, and a
			// ClickUp deep-link when we have a task_id — the host can
			// click straight from Airbnb's calendar into the ClickUp
			// booking card. ClickUp's `https://app.clickup.com/t/<id>`
			// short URL redirects to the workspace-scoped URL, so we
			// don't need the workspace ID at export time.
			$desc_parts = [];
			if ( $property_title !== '' ) {
				$desc_parts[] = $property_title;
			}
			$desc_parts[] = sprintf( '%d night%s', $nights, $nights === 1 ? '' : 's' );
			if ( $source_label !== '' ) {
				$desc_parts[] = 'Source: ' . $source_label;
			}
			if ( $include_names && $block->guest_name !== '' ) {
				$desc_parts[] = 'Guest: ' . $block->guest_name;
			}
			if ( $block->clickup_task_id !== '' ) {
				$desc_parts[] = 'ClickUp: https://app.clickup.com/t/' . $block->clickup_task_id;
			}
			$description = implode( "\n", $desc_parts );

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:ibb-' . $block->id . '@' . $site_host;
			$lines[] = 'DTSTAMP:' . $now_utc;
			$lines[] = 'DTSTART;VALUE=DATE:' . $this->compact_date( $block->range->checkin_string() );
			$lines[] = 'DTEND;VALUE=DATE:'   . $this->compact_date( $block->range->checkout_string() );
			$lines[] = 'SUMMARY:' . $this->escape( $summary );
			$lines[] = 'DESCRIPTION:' . $this->escape( $description );
			$lines[] = 'TRANSP:OPAQUE';
			$lines[] = 'STATUS:CONFIRMED';
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';

		return $this->fold( $lines );
	}

	public function compute_etag( int $property_id, string $for_ota = '' ): string {
		return md5( $this->build( $property_id, $for_ota ) );
	}

	/**
	 * Tokens are namespaced per (property, OTA) so rotating one OTA's feed
	 * doesn't invalidate the others. The legacy combined token shape
	 * (`ical:<id>`) is no longer accepted — v0.11 is a hard switch.
	 */
	public function verify_token( int $property_id, string $for_ota, string $token ): bool {
		if ( $token === '' ) {
			return false;
		}
		$secret = (string) get_option( 'ibb_rentals_token_secret', '' );
		if ( $secret === '' ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', 'ical:' . $property_id . ':' . $for_ota, $secret );
		return hash_equals( $expected, $token );
	}

	public function token_for( int $property_id, string $for_ota ): string {
		$secret = (string) get_option( 'ibb_rentals_token_secret', '' );
		return hash_hmac( 'sha256', 'ical:' . $property_id . ':' . $for_ota, $secret );
	}

	public function feed_url( int $property_id, string $for_ota ): string {
		return add_query_arg(
			'token',
			$this->token_for( $property_id, $for_ota ),
			rest_url( 'ibb-rentals/v1/ical/' . $property_id . '/' . $for_ota . '.ics' )
		);
	}

	/**
	 * Map of for_ota → feed URL for every OTA the plugin can serve. Useful
	 * for the property iCal-tab UI which renders one row per OTA.
	 *
	 * @return array<string,string>
	 */
	public function feed_urls( int $property_id ): array {
		$out = [];
		foreach ( \IBB\Rentals\Domain\Block::OTA_SOURCES as $ota ) {
			$out[ $ota ] = $this->feed_url( $property_id, $ota );
		}
		return $out;
	}

	private function compact_date( string $ymd ): string {
		return str_replace( '-', '', $ymd );
	}

	/**
	 * Human-friendly source label for SUMMARY / DESCRIPTION strings. Source
	 * slugs that round-trip through ucfirst (vrbo→Vrbo) get explicit casing.
	 */
	private function source_label( string $slug ): string {
		return match ( $slug ) {
			'web'     => 'Website',
			'direct'  => 'Walk-in',
			'manual'  => 'Manual block',
			'airbnb'  => 'Airbnb',
			'booking' => 'Booking.com',
			'agoda'   => 'Agoda',
			'vrbo'    => 'VRBO',
			'expedia' => 'Expedia',
			default   => $slug !== '' ? ucfirst( $slug ) : '',
		};
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
