<?php
/**
 * PHPUnit bootstrap for Brain Monkey.
 *
 * @package RoostKit\WhmcsConnector\Tests
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WordPress constants used by the plugin.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'AUTH_KEY' ) ) {
	define( 'AUTH_KEY', 'test-auth-key-for-unit-tests-only' );
}

if ( ! defined( 'AUTH_SALT' ) ) {
	define( 'AUTH_SALT', 'test-auth-salt-for-unit-tests-only' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'WHMCS_CONNECTOR_VERSION' ) ) {
	define( 'WHMCS_CONNECTOR_VERSION', '1.0.0-test' );
}

if ( ! defined( 'WHMCS_CONNECTOR_EDITION' ) ) {
	define( 'WHMCS_CONNECTOR_EDITION', 'free' );
}

if ( ! defined( 'WHMCS_CONNECTOR_DIR' ) ) {
	define( 'WHMCS_CONNECTOR_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'WHMCS_CONNECTOR_URL' ) ) {
	define( 'WHMCS_CONNECTOR_URL', 'https://example.com/wp-content/plugins/whmcs-connector/' );
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params = [];
		public function __construct( string $method = 'GET', string $route = '' ) {}
		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
		public function get_status(): int {
			return $this->status;
		}
		public function get_data() {
			return $this->data;
		}
	}
}
