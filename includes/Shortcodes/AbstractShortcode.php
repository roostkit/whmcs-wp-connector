<?php
/**
 * Abstract base class for all shortcodes.
 *
 * @package RoostKit\WhmcsConnector\Shortcodes
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Shortcodes;

/**
 * Shared functionality for shortcode renderers.
 *
 * All shortcodes are purely presentational — no form handling or redirects
 * happen inside render methods (shortcodes execute mid-content, after headers
 * are sent).
 */
abstract class AbstractShortcode {

	/**
	 * WHMCS installation URL (no trailing slash).
	 *
	 * @var string
	 */
	protected string $whmcs_url;

	/**
	 * Register the shortcode.
	 */
	abstract public function register(): void;

	/**
	 * Render the shortcode output.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	abstract public function render( array $atts = [] ): string;

	/**
	 * Render an error/configuration-required message.
	 *
	 * Never exposes internals — just tells the user the plugin needs setup.
	 *
	 * @param string $message Optional custom message.
	 * @return string HTML output.
	 */
	protected function render_error( string $message = '' ): string {
		if ( '' === $message ) {
			$message = __( 'WHMCS Connector is not configured. Please check the plugin settings.', 'whmcs-connector' );
		}

		// Only show detailed messages to admins.
		if ( ! current_user_can( 'manage_options' ) ) {
			$message = __( 'This content is temporarily unavailable.', 'whmcs-connector' );
		}

		return '<div class="whmcs-connector-error"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Render a message and link to get premium when a shortcode is used without an active Pro license.
	 *
	 * @param string $shortcode_tag Optional shortcode tag name (e.g. 'whmcs_price').
	 * @return string HTML output.
	 */
	protected function render_pro_required( string $shortcode_tag = '' ): string {
		$this->enqueue_frontend_css();
		$upgrade_url = apply_filters( 'whmcs_connector_upgrade_url', 'https://roostkit.site/whmcs-connector' );
		$tag_display = ! empty( $shortcode_tag ) ? '[' . trim( $shortcode_tag, '[]' ) . ']' : __( 'Shortcode', 'whmcs-connector' );

		ob_start();
		?>
		<span class="whmcs-pro-shortcode-notice">
			<span class="whmcs-pro-shortcode-badge"><?php echo esc_html__( 'PRO', 'whmcs-connector' ); ?></span>
			<span class="whmcs-pro-shortcode-text">
				<?php
				/* translators: %s: shortcode tag name */
				printf( esc_html__( '%s requires WHMCS Connector Pro.', 'whmcs-connector' ), '<code>' . esc_html( $tag_display ) . '</code>' );
				?>
			</span>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" class="whmcs-pro-shortcode-link" target="_blank" rel="noopener">
				<?php echo esc_html__( 'Get Premium', 'whmcs-connector' ); ?> &rarr;
			</a>
		</span>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Get the configured WHMCS URL.
	 *
	 * @return string
	 */
	protected function get_whmcs_url(): string {
		return $this->whmcs_url;
	}

	/**
	 * Validate that a redirect URL's host matches the configured WHMCS URL.
	 *
	 * Prevents open redirect attacks by ensuring redirect targets only go to
	 * the customer's own WHMCS installation.
	 *
	 * @param string $url The redirect URL to validate.
	 * @return bool True if the host matches the configured WHMCS URL.
	 */
	public function is_valid_redirect( string $url ): bool {
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
	 * Build a WHMCS URL by appending a path to the base URL.
	 *
	 * @param string $path Path to append (e.g. '/clientarea.php').
	 * @return string Full URL.
	 */
	protected function build_whmcs_url( string $path ): string {
		return $this->whmcs_url . '/' . ltrim( $path, '/' );
	}

	/**
	 * Enqueue frontend CSS if not already enqueued.
	 */
	protected function enqueue_frontend_css(): void {
		if ( ! wp_style_is( 'whmcs-connector-shortcodes', 'enqueued' ) ) {
			wp_enqueue_style(
				'whmcs-connector-shortcodes',
				WHMCS_CONNECTOR_URL . 'assets/frontend/shortcodes.css',
				[],
				WHMCS_CONNECTOR_VERSION
			);
		}
	}
}
