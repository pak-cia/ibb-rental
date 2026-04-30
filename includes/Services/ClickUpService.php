<?php
/**
 * Syncs guest names from a ClickUp Bookings list into wp_ibb_blocks.guest_name.
 *
 * Data source per task (no custom fields needed — ClickUp puts these on the task itself):
 *   - task.start_date (ms)         → check-in date
 *   - task.due_date   (ms)         → check-out date
 *   - task.tags[].name             → OTA source via configured tag→source map
 *   - task.name "Room - Guest"     → guest name (parsed by splitting on " - ")
 *
 * Matching strategy: UPDATE wp_ibb_blocks WHERE start_date = ? AND end_date = ? AND source = ?.
 *
 * Also exposes hierarchy fetchers (workspaces/spaces/folders/lists) used by the
 * cascading-dropdown settings UI to let the admin pick a Bookings list without
 * having to dig list IDs out of ClickUp URLs.
 *
 * ClickUp API v2 docs: https://clickup.com/api/
 */

declare( strict_types=1 );

namespace IBB\Rentals\Services;

use IBB\Rentals\Setup\Schema;
use IBB\Rentals\Support\Logger;

defined( 'ABSPATH' ) || exit;

final class ClickUpService {

	private const API_BASE   = 'https://api.clickup.com/api/v2';
	public  const STATUS_OPT = 'ibb_rentals_clickup_status';

	/** Last error captured during the current sync run (set by api_get on failure). */
	private string $last_error = '';

	/**
	 * @param array<string,string> $tag_source_map     ClickUp tag name (lowercased) → IBB source value
	 * @param array<string,int>    $unit_property_map  Unit code parsed from task title (lowercased) → IBB property ID
	 */
	public function __construct(
		private readonly string $api_token,
		private readonly string $list_id,
		private readonly string $workspace_id,
		private readonly array  $tag_source_map,
		private readonly array  $unit_property_map,
		private readonly Logger $logger,
	) {}

