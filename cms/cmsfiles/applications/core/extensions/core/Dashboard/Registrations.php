<?php
/**
 * @brief		Dashboard extension: Registrations
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Jul 2013
 */

namespace IPS\core\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Dashboard extension: Registrations
 */
class _Registrations
{
	/**
	* Can the current user view this dashboard item?
	*
	* @return	bool
	*/
	public function canView()
	{
		return \IPS\Member::loggedIn()->hasAcpRestriction( 'core' , 'members', 'registrations_manage' );
	}

	/**
	 * Return the block to show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		/* We can use the registration stats controller for this */
		/* Output */
		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Registrations' )->getChart( \IPS\Http\Url::internal( 'app=core&module=stats&controller=registrationstats' ) );
		$chart->showFilterTabs = FALSE;
		$chart->showIntervals = FALSE;
		$chart->showDateRange = FALSE;
		return  \IPS\Theme::i()->getTemplate( 'dashboard' )->registrations( $chart );;
	}
}