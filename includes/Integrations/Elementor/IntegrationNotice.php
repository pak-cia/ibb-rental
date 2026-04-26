<?php
/**
 * Admin notice for the Elementor integration.
 *
 * Surfaces what the integration provides to admins viewing IBB Rentals
 * pages. Three states:
 *
 *   - Elementor not active   → silent. Builders are optional; don't pester.
 *   - Elementor Free         → tip linking to the runbook + flag that
 *                              dynamic tags need Pro for most controls.
 *   - Elementor Pro          → tip linking to the runbook (dynamic tags +
 *                              Loop Grid query support are available).
 *
 * Dismissed-state is per-user, persists via user meta. Re-emerges if the
 * Elementor Pro state changes (key includes a Pro-presence suffix), so a
 * user upgrading to Pro sees the new tip.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

final class IntegrationNotice {

	private const META_KEY = 'ibb_rentals_elementor_notice_dismissed';

	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
		add_action( 'wp_ajax_ibb_rentals_dismiss_elementor_notice', [ $this, 'dismiss' ] );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Only show on the screens where this matters: our own admin pages,
		// the Plugins screen, and the Properties list. Avoids notice spam
		// across the whole admin.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! $this->is_relevant_screen( $screen ) ) {
			return;
		}

		// Silently no-op when Elementor isn't loaded — they may be a non-builder user.
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		$has_pro = $this->has_pro();
		$key     = $has_pro ? 'pro' : 'free';

		$dismissed = (string) get_user_meta( get_current_user_id(), self::META_KEY, true );
		if ( $dismissed === $key ) {
			return;
		}

		$nonce = wp_create_nonce( 'ibb_rentals_elementor_notice' );

		echo '<div class="notice notice-info is-dismissible ibb-rentals-elementor-notice" data-state="' . esc_attr( $key ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
		echo '<p><strong>' . esc_html__( 'IBB Rentals + Elementor', 'ibb-rentals' ) . '</strong></p>';

		if ( $has_pro ) {
			echo '<p>' . esc_html__( 'Four widgets, eleven property dynamic tags, and a Loop Grid query are registered. Build single-property templates and property archives in Elementor Theme Builder using native widgets bound to property data.', 'ibb-rentals' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Four widgets are registered (Booking Form, Property Details, Property Gallery, Property Carousel). Most dynamic-tag bindings (Heading text, Image src, Button URL) require Elementor Pro — without it, only widgets that natively expose dynamic-bindable controls will see the IBB Rentals tags.', 'ibb-rentals' ) . '</p>';
		}

		echo '</div>';
		echo "<script>(function(){
			var n=document.querySelector('.ibb-rentals-elementor-notice');
			if(!n) return;
			n.addEventListener('click',function(e){
				if(!e.target.classList.contains('notice-dismiss')) return;
				var fd=new FormData();
				fd.append('action','ibb_rentals_dismiss_elementor_notice');
				fd.append('nonce',n.dataset.nonce);
				fd.append('state',n.dataset.state);
				fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'});
			});
		})();</script>";
	}

	public function dismiss(): void {
		check_ajax_referer( 'ibb_rentals_elementor_notice', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$state = isset( $_POST['state'] ) ? sanitize_key( (string) $_POST['state'] ) : '';
		if ( ! in_array( $state, [ 'free', 'pro' ], true ) ) {
			wp_send_json_error( [ 'message' => 'bad_state' ], 400 );
		}
		update_user_meta( get_current_user_id(), self::META_KEY, $state );
		wp_send_json_success();
	}

	private function is_relevant_screen( \WP_Screen $screen ): bool {
		// Our own pages: post-type list/edit, settings, etc.
		if ( in_array( $screen->post_type, [ 'ibb_property' ], true ) ) {
			return true;
		}
		if ( strpos( (string) $screen->id, 'ibb-rentals' ) !== false ) {
			return true;
		}
		// Plugins page (where users land right after activating Elementor).
		if ( $screen->id === 'plugins' ) {
			return true;
		}
		return false;
	}

	private function has_pro(): bool {
		return defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\\ElementorPro\\Plugin' );
	}
}
