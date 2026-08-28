<?php
/**
 * Login form POST handler — runs on template_redirect, before any output.
 *
 * @package RoostKit\WhmcsConnector\Shortcodes
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Shortcodes;

use RoostKit\WhmcsConnector\Api\ApiException;
use RoostKit\WhmcsConnector\Api\Client;
use RoostKit\WhmcsConnector\Security\RateLimiter;

/**
 * Handles login form POST submission on template_redirect.
 *
 * This is intentionally separated from LoginShortcode because shortcodes
 * execute mid-content (after headers are sent), making wp_redirect() impossible.
 * This handler runs on template_redirect (priority 10), before any output.
 *
 * Communication with the shortcode renderer happens via one-time transients
 * keyed by a cryptographically random token passed in ?whmcs_login_ref={token}.
 */
final class LoginHandler {

	/**
	 * WHMCS API client instance.
	 *
	 * @var Client|null
	 */
	private ?Client $api_client;

	/**
	 * Rate limiter instance.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $rate_limiter;

	/**
	 * Configured WHMCS base URL.
	 *
	 * @var string
	 */
	private string $whmcs_url;

	/**
	 * Constructor.
	 *
	 * @param Client|null $api_client   API client.
	 * @param RateLimiter $rate_limiter Rate limiter.
	 * @param string      $whmcs_url   WHMCS URL.
	 */
	public function __construct( ?Client $api_client, RateLimiter $rate_limiter, string $whmcs_url ) {
		$this->api_client   = $api_client;
		$this->rate_limiter = $rate_limiter;
		$this->whmcs_url    = untrailingslashit( $whmcs_url );
	}

	/**
	 * Register the handler on template_redirect.
	 */
	public function register(): void {
		add_action( 'template_redirect', [ $this, 'handle' ], 10 );
	}

	/**
	 * Handle the login POST — called on every page load, no-ops if not a login submission.
	 */
	public function handle(): void {
		// Quick bail: no login submission.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['whmcs_connector_login_action'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['whmcs_connector_login_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['whmcs_connector_login_nonce'] ) ),
				'whmcs_connector_login'
			)
		) {
			$this->redirect_with_message(
				__( 'Security check failed. Please try again.', 'whmcs-connector' ),
				'error'
			);
			return;
		}

		// Client IP for rate limiting.
		$ip = $this->get_client_ip();

		// Rate limit check.
		if ( ! $this->rate_limiter->is_allowed( $ip ) ) {
			$this->redirect_with_message(
				__( 'Too many login attempts. Please try again later.', 'whmcs-connector' ),
				'error'
			);
			return;
		}

		// API client check.
		if ( null === $this->api_client ) {
			$this->redirect_with_message(
				__( 'Login is not available. The plugin is not configured.', 'whmcs-connector' ),
				'error'
			);
			return;
		}

		// Sanitize inputs.
		$email = isset( $_POST['whmcs_connector_email'] ) ? sanitize_email( wp_unslash( $_POST['whmcs_connector_email'] ) ) : '';
		// Passwords can contain any character — do not sanitize, only unslash.
		$password = isset( $_POST['whmcs_connector_password'] ) ? wp_unslash( $_POST['whmcs_connector_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $email ) || empty( $password ) ) {
			$this->rate_limiter->record_attempt( $ip );
			$this->redirect_with_message(
				__( 'Please enter your email address and password.', 'whmcs-connector' ),
				'error'
			);
			return;
		}

		// Call WHMCS ValidateLogin API.
		try {
			$result = $this->api_client->call(
				'ValidateLogin',
				[
					'email'     => $email,
					'password2' => $password,
				]
			);
		} catch ( ApiException $e ) {
			$this->rate_limiter->record_attempt( $ip );
			$this->redirect_with_message(
				__( 'The email address or password you entered is incorrect.', 'whmcs-connector' ),
				'error'
			);
			return;
		}

		// ValidateLogin returns result=success with userid on valid credentials.
		if ( ! isset( $result['userid'] ) ) {
			$this->rate_limiter->record_attempt( $ip );
			$this->redirect_with_message(
				__( 'The email address or password you entered is incorrect.', 'whmcs-connector' ),
				'error'
			);
			return;
		}

		// Success — reset rate limiter.
		$this->rate_limiter->reset( $ip );

		// Check for a redirect attribute (validated against WHMCS host).
		$redirect = isset( $_POST['whmcs_connector_redirect'] )
			? esc_url_raw( wp_unslash( $_POST['whmcs_connector_redirect'] ) )
			: '';

		if ( ! empty( $redirect ) && $this->is_valid_redirect( $redirect ) ) {
			// Add the WHMCS host to the allowed redirect list for this request.
			$whmcs_host = wp_parse_url( $this->whmcs_url, PHP_URL_HOST );
			add_filter(
				'allowed_redirect_hosts',
				function ( array $hosts ) use ( $whmcs_host ): array {
					$hosts[] = $whmcs_host;
					return $hosts;
				}
			);

			wp_redirect( wp_validate_redirect( $redirect, $this->whmcs_url . '/clientarea.php' ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}

		// Default: no SSO in v1.0. Show success message with manual link.
		$this->redirect_with_message(
			__( 'Signed in successfully.', 'whmcs-connector' ),
			'success'
		);
	}

	/**
	 * Redirect back to the referring page with a one-time message transient.
	 *
	 * @param string $message Message text.
	 * @param string $type    Message type ('error' or 'success').
	 */
	private function redirect_with_message( string $message, string $type ): void {
		// Generate cryptographically random token.
		$token = bin2hex( random_bytes( 16 ) );

		// Store message as a one-time transient (60 second expiry).
		set_transient(
			'whmcs_login_msg_' . $token,
			[
				'message' => $message,
				'type'    => $type,
			],
			60
		);

		// Determine redirect URL — use the HTTP referer, stripped of any previous ref param.
		$referer = wp_get_referer();
		if ( empty( $referer ) ) {
			$referer = home_url();
		}

		// Remove any existing whmcs_login_ref query param.
		$referer = remove_query_arg( 'whmcs_login_ref', $referer );

		$redirect_url = add_query_arg( 'whmcs_login_ref', $token, $referer );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Validate that a redirect URL's host matches the configured WHMCS URL.
	 *
	 * @param string $url URL to validate.
	 * @return bool
	 */
	private function is_valid_redirect( string $url ): bool {
		if ( empty( $url ) || empty( $this->whmcs_url ) ) {
			return false;
		}

		$redirect_host = wp_parse_url( $url, PHP_URL_HOST );
		$whmcs_host    = wp_parse_url( $this->whmcs_url, PHP_URL_HOST );

		if ( empty( $redirect_host ) || empty( $whmcs_host ) ) {
			return false;
		}

		return strtolower( $redirect_host ) === strtolower( $whmcs_host );
	}

	/**
	 * Get the client's IP address.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';
		$valid_ip = filter_var( $ip, FILTER_VALIDATE_IP );
		return false !== $valid_ip ? (string) $valid_ip : '0.0.0.0';
	}
}
