<?php
/**
 * Action Scheduler job: send the pre-arrival reminder email.
 *
 * Scheduled as a one-shot action at check-in minus 3 days, at 09:00 site
 * timezone. The email class re-checks booking status before sending so
 * cancellations that happen after scheduling are handled gracefully.
 *
 * Hook: `ibb_rentals_send_reminder` (Hooks::AS_SEND_REMINDER)
 * Args: [ $booking_id (int) ]
 */

declare( strict_types=1 );

namespace IBB\Rentals\Cron\Jobs;

use IBB\Rentals\Emails\BookingReminderEmail;

defined( 'ABSPATH' ) || exit;

final class ReminderEmailJob {

	public function handle( int $booking_id ): void {
		if ( ! class_exists( '\\WC_Email' ) ) {
			return;
		}
		( new BookingReminderEmail() )->trigger( $booking_id );
	}
}
