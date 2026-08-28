<?php
/**
 * Product repository for fetching, caching, and formatting WHMCS product data.
 *
 * @package RoostKit\WhmcsConnector
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector;

use RoostKit\WhmcsConnector\Api\ApiClientInterface;
use RoostKit\WhmcsConnector\Api\ApiException;
use RoostKit\WhmcsConnector\Api\ApiLog;
use RoostKit\WhmcsConnector\Cache\CacheManager;

/**
 * Repository for WHMCS product and pricing data.
 *
 * Manages dual-layer caching (in-memory runtime cache + persistent transients)
 * to ensure that multiple shortcodes/blocks querying products on the same page
 * avoid duplicate API requests.
 */
class ProductRepository {

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
	 * API logger instance.
	 *
	 * @var ApiLog|null
	 */
	private ?ApiLog $api_log;

	/**
	 * Billing cycle labels.
	 *
	 * @var array<string, string>
	 */
	public const CYCLE_LABELS = [
		'monthly'      => 'Monthly',
		'quarterly'    => 'Quarterly',
		'semiannually' => 'Semi-Annually',
		'annually'     => 'Annually',
		'biennially'   => 'Biennially',
		'triennially'  => 'Triennially',
	];

	/**
	 * Billing cycle duration in months.
	 *
	 * @var array<string, int>
	 */
	public const CYCLE_MONTHS = [
		'monthly'      => 1,
		'quarterly'    => 3,
		'semiannually' => 6,
		'annually'     => 12,
		'biennially'   => 24,
		'triennially'  => 36,
	];

	/**
	 * In-memory runtime cache for product query results during the current request.
	 *
	 * @var array<string, array<int, array<string, mixed>>|null>
	 */
	private static array $runtime_queries = [];

	/**
	 * In-memory runtime cache for individual products indexed by PID.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $runtime_products = [];

	/**
	 * Constructor.
	 *
	 * @param ApiClientInterface|null $api_client    API client.
	 * @param CacheManager|null       $cache_manager Cache manager.
	 * @param ApiLog|null             $api_log       API log service.
	 */
	public function __construct(
		?ApiClientInterface $api_client,
		?CacheManager $cache_manager = null,
		?ApiLog $api_log = null
	) {
		$this->api_client    = $api_client;
		$this->cache_manager = $cache_manager;
		$this->api_log       = $api_log;
	}

	/**
	 * Get products from cache or WHMCS API.
	 *
	 * @param string|int $gid Group ID filter.
	 * @param string|int $pid Product ID filter.
	 * @return array<int, array<string, mixed>>|null Array of product data, or null on error.
	 */
	public function get_products( string|int $gid = '', string|int $pid = '' ): ?array {
		$gid_str   = (string) $gid;
		$pid_str   = (string) $pid;
		$cache_key = 'products_' . md5( $gid_str . '_' . $pid_str );

		if ( array_key_exists( $cache_key, self::$runtime_queries ) ) {
			return self::$runtime_queries[ $cache_key ];
		}

		if ( null !== $this->cache_manager ) {
			$cached = $this->cache_manager->get( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				self::$runtime_queries[ $cache_key ] = $cached;
				$this->index_products( $cached );
				return $cached;
			}
		}

		if ( null === $this->api_client ) {
			self::$runtime_queries[ $cache_key ] = null;
			return null;
		}

		$params = [];
		if ( ! empty( $gid_str ) ) {
			$params['gid'] = absint( $gid_str );
		}
		if ( ! empty( $pid_str ) ) {
			$params['pid'] = sanitize_text_field( $pid_str );
		}

		try {
			$response = $this->api_client->call( 'GetProducts', $params );
		} catch ( ApiException $e ) {
			if ( null !== $this->api_log ) {
				$this->api_log->log( 'GetProducts', $e->getMessage(), $e->get_http_status() );
			}
			self::$runtime_queries[ $cache_key ] = null;
			return null;
		}

		$products = $response['products']['product'] ?? [];

		if ( ! is_array( $products ) ) {
			self::$runtime_queries[ $cache_key ] = [];
			return [];
		}

		if ( null !== $this->cache_manager ) {
			$this->cache_manager->set( $cache_key, $products );
		}

		self::$runtime_queries[ $cache_key ] = $products;
		$this->index_products( $products );

		return $products;
	}

