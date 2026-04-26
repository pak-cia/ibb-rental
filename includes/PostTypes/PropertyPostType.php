<?php
/**
 * Custom post type `ibb_property` and its taxonomies.
 *
 * Public CPT with archive — guests browse properties at /properties/. The CPT
 * holds the human-facing content (title, description, gallery); pricing,
 * availability, and booking rules live in custom tables and postmeta.
 */

declare( strict_types=1 );

namespace IBB\Rentals\PostTypes;

defined( 'ABSPATH' ) || exit;

final class PropertyPostType {

	public const POST_TYPE         = 'ibb_property';
	public const TAX_AMENITY       = 'ibb_amenity';
	public const TAX_LOCATION      = 'ibb_location';
	public const TAX_PROPERTY_TYPE = 'ibb_property_type';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ], 5 );
		add_action( 'init', [ $this, 'register_taxonomies' ], 6 );
	}

	public function register_post_type(): void {
		$labels = [
			'name'               => _x( 'Properties', 'post type general name', 'ibb-rentals' ),
			'singular_name'      => _x( 'Property', 'post type singular name', 'ibb-rentals' ),
			'menu_name'          => _x( 'Rentals', 'admin menu', 'ibb-rentals' ),
			'name_admin_bar'     => _x( 'Property', 'add new on admin bar', 'ibb-rentals' ),
			'add_new'            => _x( 'Add New', 'property', 'ibb-rentals' ),
			'add_new_item'       => __( 'Add New Property', 'ibb-rentals' ),
			'new_item'           => __( 'New Property', 'ibb-rentals' ),
			'edit_item'          => __( 'Edit Property', 'ibb-rentals' ),
			'view_item'          => __( 'View Property', 'ibb-rentals' ),
			'all_items'          => __( 'All Properties', 'ibb-rentals' ),
			'search_items'       => __( 'Search Properties', 'ibb-rentals' ),
			'not_found'          => __( 'No properties found.', 'ibb-rentals' ),
			'not_found_in_trash' => __( 'No properties found in Trash.', 'ibb-rentals' ),
		];

		register_post_type( self::POST_TYPE, [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'ibb-properties',
			'menu_icon'          => 'dashicons-palmtree',
			'menu_position'      => 26,
			'capability_type'    => 'post',
			'has_archive'        => true,
			'rewrite'            => [ 'slug' => 'properties', 'with_front' => false ],
			'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes' ],
			'taxonomies'         => [ self::TAX_AMENITY, self::TAX_LOCATION, self::TAX_PROPERTY_TYPE ],
		] );
	}

	public function register_taxonomies(): void {
		register_taxonomy( self::TAX_AMENITY, [ self::POST_TYPE ], [
			'labels'                     => [
				'name'                       => __( 'Amenities', 'ibb-rentals' ),
				'singular_name'              => __( 'Amenity', 'ibb-rentals' ),
				'menu_name'                  => __( 'Amenities', 'ibb-rentals' ),
				'all_items'                  => __( 'All Amenities', 'ibb-rentals' ),
				'edit_item'                  => __( 'Edit Amenity', 'ibb-rentals' ),
				'view_item'                  => __( 'View Amenity', 'ibb-rentals' ),
				'update_item'                => __( 'Update Amenity', 'ibb-rentals' ),
				'add_new_item'               => __( 'Add New Amenity', 'ibb-rentals' ),
				'new_item_name'              => __( 'New Amenity Name', 'ibb-rentals' ),
				'search_items'               => __( 'Search Amenities', 'ibb-rentals' ),
				'popular_items'              => __( 'Popular Amenities', 'ibb-rentals' ),
				'separate_items_with_commas' => __( 'Separate amenities with commas (e.g. Pool, Wi-Fi, Air Conditioning)', 'ibb-rentals' ),
				'add_or_remove_items'        => __( 'Add or remove amenities', 'ibb-rentals' ),
				'choose_from_most_used'      => __( 'Choose from the most-used amenities', 'ibb-rentals' ),
				'not_found'                  => __( 'No amenities found.', 'ibb-rentals' ),
				'back_to_items'              => __( '← Back to amenities', 'ibb-rentals' ),
			],
			'public'                     => true,
			'show_in_rest'               => true,
			'hierarchical'               => false,
			'show_admin_column'          => true,
			'rewrite'                    => [ 'slug' => 'amenity' ],
		] );

		register_taxonomy( self::TAX_LOCATION, [ self::POST_TYPE ], [
			'labels'             => [
				'name'              => __( 'Locations', 'ibb-rentals' ),
				'singular_name'     => __( 'Location', 'ibb-rentals' ),
				'menu_name'         => __( 'Locations', 'ibb-rentals' ),
				'all_items'         => __( 'All Locations', 'ibb-rentals' ),
				'parent_item'       => __( 'Parent Region', 'ibb-rentals' ),
				'parent_item_colon' => __( 'Parent Region:', 'ibb-rentals' ),
				'edit_item'         => __( 'Edit Location', 'ibb-rentals' ),
				'view_item'         => __( 'View Location', 'ibb-rentals' ),
				'update_item'       => __( 'Update Location', 'ibb-rentals' ),
				'add_new_item'      => __( 'Add New Location', 'ibb-rentals' ),
				'new_item_name'     => __( 'New Location Name', 'ibb-rentals' ),
				'search_items'      => __( 'Search Locations', 'ibb-rentals' ),
				'not_found'         => __( 'No locations found.', 'ibb-rentals' ),
				'back_to_items'     => __( '← Back to locations', 'ibb-rentals' ),
				'desc_field_description'   => __( 'Optional context shown on the location archive page (e.g. a one-line intro to the area).', 'ibb-rentals' ),
				'parent_field_description' => __( 'Assign a parent region to create a hierarchy — for example, "Bali" could be the parent of "Seminyak" and "Ubud".', 'ibb-rentals' ),
				'slug_field_description'   => __( 'The URL-friendly version of the location name (lowercase, letters, numbers and hyphens only).', 'ibb-rentals' ),
				'name_field_description'   => __( 'The location name as it appears to guests browsing properties.', 'ibb-rentals' ),
			],
			'public'             => true,
			'show_in_rest'       => true,
			'hierarchical'       => true,
			'show_admin_column'  => true,
			'rewrite'            => [ 'slug' => 'location' ],
		] );

		register_taxonomy( self::TAX_PROPERTY_TYPE, [ self::POST_TYPE ], [
			'labels'             => [
				'name'              => __( 'Property Types', 'ibb-rentals' ),
				'singular_name'     => __( 'Property Type', 'ibb-rentals' ),
				'menu_name'         => __( 'Property Types', 'ibb-rentals' ),
				'all_items'         => __( 'All Property Types', 'ibb-rentals' ),
				'parent_item'       => __( 'Parent Type', 'ibb-rentals' ),
				'parent_item_colon' => __( 'Parent Type:', 'ibb-rentals' ),
				'edit_item'         => __( 'Edit Property Type', 'ibb-rentals' ),
				'view_item'         => __( 'View Property Type', 'ibb-rentals' ),
				'update_item'       => __( 'Update Property Type', 'ibb-rentals' ),
				'add_new_item'      => __( 'Add New Property Type', 'ibb-rentals' ),
				'new_item_name'     => __( 'New Property Type Name', 'ibb-rentals' ),
				'search_items'      => __( 'Search Property Types', 'ibb-rentals' ),
				'not_found'         => __( 'No property types found.', 'ibb-rentals' ),
				'back_to_items'     => __( '← Back to property types', 'ibb-rentals' ),
				'desc_field_description'   => __( 'Optional summary describing this type of property — shown on its archive page.', 'ibb-rentals' ),
				'parent_field_description' => __( 'Assign a parent type to create a hierarchy — for example, "Villa" could be the parent of "Beach Villa" and "Garden Villa".', 'ibb-rentals' ),
				'slug_field_description'   => __( 'The URL-friendly version of the type name (lowercase, letters, numbers and hyphens only).', 'ibb-rentals' ),
				'name_field_description'   => __( 'The property-type name as it appears to guests (e.g. Villa, Apartment, Cabin, Bungalow).', 'ibb-rentals' ),
			],
			'public'             => true,
			'show_in_rest'       => true,
			'hierarchical'       => true,
			'show_admin_column'  => true,
			'rewrite'            => [ 'slug' => 'property-type' ],
		] );
	}
}
