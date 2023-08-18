<?php
/**
 * @brief		Legacy 3.x findpost
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		09 Jul 2015
 */

namespace IPS\forums\modules\front\forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Legacy 3.x findpost
 */
class _findpost extends \IPS\Dispatcher\Controller
{
	/**
	 * Route
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		try
		{
			\IPS\Output::i()->redirect( \IPS\forums\Topic\Post::loadAndCheckPerms( \IPS\Request::i()->pid )->url(), NULL, 301 );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F284/1', 404, '' );
		}
	}
}