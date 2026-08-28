<?php
/**
 * WHMCS Local API client.
 *
 * @package RoostKit\WhmcsConnector\Api
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Api;

use RoostKit\WhmcsConnector\Security\Crypto;

/**
 * Communicates with WHMCS via its Local/External API (includes/api.php).
 *
 * Uses wp_remote_post() for HTTP, with one retry on timeout.
 * Implements ApiClientInterface for future REST API adapter swap.
 */
final class Client implements ApiClientInterface {

	/**
	 * WHMCS installation URL (no trailing slash).
	 *
	 * @var string
	 */
	private string $whmcs_url;

	/**
	 * Encryption service.
	 *
	 * @var Crypto
	 */
	private Crypto $crypto;

	/**
	 * Plugin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * API error logger.
	 *
	 * @var ApiLog
	 */
	private ApiLog $log;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 10;

	/**
	 * Retry delay in seconds.
	 *
	 * @var int
	 */
	private const RETRY_DELAY = 1;

	/**
	 * Constructor.
	 *
	 * @param string               $whmcs_url WHMCS installation URL.
	 * @param Crypto               $crypto    Encryption service.
	 * @param array<string, mixed> $settings  Plugin settings.
	 */
	public function __construct( string $whmcs_url, Crypto $crypto, array $settings ) {
		$this->whmcs_url = untrailingslashit( $whmcs_url );
		$this->crypto    = $crypto;
		$this->settings  = $settings;
		$this->log       = new ApiLog();
	}

	/**
	 * Execute a WHMCS API action.
	 *
	 * @param string               $action WHMCS API action name.
	 * @param array<string, mixed> $params Additional parameters.
	 * @return array<string, mixed> Decoded API response.
	 *
	 * @throws ApiException On failure.
	 */
	public function call( string $action, array $params = [] ): array {
		$identifier = $this->get_decrypted_credential( 'api_identifier' );
		$secret     = $this->get_decrypted_credential( 'api_secret' );

		$postfields = [
			'identifier'   => $identifier,
			'secret'       => $secret,
			'action'       => $action,
			'responsetype' => 'json',
		];

		$access_key = $this->get_optional_decrypted_credential( 'api_access_key' );
		if ( ! empty( $access_key ) ) {
			$postfields['accesskey'] = $access_key;
		}

		$postfields = array_merge( $postfields, $params );

		$endpoint = $this->whmcs_url . '/includes/api.php';
		$response = $this->do_request( $endpoint, $postfields );

		// Retry once on timeout/connection failure.
		if ( is_wp_error( $response ) ) {
			sleep( self::RETRY_DELAY );
			$response = $this->do_request( $endpoint, $postfields );
		}

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->log->log( $action, $error_message, 0 );
			throw new ApiException(
				/* translators: %s: Error message */
				sprintf( __( 'WHMCS API connection failed: %s', 'whmcs-connector' ), $error_message ),
				$error_message
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$decoded   = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			$this->log->log( $action, 'Invalid JSON response', $http_code );
			throw new ApiException(
				__( 'WHMCS returned an invalid response.', 'whmcs-connector' ),
				'Invalid JSON: ' . substr( $body, 0, 200 ),
				$http_code
			);
		}

		if ( isset( $decoded['result'] ) && 'success' !== $decoded['result'] ) {
			$api_message = $decoded['message'] ?? __( 'Unknown error', 'whmcs-connector' );
			$this->log->log( $action, $api_message, $http_code );
			throw new ApiException(
				/* translators: %s: API error message */
				sprintf( __( 'WHMCS API error: %s', 'whmcs-connector' ), $api_message ),
				$api_message,
				$http_code
			);
		}

		return $decoded;
	}

