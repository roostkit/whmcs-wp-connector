<?php
/**
 * Main plugin orchestrator.
 *
 * @package RoostKit\WhmcsConnector
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector;

use RoostKit\WhmcsConnector\Admin\Settings;
use RoostKit\WhmcsConnector\Admin\Notices;
use RoostKit\WhmcsConnector\Admin\Welcome;
use RoostKit\WhmcsConnector\Api\Client;
use RoostKit\WhmcsConnector\Blocks\BlockRegistrar;
use RoostKit\WhmcsConnector\Cache\CacheManager;
use RoostKit\WhmcsConnector\Api\ApiLog;
use RoostKit\WhmcsConnector\ProductRepository;
use RoostKit\WhmcsConnector\Security\Crypto;
use RoostKit\WhmcsConnector\Security\RateLimiter;
use RoostKit\WhmcsConnector\Shortcodes\LoginHandler;
use RoostKit\WhmcsConnector\Shortcodes\LoginShortcode;
use RoostKit\WhmcsConnector\Shortcodes\ClientAreaShortcode;
use RoostKit\WhmcsConnector\Shortcodes\PricingShortcode;
use RoostKit\WhmcsConnector\Shortcodes\PriceShortcode;
use RoostKit\WhmcsConnector\Shortcodes\NameShortcode;
use RoostKit\WhmcsConnector\Shortcodes\DescShortcode;
use RoostKit\WhmcsConnector\Shortcodes\OrderUrlShortcode;

/**
 * Plugin singleton — coordinates all subsystems.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Plugin settings array.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Get or create the singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/**
	 * Initialize all plugin subsystems.
	 */
	public function init(): void {
		$this->load_settings();
		$this->load_textdomain();

		// Core services.
		$crypto        = new Crypto();
		$cache_manager = new CacheManager( $this->get_cache_ttl() );
		$api_client    = $this->create_api_client( $crypto );

		// Admin.
		if ( is_admin() ) {
			$settings = new Settings( $crypto, $api_client, $cache_manager );
			$settings->register();

			$notices = new Notices();
			$notices->register();

			$welcome = new Welcome();
			$welcome->register();
		}

		// Repository service.
		$api_log = new ApiLog();
		$repo    = new ProductRepository( $api_client, $cache_manager, $api_log );

		// Login POST handler — must run on template_redirect, before output.
		$login_handler = new LoginHandler(
			$api_client,
			new RateLimiter(),
			$this->get_setting( 'whmcs_url' )
		);
		$login_handler->register();

		// Frontend shortcodes.
		$this->register_shortcodes( $api_client, $cache_manager, $repo, $api_log );

		// Dynamic link replacer (e.g. #whmcs-order-1).
		$link_replacer = new LinkReplacer( $this->get_setting( 'whmcs_url' ) );
		$link_replacer->register();

		// Gutenberg blocks.
		$block_registrar = new BlockRegistrar( $repo, $api_client, $this->get_setting( 'whmcs_url' ) );
		add_action( 'init', [ $block_registrar, 'register' ] );

		// REST API endpoints (e.g. domain checker, products).
		$domain_check = new \RoostKit\WhmcsConnector\Api\DomainCheckController(
			$api_client,
			$cache_manager,
			$this->get_setting( 'whmcs_url' )
		);
		$domain_check->register();

		$products_controller = new \RoostKit\WhmcsConnector\Api\ProductsController( $repo );
		$products_controller->register();

		// Pretty permalinks.
		$permalinks = new Permalinks();
		$permalinks->register();
	}

	/**
	 * Load plugin settings from the database.
	 */
	private function load_settings(): void {
		$defaults = [
			'whmcs_url'      => '',
			'api_identifier' => '',
			'api_secret'     => '',
			'api_access_key' => '',
			'cache_ttl'      => 900,
		];

		$stored = get_option( 'whmcs_connector_settings', [] );

		$this->settings = wp_parse_args(
			is_array( $stored ) ? $stored : [],
			$defaults
		);
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'whmcs-connector',
			false,
			dirname( plugin_basename( WHMCS_CONNECTOR_FILE ) ) . '/languages'
		);
	}

	/**
	 * Create the API client instance.
	 *
	 * @param Crypto $crypto Encryption service.
	 * @return Client|null Client instance or null if not configured.
	 */
	private function create_api_client( Crypto $crypto ): ?Client {
		$whmcs_url = $this->get_setting( 'whmcs_url' );
		if ( empty( $whmcs_url ) ) {
			return null;
		}
		return new Client( $whmcs_url, $crypto, $this->settings );
	}

	/**
	 * Register all shortcodes.
	 *
	 * @param Client|null       $api_client    API client instance.
	 * @param CacheManager      $cache_manager Cache manager instance.
	 * @param ProductRepository $repo          Product repository instance.
	 * @param ApiLog            $api_log       API log instance.
	 */
	private function register_shortcodes(
		?Client $api_client,
		CacheManager $cache_manager,
		ProductRepository $repo,
		ApiLog $api_log
	): void {
		$whmcs_url = $this->get_setting( 'whmcs_url' );

		// Free shortcodes.
		$login = new LoginShortcode( $api_client );
		$login->register();

		$client_area = new ClientAreaShortcode( $whmcs_url );
		$client_area->register();

		$pricing = new PricingShortcode( $repo, $api_log, $whmcs_url );
		$pricing->register();

		// Pro token shortcodes.
		$price = new PriceShortcode( $repo, $api_log, $whmcs_url );
		$price->register();

		$name = new NameShortcode( $repo, $api_log, $whmcs_url );
		$name->register();

		$desc = new DescShortcode( $repo, $api_log, $whmcs_url );
		$desc->register();

		$order_url = new OrderUrlShortcode( $whmcs_url );
		$order_url->register();
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Default fallback value.
	 * @return mixed
	 */
	public function get_setting( string $key, mixed $fallback = '' ): mixed {
		return $this->settings[ $key ] ?? $fallback;
	}

	/**
	 * Get the cache TTL in seconds.
	 *
	 * @return int
	 */
	private function get_cache_ttl(): int {
		$ttl = (int) $this->get_setting( 'cache_ttl', 900 );
		/**
		 * Filter the cache TTL for WHMCS API responses.
		 *
		 * @param int $ttl Cache TTL in seconds. Default 900 (15 minutes).
		 */
		return (int) apply_filters( 'whmcs_connector_cache_ttl', $ttl );
	}

	/**
	 * Check if the plugin is the Pro edition.
	 *
	 * @return bool
	 */
	public static function is_pro(): bool {
		$is_pro = ( defined( 'WHMCS_CONNECTOR_FORCE_PRO' ) && WHMCS_CONNECTOR_FORCE_PRO === true )
			|| ( defined( 'WHMCS_CONNECTOR_EDITION' ) && 'pro' === WHMCS_CONNECTOR_EDITION );
		return (bool) apply_filters( 'whmcs_connector_is_pro', $is_pro );
	}

	/**
	 * Check if the API connection is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->get_setting( 'whmcs_url' ) )
			&& ! empty( $this->get_setting( 'api_identifier' ) )
			&& ! empty( $this->get_setting( 'api_secret' ) );
	}
}
