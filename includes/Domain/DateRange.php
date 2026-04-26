<?php
/**
 * Immutable half-open date range [checkin, checkout).
 *
 * The convention matches the iCalendar spec for date-only VEVENTs and matches
 * the schema of `wp_ibb_blocks` (start_date inclusive, end_date exclusive).
 * This means turnover days are allowed: a stay that checks out on the same day
 * another checks in does NOT overlap.
 *
 * All instances are at midnight UTC — stays are tracked as calendar dates,
 * never wall-clock times, so DST is a non-issue.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

final class DateRange {

	private const FMT = 'Y-m-d';

	private function __construct(
		public readonly DateTimeImmutable $checkin,
		public readonly DateTimeImmutable $checkout,
	) {}

	public static function from_strings( string $checkin, string $checkout ): self {
		$tz = new DateTimeZone( 'UTC' );
		$ci = DateTimeImmutable::createFromFormat( '!' . self::FMT, $checkin, $tz );
		$co = DateTimeImmutable::createFromFormat( '!' . self::FMT, $checkout, $tz );

		if ( ! $ci || $ci->format( self::FMT ) !== $checkin ) {
			throw new InvalidArgumentException( "Invalid checkin date: {$checkin}" );
		}
		if ( ! $co || $co->format( self::FMT ) !== $checkout ) {
			throw new InvalidArgumentException( "Invalid checkout date: {$checkout}" );
		}
		if ( $co <= $ci ) {
			throw new InvalidArgumentException( 'Checkout must be after checkin.' );
		}

		return new self( $ci, $co );
	}

	public static function from_dates( DateTimeImmutable $checkin, DateTimeImmutable $checkout ): self {
		$tz = new DateTimeZone( 'UTC' );
		return self::from_strings(
			$checkin->setTimezone( $tz )->format( self::FMT ),
			$checkout->setTimezone( $tz )->format( self::FMT )
		);
	}

	public function checkin_string(): string {
		return $this->checkin->format( self::FMT );
	}

	public function checkout_string(): string {
		return $this->checkout->format( self::FMT );
	}

	public function nights(): int {
		return (int) $this->checkin->diff( $this->checkout )->days;
	}

	public function overlaps( self $other ): bool {
		return $this->checkin < $other->checkout && $other->checkin < $this->checkout;
	}

	public function contains( DateTimeImmutable $date ): bool {
		return $date >= $this->checkin && $date < $this->checkout;
	}

	/**
	 * Yield each night's date (the night the guest sleeps). Excludes checkout.
	 *
	 * @return iterable<DateTimeImmutable>
	 */
	public function each_night(): iterable {
		$cursor = $this->checkin;
		$one    = new \DateInterval( 'P1D' );
		while ( $cursor < $this->checkout ) {
			yield $cursor;
			$cursor = $cursor->add( $one );
		}
	}

	public function equals( self $other ): bool {
		return $this->checkin == $other->checkin && $this->checkout == $other->checkout;
	}

	public function __toString(): string {
		return $this->checkin_string() . ' → ' . $this->checkout_string();
	}
}
