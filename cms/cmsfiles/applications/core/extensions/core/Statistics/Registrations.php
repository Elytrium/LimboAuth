<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/

 * @since		24 Jan 2023
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
class _Registrations extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_registrationstats';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart	= new \IPS\Helpers\Chart\Database( $url, 'core_members', 'joined', '', array( 
			'isStacked' => FALSE,
			'backgroundColor' 	=> '#ffffff',
			'colors'			=> array( '#10967e' ),
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		 ) );
		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('stats_new_registrations'), 'number', 'COUNT(*)', FALSE );
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_registrations_title');
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );
		$chart->setExtension( $this );

		/* fetch only successful registered members ; if this needs to be changed, please review the other areas where we have the name<>? AND email<>? condition */
		$chart->where[] = array( 'completed=?', true );
		
		return $chart;
	}
}