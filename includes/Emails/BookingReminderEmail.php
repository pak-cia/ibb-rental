<?php
/**
 * WooCommerce email: pre-arrival reminder sent 3 days before check-in.
 *
 * Triggered by ReminderEmailJob (Action Scheduler one-shot scheduled at
 * booking time). Appears in WooCommerce → Settings → Emails so the site
 * owner can toggle it or edit the subject/heading.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Emails;

use IBB\Rentals\Domain\Property;
use IBB\Rentals\Repositories\BookingRepository;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\WC_Email' ) ) {
	return;
}

class BookingReminderEmail extends \WC_Email {

	public function __construct() {
		$this->id             = 'ibb_booking_reminder';
		$this->customer_email = true;
		$this->title          = __( 'IBB – Pre-arrival Reminder', 'ibb-rentals' );
		$this->description    = __( 'Sent to the guest 3 days before check-in.', 'ibb-rentals' );
		$this->heading        = __( 'Your stay is coming up', 'ibb-rentals' );
		$this->subject        = __( '[{site_title}] Reminder: your stay at {property_title} begins {checkin}', 'ibb-rentals' );
		$this->template_html  = 'emails/booking-reminder.php';
		$this->template_plain = 'emails/booking-reminder-plain.php';
		$this->template_base  = IBB_RENTALS_DIR . 'templates/';

		$this->placeholders = array_merge( $this->placeholders, [
			'{property_title}' => '',
			'{checkin}'        => '',
		] );

		parent::__construct();
	}

	public function trigger( int $booking_id ): void {
		$this->setup_locale();

		$booking = ( new BookingRepository() )->find_by_id( $booking_id );
		if ( ! $booking ) {
			$this->restore_locale();
			return;
		}

		// Don't send if the booking was cancelled after scheduling.
		if ( in_array( (string) $booking['status'], [ BookingRepository::STATUS_CANCELLED, BookingRepository::STATUS_REFUNDED ], true ) ) {
			$this->restore_locale();
			return;
		}

		$this->object    = $booking;
		$this->recipient = (string) $booking['guest_email'];

		$date_fmt = (string) get_option( 'date_format', 'F j, Y' );
		$this->placeholders['{property_title}'] = (string) get_the_title( (int) $booking['property_id'] );
		$this->placeholders['{checkin}']        = date_i18n( $date_fmt, strtotime( (string) $booking['checkin'] ) );

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);
		}

		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			$this->get_template_vars( false ),
			'',
			$this->template_base
		);
	}

	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			$this->get_template_vars( true ),
			'',
			$this->template_base
		);
	}

	public function get_default_subject(): string {
		return __( '[{site_title}] Reminder: your stay at {property_title} begins {checkin}', 'ibb-rentals' );
	}

	public function get_default_heading(): string {
		return __( 'Your stay is coming up', 'ibb-rentals' );
	}

	/** @return array<string, mixed> */
	private function get_template_vars( bool $plain_text ): array {
		$booking  = $this->object ?: [];
		$property = ! empty( $booking['property_id'] )
			? Property::from_id( (int) $booking['property_id'] )
			: null;
		$date_fmt     = (string) get_option( 'date_format', 'F j, Y' );
		$checkin_fmt  = ! empty( $booking['checkin'] )
			? date_i18n( $date_fmt, strtotime( (string) $booking['checkin'] ) )
			: '';
		$checkout_fmt = ! empty( $booking['checkout'] )
			? date_i18n( $date_fmt, strtotime( (string) $booking['checkout'] ) )
			: '';

		return [
			'email'         => $this,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => $plain_text,
			'blogname'      => $this->get_blogname(),
			'booking'       => $booking,
			'property'      => $property,
			'checkin'       => $checkin_fmt,
			'checkout'      => $checkout_fmt,
		];
	}
}
