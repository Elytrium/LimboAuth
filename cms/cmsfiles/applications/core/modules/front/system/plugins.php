<?php
/**
 * @brief		A deliberately empty controller intended for use by plugins which need to add small actions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		09 Aug 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * A deliberately empty controller intended for use by plugins which need to add small actions
 */
class _plugins extends \IPS\Dispatcher\Controller
{
	/**
	 * This is only here to prevent a fatal error calling to manage from the dispatcher
	 *
	 * @return	void
	 */
	protected function manage()
	{
	}
}