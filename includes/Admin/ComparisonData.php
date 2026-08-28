<?php
/**
 * Shared comparison data for Free vs Pro features.
 *
 * @package RoostKit\WhmcsConnector\Admin
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Admin;

/**
 * Single source of truth for Free and Pro feature lists and comparison table rendering.
 */
final class ComparisonData {

	/**
	 * Get the list of Free features.
	 *
	 * @return array<int, array{title: string, description: string, tag: string}>
	 */
	public static function get_free_features(): array {
		return [
			[
				'title'       => __( 'WHMCS Client Login Form', 'whmcs-connector' ),
				'description' => __( 'Rate-limited client login form block and shortcode ([whmcs_login]) with custom redirect support.', 'whmcs-connector' ),
				'tag'         => __( 'Free Block & Shortcode', 'whmcs-connector' ),
			],
			[
				'title'       => __( 'WHMCS Client Area Navigation', 'whmcs-connector' ),
				'description' => __( 'Account navigation card block and shortcode ([whmcs_client_area]) with modular quick-links.', 'whmcs-connector' ),
				'tag'         => __( 'Free Block & Shortcode', 'whmcs-connector' ),
			],
			[
				'title'       => __( 'Basic Pricing Card Grid', 'whmcs-connector' ),
				'description' => __( 'Clean, responsive card grid block and shortcode ([whmcs_pricing]) rendering live product rates.', 'whmcs-connector' ),
				'tag'         => __( 'Free Block & Shortcode', 'whmcs-connector' ),
			],
			[
				'title'       => __( 'Dual-Layer Performance Cache', 'whmcs-connector' ),
				'description' => __( 'In-memory query deduplication plus transient caching to prevent redundant WHMCS API requests.', 'whmcs-connector' ),
				'tag'         => __( 'Core Architecture', 'whmcs-connector' ),
			],
			[
				'title'       => __( 'Encrypted Credential Storage', 'whmcs-connector' ),
				'description' => __( 'API Identifier and Secret encrypted at rest with libsodium/OpenSSL; zero external data exfiltration.', 'whmcs-connector' ),
				'tag'         => __( 'Security', 'whmcs-connector' ),
			],
		];
	}

	/**
	 * Get the list of Pro features.
	 *
	 * @return array<int, array{title: string, description: string, tag: string}>
	 */
	public static function get_pro_features(): array {
		return [
			[
				'title'       => __( 'SaaS Pricing Table Block', 'whmcs-connector' ),
				'description' => __( 'Multi-tier pricing table with live Monthly / Annual billing cycle toggle and auto-calculated discount pills.', 'whmcs-connector' ),
				'tag'         => __( 'Pro Block', 'whmcs-connector' ),
			],
			[
				'title'       => __( 'Featured Web Hosting Grid Block', 'whmcs-connector' ),
				'description' => __( 'High-converting hosting plans with ribbon badges, strikethrough discount badges, and feature checklists.', 'whmcs-connector' ),
				'tag'         => __( 'Pro Block', 'whmcs-connector' ),
			],
			[
				'title'       => __( 'Custom Cloud VPS Configurator Block', 'whmcs-connector' ),
				'description' => __( 'Interactive slider calculator dynamically scaling vCPU, RAM, and NVMe storage with live WHMCS configurable options.', 'whmcs-connector' ),
				'tag'         => __( 'Pro Block', 'whmcs-connector' ),
			],
			[
				'title'       => __( 'Live Domain Search & WHOIS Widget', 'whmcs-connector' ),
				'description' => __( 'Real-time domain availability lookup and multi-TLD pricing pills with direct WHMCS domain checkout.', 'whmcs-connector' ),
				'tag'         => __( 'Pro Block & REST API', 'whmcs-connector' ),
			],
			[
				'title'       => __( 'Atomic Shortcodes & Theme Link Interceptor', 'whmcs-connector' ),
				'description' => __( 'Inline [whmcs_price], [whmcs_name], [whmcs_order_url] and #whmcs-order-{PID} hooks for custom theme patterns.', 'whmcs-connector' ),
				'tag'         => __( 'Pro Shortcodes & Links', 'whmcs-connector' ),
			],
		];
	}

