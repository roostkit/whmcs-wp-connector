<?php
/**
 * Tests for Admin Notices.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Admin
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Admin\Notices;

class NoticesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'admin_url' )->alias( function ( $path = '' ) {
			return 'https://example.com/wp-admin/' . $path;
		} );
		Functions\when( 'current_user_can' )->alias( function ( $cap ) {
			return 'manage_options' === $cap;
		} );
		Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
			return $default;
		} );
		Functions\when( 'get_user_meta' )->justReturn( false );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'get_current_screen' )->justReturn( null );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_attr__' )->returnArg();
		Functions\when( 'esc_js' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_notices_registers_admin_notices_hook(): void {
		Functions\expect( 'add_action' )
			->twice()
			->withAnyArgs();

		$notices = new Notices();
		$notices->register();
		$this->assertTrue( true );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_renders_force_pro_warning_notice_when_constant_set(): void {
		if ( ! defined( 'WHMCS_CONNECTOR_FORCE_PRO' ) ) {
			define( 'WHMCS_CONNECTOR_FORCE_PRO', true );
		}

		$notices = new Notices();

		ob_start();
		$notices->render_notices();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'Pro features are force-enabled via WHMCS_CONNECTOR_FORCE_PRO', $output );
	}

	public function test_does_not_render_notices_for_non_admins(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$notices = new Notices();

		ob_start();
		$notices->render_notices();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_getting_started_notice_not_shown_on_wrong_screen(): void {
		// Screen returns a non-dashboard, non-plugins screen ID.
		$screen     = new \stdClass();
		$screen->id = 'whmcs-connector'; // Settings page — notice should NOT appear.
		Functions\when( 'get_current_screen' )->justReturn( $screen );

		$notices = new Notices();

		ob_start();
		$notices->render_notices();
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'whmcs-connector-getting-started-notice', $output );
	}

	public function test_getting_started_notice_not_shown_when_dismissed(): void {
		$screen     = new \stdClass();
		$screen->id = 'dashboard';
		Functions\when( 'get_current_screen' )->justReturn( $screen );
		// User has dismissed notice.
		Functions\when( 'get_user_meta' )->justReturn( true );

		$notices = new Notices();

		ob_start();
		$notices->render_notices();
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'whmcs-connector-getting-started-notice', $output );
	}

	public function test_getting_started_notice_not_shown_when_welcome_seen(): void {
		$screen     = new \stdClass();
		$screen->id = 'dashboard';
		Functions\when( 'get_current_screen' )->justReturn( $screen );
		Functions\when( 'get_user_meta' )->justReturn( false );
		// Welcome page has been visited.
		Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
			if ( 'whmcs_connector_welcome_seen' === $key ) {
				return true;
			}
			return $default;
		} );

		$notices = new Notices();

		ob_start();
		$notices->render_notices();
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'whmcs-connector-getting-started-notice', $output );
	}

	public function test_dismiss_meta_key_constant_set(): void {
		$this->assertSame( 'whmcs_connector_notice_dismissed', Notices::DISMISS_META_KEY );
	}
}
