<?php
/**
 * @brief		Offline page output
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Feb 2021
 */
 
namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Offline page Controller
 */
class _offline extends \IPS\Dispatcher\Controller
{	
	/**
	 * View Notifications
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->metaTags['robots'] = 'noindex';
        \IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->offline(), 200, 'text/html' );
    }
}