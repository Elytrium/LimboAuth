<?php
/**
 * @brief		API Exception
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Dec 2015
 */

namespace IPS\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * API Exception
 */
class _Exception extends \Exception
{
	/**
	 * @brief	Exception code
	 */
	public $exceptionCode;
	
	/**
	 * @brief	OAUth Error
	 */
	public $oauthError;
	
	/**
	 * Constructor
	 *
	 * @param	string	$message	Error Message
	 * @param	string	$code		Code
	 * @param	int		$httpCode	HTTP Error code
	 * @param	string	$oauthError	Error Message for OAuth
	 * @return	void
	 */
	public function __construct( $message, $code, $httpCode, $oauthError = 'invalid_request' )
	{
		$this->exceptionCode = $code;
		$this->oauthError = $oauthError;
		return parent::__construct( $message, $httpCode );
	}
}