<?php
/**
 * REST API controller for WHMCS product queries in Gutenberg block editor.
 *
 * @package RoostKit\WhmcsConnector\Api
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Api;

use RoostKit\WhmcsConnector\ProductRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles `/wp-json/whmcs-connector/v1/products` and `/product-groups` requests.
 */
class ProductsController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'whmcs-connector/v1';

	/**
	 * Products REST route.
	 *
	 * @var string
	 */
	public const PRODUCTS_ROUTE = '/products';

	/**
	 * Product groups REST route.
	 *
	 * @var string
	 */
	public const GROUPS_ROUTE = '/product-groups';

	/**
	 * Product repository instance.
	 *
	 * @var ProductRepository|null
	 */
	private ?ProductRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param ProductRepository|null $repository Product repository.
	 */
	public function __construct( ?ProductRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register REST routes on rest_api_init.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::PRODUCTS_ROUTE,
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'handle_get_products' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'gid'    => [
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'pid'    => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'search' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::GROUPS_ROUTE,
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'handle_get_groups' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);
	}

	/**
	 * Permission check for block editor queries.
	 *
	 * @return bool True if authorized.
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Handle get products request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function handle_get_products( WP_REST_Request $request ): WP_REST_Response {
		if ( null === $this->repository ) {
			return new WP_REST_Response(
				[
					'success'  => false,
					'message'  => __( 'Product repository not initialized.', 'whmcs-connector' ),
					'products' => [],
				],
				500
			);
		}

		$gid    = (int) ( $request->get_param( 'gid' ) ?? 0 );
		$pid    = (string) ( $request->get_param( 'pid' ) ?? '' );
		$search = strtolower( trim( (string) ( $request->get_param( 'search' ) ?? '' ) ) );

		$raw_products = $this->repository->get_products( $gid > 0 ? $gid : '', $pid );
		if ( null === $raw_products ) {
			return new WP_REST_Response(
				[
					'success'  => false,
					'message'  => __( 'Failed to fetch products from WHMCS.', 'whmcs-connector' ),
					'products' => [],
				],
				502
			);
		}

		$formatted = [];
		foreach ( $raw_products as $prod ) {
			$prod_pid  = (int) ( $prod['pid'] ?? 0 );
			$prod_name = (string) ( $prod['name'] ?? '' );
			$prod_desc = (string) ( $prod['description'] ?? '' );
			$prod_gid  = (int) ( $prod['gid'] ?? 0 );

			if ( ! empty( $search ) ) {
				if ( ! str_contains( strtolower( $prod_name ), $search ) &&
					! str_contains( strtolower( $prod_desc ), $search ) &&
					! str_contains( (string) $prod_pid, $search ) ) {
					continue;
				}
			}

			$pricing  = is_array( $prod['pricing'] ?? null ) ? $prod['pricing'] : [];
			$rates    = $this->repository->get_default_currency_pricing( $pricing );
			$cycles   = $this->repository->get_available_cycles( $pricing );
			$currency = $this->repository->get_currency_symbol( $pricing );
			$features = $this->repository->extract_features_from_description( $prod_desc );
			$tagline  = $this->repository->extract_tagline_from_description( $prod_desc );

			$formatted_pricing = [];
			$savings_map       = [];

			foreach ( $cycles as $cycle ) {
				if ( isset( $rates[ $cycle ] ) ) {
					$val                         = (float) $rates[ $cycle ];
					$formatted_pricing[ $cycle ] = $this->repository->format_price( $val, $pricing );
					$savings                     = $this->repository->compute_savings( $pricing, $cycle );
					if ( $savings > 0 ) {
						$savings_map[ $cycle ] = $savings;
					}
				}
			}

			$formatted[] = [
				'pid'              => $prod_pid,
				'gid'              => $prod_gid,
				'name'             => $prod_name,
				'description'      => $prod_desc,
				'tagline'          => $tagline,
				'currency_symbol'  => $currency,
				'available_cycles' => $cycles,
				'pricing'          => $formatted_pricing,
				'raw_pricing'      => $rates,
				'savings'          => $savings_map,
				'features'         => $features,
			];
		}

		return new WP_REST_Response(
			[
				'success'  => true,
				'products' => $formatted,
			],
			200
		);
	}

	/**
	 * Handle get product groups request.
	 *
	 * @return WP_REST_Response Response.
	 */
	public function handle_get_groups(): WP_REST_Response {
		if ( null === $this->repository ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'groups'  => [],
				],
				500
			);
		}

		$groups = $this->repository->get_product_groups();
		return new WP_REST_Response(
			[
				'success' => true,
				'groups'  => $groups,
			],
			200
		);
	}
}
