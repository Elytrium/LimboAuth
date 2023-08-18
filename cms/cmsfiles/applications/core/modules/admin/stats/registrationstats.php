<?php
/**
 * @brief		Registration Stats
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 June 2013
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Registration Stats
 */
class _registrationstats extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * @brief	Allow MySQL RW separation for efficiency
	 */
	public static $allowRWSeparation = TRUE;
	
	/**
	 * Manage Members
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'registrations_manage', 'core', 'stats' );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_registrationstats');
		\IPS\Output::i()->output = (string) \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Registrations' )->getChart( \IPS\Http\Url::internal( 'app=core&module=stats&controller=registrationstats' ) );
	}
}
