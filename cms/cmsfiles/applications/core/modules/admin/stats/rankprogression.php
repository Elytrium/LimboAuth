<?php
/**
 * @brief		rankprogression
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		27 Jun 2022
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * rankprogression
 */
class _rankprogression extends \IPS\Dispatcher\Controller
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
		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'RankProgression' )->getChart( \IPS\Http\Url::internal( "app=core&module=stats&controller=rankprogression" ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_rankprogression');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'stats' )->rankprogressionmessage();
		\IPS\Output::i()->output .= $chart->render( 'ScatterChart', array(
			'is3D'	=> TRUE,
			'vAxis'	=> array( 'title' => \IPS\Member::loggedIn()->language()->addToStack("core_stats_rank_progression_v") ),
			'legend' => 'none'
		) );
	}
}