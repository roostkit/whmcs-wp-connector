<?php
/**
 * API error logger with credential scrubbing.
 *
 * @package RoostKit\WhmcsConnector\Api
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Api;

/**
 * Logs API errors to a WordPress option, capped at 50 entries.
 *
 * Never logs raw request parameters. Scrubs any credential-like values
 * (password, secret, email) from error messages before storage, because
 * WHMCS error responses sometimes echo back submitted params.
 */
final class ApiLog {

	/**
	 * Option name for the log storage.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'whmcs_connector_api_log';

	/**
	 * Maximum number of log entries to keep.
	 *
	 * @var int
	 */
	private const MAX_ENTRIES = 50;

	/**
	 * Patterns to scrub from log messages.
	 *
	 * @var array<string>
	 */
	private const SCRUB_PATTERNS = [
		'/("?password"?\s*[:=]\s*)"[^"]*"/i',
		'/("?secret"?\s*[:=]\s*)"[^"]*"/i',
		'/("?email"?\s*[:=]\s*)"[^"]*"/i',
		'/("?identifier"?\s*[:=]\s*)"[^"]*"/i',
		'/("?api_secret"?\s*[:=]\s*)"[^"]*"/i',
		'/("?api_identifier"?\s*[:=]\s*)"[^"]*"/i',
	];

	/**
	 * Log an API error.
	 *
	 * @param string $action      The WHMCS API action that failed.
	 * @param string $message     Error message (will be scrubbed).
	 * @param int    $http_status HTTP status code (0 for connection failures).
	 */
	public function log( string $action, string $message, int $http_status = 0 ): void {
		$entries   = $this->get_entries();
		$entries[] = [
			'timestamp'   => gmdate( 'Y-m-d H:i:s' ),
			'action'      => sanitize_text_field( $action ),
			'message'     => $this->scrub( $message ),
			'http_status' => $http_status,
		];

		// Keep only the most recent entries.
		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		update_option( self::OPTION_NAME, $entries, false );
	}

	/**
	 * Get all log entries.
	 *
	 * @return array<int, array{timestamp: string, action: string, message: string, http_status: int}>
	 */
	public function get_entries(): array {
		$entries = get_option( self::OPTION_NAME, [] );
		return is_array( $entries ) ? $entries : [];
	}

	/**
	 * Clear all log entries.
	 */
	public function clear(): void {
		delete_option( self::OPTION_NAME );
	}

	/**
	 * Scrub credential-like values from a log message.
	 *
	 * @param string $message Raw error message.
	 * @return string Scrubbed message.
	 */
	private function scrub( string $message ): string {
		$scrubbed = $message;

		foreach ( self::SCRUB_PATTERNS as $pattern ) {
			$scrubbed = (string) preg_replace( $pattern, '$1"[REDACTED]"', $scrubbed );
		}

		// Truncate overly long messages.
		if ( strlen( $scrubbed ) > 500 ) {
			$scrubbed = substr( $scrubbed, 0, 497 ) . '...';
		}

		return sanitize_text_field( $scrubbed );
	}
}
