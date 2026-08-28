<?php
/**
 * Custom exception for WHMCS API errors.
 *
 * @package RoostKit\WhmcsConnector\Api
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Api;

/**
 * Thrown when a WHMCS API call fails.
 */
class ApiException extends \RuntimeException {

	/**
	 * The WHMCS API error message, if available.
	 *
	 * @var string
	 */
	private string $api_message;

	/**
	 * Constructor.
	 *
	 * @param string          $message     Exception message.
	 * @param string          $api_message Raw WHMCS API error message.
	 * @param int             $code        Exception code.
	 * @param \Throwable|null $previous    Previous exception.
	 */
	public function __construct(
		string $message = '',
		string $api_message = '',
		int $code = 0,
		?\Throwable $previous = null
	) {
		$this->api_message = $api_message;
		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Get the raw WHMCS API error message.
	 *
	 * @return string
	 */
	public function get_api_message(): string {
		return $this->api_message;
	}

	/**
	 * Get the HTTP status code or error code.
	 *
	 * @return int
	 */
	public function get_http_status(): int {
		return $this->getCode();
	}
}
