<?php
/**
 * Tests for the API Client.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Api
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Api;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Api\ApiException;
use RoostKit\WhmcsConnector\Api\Client;
use RoostKit\WhmcsConnector\Security\Crypto;

class ClientTest extends TestCase {

	private Crypto $crypto;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Use a real Crypto instance since it's final and can't be mocked.
		$this->crypto = new Crypto();

		// Default mocks for WordPress functions.
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'untrailingslashit' )->alias( function ( $string ) {
			return rtrim( $string, '/\\' );
		} );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_throws_when_identifier_missing(): void {
		$client = new Client(
			'https://whmcs.example.com',
			$this->crypto,
			[ 'api_identifier' => '', 'api_secret' => 'enc_secret' ]
		);

		$this->expectException( ApiException::class );

		$client->call( 'GetProducts' );
	}

	public function test_throws_when_secret_missing(): void {
		// Encrypt a real identifier so decryption succeeds.
		$encrypted_id = $this->crypto->encrypt( 'test-identifier' );

		$client = new Client(
			'https://whmcs.example.com',
			$this->crypto,
			[ 'api_identifier' => $encrypted_id, 'api_secret' => '' ]
		);

		$this->expectException( ApiException::class );

		$client->call( 'GetProducts' );
	}

	public function test_ip_blocked_error_detection(): void {
		$encrypted_id     = $this->crypto->encrypt( 'test-id' );
		$encrypted_secret = $this->crypto->encrypt( 'test-secret' );

		// Mock wp_remote_post to return an IP block error.
		Functions\when( 'wp_remote_post' )->justReturn( [
			'response' => [ 'code' => 403 ],
			'body'     => '{"result":"error","message":"Invalid IP 1.2.3.4"}',
		] );

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 403 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			'{"result":"error","message":"Invalid IP 1.2.3.4"}'
		);

		$client = new Client(
			'https://whmcs.example.com',
			$this->crypto,
			[ 'api_identifier' => $encrypted_id, 'api_secret' => $encrypted_secret ]
		);

		try {
			$client->call( 'GetProducts' );
			$this->fail( 'Expected ApiException' );
		} catch ( ApiException $e ) {
			$this->assertStringContainsString( 'Invalid IP', $e->get_api_message() );
		}
	}

	public function test_successful_api_call(): void {
		$encrypted_id     = $this->crypto->encrypt( 'test-id' );
		$encrypted_secret = $this->crypto->encrypt( 'test-secret' );

		$response_body = json_encode( [
			'result'   => 'success',
			'products' => [ 'product' => [] ],
		] );

		Functions\when( 'wp_remote_post' )->justReturn( [
			'response' => [ 'code' => 200 ],
			'body'     => $response_body,
		] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $response_body );

		$client = new Client(
			'https://whmcs.example.com',
			$this->crypto,
			[ 'api_identifier' => $encrypted_id, 'api_secret' => $encrypted_secret ]
		);

		$result = $client->call( 'GetProducts' );

		$this->assertSame( 'success', $result['result'] );
		$this->assertArrayHasKey( 'products', $result );
	}

	public function test_access_key_included_when_configured(): void {
		$encrypted_id     = $this->crypto->encrypt( 'test-id' );
		$encrypted_secret = $this->crypto->encrypt( 'test-secret' );
		$encrypted_key    = $this->crypto->encrypt( 'test-access-key' );

		$captured_body = null;
		Functions\when( 'wp_remote_post' )->alias( function ( $url, $args ) use ( &$captured_body ) {
			$captured_body = $args['body'];
			return [
				'response' => [ 'code' => 200 ],
				'body'     => json_encode( [ 'result' => 'success' ] ),
			];
		} );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"result":"success"}' );

		$client = new Client(
			'https://whmcs.example.com',
			$this->crypto,
			[
				'api_identifier' => $encrypted_id,
				'api_secret'     => $encrypted_secret,
				'api_access_key' => $encrypted_key,
			]
		);

		$result = $client->call( 'WhmcsDetails' );

		$this->assertSame( 'success', $result['result'] );
		$this->assertIsArray( $captured_body );
		$this->assertSame( 'test-access-key', $captured_body['accesskey'] );
		$this->assertSame( 'test-id', $captured_body['identifier'] );
		$this->assertSame( 'test-secret', $captured_body['secret'] );
	}

	public function test_access_key_omitted_when_not_configured(): void {
		$encrypted_id     = $this->crypto->encrypt( 'test-id' );
		$encrypted_secret = $this->crypto->encrypt( 'test-secret' );

		$captured_body = null;
		Functions\when( 'wp_remote_post' )->alias( function ( $url, $args ) use ( &$captured_body ) {
			$captured_body = $args['body'];
			return [
				'response' => [ 'code' => 200 ],
				'body'     => json_encode( [ 'result' => 'success' ] ),
			];
		} );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"result":"success"}' );

		$client = new Client(
			'https://whmcs.example.com',
			$this->crypto,
			[
				'api_identifier' => $encrypted_id,
				'api_secret'     => $encrypted_secret,
				'api_access_key' => '',
			]
		);

		$result = $client->call( 'WhmcsDetails' );

		$this->assertSame( 'success', $result['result'] );
		$this->assertIsArray( $captured_body );
		$this->assertArrayNotHasKey( 'accesskey', $captured_body );
	}

	public function test_api_exception_methods(): void {
		$exception = new ApiException( 'Connection failed', 'Invalid IP', 403 );

		$this->assertSame( 'Connection failed', $exception->getMessage() );
		$this->assertSame( 'Invalid IP', $exception->get_api_message() );
		$this->assertSame( 403, $exception->getCode() );
		$this->assertSame( 403, $exception->get_http_status() );
	}
}
