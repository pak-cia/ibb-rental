<?php
/**
 * Top-level "Rentals" admin menu and its submenu pages.
 *
 * The CPT registers itself as the menu's primary entry (Properties listing).
 * We attach the Bookings, Feeds, and Settings pages as submenus underneath
 * via `add_submenu_page` against the CPT slug.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Admin;

use IBB\Rentals\Plugin;
use IBB\Rentals\PostTypes\PropertyPostType;
use IBB\Rentals\Repositories\FeedRepository;
use IBB\Rentals\Services\ClickUpService;
use IBB\Rentals\Support\Hooks;
use IBB\Rentals\Woo\GatewayCapabilities;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const PARENT = 'edit.php?post_type=' . PropertyPostType::POST_TYPE;

	public const PAGE_BOOKINGS = 'ibb-rentals-bookings';
	public const PAGE_FEEDS    = 'ibb-rentals-feeds';
	public const PAGE_SETTINGS = 'ibb-rentals-settings';

	public function __construct(
		private FeedRepository $feeds,
		private GatewayCapabilities $gateway_caps,
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_pages' ], 20 );
		add_action( 'admin_init', [ $this, 'maybe_save_settings' ] );
		add_action( 'admin_post_ibb_rentals_add_feed',      [ $this, 'handle_add_feed' ] );
		add_action( 'admin_post_ibb_rentals_delete_feed',   [ $this, 'handle_delete_feed' ] );
		add_action( 'admin_post_ibb_rentals_sync_feed',     [ $this, 'handle_sync_feed' ] );
		add_action( 'admin_post_ibb_rentals_sync_clickup',  [ $this, 'handle_sync_clickup' ] );
		add_action( 'wp_ajax_ibb_rentals_clickup_workspaces', [ $this, 'ajax_clickup_workspaces' ] );
		add_action( 'wp_ajax_ibb_rentals_clickup_spaces',     [ $this, 'ajax_clickup_spaces' ] );
		add_action( 'wp_ajax_ibb_rentals_clickup_folders',    [ $this, 'ajax_clickup_folders' ] );
	}

	public function add_pages(): void {
		add_submenu_page(
			self::PARENT,
			__( 'Bookings', 'ibb-rentals' ),
			__( 'Bookings', 'ibb-rentals' ),
			'manage_woocommerce',
			self::PAGE_BOOKINGS,
			[ $this, 'render_bookings' ]
		);
		add_submenu_page(
			self::PARENT,
			__( 'Availability Calendar', 'ibb-rentals' ),
			__( 'Calendar', 'ibb-rentals' ),
			'manage_woocommerce',
			AdminCalendar::PAGE_SLUG,
			[ new AdminCalendar(), 'render' ]
		);
		add_submenu_page(
			self::PARENT,
			__( 'iCal Feeds', 'ibb-rentals' ),
			__( 'iCal Feeds', 'ibb-rentals' ),
			'manage_woocommerce',
			self::PAGE_FEEDS,
			[ $this, 'render_feeds' ]
		);
		add_submenu_page(
			self::PARENT,
			__( 'Rental settings', 'ibb-rentals' ),
			__( 'Settings', 'ibb-rentals' ),
			'manage_woocommerce',
			self::PAGE_SETTINGS,
			[ $this, 'render_settings' ]
		);
	}

	public function render_bookings(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Bookings', 'ibb-rentals' ) . '</h1>';
		echo '<form method="get">';
		echo '<input type="hidden" name="post_type" value="' . esc_attr( PropertyPostType::POST_TYPE ) . '" />';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_BOOKINGS ) . '" />';
		$table = new BookingsListTable();
		$table->prepare_items();
		$table->display();
		echo '</form></div>';
	}

	public function render_feeds(): void {
		$feeds = $this->feeds->find_all();

		$notice = '';
		if ( isset( $_GET['ibb_feed_action'] ) ) {
			$notice = match ( sanitize_key( (string) $_GET['ibb_feed_action'] ) ) {
				'added'   => __( 'Feed added.', 'ibb-rentals' ),
				'deleted' => __( 'Feed deleted.', 'ibb-rentals' ),
				'synced'  => __( 'Sync queued.', 'ibb-rentals' ),
				default   => '',
			};
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'iCal Feeds', 'ibb-rentals' ) . '</h1>';

		if ( $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'Add OTA calendar feeds (Airbnb, Booking.com, Agoda, VRBO, …) per property. The plugin polls each feed in the background and blocks the matched dates.', 'ibb-rentals' ) . '</p>';

		// Add feed form.
		$properties = get_posts( [
			'post_type'      => PropertyPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		echo '<h2>' . esc_html__( 'Add feed', 'ibb-rentals' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="ibb_rentals_add_feed" />';
		wp_nonce_field( 'ibb_rentals_add_feed' );
		echo '<table class="form-table"><tbody>';

		echo '<tr><th>' . esc_html__( 'Property', 'ibb-rentals' ) . '</th><td>';
		echo '<select name="feed_property_id" required>';
		echo '<option value="">' . esc_html__( '— select —', 'ibb-rentals' ) . '</option>';
		foreach ( $properties as $prop ) {
			printf( '<option value="%d">%s</option>', (int) $prop->ID, esc_html( $prop->post_title ) );
		}
		echo '</select></td></tr>';

		echo '<tr><th>' . esc_html__( 'Label', 'ibb-rentals' ) . '</th><td>';
		echo '<input type="text" name="feed_label" class="regular-text" placeholder="' . esc_attr__( 'e.g. Airbnb', 'ibb-rentals' ) . '" required /></td></tr>';

		echo '<tr><th>' . esc_html__( 'Source', 'ibb-rentals' ) . '</th><td>';
		echo '<select name="feed_source">';
		foreach ( [ 'airbnb', 'booking', 'agoda', 'vrbo', 'custom' ] as $src ) {
			printf( '<option value="%s">%s</option>', esc_attr( $src ), esc_html( ucfirst( $src ) ) );
		}
		echo '</select></td></tr>';

		echo '<tr><th>' . esc_html__( 'iCal URL', 'ibb-rentals' ) . '</th><td>';
		echo '<input type="url" name="feed_url" class="large-text" required /></td></tr>';

		echo '<tr><th>' . esc_html__( 'Sync interval (seconds)', 'ibb-rentals' ) . '</th><td>';
		echo '<input type="number" name="feed_sync_interval" value="900" min="300" step="60" /></td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Add feed', 'ibb-rentals' ), 'primary', 'submit', false );
		echo '</form>';

		// Existing feeds table.
		echo '<h2>' . esc_html__( 'Existing feeds', 'ibb-rentals' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Property', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Label', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'URL', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Last sync', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'ibb-rentals' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( ! $feeds ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No feeds configured yet.', 'ibb-rentals' ) . '</td></tr>';
		}

		foreach ( $feeds as $feed ) {
			$title      = get_the_title( (int) $feed['property_id'] ) ?: ( '#' . (int) $feed['property_id'] );
			$status_cls = ( $feed['last_status'] ?? '' ) === 'ok' ? 'ibb-status--confirmed' : ( $feed['last_status'] ? 'ibb-status--cancelled' : '' );
			$sync_url   = wp_nonce_url(
				admin_url( 'admin-post.php?action=ibb_rentals_sync_feed&feed_id=' . (int) $feed['id'] . '&_wp_http_referer=' . rawurlencode( (string) wp_get_referer() ) ),
				'ibb_rentals_sync_feed_' . (int) $feed['id']
			);
			$delete_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=ibb_rentals_delete_feed&feed_id=' . (int) $feed['id'] . '&_wp_http_referer=' . rawurlencode( (string) wp_get_referer() ) ),
				'ibb_rentals_delete_feed_' . (int) $feed['id']
			);
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td><code style="word-break:break-all">%s</code></td><td>%s</td><td>%s</td><td><a href="%s">%s</a> · <a href="%s" onclick="return confirm(\'%s\')">%s</a></td></tr>',
				esc_html( $title ),
				esc_html( (string) $feed['label'] ),
				esc_html( (string) $feed['source'] ),
				esc_html( (string) $feed['url'] ),
				esc_html( (string) ( $feed['last_synced_at'] ?: '—' ) ),
				'<span class="ibb-status ' . esc_attr( $status_cls ) . '">' . esc_html( (string) ( $feed['last_status'] ?: '—' ) ) . '</span>',
				esc_url( $sync_url ),
				esc_html__( 'Sync now', 'ibb-rentals' ),
				esc_url( $delete_url ),
				esc_js( __( 'Delete this feed?', 'ibb-rentals' ) ),
				esc_html__( 'Delete', 'ibb-rentals' )
			);
		}
		echo '</tbody></table></div>';
	}

	public function handle_add_feed(): void {
		check_admin_referer( 'ibb_rentals_add_feed' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ibb-rentals' ) );
		}
		$property_id = (int) ( $_POST['feed_property_id'] ?? 0 );
		$label       = sanitize_text_field( (string) wp_unslash( $_POST['feed_label'] ?? '' ) );
		$source      = sanitize_key( (string) ( $_POST['feed_source'] ?? 'custom' ) );
		$url         = esc_url_raw( (string) wp_unslash( $_POST['feed_url'] ?? '' ) );
		$interval    = max( 300, (int) ( $_POST['feed_sync_interval'] ?? 900 ) );

		if ( $property_id > 0 && $label !== '' && $url !== '' ) {
			$this->feeds->insert( [
				'property_id'   => $property_id,
				'url'           => $url,
				'label'         => $label,
				'source'        => $source,
				'sync_interval' => $interval,
				'enabled'       => 1,
			] );
		}

		wp_safe_redirect( add_query_arg( 'ibb_feed_action', 'added', admin_url( 'edit.php?post_type=' . PropertyPostType::POST_TYPE . '&page=' . self::PAGE_FEEDS ) ) );
		exit;
	}

	public function handle_delete_feed(): void {
		$feed_id = (int) ( $_GET['feed_id'] ?? 0 );
		check_admin_referer( 'ibb_rentals_delete_feed_' . $feed_id );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ibb-rentals' ) );
		}
		if ( $feed_id > 0 ) {
			$this->feeds->delete( $feed_id );
		}
		$ref = wp_get_referer() ?: admin_url( 'edit.php?post_type=' . PropertyPostType::POST_TYPE . '&page=' . self::PAGE_FEEDS );
		wp_safe_redirect( add_query_arg( 'ibb_feed_action', 'deleted', $ref ) );
		exit;
	}

	public function handle_sync_feed(): void {
		$feed_id = (int) ( $_GET['feed_id'] ?? 0 );
		check_admin_referer( 'ibb_rentals_sync_feed_' . $feed_id );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ibb-rentals' ) );
		}
		if ( $feed_id > 0 && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time(), 'ibb_rentals_import_ical_feed', [ $feed_id ], 'ibb-rentals' );
		}
		$ref = wp_get_referer() ?: admin_url( 'edit.php?post_type=' . PropertyPostType::POST_TYPE . '&page=' . self::PAGE_FEEDS );
		wp_safe_redirect( add_query_arg( 'ibb_feed_action', 'synced', $ref ) );
		exit;
	}

	public function handle_sync_clickup(): void {
		check_admin_referer( 'ibb_rentals_sync_clickup' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ibb-rentals' ) );
		}
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time(), Hooks::AS_SYNC_CLICKUP, [], Hooks::AS_GROUP );
		}
		$ref = wp_get_referer() ?: admin_url( 'edit.php?post_type=' . PropertyPostType::POST_TYPE . '&page=' . self::PAGE_SETTINGS );
		wp_safe_redirect( add_query_arg( 'ibb_settings_notice', 'clickup_synced', $ref ) );
		exit;
	}

	// ── ClickUp hierarchy AJAX (used by cascading dropdowns on settings page) ──

	private function ajax_clickup_pre_check(): string {
		check_ajax_referer( 'ibb_rentals_clickup_lookup', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}
		// Token may either be saved already, or typed in the form but not yet saved.
		$token = sanitize_text_field( (string) wp_unslash( $_REQUEST['token'] ?? '' ) );
		if ( $token === '' ) {
			$saved = (array) get_option( 'ibb_rentals_settings', [] );
			$token = (string) ( $saved['clickup_api_token'] ?? '' );
		}
		if ( $token === '' ) {
			wp_send_json_error( __( 'Enter a ClickUp API token first.', 'ibb-rentals' ) );
		}
		return $token;
	}

	public function ajax_clickup_workspaces(): void {
		$token   = $this->ajax_clickup_pre_check();
		$service = Plugin::instance()->clickup_service( $token );
		wp_send_json_success( $service->fetch_workspaces() );
	}

	public function ajax_clickup_spaces(): void {
		$token        = $this->ajax_clickup_pre_check();
		$workspace_id = sanitize_text_field( (string) wp_unslash( $_REQUEST['workspace_id'] ?? '' ) );
		if ( $workspace_id === '' ) {
			wp_send_json_error( __( 'Missing workspace_id.', 'ibb-rentals' ) );
		}
		$service = Plugin::instance()->clickup_service( $token );
		wp_send_json_success( $service->fetch_spaces( $workspace_id ) );
	}

	public function ajax_clickup_folders(): void {
		$token    = $this->ajax_clickup_pre_check();
		$space_id = sanitize_text_field( (string) wp_unslash( $_REQUEST['space_id'] ?? '' ) );
		if ( $space_id === '' ) {
			wp_send_json_error( __( 'Missing space_id.', 'ibb-rentals' ) );
		}
		$service = Plugin::instance()->clickup_service( $token );
		wp_send_json_success( $service->fetch_folders_and_lists( $space_id ) );
	}

	public function render_settings(): void {
		$settings = (array) get_option( 'ibb_rentals_settings', [] );
		$mode     = (string) ( $settings['default_payment_mode'] ?? 'full' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Rental settings', 'ibb-rentals' ) . '</h1>';
		if ( isset( $_GET['ibb_settings_notice'] ) && sanitize_key( (string) $_GET['ibb_settings_notice'] ) === 'clickup_synced' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'ClickUp sync queued.', 'ibb-rentals' ) . '</p></div>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'ibb_rentals_save_settings', 'ibb_rentals_settings_nonce' );

		// ── Booking defaults ──────────────────────────────────────────────
		echo '<h2 class="title">' . esc_html__( 'Booking defaults', 'ibb-rentals' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		$this->setting_row( __( 'Default check-in time', 'ibb-rentals' ),  'default_check_in_time',  (string) ( $settings['default_check_in_time'] ?? '15:00' ), 'time' );
		$this->setting_row( __( 'Default check-out time', 'ibb-rentals' ), 'default_check_out_time', (string) ( $settings['default_check_out_time'] ?? '11:00' ), 'time' );
		echo '<tr><th>' . esc_html__( 'Default payment mode', 'ibb-rentals' ) . '</th><td>';
		printf(
			'<select name="default_payment_mode">
				<option value="full" %s>%s</option>
				<option value="deposit" %s>%s</option>
			</select>
			<p class="description">%s</p>',
			selected( $mode, 'full', false ),
			esc_html__( 'Full payment at booking', 'ibb-rentals' ),
			selected( $mode, 'deposit', false ),
			esc_html__( 'Deposit at booking, balance before check-in', 'ibb-rentals' ),
			esc_html__( 'Applied to new properties; individual properties can override this.', 'ibb-rentals' )
		);
		echo '</td></tr>';
		$this->setting_row( __( 'Default deposit %', 'ibb-rentals' ),               'default_deposit_pct',       (int) ( $settings['default_deposit_pct'] ?? 30 ), 'number', [ 'min' => 1, 'max' => 99 ] );
		$this->setting_row( __( 'Default balance lead time (days)', 'ibb-rentals' ), 'default_balance_lead_days', (int) ( $settings['default_balance_lead_days'] ?? 14 ), 'number', [ 'min' => 0 ] );
		$this->setting_row( __( 'Cart hold (minutes)', 'ibb-rentals' ),              'cart_hold_minutes',         (int) ( $settings['cart_hold_minutes'] ?? 15 ), 'number', [ 'min' => 1 ] );
		echo '</tbody></table>';

		// ── iCal sync ─────────────────────────────────────────────────────
		echo '<h2 class="title">' . esc_html__( 'iCal sync', 'ibb-rentals' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		$this->setting_row( __( 'Default sync interval (seconds)', 'ibb-rentals' ), 'default_sync_interval', (int) ( $settings['default_sync_interval'] ?? 1800 ), 'number', [ 'min' => 300 ] );

		// Privacy toggle for outgoing feeds. When ON, SUMMARY = "Bob Jones (Agoda)"
		// and DESCRIPTION includes the guest name; the host can see who's
		// staying when on each OTA's calendar. When OFF, SUMMARY falls back
		// to "<Source> booking" with no name. ClickUp deep-links in
		// DESCRIPTION are independent of this toggle (they don't expose
		// guest data).
		$include_names = ! empty( $settings['ical_include_guest_names'] );
		echo '<tr><th>' . esc_html__( 'Guest names in feeds', 'ibb-rentals' ) . '</th><td>';
		printf(
			'<label><input type="checkbox" name="ical_include_guest_names" value="1" %s /> %s</label>',
			checked( $include_names, true, false ),
			esc_html__( 'Include guest names in outgoing iCal SUMMARY / DESCRIPTION', 'ibb-rentals' )
		);
		echo '<p class="description">' . esc_html__( 'When enabled, each OTA\'s calendar shows "Bob Jones (Agoda)" instead of "Agoda booking". ClickUp deep-links in event descriptions are always included regardless of this toggle. Turn off if your OTA contracts prohibit guest data in third-party calendar feeds.', 'ibb-rentals' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		// ── ClickUp integration ───────────────────────────────────────────
		$clickup_token        = (string) ( $settings['clickup_api_token']    ?? '' );
		$clickup_workspace_id = (string) ( $settings['clickup_workspace_id'] ?? '' );
		$clickup_space_id     = (string) ( $settings['clickup_space_id']     ?? '' );
		$clickup_folder_id    = (string) ( $settings['clickup_folder_id']    ?? '' );
		$clickup_list_id      = (string) ( $settings['clickup_list_id']      ?? '' );
		$lookup_nonce         = wp_create_nonce( 'ibb_rentals_clickup_lookup' );
		$default_map          = '{"abnb":"airbnb","airbnb":"airbnb","agoda":"agoda","booking":"booking","vrbo":"vrbo","expedia":"expedia","web":"web","direct":"direct","manual":"manual"}';

		echo '<h2 class="title">' . esc_html__( 'ClickUp integration', 'ibb-rentals' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'When configured, a background job periodically reads your ClickUp Bookings list and writes guest names onto matching OTA blocks in the calendar. Guest data is read from the task itself: title for the name (split on " - "), start_date / due_date for check-in / check-out, and tags for the OTA source.', 'ibb-rentals' ) . '</p>';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th>' . esc_html__( 'API token', 'ibb-rentals' ) . '</th><td>';
		printf(
			'<input type="password" name="clickup_api_token" id="ibb-clickup-token" value="%s" class="regular-text" autocomplete="new-password" />',
			esc_attr( $clickup_token )
		);
		echo ' <button type="button" class="button" id="ibb-clickup-fetch">' . esc_html__( 'Connect / refresh', 'ibb-rentals' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Your ClickUp personal API token. Found under ClickUp avatar → Settings → Apps.', 'ibb-rentals' ) . '</p>';
		echo '<p class="description" id="ibb-clickup-status" style="color:#666"></p></td></tr>';

		// Workspace dropdown
		echo '<tr><th>' . esc_html__( 'Workspace', 'ibb-rentals' ) . '</th><td>';
		printf(
			'<select id="ibb-clickup-workspace" name="clickup_workspace_id" disabled><option value="%s">— %s —</option></select>',
			esc_attr( $clickup_workspace_id ),
			esc_html__( 'connect first', 'ibb-rentals' )
		);
		echo '</td></tr>';

		// Space dropdown
		echo '<tr><th>' . esc_html__( 'Space', 'ibb-rentals' ) . '</th><td>';
		echo '<select id="ibb-clickup-space" name="clickup_space_id" disabled><option value="">— —</option></select>';
		echo '</td></tr>';

		// Folder dropdown
		echo '<tr><th>' . esc_html__( 'Folder', 'ibb-rentals' ) . '</th><td>';
		echo '<select id="ibb-clickup-folder" name="clickup_folder_id" disabled><option value="">— —</option></select>';
		echo '</td></tr>';

		// List dropdown — its value writes to the hidden clickup_list_id input that gets saved.
		echo '<tr><th>' . esc_html__( 'Bookings list', 'ibb-rentals' ) . '</th><td>';
		echo '<select id="ibb-clickup-list" disabled><option value="">— —</option></select>';
		printf(
			'<input type="hidden" name="clickup_list_id" id="ibb-clickup-list-id" value="%s" />',
			esc_attr( $clickup_list_id )
		);
		if ( $clickup_list_id !== '' ) {
			echo ' <code id="ibb-clickup-current-list" style="margin-left:8px;color:#666">' . esc_html( sprintf( __( 'saved: %s', 'ibb-rentals' ), $clickup_list_id ) ) . '</code>';
		}
		echo '</td></tr>';

		// ── Unit-code → property mapping ──────────────────────────────────
		// Decode the saved map so we can pre-fill each property's input.
		$saved_unit_map = [];
		if ( ! empty( $settings['clickup_unit_property_map'] ) ) {
			$decoded = json_decode( (string) $settings['clickup_unit_property_map'], true );
			if ( is_array( $decoded ) ) {
				$saved_unit_map = $decoded;
			}
		}
		// Reverse to property_id → list of codes for display.
		$codes_by_property = [];
		foreach ( $saved_unit_map as $code => $pid ) {
			$codes_by_property[ (int) $pid ][] = (string) $code;
		}

		$ibb_properties = get_posts( [
			'post_type'      => PropertyPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		echo '<tr><th>' . esc_html__( 'Unit code → property', 'ibb-rentals' ) . '</th><td>';
		echo '<p class="description" style="margin-top:0">' . esc_html__( 'Map the unit identifier in your ClickUp task titles ("v1 - Bob Jones" → "v1") to an IBB property. Enter one or more codes per property, comma-separated. Codes are case-insensitive. When matched, the sync scopes the guest-name UPDATE to that property only — no cross-property collisions.', 'ibb-rentals' ) . '</p>';

		if ( empty( $ibb_properties ) ) {
			echo '<p style="color:#888"><em>' . esc_html__( 'No properties published yet.', 'ibb-rentals' ) . '</em></p>';
		} else {
			echo '<table class="widefat striped" style="max-width:600px;margin-top:8px;"><thead><tr>';
			echo '<th style="width:60%">' . esc_html__( 'IBB property', 'ibb-rentals' ) . '</th>';
			echo '<th>' . esc_html__( 'ClickUp unit code(s)', 'ibb-rentals' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $ibb_properties as $prop ) {
				$pid    = (int) $prop->ID;
				$codes  = isset( $codes_by_property[ $pid ] ) ? implode( ', ', $codes_by_property[ $pid ] ) : '';
				printf(
					'<tr><td>%s <code style="color:#666;font-size:.85em">#%d</code></td><td><input type="text" name="clickup_unit_codes[%d]" value="%s" class="regular-text" placeholder="%s" /></td></tr>',
					esc_html( $prop->post_title ),
					$pid,
					$pid,
					esc_attr( $codes ),
					esc_attr__( 'e.g. v1, villa1', 'ibb-rentals' )
				);
			}
			echo '</tbody></table>';
		}
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Tag → source map', 'ibb-rentals' ) . '</th><td>';
		printf(
			'<textarea name="clickup_tag_map" rows="3" class="large-text code">%s</textarea>',
			esc_textarea( (string) ( $settings['clickup_tag_map'] ?? $default_map ) )
		);
		echo '<p class="description">' . esc_html__( 'JSON object mapping your ClickUp tag names to IBB sources. Keys are tag names (lowercase), values are source slugs.', 'ibb-rentals' ) . '</p></td></tr>';

		// ── Auto-create blocks from ClickUp tasks ────────────────────────
		// Sources that have an active iCal feed configured for ANY property
		// are excluded from the allowlist UI — those OTAs are already the
		// authoritative source of truth for their own bookings, and letting
		// ClickUp insert phantom blocks for them would compete.
		$feeds_by_source = [];
		foreach ( $this->feeds->find_enabled() as $feed_row ) {
			$feeds_by_source[ (string) $feed_row['source'] ] = true;
		}

		$allowlist_options = [
			'web'     => __( 'Website (this site\'s checkout — leave off, plugin already inserts these)', 'ibb-rentals' ),
			'direct'  => __( 'Walk-in / phone', 'ibb-rentals' ),
			'manual'  => __( 'Manual block', 'ibb-rentals' ),
			'airbnb'  => 'Airbnb',
			'booking' => 'Booking.com',
			'agoda'   => 'Agoda',
			'vrbo'    => 'VRBO',
			'expedia' => 'Expedia',
		];
		$saved_allowlist = [];
		if ( ! empty( $settings['clickup_create_sources'] ) ) {
			$decoded = json_decode( (string) $settings['clickup_create_sources'], true );
			if ( is_array( $decoded ) ) {
				$saved_allowlist = array_map( 'strval', $decoded );
			}
		}

		echo '<tr><th>' . esc_html__( 'Create blocks for', 'ibb-rentals' ) . '</th><td>';
		echo '<p class="description" style="margin-top:0">' . esc_html__( 'When a ClickUp task has property + dates + a mapped source but no matching block exists yet, the sync inserts one. Tick each source you want this auto-create behaviour for. OTAs that already have an iCal feed configured (greyed out) are excluded — their feed is the authoritative source of truth for their bookings, so ClickUp creating phantoms there would compete.', 'ibb-rentals' ) . '</p>';
		foreach ( $allowlist_options as $slug => $label ) {
			$has_feed = isset( $feeds_by_source[ $slug ] );
			$disabled = $has_feed ? ' disabled' : '';
			$checked  = ( ! $has_feed && in_array( $slug, $saved_allowlist, true ) ) ? ' checked' : '';
			$style    = $has_feed ? ' style="color:#999"' : '';
			printf(
				'<label%s style="display:block;margin:2px 0"><input type="checkbox" name="clickup_create_sources[]" value="%s"%s%s /> %s%s</label>',
				$style,
				esc_attr( $slug ),
				$checked,
				$disabled,
				esc_html( $label ),
				$has_feed ? ' <em>' . esc_html__( '(has iCal feed — disabled)', 'ibb-rentals' ) . '</em>' : ''
			);
		}
		echo '</td></tr>';

		$this->setting_row( __( 'Sync interval (seconds)', 'ibb-rentals' ), 'clickup_sync_interval', (int) ( $settings['clickup_sync_interval'] ?? 3600 ), 'number', [ 'min' => 300 ] );

		echo '</tbody></table>';

		if ( $clickup_token !== '' && $clickup_list_id !== '' ) {
			$sync_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=ibb_rentals_sync_clickup&_wp_http_referer=' . rawurlencode( (string) wp_get_referer() ) ),
				'ibb_rentals_sync_clickup'
			);
			echo '<p style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">';
			echo '<a href="' . esc_url( $sync_url ) . '" class="button">' . esc_html__( 'Sync now', 'ibb-rentals' ) . '</a>';

			$status = (array) get_option( ClickUpService::STATUS_OPT, [] );
			if ( ! empty( $status['last_sync_at'] ) ) {
				$when      = (int) $status['last_sync_at'];
				$error     = (string) ( $status['error'] ?? '' );
				$updated   = (int) ( $status['updated']   ?? 0 );
				$created   = (int) ( $status['created']   ?? 0 );
				$cancelled = (int) ( $status['cancelled'] ?? 0 );
				$total     = (int) ( $status['total_tasks'] ?? 0 );
				$relative  = human_time_diff( $when, time() );

				if ( $error !== '' ) {
					printf(
						'<span style="color:#b32d2e;font-weight:600">%s</span>',
						esc_html( sprintf(
							/* translators: 1 = relative time, 2 = error message */
							__( '✗ Last sync failed %1$s ago: %2$s', 'ibb-rentals' ),
							$relative,
							$error
						) )
					);
				} else {
					printf(
						'<span style="color:#0a8f3d;font-weight:600">%s</span>',
						esc_html( sprintf(
							/* translators: 1 = relative time, 2 = created, 3 = updated, 4 = cancelled, 5 = tasks fetched */
							__( '✓ Last sync %1$s ago — %2$d created, %3$d updated, %4$d cancelled (from %5$d task(s))', 'ibb-rentals' ),
							$relative,
							$created,
							$updated,
							$cancelled,
							$total
						) )
					);
				}
			} else {
				echo '<span style="color:#666">' . esc_html__( 'No sync has run yet.', 'ibb-rentals' ) . '</span>';
			}
			echo '</p>';
		}

		// ── Cascading-dropdown JS (vanilla, no jQuery dep) ────────────────
		?>
		<script>
		(function () {
			var ajaxUrl        = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce          = <?php echo wp_json_encode( $lookup_nonce ); ?>;
			var savedWorkspace = <?php echo wp_json_encode( $clickup_workspace_id ); ?>;
			var savedSpace     = <?php echo wp_json_encode( $clickup_space_id ); ?>;
			var savedFolder    = <?php echo wp_json_encode( $clickup_folder_id ); ?>;
			var savedListId    = <?php echo wp_json_encode( $clickup_list_id ); ?>;

			var tokenEl   = document.getElementById('ibb-clickup-token');
			var fetchBtn  = document.getElementById('ibb-clickup-fetch');
			var statusEl  = document.getElementById('ibb-clickup-status');
			var wsEl      = document.getElementById('ibb-clickup-workspace');
			var spaceEl   = document.getElementById('ibb-clickup-space');
			var folderEl  = document.getElementById('ibb-clickup-folder');
			var listEl    = document.getElementById('ibb-clickup-list');
			var listIdEl  = document.getElementById('ibb-clickup-list-id');

			// Folder→lists lookup populated by fetch_folders_and_lists (each folder
			// already includes its child lists, so picking a folder doesn't need
			// another round-trip).
			var foldersWithLists = [];

			// On the initial auto-cascade we don't want each level to wipe the next
			// (default behaviour for a manual user change). preserveDownstream defers
			// resetting child selects until the next level loads its own data.
			var initialRestoreInProgress = false;

			function setStatus(msg, isError) {
				statusEl.textContent = msg || '';
				statusEl.style.color = isError ? '#c00' : '#666';
			}

			function resetSelect(el, placeholder) {
				el.innerHTML = '<option value="">' + placeholder + '</option>';
				el.disabled  = true;
			}

			function call(action, params) {
				var body = new URLSearchParams(Object.assign({
					action: 'ibb_rentals_clickup_' + action,
					nonce:  nonce,
					token:  tokenEl.value,
				}, params || {}));
				return fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
					.then(function (r) { return r.json(); });
			}

			function loadWorkspaces() {
				resetSelect(wsEl,     '<?php echo esc_js( __( 'loading…', 'ibb-rentals' ) ); ?>');
				if (!initialRestoreInProgress) {
					resetSelect(spaceEl,  '— —');
					resetSelect(folderEl, '— —');
					resetSelect(listEl,   '— —');
				}
				setStatus('<?php echo esc_js( __( 'Fetching workspaces…', 'ibb-rentals' ) ); ?>');

				return call('workspaces').then(function (res) {
					if (!res.success) throw new Error(res.data || 'error');
					wsEl.innerHTML = '<option value="">— <?php echo esc_js( __( 'select workspace', 'ibb-rentals' ) ); ?> —</option>';
					res.data.forEach(function (w) {
						var o = document.createElement('option');
						o.value = w.id; o.textContent = w.name + ' (' + w.id + ')';
						if (w.id === savedWorkspace) o.selected = true;
						wsEl.appendChild(o);
					});
					wsEl.disabled = false;
					setStatus(res.data.length + ' workspace(s) found.');
					if (savedWorkspace) return loadSpaces(savedWorkspace);
				}).catch(function (e) {
					setStatus('<?php echo esc_js( __( 'Error: ', 'ibb-rentals' ) ); ?>' + (e.message || e), true);
				});
			}

			function loadSpaces(workspaceId, autoSelect) {
				resetSelect(spaceEl,  '<?php echo esc_js( __( 'loading…', 'ibb-rentals' ) ); ?>');
				if (!initialRestoreInProgress) {
					resetSelect(folderEl, '— —');
					resetSelect(listEl,   '— —');
				}
				return call('spaces', { workspace_id: workspaceId }).then(function (res) {
					if (!res.success) throw new Error(res.data || 'error');
					spaceEl.innerHTML = '<option value="">— <?php echo esc_js( __( 'select space', 'ibb-rentals' ) ); ?> —</option>';
					res.data.forEach(function (s) {
						var o = document.createElement('option');
						o.value = s.id; o.textContent = s.name;
						if (s.id === savedSpace) o.selected = true;
						spaceEl.appendChild(o);
					});
					spaceEl.disabled = false;
					if (initialRestoreInProgress && savedSpace) return loadFolders(savedSpace);
				});
			}

			function loadFolders(spaceId) {
				resetSelect(folderEl, '<?php echo esc_js( __( 'loading…', 'ibb-rentals' ) ); ?>');
				if (!initialRestoreInProgress) {
					resetSelect(listEl,   '— —');
				}
				return call('folders', { space_id: spaceId }).then(function (res) {
					if (!res.success) throw new Error(res.data || 'error');
					foldersWithLists = res.data;
					folderEl.innerHTML = '<option value="">— <?php echo esc_js( __( 'select folder', 'ibb-rentals' ) ); ?> —</option>';
					foldersWithLists.forEach(function (f) {
						var o = document.createElement('option');
						o.value = f.id; o.textContent = f.name + ' (' + (f.lists ? f.lists.length : 0) + ' lists)';
						if (f.id === savedFolder) o.selected = true;
						folderEl.appendChild(o);
					});
					folderEl.disabled = false;
					if (initialRestoreInProgress && savedFolder) {
						populateLists(savedFolder);
						initialRestoreInProgress = false;
					}
				});
			}

			function populateLists(folderId) {
				resetSelect(listEl, '— —');
				var folder = foldersWithLists.find(function (f) { return f.id === folderId; });
				if (!folder) return;
				listEl.innerHTML = '<option value="">— <?php echo esc_js( __( 'select list', 'ibb-rentals' ) ); ?> —</option>';
				folder.lists.forEach(function (l) {
					var o = document.createElement('option');
					o.value = l.id; o.textContent = l.name + ' (' + l.id + ')';
					if (l.id === savedListId) o.selected = true;
					listEl.appendChild(o);
				});
				listEl.disabled = false;
				if (savedListId) listIdEl.value = savedListId;
			}

			fetchBtn.addEventListener('click', function () {
				if (!tokenEl.value) { setStatus('<?php echo esc_js( __( 'Enter an API token first.', 'ibb-rentals' ) ); ?>', true); return; }
				initialRestoreInProgress = false;  // explicit fetch is a fresh start
				loadWorkspaces();
			});
			wsEl.addEventListener('change',     function () { if (wsEl.value)     loadSpaces(wsEl.value); });
			spaceEl.addEventListener('change',  function () { if (spaceEl.value)  loadFolders(spaceEl.value); });
			folderEl.addEventListener('change', function () { if (folderEl.value) populateLists(folderEl.value); });
			listEl.addEventListener('change',   function () { listIdEl.value = listEl.value; });

			// On reload: if we have a saved cascade, walk it automatically so the
			// dropdowns show the prior selection.
			if (tokenEl.value && savedWorkspace) {
				initialRestoreInProgress = true;
				loadWorkspaces();
			}
		})();
		</script>
		<?php

		// ── Gateway capability matrix (read-only info) ────────────────────
		$gateways = $this->gateway_caps->active_gateway_summary();
		if ( ! empty( $gateways ) ) {
			echo '<h2 class="title">' . esc_html__( 'Payment gateway capabilities', 'ibb-rentals' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'Shows which balance-collection path each active gateway will use for deposit bookings.', 'ibb-rentals' ) . '</p>';
			echo '<table class="widefat striped" style="max-width:600px;margin-bottom:20px"><thead><tr>';
			echo '<th>' . esc_html__( 'Gateway', 'ibb-rentals' ) . '</th>';
			echo '<th>' . esc_html__( 'Balance path', 'ibb-rentals' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $gateways as $gw ) {
				$is_auto = $gw['path'] === GatewayCapabilities::PATH_AUTO_CHARGE;
				echo '<tr>';
				printf( '<td>%s <code style="font-size:.85em;color:#666">%s</code></td>', esc_html( $gw['title'] ), esc_html( $gw['id'] ) );
				printf(
					'<td><span style="color:%s;font-weight:600">%s</span> — <span style="color:#666;font-size:.9em">%s</span></td>',
					$is_auto ? '#0a0' : '#888',
					$is_auto ? esc_html__( 'Auto-charge', 'ibb-rentals' ) : esc_html__( 'Payment link', 'ibb-rentals' ),
					$is_auto
						? esc_html__( 'balance charged automatically via saved token', 'ibb-rentals' )
						: esc_html__( 'guest emailed a pay-now link before check-in', 'ibb-rentals' )
				);
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		// ── System ────────────────────────────────────────────────────────
		echo '<h2 class="title">' . esc_html__( 'System', 'ibb-rentals' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		$this->setting_row( __( 'Log retention (days)', 'ibb-rentals' ), 'log_retention_days', (int) ( $settings['log_retention_days'] ?? 30 ), 'number', [ 'min' => 1 ] );
		echo '<tr><th>' . esc_html__( 'Remove all data on uninstall', 'ibb-rentals' ) . '</th><td>';
		printf(
			'<label><input type="checkbox" name="uninstall_purge_data" value="1" %s /> %s</label>',
			checked( ! empty( $settings['uninstall_purge_data'] ), true, false ),
			esc_html__( 'Drop tables and delete posts on plugin deletion', 'ibb-rentals' )
		);
		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button( __( 'Save settings', 'ibb-rentals' ) );
		echo '</form></div>';
	}

	public function maybe_save_settings(): void {
		if ( ! isset( $_POST['ibb_rentals_settings_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['ibb_rentals_settings_nonce'] ) ), 'ibb_rentals_save_settings' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$existing = (array) get_option( 'ibb_rentals_settings', [] );
		$raw_mode = sanitize_text_field( (string) wp_unslash( $_POST['default_payment_mode'] ?? 'full' ) );

		$clickup_token     = sanitize_text_field( (string) wp_unslash( $_POST['clickup_api_token']    ?? '' ) );
		$clickup_workspace = sanitize_text_field( (string) wp_unslash( $_POST['clickup_workspace_id'] ?? '' ) );
		$clickup_space     = sanitize_text_field( (string) wp_unslash( $_POST['clickup_space_id']     ?? '' ) );
		$clickup_folder    = sanitize_text_field( (string) wp_unslash( $_POST['clickup_folder_id']    ?? '' ) );
		$clickup_list      = sanitize_text_field( (string) wp_unslash( $_POST['clickup_list_id']     ?? '' ) );
		$clickup_interval  = max( 300, (int) ( $_POST['clickup_sync_interval'] ?? 3600 ) );

		// Validate the tag map JSON; keep the old value if it's malformed.
		$raw_tag_map = sanitize_textarea_field( (string) wp_unslash( $_POST['clickup_tag_map'] ?? '' ) );
		$decoded_map = json_decode( $raw_tag_map, true );
		$clickup_map = is_array( $decoded_map ) ? $raw_tag_map : ( (string) ( $existing['clickup_tag_map'] ?? '' ) );

		// Build unit-code → property-ID map from the per-property text inputs.
		// Each input may contain a comma-separated list of codes ("v1, villa1") that
		// all map to the same property. Codes are lowercased for case-insensitive matching.
		$unit_codes_input = isset( $_POST['clickup_unit_codes'] ) && is_array( $_POST['clickup_unit_codes'] )
			? wp_unslash( (array) $_POST['clickup_unit_codes'] )
			: [];
		$unit_property_map = [];
		foreach ( $unit_codes_input as $pid => $raw_codes ) {
			$pid = (int) $pid;
			if ( $pid <= 0 ) {
				continue;
			}
			$codes = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( (string) $raw_codes ) ) ) );
			foreach ( $codes as $code ) {
				$unit_property_map[ strtolower( $code ) ] = $pid;
			}
		}
		$clickup_unit_property_map_json = wp_json_encode( $unit_property_map ) ?: '{}';

		// Allowlist of sources for which ClickUp may auto-create blocks.
		// We deliberately re-validate here rather than trusting the UI to have
		// disabled feed-backed sources — if someone POSTs a source that has an
		// iCal feed configured, we drop it server-side too.
		$allowed_create = [ 'web', 'direct', 'manual', 'airbnb', 'booking', 'agoda', 'vrbo', 'expedia' ];
		$feeds_by_source = [];
		foreach ( $this->feeds->find_enabled() as $feed_row ) {
			$feeds_by_source[ (string) $feed_row['source'] ] = true;
		}
		$create_sources_input = isset( $_POST['clickup_create_sources'] ) && is_array( $_POST['clickup_create_sources'] )
			? wp_unslash( (array) $_POST['clickup_create_sources'] )
			: [];
		$create_sources = [];
		foreach ( $create_sources_input as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( in_array( $slug, $allowed_create, true ) && ! isset( $feeds_by_source[ $slug ] ) ) {
				$create_sources[] = $slug;
			}
		}
		$clickup_create_sources_json = wp_json_encode( array_values( array_unique( $create_sources ) ) ) ?: '[]';

		$updated = array_merge( $existing, [
			'default_payment_mode'      => $raw_mode === 'deposit' ? 'deposit' : 'full',
			'default_check_in_time'     => sanitize_text_field( (string) wp_unslash( $_POST['default_check_in_time'] ?? '15:00' ) ),
			'default_check_out_time'    => sanitize_text_field( (string) wp_unslash( $_POST['default_check_out_time'] ?? '11:00' ) ),
			'cart_hold_minutes'         => max( 1, (int) ( $_POST['cart_hold_minutes'] ?? 15 ) ),
			'default_deposit_pct'       => max( 1, min( 99, (int) ( $_POST['default_deposit_pct'] ?? 30 ) ) ),
			'default_balance_lead_days' => max( 0, (int) ( $_POST['default_balance_lead_days'] ?? 14 ) ),
			'default_sync_interval'     => max( 300, (int) ( $_POST['default_sync_interval'] ?? 1800 ) ),
			'ical_include_guest_names'  => ! empty( $_POST['ical_include_guest_names'] ),
			'log_retention_days'        => max( 1, (int) ( $_POST['log_retention_days'] ?? 30 ) ),
			'uninstall_purge_data'      => ! empty( $_POST['uninstall_purge_data'] ),
			'clickup_api_token'         => $clickup_token,
			'clickup_workspace_id'      => $clickup_workspace,
			'clickup_space_id'          => $clickup_space,
			'clickup_folder_id'         => $clickup_folder,
			'clickup_list_id'           => $clickup_list,
			'clickup_tag_map'           => $clickup_map,
			'clickup_unit_property_map' => $clickup_unit_property_map_json,
			'clickup_create_sources'    => $clickup_create_sources_json,
			'clickup_sync_interval'     => $clickup_interval,
		] );

		// Drop obsolete settings from previous version.
		unset( $updated['clickup_guest_name_field'], $updated['clickup_checkin_field'], $updated['clickup_checkout_field'] );
		update_option( 'ibb_rentals_settings', $updated, false );

		// Reschedule the ClickUp recurring action to pick up any interval change.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Hooks::AS_SYNC_CLICKUP, [], Hooks::AS_GROUP );
			if ( $clickup_token !== '' && $clickup_list !== '' ) {
				as_schedule_recurring_action( time() + 30, $clickup_interval, Hooks::AS_SYNC_CLICKUP, [], Hooks::AS_GROUP );
			}
		}

		add_action( 'admin_notices', static function (): void {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'ibb-rentals' ) . '</p></div>';
		} );
	}

	/** @param array<string, scalar> $extra */
	private function setting_row( string $label, string $key, mixed $value, string $type = 'text', array $extra = [] ): void {
		$attrs = '';
		foreach ( $extra as $k => $v ) {
			$attrs .= ' ' . esc_attr( $k ) . '="' . esc_attr( (string) $v ) . '"';
		}
		echo '<tr><th>' . esc_html( $label ) . '</th><td>';
		printf( '<input type="%s" name="%s" value="%s"%s />', esc_attr( $type ), esc_attr( $key ), esc_attr( (string) $value ), $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</td></tr>';
	}
}
