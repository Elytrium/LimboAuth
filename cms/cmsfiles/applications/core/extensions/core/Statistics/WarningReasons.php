<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/

 * @since		13 Dec 2022
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
class _WarningReasons extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_warnings_reason';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart	= new \IPS\Helpers\Chart\Database( $url, 'core_members_warn_logs', 'wl_date', '', array( 
			'isStacked' => TRUE,
			'backgroundColor' 	=> '#ffffff',
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		), 'LineChart', 'monthly', array( 'start' => 0, 'end' => 0 ), array( 'wl_reason', 'wl_date' ), 'warnings_reason' );
		$chart->setExtension( $this );

		$chart->groupBy = 'wl_reason';

		foreach( \IPS\core\Warnings\Reason::roots() as $reason )
		{
			$chart->addSeries(  \IPS\Member::loggedIn()->language()->addToStack('core_warn_reason_' . $reason->id ), 'number', 'COUNT(*)', TRUE, $reason->id );		
		}

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_warnings_title');
		$chart->availableTypes = array( 'LineChart', 'AreaChart', 'ColumnChart', 'BarChart' );
		$chart->extension = $this;
		
		$chart->tableParsers = array(
			'wl_date'	=> function( $val )
			{
				return (string) \IPS\DateTime::ts( $val );
			}
		);
		
		return $chart;
	}
}