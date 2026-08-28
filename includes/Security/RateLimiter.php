<?php
/**
 * Rate limiter for the login form.
 *
 * @package RoostKit\WhmcsConnector\Security
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Security;

/**
 * Transient-based rate limiter — 5 attempts per 10 minutes per IP.
 *
 * Known limitation (v1.0): IP-only rate limiting will over-block users behind
 * shared IPs (corporate NAT, CGNAT, Cloudflare). Acceptable for v1.0.
 * To revisit: filterable threshold, CAPTCHA integration.
 */
final class RateLimiter {

	/**
	 * Maximum allowed attempts within the window.
	 *
	 * @var int
	 */
	private const MAX_ATTEMPTS = 5;

	/**
	 * Time window in seconds (10 minutes).
	 *
	 * @var int
	 */
	private const WINDOW_SECONDS = 600;

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	private const PREFIX = 'whmcs_connector_rl_';

	/**
	 * Check if the given IP is allowed to attempt a login.
	 *
	 * @param string $ip Client IP address.
	 * @return bool True if under the rate limit.
	 */
	public function is_allowed( string $ip ): bool {
		$attempts = $this->get_attempts( $ip );
		return $attempts < self::MAX_ATTEMPTS;
	}

	/**
	 * Record a failed login attempt for the given IP.
	 *
	 * @param string $ip Client IP address.
	 */
	public function record_attempt( string $ip ): void {
		$key      = $this->get_key( $ip );
		$attempts = $this->get_attempts( $ip );

		set_transient( $key, $attempts + 1, self::WINDOW_SECONDS );
	}

	/**
	 * Reset the attempt counter for the given IP (on successful login).
	 *
	 * @param string $ip Client IP address.
	 */
	public function reset( string $ip ): void {
		delete_transient( $this->get_key( $ip ) );
	}

	/**
	 * Get the current attempt count for an IP.
	 *
	 * @param string $ip Client IP address.
	 * @return int
	 */
	private function get_attempts( string $ip ): int {
		$attempts = get_transient( $this->get_key( $ip ) );
		return false === $attempts ? 0 : (int) $attempts;
	}

	/**
	 * Generate a transient key from an IP address.
	 *
	 * Uses a hash to keep the key a fixed length and avoid storing raw IPs.
	 *
	 * @param string $ip Client IP address.
	 * @return string Transient key.
	 */
	private function get_key( string $ip ): string {
		return self::PREFIX . substr( hash( 'sha256', $ip ), 0, 16 );
	}
}
