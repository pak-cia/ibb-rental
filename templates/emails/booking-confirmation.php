<?php
/**
 * Booking confirmation email — HTML version.
 *
 * Variables available (set by BookingConfirmationEmail::get_template_vars()):
 * @var \WC_Email           $email
 * @var string              $email_heading
 * @var array<string,mixed> $booking
 * @var \IBB\Rentals\Domain\Property|null $property
 * @var \WC_Order|null      $order
 * @var string              $checkin
 * @var string              $checkout
 * @var int                 $nights
 * @var string              $blogname
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php
	printf(
		/* translators: %s: guest first name */
		esc_html__( 'Hi %s,', 'ibb-rentals' ),
		esc_html( explode( ' ', (string) ( $booking['guest_name'] ?? '' ) )[0] )
	);
?></p>

<p><?php esc_html_e( 'Your booking is confirmed. Here are your stay details:', 'ibb-rentals' ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;margin-bottom:20px;" border="1">
	<tbody>
		<tr>
			<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;width:35%;">
				<?php esc_html_e( 'Property', 'ibb-rentals' ); ?>
			</th>
			<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
				<?php echo esc_html( get_the_title( (int) ( $booking['property_id'] ?? 0 ) ) ); ?>
			</td>
		</tr>
		<tr>
			<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;">
				<?php esc_html_e( 'Check-in', 'ibb-rentals' ); ?>
			</th>
			<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
				<?php echo esc_html( $checkin ); ?>
				<?php if ( $property && $property->check_in_time() ) : ?>
					<span style="color:#777;font-size:.9em;">
						<?php
						echo ' — ' . esc_html(
							sprintf(
								/* translators: %s: check-in time e.g. 15:00 */
								__( 'from %s', 'ibb-rentals' ),
								$property->check_in_time()
							)
						);
						?>
					</span>
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
					<span style="color:#777;font-size:.9em;">
						<?php
						echo ' — ' . esc_html(
							sprintf(
								/* translators: %s: check-out time e.g. 11:00 */
								__( 'by %s', 'ibb-rentals' ),
								$property->check_out_time()
							)
						);
						?>
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;">
				<?php esc_html_e( 'Duration', 'ibb-rentals' ); ?>
			</th>
			<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
				<?php echo esc_html( sprintf( _n( '%d night', '%d nights', $nights, 'ibb-rentals' ), $nights ) ); ?>
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
	</tbody>
</table>

<?php /* ── Payment summary ─────────────────────────────────────────── */ ?>
<h2 style="color:#333;font-family:inherit;font-size:18px;font-weight:bold;margin:0 0 12px;">
	<?php esc_html_e( 'Payment', 'ibb-rentals' ); ?>
</h2>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;margin-bottom:20px;" border="1">
	<tbody>
		<?php
		$payment_mode  = isset( $booking['balance_due'] ) && (float) $booking['balance_due'] > 0 ? 'deposit' : 'full';
		$total         = isset( $booking['total'] ) ? wc_price( (float) $booking['total'] ) : '';
		$deposit_paid  = isset( $booking['deposit_paid'] ) ? wc_price( (float) $booking['deposit_paid'] ) : '';
		$balance_due   = isset( $booking['balance_due'] ) ? (float) $booking['balance_due'] : 0.0;
		$balance_date  = ! empty( $booking['balance_due_date'] )
			? date_i18n( (string) get_option( 'date_format', 'F j, Y' ), strtotime( (string) $booking['balance_due_date'] ) )
			: '';
		?>
		<tr>
			<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;width:35%;">
				<?php esc_html_e( 'Total', 'ibb-rentals' ); ?>
			</th>
			<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
				<?php echo wp_kses_post( $total ); ?>
			</td>
		</tr>
		<?php if ( $payment_mode === 'deposit' ) : ?>
			<tr>
				<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;">
					<?php esc_html_e( 'Paid now (deposit)', 'ibb-rentals' ); ?>
				</th>
				<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
					<?php echo wp_kses_post( $deposit_paid ); ?>
				</td>
			</tr>
			<tr>
				<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;">
					<?php esc_html_e( 'Balance due', 'ibb-rentals' ); ?>
				</th>
				<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
					<?php echo wp_kses_post( wc_price( $balance_due ) ); ?>
					<?php if ( $balance_date ) : ?>
						<span style="color:#777;font-size:.9em;">
							<?php
							echo ' — ' . esc_html(
								sprintf(
									/* translators: %s: date */
									__( 'due by %s', 'ibb-rentals' ),
									$balance_date
								)
							);
							?>
						</span>
					<?php endif; ?>
				</td>
			</tr>
		<?php else : ?>
			<tr>
				<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;">
					<?php esc_html_e( 'Status', 'ibb-rentals' ); ?>
				</th>
				<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
					<?php esc_html_e( 'Paid in full', 'ibb-rentals' ); ?>
				</td>
			</tr>
		<?php endif; ?>
		<?php if ( $order ) : ?>
			<tr>
				<th style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;background:#f8f8f8;">
					<?php esc_html_e( 'Order', 'ibb-rentals' ); ?>
				</th>
				<td style="text-align:left;border:1px solid #e5e5e5;padding:8px 12px;">
					<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
						#<?php echo esc_html( (string) $order->get_order_number() ); ?>
					</a>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<p><?php esc_html_e( 'We look forward to welcoming you. If you have any questions before your arrival please reply to this email.', 'ibb-rentals' ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
