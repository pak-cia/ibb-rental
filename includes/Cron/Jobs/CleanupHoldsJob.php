<?php
/**
 * Recurring Action Scheduler job that purges expired `source='hold'` blocks.
 *
 * Holds are inserted at checkout submission to lock dates for ~15 minutes
 * (configurable). If the order never completes, this job sweeps them out so
 * the dates become bookable again.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Cron\Jobs;

use IBB\Rentals\Repositories\AvailabilityRepository;

defined( 'ABSPATH' ) || exit;

final class CleanupHoldsJob {

	public function __construct(
		private AvailabilityRepository $blocks,
	) {}

	public function handle(): void {
		$settings = get_option( 'ibb_rentals_settings', [] );
		$minutes  = is_array( $settings ) && isset( $settings['cart_hold_minutes'] )
			? (int) $settings['cart_hold_minutes']
			: 15;
		$this->blocks->delete_expired_holds( max( 1, $minutes ) );
	}
}
