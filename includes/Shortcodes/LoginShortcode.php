<?php
/**
 * Login shortcode — purely presentational.
 *
 * @package RoostKit\WhmcsConnector\Shortcodes
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Shortcodes;

use RoostKit\WhmcsConnector\Api\Client;

/**
 * [whmcs_login] — renders a login form.
 *
 * This class is PURELY PRESENTATIONAL. It does NOT handle POST submission
 * or call wp_redirect(). All form processing happens in LoginHandler,
 * which runs on template_redirect before any output.
 *
 * Displays error/success messages from LoginHandler via one-time transients
 * keyed by ?whmcs_login_ref={token}.
 */
final class LoginShortcode extends AbstractShortcode {

	/**
	 * API client (nullable — form still renders, just can't authenticate).
	 *
	 * @var Client|null
	 */
	private ?Client $api_client;

	/**
	 * Constructor.
	 *
	 * @param Client|null $api_client API client.
	 */
	public function __construct( ?Client $api_client ) {
		$this->api_client = $api_client;

		$settings        = get_option( 'whmcs_connector_settings', [] );
		$this->whmcs_url = is_array( $settings ) ? ( $settings['whmcs_url'] ?? '' ) : '';
	}

	/**
	 * Register the shortcode.
	 */
	public function register(): void {
		add_shortcode( 'whmcs_login', [ $this, 'render' ] );
	}

	/**
	 * Render the login form.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'title'          => '',
				'email_label'    => __( 'Email address', 'whmcs-connector' ),
				'password_label' => __( 'Password', 'whmcs-connector' ),
				'submit_label'   => __( 'Sign in', 'whmcs-connector' ),
				'redirect'       => '',
				'class'          => '',
			],
			$atts,
			'whmcs_login'
		);

		$this->enqueue_frontend_css();

		// Check configuration.
		if ( empty( $this->whmcs_url ) ) {
			return $this->render_error();
		}

		// SSL enforcement — never render a password field over plain HTTP.
		// Allowed on local/development environments (localhost, .local, .test) or via filter.
		$is_local_dev = in_array( wp_get_environment_type(), [ 'local', 'development' ], true )
			|| ( isset( $_SERVER['HTTP_HOST'] ) && (bool) preg_match( '/^(localhost|127\.0\.0\.1|\S+\.(local|test))(?::\d+)?$/i', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) );

		$require_ssl = (bool) apply_filters( 'whmcs_connector_require_ssl', ! $is_local_dev );

		if ( $require_ssl && ! is_ssl() ) {
			return '<div class="whmcs-connector-login whmcs-connector-ssl-warning">'
				. '<p>' . esc_html__( 'This login form requires a secure (HTTPS) connection.', 'whmcs-connector' ) . '</p>'
				. '</div>';
		}

		// Check for messages from LoginHandler (via one-time transient).
		$message      = '';
		$message_type = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ref = isset( $_GET['whmcs_login_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['whmcs_login_ref'] ) ) : '';

		if ( ! empty( $ref ) ) {
			$transient_key = 'whmcs_login_msg_' . $ref;
			$stored        = get_transient( $transient_key );

			if ( is_array( $stored ) ) {
				$message      = $stored['message'] ?? '';
				$message_type = $stored['type'] ?? 'error';
				// Delete on first read.
				delete_transient( $transient_key );
			}
		}

		$extra_class = ! empty( $atts['class'] ) ? ' ' . sanitize_html_class( $atts['class'] ) : '';

		ob_start();
		?>
		<div class="whmcs-connector-login<?php echo esc_attr( $extra_class ); ?>">

			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h3 class="whmcs-connector-login-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( 'success' === $message_type && ! empty( $message ) ) : ?>
				<div class="whmcs-connector-login-success">
					<p><?php echo esc_html( $message ); ?></p>
					<p>
						<a href="<?php echo esc_url( $this->build_whmcs_url( 'clientarea.php' ) ); ?>" class="whmcs-connector-btn wp-element-button">
							<?php esc_html_e( 'Manage your account', 'whmcs-connector' ); ?> &rarr;
						</a>
					</p>
				</div>
			<?php else : ?>

				<?php if ( 'error' === $message_type && ! empty( $message ) ) : ?>
					<div class="whmcs-connector-login-error" role="alert">
						<p><?php echo esc_html( $message ); ?></p>
					</div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( $this->get_current_page_url() ); ?>" class="whmcs-connector-login-form">
					<?php wp_nonce_field( 'whmcs_connector_login', 'whmcs_connector_login_nonce' ); ?>
					<input type="hidden" name="whmcs_connector_login_action" value="1" />

					<?php if ( ! empty( $atts['redirect'] ) && $this->is_valid_redirect( $atts['redirect'] ) ) : ?>
						<input type="hidden" name="whmcs_connector_redirect" value="<?php echo esc_attr( $atts['redirect'] ); ?>" />
					<?php endif; ?>

					<div class="whmcs-connector-field">
						<label for="whmcs-login-email"><?php echo esc_html( $atts['email_label'] ); ?></label>
						<input type="email" id="whmcs-login-email" name="whmcs_connector_email"
								required autocomplete="email" />
					</div>

					<div class="whmcs-connector-field">
						<label for="whmcs-login-password"><?php echo esc_html( $atts['password_label'] ); ?></label>
						<input type="password" id="whmcs-login-password" name="whmcs_connector_password"
								required autocomplete="current-password" />
					</div>

					<div class="whmcs-connector-field whmcs-connector-submit">
						<button type="submit" class="whmcs-connector-btn wp-element-button">
							<?php echo esc_html( $atts['submit_label'] ); ?>
						</button>
					</div>
				</form>

			<?php endif; ?>

		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Get the current page URL for the form action attribute.
	 *
	 * @return string
	 */
	private function get_current_page_url(): string {
		global $wp;
		return home_url( add_query_arg( [], $wp->request ?? '' ) );
	}
}
