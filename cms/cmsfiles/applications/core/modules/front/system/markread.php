<?php
/**
 * @brief		Mark site as read
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 May 2014
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Mark site as read
 */
class _markread extends \IPS\Dispatcher\Controller
{
	/**
	 * Mark site as read
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Member::loggedIn()->markAllAsRead();
		}
		
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			/* Don't redirect to an external domain unless explicitly requested, and don't redirect back to ACP */
			$redirectTo = \IPS\Request::i()->referrer( FALSE, FALSE, 'front' ) ?: \IPS\Http\Url::internal( '' );

			if ( $redirectTo === NULL )
			{
				$redirectTo = \IPS\Http\Url::internal( '' );
			}

			\IPS\Output::i()->redirect( $redirectTo, 'core_site_marked_as_read' );
		}
	}
}