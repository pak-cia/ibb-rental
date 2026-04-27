<?php
/**
 * Pre-arrival reminder email — HTML version.
 *
 * @var \WC_Email           $email
 * @var string              $email_heading
 * @var array<string,mixed> $booking
 * @var \IBB\Rentals\Domain\Property|null $property
 * @var string              $checkin
 * @var string              $checkout
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php
	printf(
		esc_html__( 'Hi %s,', 'ibb-rentals' ),
		esc_html( explode( ' ', (string) ( $booking['guest_name'] ?? '' ) )[0] )
	);
?></p>

<p><?php
	printf(
		/* translators: %s: property name */
		esc_html__( 'This is a friendly reminder that your stay at %s is coming up soon. Here are your check-in details:', 'ibb-rentals' ),
		'<strong>' . esc_html( get_the_title( (int) ( $booking['property_id'] ?? 0 ) ) ) . '</strong>'
	);
?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;margin-bottom:20px;" border="1">
	<tbody>
		<tr>
			<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;width:35%;">
				<?php esc_html_e( 'Check-in', 'ibb-rentals' ); ?>
			</th>
			<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
				<strong><?php echo esc_html( $checkin ); ?></strong>
				<?php if ( $property && $property->check_in_time() ) : ?>
					<span style="color:#777;"> — <?php echo esc_html(
						sprintf( __( 'from %s', 'ibb-rentals' ), $property->check_in_time() )
					); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;">
				<?php esc_html_e( 'Check-out', 'ibb-rentals' ); ?>
			</th>
			<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
				<?php echo esc_html( $checkout ); ?>
				<?php if ( $property && $property->check_out_time() ) : ?>
					<span style="color:#777;"> — <?php echo esc_html(
						sprintf( __( 'by %s', 'ibb-rentals' ), $property->check_out_time() )
					); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;">
				<?php esc_html_e( 'Guests', 'ibb-rentals' ); ?>
			</th>
			<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
				<?php echo esc_html( (string) ( $booking['guests'] ?? 1 ) ); ?>
			</td>
		</tr>
		<?php if ( ! empty( $booking['balance_due'] ) && (float) $booking['balance_due'] > 0 ) :
			$balance_date = ! empty( $booking['balance_due_date'] )
				? date_i18n( (string) get_option( 'date_format', 'F j, Y' ), strtotime( (string) $booking['balance_due_date'] ) )
				: ''; ?>
		<tr>
			<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;">
				<?php esc_html_e( 'Balance due', 'ibb-rentals' ); ?>
			</th>
			<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
				<?php echo wp_kses_post( wc_price( (float) $booking['balance_due'] ) ); ?>
				<?php if ( $balance_date ) : ?>
					<span style="color:#c00;font-weight:600;"> — <?php echo esc_html(
						sprintf( __( 'due by %s', 'ibb-rentals' ), $balance_date )
					); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>

<p><?php esc_html_e( 'We look forward to welcoming you. If you have any questions before arrival, please don\'t hesitate to reach out.', 'ibb-rentals' ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
