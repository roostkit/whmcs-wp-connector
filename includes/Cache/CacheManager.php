<?php
/**
 * Transient-based caching layer for WHMCS API responses.
 *
 * @package RoostKit\WhmcsConnector\Cache
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Cache;

/**
 * Manages cached API responses using the WordPress Transients API.
 *
 * Only used for read-only/GET-equivalent API calls (product listings, currencies).
 * Never caches authentication or sensitive responses.
 */
final class CacheManager {

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	private const PREFIX = 'whmcs_connector_cache_';

	/**
	 * Default TTL in seconds.
	 *
	 * @var int
	 */
	private int $default_ttl;

	/**
	 * Constructor.
	 *
	 * @param int $default_ttl Default cache TTL in seconds.
	 */
	public function __construct( int $default_ttl = 900 ) {
		$this->default_ttl = $default_ttl;
	}

	/**
	 * Get a cached value.
	 *
	 * @param string $key Cache key (without prefix).
	 * @return mixed Cached data or false if not found.
	 */
	public function get( string $key ): mixed {
		return get_transient( self::PREFIX . $key );
	}

	/**
	 * Store a value in cache.
	 *
	 * @param string   $key  Cache key (without prefix).
	 * @param mixed    $data Data to cache.
	 * @param int|null $ttl  TTL in seconds. Null uses the default.
	 */
	public function set( string $key, mixed $data, ?int $ttl = null ): void {
		$ttl = $ttl ?? $this->default_ttl;

		/**
		 * Filter the cache TTL for a specific key.
		 *
		 * @param int    $ttl Cache TTL in seconds.
		 * @param string $key The cache key.
		 */
		$ttl = (int) apply_filters( 'whmcs_connector_cache_ttl', $ttl, $key );

		set_transient( self::PREFIX . $key, $data, $ttl );
	}

	/**
	 * Delete a cached value.
	 *
	 * @param string $key Cache key (without prefix).
	 */
	public function delete( string $key ): void {
		delete_transient( self::PREFIX . $key );
	}

	/**
	 * Flush all plugin cache entries.
	 *
	 * @return int Number of entries flushed.
	 */
	public function flush(): int {
		global $wpdb;

		$count = 0;

		// Delete from options table (transients without external object cache).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::PREFIX ) . '%'
			)
		);

		foreach ( $transients as $transient ) {
			$key = str_replace( '_transient_', '', $transient );
			if ( delete_transient( $key ) ) {
				++$count;
			}
		}

		return $count;
	}
}
