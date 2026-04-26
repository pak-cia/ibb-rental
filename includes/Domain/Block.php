<?php
/**
 * A single availability block — one row in `wp_ibb_blocks`.
 *
 * Blocks come from three places: direct bookings on this site (source='direct'),
 * imported iCal events from OTAs (source='airbnb'|'booking'|'agoda'|'vrbo'|'other'),
 * and manual admin block-outs (source='manual'). A short-lived 'hold' source is
 * used to reserve dates during checkout submission to prevent races.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Domain;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

final class Block {

	public const SOURCE_DIRECT  = 'direct';
	public const SOURCE_MANUAL  = 'manual';
	public const SOURCE_HOLD    = 'hold';
	public const SOURCE_AIRBNB  = 'airbnb';
	public const SOURCE_BOOKING = 'booking';
	public const SOURCE_AGODA   = 'agoda';
	public const SOURCE_VRBO    = 'vrbo';
	public const SOURCE_OTHER   = 'other';

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
		public readonly ?DateTimeImmutable $created_at = null,
		public readonly ?DateTimeImmutable $updated_at = null,
	) {}

	/** @param array<string, mixed> $row */
	public static function from_row( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );
		return new self(
			id:           isset( $row['id'] ) ? (int) $row['id'] : null,
			property_id:  (int) $row['property_id'],
			range:        DateRange::from_strings( (string) $row['start_date'], (string) $row['end_date'] ),
			source:       (string) $row['source'],
			external_uid: (string) ( $row['external_uid'] ?? '' ),
			status:       (string) ( $row['status'] ?? self::STATUS_CONFIRMED ),
			order_id:     isset( $row['order_id'] ) && $row['order_id'] ? (int) $row['order_id'] : null,
			summary:      (string) ( $row['summary'] ?? '' ),
			created_at:   isset( $row['created_at'] ) ? new DateTimeImmutable( (string) $row['created_at'], $tz ) : null,
			updated_at:   isset( $row['updated_at'] ) ? new DateTimeImmutable( (string) $row['updated_at'], $tz ) : null,
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
		return ! in_array( $this->source, [ self::SOURCE_DIRECT, self::SOURCE_MANUAL, self::SOURCE_HOLD ], true );
	}
}