	/**
	 * Get a single product by PID.
	 *
	 * Checks runtime cache first to avoid redundant API or transient calls.
	 *
	 * @param int $pid Product ID.
	 * @return array<string, mixed>|null Product data or null if not found.
	 */
	public function get_product( int $pid ): ?array {
		if ( $pid <= 0 ) {
			return null;
		}

		if ( isset( self::$runtime_products[ $pid ] ) ) {
			return self::$runtime_products[ $pid ];
		}

		$products = $this->get_products( '', (string) $pid );
		if ( null === $products || empty( $products ) ) {
			return null;
		}

		foreach ( $products as $product ) {
			if ( isset( $product['pid'] ) && (int) $product['pid'] === $pid ) {
				self::$runtime_products[ $pid ] = $product;
				return $product;
			}
		}

		// If API returned a list without matching PID, fallback to first item if single lookup.
		if ( 1 === count( $products ) ) {
			self::$runtime_products[ $pid ] = $products[0];
			return $products[0];
		}

		return null;
	}

	/**
	 * Extract default currency pricing from a WHMCS product's pricing array.
	 *
	 * @param array<string, mixed> $pricing Pricing data from WHMCS product.
	 * @return array<string, string> Billing cycle => price amount map.
	 */
	public function get_default_currency_pricing( array $pricing ): array {
		$first_currency = reset( $pricing );

		if ( ! is_array( $first_currency ) ) {
			return [];
		}

		$result = [];
		foreach ( self::CYCLE_LABELS as $cycle => $label ) {
			if ( isset( $first_currency[ $cycle ] ) && (float) $first_currency[ $cycle ] >= 0 ) {
				$result[ $cycle ] = (string) $first_currency[ $cycle ];
			}
		}

		return $result;
	}

	/**
	 * Format a price with currency prefix/suffix.
	 *
	 * @param float                $amount  Price amount.
	 * @param array<string, mixed> $pricing Full pricing data (for currency detection).
	 * @return string Formatted price string.
	 */
	public function format_price( float $amount, array $pricing ): string {
		$first_currency = reset( $pricing );
		$prefix         = is_array( $first_currency ) ? ( $first_currency['prefix'] ?? '$' ) : '$';
		$suffix         = is_array( $first_currency ) ? ( $first_currency['suffix'] ?? '' ) : '';

		return $prefix . number_format( $amount, 2 ) . $suffix;
	}

	/**
	 * Index an array of products into the in-memory runtime cache.
	 *
	 * @param array<int, array<string, mixed>> $products Product list.
	 */
	private function index_products( array $products ): void {
		foreach ( $products as $product ) {
			if ( isset( $product['pid'] ) ) {
				$pid = (int) $product['pid'];
				if ( $pid > 0 ) {
					self::$runtime_products[ $pid ] = $product;
				}
			}
		}
	}

	/**
	 * Extract currency symbol (prefix) from product pricing.
	 *
	 * @param array<string, mixed> $pricing Pricing data array.
	 * @return string Currency symbol prefix (default '$').
	 */
	public function get_currency_symbol( array $pricing ): string {
		$first_currency = reset( $pricing );
		return is_array( $first_currency ) ? ( $first_currency['prefix'] ?? '$' ) : '$';
	}

