<?php
/**
 * Availability checks and booking-rule validation.
 *
 * `is_available()` is the single chokepoint that everything else (quote endpoint,
 * cart validation, checkout submission) consults. Rules validation happens here
 * too rather than in the caller, so behaviour is consistent across REST/cart/admin.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Services;

use DateTimeImmutable;
use IBB\Rentals\Domain\DateRange;
use IBB\Rentals\Domain\Property;
use IBB\Rentals\Repositories\AvailabilityRepository;
use IBB\Rentals\Support\Hooks;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class AvailabilityService {

	public function __construct(
		private AvailabilityRepository $blocks,
	) {}

	public function is_available( int $property_id, DateRange $range ): bool {
		$result = ! $this->blocks->any_overlap( $property_id, $range );
		return (bool) apply_filters( Hooks::FILTER_IS_AVAILABLE, $result, $property_id, $range );
	}

	/**
	 * Returns every blocked calendar date inside the window, formatted as Y-m-d.
	 * Includes both DB blocks (direct bookings + iCal imports) and the property's
	 * blackout ranges so the date-picker greys them all out.
	 *
	 * @return list<string>
	 */
	public function get_blocked_dates( int $property_id, DateRange $window ): array {
		$dates = [];

		foreach ( $this->blocks->find_in_window( $property_id, $window ) as $block ) {
			foreach ( $block->range->each_night() as $night ) {
				$dates[ $night->format( 'Y-m-d' ) ] = true;
			}
		}

		$property = Property::from_id( $property_id );
		if ( $property ) {
			foreach ( $property->blackout_ranges() as $blackout ) {
				try {
					$br = DateRange::from_strings( $blackout['start'], $blackout['end'] );
				} catch ( \Throwable ) {
					continue;
				}
				if ( ! $br->overlaps( $window ) ) {
					continue;
				}
				foreach ( $br->each_night() as $night ) {
					if ( $window->contains( $night ) ) {
						$dates[ $night->format( 'Y-m-d' ) ] = true;
					}
				}
			}
		}

		ksort( $dates );
		return array_keys( $dates );
	}

	/**
	 * Validates rules a booking must satisfy beyond raw availability —
	 * min/max nights, advance window, blackout dates, max guests.
	 *
	 * @return WP_Error|true
	 */
	public function validate_booking_rules( Property $property, DateRange $range, int $guests ) {
		$nights = $range->nights();
		$min    = $property->min_nights();
		$max    = $property->max_nights();

		if ( $nights < $min ) {
			return new WP_Error(
				'min_nights',
				sprintf(
					/* translators: %d: minimum nights */
					_n( 'Minimum stay is %d night.', 'Minimum stay is %d nights.', $min, 'ibb-rentals' ),
					$min
				)
			);
		}
		if ( $max > 0 && $nights > $max ) {
			return new WP_Error(
				'max_nights',
				sprintf(
					/* translators: %d: maximum nights */
					_n( 'Maximum stay is %d night.', 'Maximum stay is %d nights.', $max, 'ibb-rentals' ),
					$max
				)
			);
		}

		$today    = new DateTimeImmutable( gmdate( 'Y-m-d' ) );
		$lead     = (int) $range->checkin->diff( $today )->days * ( $range->checkin < $today ? -1 : 1 );
		$min_lead = $property->advance_booking_days();
		$max_lead = $property->max_advance_days();

		if ( $range->checkin < $today ) {
			return new WP_Error( 'past_date', __( 'Check-in date is in the past.', 'ibb-rentals' ) );
		}
		if ( $min_lead > 0 && $lead < $min_lead ) {
			return new WP_Error(
				'advance_booking',
				sprintf(
					/* translators: %d: minimum advance days */
					__( 'Bookings must be made at least %d days in advance.', 'ibb-rentals' ),
					$min_lead
				)
			);
		}
		if ( $max_lead > 0 && $lead > $max_lead ) {
			return new WP_Error(
				'too_far_ahead',
				sprintf(
					/* translators: %d: maximum advance days */
					__( 'Bookings cannot be made more than %d days in advance.', 'ibb-rentals' ),
					$max_lead
				)
			);
		}

		if ( $guests < 1 ) {
			return new WP_Error( 'min_guests', __( 'At least one guest is required.', 'ibb-rentals' ) );
		}
		if ( $guests > $property->max_guests() ) {
			return new WP_Error(
				'max_guests',
				sprintf(
					/* translators: %d: max guests */
					__( 'This property accommodates a maximum of %d guests.', 'ibb-rentals' ),
					$property->max_guests()
				)
			);
		}

		foreach ( $property->blackout_ranges() as $blackout ) {
			try {
				$blackout_range = DateRange::from_strings( $blackout['start'], $blackout['end'] );
			} catch ( \Throwable $e ) {
				continue;
			}
			if ( $range->overlaps( $blackout_range ) ) {
				return new WP_Error( 'blackout', __( 'Selected dates fall within a blackout period.', 'ibb-rentals' ) );
			}
		}

		if ( ! $this->is_available( $property->id, $range ) ) {
			// Include overlap details in both the message and the response data so the
			// frontend (and an admin debugging via DevTools) can see which existing
			// block is in the way — far more useful than a generic "not available".
			$overlaps = $this->blocks->find_overlapping( $property->id, $range );
			$summaries = [];
			foreach ( $overlaps as $block ) {
				$source = $block->source_override !== '' ? $block->source_override : $block->source;
				$label  = ucfirst( $source );
				if ( $block->guest_name !== '' ) {
					$label .= ' / ' . $block->guest_name;
				}
				$summaries[] = sprintf(
					'%s · %s → %s',
					$label,
					$block->range->checkin_string(),
					$block->range->checkout_string()
				);
			}

			$msg = __( 'Selected dates are not available.', 'ibb-rentals' );
			if ( $summaries ) {
				$msg .= ' (' . implode( '; ', $summaries ) . ')';
			}

			return new WP_Error(
				'unavailable',
				$msg,
				[
					'overlapping_blocks' => array_map(
						fn( $b ) => [
							'id'         => $b->id,
							'source'     => $b->source,
							'override'   => $b->source_override,
							'start'      => $b->range->checkin_string(),
							'end'        => $b->range->checkout_string(),
							'guest_name' => $b->guest_name,
							'status'     => $b->status,
						],
						$overlaps
					),
				]
			);
		}

		return true;
	}
}
