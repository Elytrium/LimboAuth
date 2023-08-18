<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/

 * @since		26 Jan 2023
 */

namespace IPS\core\extensions\core\Statistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _Points extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = NULL;
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart	= new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( 'app=core&module=stats&controller=points' ), 'core_points_log', 'datetime', '', array(
			'isStacked' => FALSE,
			'backgroundColor' 	=> '#ffffff',
			'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde' ),
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		), 'AreaChart', 'daily' );
		$chart->setExtension( $this );

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_points_title');
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );
		$chart->enableHourly	= FALSE;

		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('stats_member_points'), 'number', 'SUM(points)', TRUE );

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_points_title');
		$chart->availableTypes	= array( 'AreaChart', 'ColumnChart', 'BarChart' );
		$chart->showIntervals	= TRUE;
		
		return $chart;
	}
}