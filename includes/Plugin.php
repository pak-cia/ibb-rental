<?php
/**
 * Service container and entry point for the plugin.
 *
 * Lazy-loads each subsystem on `boot()` and exposes them as named services.
 * Kept intentionally simple — a Pimple-style DI container is overkill for
 * a single-purpose plugin and adds an autoload dependency.
 */

declare( strict_types=1 );

namespace IBB\Rentals;

use IBB\Rentals\Admin\AdminCalendar;
use IBB\Rentals\Admin\Menu;
use IBB\Rentals\Admin\PropertyMetaboxes;
use IBB\Rentals\Emails\BookingConfirmationEmail;
use IBB\Rentals\Cron\Jobs\ChargeBalanceJob;
use IBB\Rentals\Cron\Jobs\CleanupHoldsJob;
use IBB\Rentals\Cron\Jobs\ImportFeedJob;
use IBB\Rentals\Cron\Jobs\SendPaymentLinkJob;
use IBB\Rentals\Frontend\Assets;
use IBB\Rentals\Frontend\Blocks;
use IBB\Rentals\Frontend\Shortcodes;
use IBB\Rentals\Frontend\TemplateLoader;
use IBB\Rentals\Integrations\Elementor\Module as ElementorModule;
use IBB\Rentals\Ical\Exporter;
use IBB\Rentals\Ical\FeedScheduler;
use IBB\Rentals\Ical\Importer;
use IBB\Rentals\Ical\Parser;
use IBB\Rentals\Rest\RouteRegistrar;
use IBB\Rentals\PostTypes\PropertyPostType;
use IBB\Rentals\Repositories\AvailabilityRepository;
use IBB\Rentals\Repositories\BookingRepository;
use IBB\Rentals\Repositories\FeedRepository;
use IBB\Rentals\Repositories\RateRepository;
use IBB\Rentals\Setup\Installer;
use IBB\Rentals\Setup\Migrations;
use IBB\Rentals\Services\AvailabilityService;
use IBB\Rentals\Services\BalanceService;
use IBB\Rentals\Services\BookingService;
use IBB\Rentals\Services\PricingService;
use IBB\Rentals\Support\Hooks;
use IBB\Rentals\Support\Logger;
use IBB\Rentals\Woo\BookingProductType;
use IBB\Rentals\Woo\CartHandler;
use IBB\Rentals\Woo\GatewayCapabilities;
use IBB\Rentals\Woo\OrderObserver;
use IBB\Rentals\Woo\ProductSync;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?self $instance = null;

	/** @var array<string, object> */
	private array $services = [];

	private bool $booted = false;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	private function __construct() {}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'ibb-rentals', false, dirname( IBB_RENTALS_BASENAME ) . '/languages' );

		// Self-heal: if migrations haven't run (plugin file dropped in without
		// activate hook firing — e.g. on Local-by-Flywheel symlink installs),
		// run them now and queue a rewrite flush.
		if ( (int) get_option( Migrations::OPTION_KEY, 0 ) < Migrations::LATEST_VERSION ) {
			Migrations::run_to_latest();
			update_option( 'ibb_rentals_flush_rewrites', 1, false );
		}

		( new PropertyPostType() )->register();

		add_action( 'init', [ Installer::class, 'maybe_flush_rewrites' ], 100 );

		( new BookingProductType() )->register();
		( new ProductSync() )->register();
		( new CartHandler( $this->availability_repo() ) )->register();
		( new OrderObserver( $this->booking_service() ) )->register();

		( new FeedScheduler( $this->feed_repo() ) )->register();
		( new RouteRegistrar( $this ) )->register();

		( new Shortcodes() )->register();
		( new TemplateLoader() )->register();
		( new Blocks() )->register();
		( new ElementorModule() )->register();

		if ( is_admin() ) {
			( new PropertyMetaboxes(
				$this->rate_repo(),
				$this->feed_repo(),
				$this->ical_exporter(),
				$this->gateway_capabilities()
			) )->register();
			( new Menu( $this->feed_repo(), $this->gateway_capabilities() ) )->register();
			( new AdminCalendar() )->register();
		} else {
			( new Assets() )->register();
		}

		add_action( Hooks::AS_CLEANUP_HOLDS, [ $this, 'run_cleanup_holds' ] );
		add_action( Hooks::AS_CHARGE_BALANCE, [ $this, 'run_charge_balance' ] );
		add_action( Hooks::AS_SEND_PAYMENT_LINK, [ $this, 'run_send_payment_link' ], 10, 2 );
		add_action( Hooks::AS_IMPORT_FEED, [ $this, 'run_import_feed' ] );

		add_action( Hooks::BOOKING_CREATED, [ $this, 'schedule_balance_flow' ], 10, 4 );

		add_filter( 'woocommerce_email_classes', [ $this, 'register_emails' ] );

		do_action( Hooks::BOOTED, $this );
	}

	public function run_import_feed( int $feed_id ): void {
		( new ImportFeedJob( $this->ical_importer() ) )->handle( $feed_id );
	}

	public function run_cleanup_holds(): void {
		( new CleanupHoldsJob( $this->availability_repo() ) )->handle();
	}

	public function run_charge_balance( int $booking_id ): void {
		( new ChargeBalanceJob( $this->balance_service() ) )->handle( $booking_id );
	}

	public function run_send_payment_link( int $booking_id, string $kind = 'first' ): void {
		( new SendPaymentLinkJob( $this->balance_service() ) )->handle( $booking_id, $kind );
	}

	/** @param array<string, \WC_Email> $emails */
	public function register_emails( array $emails ): array {
		$emails['IBB_Booking_Confirmation'] = new BookingConfirmationEmail();
		return $emails;
	}

	public function schedule_balance_flow( int $booking_id, \WC_Order $_order, \WC_Order_Item_Product $_item, string $payment_mode ): void {
		if ( $payment_mode !== 'deposit' ) {
			return;
		}
		$this->balance_service()->schedule_for_booking( $booking_id );
	}

	public function set( string $id, object $service ): void {
		$this->services[ $id ] = $service;
	}

	public function get( string $id ): ?object {
		return $this->services[ $id ] ?? null;
	}

	public function logger(): Logger {
		return $this->services['logger'] ??= new Logger();
	}

	public function availability_repo(): AvailabilityRepository {
		return $this->services['availability_repo'] ??= new AvailabilityRepository();
	}

	public function rate_repo(): RateRepository {
		return $this->services['rate_repo'] ??= new RateRepository();
	}

	public function booking_repo(): BookingRepository {
		return $this->services['booking_repo'] ??= new BookingRepository();
	}

	public function feed_repo(): FeedRepository {
		return $this->services['feed_repo'] ??= new FeedRepository();
	}

	public function availability_service(): AvailabilityService {
		return $this->services['availability_service'] ??= new AvailabilityService( $this->availability_repo() );
	}

	public function pricing_service(): PricingService {
		return $this->services['pricing_service'] ??= new PricingService( $this->rate_repo() );
	}

	public function booking_service(): BookingService {
		return $this->services['booking_service'] ??= new BookingService(
			$this->availability_repo(),
			$this->booking_repo(),
			$this->logger()
		);
	}

	public function gateway_capabilities(): GatewayCapabilities {
		return $this->services['gateway_capabilities'] ??= new GatewayCapabilities();
	}

	public function balance_service(): BalanceService {
		return $this->services['balance_service'] ??= new BalanceService(
			$this->booking_repo(),
			$this->gateway_capabilities(),
			$this->logger()
		);
	}

	public function ical_parser(): Parser {
		return $this->services['ical_parser'] ??= new Parser();
	}

	public function ical_exporter(): Exporter {
		return $this->services['ical_exporter'] ??= new Exporter( $this->availability_repo() );
	}

	public function ical_importer(): Importer {
		return $this->services['ical_importer'] ??= new Importer(
			$this->feed_repo(),
			$this->availability_repo(),
			$this->ical_parser(),
			$this->logger()
		);
	}
}