	/**
	 * Pulls all tasks from the configured list and writes guest_name onto matching blocks.
	 * Returns the number of block rows updated.
	 */
	public function sync(): int {
		$this->last_error = '';

		if ( $this->api_token === '' || $this->list_id === '' ) {
			$this->record_status( 0, 0, 'token or list ID not configured' );
			return 0;
		}

		$tasks = $this->fetch_all_tasks();
		if ( empty( $tasks ) ) {
			$this->record_status( 0, 0, $this->last_error );
			return 0;
		}

		global $wpdb;
		$table       = Schema::table( 'blocks' );
		$updated     = 0;
		$matched_uid = 0;
		$matched_dt  = 0;
		$now         = current_time( 'mysql', true );

		foreach ( $tasks as $task ) {
			$task_id     = (string) ( $task['id'] ?? '' );
			$guest_name  = $this->extract_guest_name( $task );
			$booking_id  = $this->extract_booking_id( $task );
			$checkin     = $this->extract_checkin( $task );
			$checkout    = $this->extract_checkout( $task );
			$source      = $this->extract_source( $task );
			$property_id = $this->extract_property_id( $task );

			if ( $guest_name === '' ) {
				continue;
			}

			$set_values = [ $guest_name, $task_id, $now ];

			// ── Strategy 1: match by Booking ID against external_uid ────────
			// More durable than dates: survives stay extensions, source typos, and
			// cross-property collisions. The OTA's iCal UID typically embeds the
			// reservation code (Airbnb HMxxx, Booking.com numeric, etc.); LIKE %code%
			// covers all observed formats.
			if ( $booking_id !== '' ) {
				$rows = (int) $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table}
						    SET guest_name = %s, clickup_task_id = %s, source_override = %s, updated_at = %s
						  WHERE external_uid LIKE %s",
						$set_values[0],
						$set_values[1],
						$source,
						$set_values[2],
						'%' . $wpdb->esc_like( $booking_id ) . '%'
					)
				);
				if ( $rows > 0 ) {
					$updated     += $rows;
					$matched_uid += $rows;
					continue;
				}
			}

			// ── Strategy 2: fallback by dates (+ property when mapped, + source when not) ─
			//
			// When the unit code maps to a property, source becomes redundant — a property
			// can't have two simultaneous bookings, so (property_id, dates) is unique. Dropping
			// the source filter handles the operator workflow where a non-Airbnb booking (e.g.
			// Agoda direct, or a true direct booking) is manually blocked on Airbnb so the
			// Airbnb iCal feed re-imports it as `source='airbnb'` "Airbnb (Not available)".
			//
			// When the unit code is NOT mapped, we keep source as a disambiguator to avoid
			// painting a guest name onto an unrelated block on a different property.
			if ( $checkin === '' || $checkout === '' ) {
				continue;
			}

			$where        = [ 'start_date' => $checkin, 'end_date' => $checkout ];
			$where_format = [ '%s', '%s' ];

			if ( $property_id > 0 ) {
				$where['property_id'] = $property_id;
				$where_format[]       = '%d';
			} elseif ( $source !== '' ) {
				$where['source']  = $source;
				$where_format[]   = '%s';
			} else {
				continue; // no property and no source → can't match safely
			}

			$rows = (int) $wpdb->update(
				$table,
				[
					'guest_name'      => $guest_name,
					'clickup_task_id' => $task_id,
					'source_override' => $source,
					'updated_at'      => $now,
				],
				$where,
				[ '%s', '%s', '%s', '%s' ],
				$where_format
			);

			if ( $rows > 0 ) {
				$updated    += $rows;
				$matched_dt += $rows;
			}
		}

		$this->logger->info(
			"ClickUp sync: updated guest_name on {$updated} block(s) from " . count( $tasks )
			. " task(s) (uid match: {$matched_uid}, date-tuple fallback: {$matched_dt})."
		);
		$this->record_status( $updated, count( $tasks ), $this->last_error );
		return $updated;
	}

	/**
	 * Persist the most recent run's outcome so the Settings page can show a status pill
	 * without going to WC → Status → Logs.
	 */
	private function record_status( int $updated, int $total_tasks, string $error ): void {
		update_option( self::STATUS_OPT, [
			'last_sync_at' => time(),
			'updated'      => $updated,
			'total_tasks'  => $total_tasks,
			'error'        => $error,
		], false );
	}

	// ── Hierarchy fetchers (used by cascading-dropdown settings UI) ───────────

	/**
	 * @return list<array{id:string,name:string}>
	 */
	public function fetch_workspaces(): array {
		$body = $this->api_get( '/team' );
		$out  = [];
		foreach ( (array) ( $body['teams'] ?? [] ) as $team ) {
			$out[] = [ 'id' => (string) ( $team['id'] ?? '' ), 'name' => (string) ( $team['name'] ?? '' ) ];
		}
		return $out;
	}

	/**
	 * @return list<array{id:string,name:string}>
	 */
	public function fetch_spaces( string $workspace_id ): array {
		$body = $this->api_get( '/team/' . rawurlencode( $workspace_id ) . '/space?archived=false' );
		$out  = [];
		foreach ( (array) ( $body['spaces'] ?? [] ) as $space ) {
			$out[] = [ 'id' => (string) ( $space['id'] ?? '' ), 'name' => (string) ( $space['name'] ?? '' ) ];
		}
		return $out;
	}

	/**
	 * Returns folders in the space PLUS any folderless lists wrapped in a synthetic "(No folder)" entry.
	 * Each folder in the result also includes its child lists so the UI can populate the list dropdown
	 * without an extra round-trip when the user picks a folder.
	 *
	 * @return list<array{id:string,name:string,is_folderless?:bool,lists:list<array{id:string,name:string}>}>
	 */
	public function fetch_folders_and_lists( string $space_id ): array {
		$folders_body = $this->api_get( '/space/' . rawurlencode( $space_id ) . '/folder?archived=false' );
		$lists_body   = $this->api_get( '/space/' . rawurlencode( $space_id ) . '/list?archived=false' );

		$out = [];

		// Folderless lists first, as a synthetic "(No folder)" entry — only if there are any.
		$folderless = [];
		foreach ( (array) ( $lists_body['lists'] ?? [] ) as $list ) {
			$folderless[] = [ 'id' => (string) ( $list['id'] ?? '' ), 'name' => (string) ( $list['name'] ?? '' ) ];
		}
		if ( ! empty( $folderless ) ) {
			$out[] = [
				'id'            => '__folderless__',
				'name'          => '(No folder)',
				'is_folderless' => true,
				'lists'         => $folderless,
			];
		}

		foreach ( (array) ( $folders_body['folders'] ?? [] ) as $folder ) {
			$child_lists = [];
			foreach ( (array) ( $folder['lists'] ?? [] ) as $list ) {
				$child_lists[] = [ 'id' => (string) ( $list['id'] ?? '' ), 'name' => (string) ( $list['name'] ?? '' ) ];
			}
			$out[] = [
				'id'    => (string) ( $folder['id'] ?? '' ),
				'name'  => (string) ( $folder['name'] ?? '' ),
				'lists' => $child_lists,
			];
		}

		return $out;
	}

	// ── Sync internals ────────────────────────────────────────────────────────

	/**
	 * Fetches all tasks from the list, paging through results.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function fetch_all_tasks(): array {
		$tasks = [];
		$page  = 0;

		do {
			$query = http_build_query( [
				'include_closed'               => 'true',
				'include_markdown_description' => 'false',
				'subtasks'                     => 'false',
				'page'                         => $page,
			] );
			$body  = $this->api_get( '/list/' . rawurlencode( $this->list_id ) . '/task?' . $query );
			$batch = (array) ( $body['tasks'] ?? [] );

			if ( empty( $batch ) ) {
				break;
			}

			$tasks = array_merge( $tasks, $batch );

			if ( ! empty( $body['last_page'] ) ) {
				break;
			}

			$page++;
		} while ( count( $batch ) >= 100 );

		return $tasks;
	}

	/**
	 * @param array<string,mixed> $task
	 */
	private function extract_guest_name( array $task ): string {
		$name = trim( (string) ( $task['name'] ?? '' ) );

		// "RoomCode - Guest Name" → take everything after the first " - ".
		if ( str_contains( $name, ' - ' ) ) {
			$parts = explode( ' - ', $name, 2 );
			$name  = trim( $parts[1] );
		}

		return sanitize_text_field( $name );
	}

	/**
	 * @param array<string,mixed> $task
	 */
	private function extract_checkin( array $task ): string {
		return $this->ms_to_date( $task['start_date'] ?? null );
	}

	/**
	 * @param array<string,mixed> $task
	 */
	private function extract_checkout( array $task ): string {
		return $this->ms_to_date( $task['due_date'] ?? null );
	}

	/**
	 * Pulls the unit code from the task title (everything before " - ") and looks it up
	 * in the configured map. Returns 0 if no map is configured or no match is found —
	 * the caller falls back to the (start_date, end_date, source) match in that case.
	 *
	 * @param array<string,mixed> $task
	 */
	private function extract_property_id( array $task ): int {
		if ( empty( $this->unit_property_map ) ) {
			return 0;
		}

		$name = trim( (string) ( $task['name'] ?? '' ) );
		if ( ! str_contains( $name, ' - ' ) ) {
			return 0;
		}

		$unit_code = strtolower( trim( explode( ' - ', $name, 2 )[0] ) );
		return (int) ( $this->unit_property_map[ $unit_code ] ?? 0 );
	}

	/**
	 * Pull the OTA reservation code from the task description's table-embed.
	 *
	 * ClickUp's special table format inside `description` looks like:
	 *   [table-embed:1:1 Booking Info | 1:2 abnb | 2:1 Booking ID | 2:2 HMWQACY9JK | …]
	 * Each cell carries an "R:C " prefix and cells are pipe-separated. Find the cell
	 * whose label is "Booking ID" and return the next cell's value.
	 *
	 * @param array<string,mixed> $task
	 */
	private function extract_booking_id( array $task ): string {
		$desc = (string) ( $task['description'] ?? $task['text_content'] ?? '' );
		if ( $desc === '' ) {
			return '';
		}

		$cells = explode( '|', $desc );
		foreach ( $cells as $i => $cell ) {
			// Strip the "R:C " coordinate prefix.
			$label = trim( (string) preg_replace( '/^\s*\d+:\d+\s*/', '', $cell ) );
			if ( strcasecmp( $label, 'Booking ID' ) === 0 && isset( $cells[ $i + 1 ] ) ) {
				$value = trim( (string) preg_replace( '/^\s*\d+:\d+\s*/', '', $cells[ $i + 1 ] ) );
				return $value;
			}
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $task
	 */
	private function extract_source( array $task ): string {
		foreach ( (array) ( $task['tags'] ?? [] ) as $tag ) {
			$tag_name = strtolower( (string) ( $tag['name'] ?? '' ) );
			if ( isset( $this->tag_source_map[ $tag_name ] ) ) {
				return $this->tag_source_map[ $tag_name ];
			}
		}
		return '';
	}

	/**
	 * Convert a ClickUp ms timestamp to a Y-m-d string in the site's timezone.
	 *
	 * Why not gmdate(): ClickUp stores task dates at the user-entered moment in their
	 * workspace's timezone. For e.g. "Apr 30" in a UTC+8 workspace, the stored ms
	 * timestamp is Apr 30 04:00 local = Apr 29 20:00 UTC, so gmdate() rolls back to
	 * the previous calendar day. iCal-imported blocks store dates as plain Y-m-d in
	 * the property's local time, so we have to convert on the same wall-clock basis.
	 */
	private function ms_to_date( mixed $raw ): string {
		if ( $raw === null || $raw === '' ) {
			return '';
		}
		$ts = (int) $raw;
		if ( $ts <= 0 ) {
			return '';
		}
		$dt = new \DateTimeImmutable( '@' . (int) ( $ts / 1000 ) );
		return $dt->setTimezone( wp_timezone() )->format( 'Y-m-d' );
	}

	// ── Low-level HTTP ────────────────────────────────────────────────────────

	/**
	 * GETs a ClickUp API path (relative to /api/v2). Returns the decoded JSON body, or [] on error.
	 *
	 * @return array<string,mixed>
	 */
	private function api_get( string $path ): array {
		if ( $this->api_token === '' ) {
			return [];
		}

		$url      = self::API_BASE . $path;
		$response = wp_safe_remote_get( $url, [
			'timeout' => 15,
			'headers' => [ 'Authorization' => $this->api_token ],
		] );

		if ( is_wp_error( $response ) ) {
			$msg = 'HTTP error: ' . $response->get_error_message();
			$this->last_error = $msg;
			$this->logger->error( 'ClickUp API ' . $msg . ' (' . $path . ')' );
			return [];
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$this->last_error = "HTTP {$code}";
			$this->logger->error( "ClickUp API HTTP {$code} on {$path}" );
			return [];
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded ) ? $decoded : [];
	}
}
