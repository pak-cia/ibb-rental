<?php
/**
 * Fires when an admin clicks "Delete" on the plugin in wp-admin.
 *
 * Drops custom tables and removes options ONLY if the admin opted in via the
 * "Remove all data on uninstall" setting. Posts in the CPT are preserved by
 * default — losing booking history on an accidental delete is unacceptable.
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings    = get_option( 'ibb_rentals_settings', [] );
$purge       = is_array( $settings ) && ! empty( $settings['uninstall_purge_data'] );

if ( ! $purge ) {
	return;
}

global $wpdb;

$tables = [
	$wpdb->prefix . 'ibb_blocks',
	$wpdb->prefix . 'ibb_rates',
	$wpdb->prefix . 'ibb_bookings',
	$wpdb->prefix . 'ibb_ical_feeds',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

delete_option( 'ibb_rentals_db_version' );
delete_option( 'ibb_rentals_token_secret' );
delete_option( 'ibb_rentals_settings' );

$post_ids = get_posts( [
	'post_type'      => 'ibb_property',
	'post_status'    => 'any',
	'numberposts'    => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
	'suppress_filters' => true,
] );
foreach ( $post_ids as $id ) {
	wp_delete_post( (int) $id, true );
}

$product_ids = get_posts( [
	'post_type'      => 'product',
	'post_status'    => 'any',
	'numberposts'    => -1,
	'meta_key'       => '_ibb_property_id',
	'fields'         => 'ids',
	'no_found_rows'  => true,
	'suppress_filters' => true,
] );
foreach ( $product_ids as $id ) {
	wp_delete_post( (int) $id, true );
}
