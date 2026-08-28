<?php
/**
 * Tests for ComparisonData.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Admin
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Admin\ComparisonData;

class ComparisonDataTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_attr__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html_e' )->echoArg();
		Functions\when( 'esc_attr_e' )->echoArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_free_features_returns_array(): void {
		$features = ComparisonData::get_free_features();

		$this->assertIsArray( $features );
		$this->assertNotEmpty( $features );
	}

	public function test_each_free_feature_has_required_keys(): void {
		foreach ( ComparisonData::get_free_features() as $feature ) {
			$this->assertArrayHasKey( 'title', $feature );
			$this->assertArrayHasKey( 'description', $feature );
			$this->assertArrayHasKey( 'tag', $feature );
			$this->assertIsString( $feature['title'] );
			$this->assertIsString( $feature['description'] );
		}
	}

	public function test_get_pro_features_returns_five_items(): void {
		$features = ComparisonData::get_pro_features();

		$this->assertCount( 5, $features );
	}

	public function test_each_pro_feature_has_required_keys(): void {
		foreach ( ComparisonData::get_pro_features() as $feature ) {
			$this->assertArrayHasKey( 'title', $feature );
			$this->assertArrayHasKey( 'description', $feature );
			$this->assertArrayHasKey( 'tag', $feature );
		}
	}

	public function test_render_table_returns_html_string(): void {
		$html = ComparisonData::render_table();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'whmcs-connector-comparison-table', $html );
		$this->assertStringContainsString( '<table', $html );
		$this->assertStringContainsString( '<thead', $html );
		$this->assertStringContainsString( '<tbody', $html );
	}

	public function test_render_table_includes_pro_column(): void {
		$html = ComparisonData::render_table();

		$this->assertStringContainsString( 'column-pro', $html );
	}

	public function test_render_table_includes_free_column(): void {
		$html = ComparisonData::render_table();

		$this->assertStringContainsString( 'column-free', $html );
	}

	public function test_render_table_contains_check_icons(): void {
		$html = ComparisonData::render_table();

		// Table must contain at least one check mark and at least one dash.
		$this->assertStringContainsString( 'whmcs-icon-check', $html );
		$this->assertStringContainsString( 'whmcs-icon-dash', $html );
	}
}
