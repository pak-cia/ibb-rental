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

use IBB\Rentals\PostTypes\PropertyPostType;
use IBB\Rentals\Repositories\FeedRepository;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const PARENT = 'edit.php?post_type=' . PropertyPostType::POST_TYPE;

	public const PAGE_BOOKINGS = 'ibb-rentals-bookings';
	public const PAGE_FEEDS    = 'ibb-rentals-feeds';
	public const PAGE_SETTINGS = 'ibb-rentals-settings';

	public function __construct(
		private FeedRepository $feeds,
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_pages' ], 20 );
		add_action( 'admin_init', [ $this, 'maybe_save_settings' ] );
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
		$feeds = $this->feeds->find_enabled();
		echo '<div class="wrap"><h1>' . esc_html__( 'iCal Feeds', 'ibb-rentals' ) . '</h1>';
		echo '<p>' . esc_html__( 'Add OTA calendar feeds (Airbnb, Booking.com, Agoda, VRBO, …) per property. The plugin polls each feed in the background and blocks the matched dates.', 'ibb-rentals' ) . '</p>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Property', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Label', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'URL', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Last sync', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'ibb-rentals' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $feeds ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No feeds configured. Use the REST API or each property\'s iCal tab to add feeds.', 'ibb-rentals' ) . '</td></tr>';
		}
		foreach ( $feeds as $feed ) {
			$title = get_the_title( (int) $feed['property_id'] ) ?: ( '#' . (int) $feed['property_id'] );
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
				esc_html( $title ),
				esc_html( (string) $feed['label'] ),
				esc_html( (string) $feed['source'] ),
				esc_html( (string) $feed['url'] ),
				esc_html( (string) ( $feed['last_synced_at'] ?: '—' ) ),
				esc_html( (string) ( $feed['last_status'] ?: '—' ) )
			);
		}
		echo '</tbody></table></div>';
	}

	public function render_settings(): void {
		$settings = (array) get_option( 'ibb_rentals_settings', [] );
		echo '<div class="wrap"><h1>' . esc_html__( 'Rental settings', 'ibb-rentals' ) . '</h1>';
		echo '<form method="post">';
		wp_nonce_field( 'ibb_rentals_save_settings', 'ibb_rentals_settings_nonce' );
		echo '<table class="form-table"><tbody>';
		$this->setting_row( __( 'Default sync interval (seconds)', 'ibb-rentals' ),       'default_sync_interval',     (int) ( $settings['default_sync_interval'] ?? 1800 ), 'number', [ 'min' => 300 ] );
		$this->setting_row( __( 'Default check-in time', 'ibb-rentals' ),                 'default_check_in_time',     (string) ( $settings['default_check_in_time'] ?? '15:00' ), 'time' );
		$this->setting_row( __( 'Default check-out time', 'ibb-rentals' ),                'default_check_out_time',    (string) ( $settings['default_check_out_time'] ?? '11:00' ), 'time' );
		$this->setting_row( __( 'Cart hold (minutes)', 'ibb-rentals' ),                   'cart_hold_minutes',         (int) ( $settings['cart_hold_minutes'] ?? 15 ), 'number', [ 'min' => 1 ] );
		$this->setting_row( __( 'Default deposit %', 'ibb-rentals' ),                     'default_deposit_pct',       (int) ( $settings['default_deposit_pct'] ?? 30 ), 'number', [ 'min' => 0, 'max' => 100 ] );
		$this->setting_row( __( 'Default balance lead time (days)', 'ibb-rentals' ),      'default_balance_lead_days', (int) ( $settings['default_balance_lead_days'] ?? 14 ), 'number', [ 'min' => 0 ] );
		$this->setting_row( __( 'Log retention (days)', 'ibb-rentals' ),                  'log_retention_days',        (int) ( $settings['log_retention_days'] ?? 30 ), 'number', [ 'min' => 1 ] );
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
		$updated  = array_merge( $existing, [
			'default_sync_interval'     => max( 300, (int) ( $_POST['default_sync_interval'] ?? 1800 ) ),
			'default_check_in_time'     => sanitize_text_field( (string) wp_unslash( $_POST['default_check_in_time'] ?? '15:00' ) ),
			'default_check_out_time'    => sanitize_text_field( (string) wp_unslash( $_POST['default_check_out_time'] ?? '11:00' ) ),
			'cart_hold_minutes'         => max( 1, (int) ( $_POST['cart_hold_minutes'] ?? 15 ) ),
			'default_deposit_pct'       => max( 0, min( 100, (int) ( $_POST['default_deposit_pct'] ?? 30 ) ) ),
			'default_balance_lead_days' => max( 0, (int) ( $_POST['default_balance_lead_days'] ?? 14 ) ),
			'log_retention_days'        => max( 1, (int) ( $_POST['log_retention_days'] ?? 30 ) ),
			'uninstall_purge_data'      => ! empty( $_POST['uninstall_purge_data'] ),
		] );
		update_option( 'ibb_rentals_settings', $updated, false );

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
