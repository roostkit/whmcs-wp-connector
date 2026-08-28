<?php
/**
 * Pricing shortcode (Free Core).
 *
 * @package RoostKit\WhmcsConnector\Shortcodes
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Shortcodes;

use RoostKit\WhmcsConnector\Api\ApiLog;
use RoostKit\WhmcsConnector\ProductRepository;

/**
 * [whmcs_pricing] — renders a standard product card grid from WHMCS API.
 *
 * Unopinionated, functional, theme-inheriting output with "Order Now" links
 * redirecting to the customer's WHMCS checkout.
 */
final class PricingShortcode extends AbstractShortcode {

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
	 * @param string            $whmcs_url  WHMCS installation URL.
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
		add_shortcode( 'whmcs_pricing', [ $this, 'render' ] );
	}

	/**
	 * Render the pricing card grid.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'gid'           => '',
				'pid'           => '',
				'billing_cycle' => '',
				'columns'       => '3',
				'class'         => '',
			],
			$atts,
			'whmcs_pricing'
		);

		$this->enqueue_frontend_css();

		if ( empty( $this->whmcs_url ) ) {
			return $this->render_error();
		}

		$products = $this->repository->get_products( $atts['gid'], $atts['pid'] );

		if ( null === $products || empty( $products ) ) {
			return $this->render_error( __( 'No products found.', 'whmcs-connector' ) );
		}

		$columns     = max( 1, min( 4, (int) $atts['columns'] ) );
		$extra_class = ! empty( $atts['class'] ) ? ' ' . sanitize_html_class( $atts['class'] ) : '';
		$cycle_filt  = sanitize_key( (string) $atts['billing_cycle'] );

		ob_start();
		?>
		<div class="whmcs-connector-pricing-grid whmcs-cols-<?php echo esc_attr( (string) $columns ); ?><?php echo esc_attr( $extra_class ); ?>">
			<?php foreach ( $products as $product ) : ?>
				<?php
				$pid       = (int) ( $product['pid'] ?? 0 );
				$name      = (string) ( $product['name'] ?? '' );
				$desc      = (string) ( $product['description'] ?? '' );
				$pricing   = $product['pricing'] ?? [];
				$order_url = $this->whmcs_url . '/cart.php?a=add&pid=' . $pid;
				if ( ! empty( $cycle_filt ) ) {
					$order_url .= '&billingcycle=' . rawurlencode( $cycle_filt );
				}
				?>
				<div class="whmcs-connector-pricing-card">
					<h3 class="whmcs-connector-product-title"><?php echo esc_html( $name ); ?></h3>

					<?php if ( ! empty( $desc ) ) : ?>
						<div class="whmcs-connector-product-desc">
							<?php echo wp_kses_post( nl2br( $desc ) ); ?>
						</div>
					<?php endif; ?>

					<div class="whmcs-connector-product-pricing">
						<?php
						$rates = is_array( $pricing ) ? $this->repository->get_default_currency_pricing( $pricing ) : [];
						if ( ! empty( $cycle_filt ) && isset( $rates[ $cycle_filt ] ) ) {
							$rates = [ $cycle_filt => $rates[ $cycle_filt ] ];
						}
						?>
						<?php if ( ! empty( $rates ) ) : ?>
							<ul class="whmcs-connector-price-list">
								<?php foreach ( $rates as $cycle => $amount ) : ?>
									<li>
										<span class="whmcs-cycle-label"><?php echo esc_html( ProductRepository::CYCLE_LABELS[ $cycle ] ?? ucfirst( $cycle ) ); ?>:</span>
										<span class="whmcs-cycle-amount"><?php echo esc_html( $this->repository->format_price( (float) $amount, is_array( $pricing ) ? $pricing : [] ) ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<div class="whmcs-connector-pricing-action">
						<a href="<?php echo esc_url( $order_url ); ?>" class="whmcs-connector-btn wp-element-button" target="_blank" rel="noopener">
							<?php esc_html_e( 'Order Now', 'whmcs-connector' ); ?> &rarr;
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
