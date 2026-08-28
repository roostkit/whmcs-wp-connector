<?php
/**
 * REST API controller for WHMCS domain availability check.
 *
 * @package RoostKit\WhmcsConnector\Api
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Api;

use RoostKit\WhmcsConnector\Cache\CacheManager;

/**
 * Handles `/wp-json/whmcs-connector/v1/domain-check` requests.
 */
class DomainCheckController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'whmcs-connector/v1';

	/**
	 * REST route.
	 *
	 * @var string
	 */
	public const REST_ROUTE = '/domain-check';

	/**
	 * WHMCS API client instance.
	 *
	 * @var ApiClientInterface|null
	 */
	private ?ApiClientInterface $api_client;

	/**
	 * Cache manager instance.
	 *
	 * @var CacheManager|null
	 */
	private ?CacheManager $cache_manager;

	/**
	 * Configured WHMCS base URL.
	 *
	 * @var string
	 */
	private string $whmcs_url;

	/**
	 * Default suggestion TLDs.
	 *
	 * @var array<string>
	 */
	private const DEFAULT_SUGGEST_TLDS = [ '.com', '.net', '.org', '.io', '.co' ];

	/**
	 * Constructor.
	 *
	 * @param ApiClientInterface|null $api_client    API client.
	 * @param CacheManager|null       $cache_manager Cache manager.
	 * @param string                  $whmcs_url      WHMCS URL.
	 */
	public function __construct(
		?ApiClientInterface $api_client,
		?CacheManager $cache_manager = null,
		string $whmcs_url = ''
	) {
		$this->api_client    = $api_client;
		$this->cache_manager = $cache_manager;
		$this->whmcs_url     = untrailingslashit( $whmcs_url );
	}

	/**
	 * Register REST routes on rest_api_init.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the domain check route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				[
					'methods'             => [ 'POST', 'GET' ],
					'callback'            => [ $this, 'handle_domain_check' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'domain'          => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'tld'             => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'suggest_tlds'    => [
							'required' => false,
						],
						'max_suggestions' => [
							'required'          => false,
							'type'              => 'integer',
							'minimum'           => 1,
							'maximum'           => 8,
							'default'           => 4,
							'sanitize_callback' => 'absint',
						],
						'default_tld'     => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '.com',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);
	}

	/**
	 * Handle domain check REST request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function handle_domain_check( \WP_REST_Request $request ): \WP_REST_Response {
		$raw_domain      = (string) $request->get_param( 'domain' );
		$raw_tld         = (string) ( $request->get_param( 'tld' ) ?? '' );
		$suggest         = $request->get_param( 'suggest_tlds' );
		$max_suggestions = max( 1, min( 8, (int) ( $request->get_param( 'max_suggestions' ) ?? 4 ) ) );
		$raw_default_tld = trim( (string) ( $request->get_param( 'default_tld' ) ?? '.com' ) );
		$default_tld     = str_starts_with( $raw_default_tld, '.' ) ? $raw_default_tld : '.' . $raw_default_tld;

		// Clean domain query.
		$clean_domain = strtolower( trim( $raw_domain ) );
		$clean_domain = (string) preg_replace( '#^https?://#i', '', $clean_domain );
		$clean_domain = (string) preg_replace( '#/.*$#', '', $clean_domain );
		$clean_domain = (string) preg_replace( '/[^a-z0-9\-\.]/i', '', $clean_domain );

		if ( empty( $clean_domain ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Please enter a valid domain name.', 'whmcs-connector' ),
				],
				400
			);
		}

		// Split into SLD and TLD.
		if ( str_contains( $clean_domain, '.' ) ) {
			$parts = explode( '.', $clean_domain, 2 );
			$sld   = $parts[0];
			$tld   = '.' . $parts[1];
		} else {
			$sld = $clean_domain;
			$tld = ! empty( $raw_tld ) ? ( str_starts_with( $raw_tld, '.' ) ? $raw_tld : '.' . $raw_tld ) : $default_tld;
		}

		$primary_domain = $sld . $tld;

		// Parse suggestion TLDs.
		$suggestion_tlds = self::DEFAULT_SUGGEST_TLDS;
		if ( is_array( $suggest ) ) {
			$suggestion_tlds = array_map(
				static function ( $t ): string {
					$t = trim( (string) $t );
					return str_starts_with( $t, '.' ) ? $t : '.' . $t;
				},
				$suggest
			);
		} elseif ( is_string( $suggest ) && ! empty( $suggest ) ) {
			$suggestion_tlds = array_map(
				static function ( $t ): string {
					$t = trim( $t );
					return str_starts_with( $t, '.' ) ? $t : '.' . $t;
				},
				explode( ',', $suggest )
			);
		}

		// Remove searched TLD from suggestions to avoid duplicates.
		$suggestion_tlds = array_values(
			array_filter(
				$suggestion_tlds,
				static fn( $item_tld ) => strtolower( (string) $item_tld ) !== strtolower( $tld )
			)
		);

		// Limit to configured max suggestions.
		$suggestion_tlds = array_slice( $suggestion_tlds, 0, $max_suggestions );

		// Check primary domain.
		$primary_result = $this->check_single_domain( $primary_domain );

		// Check suggestions.
		$suggestions = [];
		foreach ( $suggestion_tlds as $sug_tld ) {
			$sug_domain    = $sld . $sug_tld;
			$suggestions[] = $this->check_single_domain( $sug_domain );
		}

		return new \WP_REST_Response(
			[
				'success'     => true,
				'searched'    => $primary_result,
				'suggestions' => $suggestions,
			],
			200
		);
	}

	/**
	 * Check availability for a single domain name.
	 *
	 * @param string $domain Domain name (e.g. example.com).
	 * @return array<string, mixed>
	 */
	public function check_single_domain( string $domain ): array {
		$domain    = strtolower( trim( $domain ) );
		$cache_key = 'whois_' . md5( $domain );

		if ( null !== $this->cache_manager ) {
			$cached = $this->cache_manager->get( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$is_available = true;
		$price_str    = '';

		if ( null !== $this->api_client ) {
			try {
				$response = $this->api_client->call(
					'DomainWhois',
					[
						'domain' => $domain,
					]
				);

				$status = strtolower( (string) ( $response['status'] ?? '' ) );
				if ( 'available' === $status ) {
					$is_available = true;
				} elseif ( 'unavailable' === $status ) {
					$is_available = false;
				} else {
					// Fallback to result inspection.
					$is_available = ( 'success' === ( $response['result'] ?? '' ) && 'available' === $status );
				}
			} catch ( \Exception ) {
				// On API exception or failure, default to available for ordering flow.
				$is_available = true;
			}
		}

		// Pricing & Order URLs.
		$order_action = $is_available ? 'register' : 'transfer';
		$order_url    = ! empty( $this->whmcs_url )
			? $this->whmcs_url . '/cart.php?a=add&domain=' . $order_action . '&query=' . rawurlencode( $domain )
			: '#';

		$result = [
			'domain'    => $domain,
			'available' => $is_available,
			'price'     => $price_str,
			'order_url' => $order_url,
		];

		if ( null !== $this->cache_manager ) {
			$this->cache_manager->set( $cache_key, $result, 300 ); // Cache for 5 mins.
		}

		return $result;
	}
}
