<?php
/**
 * Atomic order URL shortcode (Pro only).
 *
 * @package RoostKit\WhmcsConnector\Shortcodes
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Shortcodes;

use RoostKit\WhmcsConnector\Plugin;

/**
 * [whmcs_order_url pid="{id}"] — outputs a raw WHMCS cart URL.
 *
 * Atomic shortcode designed for pasting directly into block or button URL fields.
 * Outputs raw URL string only (escaped with esc_url, no HTML markup).
 */
final class OrderUrlShortcode extends AbstractShortcode {

	/**
	 * Constructor.
	 *
	 * @param string $whmcs_url WHMCS URL.
	 */
	public function __construct( string $whmcs_url ) {
		$this->whmcs_url = untrailingslashit( $whmcs_url );
	}

	/**
	 * Register the shortcode.
	 */
	public function register(): void {
		add_shortcode( 'whmcs_order_url', [ $this, 'render' ] );
	}

	/**
	 * Render the raw order URL.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Escaped order URL or empty string.
	 */
	public function render( array $atts = [] ): string {
		if ( ! Plugin::is_pro() ) {
			return $this->render_pro_required( 'whmcs_order_url' );
		}

		$atts = shortcode_atts(
			[
				'pid' => '',
			],
			$atts,
			'whmcs_order_url'
		);

		if ( empty( $this->whmcs_url ) ) {
			return '';
		}

		$raw_pid = $atts['pid'] ?? '';
		if ( ! is_numeric( $raw_pid ) || (int) $raw_pid <= 0 ) {
			return '';
		}

		$pid = (int) $raw_pid;

		$url = $this->whmcs_url . '/cart.php?a=add&pid=' . $pid;

		return esc_url( $url );
	}
}
