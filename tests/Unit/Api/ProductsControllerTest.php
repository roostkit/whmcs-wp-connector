<?php
/**
 * Tests for ProductsController REST API.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Api
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Api;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Api\ProductsController;
use RoostKit\WhmcsConnector\ProductRepository;
use WP_REST_Request;

class ProductsControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'current_user_can' )->alias( function ( $cap ) {
			return 'edit_posts' === $cap;
		} );
		Functions\when( 'absint' )->alias( function ( $val ) {
			return abs( (int) $val );
		} );
		Functions\when( 'sanitize_text_field' )->alias( function ( $val ) {
			return trim( (string) $val );
		} );
		Functions\when( '__' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_check_permission_returns_true_for_authorized_users(): void {
		$controller = new ProductsController( null );
		$this->assertTrue( $controller->check_permission() );
	}

	public function test_handle_get_products_returns_formatted_list_with_cycles_and_savings(): void {
		$repo_mock = $this->createMock( ProductRepository::class );
		$repo_mock->method( 'get_products' )->willReturn( [
			[
				'pid'         => 101,
				'gid'         => 1,
				'name'        => 'Pro Cloud VPS',
				'description' => "High performance cloud server\n- 4 Dedicated vCPUs\n- 8GB RAM\n- 120GB NVMe SSD",
				'pricing'     => [
					'USD' => [
						'prefix'   => '$',
						'monthly'  => '20.00',
						'annually' => '200.00', // 20*12 = 240, 200/240 = Save 17%
					],
				],
			],
			[
				'pid'         => 102,
				'gid'         => 1,
				'name'        => 'Annual Only Plan',
				'description' => "Annual package only\n- 1 Site",
				'pricing'     => [
					'USD' => [
						'prefix'   => '$',
						'annually' => '100.00',
					],
				],
			],
		] );

		$repo_mock->method( 'get_default_currency_pricing' )->willReturnCallback( function ( $pricing ) {
			$first = reset( $pricing );
			return is_array( $first ) ? array_intersect_key( $first, array_flip( [ 'monthly', 'annually' ] ) ) : [];
		} );

		$repo_mock->method( 'get_available_cycles' )->willReturnCallback( function ( $pricing ) {
			$first = reset( $pricing );
			$cycles = [];
			if ( isset( $first['monthly'] ) ) {
				$cycles[] = 'monthly';
			}
			if ( isset( $first['annually'] ) ) {
				$cycles[] = 'annually';
			}
			return $cycles;
		} );

		$repo_mock->method( 'get_currency_symbol' )->willReturn( '$' );

		$repo_mock->method( 'format_price' )->willReturnCallback( function ( $amt ) {
			return '$' . number_format( (float) $amt, 2 );
		} );

		$repo_mock->method( 'compute_savings' )->willReturnCallback( function ( $pricing, $cycle ) {
			$first = reset( $pricing );
			if ( 'annually' === $cycle && isset( $first['monthly'] ) && (float) $first['monthly'] > 0 ) {
				$monthly = (float) $first['monthly'];
				$annual  = (float) $first['annually'];
				if ( $annual < $monthly * 12 ) {
					return (int) round( ( ( $monthly * 12 - $annual ) / ( $monthly * 12 ) ) * 100 );
				}
			}
			return 0;
		} );

		$repo_mock->method( 'extract_features_from_description' )->willReturn( [ '4 Dedicated vCPUs', '8GB RAM', '120GB NVMe SSD' ] );
		$repo_mock->method( 'extract_tagline_from_description' )->willReturn( 'High performance cloud server' );

		$controller = new ProductsController( $repo_mock );
		$request    = new WP_REST_Request( 'GET', '/products' );

		$response = $controller->handle_get_products( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertCount( 2, $data['products'] );

		// Product 101 checks
		$p1 = $data['products'][0];
		$this->assertSame( 101, $p1['pid'] );
		$this->assertSame( 'Pro Cloud VPS', $p1['name'] );
		$this->assertSame( 'High performance cloud server', $p1['tagline'] );
		$this->assertSame( [ 'monthly', 'annually' ], $p1['available_cycles'] );
		$this->assertSame( '$20.00', $p1['pricing']['monthly'] );
		$this->assertSame( '$200.00', $p1['pricing']['annually'] );
		$this->assertSame( 17, $p1['savings']['annually'] );

		// Product 102 checks (annually only, no savings)
		$p2 = $data['products'][1];
		$this->assertSame( 102, $p2['pid'] );
		$this->assertSame( [ 'annually' ], $p2['available_cycles'] );
		$this->assertArrayNotHasKey( 'annually', $p2['savings'] );
	}

	public function test_handle_get_product_groups(): void {
		$repo_mock = $this->createMock( ProductRepository::class );
		$repo_mock->method( 'get_product_groups' )->willReturn( [
			[ 'id' => 1, 'name' => 'Web Hosting' ],
			[ 'id' => 2, 'name' => 'Cloud VPS' ],
		] );

		$controller = new ProductsController( $repo_mock );
		$response   = $controller->handle_get_groups();

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertCount( 2, $data['groups'] );
		$this->assertSame( 'Web Hosting', $data['groups'][0]['name'] );
	}
}
