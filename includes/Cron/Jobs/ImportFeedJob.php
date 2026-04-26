<?php
/**
 * Per-feed Action Scheduler handler. Wraps Importer::import() so AS can call
 * `do_action( 'ibb_rentals_import_ical_feed', $feed_id )` and we'll do the right thing.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Cron\Jobs;

use IBB\Rentals\Ical\Importer;

defined( 'ABSPATH' ) || exit;

final class ImportFeedJob {

	public function __construct(
		private Importer $importer,
	) {}

	public function handle( int $feed_id ): void {
		$this->importer->import( $feed_id );
	}
}
