<?php
/**
 * Pre-arrival reminder email — plain-text version.
 *
 * @var \WC_Email           $email
 * @var string              $email_heading
 * @var array<string,mixed> $booking
 * @var \IBB\Rentals\Domain\Property|null $property
 * @var string              $checkin
 * @var string              $checkout
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

printf( esc_html__( 'Hi %s,', 'ibb-rentals' ) . "\n\n", esc_html( (string) ( $booking['guest_name'] ?? '' ) ) );

printf(
	esc_html__( 'Your stay at %s is coming up soon. Here are your check-in details:', 'ibb-rentals' ) . "\n\n",
	esc_html( get_the_title( (int) ( $booking['property_id'] ?? 0 ) ) )
);

$checkin_line  = $checkin;
$checkout_line = $checkout;
if ( $property && $property->check_in_time() )  $checkin_line  .= ' (' . sprintf( esc_html__( 'from %s', 'ibb-rentals' ), $property->check_in_time() ) . ')';
if ( $property && $property->check_out_time() ) $checkout_line .= ' (' . sprintf( esc_html__( 'by %s', 'ibb-rentals' ), $property->check_out_time() ) . ')';

printf( "%-20s %s\n", esc_html__( 'Check-in:', 'ibb-rentals' ),  esc_html( $checkin_line ) );
printf( "%-20s %s\n", esc_html__( 'Check-out:', 'ibb-rentals' ), esc_html( $checkout_line ) );
printf( "%-20s %s\n", esc_html__( 'Guests:', 'ibb-rentals' ),    esc_html( (string) ( $booking['guests'] ?? 1 ) ) );

if ( ! empty( $booking['balance_due'] ) && (float) $booking['balance_due'] > 0 ) {
	$balance_line = strip_tags( wc_price( (float) $booking['balance_due'] ) );
	if ( ! empty( $booking['balance_due_date'] ) ) {
		$balance_line .= ' ' . sprintf(
			esc_html__( '(due by %s)', 'ibb-rentals' ),
			date_i18n( (string) get_option( 'date_format', 'F j, Y' ), strtotime( (string) $booking['balance_due_date'] ) )
		);
	}
	printf( "%-20s %s\n", esc_html__( 'Balance due:', 'ibb-rentals' ), esc_html( $balance_line ) );
}

echo "\n";
$additional = method_exists( $email, 'get_additional_content' ) ? trim( (string) $email->get_additional_content() ) : '';
if ( $additional === '' ) {
	$additional = __( 'Looking forward to your stay! Reach out if you need anything before your arrival.', 'ibb-rentals' );
}
echo esc_html( wp_strip_all_tags( $additional ) );
echo "\n\n";

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
