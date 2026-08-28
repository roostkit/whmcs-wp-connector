<?php
/**
 * Welcome / Getting Started screen.
 *
 * @package RoostKit\WhmcsConnector\Admin
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Admin;

use RoostKit\WhmcsConnector\Plugin;

/**
 * Handles the one-time activation welcome screen and getting started guide.
 */
final class Welcome {

	/**
	 * Welcome screen slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'whmcs-connector-welcome';

	/**
	 * Activation redirect transient key.
	 *
	 * @var string
	 */
	public const REDIRECT_TRANSIENT = 'whmcs_connector_activation_redirect';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'check_activation_redirect' ] );
	}

	/**
	 * Add the hidden submenu page.
	 */
	public function add_menu_page(): void {
		$hook = add_submenu_page(
			'whmcs-connector',
			__( 'Welcome to WHMCS Connector', 'whmcs-connector' ),
			__( 'Getting Started', 'whmcs-connector' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);

		if ( $hook ) {
			add_action( 'admin_print_styles-' . $hook, [ $this, 'enqueue_assets' ] );
		}
	}

	/**
	 * Enqueue styles and scripts for the welcome screen.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'whmcs-connector-admin',
			WHMCS_CONNECTOR_URL . 'assets/admin/admin.css',
			[],
			WHMCS_CONNECTOR_VERSION
		);
	}

	/**
	 * Check for the one-time activation redirect transient and handle redirect.
	 */
	public function check_activation_redirect(): void {
		if ( ! get_transient( self::REDIRECT_TRANSIENT ) ) {
			return;
		}

		// Delete the transient immediately to prevent multiple redirects.
		delete_transient( self::REDIRECT_TRANSIENT );

		// Guard against AJAX, WP-CLI, CRON, and REST requests.
		if ( wp_doing_ajax() || wp_doing_cron() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return;
		}

		// Guard against bulk activation.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Guard against non-admins or network admin.
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() ) {
			return;
		}

		// Mark welcome as seen.
		update_option( 'whmcs_connector_welcome_seen', true );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Render the welcome screen.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Ensure welcome is marked as seen when page is visited.
		update_option( 'whmcs_connector_welcome_seen', true );

		$settings_url = admin_url( 'admin.php?page=whmcs-connector' );
		$is_pro       = Plugin::is_pro();
		?>
		<div class="wrap whmcs-connector-welcome-wrap">
			<div class="whmcs-connector-welcome-hero">
				<div class="whmcs-connector-badge-pill">
					<?php esc_html_e( 'WHMCS CONNECTOR BY ROOSTKIT', 'whmcs-connector' ); ?>
				</div>
				<h1><?php esc_html_e( 'Connect WHMCS Directly to WordPress', 'whmcs-connector' ); ?></h1>
				<p class="whmcs-connector-welcome-intro">
					<?php esc_html_e( 'Display live pricing, client login, domain lookup, and client area quick-links natively in your theme with official API integration.', 'whmcs-connector' ); ?>
				</p>
			</div>

			<div class="whmcs-connector-welcome-steps">
				<h2><?php esc_html_e( 'Quick-Start Checklist', 'whmcs-connector' ); ?></h2>
				<div class="whmcs-connector-steps-grid">
					<div class="whmcs-connector-step-card">
						<div class="whmcs-step-num">1</div>
						<div class="whmcs-step-body">
							<h3><?php esc_html_e( 'Connect WHMCS API', 'whmcs-connector' ); ?></h3>
							<p>
								<?php esc_html_e( 'Enter your WHMCS URL, API Identifier, and API Secret in the Settings screen. Use Test Connection to verify.', 'whmcs-connector' ); ?>
							</p>
							<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
								<?php esc_html_e( 'Configure Connection', 'whmcs-connector' ); ?> &rarr;
							</a>
						</div>
					</div>

					<div class="whmcs-connector-step-card">
						<div class="whmcs-step-num">2</div>
						<div class="whmcs-step-body">
							<h3><?php esc_html_e( 'Add Blocks or Shortcodes', 'whmcs-connector' ); ?></h3>
							<p>
								<?php esc_html_e( 'Insert native Gutenberg blocks or shortcodes ([whmcs_pricing], [whmcs_login], [whmcs_client_area]) on your pages.', 'whmcs-connector' ); ?>
							</p>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>" class="button button-secondary">
								<?php esc_html_e( 'View Pages', 'whmcs-connector' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>

			<div class="whmcs-connector-welcome-comparison">
				<h2><?php esc_html_e( 'Features Included in Your Installation', 'whmcs-connector' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Compare Free and Pro features below. Upgrading unlocks high-converting pricing tables, VPS sliders, live domain search, and atomic theme tokens.', 'whmcs-connector' ); ?>
				</p>

				<?php echo ComparisonData::render_table(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php if ( ! $is_pro ) : ?>
					<div class="whmcs-connector-welcome-upsell-bar">
						<div>
							<h3><?php esc_html_e( 'Upgrade Your WHMCS Experience', 'whmcs-connector' ); ?></h3>
							<p><?php esc_html_e( 'Get SaaS pricing toggles, VPS sliders, live domain search, and atomic theme shortcodes.', 'whmcs-connector' ); ?></p>
						</div>
						<div class="whmcs-welcome-cta-box">
							<span class="whmcs-connector-price">
								<del>39,90 &euro;</del>
								<span class="whmcs-connector-price-current"><?php esc_html_e( 'Instant Access', 'whmcs-connector' ); ?></span>
							</span>
							<a href="https://roostkit.site/whmcs-connector" class="button button-primary button-hero" target="_blank" rel="noopener">
								<?php esc_html_e( 'Explore Addons', 'whmcs-connector' ); ?> &rarr;
							</a>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<div class="whmcs-connector-welcome-footer">
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Go to Settings', 'whmcs-connector' ); ?> &rarr;
				</a>
				<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" class="whmcs-connector-skip-link">
					<?php esc_html_e( 'Dismiss and return to Dashboard', 'whmcs-connector' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}
