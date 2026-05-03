<?php
/**
 * A single availability block — one row in `wp_ibb_blocks`.
 *
 * Sources (the `source` column):
 *   - `web`     — booked via this site's WooCommerce checkout (paid order)
 *   - `direct`  — walk-in / phone / in-person, entered manually by the host
 *   - `manual`  — admin block-out (maintenance, owner stay, etc.)
 *   - `hold`    — short-lived race-prevention lock during checkout submission
 *   - `airbnb` / `booking` / `agoda` / `vrbo` / `expedia` — imported from that
 *     OTA, either via iCal feed *or* synthesised from ClickUp tasks for OTAs
 *     that don't expose an iCal feed (Agoda is the motivating case). The plugin
 *     becomes the central availability hub: every OTA points its inbound
 *     calendar at our per-OTA export URL, and every block gets fanned out to
 *     every other OTA — see Ical/Exporter for the loop-guard rule.
 *   - `other`   — generic catch-all when iCal source can't be classified
 */

declare( strict_types=1 );

namespace IBB\Rentals\Domain;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

final class Block {

	public const SOURCE_WEB     = 'web';      // Plugin/website checkout
	public const SOURCE_DIRECT  = 'direct';   // Walk-in / phone / in-person
	public const SOURCE_MANUAL  = 'manual';
	public const SOURCE_HOLD    = 'hold';
	public const SOURCE_AIRBNB  = 'airbnb';
	public const SOURCE_BOOKING = 'booking';
	public const SOURCE_AGODA   = 'agoda';
	public const SOURCE_VRBO    = 'vrbo';
	public const SOURCE_EXPEDIA = 'expedia';
	public const SOURCE_OTHER   = 'other';

	/**
	 * Sources that originate inside this plugin (not synced in from
	 * elsewhere). These are always exported to every OTA's feed — they
	 * never need a loop-guard because no OTA owns them.
	 *
	 * @var list<string>
	 */
	public const LOCAL_SOURCES = [ self::SOURCE_WEB, self::SOURCE_DIRECT, self::SOURCE_MANUAL ];

	/**
	 * Sources tied to a specific OTA. Used by the per-OTA feed exporter to
	 * decide which blocks to suppress when serving that OTA's feed (a block
	 * with `source=airbnb` is excluded from the Airbnb feed to prevent
	 * loops, but included in every other OTA's feed).
	 *
	 * @var list<string>
	 */
	public const OTA_SOURCES = [
		self::SOURCE_AIRBNB,
		self::SOURCE_BOOKING,
		self::SOURCE_AGODA,
		self::SOURCE_VRBO,
		self::SOURCE_EXPEDIA,
	];

	public const STATUS_CONFIRMED = 'confirmed';
	public const STATUS_TENTATIVE = 'tentative';
	public const STATUS_CANCELLED = 'cancelled';

	public function __construct(
		public readonly ?int $id,
		public readonly int $property_id,
		public readonly DateRange $range,
		public readonly string $source,
		public readonly string $external_uid,
		public readonly string $status,
		public readonly ?int $order_id,
		public readonly string $summary,
		public readonly string $guest_name = '',
		public readonly string $clickup_task_id = '',
		public readonly string $source_override = '',
		public readonly ?DateTimeImmutable $created_at = null,
		public readonly ?DateTimeImmutable $updated_at = null,
	) {}

	/**
	 * Returns the source to display in the calendar / treat as canonical for the
	 * booking's actual OTA. The ClickUp sync writes `source_override` based on the
	 * task's tag, since iCal imports can misattribute a booking — e.g. when a direct
	 * or Agoda booking is manually blocked on Airbnb to prevent double-booking,
	 * the Airbnb iCal feed re-imports it as `source='airbnb'`. The override (when
	 * present) reflects the booking's real origin per ClickUp.
	 */
	public function effective_source(): string {
		return $this->source_override !== '' ? $this->source_override : $this->source;
	}

	/** @param array<string, mixed> $row */
	public static function from_row( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );
		return new self(
			id:              isset( $row['id'] ) ? (int) $row['id'] : null,
			property_id:     (int) $row['property_id'],
			range:           DateRange::from_strings( (string) $row['start_date'], (string) $row['end_date'] ),
			source:          (string) $row['source'],
			external_uid:    (string) ( $row['external_uid'] ?? '' ),
			status:          (string) ( $row['status'] ?? self::STATUS_CONFIRMED ),
			order_id:        isset( $row['order_id'] ) && $row['order_id'] ? (int) $row['order_id'] : null,
			summary:         (string) ( $row['summary'] ?? '' ),
			guest_name:      (string) ( $row['guest_name'] ?? '' ),
			clickup_task_id: (string) ( $row['clickup_task_id'] ?? '' ),
			source_override: (string) ( $row['source_override'] ?? '' ),
			created_at:      isset( $row['created_at'] ) ? new DateTimeImmutable( (string) $row['created_at'], $tz ) : null,
			updated_at:      isset( $row['updated_at'] ) ? new DateTimeImmutable( (string) $row['updated_at'], $tz ) : null,
		);
	}

	/** @return array<string, mixed> */
	public function to_row(): array {
		return [
			'property_id'  => $this->property_id,
			'start_date'   => $this->range->checkin_string(),
			'end_date'     => $this->range->checkout_string(),
			'source'       => $this->source,
			'external_uid' => $this->external_uid,
			'status'       => $this->status,
			'order_id'     => $this->order_id,
			'summary'      => $this->summary,
		];
	}

	public function is_active(): bool {
		return $this->status === self::STATUS_CONFIRMED || $this->status === self::STATUS_TENTATIVE;
	}

	public function is_imported(): bool {
		return ! in_array( $this->source, [ self::SOURCE_WEB, self::SOURCE_DIRECT, self::SOURCE_MANUAL, self::SOURCE_HOLD ], true );
	}
}
