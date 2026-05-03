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
use IBB\Rentals\Emails\BookingReminderEmail;
use IBB\Rentals\Cron\Jobs\ChargeBalanceJob;
use IBB\Rentals\Cron\Jobs\CleanupHoldsJob;
use IBB\Rentals\Cron\Jobs\ImportFeedJob;
use IBB\Rentals\Cron\Jobs\ReminderEmailJob;
use IBB\Rentals\Cron\Jobs\SendPaymentLinkJob;
use IBB\Rentals\Cron\Jobs\SyncClickUpJob;
use IBB\Rentals\Services\ClickUpService;
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
use IBB\Rentals\Woo\WebhookTopics;

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
		( new WebhookTopics( $this->booking_repo() ) )->register();

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

		add_action( Hooks::AS_CLEANUP_HOLDS,  [ $this, 'run_cleanup_holds' ] );
		add_action( Hooks::AS_CHARGE_BALANCE, [ $this, 'run_charge_balance' ] );
		add_action( Hooks::AS_SEND_PAYMENT_LINK, [ $this, 'run_send_payment_link' ], 10, 2 );
		add_action( Hooks::AS_IMPORT_FEED,    [ $this, 'run_import_feed' ] );
		add_action( Hooks::AS_SEND_REMINDER,  [ $this, 'run_send_reminder' ] );
		add_action( Hooks::AS_SYNC_CLICKUP,   [ $this, 'run_sync_clickup' ] );

		// Auto-schedule ClickUp sync if configured and no recurring action exists yet.
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$settings = (array) get_option( 'ibb_rentals_settings', [] );
			if ( ! empty( $settings['clickup_api_token'] ) && ! empty( $settings['clickup_list_id'] ) ) {
				$interval = max( 300, (int) ( $settings['clickup_sync_interval'] ?? 3600 ) );
				if ( ! as_next_scheduled_action( Hooks::AS_SYNC_CLICKUP, [], Hooks::AS_GROUP ) ) {
					as_schedule_recurring_action( time() + 60, $interval, Hooks::AS_SYNC_CLICKUP, [], Hooks::AS_GROUP );
				}
			}
		}

		add_action( Hooks::BOOKING_CREATED, [ $this, 'schedule_balance_flow' ], 10, 4 );
		add_action( Hooks::BOOKING_CREATED, [ $this, 'schedule_reminder' ],     30, 4 );

		add_filter( 'woocommerce_email_classes', [ $this, 'register_emails' ] );

		// Eagerly initialise WC email classes so BookingConfirmationEmail's
		// BOOKING_CREATED hook is registered before any order-status transition fires.
		// woocommerce_email_classes is lazy (fires inside WC_Emails::get_emails()),
		// so without this the hook misses on the same request as the booking.
		add_action( 'woocommerce_init', static function (): void {
			WC()->mailer()->get_emails();
		}, 1 );

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

	public function run_send_reminder( int $booking_id ): void {
		( new ReminderEmailJob() )->handle( $booking_id );
	}

	public function run_sync_clickup(): void {
		( new SyncClickUpJob( $this->clickup_service() ) )->handle();
	}

	public function schedule_reminder( int $booking_id, \WC_Order $_order, \WC_Order_Item_Product $_item, string $_payment_mode ): void {
		$booking = $this->booking_repo()->find_by_id( $booking_id );
		if ( ! $booking || empty( $booking['checkin'] ) ) {
			return;
		}
		// 09:00 site time, 3 days before check-in.
		$tz        = new \DateTimeZone( wp_timezone_string() );
		$checkin   = new \DateTimeImmutable( (string) $booking['checkin'] . ' 09:00:00', $tz );
		$send_at   = $checkin->modify( '-3 days' );
		if ( $send_at->getTimestamp() > time() && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $send_at->getTimestamp(), Hooks::AS_SEND_REMINDER, [ $booking_id ], Hooks::AS_GROUP );
		}
	}

	/** @param array<string, \WC_Email> $emails */
	public function register_emails( array $emails ): array {
		$emails['IBB_Booking_Confirmation'] = new BookingConfirmationEmail();
		$emails['IBB_Booking_Reminder']     = new BookingReminderEmail();
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

	public function clickup_service( string $api_token_override = '' ): ClickUpService {
		$s       = (array) get_option( 'ibb_rentals_settings', [] );
		$tag_map = [];
		if ( ! empty( $s['clickup_tag_map'] ) ) {
			$decoded = json_decode( (string) $s['clickup_tag_map'], true );
			if ( is_array( $decoded ) ) {
				$tag_map = $decoded;
			}
		}
		if ( empty( $tag_map ) ) {
			$tag_map = [
				'abnb'    => 'airbnb',
				'airbnb'  => 'airbnb',
				'agoda'   => 'agoda',
				'booking' => 'booking',
				'vrbo'    => 'vrbo',
				'expedia' => 'expedia',
				'direct'  => 'direct',
				'manual'  => 'manual',
			];
		}
		// Unit-code → property-ID map. Stored as JSON so existing array_merge save flow stays simple.
		$unit_map = [];
		if ( ! empty( $s['clickup_unit_property_map'] ) ) {
			$decoded = json_decode( (string) $s['clickup_unit_property_map'], true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $code => $pid ) {
					$unit_map[ strtolower( (string) $code ) ] = (int) $pid;
				}
			}
		}

		// Source-allowlist: ClickUp may auto-create blocks for these sources
		// when no existing block matches the task. Configured at
		// Rentals → Settings → ClickUp → "Create blocks for".
		$create_sources = [];
		if ( ! empty( $s['clickup_create_sources'] ) ) {
			$decoded = json_decode( (string) $s['clickup_create_sources'], true );
			if ( is_array( $decoded ) ) {
				$create_sources = array_values( array_map( 'strval', $decoded ) );
			}
		}

		// Status filter: only fetch tasks in these ClickUp statuses. CSV in
		// settings, default to the active workflow statuses (everything that
		// represents a current or recent booking). When empty, no filter is
		// applied — the API returns every status.
		$sync_statuses = [];
		$raw_statuses  = (string) ( $s['clickup_sync_statuses'] ?? 'upcoming, currently staying, checked out, cancelled' );
		foreach ( explode( ',', $raw_statuses ) as $piece ) {
			$piece = trim( $piece );
			if ( $piece !== '' ) {
				$sync_statuses[] = $piece;
			}
		}

		// Not cached: settings may change between AS invocations.
		// $api_token_override lets the cascading-dropdown AJAX endpoints look up the hierarchy
		// using a token the user has typed in but not yet saved.
		return new ClickUpService(
			api_token:         $api_token_override !== '' ? $api_token_override : (string) ( $s['clickup_api_token'] ?? '' ),
			list_id:           (string) ( $s['clickup_list_id']      ?? '' ),
			workspace_id:      (string) ( $s['clickup_workspace_id'] ?? '' ),
			tag_source_map:    $tag_map,
			unit_property_map: $unit_map,
			logger:            $this->logger(),
			create_sources:    $create_sources,
			sync_statuses:     $sync_statuses,
		);
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
