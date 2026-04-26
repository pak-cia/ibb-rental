<?php
/**
 * SQL layer over `wp_ibb_ical_feeds` — the registry of OTA iCal URLs we poll.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Repositories;

use IBB\Rentals\Setup\Schema;

defined( 'ABSPATH' ) || exit;

final class FeedRepository {

	private \wpdb $db;
	private string $table;

	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;
		$this->db    = $db ?? $wpdb;
		$this->table = Schema::table( 'ical_feeds' );
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
	public function find_for_property( int $property_id ): array {
		$sql = $this->db->prepare(
			"SELECT * FROM {$this->table} WHERE property_id = %d ORDER BY id",
			$property_id
		);
		return $this->db->get_results( $sql, ARRAY_A ) ?: [];
	}

	/** @return list<array<string, mixed>> */
	public function find_enabled(): array {
		$sql = "SELECT * FROM {$this->table} WHERE enabled = 1 ORDER BY id";
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

	public function record_success( int $id, string $etag, string $last_modified ): void {
		$this->update( $id, [
			'last_synced_at' => current_time( 'mysql', true ),
			'last_status'    => 'ok',
			'last_error'     => '',
			'etag'           => $etag,
			'last_modified'  => $last_modified,
			'failure_count'  => 0,
		] );
	}

	public function record_failure( int $id, string $error ): void {
		$row = $this->find_by_id( $id );
		$failures = $row ? (int) $row['failure_count'] + 1 : 1;
		$this->update( $id, [
			'last_synced_at' => current_time( 'mysql', true ),
			'last_status'    => 'error',
			'last_error'     => $error,
			'failure_count'  => $failures,
		] );
	}
}