	/**
	 * Extract base price and configurable option per-unit rates for a VPS product.
	 *
	 * Parses WHMCS product configurable options (CPU, RAM, Disk) if present in API response,
	 * or computes rates based on base product pricing.
	 *
	 * @param int $pid Product ID.
	 * @return array{base_price: float, cpu_rate: float, ram_rate: float, disk_rate: float, currency: string}
	 */
	public function get_vps_config_rates( int $pid ): array {
		$product = $this->get_product( $pid );
		$pricing = is_array( $product ) ? ( $product['pricing'] ?? [] ) : [];
		$symbol  = $this->get_currency_symbol( $pricing );

		$first_currency = reset( $pricing );
		$monthly_price  = is_array( $first_currency ) && isset( $first_currency['monthly'] )
			? (float) $first_currency['monthly']
			: 12.00;

		$cpu_rate    = 6.00;
		$ram_rate    = 3.50;
		$disk_rate   = 1.50;
		$cpu_opt_id  = 1;
		$ram_opt_id  = 2;
		$disk_opt_id = 3;

		// Check if WHMCS returned configoptions in product payload.
		if ( is_array( $product ) && ! empty( $product['configoptions']['configoption'] ) ) {
			$options = $product['configoptions']['configoption'];
			if ( is_array( $options ) ) {
				foreach ( $options as $opt ) {
					$opt_id   = (int) ( $opt['id'] ?? 0 );
					$opt_name = strtolower( (string) ( $opt['name'] ?? '' ) );
					$opt_type = (int) ( $opt['type'] ?? 0 );

					// WHMCS configurable option sub-options pricing.
					$sub_opts       = $opt['options']['option'] ?? [];
					$first_sub      = is_array( $sub_opts ) ? reset( $sub_opts ) : null;
					$sub_pricing    = is_array( $first_sub ) ? ( $first_sub['pricing'] ?? [] ) : [];
					$sub_first_curr = is_array( $sub_pricing ) ? reset( $sub_pricing ) : null;
					$sub_monthly    = is_array( $sub_first_curr ) && isset( $sub_first_curr['monthly'] )
						? (float) $sub_first_curr['monthly']
						: 0.0;

					if ( str_contains( $opt_name, 'cpu' ) || str_contains( $opt_name, 'core' ) ) {
						if ( $opt_id > 0 ) {
							$cpu_opt_id = $opt_id;
						}
						if ( $sub_monthly > 0 ) {
							$cpu_rate = $sub_monthly;
						}
					} elseif ( str_contains( $opt_name, 'ram' ) || str_contains( $opt_name, 'memory' ) ) {
						if ( $opt_id > 0 ) {
							$ram_opt_id = $opt_id;
						}
						if ( $sub_monthly > 0 ) {
							$ram_rate = $sub_monthly;
						}
					} elseif ( str_contains( $opt_name, 'disk' ) || str_contains( $opt_name, 'storage' ) || str_contains( $opt_name, 'ssd' ) || str_contains( $opt_name, 'nvme' ) ) {
						if ( $opt_id > 0 ) {
							$disk_opt_id = $opt_id;
						}
						if ( $sub_monthly > 0 ) {
							$disk_rate = $sub_monthly;
						}
					}
				}
			}
		}

		return [
			'base_price'  => max( 0.0, $monthly_price ),
			'cpu_rate'    => $cpu_rate,
			'ram_rate'    => $ram_rate,
			'disk_rate'   => $disk_rate,
			'cpu_opt_id'  => $cpu_opt_id,
			'ram_opt_id'  => $ram_opt_id,
			'disk_opt_id' => $disk_opt_id,
			'currency'    => $symbol,
		];
	}

	/**
	 * Get list of available billing cycle keys from pricing array.
	 *
	 * @param array<string, mixed> $pricing Full WHMCS pricing array or rates map.
	 * @return array<string> Array of cycle keys (e.g. ['monthly', 'annually']).
	 */
	public function get_available_cycles( array $pricing ): array {
		$rates     = $this->get_default_currency_pricing( $pricing );
		$available = [];
		foreach ( self::CYCLE_LABELS as $cycle => $label ) {
			if ( isset( $rates[ $cycle ] ) && (float) $rates[ $cycle ] >= 0 && '' !== $rates[ $cycle ] ) {
				$available[] = $cycle;
			}
		}
		return $available;
	}

