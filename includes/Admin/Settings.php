<?php
/**
 * Admin settings screen.
 *
 * @package RoostKit\WhmcsConnector\Admin
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Admin;

use RoostKit\WhmcsConnector\Admin\ComparisonData;
use RoostKit\WhmcsConnector\Api\ApiLog;
use RoostKit\WhmcsConnector\Api\Client;
use RoostKit\WhmcsConnector\Cache\CacheManager;
use RoostKit\WhmcsConnector\Plugin;
use RoostKit\WhmcsConnector\Security\Crypto;

/**
 * Registers the WHMCS Connector admin settings page.
 */
final class Settings {

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'whmcs_connector_settings';

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'whmcs-connector';

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'whmcs_connector_settings_save';

	/**
	 * Encryption service instance.
	 *
	 * @var Crypto
	 */
	private Crypto $crypto;

	/**
	 * WHMCS API client instance.
	 *
	 * @var Client|null
	 */
	private ?Client $api_client;

	/**
	 * Cache manager instance.
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache_manager;

	/**
	 * Constructor.
	 *
	 * @param Crypto       $crypto        Encryption service.
	 * @param Client|null  $api_client    API client (null if not configured).
	 * @param CacheManager $cache_manager Cache manager.
	 */
	public function __construct( Crypto $crypto, ?Client $api_client, CacheManager $cache_manager ) {
		$this->crypto        = $crypto;
		$this->api_client    = $api_client;
		$this->cache_manager = $cache_manager;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'handle_save' ] );
		add_action( 'wp_ajax_whmcs_connector_test_connection', [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_whmcs_connector_clear_cache', [ $this, 'ajax_clear_cache' ] );
	}

	/**
	 * Add the admin menu page.
	 */
	public function add_menu_page(): void {
		$hook = add_menu_page(
			__( 'WHMCS Connector', 'whmcs-connector' ),
			__( 'WHMCS Connector', 'whmcs-connector' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ],
			'dashicons-admin-links',
			80
		);

		add_action( 'admin_print_styles-' . $hook, [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue admin CSS and JS.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'whmcs-connector-admin',
			WHMCS_CONNECTOR_URL . 'assets/admin/admin.css',
			[],
			WHMCS_CONNECTOR_VERSION
		);

		wp_enqueue_script(
			'whmcs-connector-admin',
			WHMCS_CONNECTOR_URL . 'assets/admin/admin.js',
			[],
			WHMCS_CONNECTOR_VERSION,
			true
		);

		wp_localize_script(
			'whmcs-connector-admin',
			'whmcsConnectorAdmin',
			[
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'testConnectionNonce' => wp_create_nonce( 'whmcs_connector_test_connection' ),
				'clearCacheNonce'     => wp_create_nonce( 'whmcs_connector_clear_cache' ),
				'strings'             => [
					'testing'  => __( 'Testing connection...', 'whmcs-connector' ),
					'clearing' => __( 'Clearing cache...', 'whmcs-connector' ),
					'cleared'  => __( 'Cache cleared.', 'whmcs-connector' ),
					'error'    => __( 'An error occurred.', 'whmcs-connector' ),
				],
			]
		);
	}

	/**
	 * Handle settings form submission.
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST['whmcs_connector_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['whmcs_connector_settings_nonce'] ) ),
			self::NONCE_ACTION
		) ) {
			wp_die( esc_html__( 'Security check failed.', 'whmcs-connector' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'whmcs-connector' ) );
		}

		$current  = get_option( self::OPTION_NAME, [] );
		$settings = is_array( $current ) ? $current : [];

		// WHMCS URL — sanitize and strip trailing slash.
		if ( isset( $_POST['whmcs_url'] ) ) {
			$settings['whmcs_url'] = untrailingslashit(
				esc_url_raw( wp_unslash( $_POST['whmcs_url'] ) )
			);
		}

		// API Identifier — encrypt if provided (non-empty means "update").
		if ( ! empty( $_POST['api_identifier'] ) ) {
			$settings['api_identifier'] = $this->crypto->encrypt(
				sanitize_text_field( wp_unslash( $_POST['api_identifier'] ) )
			);
		}

		// API Secret — encrypt if provided.
		if ( ! empty( $_POST['api_secret'] ) ) {
			$settings['api_secret'] = $this->crypto->encrypt(
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords may contain special characters
				wp_unslash( $_POST['api_secret'] )
			);
		}

		// API Access Key — encrypt if provided.
		if ( ! empty( $_POST['api_access_key'] ) ) {
			$settings['api_access_key'] = $this->crypto->encrypt(
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- keys may contain special characters
				wp_unslash( $_POST['api_access_key'] )
			);
		}

		// Cache TTL.
		if ( isset( $_POST['cache_ttl'] ) ) {
			$ttl                   = absint( $_POST['cache_ttl'] );
			$settings['cache_ttl'] = max( 60, min( 3600, $ttl ) );
		}

		update_option( self::OPTION_NAME, $settings );

		// Set a transient to show a success notice.
		set_transient( 'whmcs_connector_settings_saved', true, 30 );

		wp_safe_redirect(
			add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) )
		);
		exit;
	}

	/**
	 * AJAX handler: test WHMCS API connection.
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'whmcs_connector_test_connection' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'whmcs-connector' ) ] );
		}

		if ( null === $this->api_client ) {
			wp_send_json_error( [ 'message' => __( 'Please save your WHMCS URL and API credentials first.', 'whmcs-connector' ) ] );
		}

		$result = $this->api_client->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success( [ 'message' => $result['message'] ] );
		} else {
			wp_send_json_error( [ 'message' => $result['message'] ] );
		}
	}

	/**
	 * AJAX handler: clear the cache.
	 */
	public function ajax_clear_cache(): void {
		check_ajax_referer( 'whmcs_connector_clear_cache' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'whmcs-connector' ) ] );
		}

		$count = $this->cache_manager->flush();

		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: %d: Number of cleared cache entries */
					__( 'Cache cleared. %d entries removed.', 'whmcs-connector' ),
					$count
				),
			]
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = get_option( self::OPTION_NAME, [] );
		$settings   = is_array( $settings ) ? $settings : [];
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'connection'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$api_log    = new ApiLog();
		$saved      = get_transient( 'whmcs_connector_settings_saved' );
		if ( $saved ) {
			delete_transient( 'whmcs_connector_settings_saved' );
		}

		?>
		<div class="wrap whmcs-connector-settings">
			<h1><?php esc_html_e( 'WHMCS Connector', 'whmcs-connector' ); ?></h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'whmcs-connector' ); ?></p>
				</div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						[
							'page' => self::PAGE_SLUG,
							'tab'  => 'connection',
						],
						admin_url( 'admin.php' )
					)
				);
				?>
							"
					class="nav-tab <?php echo 'connection' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Connection', 'whmcs-connector' ); ?>
				</a>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						[
							'page' => self::PAGE_SLUG,
							'tab'  => 'cache',
						],
						admin_url( 'admin.php' )
					)
				);
				?>
							"
					class="nav-tab <?php echo 'cache' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Cache', 'whmcs-connector' ); ?>
				</a>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						[
							'page' => self::PAGE_SLUG,
							'tab'  => 'log',
						],
						admin_url( 'admin.php' )
					)
				);
				?>
							"
					class="nav-tab <?php echo 'log' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'API Log', 'whmcs-connector' ); ?>
				</a>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						[
							'page' => self::PAGE_SLUG,
							'tab'  => 'about',
						],
						admin_url( 'admin.php' )
					)
				);
				?>
							"
					class="nav-tab <?php echo 'about' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'About', 'whmcs-connector' ); ?>
				</a>
			</nav>

			<div class="whmcs-connector-tab-content">
				<?php
				match ( $active_tab ) {
					'cache' => $this->render_cache_tab( $settings ),
					'log'   => $this->render_log_tab( $api_log ),
					'about' => $this->render_about_tab(),
					default => $this->render_connection_tab( $settings ),
				};
		?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Connection tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_connection_tab( array $settings ): void {
		$has_identifier = ! empty( $settings['api_identifier'] );
		$has_secret     = ! empty( $settings['api_secret'] );
		$has_access_key = ! empty( $settings['api_access_key'] );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( self::NONCE_ACTION, 'whmcs_connector_settings_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="whmcs_url"><?php esc_html_e( 'WHMCS URL', 'whmcs-connector' ); ?></label>
					</th>
					<td>
						<input type="url" id="whmcs_url" name="whmcs_url"
								value="<?php echo esc_attr( $settings['whmcs_url'] ?? '' ); ?>"
								class="regular-text" placeholder="https://billing.example.com" />
						<p class="description">
							<?php esc_html_e( 'The full URL to your WHMCS installation, without a trailing slash.', 'whmcs-connector' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="api_identifier"><?php esc_html_e( 'API Identifier', 'whmcs-connector' ); ?></label>
					</th>
					<td>
						<input type="text" id="api_identifier" name="api_identifier"
								value="" class="regular-text"
								placeholder="<?php echo $has_identifier ? esc_attr__( '(saved, enter to change)', 'whmcs-connector' ) : ''; ?>" />
						<?php if ( $has_identifier ) : ?>
							<span class="whmcs-connector-saved-indicator" aria-label="<?php esc_attr_e( 'Saved', 'whmcs-connector' ); ?>">&#10003;</span>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Your WHMCS API Identifier. Encrypted before storage.', 'whmcs-connector' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="api_secret"><?php esc_html_e( 'API Secret', 'whmcs-connector' ); ?></label>
					</th>
					<td>
						<div class="whmcs-connector-password-field">
							<input type="password" id="api_secret" name="api_secret"
									value="" class="regular-text"
									placeholder="<?php echo $has_secret ? esc_attr__( '(saved, enter to change)', 'whmcs-connector' ) : ''; ?>" />
							<button type="button" class="button button-secondary whmcs-connector-toggle-password"
									data-target="api_secret" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'whmcs-connector' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</button>
						</div>
						<?php if ( $has_secret ) : ?>
							<span class="whmcs-connector-saved-indicator" aria-label="<?php esc_attr_e( 'Saved', 'whmcs-connector' ); ?>">&#10003;</span>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Your WHMCS API Secret. Encrypted before storage.', 'whmcs-connector' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="api_access_key"><?php esc_html_e( 'API Access Key', 'whmcs-connector' ); ?></label>
					</th>
					<td>
						<div class="whmcs-connector-password-field">
							<input type="password" id="api_access_key" name="api_access_key"
									value="" class="regular-text"
									placeholder="<?php echo $has_access_key ? esc_attr__( '(saved, enter to change)', 'whmcs-connector' ) : ''; ?>" />
							<button type="button" class="button button-secondary whmcs-connector-toggle-password"
									data-target="api_access_key" aria-label="<?php esc_attr_e( 'Toggle access key visibility', 'whmcs-connector' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</button>
						</div>
						<?php if ( $has_access_key ) : ?>
							<span class="whmcs-connector-saved-indicator" aria-label="<?php esc_attr_e( 'Saved', 'whmcs-connector' ); ?>">&#10003;</span>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Optional. Bypasses WHMCS API IP restrictions using the $api_access_key configured in your WHMCS configuration.php. Recommended for local development or dynamic IP environments; for production, IP allowlisting via WHMCS System Settings is recommended.', 'whmcs-connector' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<?php submit_button( __( 'Save Settings', 'whmcs-connector' ), 'primary', 'submit', false ); ?>
				<button type="button" id="whmcs-connector-test-connection" class="button button-secondary" style="margin-left: 8px;">
					<?php esc_html_e( 'Test Connection', 'whmcs-connector' ); ?>
				</button>
			</p>

			<div id="whmcs-connector-test-result" class="whmcs-connector-test-result" role="alert" aria-live="polite"></div>
		</form>
		<?php
	}

	/**
	 * Render the Cache tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_cache_tab( array $settings ): void {
		$ttl_minutes = ( ( $settings['cache_ttl'] ?? 900 ) / 60 );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( self::NONCE_ACTION, 'whmcs_connector_settings_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="cache_ttl"><?php esc_html_e( 'Cache Duration', 'whmcs-connector' ); ?></label>
					</th>
					<td>
						<input type="range" id="cache_ttl" name="cache_ttl"
								value="<?php echo esc_attr( (string) ( $settings['cache_ttl'] ?? 900 ) ); ?>"
								min="60" max="3600" step="60" class="whmcs-connector-range" />
						<span id="cache_ttl_display"><?php echo esc_html( (string) (int) $ttl_minutes ); ?></span>
						<?php esc_html_e( 'minutes', 'whmcs-connector' ); ?>
						<p class="description">
							<?php esc_html_e( 'How long to cache WHMCS API responses (product listings, currencies). Default: 15 minutes.', 'whmcs-connector' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<?php submit_button( __( 'Save Settings', 'whmcs-connector' ), 'primary', 'submit', false ); ?>
				<button type="button" id="whmcs-connector-clear-cache" class="button button-secondary" style="margin-left: 8px;">
					<?php esc_html_e( 'Clear Cache', 'whmcs-connector' ); ?>
				</button>
			</p>

			<div id="whmcs-connector-cache-result" class="whmcs-connector-test-result" role="alert" aria-live="polite"></div>
		</form>
		<?php
	}

	/**
	 * Render the API Log tab.
	 *
	 * @param ApiLog $api_log Logger instance.
	 */
	private function render_log_tab( ApiLog $api_log ): void {
		$entries = $api_log->get_entries();
		$entries = array_reverse( $entries );
		?>
		<h2><?php esc_html_e( 'Recent API Errors', 'whmcs-connector' ); ?></h2>

		<?php if ( empty( $entries ) ) : ?>
			<p><?php esc_html_e( 'No API errors logged.', 'whmcs-connector' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'whmcs-connector' ); ?></th>
						<th><?php esc_html_e( 'Action', 'whmcs-connector' ); ?></th>
						<th><?php esc_html_e( 'HTTP', 'whmcs-connector' ); ?></th>
						<th><?php esc_html_e( 'Message', 'whmcs-connector' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) : ?>
						<tr>
							<td><code><?php echo esc_html( $entry['timestamp'] ?? '' ); ?></code></td>
							<td><code><?php echo esc_html( $entry['action'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( (string) ( $entry['http_status'] ?? 0 ) ); ?></td>
							<td><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the About tab.
	 */
	private function render_about_tab(): void {
		$is_pro = Plugin::is_pro();
		?>
		<div class="whmcs-connector-about">
			<h2><?php esc_html_e( 'WHMCS Connector by RoostKit', 'whmcs-connector' ); ?></h2>
			<table class="form-table whmcs-connector-meta-table">
				<tr>
					<th><?php esc_html_e( 'Version', 'whmcs-connector' ); ?></th>
					<td><code><?php echo esc_html( WHMCS_CONNECTOR_VERSION ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Edition', 'whmcs-connector' ); ?></th>
					<td>
						<?php if ( $is_pro ) : ?>
							<span class="whmcs-connector-badge-pro"><?php esc_html_e( 'PRO EDITION', 'whmcs-connector' ); ?></span>
						<?php else : ?>
							<span class="whmcs-connector-badge-free"><?php esc_html_e( 'FREE EDITION', 'whmcs-connector' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php if ( $is_pro ) : ?>
				<div class="whmcs-connector-pro-active-card">
					<h3><?php esc_html_e( 'Pro Features Active', 'whmcs-connector' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Your installation has full access to all professional blocks, live AJAX domain checking, and atomic theme shortcodes.', 'whmcs-connector' ); ?>
					</p>
					<ul class="whmcs-connector-feature-checklist">
						<?php foreach ( ComparisonData::get_pro_features() as $feature ) : ?>
							<li>
								<span class="whmcs-icon-check">&#10003;</span>
								<div>
									<strong><?php echo esc_html( $feature['title'] ); ?></strong>
									<span class="description"><?php echo esc_html( $feature['description'] ); ?></span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php else : ?>
				<div class="whmcs-connector-comparison-section">
					<h3><?php esc_html_e( 'Free vs. Pro Comparison', 'whmcs-connector' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'WHMCS Connector Free provides essential integration. Upgrade to Pro for high-converting pricing blocks, interactive VPS calculators, and theme-sync shortcodes.', 'whmcs-connector' ); ?>
					</p>
					<?php echo ComparisonData::render_table(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<div class="whmcs-connector-upsell">
					<h3><?php esc_html_e( 'Upgrade Your WHMCS Experience', 'whmcs-connector' ); ?></h3>
					<p><?php esc_html_e( 'Unlock SaaS pricing tables with billing cycle toggles, VPS resource sliders, live domain search, and atomic theme-integration shortcodes.', 'whmcs-connector' ); ?></p>
					<p class="whmcs-connector-price">
						<del>39,90 &euro;</del>
						<span class="whmcs-connector-price-current"><?php esc_html_e( 'Instant Access on RoostKit', 'whmcs-connector' ); ?></span>
					</p>
					<a href="https://roostkit.site/whmcs-connector" class="button button-primary button-hero" target="_blank" rel="noopener">
						<?php esc_html_e( 'Explore Addons', 'whmcs-connector' ); ?> &rarr;
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
