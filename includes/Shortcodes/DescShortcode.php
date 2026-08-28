<?php
/**
 * Atomic product description shortcode (Pro only).
 *
 * @package RoostKit\WhmcsConnector\Shortcodes
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Shortcodes;

use RoostKit\WhmcsConnector\Api\ApiLog;
use RoostKit\WhmcsConnector\Plugin;
use RoostKit\WhmcsConnector\ProductRepository;

/**
 * [whmcs_desc pid="{id}"] — outputs the product description.
 *
 * Atomic shortcode designed for inline composition in custom theme layouts,
 * block patterns, or builder templates.
 */
final class DescShortcode extends AbstractShortcode {

	/**
	 * Product repository instance.
	 *
	 * @var ProductRepository
	 */
	private ProductRepository $repository;

	/**
	 * API logger instance.
	 *
	 * @var ApiLog|null
	 */
	private ?ApiLog $api_log;

	/**
	 * Constructor.
	 *
	 * @param ProductRepository $repository Product repository.
	 * @param ApiLog|null       $api_log    API logger instance.
	 * @param string            $whmcs_url  WHMCS URL.
	 */
	public function __construct( ProductRepository $repository, ?ApiLog $api_log = null, string $whmcs_url = '' ) {
		$this->repository = $repository;
		$this->api_log    = $api_log;
		$this->whmcs_url  = untrailingslashit( $whmcs_url );
	}

	/**
	 * Register the shortcode.
	 */
	public function register(): void {
		add_shortcode( 'whmcs_desc', [ $this, 'render' ] );
	}

	/**
	 * Render the product description string.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Product description HTML or empty string on miss.
	 */
	public function render( array $atts = [] ): string {
		if ( ! Plugin::is_pro() ) {
			return $this->render_pro_required( 'whmcs_desc' );
		}

		$atts = shortcode_atts(
			[
				'pid' => '',
			],
			$atts,
			'whmcs_desc'
		);

		$raw_pid = $atts['pid'] ?? '';
		if ( ! is_numeric( $raw_pid ) || (int) $raw_pid <= 0 ) {
			$this->log_miss( 'Invalid or missing product ID (pid).' );
			return '';
		}

		$pid     = (int) $raw_pid;
		$product = $this->repository->get_product( $pid );
		if ( null === $product || empty( $product ) ) {
			$this->log_miss( sprintf( 'Product #%d not found.', $pid ) );
			return '';
		}

		$desc = (string) ( $product['description'] ?? '' );
		return wp_kses_post( $desc );
	}

	/**
	 * Log a miss to ApiLog at low severity.
	 *
	 * @param string $message Miss explanation.
	 */
	private function log_miss( string $message ): void {
		if ( null !== $this->api_log ) {
			$this->api_log->log( 'whmcs_desc', $message, 0 );
		}
	}
}
