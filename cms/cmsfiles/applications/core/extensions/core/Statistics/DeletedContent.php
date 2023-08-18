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
class _DeletedContent extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_activitystats_deletedcontent';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart	= new \IPS\Helpers\Chart\Database( $url, 'core_deletion_log', 'dellog_deleted_date', '', array( 
			'isStacked' => TRUE,
			'backgroundColor' 	=> '#ffffff',
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		 ), 'LineChart', 'monthly', array( 'start' => 0, 'end' => 0 ), array( 'dellog_content_class', 'dellog_deleted_date' ), 'deletedcontent' );
		 $chart->setExtension( $this );
		
		$chart->groupBy = 'dellog_content_class';

		$types = \IPS\Db::i()->select( 'DISTINCT(dellog_content_class)', 'core_deletion_log' );
		
		foreach( $types as $class )
		{
			$lang = $class::$title;
			$chart->addSeries(  \IPS\Member::loggedIn()->language()->addToStack( $lang ), 'number', 'COUNT(*)', TRUE, $class );		
		}

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_deletedcontent_title');
		$chart->availableTypes = array( 'LineChart', 'AreaChart', 'ColumnChart', 'BarChart' );

		$chart->tableParsers = array(
			'dellog_deleted_date'	=> function( $val )
			{
				return (string) \IPS\DateTime::ts( $val );
			}
		);
		
		return $chart;
	}
}