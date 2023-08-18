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
class _ReactionsApp extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_activitystats_reactions_type';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart = new \IPS\Helpers\Chart\Database( $url, 'core_reputation_index', 'rep_date', '', array( 
				'isStacked'			=> FALSE,
				'backgroundColor' 	=> '#ffffff',
				'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde', '#b09be4', '#eec766', '#9fc973', '#e291bf', '#55c1a6', '#5fb9da' ),
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4
			), 
			'PieChart',
			'monthly',
			array( 'start' => 0, 'end' => 0 ),
			array(),
			'app'
		);
		$chart->setExtension( $this );
		
		$chart->groupBy = 'app';

		foreach( \IPS\Application::roots() as $app )
		{
			$chart->addSeries( $app->_title, 'number', 'COUNT(*)', TRUE, $app->directory );
		}
		
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart', 'PieChart' );
		
		return $chart;
	}
}