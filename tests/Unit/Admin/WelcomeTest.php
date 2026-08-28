<?php
/**
 * Tests for Welcome screen.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Admin
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Admin\Welcome;

class WelcomeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html_e' )->echoArg();
		Functions\when( 'admin_url' )->alias( function ( $path = '' ) {
			return 'https://example.com/wp-admin/' . $path;
		} );
		Functions\when( 'current_user_can' )->alias( function ( $cap ) {
			return 'manage_options' === $cap;
		} );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'is_network_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_hooks_added(): void {
		Functions\expect( 'add_action' )
			->times( 2 )
			->withAnyArgs();

		$welcome = new Welcome();
		$welcome->register();
		$this->assertTrue( true );
	}

	public function test_check_activation_redirect_no_op_when_transient_absent(): void {
		// get_transient returns false — no redirect should happen.
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'wp_safe_redirect' )->never();

		$welcome = new Welcome();
		$welcome->check_activation_redirect();
		$this->assertTrue( true );
	}

	public function test_check_activation_redirect_skips_bulk_activation(): void {
		Functions\when( 'get_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\expect( 'wp_safe_redirect' )->never();

		// Simulate bulk activation query param.
		$_GET['activate-multi'] = '1';

		$welcome = new Welcome();
		$welcome->check_activation_redirect();

		unset( $_GET['activate-multi'] );
		$this->assertTrue( true );
	}

	public function test_page_slug_constant_is_set(): void {
		$this->assertSame( 'whmcs-connector-welcome', Welcome::PAGE_SLUG );
	}

	public function test_redirect_transient_constant_is_set(): void {
		$this->assertSame( 'whmcs_connector_activation_redirect', Welcome::REDIRECT_TRANSIENT );
	}
}
