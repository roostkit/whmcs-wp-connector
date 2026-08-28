<?php
/**
 * Admin notices.
 *
 * @package RoostKit\WhmcsConnector\Admin
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Admin;

use RoostKit\WhmcsConnector\Plugin;

/**
 * Displays admin notices for missing configuration, rewrite flush needs,
 * and the one-time dismissible "Getting Started" welcome notice.
 */
final class Notices {

	/**
	 * User meta key for tracking welcome notice dismissal.
	 *
	 * @var string
	 */
	public const DISMISS_META_KEY = 'whmcs_connector_notice_dismissed';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render_notices' ] );
		add_action( 'wp_ajax_whmcs_connector_dismiss_notice', [ $this, 'ajax_dismiss_notice' ] );
	}

	/**
	 * Render any applicable admin notices.
	 */
	public function render_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin = Plugin::get_instance();

		// Notice: API not configured.
		if ( ! $plugin->is_configured() ) {
			$settings_url = admin_url( 'admin.php?page=whmcs-connector' );
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'WHMCS Connector: API credentials are not configured.', 'whmcs-connector' ),
				esc_url( $settings_url ),
				esc_html__( 'Configure now', 'whmcs-connector' )
			);
		}

		// Notice: rewrite rules need flushing.
		if ( get_option( 'whmcs_connector_flush_rewrite' ) ) {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html__( 'WHMCS Connector: Permalink rules updated. Please visit Settings → Permalinks and click "Save Changes" to apply.', 'whmcs-connector' )
			);
		}

		// Notice: Pro features force-enabled dev override.
		if ( defined( 'WHMCS_CONNECTOR_FORCE_PRO' ) && WHMCS_CONNECTOR_FORCE_PRO === true ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'Pro features are force-enabled via WHMCS_CONNECTOR_FORCE_PRO — remove this constant before going live.', 'whmcs-connector' )
			);
		}

		// One-time "Getting Started" notice — shown on dashboard and plugins pages only.
		$this->maybe_render_getting_started_notice();
	}

	/**
	 * Conditionally render the one-time getting started notice.
	 *
	 * Shown only:
	 * - On dashboard (index.php) and plugins.php screens.
	 * - To users who haven't dismissed it (per-user meta).
	 * - When the welcome screen hasn't been visited yet.
	 * - Not if already on the welcome screen itself.
	 */
	private function maybe_render_getting_started_notice(): void {
		$user_id = get_current_user_id();

		// Only show on specific screens.
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'plugins' ], true ) ) {
			return;
		}

		// Don't show if user already dismissed.
		if ( get_user_meta( $user_id, self::DISMISS_META_KEY, true ) ) {
			return;
		}

		// Don't show if the welcome screen has already been visited.
		if ( get_option( 'whmcs_connector_welcome_seen' ) ) {
			return;
		}

		$welcome_url = admin_url( 'admin.php?page=' . Welcome::PAGE_SLUG );
		$about_url   = admin_url( 'admin.php?page=whmcs-connector&tab=about' );

		printf(
			'<div class="notice notice-info whmcs-connector-getting-started-notice" id="whmcs-connector-getting-started-notice" data-nonce="%s" data-user-id="%d">
				<p>
					<strong>%s</strong>
					&mdash;
					<a href="%s">%s</a>
					&middot;
					<a href="%s">%s</a>
					&middot;
					<button type="button" class="button-link whmcs-connector-dismiss-notice" aria-label="%s">%s</button>
				</p>
			</div>',
			esc_attr( wp_create_nonce( 'whmcs_connector_dismiss_notice' ) ),
			esc_attr( (string) $user_id ),
			esc_html__( 'WHMCS Connector is ready.', 'whmcs-connector' ),
			esc_url( $welcome_url ),
			esc_html__( 'Get Started', 'whmcs-connector' ),
			esc_url( $about_url ),
			esc_html__( 'See what\'s in Pro', 'whmcs-connector' ),
			esc_attr__( 'Dismiss this notice permanently', 'whmcs-connector' ),
			esc_html__( 'Dismiss', 'whmcs-connector' )
		);

		// Enqueue the inline dismiss script only when the notice is shown.
		add_action( 'admin_footer', [ $this, 'render_dismiss_script' ] );
	}

	/**
	 * Render the inline JS that handles AJAX dismissal of the getting started notice.
	 */
	public function render_dismiss_script(): void {
		?>
		<script id="whmcs-connector-dismiss-script">
		( function () {
			var notice = document.getElementById( 'whmcs-connector-getting-started-notice' );
			if ( ! notice ) { return; }
			var btn = notice.querySelector( '.whmcs-connector-dismiss-notice' );
			if ( ! btn ) { return; }
			btn.addEventListener( 'click', function () {
				var nonce  = notice.getAttribute( 'data-nonce' );
				var userId = notice.getAttribute( 'data-user-id' );
				notice.style.opacity = '0.5';
				fetch( '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
					method  : 'POST',
					headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
					body    : new URLSearchParams({
						action     : 'whmcs_connector_dismiss_notice',
						_ajax_nonce: nonce,
						user_id    : userId
					})
				} ).finally( function () {
					notice.parentNode && notice.parentNode.removeChild( notice );
				} );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * AJAX handler: persist notice dismissal to user meta so it never shows again.
	 */
	public function ajax_dismiss_notice(): void {
		check_ajax_referer( 'whmcs_connector_dismiss_notice' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'whmcs-connector' ) ] );
		}

		update_user_meta( get_current_user_id(), self::DISMISS_META_KEY, true );

		wp_send_json_success();
	}
}
