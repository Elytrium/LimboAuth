<?php
/**
 * @brief		Messenger Stats
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 June 2013
 */

namespace IPS\core\modules\admin\messengerstats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Messenger Stats
 */
class _pmstats extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Manage Members
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'messages_manage', 'core', 'members' );
		
		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Conversations' )->getChart( \IPS\Http\Url::internal( 'app=core&module=messengerstats&controller=pmstats' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_messengerstats_pmstats');
		\IPS\Output::i()->output = (string) $chart;
	}
}