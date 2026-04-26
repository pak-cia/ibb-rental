<?php
/**
 * Mirrors each `ibb_property` post to a hidden `ibb_booking` product.
 *
 * The product is the unit WooCommerce understands; the property is the unit
 * we expose to admins and guests. Keeping them in lockstep means orders,
 * reports, coupons, and tax all work without WC ever knowing about properties.
 *
 * Visibility is forced to `hidden` so rentals don't appear in /shop loops —
 * front-end discovery happens via the property archive (/properties/) and
 * the search shortcode.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Woo;

use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class ProductSync {

	public const META_PROPERTY_ID = '_ibb_property_id';

	public function register(): void {
		add_action( 'save_post_' . PropertyPostType::POST_TYPE, [ $this, 'sync' ], 20, 3 );
		add_action( 'wp_trash_post', [ $this, 'on_trash' ] );
		add_action( 'before_delete_post', [ $this, 'on_delete' ] );
		add_action( 'untrashed_post', [ $this, 'on_untrash' ] );

		// Lock the mirrored product against direct edits.
		add_filter( 'user_has_cap', [ $this, 'block_direct_edits' ], 10, 4 );
		add_action( 'admin_notices', [ $this, 'maybe_show_lock_notice' ] );
		add_filter( 'post_row_actions', [ $this, 'filter_row_actions' ], 10, 2 );
	}

	private function is_mirrored_product( int $post_id ): bool {
		return $post_id > 0 && (int) get_post_meta( $post_id, self::META_PROPERTY_ID, true ) > 0;
	}

	/**
	 * Strip edit/delete capabilities for the mirrored product so direct admin
	 * edits and bulk actions can't desync it from its property.
	 *
	 * @param array<string,bool> $allcaps
	 * @param array<int,string>  $caps
	 * @param array<int,mixed>   $args
	 * @return array<string,bool>
	 */
	public function block_direct_edits( array $allcaps, array $caps, array $args, ?\WP_User $user ): array {
		if ( count( $args ) < 3 || ! is_numeric( $args[2] ) ) {
			return $allcaps;
		}
		$post_id = (int) $args[2];
		if ( ! $this->is_mirrored_product( $post_id ) ) {
			return $allcaps;
		}
		$cap = (string) $args[0];
		if ( in_array( $cap, [ 'edit_post', 'edit_product', 'delete_post', 'delete_product' ], true ) ) {
			$allcaps[ $cap ] = false;
			foreach ( $caps as $required ) {
				$allcaps[ $required ] = false;
			}
		}
		return $allcaps;
	}

	public function maybe_show_lock_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== 'product' ) {
			return;
		}
		$post_id = (int) ( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $this->is_mirrored_product( $post_id ) ) {
			return;
		}
		$property_id   = (int) get_post_meta( $post_id, self::META_PROPERTY_ID, true );
		$property_link = get_edit_post_link( $property_id );
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Auto-managed product', 'ibb-rentals' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'This product is auto-mirrored from a vacation-rental property. Editing it here has no effect — changes are overwritten by the property post on save.', 'ibb-rentals' );
		if ( $property_link ) {
			echo ' <a href="' . esc_url( $property_link ) . '">' . esc_html__( 'Edit the property instead →', 'ibb-rentals' ) . '</a>';
		}
		echo '</p></div>';
	}

	/**
	 * @param array<string,string> $actions
	 * @return array<string,string>
	 */
	public function filter_row_actions( array $actions, \WP_Post $post ): array {
		if ( $post->post_type !== 'product' || ! $this->is_mirrored_product( $post->ID ) ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'], $actions['edit'], $actions['trash'], $actions['delete'] );
		$property_id   = (int) get_post_meta( $post->ID, self::META_PROPERTY_ID, true );
		$property_link = get_edit_post_link( $property_id );
		if ( $property_link ) {
			$actions['edit_property'] = '<a href="' . esc_url( $property_link ) . '">' . esc_html__( 'Edit property', 'ibb-rentals' ) . '</a>';
		}
		return $actions;
	}

	public function sync( int $post_id, \WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( $post->post_status === 'auto-draft' || $post->post_status === 'trash' ) {
			return;
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product_id = (int) get_post_meta( $post_id, '_ibb_linked_product_id', true );
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product instanceof \WC_Product ) {
			$product_id = $this->create_product( $post );
			update_post_meta( $post_id, '_ibb_linked_product_id', $product_id );
			return;
		}

		$product->set_name( $post->post_title );
		$product->set_status( $post->post_status === 'publish' ? 'publish' : 'private' );
		$product->set_short_description( $post->post_excerpt );
		$product->set_description( $post->post_content );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_regular_price( (string) ( get_post_meta( $post_id, '_ibb_base_rate', true ) ?: '0' ) );
		$product->set_price( (string) ( get_post_meta( $post_id, '_ibb_base_rate', true ) ?: '0' ) );
		$product->update_meta_data( self::META_PROPERTY_ID, $post_id );
		$product->update_meta_data( '_ibb_base_rate', (string) get_post_meta( $post_id, '_ibb_base_rate', true ) );
		$product->save();
	}

	private function create_product( \WP_Post $post ): int {
		$product = new \WC_Product_IBB_Booking();
		$product->set_name( $post->post_title );
		$product->set_status( $post->post_status === 'publish' ? 'publish' : 'private' );
		$product->set_short_description( $post->post_excerpt );
		$product->set_description( $post->post_content );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( false );
		$product->set_regular_price( (string) ( get_post_meta( $post->ID, '_ibb_base_rate', true ) ?: '0' ) );
		$product->set_price( (string) ( get_post_meta( $post->ID, '_ibb_base_rate', true ) ?: '0' ) );
		$product->update_meta_data( self::META_PROPERTY_ID, $post->ID );
		$product->update_meta_data( '_ibb_base_rate', (string) get_post_meta( $post->ID, '_ibb_base_rate', true ) );
		return (int) $product->save();
	}

	public function on_trash( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== PropertyPostType::POST_TYPE ) {
			return;
		}
		$product_id = (int) get_post_meta( $post_id, '_ibb_linked_product_id', true );
		if ( $product_id ) {
			wp_trash_post( $product_id );
		}
	}

	public function on_untrash( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== PropertyPostType::POST_TYPE ) {
			return;
		}
		$product_id = (int) get_post_meta( $post_id, '_ibb_linked_product_id', true );
		if ( $product_id ) {
			wp_untrash_post( $product_id );
		}
	}

	public function on_delete( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== PropertyPostType::POST_TYPE ) {
			return;
		}
		$product_id = (int) get_post_meta( $post_id, '_ibb_linked_product_id', true );
		if ( $product_id ) {
			wp_delete_post( $product_id, true );
		}
	}
}
