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
class _Badges extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_badges_type';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart = new \IPS\Helpers\Chart\Database( $url, 'core_member_badges', 'datetime', '', array(
			'isStacked' => FALSE,
			'backgroundColor' => '#ffffff',
			'hAxis' => array('gridlines' => array('color' => '#f5f5f5')),
			'lineWidth' => 1,
			'areaOpacity' => 0.4
			),
			'AreaChart',
			'daily',
			array('start' => 0, 'end' => 0),
			array(),
			'type'
		);
		$chart->setExtension( $this );

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack( 'stats_badges_title' );
		$chart->availableTypes = array('AreaChart', 'ColumnChart', 'BarChart');
		$chart->enableHourly = FALSE;

		$chart->groupBy = 'badge';

		foreach ( \IPS\core\Achievements\Badge::roots() as $badge )
		{
			$chart->addSeries( $badge->_title, 'number', 'COUNT(*)', TRUE, $badge->id );
		}

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack( 'stats_badges_title' );
		$chart->availableTypes = array('AreaChart', 'ColumnChart', 'BarChart');
		$chart->showIntervals = TRUE;
		
		return $chart;
	}
}