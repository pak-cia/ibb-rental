<?php
/**
 * Action Scheduler handler for `ibb_rentals_sync_clickup`.
 * Delegates to ClickUpService::sync() and logs the result.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Cron\Jobs;

use IBB\Rentals\Services\ClickUpService;

defined( 'ABSPATH' ) || exit;

final class SyncClickUpJob {

	public function __construct(
		private readonly ClickUpService $service,
	) {}

	public function handle(): void {
		$this->service->sync();
	}
}
