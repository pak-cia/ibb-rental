<?php
/**
 * WooCommerce email: booking confirmation sent to the guest.
 *
 * Triggered on `ibb-rentals/booking/created`. Appears in
 * WooCommerce → Settings → Emails so the site owner can toggle it,
 * edit subject / heading, and see a live preview.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Emails;

use IBB\Rentals\Domain\Property;
use IBB\Rentals\Repositories\BookingRepository;
use IBB\Rentals\Support\Hooks;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\WC_Email' ) ) {
	return;
}

class BookingConfirmationEmail extends \WC_Email {

	public function __construct() {
		$this->id             = 'ibb_booking_confirmation';
		$this->customer_email = true;
		$this->title          = __( 'IBB – Booking Confirmation', 'ibb-rentals' );
		$this->description    = __( 'Sent to the guest immediately after a booking is confirmed.', 'ibb-rentals' );
		$this->heading        = __( 'Your booking is confirmed', 'ibb-rentals' );
		$this->subject        = __( '[{site_title}] Booking confirmed – {property_title} ({checkin} → {checkout})', 'ibb-rentals' );
		$this->template_html  = 'emails/booking-confirmation.php';
		$this->template_plain = 'emails/booking-confirmation-plain.php';
		$this->template_base  = IBB_RENTALS_DIR . 'templates/';

		$this->placeholders = array_merge( $this->placeholders, [
			'{property_title}' => '',
			'{checkin}'        => '',
			'{checkout}'       => '',
		] );

		parent::__construct();

		add_action( Hooks::BOOKING_CREATED, [ $this, 'trigger' ], 20, 4 );
	}

	public function trigger( int $booking_id, \WC_Order $order, \WC_Order_Item_Product $item, string $payment_mode ): void {
		$this->setup_locale();

		$booking = ( new BookingRepository() )->find_by_id( $booking_id );
		if ( ! $booking ) {
			$this->restore_locale();
			return;
		}

		$this->object    = $booking;
		$this->recipient = (string) $booking['guest_email'];

		$date_fmt = (string) get_option( 'date_format', 'F j, Y' );
		$this->placeholders['{property_title}'] = (string) get_the_title( (int) $booking['property_id'] );
		$this->placeholders['{checkin}']        = date_i18n( $date_fmt, strtotime( (string) $booking['checkin'] ) );
		$this->placeholders['{checkout}']       = date_i18n( $date_fmt, strtotime( (string) $booking['checkout'] ) );

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
		return __( '[{site_title}] Booking confirmed – {property_title} ({checkin} → {checkout})', 'ibb-rentals' );
	}

	public function get_default_heading(): string {
		return __( 'Your booking is confirmed', 'ibb-rentals' );
	}

	/** @return array<string, mixed> */
	private function get_template_vars( bool $plain_text ): array {
		$booking  = $this->object ?: [];
		$property = ! empty( $booking['property_id'] )
			? Property::from_id( (int) $booking['property_id'] )
			: null;
		$order    = ! empty( $booking['order_id'] )
			? wc_get_order( (int) $booking['order_id'] )
			: null;

		$date_fmt     = (string) get_option( 'date_format', 'F j, Y' );
		$checkin_fmt  = ! empty( $booking['checkin'] )
			? date_i18n( $date_fmt, strtotime( (string) $booking['checkin'] ) )
			: '';
		$checkout_fmt = ! empty( $booking['checkout'] )
			? date_i18n( $date_fmt, strtotime( (string) $booking['checkout'] ) )
			: '';

		$nights = ( ! empty( $booking['checkin'] ) && ! empty( $booking['checkout'] ) )
			? (int) ( new \DateTime( (string) $booking['checkin'] ) )
				->diff( new \DateTime( (string) $booking['checkout'] ) )
				->days
			: 0;

		return [
			'email'         => $this,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => $plain_text,
			'blogname'      => $this->get_blogname(),
			'booking'       => $booking,
			'property'      => $property,
			'order'         => $order,
			'checkin'       => $checkin_fmt,
			'checkout'      => $checkout_fmt,
			'nights'        => $nights,
		];
	}
}
