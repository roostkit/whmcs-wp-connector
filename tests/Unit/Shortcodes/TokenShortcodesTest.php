<?php
/**
 * Tests for Atomic Shortcodes ([whmcs_price], [whmcs_name], [whmcs_desc], and [whmcs_order_url]).
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Shortcodes
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Shortcodes;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Api\ApiClientInterface;
use RoostKit\WhmcsConnector\Api\ApiLog;
use RoostKit\WhmcsConnector\Cache\CacheManager;
use RoostKit\WhmcsConnector\ProductRepository;
use RoostKit\WhmcsConnector\Shortcodes\DescShortcode;
use RoostKit\WhmcsConnector\Shortcodes\NameShortcode;
use RoostKit\WhmcsConnector\Shortcodes\OrderUrlShortcode;
use RoostKit\WhmcsConnector\Shortcodes\PriceShortcode;

class TokenShortcodesTest extends TestCase {

	private array $options = [];
	private array $transients = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->options    = [];
		$this->transients = [];

		ProductRepository::clear_runtime_cache();

		// Mock WordPress functions.
		Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
			return $this->options[ $key ] ?? $default;
		} );

		Functions\when( 'update_option' )->alias( function ( $key, $value ) {
			$this->options[ $key ] = $value;
			return true;
		} );

		Functions\when( 'get_transient' )->alias( function ( $key ) {
			return $this->transients[ $key ] ?? false;
		} );

		Functions\when( 'set_transient' )->alias( function ( $key, $value, $expiration ) {
			$this->transients[ $key ] = $value;
			return true;
		} );

		Functions\when( 'delete_transient' )->alias( function ( $key ) {
			unset( $this->transients[ $key ] );
			return true;
		} );

		Functions\when( 'apply_filters' )->alias( function ( $tag, $value = null ) {
			if ( 'whmcs_connector_is_pro' === $tag ) {
				return true;
			}
			return $value;
		} );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_key' )->alias( function ( $key ) {
			return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
		} );
		Functions\when( 'sanitize_html_class' )->returnArg();
		Functions\when( 'absint' )->alias( function ( $val ) {
			return abs( (int) $val );
		} );
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html_e' )->alias( function ( $text ) {
			echo $text;
		} );
		Functions\when( 'wp_style_is' )->justReturn( false );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'add_shortcode' )->justReturn( true );
		Functions\when( 'untrailingslashit' )->alias( function ( $string ) {
			return rtrim( (string) $string, '/\\' );
		} );
		Functions\when( 'shortcode_atts' )->alias( function ( array $pairs, array $atts, string $shortcode = '' ) {
			$out = [];
			foreach ( $pairs as $name => $default ) {
				if ( array_key_exists( $name, $atts ) ) {
					$out[ $name ] = $atts[ $name ];
				} else {
					$out[ $name ] = $default;
				}
			}
			return $out;
		} );
	}

	protected function tearDown(): void {
		ProductRepository::clear_runtime_cache();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Sample WHMCS API product payload fixture.
	 */
	private function get_sample_products_response(): array {
		return [
			'result'   => 'success',
			'products' => [
				'product' => [
					[
						'pid'         => '1',
						'gid'         => '1',
						'name'        => 'Starter Shared Hosting',
						'description' => 'Great for starter sites',
						'pricing'     => [
							'USD' => [
								'prefix'       => '$',
								'suffix'       => ' USD',
								'monthly'      => '10.00',
								'quarterly'    => '28.00',
								'semiannually' => '54.00',
								'annually'     => '100.00',
								'biennially'   => '190.00',
								'triennially'  => '-1.00', // disabled
							],
						],
					],
					[
						'pid'         => '2',
						'gid'         => '1',
						'name'        => 'Business Hosting',
						'description' => 'For high-traffic businesses',
						'pricing'     => [
							'USD' => [
								'prefix'       => '$',
								'suffix'       => '',
								'monthly'      => '25.00',
								'annually'     => '250.00',
								'biennially'   => '-1.00',
							],
						],
					],
				],
			],
		];
	}

	public function test_price_shortcode_formats_price_for_known_product_and_cycle(): void {
		$mock_client = $this->createMock( ApiClientInterface::class );
		$mock_client->expects( $this->once() )
			->method( 'call' )
			->with( 'GetProducts', [ 'pid' => '1' ] )
			->willReturn( [
				'result'   => 'success',
				'products' => [
					'product' => [
						$this->get_sample_products_response()['products']['product'][0],
					],
				],
			] );

		$cache_manager = new CacheManager();
		$api_log       = new ApiLog();
		$repo          = new ProductRepository( $mock_client, $cache_manager, $api_log );
		$shortcode     = new PriceShortcode( $repo, $api_log, 'https://whmcs.example.com' );

		// Test annually.
		$output = $shortcode->render( [
			'pid'   => '1',
			'cycle' => 'annually',
		] );
		$this->assertSame( '$100.00 USD', $output );

		// Test monthly (default when cycle not specified).
		$output_monthly = $shortcode->render( [
			'pid' => '1',
		] );
		$this->assertSame( '$10.00 USD', $output_monthly );
	}

	public function test_price_shortcode_returns_empty_string_for_unknown_pid(): void {
		$mock_client = $this->createMock( ApiClientInterface::class );
		$mock_client->expects( $this->once() )
			->method( 'call' )
			->with( 'GetProducts', [ 'pid' => '999' ] )
			->willReturn( [
				'result'   => 'success',
				'products' => [ 'product' => [] ],
			] );

		$cache_manager = new CacheManager();
		$api_log       = new ApiLog();
		$repo          = new ProductRepository( $mock_client, $cache_manager, $api_log );
		$shortcode     = new PriceShortcode( $repo, $api_log, 'https://whmcs.example.com' );

		$output = $shortcode->render( [
			'pid'   => '999',
			'cycle' => 'annually',
		] );

		$this->assertSame( '', $output );

		// Verify miss logged to ApiLog.
		$entries = $api_log->get_entries();
		$this->assertNotEmpty( $entries );
		$this->assertSame( 'whmcs_price', $entries[0]['action'] );
		$this->assertStringContainsString( 'Product #999 not found', $entries[0]['message'] );
	}

	public function test_price_shortcode_returns_empty_string_for_disabled_cycle(): void {
		$mock_client = $this->createMock( ApiClientInterface::class );
		$mock_client->expects( $this->once() )
			->method( 'call' )
			->with( 'GetProducts', [ 'pid' => '1' ] )
			->willReturn( [
				'result'   => 'success',
				'products' => [
					'product' => [
						$this->get_sample_products_response()['products']['product'][0],
					],
				],
			] );

		$cache_manager = new CacheManager();
		$api_log       = new ApiLog();
		$repo          = new ProductRepository( $mock_client, $cache_manager, $api_log );
		$shortcode     = new PriceShortcode( $repo, $api_log, 'https://whmcs.example.com' );

		// triennially is -1.00 (disabled).
		$output = $shortcode->render( [
			'pid'   => '1',
			'cycle' => 'triennially',
		] );

		$this->assertSame( '', $output );

		$entries = $api_log->get_entries();
		$this->assertNotEmpty( $entries );
		$this->assertStringContainsString( "Billing cycle 'triennially' is disabled", $entries[0]['message'] );
	}

	public function test_name_shortcode_returns_product_name(): void {
		$mock_client = $this->createMock( ApiClientInterface::class );
		$mock_client->expects( $this->once() )
			->method( 'call' )
			->with( 'GetProducts', [ 'pid' => '1' ] )
			->willReturn( [
				'result'   => 'success',
				'products' => [
					'product' => [
						$this->get_sample_products_response()['products']['product'][0],
					],
				],
			] );

		$cache_manager  = new CacheManager();
		$api_log        = new ApiLog();
		$repo           = new ProductRepository( $mock_client, $cache_manager, $api_log );
		$name_shortcode = new NameShortcode( $repo, $api_log, 'https://whmcs.example.com' );

		$output = $name_shortcode->render( [ 'pid' => '1' ] );
		$this->assertSame( 'Starter Shared Hosting', $output );
	}

	public function test_desc_shortcode_returns_product_description(): void {
		$mock_client = $this->createMock( ApiClientInterface::class );
		$mock_client->expects( $this->once() )
			->method( 'call' )
			->with( 'GetProducts', [ 'pid' => '1' ] )
			->willReturn( [
				'result'   => 'success',
				'products' => [
					'product' => [
						$this->get_sample_products_response()['products']['product'][0],
					],
				],
			] );

		$cache_manager  = new CacheManager();
		$api_log        = new ApiLog();
		$repo           = new ProductRepository( $mock_client, $cache_manager, $api_log );
		$desc_shortcode = new DescShortcode( $repo, $api_log, 'https://whmcs.example.com' );

		$output = $desc_shortcode->render( [ 'pid' => '1' ] );
		$this->assertSame( 'Great for starter sites', $output );
	}

	public function test_order_url_shortcode_constructs_and_escapes_url(): void {
		$shortcode = new OrderUrlShortcode( 'https://whmcs.example.com' );

		$output = $shortcode->render( [ 'pid' => '42' ] );
		$this->assertSame( 'https://whmcs.example.com/cart.php?a=add&pid=42', $output );
	}

	public function test_order_url_shortcode_returns_empty_string_on_invalid_pid_or_url(): void {
		$shortcode = new OrderUrlShortcode( 'https://whmcs.example.com' );

		$this->assertSame( '', $shortcode->render( [ 'pid' => '' ] ) );
		$this->assertSame( '', $shortcode->render( [ 'pid' => '0' ] ) );
		$this->assertSame( '', $shortcode->render( [ 'pid' => '-10' ] ) );
		$this->assertSame( '', $shortcode->render( [ 'pid' => 'abc' ] ) );

		$empty_url_shortcode = new OrderUrlShortcode( '' );
		$this->assertSame( '', $empty_url_shortcode->render( [ 'pid' => '1' ] ) );
	}

	public function test_shared_product_fetching_is_deduplicated_across_atomic_shortcodes(): void {
		$mock_client = $this->createMock( ApiClientInterface::class );

		// CRITICAL: Expect GetProducts to be called EXACTLY ONCE for pid 1.
		$mock_client->expects( $this->once() )
			->method( 'call' )
			->with( 'GetProducts', [ 'pid' => '1' ] )
			->willReturn( [
				'result'   => 'success',
				'products' => [
					'product' => [
						$this->get_sample_products_response()['products']['product'][0],
					],
				],
			] );

		$cache_manager   = new CacheManager();
		$api_log         = new ApiLog();
		$repo            = new ProductRepository( $mock_client, $cache_manager, $api_log );
		$name_shortcode  = new NameShortcode( $repo, $api_log, 'https://whmcs.example.com' );
		$desc_shortcode  = new DescShortcode( $repo, $api_log, 'https://whmcs.example.com' );
		$price_shortcode = new PriceShortcode( $repo, $api_log, 'https://whmcs.example.com' );

		// 1. First shortcode queries name for product 1.
		$name = $name_shortcode->render( [ 'pid' => '1' ] );
		$this->assertSame( 'Starter Shared Hosting', $name );

		// 2. Second shortcode queries description for product 1.
		$desc = $desc_shortcode->render( [ 'pid' => '1' ] );
		$this->assertSame( 'Great for starter sites', $desc );

		// 3. Third shortcode queries price for product 1.
		$price = $price_shortcode->render( [ 'pid' => '1', 'cycle' => 'monthly' ] );
		$this->assertSame( '$10.00 USD', $price );
	}

	public function test_atomic_shortcodes_render_pro_notice_when_not_pro(): void {
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value = null ) {
			if ( 'whmcs_connector_is_pro' === $tag ) {
				return false;
			}
			return $value;
		} );
		Functions\when( 'current_user_can' )->justReturn( false );

		$cache_manager   = new CacheManager();
		$api_log         = new ApiLog();
		$repo            = new ProductRepository( null, $cache_manager, $api_log );
		$name_shortcode  = new NameShortcode( $repo, $api_log, 'https://whmcs.example.com' );
		$desc_shortcode  = new DescShortcode( $repo, $api_log, 'https://whmcs.example.com' );
		$price_shortcode = new PriceShortcode( $repo, $api_log, 'https://whmcs.example.com' );
		$order_shortcode = new OrderUrlShortcode( 'https://whmcs.example.com' );

		$name_output  = $name_shortcode->render( [ 'pid' => '1' ] );
		$desc_output  = $desc_shortcode->render( [ 'pid' => '1' ] );
		$price_output = $price_shortcode->render( [ 'pid' => '1' ] );
		$order_output = $order_shortcode->render( [ 'pid' => '1' ] );

		foreach ( [ $name_output, $desc_output, $price_output, $order_output ] as $output ) {
			$this->assertStringContainsString( 'whmcs-pro-shortcode-notice', $output );
			$this->assertStringContainsString( 'PRO', $output );
			$this->assertStringContainsString( 'Get Premium', $output );
			$this->assertStringContainsString( 'https://roostkit.site/whmcs-connector', $output );
		}

		$this->assertStringContainsString( '[whmcs_name]', $name_output );
		$this->assertStringContainsString( '[whmcs_desc]', $desc_output );
		$this->assertStringContainsString( '[whmcs_price]', $price_output );
		$this->assertStringContainsString( '[whmcs_order_url]', $order_output );
	}
}