	/**
	 * Compute discount/savings percentage for a cycle compared against shorter cycle baseline.
	 *
	 * Formula: (monthly_price * months_in_cycle - cycle_price) / (monthly_price * months_in_cycle)
	 *
	 * @param array<string, mixed> $pricing      WHMCS pricing array.
	 * @param string               $target_cycle Target cycle (e.g. 'annually').
	 * @return int Savings percentage (rounded whole integer), or 0 if no savings or no shorter cycle.
	 */
	public function compute_savings( array $pricing, string $target_cycle ): int {
		$rates = $this->get_default_currency_pricing( $pricing );
		if ( ! isset( $rates[ $target_cycle ] ) || ! isset( self::CYCLE_MONTHS[ $target_cycle ] ) ) {
			return 0;
		}

		$target_months = self::CYCLE_MONTHS[ $target_cycle ];
		if ( $target_months <= 1 ) {
			return 0;
		}

		$target_price = (float) $rates[ $target_cycle ];

		// Baseline: check for monthly price first.
		$baseline_monthly = null;
		if ( isset( $rates['monthly'] ) && (float) $rates['monthly'] > 0 ) {
			$baseline_monthly = (float) $rates['monthly'];
		} else {
			// Find the shortest available cycle shorter than target cycle.
			foreach ( self::CYCLE_MONTHS as $shorter_cycle => $months ) {
				if ( $months < $target_months && isset( $rates[ $shorter_cycle ] ) && (float) $rates[ $shorter_cycle ] > 0 ) {
					$baseline_monthly = (float) $rates[ $shorter_cycle ] / $months;
					break;
				}
			}
		}

		if ( null === $baseline_monthly || $baseline_monthly <= 0 ) {
			return 0;
		}

		$base_cost = $baseline_monthly * $target_months;
		if ( $target_price >= $base_cost ) {
			return 0;
		}

		$savings = ( ( $base_cost - $target_price ) / $base_cost ) * 100;
		return (int) round( $savings );
	}

	/**
	 * Extract product groups from all products.
	 *
	 * @return array<int, array{id: int, name: string}> List of unique product groups.
	 */
	public function get_product_groups(): array {
		$products = $this->get_products();
		if ( null === $products || empty( $products ) ) {
			return [];
		}

		$groups = [];
		foreach ( $products as $product ) {
			$gid = isset( $product['gid'] ) ? (int) $product['gid'] : 0;
			if ( $gid > 0 && ! isset( $groups[ $gid ] ) ) {
				$group_name     = $product['groupname'] ?? ( $product['group_name'] ?? ( 'Group ' . $gid ) );
				$groups[ $gid ] = [
					'id'   => $gid,
					'name' => (string) $group_name,
				];
			}
		}

		return array_values( $groups );
	}

	/**
	 * Extract clean feature bullets from a product description string.
	 *
	 * @param string $description WHMCS product description (HTML or plaintext).
	 * @return array<string> Array of feature bullet strings.
	 */
	public function extract_features_from_description( string $description ): array {
		if ( empty( trim( $description ) ) ) {
			return [];
		}

		// Convert HTML linebreaks to newlines and strip tags.
		$text  = str_ireplace( [ '<br>', '<br/>', '<br />', '</li>', '</p>' ], "\n", $description );
		$text  = wp_strip_all_tags( $text );
		$lines = explode( "\n", $text );

		$features = [];
		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			// Remove leading bullets (- , * , • , ✓ , etc.).
			$cleaned = preg_replace( '/^[-*•✓✔\d+\.]\s*/u', '', $trimmed );
			if ( '' !== $cleaned && strlen( $cleaned ) > 1 ) {
				$features[] = $cleaned;
			}
		}

		return array_values( array_unique( $features ) );
	}

	/**
	 * Extract the first non-empty line from product description as a tagline.
	 *
	 * @param string $description WHMCS product description.
	 * @return string Tagline string or empty string.
	 */
	public function extract_tagline_from_description( string $description ): string {
		$features = $this->extract_features_from_description( $description );
		return ! empty( $features ) ? $features[0] : '';
	}

	/**
	 * Clear the in-memory runtime cache. Useful during tests.
	 */
	public static function clear_runtime_cache(): void {
		self::$runtime_queries  = [];
		self::$runtime_products = [];
	}
}
