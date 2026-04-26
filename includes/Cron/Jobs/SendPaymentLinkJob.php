<?php
/**
 * One-shot Action Scheduler job: emails the guest a WC pay-for-order URL
 * to settle the balance manually.
 *
 * Used for non-token-capable gateways (Xendit VA/QRIS, bank transfer, COD)
 * and as the fallback path when an off-session card charge fails.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Cron\Jobs;

use IBB\Rentals\Services\BalanceService;

defined( 'ABSPATH' ) || exit;

final class SendPaymentLinkJob {

	public function __construct(
		private BalanceService $balance,
	) {}

	public function handle( int $booking_id, string $kind = 'first' ): void {
		$this->balance->send_link( $booking_id, $kind );
	}
}