	/**
	 * Test the API connection using WhmcsDetails (lightweight).
	 *
	 * @return array{success: bool, message: string, data?: array<string, mixed>}
	 */
	public function test_connection(): array {
		try {
			$result = $this->call( 'WhmcsDetails' );

			return [
				'success' => true,
				'message' => sprintf(
					/* translators: %s: WHMCS version */
					__( 'Connected to WHMCS %s', 'whmcs-connector' ),
					$result['whmcsVersion'] ?? __( '(version unknown)', 'whmcs-connector' )
				),
				'data'    => $result,
			];
		} catch ( ApiException $e ) {
			$ip_blocked = $this->is_ip_blocked_error( $e->get_api_message() );

			$message = $e->getMessage();
			if ( $ip_blocked ) {
				$outbound_ip = $this->detect_outbound_ip();
				$ip_display  = ! empty( $outbound_ip ) ? $outbound_ip : __( '(could not detect)', 'whmcs-connector' );
				$message     = sprintf(
					/* translators: %s: Server IP address */
					__( 'Connection blocked — your WHMCS may be restricting API access by IP. Your server\'s detected outbound IP is: %s. Note: if your site uses a CDN or proxy (e.g. Cloudflare), the actual IP reaching your WHMCS server may differ. Check your hosting provider\'s documentation for the origin IP.', 'whmcs-connector' ),
					$ip_display
				);
			}

			return [
				'success' => false,
				'message' => $message,
			];
		}
	}

	/**
	 * Perform an HTTP POST request to the WHMCS API.
	 *
	 * @param string               $endpoint URL.
	 * @param array<string, mixed> $body     POST body.
	 * @return array|\WP_Error Response or error.
	 */
	private function do_request( string $endpoint, array $body ): array|\WP_Error {
		$ipresolve_callback = static function ( $handle ): void {
			if ( is_resource( $handle ) || $handle instanceof \CurlHandle ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
				curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
			}
		};

		add_action( 'http_api_curl', $ipresolve_callback, 10, 1 );

		$response = wp_remote_post(
			$endpoint,
			[
				'body'      => $body,
				'timeout'   => self::TIMEOUT,
				'sslverify' => (bool) apply_filters( 'whmcs_connector_sslverify', true ),
			]
		);

		remove_action( 'http_api_curl', $ipresolve_callback, 10 );

		return $response;
	}

	/**
	 * Decrypt a stored credential.
	 *
	 * @param string $key Settings key.
	 * @return string Decrypted value.
	 *
	 * @throws ApiException If credential is missing or decryption fails.
	 */
	private function get_decrypted_credential( string $key ): string {
		$encrypted = $this->settings[ $key ] ?? '';
		if ( empty( $encrypted ) ) {
			throw new ApiException(
				/* translators: %s: Credential name */
				sprintf( __( 'WHMCS %s is not configured.', 'whmcs-connector' ), $key )
			);
		}

		try {
			return $this->crypto->decrypt( $encrypted );
		} catch ( \RuntimeException $e ) {
			throw new ApiException(
				__( 'Failed to decrypt API credentials. If you recently changed your WordPress security keys, please re-enter your WHMCS API credentials in the plugin settings.', 'whmcs-connector' ),
				$e->getMessage()
			);
		}
	}

	/**
	 * Decrypt an optional stored credential.
	 *
	 * @param string $key Settings key.
	 * @return string Decrypted value, or empty string if not configured.
	 *
	 * @throws ApiException If decryption fails on a non-empty stored value.
	 */
	private function get_optional_decrypted_credential( string $key ): string {
		$encrypted = $this->settings[ $key ] ?? '';
		if ( empty( $encrypted ) ) {
			return '';
		}

		try {
			return $this->crypto->decrypt( $encrypted );
		} catch ( \RuntimeException $e ) {
			throw new ApiException(
				__( 'Failed to decrypt API credentials. If you recently changed your WordPress security keys, please re-enter your WHMCS API credentials in the plugin settings.', 'whmcs-connector' ),
				$e->getMessage()
			);
		}
	}

	/**
	 * Check if an API error message indicates IP-based access denial.
	 *
	 * @param string $message Error message from WHMCS.
	 * @return bool
	 */
	private function is_ip_blocked_error( string $message ): bool {
		return (bool) preg_match( '/unauthorized|ip.*not.*allowed|access.*denied|invalid\s+ip/i', $message );
	}

	/**
	 * Attempt to detect this server's outbound IP address.
	 *
	 * @return string|null IP address or null on failure.
	 */
	private function detect_outbound_ip(): ?string {
		$response = wp_remote_get(
			'https://api.ipify.org',
			[
				'timeout'   => 5,
				'sslverify' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$ip = trim( wp_remote_retrieve_body( $response ) );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : null;
	}
}
