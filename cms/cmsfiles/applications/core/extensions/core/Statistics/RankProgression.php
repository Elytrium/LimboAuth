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
class _RankProgression extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_rankprogression';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart = new \IPS\Helpers\Chart;
		$chart->addHeader( "Rank", 'string' );
		$chart->addHeader( "Days", 'number' );

		$data = \IPS\Db::i()->select( 'new_rank, AVG(time_to_new_rank) as time_to_rank', 'core_points_log', array('time_to_new_rank IS NOT NULL'), 'core_member_ranks.points ASC', NULL, ['new_rank'] );
		$data->join( 'core_member_ranks', 'core_member_ranks.id = core_points_log.new_rank' );

		foreach ( $data as $row )
		{
			try
			{
				$rank = \IPS\core\Achievements\Rank::load( $row['new_rank'] );
			}
			catch ( \Exception $e )
			{
				continue;
			}
			$chart->addRow( array( $rank->_title, floor( $row['time_to_rank'] / 86400 ) ) );
		}
		
		return $chart;
	}
}