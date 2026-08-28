<?php
/**
 * Gutenberg Block Patterns for WHMCS Connector.
 *
 * @package RoostKit\WhmcsConnector\Blocks
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Blocks;

/**
 * Registers block pattern categories and standard block patterns.
 */
final class Patterns {

	/**
	 * Register pattern category and patterns.
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_pattern_category' ) || ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		$this->register_category();
		$this->register_patterns();
	}

	/**
	 * Register the WHMCS Connector pattern category.
	 */
	private function register_category(): void {
		register_block_pattern_category(
			'whmcs-connector',
			[
				'label' => __( 'WHMCS Connector', 'whmcs-connector' ),
			]
		);
	}

	/**
	 * Register individual block patterns.
	 */
	private function register_patterns(): void {
		// 1. Three Column Modern Hosting Grid.
		register_block_pattern(
			'whmcs-connector/pricing-3-col',
			[
				'title'       => __( 'WHMCS 3-Column Modern Hosting Grid', 'whmcs-connector' ),
				'description' => __( 'Responsive 3-column pricing grid with dynamic WHMCS atomic price shortcodes, SVG checkmark features, and order buttons.', 'whmcs-connector' ),
				'categories'  => [ 'whmcs-connector', 'pricing', 'buttons', 'columns' ],
				'keywords'    => [ 'pricing', 'whmcs', 'table', 'grid', 'hosting', 'plan' ],
				'content'     => '<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"24px","left":"24px"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"style":{"spacing":{"padding":{"top":"36px","right":"28px","bottom":"36px","left":"28px"}},"border":{"width":"1px","style":"solid","radius":"16px"}},"className":"whmcs-pricing-card"} -->
<div class="wp-block-column whmcs-pricing-card" style="border-style:solid;border-width:1px;border-radius:16px;padding-top:36px;padding-right:28px;padding-bottom:36px;padding-left:28px"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">' . esc_html__( 'Starter Hosting', 'whmcs-connector' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"}}} -->
<p style="font-size:14px">' . esc_html__( 'Essential performance for personal websites, portfolios, and blogs.', 'whmcs-connector' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"32px","fontWeight":"700"}}} -->
<p style="font-size:32px;font-weight:700">[whmcs_price pid="1" cycle="monthly"] <span style="font-size:14px;font-weight:normal;opacity:0.75">/mo</span></p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"whmcs-feature-list"} -->
<ul class="wp-block-list whmcs-feature-list"><!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( '1 High-Speed Website', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( '10 GB NVMe Storage', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( 'Free Automated SSL Certificate', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( 'Standard 24/7 Ticket Support', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"28px"}}}} -->
<div class="wp-block-buttons" style="margin-top:28px"><!-- wp:button {"width":100,"style":{"border":{"radius":"8px"}},"className":"is-style-outline"} -->
<div class="wp-block-button has-custom-width wp-block-button__width-100 is-style-outline"><a class="wp-block-button__link wp-element-button" href="#whmcs-order-1" style="border-radius:8px">' . esc_html__( 'Order Starter', 'whmcs-connector' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"padding":{"top":"36px","right":"28px","bottom":"36px","left":"28px"}},"border":{"width":"2px","style":"solid","radius":"16px"}},"className":"whmcs-pricing-card is-popular"} -->
<div class="wp-block-column whmcs-pricing-card is-popular" style="border-style:solid;border-width:2px;border-radius:16px;padding-top:36px;padding-right:28px;padding-bottom:36px;padding-left:28px"><!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","fontWeight":"700","textTransform":"uppercase"}},"className":"whmcs-badge"} -->
<p class="whmcs-badge" style="font-size:12px;font-weight:700;text-transform:uppercase">' . esc_html__( 'Most Popular', 'whmcs-connector' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">' . esc_html__( 'Professional Plan', 'whmcs-connector' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"}}} -->
<p style="font-size:14px">' . esc_html__( 'Turbocharged performance optimized for growing business websites.', 'whmcs-connector' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"32px","fontWeight":"700"}}} -->
<p style="font-size:32px;font-weight:700">[whmcs_price pid="2" cycle="monthly"] <span style="font-size:14px;font-weight:normal;opacity:0.75">/mo</span></p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"whmcs-feature-list"} -->
<ul class="wp-block-list whmcs-feature-list"><!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( 'Unlimited Websites', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( '50 GB Ultra NVMe Storage', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( 'Free Domain Name Included', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( 'Priority 24/7 VIP Support', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"28px"}}}} -->
<div class="wp-block-buttons" style="margin-top:28px"><!-- wp:button {"width":100,"style":{"border":{"radius":"8px"}},"className":"is-style-fill"} -->
<div class="wp-block-button has-custom-width wp-block-button__width-100 is-style-fill"><a class="wp-block-button__link wp-element-button" href="#whmcs-order-2" style="border-radius:8px">' . esc_html__( 'Order Professional', 'whmcs-connector' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"padding":{"top":"36px","right":"28px","bottom":"36px","left":"28px"}},"border":{"width":"1px","style":"solid","radius":"16px"}},"className":"whmcs-pricing-card"} -->
<div class="wp-block-column whmcs-pricing-card" style="border-style:solid;border-width:1px;border-radius:16px;padding-top:36px;padding-right:28px;padding-bottom:36px;padding-left:28px"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">' . esc_html__( 'Enterprise Cloud', 'whmcs-connector' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"}}} -->
<p style="font-size:14px">' . esc_html__( 'Maximum computing resources and dedicated infrastructure for mission-critical apps.', 'whmcs-connector' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"32px","fontWeight":"700"}}} -->
<p style="font-size:32px;font-weight:700">[whmcs_price pid="3" cycle="monthly"] <span style="font-size:14px;font-weight:normal;opacity:0.75">/mo</span></p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"whmcs-feature-list"} -->
<ul class="wp-block-list whmcs-feature-list"><!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( 'Unlimited Websites & Databases', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( '200 GB Enterprise NVMe SSD', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( 'Dedicated IPv4 Address', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( 'Dedicated Senior Account Manager', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"28px"}}}} -->
<div class="wp-block-buttons" style="margin-top:28px"><!-- wp:button {"width":100,"style":{"border":{"radius":"8px"}},"className":"is-style-outline"} -->
<div class="wp-block-button has-custom-width wp-block-button__width-100 is-style-outline"><a class="wp-block-button__link wp-element-button" href="#whmcs-order-3" style="border-radius:8px">' . esc_html__( 'Order Enterprise', 'whmcs-connector' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->',
			]
		);

		// 2. Single Product Feature Highlight (Horizontal Card Layout).
		register_block_pattern(
			'whmcs-connector/pricing-horizontal-card',
			[
				'title'       => __( 'WHMCS Single Product Feature Highlight', 'whmcs-connector' ),
				'description' => __( 'Horizontal card layout featuring product details, SVG checkmark list, atomic price, and order button.', 'whmcs-connector' ),
				'categories'  => [ 'whmcs-connector', 'pricing', 'buttons' ],
				'keywords'    => [ 'featured', 'pricing', 'whmcs', 'product', 'card', 'horizontal' ],
				'content'     => '<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"40px","right":"36px","bottom":"40px","left":"36px"}},"border":{"width":"1px","style":"solid","radius":"20px"}},"className":"whmcs-horizontal-product-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide whmcs-horizontal-product-card" style="border-style:solid;border-width:1px;border-radius:20px;padding-top:40px;padding-right:36px;padding-bottom:40px;padding-left:36px"><!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"40px"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"60%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:60%"><!-- wp:heading {"level":3,"fontSize":"x-large"} -->
<h3 class="wp-block-heading has-x-large-font-size">' . esc_html__( 'Managed Cloud VPS Hosting', 'whmcs-connector' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"15px"}}} -->
<p style="font-size:15px">' . esc_html__( 'Dedicated cloud server performance engineered for speed, high uptime, and developer flexibility with full root access.', 'whmcs-connector' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"whmcs-feature-list"} -->
<ul class="wp-block-list whmcs-feature-list"><!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( '4 Dedicated vCPU Cores & 8 GB High-Speed RAM', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( '160 GB Enterprise NVMe Gen4 Storage', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><svg class="whmcs-check-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> ' . esc_html__( 'Automated Daily Backups & Instant Snapshots', 'whmcs-connector' ) . '</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"40%","style":{"spacing":{"padding":{"top":"28px","right":"28px","bottom":"28px","left":"28px"}},"border":{"width":"1px","style":"solid","radius":"16px"}},"className":"whmcs-highlight-price-box"} -->
<div class="wp-block-column is-vertically-aligned-center whmcs-highlight-price-box" style="border-style:solid;border-width:1px;border-radius:16px;padding-top:28px;padding-right:28px;padding-bottom:28px;padding-left:28px;flex-basis:40%"><!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","fontWeight":"700","textTransform":"uppercase"}}} -->
<p style="font-size:12px;font-weight:700;text-transform:uppercase">' . esc_html__( 'Starting From', 'whmcs-connector' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"36px","fontWeight":"700"}}} -->
<p style="font-size:36px;font-weight:700">[whmcs_price pid="1" cycle="monthly"] <span style="font-size:15px;font-weight:normal;opacity:0.75">/mo</span></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"13px"}}} -->
<p style="font-size:13px">' . esc_html__( 'Renews at same rate. Cancel anytime.', 'whmcs-connector' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"20px"}}}} -->
<div class="wp-block-buttons" style="margin-top:20px"><!-- wp:button {"width":100,"style":{"border":{"radius":"8px"}},"className":"is-style-fill"} -->
<div class="wp-block-button has-custom-width wp-block-button__width-100 is-style-fill"><a class="wp-block-button__link wp-element-button" href="#whmcs-order-1" style="border-radius:8px">' . esc_html__( 'Deploy Server Now', 'whmcs-connector' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->',
			]
		);

		// 3. Client Portal Quick Actions Pattern.
		register_block_pattern(
			'whmcs-connector/client-portal',
			[
				'title'       => __( 'WHMCS Client Portal Quick Actions', 'whmcs-connector' ),
				'description' => __( 'Client dashboard quick actions linking to client area, tickets, invoices, and knowledgebase.', 'whmcs-connector' ),
				'categories'  => [ 'whmcs-connector', 'buttons' ],
				'keywords'    => [ 'portal', 'dashboard', 'client', 'whmcs', 'tickets' ],
				'content'     => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"28px","right":"28px","bottom":"28px","left":"28px"}},"border":{"width":"1px","style":"solid","radius":"12px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="border-style:solid;border-width:1px;border-radius:12px;padding-top:28px;padding-right:28px;padding-bottom:28px;padding-left:28px"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'Client Portal', 'whmcs-connector' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'Access your services, view open support tickets, and pay pending invoices.', 'whmcs-connector' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","flexWrap":"wrap"},"style":{"spacing":{"margin":{"top":"20px"}}}} -->
<div class="wp-block-buttons" style="margin-top:20px"><!-- wp:button {"className":"is-style-fill"} -->
<div class="wp-block-button is-style-fill"><a class="wp-block-button__link wp-element-button" href="#whmcs-clientarea">' . esc_html__( 'Client Area', 'whmcs-connector' ) . '</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#whmcs-tickets">' . esc_html__( 'Support Tickets', 'whmcs-connector' ) . '</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#whmcs-invoices">' . esc_html__( 'Invoices', 'whmcs-connector' ) . '</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#whmcs-knowledgebase">' . esc_html__( 'Knowledgebase', 'whmcs-connector' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
			]
		);
	}
}
