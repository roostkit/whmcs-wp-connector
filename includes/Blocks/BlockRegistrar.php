<?php
/**
 * Gutenberg block registrar.
 *
 * @package RoostKit\WhmcsConnector\Blocks
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Blocks;

use RoostKit\WhmcsConnector\Api\ApiLog;
use RoostKit\WhmcsConnector\Api\Client;
use RoostKit\WhmcsConnector\License\LicenseClient;
use RoostKit\WhmcsConnector\ProductRepository;
use RoostKit\WhmcsConnector\Shortcodes\ClientAreaShortcode;
use RoostKit\WhmcsConnector\Shortcodes\LoginShortcode;
use RoostKit\WhmcsConnector\Shortcodes\PricingShortcode;

/**
 * Registers all Gutenberg blocks, patterns, and their render callbacks.
 */
final class BlockRegistrar {

	/**
	 * Product repository instance.
	 *
	 * @var ProductRepository|null
	 */
	private ?ProductRepository $repository;

	/**
	 * API client instance.
	 *
	 * @var Client|null
	 */
	private ?Client $api_client;

	/**
	 * API logger instance.
	 *
	 * @var ApiLog|null
	 */
	private ?ApiLog $api_log;

	/**
	 * WHMCS installation URL.
	 *
	 * @var string
	 */
	private string $whmcs_url;

	/**
	 * Constructor.
	 *
	 * @param ProductRepository|null $repository Product repository.
	 * @param Client|null            $api_client API client instance.
	 * @param string                 $whmcs_url  WHMCS URL.
	 * @param ApiLog|null            $api_log    API log service.
	 */
	public function __construct(
		?ProductRepository $repository = null,
		?Client $api_client = null,
		string $whmcs_url = '',
		?ApiLog $api_log = null
	) {
		$this->repository = $repository;
		$this->api_client = $api_client;
		$this->whmcs_url  = untrailingslashit( $whmcs_url );
		$this->api_log    = $api_log ?? new ApiLog();
	}

	/**
	 * Register all blocks and patterns.
	 */
	public function register(): void {
		$this->register_assets();
		$this->register_block_category();
		$this->register_patterns();
		$this->register_blocks();
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Register shared styles for block.json "style" dependencies.
	 */
	public function register_assets(): void {
		wp_register_style(
			'whmcs-connector-styles',
			WHMCS_CONNECTOR_URL . 'assets/frontend/shortcodes.css',
			[],
			WHMCS_CONNECTOR_VERSION
		);
	}

	/**
	 * Enqueue frontend CSS and data in the block editor for styled live previews and Pro gating.
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_style(
			'whmcs-connector-block-editor',
			WHMCS_CONNECTOR_URL . 'assets/frontend/shortcodes.css',
			[],
			WHMCS_CONNECTOR_VERSION
		);

		$is_pro = class_exists( LicenseClient::class ) && LicenseClient::is_pro_active();

		$script_data = [
			'isPro'      => $is_pro,
			'whmcsUrl'   => $this->whmcs_url,
			'upgradeUrl' => 'https://roostkit.site/whmcs-connector',
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'restUrl'    => rest_url( 'whmcs-connector/v1' ),
			'restNonce'  => wp_create_nonce( 'wp_rest' ),
		];

		wp_add_inline_script(
			'wp-blocks',
			'window.whmcsConnectorData = ' . wp_json_encode( $script_data ) . ';',
			'before'
		);

		wp_register_script(
			'whmcs-connector-blocks-common',
			WHMCS_CONNECTOR_URL . 'assets/admin/blocks-common.js',
			[ 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-api-fetch' ],
			WHMCS_CONNECTOR_VERSION,
			true
		);
		wp_enqueue_script( 'whmcs-connector-blocks-common' );
	}

	/**
	 * Register the block category.
	 */
	private function register_block_category(): void {
		add_filter(
			'block_categories_all',
			function ( array $categories ): array {
				array_unshift(
					$categories,
					[
						'slug'  => 'whmcs-connector',
						'title' => __( 'WHMCS Connector', 'whmcs-connector' ),
						'icon'  => 'admin-links',
					]
				);
				return $categories;
			}
		);
	}

	/**
	 * Register block patterns.
	 */
	private function register_patterns(): void {
		$patterns = new Patterns();
		$patterns->register();
	}

	/**
	 * Register individual blocks from their block.json files.
	 */
	private function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$blocks_dir = WHMCS_CONNECTOR_DIR . 'includes/Blocks/';

		// 1. Free: Login Form.
		if ( file_exists( $blocks_dir . 'Login/block.json' ) ) {
			register_block_type(
				$blocks_dir . 'Login',
				[
					'render_callback' => [ $this, 'render_login' ],
				]
			);
		}

		// 2. Free: Client Area.
		if ( file_exists( $blocks_dir . 'ClientArea/block.json' ) ) {
			register_block_type(
				$blocks_dir . 'ClientArea'
			);
		}

		// 3. Free: Pricing (plain card grid).
		if ( file_exists( $blocks_dir . 'Pricing/block.json' ) ) {
			register_block_type(
				$blocks_dir . 'Pricing',
				[
					'render_callback' => [ $this, 'render_pricing' ],
				]
			);
		}

		// 4. Pro: SaaS Pricing Table (Toggle Grid).
		if ( file_exists( $blocks_dir . 'SaasPricing/block.json' ) ) {
			register_block_type(
				$blocks_dir . 'SaasPricing',
				[
					'render_callback' => [ $this, 'render_saas_pricing' ],
				]
			);
		}

		// 5. Pro: Featured Web Hosting Grid.
		if ( file_exists( $blocks_dir . 'FeaturedHosting/block.json' ) ) {
			register_block_type(
				$blocks_dir . 'FeaturedHosting',
				[
					'render_callback' => [ $this, 'render_featured_hosting' ],
				]
			);
		}

		// 6. Pro: VPS Resource Slider.
		if ( file_exists( $blocks_dir . 'VpsSlider/block.json' ) ) {
			register_block_type(
				$blocks_dir . 'VpsSlider',
				[
					'render_callback' => [ $this, 'render_vps_slider' ],
				]
			);
		}

		// 7. Pro: Domain Search.
		if ( file_exists( $blocks_dir . 'DomainSearch/block.json' ) ) {
			register_block_type(
				$blocks_dir . 'DomainSearch',
				[
					'render_callback' => [ $this, 'render_domain_search' ],
				]
			);
		}
	}

