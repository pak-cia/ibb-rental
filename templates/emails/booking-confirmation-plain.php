<?php
/**
 * Booking confirmation email — plain-text version.
 *
 * @var \WC_Email           $email
 * @var string              $email_heading
 * @var array<string,mixed> $booking
 * @var \IBB\Rentals\Domain\Property|null $property
 * @var \WC_Order|null      $order
 * @var string              $checkin
 * @var string              $checkout
 * @var int                 $nights
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

printf(
	/* translators: %s: guest name */
	esc_html__( 'Hi %s,', 'ibb-rentals' ) . "\n\n",
	esc_html( (string) ( $booking['guest_name'] ?? '' ) )
);

esc_html_e( 'Your booking is confirmed. Here are your stay details:', 'ibb-rentals' );
echo "\n\n";

printf( "%-20s %s\n", esc_html__( 'Property:', 'ibb-rentals' ), esc_html( get_the_title( (int) ( $booking['property_id'] ?? 0 ) ) ) );

$checkin_line  = $checkin;
$checkout_line = $checkout;
if ( $property && $property->check_in_time() ) {
	$checkin_line .= sprintf( ' (%s %s)', esc_html__( 'from', 'ibb-rentals' ), $property->check_in_time() );
}
if ( $property && $property->check_out_time() ) {
	$checkout_line .= sprintf( ' (%s %s)', esc_html__( 'by', 'ibb-rentals' ), $property->check_out_time() );
}
printf( "%-20s %s\n", esc_html__( 'Check-in:', 'ibb-rentals' ), esc_html( $checkin_line ) );
printf( "%-20s %s\n", esc_html__( 'Check-out:', 'ibb-rentals' ), esc_html( $checkout_line ) );
printf( "%-20s %s\n", esc_html__( 'Duration:', 'ibb-rentals' ), esc_html( sprintf( _n( '%d night', '%d nights', $nights, 'ibb-rentals' ), $nights ) ) );
printf( "%-20s %s\n", esc_html__( 'Guests:', 'ibb-rentals' ), esc_html( (string) ( $booking['guests'] ?? 1 ) ) );

echo "\n" . esc_html__( '--- Payment ---', 'ibb-rentals' ) . "\n\n";

$payment_mode = isset( $booking['balance_due'] ) && (float) $booking['balance_due'] > 0 ? 'deposit' : 'full';
$total        = isset( $booking['total'] ) ? strip_tags( wc_price( (float) $booking['total'] ) ) : '';

printf( "%-20s %s\n", esc_html__( 'Total:', 'ibb-rentals' ), esc_html( $total ) );

if ( $payment_mode === 'deposit' ) {
	$deposit = isset( $booking['deposit_paid'] ) ? strip_tags( wc_price( (float) $booking['deposit_paid'] ) ) : '';
	$balance = isset( $booking['balance_due'] ) ? strip_tags( wc_price( (float) $booking['balance_due'] ) ) : '';
	printf( "%-20s %s\n", esc_html__( 'Paid now:', 'ibb-rentals' ), esc_html( $deposit ) );
	$balance_line = $balance;
	if ( ! empty( $booking['balance_due_date'] ) ) {
		$balance_line .= ' ' . sprintf(
			esc_html__( '(due by %s)', 'ibb-rentals' ),
			date_i18n( (string) get_option( 'date_format', 'F j, Y' ), strtotime( (string) $booking['balance_due_date'] ) )
		);
	}
	printf( "%-20s %s\n", esc_html__( 'Balance due:', 'ibb-rentals' ), esc_html( $balance_line ) );
} else {
	printf( "%-20s %s\n", esc_html__( 'Status:', 'ibb-rentals' ), esc_html__( 'Paid in full', 'ibb-rentals' ) );
}

if ( $order ) {
	printf( "%-20s #%s\n", esc_html__( 'Order:', 'ibb-rentals' ), esc_html( (string) $order->get_order_number() ) );
	printf( "%-20s %s\n", '', esc_url( $order->get_view_order_url() ) );
}

echo "\n";
$additional = method_exists( $email, 'get_additional_content' ) ? trim( (string) $email->get_additional_content() ) : '';
if ( $additional === '' ) {
	$additional = __( 'We look forward to welcoming you. If you have any questions before your arrival please reply to this email.', 'ibb-rentals' );
}
echo esc_html( wp_strip_all_tags( $additional ) );
echo "\n\n";

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
