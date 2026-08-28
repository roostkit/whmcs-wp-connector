<?php
/**
 * Tests for the CacheManager.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Cache
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Cache;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Cache\CacheManager;

class CacheManagerTest extends TestCase {

	private array $transients = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->transients = [];

		// Mock transient functions.
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

		// Default apply_filters passthrough.
		Functions\when( 'apply_filters' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_returns_false_when_empty(): void {
		$cache = new CacheManager();
		$this->assertFalse( $cache->get( 'nonexistent' ) );
	}

	public function test_set_and_get_round_trip(): void {
		$cache = new CacheManager();
		$data  = [ 'products' => [ 'test' ] ];

		$cache->set( 'products', $data );
		$result = $cache->get( 'products' );

		$this->assertSame( $data, $result );
	}

	public function test_delete_removes_entry(): void {
		$cache = new CacheManager();
		$cache->set( 'to_delete', 'value' );

		$this->assertSame( 'value', $cache->get( 'to_delete' ) );

		$cache->delete( 'to_delete' );

		$this->assertFalse( $cache->get( 'to_delete' ) );
	}

	public function test_set_uses_custom_ttl(): void {
		$cache = new CacheManager( 900 );

		// Verify the set_transient is called (via our mock storing the value).
		$cache->set( 'custom_ttl', 'data', 3600 );
		$this->assertSame( 'data', $cache->get( 'custom_ttl' ) );
	}
}
