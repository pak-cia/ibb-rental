<?php
/**
 * SQL layer over `wp_ibb_bookings`.
 *
 * Booking rows are the canonical record of a confirmed stay — they survive
 * the lifecycle of the WC order and remain queryable for cancellations,
 * balance-due scheduling, reminder emails, and reporting.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Repositories;

use IBB\Rentals\Setup\Schema;

defined( 'ABSPATH' ) || exit;

final class BookingRepository {

	public const STATUS_PENDING        = 'pending';
	public const STATUS_CONFIRMED      = 'confirmed';
	public const STATUS_BALANCE_PENDING= 'balance_pending';
	public const STATUS_COMPLETED      = 'completed';
	public const STATUS_CANCELLED      = 'cancelled';
	public const STATUS_REFUNDED       = 'refunded';

	private \wpdb $db;
	private string $table;

	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;
		$this->db    = $db ?? $wpdb;
		$this->table = Schema::table( 'bookings' );
	}

	/** @param array<string, mixed> $data @return int new booking id */
	public function insert( array $data ): int {
		$now = current_time( 'mysql', true );
		$this->db->insert( $this->table, $data + [
			'created_at' => $now,
			'updated_at' => $now,
		] );
		return (int) $this->db->insert_id;
	}

	/** @param array<string, mixed> $data */
	public function update( int $id, array $data ): void {
		$this->db->update(
			$this->table,
			$data + [ 'updated_at' => current_time( 'mysql', true ) ],
			[ 'id' => $id ]
		);
	}

	/** @return array<string, mixed>|null */
	public function find_by_id( int $id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/** @return list<array<string, mixed>> */
	public function find_by_order( int $order_id ): array {
		$sql = $this->db->prepare(
			"SELECT * FROM {$this->table} WHERE order_id = %d ORDER BY id",
			$order_id
		);
		return $this->db->get_results( $sql, ARRAY_A ) ?: [];
	}

	/** @return list<array<string, mixed>> */
	public function find_by_status( string $status, ?string $on_or_before = null ): array {
		if ( $on_or_before === null ) {
			$sql = $this->db->prepare( "SELECT * FROM {$this->table} WHERE status = %s ORDER BY balance_due_date", $status );
		} else {
			$sql = $this->db->prepare(
				"SELECT * FROM {$this->table} WHERE status = %s AND balance_due_date <= %s ORDER BY balance_due_date",
				$status,
				$on_or_before
			);
		}
		return $this->db->get_results( $sql, ARRAY_A ) ?: [];
	}

	public function update_status( int $id, string $status ): void {
		$this->update( $id, [ 'status' => $status ] );
	}
}
