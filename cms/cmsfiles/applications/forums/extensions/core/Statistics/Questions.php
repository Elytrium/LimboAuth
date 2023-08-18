<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Forums
 * @since		26 Jan 2023
 */

namespace IPS\forums\extensions\core\Statistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _Questions extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'forums_stats_questions';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart	= new \IPS\Helpers\Chart\Database( $url, 'forums_question_ratings', 'date', '', array( 
			'isStacked' => FALSE,
			'backgroundColor' 	=> '#ffffff',
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		) );
		$chart->setExtension( $this );
		
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_question_ratings_title');
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );

		$chart->groupBy = 'rating';
		
		foreach( array( 1, -1 ) as $v )
		{
			$label = ( $v == -1 ) ? 'negative' : 'positive';
			$chart->addSeries(  \IPS\Member::loggedIn()->language()->addToStack('stats_question_ratings_' . $label ), 'number', 'COUNT(*)', TRUE, $label );		
		}
		
		return $chart;
	}
}