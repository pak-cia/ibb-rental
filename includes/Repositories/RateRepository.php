<?php
/**
 * SQL layer over `wp_ibb_rates`.
 *
 * Rate rows define seasonal/date-range overrides on top of a property's base
 * nightly rate. On overlap, `priority DESC, id DESC` wins — so a 3-day flash
 * promo with priority=100 beats a "high season" rate at priority=10 covering
 * the same dates.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Repositories;

use IBB\Rentals\Setup\Schema;

defined( 'ABSPATH' ) || exit;

final class RateRepository {

	private \wpdb $db;
	private string $table;

	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;
		$this->db    = $db ?? $wpdb;
		$this->table = Schema::table( 'rates' );
	}

	/**
	 * Returns rate rows that cover any portion of [start_date, end_date),
	 * ordered by priority desc — caller picks the first match for each night.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function find_for_window( int $property_id, string $start_date, string $end_date ): array {
		$sql = $this->db->prepare(
			"SELECT * FROM {$this->table}
			 WHERE property_id = %d
			   AND date_from <= %s
			   AND date_to   >= %s
			 ORDER BY priority DESC, id DESC",
			$property_id,
			$end_date,
			$start_date
		);
		$rows = $this->db->get_results( $sql, ARRAY_A );
		return $rows ?: [];
	}

	/** @return list<array<string, mixed>> */
	public function find_for_property( int $property_id ): array {
		$sql = $this->db->prepare(
			"SELECT * FROM {$this->table} WHERE property_id = %d ORDER BY date_from",
			$property_id
		);
		return $this->db->get_results( $sql, ARRAY_A ) ?: [];
	}

	/** @param array<string, mixed> $data */
	public function insert( array $data ): int {
		$now = current_time( 'mysql', true );
		$this->db->insert( $this->table, $data + [ 'created_at' => $now, 'updated_at' => $now ] );
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

	public function delete( int $id ): void {
		$this->db->delete( $this->table, [ 'id' => $id ] );
	}
}
