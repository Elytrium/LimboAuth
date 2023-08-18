<?php
/**
 * @brief		points
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		10 Mar 2021
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * points
 */
class _points extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Allow MySQL RW separation for efficiency
	 */
	public static $allowRWSeparation = TRUE;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'overview_manage' );
		parent::execute();
	}

	/**
	 * Points earned activity chart
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Points' )->getChart( \IPS\Http\Url::internal( "app=core&module=stats&controller=points" ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_points');
		\IPS\Output::i()->output	= (string) $chart;
	}
}