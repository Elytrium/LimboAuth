<?php
/**
 * @brief		marketplace
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		16 Jul 2020
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * marketplace
 */
class _marketplace extends \IPS\Dispatcher\Controller
{
	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( isset( \IPS\Request::i()->member ) )
		{
			try
			{
				$token = \IPS\Db::i()->select( 'token', 'core_marketplace_tokens', array( 'id=?', \IPS\Request::i()->member ) )->first();
				if ( $token !== 'pending-' . \IPS\Request::i()->hash )
				{
					throw new \UnderflowException;
				}

				\IPS\Db::i()->update( 'core_marketplace_tokens', array( 'token' => \IPS\Request::i()->access_token, 'expires_at' => time() + \IPS\Request::i()->expires_in ), array( 'id=?', \IPS\Request::i()->member ) );

				$response = 'OK';
				$responseCode = 200;
			}
			catch ( \UnderflowException $e )
			{
				$response = 'BAD_HASH';
				$responseCode = 403;
			}
		}
		else
		{
			$response = 'NO_MEMBER';
			$responseCode = 404;
		}

		\IPS\Output::i()->sendOutput( $response, $responseCode, 'text/plain' );
	}
}