<?php
/**
 * Tests for ProductRepository.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Api\ApiClientInterface;
use RoostKit\WhmcsConnector\ProductRepository;

class ProductRepositoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		ProductRepository::clear_runtime_cache();

		Functions\when( 'wp_strip_all_tags' )->alias( function ( $string ) {
			return strip_tags( (string) $string );
		} );
		Functions\when( 'sanitize_text_field' )->alias( function ( $str ) {
			return trim( (string) $str );
		} );
		Functions\when( 'absint' )->alias( function ( $val ) {
			return abs( (int) $val );
		} );
	}

	protected function tearDown(): void {
		ProductRepository::clear_runtime_cache();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_available_cycles(): void {
		$repo = new ProductRepository( null );

		$pricing = [
			'USD' => [
				'monthly'      => '10.00',
				'quarterly'    => '28.00',
				'semiannually' => '-1.00',
				'annually'     => '100.00',
			],
		];

		$cycles = $repo->get_available_cycles( $pricing );
		$this->assertSame( [ 'monthly', 'quarterly', 'annually' ], $cycles );
	}

	public function test_compute_savings_calculates_correct_percentage(): void {
		$repo = new ProductRepository( null );

		// Scenario A: Monthly $10, Annual $100. Base annual = $120. Savings = 20 / 120 = 16.666% -> 17%.
		$pricing_a = [
			'USD' => [
				'monthly'  => '10.00',
				'annually' => '100.00',
			],
		];
		$savings_a = $repo->compute_savings( $pricing_a, 'annually' );
		$this->assertSame( 17, $savings_a );

		// Monthly cycle has 0% savings (it is the baseline).
		$this->assertSame( 0, $repo->compute_savings( $pricing_a, 'monthly' ) );

		// Scenario B: Annual only (no monthly). Savings = 0.
		$pricing_b = [
			'USD' => [
				'annually' => '100.00',
			],
		];
		$this->assertSame( 0, $repo->compute_savings( $pricing_b, 'annually' ) );

		// Scenario C: No discount (Monthly $10, Annual $120). Savings = 0.
		$pricing_c = [
			'USD' => [
				'monthly'  => '10.00',
				'annually' => '120.00',
			],
		];
		$this->assertSame( 0, $repo->compute_savings( $pricing_c, 'annually' ) );

		// Scenario D: Quarterly $25 vs Monthly $10. Base = $30. Savings = 5 / 30 = 16.666% -> 17%.
		$pricing_d = [
			'USD' => [
				'monthly'   => '10.00',
				'quarterly' => '25.00',
			],
		];
		$this->assertSame( 17, $repo->compute_savings( $pricing_d, 'quarterly' ) );
	}

	public function test_extract_features_and_tagline_from_description(): void {
		$repo = new ProductRepository( null );

		$desc = "<p>Ultra fast NVMe Cloud Server</p>\n<ul>\n<li>✓ 4 Cores CPU</li>\n<li>- 8 GB RAM</li>\n<li>* 120 GB SSD</li>\n</ul>";

		$tagline = $repo->extract_tagline_from_description( $desc );
		$this->assertSame( 'Ultra fast NVMe Cloud Server', $tagline );

		$features = $repo->extract_features_from_description( $desc );
		$this->assertSame( [ 'Ultra fast NVMe Cloud Server', '4 Cores CPU', '8 GB RAM', '120 GB SSD' ], $features );
	}

	public function test_get_product_groups(): void {
		$client_mock = $this->createMock( ApiClientInterface::class );
		$client_mock->method( 'call' )->willReturn( [
			'result'   => 'success',
			'products' => [
				'product' => [
					[ 'pid' => 1, 'gid' => 10, 'groupname' => 'Web Hosting' ],
					[ 'pid' => 2, 'gid' => 10, 'groupname' => 'Web Hosting' ],
					[ 'pid' => 3, 'gid' => 20, 'groupname' => 'VPS Servers' ],
				],
			],
		] );

		$repo   = new ProductRepository( $client_mock );
		$groups = $repo->get_product_groups();

		$this->assertCount( 2, $groups );
		$this->assertSame( [ 'id' => 10, 'name' => 'Web Hosting' ], $groups[0] );
		$this->assertSame( [ 'id' => 20, 'name' => 'VPS Servers' ], $groups[1] );
	}
}
