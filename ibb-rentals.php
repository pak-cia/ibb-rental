<?php
/**
 * Plugin Name:       IBB Rentals
 * Plugin URI:        https://github.com/ibb/ibb-rentals
 * Description:       Tourism vacation-rental property management for WooCommerce — direct bookings, iCal calendar sync (Airbnb, Booking.com, Agoda, VRBO), seasonal pricing, deposit + balance payments.
 * Version:           0.10.2
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            IBB
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ibb-rentals
 * Domain Path:       /languages
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'IBB_RENTALS_VERSION', '0.10.2' );
define( 'IBB_RENTALS_FILE', __FILE__ );
define( 'IBB_RENTALS_DIR', plugin_dir_path( __FILE__ ) );
define( 'IBB_RENTALS_URL', plugin_dir_url( __FILE__ ) );
define( 'IBB_RENTALS_BASENAME', plugin_basename( __FILE__ ) );
define( 'IBB_RENTALS_MIN_PHP', '8.1' );
define( 'IBB_RENTALS_MIN_WP', '6.5' );
define( 'IBB_RENTALS_MIN_WC', '9.0' );

if ( file_exists( IBB_RENTALS_DIR . 'vendor/autoload.php' ) ) {
	require IBB_RENTALS_DIR . 'vendor/autoload.php';
}

require IBB_RENTALS_DIR . 'includes/Autoloader.php';
\IBB\Rentals\Autoloader::register();

add_action( 'before_woocommerce_init', static function (): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', IBB_RENTALS_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', IBB_RENTALS_FILE, true );
	}
} );

register_activation_hook( IBB_RENTALS_FILE, [ \IBB\Rentals\Setup\Installer::class, 'activate' ] );
register_deactivation_hook( IBB_RENTALS_FILE, [ \IBB\Rentals\Setup\Installer::class, 'deactivate' ] );

add_action( 'plugins_loaded', static function (): void {
	$requirements = new \IBB\Rentals\Setup\Requirements();
	if ( ! $requirements->are_met() ) {
		add_action( 'admin_notices', [ $requirements, 'render_failure_notice' ] );
		return;
	}

	\IBB\Rentals\Plugin::instance()->boot();
}, 20 );