	/**
	 * Render callback for the Login block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string HTML output.
	 */
	public function render_login( array $attributes ): string {
		$shortcode = new LoginShortcode( $this->api_client );
		return $shortcode->render( $attributes );
	}

	/**
	 * Render callback for the Client Area block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string HTML output.
	 */
	public function render_client_area( array $attributes ): string {
		$shortcode = new ClientAreaShortcode( $this->whmcs_url );
		return $shortcode->render( $attributes );
	}

	/**
	 * Render callback for the Free Pricing block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string HTML output.
	 */
	public function render_pricing( array $attributes ): string {
		if ( null === $this->repository ) {
			return '';
		}
		$shortcode = new PricingShortcode( $this->repository, null, $this->whmcs_url );
		return $shortcode->render( $attributes );
	}

	/**
	 * Render callback for Domain Search block (Pro Gated).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string HTML output.
	 */
	public function render_domain_search( array $attributes ): string {
		// Strict server-side license gate — render nothing without active license.
		if ( ! class_exists( LicenseClient::class ) || ! LicenseClient::is_pro_active() ) {
			return '';
		}

		// ── Core content attributes ──────────────────────────────────────────────
		$heading      = $attributes['heading'] ?? __( 'Find Your Perfect Domain Name', 'whmcs-connector' );
		$subheading   = $attributes['subheading'] ?? __( 'Enter your desired domain name to check availability and register instantly.', 'whmcs-connector' );
		$placeholder  = $attributes['placeholder'] ?? 'example.com';
		$button_text  = $attributes['button_text'] ?? __( 'Search Domain', 'whmcs-connector' );
		$tlds_raw     = $attributes['tlds'] ?? '.com, .net, .org, .io, .co';
		$accent_color = ! empty( $attributes['accentColor'] ) ? sanitize_hex_color( $attributes['accentColor'] ) : '';
		$extra_class  = ! empty( $attributes['class'] ) ? ' ' . sanitize_html_class( $attributes['class'] ) : '';

		// ── Content label attributes ─────────────────────────────────────────────
		$register_btn_text = sanitize_text_field( $attributes['register_btn_text'] ?? __( 'Register Now →', 'whmcs-connector' ) );
		$transfer_btn_text = sanitize_text_field( $attributes['transfer_btn_text'] ?? __( 'Transfer Domain', 'whmcs-connector' ) );
		$select_btn_text   = sanitize_text_field( $attributes['select_btn_text'] ?? __( 'Select', 'whmcs-connector' ) );
		$suggestions_title = sanitize_text_field( $attributes['suggestions_title'] ?? __( 'Alternative Extensions Available:', 'whmcs-connector' ) );
		$available_label   = sanitize_text_field( $attributes['available_label'] ?? __( '✓ Available', 'whmcs-connector' ) );
		$unavailable_label = sanitize_text_field( $attributes['unavailable_label'] ?? __( '✕ Unavailable', 'whmcs-connector' ) );

		// ── Behavior attributes ──────────────────────────────────────────────────
		$max_suggestions  = max( 1, min( 8, (int) ( $attributes['max_suggestions'] ?? 4 ) ) );
		$default_tld      = sanitize_text_field( $attributes['default_tld'] ?? '.com' );
		$show_suggestions = isset( $attributes['show_suggestions'] ) ? (bool) $attributes['show_suggestions'] : true;
		$show_tld_badges  = isset( $attributes['show_tld_badges'] ) ? (bool) $attributes['show_tld_badges'] : true;
		$open_in_new_tab  = isset( $attributes['open_in_new_tab'] ) ? (bool) $attributes['open_in_new_tab'] : true;

		// ── Style attributes ─────────────────────────────────────────────────────
		$style_vars = [
			'--whmcs-ds-card-bg'       => sanitize_text_field( $attributes['cardBg'] ?? '' ),
			'--whmcs-ds-card-padding'  => sanitize_text_field( $attributes['cardPadding'] ?? '' ),
			'--whmcs-ds-card-radius'   => sanitize_text_field( $attributes['cardBorderRadius'] ?? '' ),
			'--whmcs-ds-card-border'   => sanitize_text_field( $attributes['cardBorderColor'] ?? '' ),
			'--whmcs-ds-card-shadow'   => sanitize_text_field( $attributes['cardShadow'] ?? '' ),
			'--whmcs-ds-input-bg'      => sanitize_text_field( $attributes['inputBg'] ?? '' ),
			'--whmcs-ds-input-radius'  => sanitize_text_field( $attributes['inputBorderRadius'] ?? '' ),
			'--whmcs-ds-input-padding' => sanitize_text_field( $attributes['inputPadding'] ?? '' ),
			'--whmcs-ds-btn-radius'    => sanitize_text_field( $attributes['buttonBorderRadius'] ?? '' ),
			'--whmcs-ds-btn-padding'   => sanitize_text_field( $attributes['buttonPadding'] ?? '' ),
			'--whmcs-ds-badge-bg'      => sanitize_text_field( $attributes['badgeBg'] ?? '' ),
			'--whmcs-ds-badge-color'   => sanitize_text_field( $attributes['badgeColor'] ?? '' ),
			'--whmcs-ds-badge-radius'  => sanitize_text_field( $attributes['badgeBorderRadius'] ?? '' ),
		];

		// ── Derived values ───────────────────────────────────────────────────────
		$inline_style = $accent_color ? '--whmcs-connector-accent: ' . esc_attr( $accent_color ) . ';' : '';
		foreach ( $style_vars as $var_name => $var_value ) {
			if ( '' !== $var_value ) {
				$inline_style .= esc_attr( $var_name ) . ': ' . esc_attr( $var_value ) . ';';
			}
		}
		$style_attr = $inline_style ? ' style="' . $inline_style . '"' : '';
		$tlds       = array_filter( array_map( 'trim', explode( ',', (string) $tlds_raw ) ) );
		$rest_url   = rest_url( 'whmcs-connector/v1/domain-check' );

		ob_start();
		?>
		<div class="whmcs-domain-search-block<?php echo esc_attr( $extra_class ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			data-whmcs-url="<?php echo esc_url( $this->whmcs_url ); ?>"
			data-rest-url="<?php echo esc_url( $rest_url ); ?>"
			data-tlds="<?php echo esc_attr( (string) $tlds_raw ); ?>"
			data-register-btn-text="<?php echo esc_attr( $register_btn_text ); ?>"
			data-transfer-btn-text="<?php echo esc_attr( $transfer_btn_text ); ?>"
			data-select-btn-text="<?php echo esc_attr( $select_btn_text ); ?>"
			data-suggestions-title="<?php echo esc_attr( $suggestions_title ); ?>"
			data-available-label="<?php echo esc_attr( $available_label ); ?>"
			data-unavailable-label="<?php echo esc_attr( $unavailable_label ); ?>"
			data-max-suggestions="<?php echo esc_attr( (string) $max_suggestions ); ?>"
			data-default-tld="<?php echo esc_attr( $default_tld ); ?>"
			data-show-suggestions="<?php echo esc_attr( $show_suggestions ? 'true' : 'false' ); ?>"
			data-open-in-new-tab="<?php echo esc_attr( $open_in_new_tab ? 'true' : 'false' ); ?>">

			<div class="whmcs-domain-search-card">
				<?php if ( ! empty( $heading ) ) : ?>
					<h3 class="whmcs-domain-search-heading"><?php echo esc_html( $heading ); ?></h3>
				<?php endif; ?>
				<?php if ( ! empty( $subheading ) ) : ?>
					<p class="whmcs-domain-search-subheading"><?php echo esc_html( $subheading ); ?></p>
				<?php endif; ?>

				<form class="whmcs-domain-search-form" data-whmcs-url="<?php echo esc_url( $this->whmcs_url ); ?>" data-rest-url="<?php echo esc_url( $rest_url ); ?>">
					<div class="whmcs-domain-search-input-wrap">
						<input type="text" class="whmcs-domain-search-input" placeholder="<?php echo esc_attr( $placeholder ); ?>" required />
						<button type="submit" class="whmcs-connector-btn whmcs-domain-search-submit wp-element-button">
							<span class="whmcs-btn-text"><?php echo esc_html( $button_text ); ?></span>
							<span class="whmcs-btn-spinner" style="display: none;"></span>
						</button>
					</div>
				</form>

				<?php if ( $show_tld_badges && ! empty( $tlds ) ) : ?>
					<div class="whmcs-domain-search-tlds">
						<?php foreach ( $tlds as $tld ) : ?>
							<button type="button" class="whmcs-tld-badge" data-tld="<?php echo esc_attr( $tld ); ?>"><?php echo esc_html( $tld ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="whmcs-domain-results" style="display: none;"></div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}   /**
		 * Render callback for SaaS Pricing Table block (Pro - Modern SaaS).
		 *
		 * @param array<string, mixed> $attributes Block attributes.
		 * @return string HTML output.
		 */
	public function render_saas_pricing( array $attributes ): string {
		// Strict server-side license gate — render nothing without active license.
		if ( ! class_exists( LicenseClient::class ) || ! LicenseClient::is_pro_active() ) {
			return '';
		}

		$extra_class        = ! empty( $attributes['class'] ) ? ' ' . sanitize_html_class( $attributes['class'] ) : '';
		$button_text        = $attributes['button_text'] ?? __( 'Buy Now', 'whmcs-connector' );
		$accent_color       = ! empty( $attributes['accentColor'] ) ? sanitize_hex_color( $attributes['accentColor'] ) : '';
		$style_attr         = $accent_color ? ' style="--whmcs-connector-accent: ' . esc_attr( $accent_color ) . ';"' : '';
		$show_savings_badge = ! isset( $attributes['show_savings_badge'] ) || (bool) $attributes['show_savings_badge'];

		// Support new plans repeater array or fallback to legacy tier attributes.
		$raw_plans = [];
		if ( ! empty( $attributes['plans'] ) && is_array( $attributes['plans'] ) ) {
			$raw_plans = $attributes['plans'];
		} else {
			// Legacy fallback.
			for ( $i = 1; $i <= 3; $i++ ) {
				$raw_plans[] = [
					'pid'        => $attributes[ "tier{$i}_pid" ] ?? (string) $i,
					'tagline'    => $attributes[ "tier{$i}_desc" ] ?? '',
					'is_popular' => ( $attributes[ "tier{$i}_badge" ] ?? '' ) === 'POPULAR',
					'features'   => ! empty( $attributes[ "tier{$i}_features" ] )
						? array_filter( array_map( 'trim', explode( ',', (string) $attributes[ "tier{$i}_features" ] ) ) )
						: [],
				];
			}
		}

		if ( empty( $raw_plans ) || null === $this->repository ) {
			return '';
		}

		$plans_data      = [];
		$all_plan_cycles = [];

		foreach ( $raw_plans as $idx => $plan_item ) {
			$pid = absint( $plan_item['pid'] ?? 0 );
			if ( $pid <= 0 ) {
				continue;
			}

			$product = $this->repository->get_product( $pid );
			if ( ! is_array( $product ) || empty( $product ) ) {
				if ( null !== $this->api_log ) {
					$this->api_log->log( 'render_saas_pricing', "Product ID {$pid} was not found in WHMCS. Skipping plan on frontend.", 404 );
				}
				continue;
			}

			$prod_name = (string) ( $product['name'] ?? __( 'Plan', 'whmcs-connector' ) );
			$prod_desc = (string) ( $product['description'] ?? '' );
			$pricing   = is_array( $product['pricing'] ?? null ) ? $product['pricing'] : [];
			$rates     = $this->repository->get_default_currency_pricing( $pricing );
			$cycles    = $this->repository->get_available_cycles( $pricing );

			if ( empty( $cycles ) ) {
				continue;
			}

			$all_plan_cycles[] = $cycles;

			// Features resolution: use custom override if set, otherwise extract from description.
			$features = ! empty( $plan_item['features'] ) && is_array( $plan_item['features'] )
				? $plan_item['features']
				: $this->repository->extract_features_from_description( $prod_desc );

			// Tagline resolution.
			$tagline = ! empty( $plan_item['tagline'] )
				? (string) $plan_item['tagline']
				: $this->repository->extract_tagline_from_description( $prod_desc );

			$is_popular = ! empty( $plan_item['is_popular'] ) || ! empty( $plan_item['badge'] );

			// Compute prices and savings for all cycles.
			$prices_map  = [];
			$savings_map = [];
			foreach ( $cycles as $cycle_key ) {
				if ( isset( $rates[ $cycle_key ] ) ) {
					$prices_map[ $cycle_key ] = $this->repository->format_price( (float) $rates[ $cycle_key ], $pricing );
					$savings                  = $this->repository->compute_savings( $pricing, $cycle_key );
					if ( $savings > 0 ) {
						$savings_map[ $cycle_key ] = $savings;
					}
				}
			}

			$plans_data[] = [
				'pid'        => $pid,
				'name'       => $prod_name,
				'tagline'    => $tagline,
				'is_popular' => $is_popular,
				'features'   => $features,
				'cycles'     => $cycles,
				'prices'     => $prices_map,
				'savings'    => $savings_map,
			];
		}

		if ( empty( $plans_data ) ) {
			return '';
		}

		// Compute intersection of available cycles across all selected plans.
		$intersection_cycles = ! empty( $all_plan_cycles )
			? array_values( array_intersect( ...$all_plan_cycles ) )
			: [];

		if ( empty( $intersection_cycles ) ) {
			$intersection_cycles = $plans_data[0]['cycles'];
		}

		$requested_default = (string) ( $attributes['default_cycle'] ?? '' );
		$active_cycle      = in_array( $requested_default, $intersection_cycles, true )
			? $requested_default
			: ( in_array( 'annually', $intersection_cycles, true ) ? 'annually' : $intersection_cycles[0] );

		$cycle_period_labels = [
			'monthly'      => __( '/ month', 'whmcs-connector' ),
			'quarterly'    => __( '/ quarter', 'whmcs-connector' ),
			'semiannually' => __( '/ 6 months', 'whmcs-connector' ),
			'annually'     => __( '/ year', 'whmcs-connector' ),
			'biennially'   => __( '/ 2 years', 'whmcs-connector' ),
			'triennially'  => __( '/ 3 years', 'whmcs-connector' ),
		];

		ob_start();
		?>
		<div class="whmcs-saas-pricing-wrapper<?php echo esc_attr( $extra_class ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-whmcs-url="<?php echo esc_url( $this->whmcs_url ); ?>">
			<?php if ( count( $intersection_cycles ) > 1 ) : ?>
				<div class="whmcs-saas-hero-header">
					<div class="whmcs-saas-capsule-toggle" role="tablist">
						<?php foreach ( $intersection_cycles as $cycle_key ) : ?>
							<?php
							$cycle_label = ProductRepository::CYCLE_LABELS[ $cycle_key ] ?? ucfirst( $cycle_key );
							$is_active   = ( $cycle_key === $active_cycle );
							?>
							<button type="button" class="whmcs-saas-toggle-btn<?php echo $is_active ? ' is-active' : ''; ?>"
								data-cycle="<?php echo esc_attr( $cycle_key ); ?>"
								role="tab"
								aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
								<?php echo esc_html( strtoupper( $cycle_label ) ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="whmcs-saas-cards-grid" style="<?php echo count( $intersection_cycles ) <= 1 ? 'margin-top: 0;' : ''; ?>">
				<?php foreach ( $plans_data as $plan ) : ?>
					<?php
					$reset_val    = ! empty( $plan['prices'] ) ? reset( $plan['prices'] ) : '$0.00';
					$card_price   = isset( $plan['prices'][ $active_cycle ] ) ? $plan['prices'][ $active_cycle ] : $reset_val;
					$card_savings = $plan['savings'][ $active_cycle ] ?? 0;
					$period_label = $cycle_period_labels[ $active_cycle ] ?? ( '/' . $active_cycle );
					$order_url    = ! empty( $this->whmcs_url )
						? $this->whmcs_url . '/cart.php?a=add&pid=' . rawurlencode( (string) $plan['pid'] ) . '&billingcycle=' . rawurlencode( $active_cycle )
						: '#';
					?>
					<div class="whmcs-saas-card<?php echo $plan['is_popular'] ? ' is-popular-card' : ''; ?>"
						data-pid="<?php echo esc_attr( (string) $plan['pid'] ); ?>"
						data-prices="<?php echo esc_attr( wp_json_encode( $plan['prices'] ) ); ?>"
						data-savings="<?php echo esc_attr( wp_json_encode( $plan['savings'] ) ); ?>"
						data-periods="<?php echo esc_attr( wp_json_encode( $cycle_period_labels ) ); ?>">

						<div class="whmcs-saas-title-row">
							<h3 class="whmcs-saas-plan-title"><?php echo esc_html( $plan['name'] ); ?></h3>
							<?php if ( $plan['is_popular'] ) : ?>
								<span class="whmcs-saas-popular-badge"><?php esc_html_e( 'POPULAR', 'whmcs-connector' ); ?></span>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $plan['tagline'] ) ) : ?>
							<p class="whmcs-saas-plan-desc"><?php echo esc_html( $plan['tagline'] ); ?></p>
						<?php endif; ?>

						<div class="whmcs-saas-price-row">
							<span class="whmcs-saas-price-num"><?php echo esc_html( $card_price ); ?></span>
							<span class="whmcs-saas-price-period"><?php echo esc_html( ' ' . $period_label ); ?></span>
						</div>

						<?php if ( $show_savings_badge ) : ?>
							<div class="whmcs-saas-savings-row" style="<?php echo $card_savings <= 0 ? 'display:none;' : ''; ?>">
								<?php /* translators: %d: Savings discount percentage. */ ?>
								<span class="whmcs-saas-discount-badge"><?php echo esc_html( sprintf( __( 'Save %d%%', 'whmcs-connector' ), $card_savings ) ); ?></span>
							</div>
						<?php endif; ?>

						<a href="<?php echo esc_url( $order_url ); ?>" class="whmcs-saas-buy-btn wp-element-button" target="_blank" rel="noopener">
							<?php echo esc_html( $button_text ); ?>
						</a>

						<?php if ( ! empty( $plan['features'] ) ) : ?>
							<ul class="whmcs-saas-feature-list">
								<?php foreach ( $plan['features'] as $feat ) : ?>
									<?php
									$feat_str  = (string) $feat;
									$is_cross  = str_starts_with( $feat_str, '!' ) || str_starts_with( $feat_str, '✕' );
									$clean_txt = preg_replace( '/^[!✕✓✔]\s*/u', '', $feat_str );
									?>
									<li class="<?php echo $is_cross ? 'is-disabled' : 'is-enabled'; ?>">
										<span class="whmcs-feat-icon"><?php echo $is_cross ? '✕' : '✓'; ?></span>
										<span class="whmcs-feat-text"><?php echo esc_html( (string) $clean_txt ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render callback for Featured Web Hosting Grid block (Pro).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string HTML output.
	 */
	public function render_featured_hosting( array $attributes ): string {
		// Strict server-side license gate — render nothing without active license.
		if ( ! class_exists( LicenseClient::class ) || ! LicenseClient::is_pro_active() ) {
			return '';
		}

		$extra_class        = ! empty( $attributes['class'] ) ? ' ' . sanitize_html_class( $attributes['class'] ) : '';
		$button_text        = $attributes['button_text'] ?? 'ORDER';
		$features_label     = $attributes['features_label'] ?? __( 'Features', 'whmcs-connector' );
		$accent_color       = ! empty( $attributes['accentColor'] ) ? sanitize_hex_color( $attributes['accentColor'] ) : '';
		$style_attr         = $accent_color ? ' style="--whmcs-connector-accent: ' . esc_attr( $accent_color ) . ';"' : '';
		$show_savings_badge = ! isset( $attributes['show_savings_badge'] ) || (bool) $attributes['show_savings_badge'];
		$target_cycle       = (string) ( $attributes['cycle'] ?? 'monthly' );

		// Support new plans repeater array or fallback to legacy plan1..3 attributes.
		$raw_plans = [];
		if ( ! empty( $attributes['plans'] ) && is_array( $attributes['plans'] ) ) {
			$raw_plans = $attributes['plans'];
		} else {
			for ( $i = 1; $i <= 3; $i++ ) {
				$raw_plans[] = [
					'pid'      => $attributes[ "plan{$i}_pid" ] ?? (string) $i,
					'tagline'  => $attributes[ "plan{$i}_tagline" ] ?? '',
					'ribbon'   => $attributes[ "plan{$i}_ribbon" ] ?? ( 2 === $i ? 'Most Popular' : '' ),
					'features' => ! empty( $attributes[ "plan{$i}_features" ] )
						? array_filter( array_map( 'trim', explode( ',', (string) $attributes[ "plan{$i}_features" ] ) ) )
						: [],
				];
			}
		}

		if ( empty( $raw_plans ) || null === $this->repository ) {
			return '';
		}

		$plans_data = [];
		foreach ( $raw_plans as $idx => $plan_item ) {
			$pid = absint( $plan_item['pid'] ?? 0 );
			if ( $pid <= 0 ) {
				continue;
			}

			$product = $this->repository->get_product( $pid );
			if ( ! is_array( $product ) || empty( $product ) ) {
				if ( null !== $this->api_log ) {
					$this->api_log->log( 'render_featured_hosting', "Product ID {$pid} was not found in WHMCS. Skipping plan on frontend.", 404 );
				}
				continue;
			}

			$prod_name = (string) ( $product['name'] ?? __( 'Hosting Plan', 'whmcs-connector' ) );
			$prod_desc = (string) ( $product['description'] ?? '' );
			$pricing   = is_array( $product['pricing'] ?? null ) ? $product['pricing'] : [];
			$rates     = $this->repository->get_default_currency_pricing( $pricing );
			$cycles    = $this->repository->get_available_cycles( $pricing );

			if ( empty( $cycles ) ) {
				continue;
			}

			$cycle_to_use = in_array( $target_cycle, $cycles, true ) ? $target_cycle : $cycles[0];
			$price_val    = (float) ( $rates[ $cycle_to_use ] ?? 0 );
			$price_str    = $this->repository->format_price( $price_val, $pricing );

			// Compute savings against baseline if applicable.
			$savings     = $this->repository->compute_savings( $pricing, $cycle_to_use );
			$regular_str = '';
			if ( $savings > 0 && isset( ProductRepository::CYCLE_MONTHS[ $cycle_to_use ] ) ) {
				$months       = ProductRepository::CYCLE_MONTHS[ $cycle_to_use ];
				$monthly_rate = isset( $rates['monthly'] ) ? (float) $rates['monthly'] : ( $price_val / ( 1 - ( $savings / 100 ) ) / $months );
				$regular_str  = $this->repository->format_price( $monthly_rate * $months, $pricing );
			}

			$tagline = ! empty( $plan_item['tagline'] )
				? (string) $plan_item['tagline']
				: $this->repository->extract_tagline_from_description( $prod_desc );

			$ribbon = ! empty( $plan_item['ribbon'] )
				? (string) $plan_item['ribbon']
				: ( ! empty( $plan_item['is_popular'] ) ? __( 'Most Popular', 'whmcs-connector' ) : '' );

			$features = ! empty( $plan_item['features'] ) && is_array( $plan_item['features'] )
				? $plan_item['features']
				: $this->repository->extract_features_from_description( $prod_desc );

			$order_url = ! empty( $this->whmcs_url )
				? $this->whmcs_url . '/cart.php?a=add&pid=' . rawurlencode( (string) $pid ) . '&billingcycle=' . rawurlencode( $cycle_to_use )
				: '#';

			$cycle_suffix = 'monthly' === $cycle_to_use ? '/mo' : ( 'annually' === $cycle_to_use ? '/yr' : ( '/' . $cycle_to_use ) );

			$plans_data[] = [
				'pid'           => $pid,
				'name'          => $prod_name,
				'tagline'       => $tagline,
				'price'         => $price_str,
				'regular_price' => $regular_str,
				'savings'       => $savings,
				'ribbon'        => $ribbon,
				'features'      => $features,
				'order_url'     => $order_url,
				'cycle_suffix'  => $cycle_suffix,
			];
		}

		if ( empty( $plans_data ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="whmcs-ultahost-wrapper<?php echo esc_attr( $extra_class ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-whmcs-url="<?php echo esc_url( $this->whmcs_url ); ?>">
			<div class="whmcs-ultahost-grid">
				<?php foreach ( $plans_data as $plan ) : ?>
					<?php $is_popular = ! empty( $plan['ribbon'] ); ?>
					<div class="whmcs-ultahost-card<?php echo $is_popular ? ' is-popular' : ''; ?>">
						<?php if ( $is_popular ) : ?>
							<div class="whmcs-ultahost-ribbon"><?php echo esc_html( $plan['ribbon'] ); ?></div>
						<?php endif; ?>

						<h3 class="whmcs-ultahost-name"><?php echo esc_html( $plan['name'] ); ?></h3>
						<?php if ( ! empty( $plan['tagline'] ) ) : ?>
							<p class="whmcs-ultahost-tagline"><?php echo esc_html( $plan['tagline'] ); ?></p>
						<?php endif; ?>

						<div class="whmcs-ultahost-price-box">
							<span class="whmcs-ultahost-price"><?php echo esc_html( $plan['price'] ); ?></span>
							<span class="whmcs-ultahost-per"><?php echo esc_html( $plan['cycle_suffix'] ); ?></span>
						</div>

						<?php if ( ! empty( $plan['regular_price'] ) || ( $show_savings_badge && $plan['savings'] > 0 ) ) : ?>
							<div class="whmcs-ultahost-discount-row">
								<?php if ( ! empty( $plan['regular_price'] ) ) : ?>
									<span class="whmcs-ultahost-strike"><?php echo esc_html( $plan['regular_price'] ); ?></span>
								<?php endif; ?>
								<?php if ( $show_savings_badge && $plan['savings'] > 0 ) : ?>
									<?php /* translators: %d: Savings discount percentage. */ ?>
									<span class="whmcs-ultahost-discount-pill"><?php echo esc_html( sprintf( __( 'Save %d%%', 'whmcs-connector' ), $plan['savings'] ) ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<a href="<?php echo esc_url( $plan['order_url'] ); ?>" class="whmcs-ultahost-order-btn wp-element-button <?php echo $is_popular ? 'is-solid' : 'is-outline'; ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $button_text ); ?>
						</a>

						<h4 class="whmcs-ultahost-features-title"><?php echo esc_html( $features_label ); ?></h4>

						<?php if ( ! empty( $plan['features'] ) ) : ?>
							<ul class="whmcs-ultahost-feature-list">
								<?php foreach ( $plan['features'] as $spec ) : ?>
									<?php $clean_spec = preg_replace( '/^[!✕✓✔]\s*/u', '', (string) $spec ); ?>
									<li>
										<span class="whmcs-ultahost-check">✓</span>
										<span class="whmcs-ultahost-text"><?php echo esc_html( (string) $clean_spec ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render callback for VPS Resource Slider block (Pro).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string HTML output.
	 */
	public function render_vps_slider( array $attributes ): string {
		// Strict server-side license gate — render nothing without active license.
		if ( ! class_exists( LicenseClient::class ) || ! LicenseClient::is_pro_active() ) {
			return '';
		}

		$heading      = $attributes['heading'] ?? __( 'Custom Cloud VPS Configurator', 'whmcs-connector' );
		$subheading   = $attributes['subheading'] ?? __( 'Scale computing cores, high-speed memory, and NVMe Gen4 storage dynamically.', 'whmcs-connector' );
		$base_pid     = (string) ( $attributes['base_pid'] ?? '1' );
		$btn_text     = (string) ( $attributes['button_text'] ?? __( 'Deploy Cloud Server', 'whmcs-connector' ) );
		$accent_color = ! empty( $attributes['accentColor'] ) ? sanitize_hex_color( $attributes['accentColor'] ) : '';
		$extra_class  = ! empty( $attributes['class'] ) ? ' ' . sanitize_html_class( $attributes['class'] ) : '';
		$style_attr   = $accent_color ? ' style="--whmcs-connector-accent: ' . esc_attr( $accent_color ) . ';"' : '';

		// Pull live configurable option pricing from WHMCS product repository.
		$base_price  = (float) ( $attributes['base_price'] ?? 12.00 );
		$cpu_rate    = (float) ( $attributes['cpu_price_per_core'] ?? 6.00 );
		$ram_rate    = (float) ( $attributes['ram_price_per_gb'] ?? 3.50 );
		$disk_rate   = (float) ( $attributes['disk_price_per_10gb'] ?? 1.50 );
		$currency    = (string) ( $attributes['currency_symbol'] ?? '$' );
		$cpu_opt_id  = absint( $attributes['cpu_opt_id'] ?? 0 );
		$ram_opt_id  = absint( $attributes['ram_opt_id'] ?? 0 );
		$disk_opt_id = absint( $attributes['disk_opt_id'] ?? 0 );

		$pid = absint( $base_pid );
		if ( $pid > 0 && null !== $this->repository ) {
			$vps_rates  = $this->repository->get_vps_config_rates( $pid );
			$base_price = $vps_rates['base_price'];
			$cpu_rate   = $vps_rates['cpu_rate'];
			$ram_rate   = $vps_rates['ram_rate'];
			$disk_rate  = $vps_rates['disk_rate'];
			$currency   = $vps_rates['currency'];

			if ( 0 === $cpu_opt_id && ! empty( $vps_rates['cpu_opt_id'] ) ) {
				$cpu_opt_id = (int) $vps_rates['cpu_opt_id'];
			}
			if ( 0 === $ram_opt_id && ! empty( $vps_rates['ram_opt_id'] ) ) {
				$ram_opt_id = (int) $vps_rates['ram_opt_id'];
			}
			if ( 0 === $disk_opt_id && ! empty( $vps_rates['disk_opt_id'] ) ) {
				$disk_opt_id = (int) $vps_rates['disk_opt_id'];
			}
		}

		if ( 0 === $cpu_opt_id ) {
			$cpu_opt_id = 1;
		}
		if ( 0 === $ram_opt_id ) {
			$ram_opt_id = 2;
		}
		if ( 0 === $disk_opt_id ) {
			$disk_opt_id = 3;
		}

		// Initial calculated total for 4 CPU, 8 RAM, 120 Disk: base + 3*(cpu) + 7*(ram) + 10*(disk/10).
		$initial_total = $base_price + ( 3 * $cpu_rate ) + ( 7 * $ram_rate ) + ( 10 * $disk_rate );
		$order_url     = ! empty( $this->whmcs_url )
			? $this->whmcs_url . '/cart.php?a=add&pid=' . rawurlencode( (string) $base_pid ) . '&billingcycle=monthly&configoption[' . $cpu_opt_id . ']=4&configoption[' . $ram_opt_id . ']=8&configoption[' . $disk_opt_id . ']=120'
			: '#';

		ob_start();
		?>
		<div class="whmcs-vps-slider-block<?php echo esc_attr( $extra_class ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			data-whmcs-url="<?php echo esc_url( $this->whmcs_url ); ?>"
			data-base-pid="<?php echo esc_attr( (string) $base_pid ); ?>"
			data-base-price="<?php echo esc_attr( (string) $base_price ); ?>"
			data-cpu-rate="<?php echo esc_attr( (string) $cpu_rate ); ?>"
			data-ram-rate="<?php echo esc_attr( (string) $ram_rate ); ?>"
			data-disk-rate="<?php echo esc_attr( (string) $disk_rate ); ?>"
			data-cpu-opt-id="<?php echo esc_attr( (string) $cpu_opt_id ); ?>"
			data-ram-opt-id="<?php echo esc_attr( (string) $ram_opt_id ); ?>"
			data-disk-opt-id="<?php echo esc_attr( (string) $disk_opt_id ); ?>">

			<div class="whmcs-vps-card">
				<?php if ( ! empty( $heading ) ) : ?>
					<h2 class="whmcs-vps-heading"><?php echo esc_html( $heading ); ?></h2>
				<?php endif; ?>
				<?php if ( ! empty( $subheading ) ) : ?>
					<p class="whmcs-vps-subheading"><?php echo esc_html( $subheading ); ?></p>
				<?php endif; ?>

				<div class="whmcs-vps-layout">
					<div class="whmcs-vps-sliders-col">
						<div class="whmcs-vps-slider-box">
							<div class="whmcs-vps-slider-header">
								<span class="whmcs-vps-slider-label"><?php esc_html_e( 'Compute (vCPU)', 'whmcs-connector' ); ?></span>
								<span class="whmcs-vps-pill whmcs-vps-cpu-val">4 Cores</span>
							</div>
							<input type="range" min="1" max="32" step="1" value="4" data-resource="cpu" class="whmcs-range-input"
								role="slider" aria-label="<?php esc_attr_e( 'Compute (vCPU)', 'whmcs-connector' ); ?>"
								aria-valuemin="1" aria-valuemax="32" aria-valuenow="4" aria-valuetext="4 Cores" />
						</div>

						<div class="whmcs-vps-slider-box">
							<div class="whmcs-vps-slider-header">
								<span class="whmcs-vps-slider-label"><?php esc_html_e( 'Memory (RAM)', 'whmcs-connector' ); ?></span>
								<span class="whmcs-vps-pill whmcs-vps-ram-val">8 GB RAM</span>
							</div>
							<input type="range" min="1" max="128" step="1" value="8" data-resource="ram" class="whmcs-range-input"
								role="slider" aria-label="<?php esc_attr_e( 'Memory (RAM)', 'whmcs-connector' ); ?>"
								aria-valuemin="1" aria-valuemax="128" aria-valuenow="8" aria-valuetext="8 GB RAM" />
						</div>

						<div class="whmcs-vps-slider-box">
							<div class="whmcs-vps-slider-header">
								<span class="whmcs-vps-slider-label"><?php esc_html_e( 'Enterprise NVMe Storage', 'whmcs-connector' ); ?></span>
								<span class="whmcs-vps-pill whmcs-vps-disk-val">120 GB NVMe</span>
							</div>
							<input type="range" min="20" max="1000" step="10" value="120" data-resource="disk" class="whmcs-range-input"
								role="slider" aria-label="<?php esc_attr_e( 'Enterprise NVMe Storage', 'whmcs-connector' ); ?>"
								aria-valuemin="20" aria-valuemax="1000" aria-valuenow="120" aria-valuetext="120 GB NVMe" />
						</div>
					</div>

					<div class="whmcs-vps-summary-sidebar">
						<div class="whmcs-vps-summary-badge"><?php esc_html_e( 'CONFIGURATION SUMMARY', 'whmcs-connector' ); ?></div>
						<div class="whmcs-vps-price-display">
							<span class="whmcs-vps-currency"><?php echo esc_html( $currency ); ?></span>
							<span class="whmcs-vps-amount"><?php echo esc_html( number_format( $initial_total, 2 ) ); ?></span>
							<span class="whmcs-vps-cycle">/month</span>
						</div>
						<div class="whmcs-vps-specs">
							<div class="whmcs-vps-spec-item whmcs-vps-spec-cpu">⚡ 4 Dedicated vCPU Cores</div>
							<div class="whmcs-vps-spec-item whmcs-vps-spec-ram">🧠 8 GB ECC Memory</div>
							<div class="whmcs-vps-spec-item whmcs-vps-spec-disk">💾 120 GB Enterprise NVMe SSD</div>
							<div class="whmcs-vps-spec-item">🌐 <?php esc_html_e( 'Unlimited 1Gbps Bandwidth', 'whmcs-connector' ); ?></div>
							<div class="whmcs-vps-spec-item">🛡️ <?php esc_html_e( 'Anti-DDoS Protection Included', 'whmcs-connector' ); ?></div>
						</div>
						<a href="<?php echo esc_url( $order_url ); ?>" class="whmcs-connector-btn whmcs-vps-deploy-btn wp-element-button is-style-fill" target="_blank" rel="noopener">
							<?php echo esc_html( $btn_text ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
