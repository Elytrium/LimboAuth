<?php
/**
 * @brief		Safe GraphQL Exception (i.e. client can be informed of the error message)
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Dec 2015
 */

namespace IPS\Api\GraphQL;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

use GraphQL\Error\ClientAware;

/**
 * API Exception
 */
class _SafeException extends \Exception implements ClientAware
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
	public function __construct( $message, $code, $httpCode )
	{
		$this->exceptionCode = $code;
		return parent::__construct( $message, $httpCode );
	}

	public function getCategory()
	{
		return 'clienterror';
	}

	public function isClientSafe()
	{
		return true;
	}
}