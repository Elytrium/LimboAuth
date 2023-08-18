<?php

/**
 * @brief        Verify Exception
 * @author        <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) Invision Power Services, Inc.
 * @license        https://www.invisioncommunity.com/legal/standards/
 * @package        Invision Community
 * @subpackage
 * @since        4/4/2023
 */

namespace IPS\MFA\Verify;

/* To prevent PHP errors (extending class does not exist) revealing path */
if( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Exception extends \DomainException
{
	const EXPIRED_CODE = 20404;

	public function __construct( string $message = "", int $code = 0, ?\Throwable $previous = null )
	{
		switch( $code )
		{
			case static::EXPIRED_CODE:
				$message = 'verify_mfa_reused_code';
				break;
		}

		parent::__construct( $message, $code, $previous );
	}
}