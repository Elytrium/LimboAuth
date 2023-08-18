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
class _OnlineUsers extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_onlineusers';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		/* Determine minimum date */
		$minimumDate = NULL;

		if( \IPS\Settings::i()->stats_online_users_prune )
		{
			$minimumDate = \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->stats_online_users_prune . 'D' ) );
		}

		/* We can't retrieve any stats prior to the new tracking being implemented */
		try
		{
			$oldestLog = \IPS\Db::i()->select( 'MIN(time)', 'core_statistics', array( 'type=?', 'online_users' ) )->first();

			if( !$minimumDate OR $oldestLog < $minimumDate->getTimestamp() )
			{
				$minimumDate = \IPS\DateTime::ts( $oldestLog );
			}
		}
		catch( \UnderflowException $e )
		{
			/* We have nothing tracked, set minimum date to today */
			$minimumDate = \IPS\DateTime::create();
		}

		$chart = new \IPS\Helpers\Chart\Callback( 
			$url, 
			array( $this, 'getResults' ),
			'', 
			array( 
				'isStacked' => TRUE,
				'backgroundColor' 	=> '#ffffff',
				'colors'			=> array( '#10967e', '#ea7963' ),
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4
			), 
			'AreaChart', 
			'none',
			array( 'start' => \IPS\DateTime::ts( time() - ( 60 * 60 * 24 * 30 ) ), 'end' => \IPS\DateTime::create() ),
			'',
			$minimumDate
		);
		$chart->setExtension( $this );

		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('members'), 'number' );
		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('guests'), 'number' );

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_onlineusers_title');
		$chart->availableTypes	= array( 'AreaChart', 'ColumnChart', 'BarChart' );
		$chart->showIntervals	= FALSE;
		
		return $chart;
	}
	
	/**
	 * Fetch the results
	 *
	 * @param	\IPS\Helpers\Chart\Callback	$chart	Chart object
	 * @return	array
	 */
	public function getResults( $chart )
	{
		$where = array( array( 'type=?', 'online_users' ), array( "time>?", 0 ) );

		if ( $chart->start )
		{
			$where[] = array( "time>?", $chart->start->getTimestamp() );
		}
		if ( $chart->end )
		{
			$where[] = array( "time<?", $chart->end->getTimestamp() );
		}

		$results = array();

		foreach( \IPS\Db::i()->select( '*', 'core_statistics', $where, 'time ASC' ) as $row )
		{
			if( !isset( $results[ $row['time'] ] ) )
			{
				$results[ $row['time'] ] = array( 
					'time' => $row['time'], 
					\IPS\Member::loggedIn()->language()->get('members') => 0,
					\IPS\Member::loggedIn()->language()->get('guests') => 0
				);
			}

			if( $row['value_4'] == 'members' )
			{
				$results[ $row['time'] ][ \IPS\Member::loggedIn()->language()->get('members') ] = $row['value_1'];
			}

			if( $row['value_4'] == 'guests' )
			{
				$results[ $row['time'] ][ \IPS\Member::loggedIn()->language()->get('guests') ] = $row['value_1'];
			}
		}

		return $results;
	}
}