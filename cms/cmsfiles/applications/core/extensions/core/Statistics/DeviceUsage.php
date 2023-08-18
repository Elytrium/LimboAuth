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
class _DeviceUsage extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_deviceusage';
	
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

		if( \IPS\Settings::i()->stats_device_usage_prune )
		{
			$minimumDate = \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->stats_device_usage_prune . 'D' ) );
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

		$chart	= new \IPS\Helpers\Chart\Database( $url, 'core_statistics', 'time', '', array( 
			'isStacked' => FALSE,
			'backgroundColor' 	=> '#ffffff',
			'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde' ),
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		 ), 'AreaChart', 'hourly' );
		 $chart->setExtension( $this );

		$chart->where[]	= array( 'type=?', 'devices' );
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_deviceusage_title');
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );
		$chart->enableHourly	= TRUE;

		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('stats_devices_mobiles'), 'number', 'SUM(value_1)', TRUE );
		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('stats_devices_tablets'), 'number', 'SUM(value_2)', TRUE );
		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('stats_devices_consoles'), 'number', 'SUM(value_3)', TRUE );
		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('stats_devices_desktops'), 'number', 'SUM(value_4)', TRUE );
		
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