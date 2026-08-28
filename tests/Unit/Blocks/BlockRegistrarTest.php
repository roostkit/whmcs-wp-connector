<?php
/**
 * Tests for BlockRegistrar and Patterns.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Blocks
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Blocks\BlockRegistrar;
use RoostKit\WhmcsConnector\Blocks\Patterns;
use RoostKit\WhmcsConnector\License\LicenseClient;
use RoostKit\WhmcsConnector\ProductRepository;
use RoostKit\WhmcsConnector\Shortcodes\LoginShortcode;

class BlockRegistrarTest extends TestCase {

	private array $registered_patterns = [];
	private array $registered_categories = [];
	private array $registered_blocks = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registered_patterns   = [];
		$this->registered_categories = [];
		$this->registered_blocks     = [];

		Functions\when( 'register_block_pattern_category' )->alias( function ( $category, $props ) {
			$this->registered_categories[ $category ] = $props;
			return true;
		} );

		Functions\when( 'register_block_pattern' )->alias( function ( $pattern, $props ) {
			$this->registered_patterns[ $pattern ] = $props;
			return true;
		} );

		Functions\when( 'register_block_type' )->alias( function ( $file, $args = [] ) {
			$this->registered_blocks[ $file ] = $args;
			return true;
		} );

		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_style_is' )->justReturn( true );
		Functions\when( 'wp_add_inline_script' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( function ( $data ) {
			return json_encode( $data );
		} );
		Functions\when( 'admin_url' )->alias( function ( $path = '' ) {
			return 'https://example.com/wp-admin/' . $path;
		} );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr_e' )->alias( function ( $text ) {
			echo $text;
		} );
		Functions\when( 'esc_html_e' )->alias( function ( $text ) {
			echo $text;
		} );
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'wp_nonce_field' )->justReturn( '<input type="hidden" name="nonce" value="123" />' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'rest_url' )->alias( function ( $path = '' ) {
			return 'https://example.com/wp-json/' . $path;
		} );
		Functions\when( 'add_query_arg' )->justReturn( 'https://example.com' );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\when( 'is_ssl' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value = null ) {
			if ( 'whmcs_connector_is_pro' === $tag || 'whmcs_connector_is_pro_active' === $tag ) {
				return true;
			}
			return $value;
		} );
		Functions\when( 'shortcode_atts' )->alias( function ( $pairs, $atts, $shortcode = '' ) {
			$out = [];
			foreach ( $pairs as $name => $default ) {
				$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
			}
			return $out;
		} );
		Functions\when( 'get_option' )->alias( function ( $key, $default = [] ) {
			if ( 'whmcs_connector_settings' === $key ) {
				return [ 'whmcs_url' => 'https://whmcs.example.com' ];
			}
			return $default;
		} );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( 'sanitize_html_class' )->alias( function ( $class ) {
			return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
		} );
		Functions\when( 'sanitize_hex_color' )->alias( function ( $color ) {
			return preg_match( '/^#([a-f0-9]{3}){1,2}$/i', (string) $color ) ? $color : '';
		} );
		Functions\when( 'sanitize_key' )->alias( function ( $key ) {
			return strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $key ) );
		} );
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'untrailingslashit' )->alias( function ( $string ) {
			return rtrim( (string) $string, '/\\' );
		} );
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce_abc123' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_patterns_registration(): void {
		$patterns = new Patterns();
		$patterns->register();

		$this->assertArrayHasKey( 'whmcs-connector', $this->registered_categories );
		$this->assertArrayHasKey( 'whmcs-connector/pricing-3-col', $this->registered_patterns );
		$this->assertArrayHasKey( 'whmcs-connector/pricing-horizontal-card', $this->registered_patterns );
		$this->assertArrayHasKey( 'whmcs-connector/client-portal', $this->registered_patterns );
	}

	public function test_block_registrar_registers_free_and_pro_blocks(): void {
		if ( ! defined( 'WHMCS_CONNECTOR_DIR' ) ) {
			define( 'WHMCS_CONNECTOR_DIR', dirname( __DIR__, 3 ) . '/' );
		}
		if ( ! defined( 'WHMCS_CONNECTOR_URL' ) ) {
			define( 'WHMCS_CONNECTOR_URL', 'https://example.com/wp-content/plugins/whmcs-connector/' );
		}
		if ( ! defined( 'WHMCS_CONNECTOR_VERSION' ) ) {
			define( 'WHMCS_CONNECTOR_VERSION', '1.0.0' );
		}

		$registrar = new BlockRegistrar();
		$registrar->register();

		// Free blocks
		$this->assertArrayHasKey( WHMCS_CONNECTOR_DIR . 'includes/Blocks/Login', $this->registered_blocks );
		$this->assertArrayHasKey( WHMCS_CONNECTOR_DIR . 'includes/Blocks/ClientArea', $this->registered_blocks );
		$this->assertArrayHasKey( WHMCS_CONNECTOR_DIR . 'includes/Blocks/Pricing', $this->registered_blocks );

		// Pro blocks (if present in build)
		if ( is_dir( WHMCS_CONNECTOR_DIR . 'includes/Blocks/SaasPricing' ) ) {
			$this->assertArrayHasKey( WHMCS_CONNECTOR_DIR . 'includes/Blocks/SaasPricing', $this->registered_blocks );
			$this->assertArrayHasKey( WHMCS_CONNECTOR_DIR . 'includes/Blocks/FeaturedHosting', $this->registered_blocks );
			$this->assertArrayHasKey( WHMCS_CONNECTOR_DIR . 'includes/Blocks/VpsSlider', $this->registered_blocks );
			$this->assertArrayHasKey( WHMCS_CONNECTOR_DIR . 'includes/Blocks/DomainSearch', $this->registered_blocks );
		}

		// ClientArea block should NOT register a render_callback (static InnerBlocks block).
		$this->assertEmpty( $this->registered_blocks[ WHMCS_CONNECTOR_DIR . 'includes/Blocks/ClientArea' ] );
	}

	public function test_login_shortcode_renders_custom_labels_and_title(): void {
		Functions\when( 'get_option' )->justReturn( [ 'whmcs_url' => 'https://whmcs.example.com' ] );

		$login_shortcode = new LoginShortcode( null );
		$html = $login_shortcode->render(
			[
				'title'          => 'Customer Portal Sign In',
				'email_label'    => 'Account Email',
				'password_label' => 'Secret Key',
				'submit_label'   => 'Authenticate Now',
			]
		);

		$this->assertStringContainsString( 'Customer Portal Sign In', $html );
		$this->assertStringContainsString( 'Account Email', $html );
		$this->assertStringContainsString( 'Secret Key', $html );
		$this->assertStringContainsString( 'Authenticate Now', $html );
		$this->assertStringContainsString( 'wp-element-button', $html );
	}

	public function test_free_pricing_render_callback(): void {
		$repo_mock = $this->createMock( ProductRepository::class );
		$repo_mock->method( 'get_products' )->willReturn( [
			[
				'pid'         => 1,
				'name'        => 'Standard Web Hosting',
				'description' => 'Reliable cPanel hosting for personal sites.',
				'pricing'     => [
					'USD' => [
						'prefix'   => '$',
						'suffix'   => '',
						'monthly'  => '9.99',
						'annually' => '99.00',
					],
				],
			],
		] );
		$repo_mock->method( 'get_default_currency_pricing' )->willReturn( [
			'monthly'  => '9.99',
			'annually' => '99.00',
		] );
		$repo_mock->method( 'format_price' )->willReturnCallback( function ( $amt ) {
			return '$' . number_format( (float) $amt, 2 );
		} );

		$registrar = new BlockRegistrar( $repo_mock, null, 'https://whmcs.example.com' );
		$html      = $registrar->render_pricing( [ 'columns' => '3' ] );

		$this->assertStringContainsString( 'whmcs-connector-pricing-grid', $html );
		$this->assertStringContainsString( 'Standard Web Hosting', $html );
		$this->assertStringContainsString( '$9.99', $html );
		$this->assertStringContainsString( 'https://whmcs.example.com/cart.php?a=add&pid=1', $html );
	}

	public function test_pro_blocks_render_empty_when_pro_license_inactive(): void {
		// Mock is_pro_active to false
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value = null ) {
			if ( 'whmcs_connector_is_pro_active' === $tag ) {
				return false;
			}
			return $value;
		} );

		$registrar = new BlockRegistrar( null, null, 'https://whmcs.example.com' );

		$saas_html    = $registrar->render_saas_pricing( [] );
		$hosting_html = $registrar->render_featured_hosting( [] );
		$vps_html     = $registrar->render_vps_slider( [] );
		$domain_html  = $registrar->render_domain_search( [] );

		// Frontend MUST render empty string without active license
		$this->assertSame( '', $saas_html );
		$this->assertSame( '', $hosting_html );
		$this->assertSame( '', $vps_html );
		$this->assertSame( '', $domain_html );
	}

	public function test_pro_blocks_render_with_live_data_and_accent_color_when_pro_active(): void {
		if ( ! is_dir( WHMCS_CONNECTOR_DIR . 'includes/Blocks/SaasPricing' ) ) {
			$this->markTestSkipped( 'Pro blocks are not present in Free build.' );
		}
		// Mock is_pro_active to true
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value = null ) {
			if ( 'whmcs_connector_is_pro_active' === $tag ) {
				return true;
			}
			return $value;
		} );

		$repo_mock = $this->createMock( ProductRepository::class );
		$repo_mock->method( 'get_product' )->willReturnCallback( function ( $pid ) {
			return [
				'pid'         => $pid,
				'name'        => 'Live Plan #' . $pid,
				'description' => "Fast cloud server\n- High speed NVMe\n- 10Gbps Network",
				'pricing'     => [
					'USD' => [
						'prefix'   => '$',
						'monthly'  => '10.00',
						'annually' => '100.00',
					],
				],
			];
		} );
		$repo_mock->method( 'get_default_currency_pricing' )->willReturn( [
			'monthly'  => '10.00',
			'annually' => '100.00',
		] );
		$repo_mock->method( 'get_available_cycles' )->willReturn( [ 'monthly', 'annually' ] );
		$repo_mock->method( 'format_price' )->willReturnCallback( function ( $amt ) {
			return '$' . number_format( (float) $amt, 2 );
		} );
		$repo_mock->method( 'compute_savings' )->willReturnCallback( function ( $pricing, $cycle ) {
			return 'annually' === $cycle ? 17 : 0;
		} );
		$repo_mock->method( 'extract_features_from_description' )->willReturn( [ 'High speed NVMe', '10Gbps Network' ] );
		$repo_mock->method( 'extract_tagline_from_description' )->willReturn( 'Fast cloud server' );

		$repo_mock->method( 'get_vps_config_rates' )->willReturn( [
			'base_price'  => 15.00,
			'cpu_rate'    => 5.00,
			'ram_rate'    => 3.00,
			'disk_rate'   => 1.00,
			'cpu_opt_id'  => 5,
			'ram_opt_id'  => 6,
			'disk_opt_id' => 7,
			'currency'    => '$',
		] );

		Functions\when( 'sanitize_text_field' )->alias( function ( $str ) {
			return trim( (string) $str );
		} );

		$registrar = new BlockRegistrar( $repo_mock, null, 'https://whmcs.example.com' );

		// 1. SaaS Pricing
		$saas_html = $registrar->render_saas_pricing( [
			'accentColor' => '#ff5500',
			'plans'       => [
				[ 'pid' => '10', 'tagline' => 'Starter Tier', 'is_popular' => false, 'features' => [ 'Feature A' ] ],
			],
		] );
		$this->assertStringContainsString( 'whmcs-saas-pricing-wrapper', $saas_html );
		$this->assertStringContainsString( '--whmcs-connector-accent: #ff5500', $saas_html );
		$this->assertStringContainsString( 'Starter Tier', $saas_html );
		$this->assertStringContainsString( 'Live Plan #10', $saas_html );
		$this->assertStringContainsString( 'Save 17%', $saas_html );
		$this->assertStringContainsString( 'https://whmcs.example.com/cart.php?a=add&pid=10&billingcycle=annually', $saas_html );

		// 2. Featured Hosting Grid
		$hosting_html = $registrar->render_featured_hosting( [
			'accentColor' => '#10b981',
			'cycle'       => 'annually',
			'plans'       => [
				[ 'pid' => '20', 'tagline' => 'Pro Reseller', 'ribbon' => 'Special Offer' ],
			],
		] );
		$this->assertStringContainsString( 'whmcs-ultahost-wrapper', $hosting_html );
		$this->assertStringContainsString( '--whmcs-connector-accent: #10b981', $hosting_html );
		$this->assertStringContainsString( 'Pro Reseller', $hosting_html );
		$this->assertStringContainsString( 'Live Plan #20', $hosting_html );
		$this->assertStringContainsString( 'Special Offer', $hosting_html );
		$this->assertStringContainsString( 'Save 17%', $hosting_html );

		// 3. VPS Resource Slider
		$vps_html = $registrar->render_vps_slider( [
			'accentColor' => '#6366f1',
			'base_pid'    => '10',
		] );
		$this->assertStringContainsString( 'whmcs-vps-slider-block', $vps_html );
		$this->assertStringContainsString( '--whmcs-connector-accent: #6366f1', $vps_html );
		$this->assertStringContainsString( 'data-cpu-opt-id="5"', $vps_html );
		$this->assertStringContainsString( 'data-ram-opt-id="6"', $vps_html );
		$this->assertStringContainsString( 'data-disk-opt-id="7"', $vps_html );
		$this->assertStringContainsString( 'billingcycle=monthly&configoption[5]=4&configoption[6]=8&configoption[7]=120', $vps_html );
		$this->assertStringContainsString( 'role="slider"', $vps_html );
		$this->assertStringContainsString( 'aria-label="Compute (vCPU)"', $vps_html );

		// 4. Domain Search — default attributes
		$domain_html = $registrar->render_domain_search( [
			'accentColor' => '#0ea5e9',
		] );
		$this->assertStringContainsString( 'whmcs-domain-search-block', $domain_html );
		$this->assertStringContainsString( '--whmcs-connector-accent: #0ea5e9', $domain_html );
		$this->assertStringContainsString( 'data-register-btn-text="Register Now', $domain_html );
		$this->assertStringContainsString( 'data-transfer-btn-text="Transfer Domain"', $domain_html );
		$this->assertStringContainsString( 'data-max-suggestions="4"', $domain_html );
		$this->assertStringContainsString( 'data-default-tld=".com"', $domain_html );
		$this->assertStringContainsString( 'data-show-suggestions="true"', $domain_html );
		$this->assertStringContainsString( 'data-open-in-new-tab="true"', $domain_html );
		// TLD badges visible by default.
		$this->assertStringContainsString( 'whmcs-tld-badge', $domain_html );

		// 4b. Domain Search — custom labels & behavior
		$domain_custom_html = $registrar->render_domain_search( [
			'register_btn_text' => 'Buy It Now',
			'max_suggestions'   => 2,
			'default_tld'       => '.io',
			'show_suggestions'  => false,
			'show_tld_badges'   => false,
			'open_in_new_tab'   => false,
		] );
		$this->assertStringContainsString( 'data-register-btn-text="Buy It Now"', $domain_custom_html );
		$this->assertStringContainsString( 'data-max-suggestions="2"', $domain_custom_html );
		$this->assertStringContainsString( 'data-default-tld=".io"', $domain_custom_html );
		$this->assertStringContainsString( 'data-show-suggestions="false"', $domain_custom_html );
		$this->assertStringContainsString( 'data-open-in-new-tab="false"', $domain_custom_html );
		// TLD badges suppressed when show_tld_badges is false.
		$this->assertStringNotContainsString( 'whmcs-tld-badge', $domain_custom_html );

		// 4c. Domain Search — style CSS custom properties
		$domain_styled_html = $registrar->render_domain_search( [
			'cardBg'            => '#f0f4ff',
			'cardBorderRadius'  => '24px',
			'cardPadding'       => '48px 40px',
			'cardBorderColor'   => '#c7d7fe',
			'cardShadow'        => 'none',
			'inputBg'           => '#ffffff',
			'inputBorderRadius' => '8px',
			'badgeBg'           => '#e0e7ff',
			'badgeColor'        => '#3730a3',
			'badgeBorderRadius' => '6px',
			'buttonBorderRadius'=> '8px',
			'buttonPadding'     => '12px 24px',
		] );
		$this->assertStringContainsString( '--whmcs-ds-card-bg: #f0f4ff', $domain_styled_html );
		$this->assertStringContainsString( '--whmcs-ds-card-radius: 24px', $domain_styled_html );
		$this->assertStringContainsString( '--whmcs-ds-card-padding: 48px 40px', $domain_styled_html );
		$this->assertStringContainsString( '--whmcs-ds-badge-bg: #e0e7ff', $domain_styled_html );
		$this->assertStringContainsString( '--whmcs-ds-badge-color: #3730a3', $domain_styled_html );
		$this->assertStringContainsString( '--whmcs-ds-btn-radius: 8px', $domain_styled_html );
		// Empty style attrs must NOT emit CSS vars (no-op).
		$domain_no_style_html = $registrar->render_domain_search( [] );
		$this->assertStringNotContainsString( '--whmcs-ds-', $domain_no_style_html );
	}

	public function test_saas_pricing_skips_missing_products_and_computes_cycle_intersection(): void {
		if ( ! is_dir( WHMCS_CONNECTOR_DIR . 'includes/Blocks/SaasPricing' ) ) {
			$this->markTestSkipped( 'Pro blocks are not present in Free build.' );
		}
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value = null ) {
			if ( 'whmcs_connector_is_pro_active' === $tag ) {
				return true;
			}
			return $value;
		} );

		$repo_mock = $this->createMock( ProductRepository::class );
		$repo_mock->method( 'get_product' )->willReturnCallback( function ( $pid ) {
			if ( 99 === $pid ) {
				return null; // Missing/deleted product
			}
			if ( 1 === $pid ) {
				return [
					'pid'         => 1,
					'name'        => 'Plan 1',
					'description' => 'Desc 1',
					'pricing'     => [
						'USD' => [ 'monthly' => '10.00', 'quarterly' => '28.00', 'annually' => '100.00' ],
					],
				];
			}
			if ( 2 === $pid ) {
				return [
					'pid'         => 2,
					'name'        => 'Plan 2',
					'description' => 'Desc 2',
					'pricing'     => [
						'USD' => [ 'monthly' => '20.00', 'annually' => '200.00' ],
					],
				];
			}
			return null;
		} );

		$repo_mock->method( 'get_default_currency_pricing' )->willReturnCallback( function ( $pricing ) {
			$first = reset( $pricing );
			return is_array( $first ) ? $first : [];
		} );

		$repo_mock->method( 'get_available_cycles' )->willReturnCallback( function ( $pricing ) {
			$first = reset( $pricing );
			return is_array( $first ) ? array_keys( $first ) : [];
		} );

		$repo_mock->method( 'format_price' )->willReturnCallback( function ( $amt ) {
			return '$' . number_format( (float) $amt, 2 );
		} );

		$repo_mock->method( 'compute_savings' )->willReturnCallback( function ( $pricing, $cycle ) {
			return 'annually' === $cycle ? 17 : 0;
		} );

		$repo_mock->method( 'extract_features_from_description' )->willReturn( [ 'Feature 1' ] );
		$repo_mock->method( 'extract_tagline_from_description' )->willReturn( 'Tagline 1' );

		$logged_entries = [];
		Functions\when( 'update_option' )->alias( function ( $name, $val ) use ( &$logged_entries ) {
			if ( 'whmcs_connector_api_log' === $name ) {
				$logged_entries = $val;
			}
			return true;
		} );
		Functions\when( 'sanitize_text_field' )->alias( function ( $str ) {
			return trim( (string) $str );
		} );

		$api_log   = new \RoostKit\WhmcsConnector\Api\ApiLog();
		$registrar = new BlockRegistrar( $repo_mock, null, 'https://whmcs.example.com', $api_log );

		$html = $registrar->render_saas_pricing( [
			'plans' => [
				[ 'pid' => '1' ],
				[ 'pid' => '99' ], // deleted
				[ 'pid' => '2' ],
			],
		] );

		// Product 99 must be skipped silently on frontend
		$this->assertStringNotContainsString( 'Product ID 99', $html );
		$this->assertStringContainsString( 'Plan 1', $html );
		$this->assertStringContainsString( 'Plan 2', $html );

		// Cycle intersection of Plan 1 [monthly, quarterly, annually] and Plan 2 [monthly, annually] is [monthly, annually]
		$this->assertStringContainsString( 'MONTHLY', $html );
		$this->assertStringContainsString( 'ANNUALLY', $html );
		$this->assertStringNotContainsString( 'QUARTERLY', $html );

		// Confirm ApiLog recorded the missing product notice
		$this->assertNotEmpty( $logged_entries );
		$this->assertStringContainsString( 'Product ID 99', $logged_entries[0]['message'] );
	}
}

