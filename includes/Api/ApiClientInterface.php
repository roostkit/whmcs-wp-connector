<?php
/**
 * API client interface.
 *
 * @package RoostKit\WhmcsConnector\Api
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Api;

/**
 * Contract for WHMCS API adapters.
 *
 * Built as an interface so a future REST API adapter can be swapped in
 * without changing call sites throughout the plugin.
 */
interface ApiClientInterface {

	/**
	 * Execute a WHMCS API action.
	 *
	 * @param string               $action WHMCS API action name (e.g. 'GetProducts').
	 * @param array<string, mixed> $params Additional parameters for the action.
	 * @return array<string, mixed> Decoded API response.
	 *
	 * @throws ApiException On API error or communication failure.
	 */
	public function call( string $action, array $params = [] ): array;

	/**
	 * Test the API connection with a lightweight call.
	 *
	 * @return array{success: bool, message: string, data?: array<string, mixed>}
	 */
	public function test_connection(): array;
}
