<?php
/**
 * Quote engine.
 *
 * Walks each night of a stay, picks the highest-priority rate row that covers
 * it (falling back to the property's base rate), applies weekend uplift, then
 * applies a single LOS discount (the longest tier the stay qualifies for) to
 * the nightly subtotal. Cleaning and extra-guest fees are added separately.
 * For deposit-mode properties, the total is split into deposit-due-now and
 * balance-due-on a calculated date.
 *
 * Money is treated as float DECIMAL throughout — we round only at the boundary
 * (in `Quote::to_array()` via `round($v, 2)`) which matches WC's own conventions
 * and avoids scattering Money primitives across the codebase.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Services;

use DateTimeImmutable;
use IBB\Rentals\Domain\DateRange;
use IBB\Rentals\Domain\Property;
use IBB\Rentals\Domain\Quote;
use IBB\Rentals\Repositories\RateRepository;
use IBB\Rentals\Support\Hooks;

defined( 'ABSPATH' ) || exit;

final class PricingService {

	public function __construct(
		private RateRepository $rates,
		private string $currency = '',
	) {
		if ( $this->currency === '' && function_exists( 'get_woocommerce_currency' ) ) {
			$this->currency = (string) get_woocommerce_currency();
		}
	}

	public function get_quote( Property $property, DateRange $range, int $guests ): Quote {
		$rate_rows    = $this->rates->find_for_window( $property->id, $range->checkin_string(), $range->checkout_string() );
		$weekend_days = $property->weekend_days();
		$base_rate    = $property->base_rate();
		$weekend_pct  = $property->weekend_uplift_pct();

		$nights = [];
		$nightly_subtotal = 0.0;

		foreach ( $range->each_night() as $date ) {
			$rate_row     = $this->rate_for_night( $rate_rows, $date );
			$nightly_base = $rate_row ? (float) $rate_row['nightly_rate'] : $base_rate;

			$is_weekend = in_array( (int) $date->format( 'N' ), $weekend_days, true );
			$applied    = $nightly_base;
			if ( $is_weekend ) {
				$applied = $this->apply_weekend_uplift( $nightly_base, $rate_row, $weekend_pct );
			}

			$nights[] = [
				'date'         => $date->format( 'Y-m-d' ),
				'base_rate'    => round( $nightly_base, 2 ),
				'weekend'      => $is_weekend,
				'applied_rate' => round( $applied, 2 ),
			];
			$nightly_subtotal += $applied;
		}

		$los          = $this->pick_los_discount( $property->los_discounts(), $range->nights() );
		$los_amount   = $los ? round( $nightly_subtotal * ( $los['pct'] / 100 ), 2 ) : 0.0;
		$discounted   = $nightly_subtotal - $los_amount;

		$over_threshold   = max( 0, $guests - $property->extra_guest_threshold() );
		$extra_guest_fee  = $over_threshold * $property->extra_guest_fee() * $range->nights();
		$cleaning_fee     = $property->cleaning_fee();
		$security_deposit = $property->security_deposit();

		$total = $discounted + $extra_guest_fee + $cleaning_fee;

		// Tax is computed per component using each component's own tax class,
		// then aggregated by rate-id so the front-end can render one line per
		// distinct tax rate (matches what WC's checkout will display). Net
		// amounts are passed to WC_Tax (we do not handle inclusive pricing in
		// v1 — see Pricing/CHANGELOG for the rationale).
		$accommodation_tax_class = $property->tax_class();
		$cleaning_tax_class      = $property->cleaning_tax_class();
		$extra_guest_tax_class   = $property->extra_guest_tax_class();

		$tax_components = [
			[ 'amount' => $discounted,        'class' => $accommodation_tax_class ],
			[ 'amount' => $cleaning_fee,      'class' => $cleaning_tax_class ],
			[ 'amount' => $extra_guest_fee,   'class' => $extra_guest_tax_class ],
		];
		[ $tax_breakdown, $tax_total ] = $this->compute_tax( $tax_components );
		$grand_total                   = round( $total + $tax_total, 2 );

		[ $payment_mode, $deposit_due, $balance_due, $balance_due_date ] = $this->split_payment( $property, $range, $grand_total );

		$quote = new Quote(
			property_id:       $property->id,
			range:             $range,
			guests:            $guests,
			nights:            $nights,
			nightly_subtotal:  $nightly_subtotal,
			los_discount:      $los ? [ 'min_nights' => $los['min_nights'], 'pct' => $los['pct'], 'amount' => $los_amount ] : null,
			extra_guest_fee:   $extra_guest_fee,
			cleaning_fee:      $cleaning_fee,
			security_deposit:  $security_deposit,
			total:             $total,
			tax_breakdown:     $tax_breakdown,
			tax_total:         $tax_total,
			grand_total:       $grand_total,
			accommodation_tax_class: $accommodation_tax_class,
			cleaning_tax_class:      $cleaning_tax_class,
			extra_guest_tax_class:   $extra_guest_tax_class,
			payment_mode:      $payment_mode,
			deposit_due:       $deposit_due,
			balance_due:       $balance_due,
			balance_due_date:  $balance_due_date,
			currency:          $this->currency,
			issued_at:         time(),
		);

		do_action( Hooks::QUOTE_COMPUTED, $quote, $property, $range );
		return $quote;
	}

	/**
	 * @param list<array<string, mixed>> $rate_rows already sorted by priority desc
	 * @return array<string, mixed>|null
	 */
	private function rate_for_night( array $rate_rows, DateTimeImmutable $date ): ?array {
		$ymd = $date->format( 'Y-m-d' );
		foreach ( $rate_rows as $row ) {
			if ( (string) $row['date_from'] <= $ymd && (string) $row['date_to'] >= $ymd ) {
				return $row;
			}
		}
		return null;
	}

	/** @param array<string, mixed>|null $rate_row */
	private function apply_weekend_uplift( float $base, ?array $rate_row, float $property_weekend_pct ): float {
		if ( $rate_row && $rate_row['weekend_uplift'] !== null && $rate_row['weekend_uplift'] !== '' ) {
			$value = (float) $rate_row['weekend_uplift'];
			$type  = (string) ( $rate_row['uplift_type'] ?? 'pct' );
			return $type === 'abs' ? $base + $value : $base * ( 1 + $value / 100 );
		}
		if ( $property_weekend_pct > 0 ) {
			return $base * ( 1 + $property_weekend_pct / 100 );
		}
		return $base;
	}

	/**
	 * @param list<array{min_nights:int,pct:float}> $tiers  pre-sorted desc by min_nights
	 * @return array{min_nights:int,pct:float}|null
	 */
	private function pick_los_discount( array $tiers, int $nights ): ?array {
		foreach ( $tiers as $tier ) {
			if ( $nights >= $tier['min_nights'] ) {
				return $tier;
			}
		}
		return null;
	}

	/**
	 * Compute tax for a list of (amount, IBB-tax-class) components.
	 *
	 * Each component's tax class is mapped to a WC tax-class slug:
	 *   '' (or unknown)  → not taxed → component skipped
	 *   'standard'       → '' (WC's standard rate)
	 *   any other slug   → that slug verbatim (validated by save handler)
	 *
	 * For each taxable component we resolve the active rates via
	 * `WC_Tax::find_rates()` (uses store base location + customer billing for
	 * shop-default; here we have no checkout context so base location is the
	 * sensible choice — matches what's printed in the cart subtotal during
	 * the quote-generation phase). Per-rate tax is summed across components
	 * and returned bucketed by rate-id so the UI can render one line per
	 * distinct rate (e.g. "PB1 10%", "Service 5%").
	 *
	 * @param list<array{amount:float,class:string}> $components
	 * @return array{0:list<array{label:string,rate_id:int,amount:float}>,1:float}
	 */
	private function compute_tax( array $components ): array {
		if ( ! class_exists( '\\WC_Tax' ) ) {
			return [ [], 0.0 ];
		}

		$by_rate_id = [];
		foreach ( $components as $component ) {
			$amount = (float) $component['amount'];
			$class  = (string) $component['class'];
			if ( $amount <= 0 || $class === '' ) {
				continue;
			}
			$wc_class = $class === 'standard' ? '' : $class;
			$rates    = \WC_Tax::find_rates( [ 'tax_class' => $wc_class ] );
			if ( ! $rates ) {
				continue;
			}
			$tax_amounts = \WC_Tax::calc_tax( $amount, $rates, false );
			foreach ( $tax_amounts as $rate_id => $tax_amount ) {
				$rate_id = (int) $rate_id;
				if ( ! isset( $by_rate_id[ $rate_id ] ) ) {
					$by_rate_id[ $rate_id ] = [
						'label'   => (string) \WC_Tax::get_rate_label( $rate_id ),
						'rate_id' => $rate_id,
						'amount'  => 0.0,
					];
				}
				$by_rate_id[ $rate_id ]['amount'] += (float) $tax_amount;
			}
		}

		$breakdown = array_values( $by_rate_id );
		$total     = 0.0;
		foreach ( $breakdown as $row ) {
			$total += $row['amount'];
		}
		return [ $breakdown, round( $total, 2 ) ];
	}

	/**
	 * @return array{0:string,1:float,2:float,3:?string}  [payment_mode, deposit_due, balance_due, balance_due_date|null]
	 */
	private function split_payment( Property $property, DateRange $range, float $total ): array {
		if ( $property->payment_mode() === Property::PAYMENT_FULL ) {
			return [ Property::PAYMENT_FULL, $total, 0.0, null ];
		}

		$lead_days        = $property->balance_due_days_before();
		$balance_due_date = $range->checkin->modify( "-{$lead_days} days" );
		$today            = new DateTimeImmutable( gmdate( 'Y-m-d' ) );

		if ( $balance_due_date < $today->modify( '+2 days' ) ) {
			return [ Property::PAYMENT_FULL, $total, 0.0, null ];
		}

		$deposit_pct  = $property->deposit_pct();
		$deposit_due  = round( $total * ( $deposit_pct / 100 ), 2 );
		$balance_due  = round( $total - $deposit_due, 2 );

		return [ Property::PAYMENT_DEPOSIT, $deposit_due, $balance_due, $balance_due_date->format( 'Y-m-d' ) ];
	}
}
