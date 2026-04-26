<?php
/**
 * Custom WP_List_Table for the bookings admin page.
 *
 * Reads from `wp_ibb_bookings` directly (not via WC orders) so cancelled and
 * refunded rows remain queryable and filterable. The "Order #" column links
 * back to the WC order edit screen.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Admin;

use IBB\Rentals\Setup\Schema;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class BookingsListTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'booking',
			'plural'   => 'bookings',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'id'        => __( 'Booking #', 'ibb-rentals' ),
			'property'  => __( 'Property', 'ibb-rentals' ),
			'guest'     => __( 'Guest', 'ibb-rentals' ),
			'checkin'   => __( 'Check-in', 'ibb-rentals' ),
			'checkout'  => __( 'Check-out', 'ibb-rentals' ),
			'guests'    => __( 'Guests', 'ibb-rentals' ),
			'total'     => __( 'Total', 'ibb-rentals' ),
			'status'    => __( 'Status', 'ibb-rentals' ),
			'order'     => __( 'Order', 'ibb-rentals' ),
		];
	}

	public function get_sortable_columns(): array {
		return [
			'id'       => [ 'id', true ],
			'checkin'  => [ 'checkin', false ],
			'status'   => [ 'status', false ],
		];
	}

	public function prepare_items(): void {
		global $wpdb;

		$per_page = 20;
		$current  = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset   = ( $current - 1 ) * $per_page;

		$orderby  = sanitize_key( (string) ( $_GET['orderby'] ?? 'id' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby  = in_array( $orderby, [ 'id', 'checkin', 'status' ], true ) ? $orderby : 'id';
		$order    = strtoupper( (string) ( $_GET['order'] ?? 'desc' ) ) === 'ASC' ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$status_filter = sanitize_key( (string) ( $_GET['booking_status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$table = Schema::table( 'bookings' );
		$where = $status_filter !== '' ? $wpdb->prepare( 'WHERE status = %s', $status_filter ) : '';

		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$this->items = $rows ?: [];
		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => max( 1, (int) ceil( $total_items / $per_page ) ),
		] );
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	public function column_default( $item, $column_name ): string {
		return (string) ( $item[ $column_name ] ?? '' );
	}

	public function column_id( $item ): string {
		return '#' . (int) $item['id'];
	}

	public function column_property( $item ): string {
		$id    = (int) $item['property_id'];
		$title = get_the_title( $id ) ?: ( '#' . $id );
		$link  = get_edit_post_link( $id );
		return $link ? sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $title ) ) : esc_html( $title );
	}

	public function column_guest( $item ): string {
		return esc_html( (string) $item['guest_name'] ) . '<br><small>' . esc_html( (string) $item['guest_email'] ) . '</small>';
	}

	public function column_total( $item ): string {
		return function_exists( 'wc_price' ) ? wc_price( (float) $item['total'] ) : esc_html( (string) $item['total'] );
	}

	public function column_status( $item ): string {
		$labels = [
			'pending'         => __( 'Pending', 'ibb-rentals' ),
			'confirmed'       => __( 'Confirmed', 'ibb-rentals' ),
			'balance_pending' => __( 'Balance pending', 'ibb-rentals' ),
			'completed'       => __( 'Completed', 'ibb-rentals' ),
			'cancelled'       => __( 'Cancelled', 'ibb-rentals' ),
			'refunded'        => __( 'Refunded', 'ibb-rentals' ),
		];
		$status = (string) $item['status'];
		return '<span class="ibb-status ibb-status--' . esc_attr( $status ) . '">' . esc_html( $labels[ $status ] ?? $status ) . '</span>';
	}

	public function column_order( $item ): string {
		$order_id = (int) $item['order_id'];
		if ( ! $order_id ) {
			return '—';
		}
		$url = function_exists( 'wc_get_order' ) ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '';
		return $url ? sprintf( '<a href="%s">#%d</a>', esc_url( $url ), $order_id ) : ( '#' . $order_id );
	}

	protected function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}
		$current = sanitize_key( (string) ( $_GET['booking_status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="alignleft actions"><select name="booking_status">';
		echo '<option value="">' . esc_html__( 'All statuses', 'ibb-rentals' ) . '</option>';
		foreach ( [ 'pending', 'confirmed', 'balance_pending', 'completed', 'cancelled', 'refunded' ] as $s ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $s ),
				selected( $current, $s, false ),
				esc_html( ucfirst( str_replace( '_', ' ', $s ) ) )
			);
		}
		echo '</select>';
		submit_button( __( 'Filter', 'ibb-rentals' ), '', 'filter_action', false );
		echo '</div>';
	}
}
