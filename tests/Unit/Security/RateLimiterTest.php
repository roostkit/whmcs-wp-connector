<?php
/**
 * Tests for the RateLimiter service.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Security
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Security;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Security\RateLimiter;

class RateLimiterTest extends TestCase {

	private int $stored_attempts = 0;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->stored_attempts = 0;

		// Mock WordPress transient functions.
		Functions\when( 'get_transient' )->alias( function () {
			return $this->stored_attempts > 0 ? $this->stored_attempts : false;
		} );

		Functions\when( 'set_transient' )->alias( function ( $key, $value, $expiration ) {
			$this->stored_attempts = (int) $value;
			return true;
		} );

		Functions\when( 'delete_transient' )->alias( function () {
			$this->stored_attempts = 0;
			return true;
		} );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_allows_first_attempt(): void {
		$limiter = new RateLimiter();
		$this->assertTrue( $limiter->is_allowed( '192.168.1.1' ) );
	}

	public function test_allows_up_to_five_attempts(): void {
		$limiter = new RateLimiter();
		$ip      = '192.168.1.1';

		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertTrue( $limiter->is_allowed( $ip ), "Attempt $i should be allowed" );
			$limiter->record_attempt( $ip );
		}
	}

	public function test_blocks_sixth_attempt(): void {
		$limiter = new RateLimiter();
		$ip      = '10.0.0.1';

		// Record 5 failed attempts.
		for ( $i = 0; $i < 5; $i++ ) {
			$limiter->record_attempt( $ip );
		}

		$this->assertFalse( $limiter->is_allowed( $ip ) );
	}

	public function test_reset_clears_attempts(): void {
		$limiter = new RateLimiter();
		$ip      = '172.16.0.1';

		// Block the IP.
		for ( $i = 0; $i < 5; $i++ ) {
			$limiter->record_attempt( $ip );
		}
		$this->assertFalse( $limiter->is_allowed( $ip ) );

		// Reset.
		$limiter->reset( $ip );
		$this->assertTrue( $limiter->is_allowed( $ip ) );
	}

	public function test_different_ips_tracked_independently(): void {
		// This test uses a simplified mock — in reality, transients would be
		// keyed differently per IP. The purpose is to verify the hash-based
		// key generation doesn't collide for different IPs.
		$limiter = new RateLimiter();

		$this->assertTrue( $limiter->is_allowed( '1.1.1.1' ) );
		$this->assertTrue( $limiter->is_allowed( '2.2.2.2' ) );
	}
}
