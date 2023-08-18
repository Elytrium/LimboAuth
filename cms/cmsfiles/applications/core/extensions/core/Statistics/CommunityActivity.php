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
class _CommunityActivity extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_activitystats_communityactivity';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
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
			'weekly',
			array( 'start' => \IPS\DateTime::create()->sub( new \DateInterval( 'P90D' ) ), 'end' => \IPS\DateTime::ts( time() ) )
		);
		$chart->setExtension( $this );
		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('activity'), 'number', FALSE );
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('member_activity_timeperiod');
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );
		
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
		/* Get results */
		$results = array();
		
		if ( \IPS\Settings::i()->search_method == 'mysql' )
		{
			$results = array_replace_recursive( $results, $this->getSqlResults( 'core_search_index', 'index_date_updated', 'index_author', $chart ) );
		}
		else
		{
			foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter' ) as $contentRouter )
			{
				foreach ( $contentRouter->classes as $class )
				{
					if ( isset( $class::$databaseColumnMap['author'] ) )
					{
						$results = array_replace_recursive( $results, $this->getSqlResults(
							$class::$databaseTable,
							$class::$databasePrefix . ( isset( $class::$databaseColumnMap['updated'] ) ? $class::$databaseColumnMap['updated'] : $class::$databaseColumnMap['date'] ),
							$class::$databasePrefix . $class::$databaseColumnMap['author'],
							$chart
						) );
					}
				}
			}
		}
		$results = array_replace_recursive( $results, $this->getSqlResults( 'core_reputation_index', 'rep_date', 'member_id', $chart ) );
		$results = array_replace_recursive( $results, $this->getSqlResults( 'core_follow', 'follow_added', 'follow_member_id', $chart ) );

		/* Reformat now */
		$finalResults = array();

		foreach( $results as $date => $members )
		{
			$finalResults[ $date ] = array( 'time' => $date, \IPS\Member::loggedIn()->language()->get('activity') => \count( $members ) );
		}

		return $finalResults;
	}

	/**
	 * Get SQL query/results
	 *
	 * @note Consolidated to reduce duplicated code
	 * @param	string	$table	Database table
	 * @param	string	$date	Date column
	 * @param	string	$author	Author column
	 * @param	object	$chart	Chart
	 * @return	array
	 */
	protected function getSqlResults( $table, $date, $author, $chart )
	{
		/* What's our SQL time? */
		switch ( $chart->timescale )
		{
			case 'daily':
				$timescale = '%Y-%c-%e';
				break;
			
			case 'weekly':
				$timescale = '%x-%v';
				break;
				
			case 'monthly':
				$timescale = '%Y-%c';
				break;
		}

		$results	= array();
		$where		= array( array( "{$date}>?", 0 ) );
		$where[]	= array( "{$author}>?", 0 );
		if ( $chart->start )
		{
			$where[] = array( "{$date}>?", $chart->start->getTimestamp() );
		}
		if ( $chart->end )
		{
			$where[] = array( "{$date}<?", $chart->end->getTimestamp() );
		}

		/* First we need to get search index activity */
		$fromUnixTime = "FROM_UNIXTIME( IFNULL( {$date}, 0 ) )";
		if ( !$chart->timezoneError and \IPS\Member::loggedIn()->timezone and \in_array( \IPS\Member::loggedIn()->timezone, \IPS\DateTime::getTimezoneIdentifiers() ) )
		{
			$fromUnixTime = "CONVERT_TZ( {$fromUnixTime}, @@session.time_zone, '" . \IPS\Db::i()->escape_string( \IPS\Member::loggedIn()->timezone ) . "' )";
		}

		$stmt = \IPS\Db::i()->select( "DATE_FORMAT( {$fromUnixTime}, '{$timescale}' ) AS time, {$author}", $table, $where, 'time ASC', NULL, array( $author, 'time' ) );

		foreach( $stmt as $row )
		{
			$results[ $row['time'] ][ $row[ $author ] ] = 1;
		}

		return $results;
	}
}