<?php
/**
 * Tests for the LinkReplacer.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\LinkReplacer;

class LinkReplacerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'apply_filters' )->alias( function ( $tag, $value = null ) {
			if ( 'whmcs_connector_is_pro' === $tag ) {
				return true;
			}
			return $value;
		} );
		Functions\when( 'untrailingslashit' )->alias( function ( $string ) {
			return rtrim( (string) $string, '/\\' );
		} );
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'add_filter' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_replaces_basic_pid_link(): void {
		$replacer = new LinkReplacer( 'https://whmcs.example.com' );
		$html     = '<div class="wp-block-button"><a class="wp-block-button__link" href="#whmcs-order-5">Order Plan</a></div>';

		$output = $replacer->replace_links( $html );

		$this->assertSame(
			'<div class="wp-block-button"><a class="wp-block-button__link" href="https://whmcs.example.com/cart.php?a=add&pid=5">Order Plan</a></div>',
			$output
		);
	}

	public function test_order_links_not_replaced_when_not_pro(): void {
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value = null ) {
			if ( 'whmcs_connector_is_pro' === $tag ) {
				return false;
			}
			return $value;
		} );

		$replacer = new LinkReplacer( 'https://whmcs.example.com' );
		$html     = '<a href="#whmcs-order-5">Order Plan</a> and <a href="#whmcs-clientarea">Portal</a>';

		$output = $replacer->replace_links( $html );

		// #whmcs-order-5 remains untouched, but #whmcs-clientarea is still resolved
		$this->assertSame(
			'<a href="#whmcs-order-5">Order Plan</a> and <a href="https://whmcs.example.com/clientarea.php">Portal</a>',
			$output
		);
	}

	public function test_replaces_pid_with_billing_cycle(): void {
		$replacer = new LinkReplacer( 'https://whmcs.example.com' );
		$html     = '<p>Get it now: <a href="#whmcs-order-12-annually">Annual Plan</a></p>';

		$output = $replacer->replace_links( $html );

		$this->assertSame(
			'<p>Get it now: <a href="https://whmcs.example.com/cart.php?a=add&pid=12&billingcycle=annually">Annual Plan</a></p>',
			$output
		);
	}

	public function test_handles_single_quotes_and_multiple_links(): void {
		$replacer = new LinkReplacer( 'https://whmcs.example.com/' );
		$html     = '<a href=\'#whmcs-order-1\'>Monthly</a> and <a href="#whmcs-order-2-biennially">2 Year</a>';

		$output = $replacer->replace_links( $html );

		$expected = '<a href=\'https://whmcs.example.com/cart.php?a=add&pid=1\'>Monthly</a> and <a href="https://whmcs.example.com/cart.php?a=add&pid=2&billingcycle=biennially">2 Year</a>';
		$this->assertSame( $expected, $output );
	}

	public function test_ignores_non_matching_hashes_and_zero_pid(): void {
		$replacer = new LinkReplacer( 'https://whmcs.example.com' );
		$html     = '<a href="#faq">FAQ</a> <a href="#whmcs-order-0">Zero</a>';

		$output = $replacer->replace_links( $html );

		$this->assertSame( $html, $output );
	}

	public function test_ignores_when_whmcs_url_is_empty(): void {
		$replacer = new LinkReplacer( '' );
		$html     = '<a href="#whmcs-order-5">Order Plan</a>';

		$output = $replacer->replace_links( $html );

		$this->assertSame( $html, $output );
	}

	public function test_replace_block_links_delegates_to_replace_links(): void {
		$replacer = new LinkReplacer( 'https://whmcs.example.com' );
		$html     = '<a href="#whmcs-order-8-quarterly">Quarterly</a>';

		$output = $replacer->replace_block_links( $html, [ 'blockName' => 'core/button' ] );

		$this->assertSame(
			'<a href="https://whmcs.example.com/cart.php?a=add&pid=8&billingcycle=quarterly">Quarterly</a>',
			$output
		);
	}

	public function test_replaces_portal_shortcut_links(): void {
		$replacer = new LinkReplacer( 'https://whmcs.example.com' );
		$html     = '<a href="#whmcs-clientarea">Client Area</a><a href="#whmcs-tickets">Tickets</a><a href="#whmcs-invoices">Invoices</a><a href="#whmcs-knowledgebase">KB</a>';

		$output = $replacer->replace_links( $html );

		$this->assertSame(
			'<a href="https://whmcs.example.com/clientarea.php">Client Area</a><a href="https://whmcs.example.com/supporttickets.php">Tickets</a><a href="https://whmcs.example.com/clientarea.php?action=invoices">Invoices</a><a href="https://whmcs.example.com/knowledgebase.php">KB</a>',
			$output
		);
	}
}
