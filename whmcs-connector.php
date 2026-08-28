<?php
/**
 * WHMCS Connector Pro by RoostKit
 *
 * Connect your WHMCS installation to WordPress using the official WHMCS API.
 *
 * @package     RoostKit\WhmcsConnector
 * @author      Saleh Shojaei
 * @copyright   2024 RoostKit
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: WHMCS Connector by RoostKit
 * Plugin URI:  https://roostkit.site/whmcs-connector
 * Description: Connect your WHMCS installation to WordPress using the official WHMCS API. Display pricing, client login, and client area links natively.
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author:      RoostKit
 * Author URI:  https://roostkit.site
 * Text Domain: whmcs-connector
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

// Abort if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin edition.
 */
define( 'WHMCS_CONNECTOR_EDITION', 'free' );

/**
 * Plugin version — single source of truth.
 */
define( 'WHMCS_CONNECTOR_VERSION', '1.0.0' );

/**
 * Absolute path to this plugin's main file.
 */
define( 'WHMCS_CONNECTOR_FILE', __FILE__ );

/**
 * Absolute path to this plugin's directory (with trailing slash).
 */
define( 'WHMCS_CONNECTOR_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL to this plugin's directory (with trailing slash).
 */
define( 'WHMCS_CONNECTOR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader.
 */
$whmcs_connector_autoloader = WHMCS_CONNECTOR_DIR . 'vendor/autoload.php';
if ( file_exists( $whmcs_connector_autoloader ) ) {
	require_once $whmcs_connector_autoloader;
}

/**
 * Boot the plugin on `plugins_loaded` to ensure WordPress core is fully available.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		$plugin = \RoostKit\WhmcsConnector\Plugin::get_instance();
		$plugin->init();
	}
);

/**
 * Activation hook — flush rewrite rules and trigger one-time welcome redirect.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		// Set flag so rewrite rules are flushed on next init.
		update_option( 'whmcs_connector_flush_rewrite', true );

		// Set transient for one-time welcome redirect.
		set_transient( 'whmcs_connector_activation_redirect', true, 60 );
	}
);

/**
 * Deactivation hook — clean up rewrite rules.
 */
register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
		delete_option( 'whmcs_connector_flush_rewrite' );
	}
);
