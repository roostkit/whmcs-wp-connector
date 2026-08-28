<?php
/**
 * Atomic price shortcode (Pro only).
 *
 * @package RoostKit\WhmcsConnector\Shortcodes
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Shortcodes;

use RoostKit\WhmcsConnector\Api\ApiLog;
use RoostKit\WhmcsConnector\Plugin;
use RoostKit\WhmcsConnector\ProductRepository;

/**
 * [whmcs_price pid="{id}" cycle="annually"] — outputs a single formatted price string.
 *
 * Atomic shortcode designed for inline composition in custom theme layouts,
 * block patterns, or builder templates. Outputs raw text only (no HTML wrappers).
 */
final class PriceShortcode extends AbstractShortcode {

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
		add_shortcode( 'whmcs_price', [ $this, 'render' ] );
	}

	/**
	 * Render the formatted price string.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Formatted price or empty string on miss.
	 */
	public function render( array $atts = [] ): string {
		if ( ! Plugin::is_pro() ) {
			return $this->render_pro_required( 'whmcs_price' );
		}

		$atts = shortcode_atts(
			[
				'pid'   => '',
				'cycle' => 'monthly',
			],
			$atts,
			'whmcs_price'
		);

		$raw_pid = $atts['pid'] ?? '';
		if ( ! is_numeric( $raw_pid ) || (int) $raw_pid <= 0 ) {
			$this->log_miss( 'Invalid or missing product ID (pid).' );
			return '';
		}

		$pid = (int) $raw_pid;

		$cycle = sanitize_key( (string) $atts['cycle'] );
		if ( empty( $cycle ) ) {
			$cycle = 'monthly';
		}

		$product = $this->repository->get_product( $pid );
		if ( null === $product || empty( $product ) ) {
			$this->log_miss( sprintf( 'Product #%d not found.', $pid ) );
			return '';
		}

		$pricing = $product['pricing'] ?? [];
		if ( ! is_array( $pricing ) || empty( $pricing ) ) {
			$this->log_miss( sprintf( 'No pricing structure found for product #%d.', $pid ) );
			return '';
		}

		$first_currency = reset( $pricing );
		if ( ! is_array( $first_currency ) || ! isset( $first_currency[ $cycle ] ) ) {
			$this->log_miss( sprintf( "Billing cycle '%s' not found for product #%d.", $cycle, $pid ) );
			return '';
		}

		$price = (float) $first_currency[ $cycle ];
		if ( $price < 0 ) {
			// Negative price in WHMCS indicates disabled billing cycle.
			$this->log_miss( sprintf( "Billing cycle '%s' is disabled for product #%d.", $cycle, $pid ) );
			return '';
		}

		return $this->repository->format_price( $price, $pricing );
	}

	/**
	 * Log a miss to ApiLog at low severity.
	 *
	 * @param string $message Miss explanation.
	 */
	private function log_miss( string $message ): void {
		if ( null !== $this->api_log ) {
			$this->api_log->log( 'whmcs_price', $message, 0 );
		}
	}
}
