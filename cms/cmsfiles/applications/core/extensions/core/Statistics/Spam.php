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
class _Spam extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_spam';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart	= new \IPS\Helpers\Chart\Database( $url, 'core_spam_service_log', 'log_date', '', array( 
			'isStacked' => TRUE,
			'backgroundColor' 	=> '#ffffff',
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		), 'LineChart', 'monthly', array( 'start' => 0, 'end' => 0 ), array( 'log_code', 'log_date' ), 'spam' );
		$chart->setExtension( $this );
		
		$chart->groupBy = 'log_code';

		foreach( array( 1,2,3,4 ) as $v )
		{
			$chart->addSeries(  \IPS\Member::loggedIn()->language()->addToStack('spam_service_action_stats_' . $v ), 'number', 'COUNT(*)', TRUE, $v );		
		}

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_spam_title');
		$chart->availableTypes = array( 'LineChart', 'AreaChart', 'ColumnChart', 'BarChart' );

		$chart->tableParsers = array(
			'log_date'	=> function( $val )
			{
				return (string) \IPS\DateTime::ts( $val );
			}
		);
		
		return $chart;
	}
}