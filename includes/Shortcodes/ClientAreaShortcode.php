<?php
/**
 * Client Area shortcode.
 *
 * @package RoostKit\WhmcsConnector\Shortcodes
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Shortcodes;

/**
 * [whmcs_client_area] — Renders quick-links to the WHMCS client area.
 *
 * Version 1.0 behavior (no SSO): shows a card with links to the WHMCS install.
 * True embedded client data requires Phase 2 SSO.
 */
final class ClientAreaShortcode extends AbstractShortcode {

	/**
	 * Default quick-links.
	 *
	 * @var array<string, array{label: string, path: string}>
	 */
	private const DEFAULT_LINKS = [
		'clientarea'    => [
			'label' => 'Client Area',
			'path'  => 'clientarea.php',
		],
		'tickets'       => [
			'label' => 'Support Tickets',
			'path'  => 'supporttickets.php',
		],
		'invoices'      => [
			'label' => 'Invoices',
			'path'  => 'clientarea.php?action=invoices',
		],
		'knowledgebase' => [
			'label' => 'Knowledgebase',
			'path'  => 'knowledgebase.php',
		],
	];

	/**
	 * Constructor.
	 *
	 * @param string $whmcs_url WHMCS installation URL.
	 */
	public function __construct( string $whmcs_url ) {
		$this->whmcs_url = untrailingslashit( $whmcs_url );
	}

	/**
	 * Register the shortcode.
	 */
	public function register(): void {
		add_shortcode( 'whmcs_client_area', [ $this, 'render' ] );
	}

	/**
	 * Render the client area card.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'class'      => '',
				'show_links' => '', // Comma-separated: clientarea,tickets,invoices,knowledgebase.
			],
			$atts,
			'whmcs_client_area'
		);

		$this->enqueue_frontend_css();

		if ( empty( $this->whmcs_url ) ) {
			return $this->render_error();
		}

		$links       = $this->get_visible_links( $atts['show_links'] );
		$extra_class = ! empty( $atts['class'] ) ? ' ' . sanitize_html_class( $atts['class'] ) : '';

		ob_start();
		?>
		<div class="whmcs-connector-client-area<?php echo esc_attr( $extra_class ); ?>">
			<h3 class="whmcs-connector-client-area-title">
				<?php esc_html_e( 'Manage your account', 'whmcs-connector' ); ?>
			</h3>

			<div class="whmcs-connector-client-area-links">
				<?php foreach ( $links as $link ) : ?>
					<a href="<?php echo esc_url( $this->build_whmcs_url( $link['path'] ) ); ?>"
						class="whmcs-connector-client-area-link"
						target="_blank" rel="noopener">
						<span class="whmcs-connector-link-label"><?php echo esc_html( $link['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Get the list of visible links based on the show_links attribute.
	 *
	 * @param string $show_links Comma-separated link keys.
	 * @return array<int, array{label: string, path: string}>
	 */
	private function get_visible_links( string $show_links ): array {
		$all_links = self::DEFAULT_LINKS;

		// Translate labels for i18n.
		$all_links['clientarea']['label']    = __( 'Client Area', 'whmcs-connector' );
		$all_links['tickets']['label']       = __( 'Support Tickets', 'whmcs-connector' );
		$all_links['invoices']['label']      = __( 'Invoices', 'whmcs-connector' );
		$all_links['knowledgebase']['label'] = __( 'Knowledgebase', 'whmcs-connector' );

		if ( empty( $show_links ) ) {
			return array_values( $all_links );
		}

		$keys   = array_map( 'trim', explode( ',', $show_links ) );
		$result = [];

		foreach ( $keys as $key ) {
			if ( isset( $all_links[ $key ] ) ) {
				$result[] = $all_links[ $key ];
			}
		}

		return $result;
	}
}
