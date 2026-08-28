/**
 * WHMCS Connector — Admin JavaScript
 *
 * Handles: test connection, clear cache, password toggle, cache TTL slider.
 * No jQuery dependency.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initTestConnection();
		initClearCache();
		initPasswordToggles();
		initCacheTtlSlider();
	} );

	/**
	 * Test Connection button handler.
	 */
	function initTestConnection() {
		const btn = document.getElementById( 'whmcs-connector-test-connection' );
		const result = document.getElementById( 'whmcs-connector-test-result' );

		if ( ! btn || ! result ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			result.className = 'whmcs-connector-test-result loading';
			result.textContent = whmcsConnectorAdmin.strings.testing;
			btn.disabled = true;

			fetch( whmcsConnectorAdmin.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams( {
					action: 'whmcs_connector_test_connection',
					_ajax_nonce: whmcsConnectorAdmin.testConnectionNonce,
				} ),
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					result.className =
						'whmcs-connector-test-result ' + ( data.success ? 'success' : 'error' );
					result.textContent = data.data ? data.data.message : whmcsConnectorAdmin.strings.error;
				} )
				.catch( function () {
					result.className = 'whmcs-connector-test-result error';
					result.textContent = whmcsConnectorAdmin.strings.error;
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	}

	/**
	 * Clear Cache button handler.
	 */
	function initClearCache() {
		const btn = document.getElementById( 'whmcs-connector-clear-cache' );
		const result = document.getElementById( 'whmcs-connector-cache-result' );

		if ( ! btn || ! result ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			result.className = 'whmcs-connector-test-result loading';
			result.textContent = whmcsConnectorAdmin.strings.clearing;
			btn.disabled = true;

			fetch( whmcsConnectorAdmin.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams( {
					action: 'whmcs_connector_clear_cache',
					_ajax_nonce: whmcsConnectorAdmin.clearCacheNonce,
				} ),
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					result.className =
						'whmcs-connector-test-result ' + ( data.success ? 'success' : 'error' );
					result.textContent = data.data
						? data.data.message
						: whmcsConnectorAdmin.strings.cleared;
				} )
				.catch( function () {
					result.className = 'whmcs-connector-test-result error';
					result.textContent = whmcsConnectorAdmin.strings.error;
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	}

	/**
	 * Password field show/hide toggles.
	 */
	function initPasswordToggles() {
		document.querySelectorAll( '.whmcs-connector-toggle-password' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const targetId = btn.getAttribute( 'data-target' );
				const input = document.getElementById( targetId );

				if ( ! input ) {
					return;
				}

				if ( input.type === 'password' ) {
					input.type = 'text';
					btn.querySelector( '.dashicons' ).className = 'dashicons dashicons-hidden';
				} else {
					input.type = 'password';
					btn.querySelector( '.dashicons' ).className = 'dashicons dashicons-visibility';
				}
			} );
		} );
	}

	/**
	 * Cache TTL range slider label update.
	 */
	function initCacheTtlSlider() {
		const slider = document.getElementById( 'cache_ttl' );
		const display = document.getElementById( 'cache_ttl_display' );

		if ( ! slider || ! display ) {
			return;
		}

		slider.addEventListener( 'input', function () {
			display.textContent = Math.round( slider.value / 60 );
		} );
	}
} )();
