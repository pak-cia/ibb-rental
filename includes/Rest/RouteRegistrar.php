<?php
/**
 * Single point of registration for every REST route the plugin exposes.
 *
 * All routes live under `/wp-json/ibb-rentals/v1/`. Capability checks are
 * declared per-route — public read endpoints (availability/quote/ical) are
 * open by design; mutations require `manage_woocommerce`.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Rest;

use IBB\Rentals\Plugin;
use IBB\Rentals\Rest\Controllers\AvailabilityController;
use IBB\Rentals\Rest\Controllers\FeedsController;
use IBB\Rentals\Rest\Controllers\IcalController;
use IBB\Rentals\Rest\Controllers\QuoteController;

defined( 'ABSPATH' ) || exit;

final class RouteRegistrar {

	public const NAMESPACE = 'ibb-rentals/v1';

	public function __construct(
		private Plugin $plugin,
	) {}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new AvailabilityController( $this->plugin->availability_service() ) )->register( self::NAMESPACE );
		( new QuoteController(
			$this->plugin->availability_service(),
			$this->plugin->pricing_service()
		) )->register( self::NAMESPACE );
		( new IcalController( $this->plugin->ical_exporter() ) )->register( self::NAMESPACE );
		( new FeedsController( $this->plugin->feed_repo(), $this->plugin->ical_importer() ) )->register( self::NAMESPACE );
	}
}
