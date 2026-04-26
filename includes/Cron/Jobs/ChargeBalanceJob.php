<?php
/**
 * One-shot Action Scheduler job: attempts to charge the saved card on file
 * for the second instalment of a deposit-mode booking.
 *
 * Idempotent — `BalanceService::charge()` takes a per-booking lock and skips
 * if the booking is no longer in `balance_pending` state.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Cron\Jobs;

use IBB\Rentals\Services\BalanceService;

defined( 'ABSPATH' ) || exit;

final class ChargeBalanceJob {

	public function __construct(
		private BalanceService $balance,
	) {}

	public function handle( int $booking_id ): void {
		$this->balance->charge( $booking_id );
	}
}
