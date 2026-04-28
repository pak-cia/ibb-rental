<?php
/**
 * WooCommerce outgoing webhook topics for third-party automation (n8n, Odoo, etc.).
 *
 * Registers three custom topics so admins can create WC webhooks at
 * WooCommerce → Settings → Advanced → Webhooks without any custom endpoint
 * or auth setup on our side.
 *
 * Topics:
 *   ibb_rentals.booking.created   — fires on ibb-rentals/booking/created
 *   ibb_rentals.booking.cancelled — fires on ibb-rentals/booking/cancelled
 *   ibb_rentals.balance.charged   — fires on ibb-rentals/balance/charged
 *
 * All three actions pass an integer booking ID as their first argument, which
 * WC's webhook processor accepts. The payload filter fetches the full booking
 * row and returns it as the JSON body delivered to the endpoint URL.
 *
 * Note on topic naming: WC uses dot notation (resource.event). Using
 * `ibb_rentals` (underscores) as the resource prefix avoids any slash-parsing
 * edge-cases in WC's topic string manipulation.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Woo;

use IBB\Rentals\Repositories\BookingRepository;

defined( 'ABSPATH' ) || exit;

final class WebhookTopics {

	private BookingRepository $bookings;

	public function __construct( BookingRepository $bookings ) {
		$this->bookings = $bookings;
	}

	public function register(): void {
		add_filter( 'woocommerce_webhook_topics',      [ $this, 'add_topics' ] );
		add_filter( 'woocommerce_webhook_topic_hooks', [ $this, 'add_topic_hooks' ], 10, 2 );
		add_filter( 'woocommerce_webhook_payload',     [ $this, 'build_payload' ], 10, 4 );
	}

	/**
	 * Adds the three topics to WC's webhook topic dropdown.
	 *
	 * @param array<string,string> $topics
	 * @return array<string,string>
	 */
	public function add_topics( array $topics ): array {
		$topics['ibb_rentals.booking.created']   = __( 'IBB — Booking created', 'ibb-rentals' );
		$topics['ibb_rentals.booking.cancelled'] = __( 'IBB — Booking cancelled', 'ibb-rentals' );
		$topics['ibb_rentals.balance.charged']   = __( 'IBB — Balance charged', 'ibb-rentals' );
		return $topics;
	}

	/**
	 * Maps each topic to the WP action hook that triggers it.
	 *
	 * @param array<string,list<string>> $topic_hooks
	 * @param \WC_Webhook                $webhook
	 * @return array<string,list<string>>
	 */
	public function add_topic_hooks( array $topic_hooks, \WC_Webhook $webhook ): array {
		$topic_hooks['ibb_rentals.booking.created']   = [ 'ibb-rentals/booking/created' ];
		$topic_hooks['ibb_rentals.booking.cancelled'] = [ 'ibb-rentals/booking/cancelled' ];
		$topic_hooks['ibb_rentals.balance.charged']   = [ 'ibb-rentals/balance/charged' ];
		return $topic_hooks;
	}

	/**
	 * Builds the JSON payload for IBB topics.
	 *
	 * WC passes the first argument of the fired action as `$resource_id`
	 * (an integer booking ID for all three hooks). We fetch the booking row
	 * and return it so the receiving endpoint gets the full context.
	 *
	 * @param array<string,mixed> $payload
	 * @param string              $resource     Resource part of the topic (e.g. 'ibb_rentals').
	 * @param mixed               $resource_id  First arg from the action — booking ID (int).
	 * @param int                 $webhook_id
	 * @return array<string,mixed>
	 */
	public function build_payload( array $payload, string $resource, $resource_id, int $webhook_id ): array {
		if ( $resource !== 'ibb_rentals' ) {
			return $payload;
		}

		$booking_id = (int) $resource_id;
		if ( $booking_id <= 0 ) {
			return $payload;
		}

		$row = $this->bookings->find_by_id( $booking_id );
		return $row ?? [ 'id' => $booking_id ];
	}
}
