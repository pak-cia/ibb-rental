<?php
/**
 * SQL layer over `wp_ibb_blocks`.
 *
 * Hot path: `find_overlapping()` runs on every quote request, every
 * add-to-cart, and every checkout submission, so the query is hand-tuned to
 * use the `(property_id, start_date, end_date)` compound index. Half-open
 * interval semantics: turnover days (checkin == previous checkout) are NOT
 * an overlap.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Repositories;

use IBB\Rentals\Domain\Block;
use IBB\Rentals\Domain\DateRange;
use IBB\Rentals\Setup\Schema;

defined( 'ABSPATH' ) || exit;

final class AvailabilityRepository {

	private \wpdb $db;
	private string $table;

	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;
		$this->db    = $db ?? $wpdb;
		$this->table = Schema::table( 'blocks' );
	}

	/**
	 * Returns active blocks (confirmed or tentative) that overlap the range.
	 *
	 * @return list<Block>
	 */
	public function find_overlapping( int $property_id, DateRange $range, ?string $exclude_source = null ): array {
		$sql = $this->db->prepare(
			"SELECT * FROM {$this->table}
			 WHERE property_id = %d
			   AND status IN ('confirmed','tentative')
			   AND start_date < %s
			   AND end_date   > %s",
			$property_id,
			$range->checkout_string(),
			$range->checkin_string()
		);

		if ( $exclude_source !== null ) {
			$sql .= $this->db->prepare( ' AND source <> %s', $exclude_source );
		}

		$rows = $this->db->get_results( $sql, ARRAY_A );
		return $this->hydrate( $rows );
	}

	public function any_overlap( int $property_id, DateRange $range ): bool {
		$sql = $this->db->prepare(
			"SELECT 1 FROM {$this->table}
			 WHERE property_id = %d
			   AND status IN ('confirmed','tentative')
			   AND start_date < %s
			   AND end_date   > %s
			 LIMIT 1",
			$property_id,
			$range->checkout_string(),
			$range->checkin_string()
		);
		return (bool) $this->db->get_var( $sql );
	}

	/**
	 * @return list<Block>
	 */
	public function find_in_window( int $property_id, DateRange $window ): array {
		$sql = $this->db->prepare(
			"SELECT * FROM {$this->table}
			 WHERE property_id = %d
			   AND status IN ('confirmed','tentative')
			   AND start_date < %s
			   AND end_date   > %s
			 ORDER BY start_date",
			$property_id,
			$window->checkout_string(),
			$window->checkin_string()
		);
		return $this->hydrate( $this->db->get_results( $sql, ARRAY_A ) );
	}

	/**
	 * @return list<Block>
	 */
	public function find_exportable( int $property_id ): array {
		$sql = $this->db->prepare(
			"SELECT * FROM {$this->table}
			 WHERE property_id = %d
			   AND status = 'confirmed'
			   AND source IN ('direct','manual')
			 ORDER BY start_date",
			$property_id
		);
		return $this->hydrate( $this->db->get_results( $sql, ARRAY_A ) );
	}

	public function find_by_id( int $id ): ?Block {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);
		return $row ? Block::from_row( $row ) : null;
	}

	public function find_by_uid( int $property_id, string $source, string $uid ): ?Block {
		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE property_id = %d AND source = %s AND external_uid = %s",
				$property_id,
				$source,
				$uid
			),
			ARRAY_A
		);
		return $row ? Block::from_row( $row ) : null;
	}

	public function insert( Block $block ): int {
		$now = current_time( 'mysql', true );
		$this->db->insert( $this->table, $block->to_row() + [
			'created_at' => $now,
			'updated_at' => $now,
		] );
		return (int) $this->db->insert_id;
	}

	/**
	 * Insert-or-update keyed by `(property_id, source, external_uid)`.
	 * Used by the iCal importer to make sync idempotent.
	 *
	 * @return int  the row id (existing or newly inserted)
	 */
	public function upsert_by_uid( Block $block ): int {
		$existing = $this->find_by_uid( $block->property_id, $block->source, $block->external_uid );
		$now      = current_time( 'mysql', true );
		$row      = $block->to_row();

		if ( $existing && $existing->id ) {
			$this->db->update(
				$this->table,
				$row + [ 'updated_at' => $now ],
				[ 'id' => $existing->id ]
			);
			return $existing->id;
		}

		$this->db->insert( $this->table, $row + [ 'created_at' => $now, 'updated_at' => $now ] );
		return (int) $this->db->insert_id;
	}

	public function update_status( int $id, string $status ): void {
		$this->db->update(
			$this->table,
			[ 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ],
			[ 'id' => $id ]
		);
	}

	public function delete_by_id( int $id ): void {
		$this->db->delete( $this->table, [ 'id' => $id ] );
	}

	/**
	 * Delete all rows for a (property, source) pair whose external_uid is NOT in the given set.
	 * Used by the iCal importer to remove cancellations from an OTA feed.
	 *
	 * @param list<string> $keep_uids
	 */
	public function delete_stale_by_source( int $property_id, string $source, array $keep_uids ): int {
		if ( empty( $keep_uids ) ) {
			$sql = $this->db->prepare(
				"DELETE FROM {$this->table} WHERE property_id = %d AND source = %s",
				$property_id,
				$source
			);
		} else {
			$placeholders = implode( ',', array_fill( 0, count( $keep_uids ), '%s' ) );
			$sql = $this->db->prepare(
				"DELETE FROM {$this->table}
				 WHERE property_id = %d AND source = %s
				   AND external_uid NOT IN ({$placeholders})",
				array_merge( [ $property_id, $source ], $keep_uids )
			);
		}
		$this->db->query( $sql );
		return (int) $this->db->rows_affected;
	}

	public function delete_expired_holds( int $older_than_minutes = 15 ): int {
		$threshold = gmdate( 'Y-m-d H:i:s', time() - $older_than_minutes * 60 );
		$this->db->query(
			$this->db->prepare(
				"DELETE FROM {$this->table} WHERE source = %s AND created_at < %s",
				Block::SOURCE_HOLD,
				$threshold
			)
		);
		return (int) $this->db->rows_affected;
	}

	/**
	 * @param array<int, array<string, mixed>>|null $rows
	 * @return list<Block>
	 */
	private function hydrate( ?array $rows ): array {
		if ( ! $rows ) {
			return [];
		}
		return array_values( array_map( [ Block::class, 'from_row' ], $rows ) );
	}
}
