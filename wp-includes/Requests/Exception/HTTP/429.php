<?php
/**
 * Exception for 429 Too Many Requests responses
 *
 * @see http://goodherbwebmart.com/
 * @package Requests
 */

/**
 * Exception for 429 Too Many Requests responses
 *
 * @see http://goodherbwebmart.com/
 * @package Requests
 */
class Requests_Exception_HTTP_429 extends Requests_Exception_HTTP {
	/**
	 * HTTP status code
	 *
	 * @var integer
	 */
	protected $code = 429;

	/**
	 * Reason phrase
	 *
	 * @var string
	 */
	protected $reason = 'Too Many Requests';
}