	/**
	 * Render the Free vs Pro comparison table HTML.
	 *
	 * @return string HTML output.
	 */
	public static function render_table(): string {
		$rows = [
			[
				'feature'     => __( 'WHMCS Local API Integration', 'whmcs-connector' ),
				'description' => __( 'Secure API connection without HTML scraping', 'whmcs-connector' ),
				'free'        => true,
				'pro'         => true,
			],
			[
				'feature'     => __( 'Encrypted Credential Storage', 'whmcs-connector' ),
				'description' => __( 'API keys encrypted at rest with libsodium', 'whmcs-connector' ),
				'free'        => true,
				'pro'         => true,
			],
			[
				'feature'     => __( 'Dual-Layer API Caching', 'whmcs-connector' ),
				'description' => __( 'Transient query caching + in-memory deduplication', 'whmcs-connector' ),
				'free'        => true,
				'pro'         => true,
			],
			[
				'feature'     => __( 'WHMCS Client Login Form', 'whmcs-connector' ),
				'description' => __( 'Rate-limited login block & [whmcs_login] shortcode', 'whmcs-connector' ),
				'free'        => true,
				'pro'         => true,
			],
			[
				'feature'     => __( 'WHMCS Client Area Navigation', 'whmcs-connector' ),
				'description' => __( 'Account quick-links block & [whmcs_client_area] shortcode', 'whmcs-connector' ),
				'free'        => true,
				'pro'         => true,
			],
			[
				'feature'     => __( 'Standard Product Pricing Grid', 'whmcs-connector' ),
				'description' => __( 'Card grid block & [whmcs_pricing] shortcode', 'whmcs-connector' ),
				'free'        => true,
				'pro'         => true,
			],
			[
				'feature'     => __( 'SaaS Pricing Table with Cycle Toggle', 'whmcs-connector' ),
				'description' => __( 'Monthly/Annual capsule switcher with live price updates', 'whmcs-connector' ),
				'free'        => false,
				'pro'         => true,
			],
			[
				'feature'     => __( 'Featured Web Hosting Grid', 'whmcs-connector' ),
				'description' => __( 'Popular ribbon badges, discount pills & feature checklists', 'whmcs-connector' ),
				'free'        => false,
				'pro'         => true,
			],
			[
				'feature'     => __( 'Custom Cloud VPS Configurator', 'whmcs-connector' ),
				'description' => __( 'Live CPU, RAM & NVMe resource sliders with WHMCS options', 'whmcs-connector' ),
				'free'        => false,
				'pro'         => true,
			],
			[
				'feature'     => __( 'Live Domain Search & TLD Availability', 'whmcs-connector' ),
				'description' => __( 'AJAX WHOIS domain lookup widget with TLD pills', 'whmcs-connector' ),
				'free'        => false,
				'pro'         => true,
			],
			[
				'feature'     => __( 'Atomic Shortcodes & Link Interceptor', 'whmcs-connector' ),
				'description' => __( '[whmcs_price], [whmcs_order_url], and #whmcs-order-{PID}', 'whmcs-connector' ),
				'free'        => false,
				'pro'         => true,
			],
		];

		ob_start();
		?>
		<div class="whmcs-connector-comparison-wrapper">
			<table class="whmcs-connector-comparison-table widefat striped" role="table">
				<thead>
					<tr>
						<th scope="col" class="column-feature"><?php esc_html_e( 'Feature', 'whmcs-connector' ); ?></th>
						<th scope="col" class="column-tier column-free"><?php esc_html_e( 'Free Edition', 'whmcs-connector' ); ?></th>
						<th scope="col" class="column-tier column-pro">
							<?php esc_html_e( 'Pro Edition', 'whmcs-connector' ); ?>
							<span class="whmcs-connector-badge-pro"><?php esc_html_e( 'PRO', 'whmcs-connector' ); ?></span>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td class="column-feature">
								<strong><?php echo esc_html( $row['feature'] ); ?></strong>
								<span class="description"><?php echo esc_html( $row['description'] ); ?></span>
							</td>
							<td class="column-tier column-free">
								<?php if ( $row['free'] ) : ?>
									<span class="whmcs-icon-check" aria-label="<?php esc_attr_e( 'Included in Free', 'whmcs-connector' ); ?>">&#10003;</span>
								<?php else : ?>
									<span class="whmcs-icon-dash" aria-label="<?php esc_attr_e( 'Not included in Free', 'whmcs-connector' ); ?>">&mdash;</span>
								<?php endif; ?>
							</td>
							<td class="column-tier column-pro">
								<span class="whmcs-icon-check" aria-label="<?php esc_attr_e( 'Included in Pro', 'whmcs-connector' ); ?>">&#10003;</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
