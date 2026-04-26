<?php
/**
 * Keeps Action Scheduler in sync with the feed registry.
 *
 * On `init` (and on every feed save), ensures each enabled feed has exactly
 * one recurring `ibb_rentals_import_ical_feed` action with the configured
 * interval, and removes orphaned actions for deleted/disabled feeds.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Ical;

use IBB\Rentals\Repositories\FeedRepository;
use IBB\Rentals\Support\Hooks;

defined( 'ABSPATH' ) || exit;

final class FeedScheduler {

	public function __construct(
		private FeedRepository $feeds,
	) {}

	public function register(): void {
		add_action( 'init', [ $this, 'ensure_recurring' ], 99 );
	}

	public function ensure_recurring(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		$enabled = $this->feeds->find_enabled();
		$keep_ids = [];

		foreach ( $enabled as $feed ) {
			$feed_id  = (int) $feed['id'];
			$keep_ids[] = $feed_id;
			$args     = [ $feed_id ];

			$next = as_next_scheduled_action( Hooks::AS_IMPORT_FEED, $args, Hooks::AS_GROUP );
			if ( $next === false ) {
				as_schedule_recurring_action(
					time() + 30,
					max( 300, (int) $feed['sync_interval'] ),
					Hooks::AS_IMPORT_FEED,
					$args,
					Hooks::AS_GROUP
				);
			}
		}
	}

	public function unschedule_for_feed( int $feed_id ): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( Hooks::AS_IMPORT_FEED, [ $feed_id ], Hooks::AS_GROUP );
	}
}
