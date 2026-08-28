<?php
/**
 * Pretty permalink registration.
 *
 * @package RoostKit\WhmcsConnector
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector;

/**
 * Registers rewrite rules for /clientarea/ and /pricing/ routes.
 */
final class Permalinks {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_action( 'init', [ $this, 'maybe_flush_rules' ], 999 );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_routes' ] );
	}

	/**
	 * Add rewrite rules.
	 */
	public function add_rewrite_rules(): void {
		/**
		 * Filter the permalink slugs.
		 *
		 * @param array<string, string> $slugs Slug => shortcode mapping.
		 */
		$slugs = apply_filters(
			'whmcs_connector_permalink_slugs',
			[
				'clientarea' => 'whmcs_client_area',
				'pricing'    => 'whmcs_pricing',
			]
		);

		foreach ( $slugs as $slug => $shortcode ) {
			add_rewrite_rule(
				'^' . preg_quote( $slug, '/' ) . '/?$',
				'index.php?whmcs_connector_route=' . $shortcode,
				'top'
			);
		}
	}

	/**
	 * Register the custom query var.
	 *
	 * @param array<string> $vars Existing query vars.
	 * @return array<string>
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'whmcs_connector_route';
		return $vars;
	}

	/**
	 * Handle custom routes by rendering the appropriate shortcode.
	 */
	public function handle_routes(): void {
		$route = get_query_var( 'whmcs_connector_route' );

		if ( empty( $route ) ) {
			return;
		}

		$allowed_shortcodes = [ 'whmcs_client_area', 'whmcs_pricing' ];

		if ( ! in_array( $route, $allowed_shortcodes, true ) ) {
			return;
		}

		// Set up a virtual page.
		global $wp_query;
		$wp_query->is_404  = false;
		$wp_query->is_page = true;

		// Render using the theme's page template.
		add_filter(
			'the_content',
			function () use ( $route ): string {
				return do_shortcode( '[' . $route . ']' );
			}
		);

		// Set the page title.
		$titles = [
			'whmcs_client_area' => __( 'Client Area', 'whmcs-connector' ),
			'whmcs_pricing'     => __( 'Pricing', 'whmcs-connector' ),
		];

		add_filter(
			'document_title_parts',
			function ( array $parts ) use ( $route, $titles ): array {
				$parts['title'] = $titles[ $route ] ?? '';
				return $parts;
			}
		);
	}

	/**
	 * Flush rewrite rules if flagged (on activation).
	 */
	public function maybe_flush_rules(): void {
		if ( get_option( 'whmcs_connector_flush_rewrite' ) ) {
			flush_rewrite_rules();
			delete_option( 'whmcs_connector_flush_rewrite' );
		}
	}
}
