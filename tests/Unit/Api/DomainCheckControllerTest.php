<?php
/**
 * Tests for DomainCheckController.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Api
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Api;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Api\ApiClientInterface;
use RoostKit\WhmcsConnector\Api\DomainCheckController;
use RoostKit\WhmcsConnector\Cache\CacheManager;

class DomainCheckControllerTest extends TestCase {

	private array $registered_routes = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registered_routes = [];

		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( 'untrailingslashit' )->alias( function ( $string ) {
			return rtrim( (string) $string, '/\\' );
		} );

		Functions\when( 'register_rest_route' )->alias( function ( $namespace, $route, $args ) {
			$this->registered_routes[ $namespace . $route ] = $args;
			return true;
		} );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_routes(): void {
		$controller = new DomainCheckController( null, null, 'https://whmcs.example.com' );
		$controller->register_routes();

		$this->assertArrayHasKey( 'whmcs-connector/v1/domain-check', $this->registered_routes );
	}

	public function test_handle_domain_check_returns_available_status_and_suggestions(): void {
		$mock_client = $this->createMock( ApiClientInterface::class );
		$mock_client->expects( $this->any() )
			->method( 'call' )
			->willReturnCallback( function ( $action, $params ) {
				$this->assertSame( 'DomainWhois', $action );
				$domain = $params['domain'] ?? '';
				if ( 'example.com' === $domain ) {
					return [
						'result' => 'success',
						'status' => 'available',
					];
				}
				if ( 'example.net' === $domain ) {
					return [
						'result' => 'success',
						'status' => 'available',
					];
				}
				return [
					'result' => 'success',
					'status' => 'unavailable',
				];
			} );

		$cache_manager = new CacheManager();
		$controller    = new DomainCheckController( $mock_client, $cache_manager, 'https://whmcs.example.com' );

		$request = new \WP_REST_Request( 'POST', '/whmcs-connector/v1/domain-check' );
		$request->set_param( 'domain', 'example.com' );
		$request->set_param( 'suggest_tlds', [ '.net', '.org' ] );

		$response = $controller->handle_domain_check( $request );

		$this->assertSame( 200, $response->status );
		$this->assertTrue( $response->data['success'] );
		$this->assertSame( 'example.com', $response->data['searched']['domain'] );
		$this->assertTrue( $response->data['searched']['available'] );
		$this->assertStringContainsString( 'cart.php?a=add&domain=register&query=example.com', $response->data['searched']['order_url'] );

		// Suggestions check
		$this->assertCount( 2, $response->data['suggestions'] );
		$this->assertSame( 'example.net', $response->data['suggestions'][0]['domain'] );
		$this->assertTrue( $response->data['suggestions'][0]['available'] );
		$this->assertSame( 'example.org', $response->data['suggestions'][1]['domain'] );
		$this->assertFalse( $response->data['suggestions'][1]['available'] );
		$this->assertStringContainsString( 'cart.php?a=add&domain=transfer&query=example.org', $response->data['suggestions'][1]['order_url'] );
	}

	public function test_handle_domain_check_rejects_empty_domain(): void {
		$controller = new DomainCheckController( null, null, 'https://whmcs.example.com' );

		$request = new \WP_REST_Request( 'POST', '/whmcs-connector/v1/domain-check' );
		$request->set_param( 'domain', '' );

		$response = $controller->handle_domain_check( $request );

		$this->assertSame( 400, $response->status );
		$this->assertFalse( $response->data['success'] );
	}
}
