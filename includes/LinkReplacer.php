<?php
/**
 * Dynamic link replacer for Gutenberg and Page Builder button integration.
 *
 * @package RoostKit\WhmcsConnector
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector;

/**
 * Replaces `#whmcs-order-{PID}` and `#whmcs-order-{PID}-{CYCLE}` hash links
 * in post content and block renders with dynamic WHMCS checkout URLs.
 */
class LinkReplacer {

	/**
	 * WHMCS installation URL (no trailing slash).
	 *
	 * @var string
	 */
	private string $whmcs_url;

	/**
	 * Recognized billing cycles.
	 *
	 * @var array<string>
	 */
	private const VALID_CYCLES = [
		'monthly',
		'quarterly',
		'semiannually',
		'annually',
		'biennially',
		'triennially',
	];

	/**
	 * Recognized portal shortcuts.
	 *
	 * @var array<string, string>
	 */
	private const PORTAL_SHORTCUTS = [
		'clientarea'    => '/clientarea.php',
		'tickets'       => '/supporttickets.php',
		'invoices'      => '/clientarea.php?action=invoices',
		'knowledgebase' => '/knowledgebase.php',
	];

	/**
	 * Constructor.
	 *
	 * @param string $whmcs_url Configured WHMCS base URL.
	 */
	public function __construct( string $whmcs_url ) {
		$this->whmcs_url = untrailingslashit( $whmcs_url );
	}

	/**
	 * Register filters for content and block rendering.
	 */
	public function register(): void {
		add_filter( 'the_content', [ $this, 'replace_links' ], 20 );
		add_filter( 'widget_text', [ $this, 'replace_links' ], 20 );
		add_filter( 'render_block', [ $this, 'replace_block_links' ], 20 );
	}

	/**
	 * Filter callback for render_block.
	 *
	 * @param string $block_content The block content.
	 * @return string Modified block content.
	 */
	public function replace_block_links( string $block_content ): string {
		return $this->replace_links( $block_content );
	}

	/**
	 * Replace `#whmcs-...` hash anchors inside href attributes with WHMCS URLs.
	 *
	 * @param string $content Input HTML content.
	 * @return string Processed content.
	 */
	public function replace_links( string $content ): string {
		if ( empty( $content ) || false === strpos( $content, '#whmcs-' ) ) {
			return $content;
		}

		if ( empty( $this->whmcs_url ) ) {
			return $content;
		}

		// 1. Replace order links: #whmcs-order-{PID} or #whmcs-order-{PID}-{CYCLE} (Pro only).
		if ( Plugin::is_pro() ) {
			$pattern_order = '/(href=["\'])(#whmcs-order-([0-9]+)(?:-([a-zA-Z]+))?)(["\'])/i';
			$content       = (string) preg_replace_callback(
				$pattern_order,
				function ( array $matches ): string {
					$prefix  = $matches[1];
					$pid     = (int) $matches[3];
					$raw_cyc = strtolower( $matches[4] ?? '' );
					$quote   = $matches[5];

					if ( $pid <= 0 ) {
						return $matches[0];
					}

					$url = $this->whmcs_url . '/cart.php?a=add&pid=' . $pid;

					if ( ! empty( $raw_cyc ) && in_array( $raw_cyc, self::VALID_CYCLES, true ) ) {
						$url .= '&billingcycle=' . $raw_cyc;
					}

					return $prefix . esc_url( $url ) . $quote;
				},
				$content
			);
		}

		// 2. Replace portal shortcut links: #whmcs-{shortcut}.
		$pattern_portal = '/(href=["\'])(#whmcs-(clientarea|tickets|invoices|knowledgebase))(["\'])/i';
		$content        = (string) preg_replace_callback(
			$pattern_portal,
			function ( array $matches ): string {
				$prefix   = $matches[1];
				$shortcut = strtolower( $matches[3] );
				$quote    = $matches[4];

				if ( isset( self::PORTAL_SHORTCUTS[ $shortcut ] ) ) {
					$url = $this->whmcs_url . self::PORTAL_SHORTCUTS[ $shortcut ];
					return $prefix . esc_url( $url ) . $quote;
				}

				return $matches[0];
			},
			$content
		);

		return $content;
	}
}
