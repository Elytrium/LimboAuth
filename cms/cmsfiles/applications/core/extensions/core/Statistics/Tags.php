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
class _Tags extends \IPS\core\Statistics\Chart
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
		$chart = new \IPS\Helpers\Chart\Database( $url, 'core_tags', 'tag_added', '', array( 
				'isStacked'			=> FALSE,
				'backgroundColor' 	=> '#ffffff',
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4
			), 
			'AreaChart',
			'daily',
			array( 'start' => \IPS\DateTime::create()->sub( new \DateInterval( 'P1M' ) ), 'end' => 0 )
		);
		$chart->setExtension( $this );
		$chart->groupBy			= 'tag_text';
		$chart->availableTypes	= array( 'AreaChart', 'ColumnChart', 'BarChart', 'PieChart' );

		$where = $chart->where;
		$where[] = array( "tag_added>?", 0 );
		if ( $chart->start )
		{
			$where[] = array( "tag_added>?", $chart->start->getTimestamp() );
		}
		if ( $chart->end )
		{
			$where[] = array( "tag_added<?", $chart->end->getTimestamp() );
		}
		
		/* Only get visible tags */
		$where[] = array( 'tag_aai_lookup IN(?)', \IPS\Db::i()->select( 'tag_perm_aai_lookup', 'core_tags_perms', [ 'tag_perm_visible=1' ] ) );

		foreach( \IPS\Db::i()->select( 'tag_text', 'core_tags', $where, NULL, NULL, array( 'tag_text' ) ) as $tag )
		{
			$chart->addSeries( $tag, 'number', 'COUNT(*)', TRUE, $tag );
		}
		
		return $chart;
	}
}