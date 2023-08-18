<?php
/**
 * @brief		Authy API Exception
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 March 2017
 */

namespace IPS\MFA\Authy;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Authy API Exception
 */
class _Exception extends \DomainException
{
	/**
	 * @brief	Token reused
	 */
	const TOKEN_REUSED = 60019;

	/**
	 * @brief	Token invalid
	 */
	const TOKEN_INVALID = 60020;

	/**
	 * @brief	User invalid
	 */
	const USER_INVALID = 60027;

	/**
	 * @brief	Phone number invalid
	 */
	const PHONE_NUMBER_INVALID = 60033;
	
	/**
	 * Get user-friendly error message
	 *
	 * @return	string
	 */
	public function getUserMessage()
	{
		switch ( $this->getCode() )
		{
			case static::USER_INVALID:
			case static::PHONE_NUMBER_INVALID:
				return 'authy_mfa_invalid_number';
				
			case static::TOKEN_REUSED:
				return 'authy_mfa_reused_code';
				
			case static::TOKEN_INVALID:
				return 'authy_mfa_invalid_code';
				
			default:
				return 'authy_generic_error';
		}
	}
}