<?php
/**
 * @brief		Statistics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 Dec 2019
 */

namespace IPS\core\modules\admin\clubs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics
 */
class _stats extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'stats_manage' );
		parent::execute();
	}

	/**
	 * Display the statistics
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* If clubs are not enabled, stop now */
		if ( !\IPS\Settings::i()->clubs )
		{
			$availableTypes = array();
			foreach ( \IPS\Member\Club::availableNodeTypes( NULL ) as $class )
			{
				$availableTypes[] = \IPS\Member::loggedIn()->language()->addToStack( $class::clubAcpTitle() );
			}
			
			$availableTypes = \IPS\Member::loggedIn()->language()->formatList( $availableTypes );
			
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'clubs' )->disabled( $availableTypes );
			return;
		}

		/* Generate the tabs */
		$tabs		= array( 'overview' => 'overview', 'total' => 'stats_club_activity', 'byclub' => 'stats_club_club_activity' );
		$activeTab	= ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'overview';

		/* Get the HTML to output */
		$method = '_' . $activeTab;
		$output = $this->$method();

		/* And then print it */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) $output;
		}
		else
		{	
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_clubs_stats');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string) $output, \IPS\Http\Url::internal( "app=core&module=clubs&controller=stats" ) );
		}
	}

	/**
	 * Club activity overview
	 *
	 * @return	string
	 */
	protected function _overview()
	{
		$clubTypePieChart	= $this->_getClubTypePieChart();
		$clubSignups		= $this->_getClubSignups();
		$clubCreations		= $this->_getClubCreations();

		return \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->statsOverview( $clubTypePieChart, $clubSignups, $clubCreations );
	}

	/**
	 * Get line chart showing club signups
	 *
	 * @return \IPS\Helpers\Chart
	 */
	protected function _getClubSignups()
	{
		$chart	= new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=stats&fetch=signups' ), 'core_clubs_memberships', 'joined', '', array( 
			'isStacked' => TRUE,
			'backgroundColor' 	=> '#ffffff',
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		 ), 'ColumnChart', 'monthly', array( 'start' => 0, 'end' => 0 ), array( 'club_id', 'name' ), 'signups' );
		
		$chart->joins[] = array( 'core_clubs', 'core_clubs.id=core_clubs_memberships.club_id' );
		$chart->groupBy = 'club_id';

		foreach( \IPS\Member\Club::clubs( NULL, NULL, 'name' ) as $club )
		{
			$chart->addSeries( $club->name, 'number', 'COUNT(*)', TRUE, $club->id );
		}

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_clubs_signups');
		$chart->availableTypes = array( 'ColumnChart', 'BarChart' );

		if( \IPS\Request::i()->fetch == 'signups' AND \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( (string) $chart, 200 );
		}

		return $chart;
	}

	/**
	 * Get line chart showing club creations
	 *
	 * @return \IPS\Helpers\Chart
	 */
	protected function _getClubCreations()
	{
		$chart	= new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=stats&fetch=clubs' ), 'core_clubs', 'created', '', array( 
			'isStacked' => FALSE,
			'backgroundColor' 	=> '#ffffff',
			'colors'			=> array( '#10967e' ),
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		 ), 'ColumnChart', 'monthly', array( 'start' => 0, 'end' => 0 ), array(), 'signups' );
		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('stats_clubs_creations'), 'number', 'COUNT(*)', FALSE );
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_clubs_creations');
		$chart->availableTypes = array( 'ColumnChart', 'BarChart' );

		if( \IPS\Request::i()->fetch == 'clubs' AND \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( (string) $chart, 200 );
		}

		return $chart;
	}

	/**
	 * Get pie chart showing club types
	 *
	 * @return \IPS\Helpers\Chart
	 */
	protected function _getClubTypePieChart()
	{
		$percentages	= array();
		$counts			= array();
		$total			= 0;

		foreach( \IPS\Db::i()->select( 'type, COUNT(*) as total', 'core_clubs', array(), NULL, NULL, array( 'type' ) ) as $clubs )
		{
			$counts[ $clubs['type'] ] = $clubs['total'];
			$total += $clubs['total'];
		}

		foreach( $counts as $type => $typeTotal )
		{
			$percentages[ $type ] = number_format( round( 100 / $total * $typeTotal, 2 ), 2 );
		}

		return \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->clubTypesBar( $percentages, $counts );
	}

	/**
	 * Total club activity
	 *
	 * @return	\IPS\Helpers\Chart\Dynamic
	 */
	protected function _total()
	{
		$chart = new \IPS\Helpers\Chart\Callback( 
			\IPS\Http\Url::internal( 'app=core&module=clubs&controller=stats&tab=total' ), 
			array( $this, 'getResults' ),
			'', 
			array( 
				'isStacked' => TRUE,
				'backgroundColor' 	=> '#ffffff',
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4
			), 
			'ColumnChart', 
			'monthly',
			array( 'start' => ( new \IPS\DateTime )->sub( new \DateInterval('P6M') ), 'end' => \IPS\DateTime::create() ),
			'total'
		);

		foreach( \IPS\Member\Club::availableNodeTypes( NULL ) as $nodeType )
		{
			$contentClass = $nodeType::$contentItemClass;
			$chart->addSeries( \IPS\Member::loggedIn()->language()->get( $contentClass::$title . '_pl' ), 'number', TRUE );
		}

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack( 'stats_club_activity' );
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );

		return $chart;
	}

	/**
	 * Activity by club
	 *
	 * @return	string
	 */
	protected function _byclub()
	{
		$chart = new \IPS\Helpers\Chart\Callback( 
			\IPS\Http\Url::internal( 'app=core&module=clubs&controller=stats&tab=byclub' ), 
			array( $this, 'getResults' ),
			'', 
			array( 
				'isStacked' => TRUE,
				'backgroundColor' 	=> '#ffffff',
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4
			), 
			'ColumnChart', 
			'monthly',
			array( 'start' => ( new \IPS\DateTime )->sub( new \DateInterval('P6M') ), 'end' => \IPS\DateTime::create() ),
			'byclub'
		);

		foreach( \IPS\Member\Club::clubs( NULL, NULL, 'name' ) as $club )
		{
			$chart->addSeries( $club->name, 'number', TRUE );
		}

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack( 'stats_club_club_activity' );
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
		/* Get the info we need */
		$nodeTypes	= \IPS\Member\Club::availableNodeTypes( NULL );
		$clubNodes	= array();
		$classes	= array();
		$classMap	= array();
		$results	= array();

		foreach( $nodeTypes as $nodeType )
		{
			$clubNodes[ $nodeType ]	= $nodeType::clubNodes( NULL );
			$itemClass	= $nodeType::$contentItemClass;
			$classes[]	= $itemClass;

			$classMap[ $itemClass ]	= $nodeType;

			if( isset( $itemClass::$commentClass ) )
			{
				$classes[] = $itemClass::$commentClass;
				$classMap[ $itemClass::$commentClass ]	= $nodeType;
			}

			if( isset( $itemClass::$reviewClass ) )
			{
				$classes[] = $itemClass::$reviewClass;
				$classMap[ $itemClass::$reviewClass ]	= $nodeType;
			}
		}

		/* If we are fetching by club, we need to build a map of containers to clubs */
		if( mb_strpos( $chart->identifier, 'byclub' ) !== FALSE )
		{
			$clubNodeMap = array();

			foreach( \IPS\Db::i()->select( '*', 'core_clubs_node_map' ) as $nodeMap )
			{
				$nodeClass = $nodeMap['node_class'];
				$nodeClass = $nodeClass::$contentItemClass;
				$clubNodeMap[ $nodeClass::$databaseTable . '-' . $nodeMap['node_id'] ] = $nodeMap['club_id'];

				if( isset( $nodeClass::$commentClass ) )
				{
					$commentClass = $nodeClass::$commentClass;
					$clubNodeMap[ $commentClass::$databaseTable . '-' . $nodeMap['node_id'] ] = $nodeMap['club_id'];
				}

				if( isset( $nodeClass::$reviewClass ) )
				{
					$reviewClass = $nodeClass::$reviewClass;
					$clubNodeMap[ $reviewClass::$databaseTable . '-' . $nodeMap['node_id'] ] = $nodeMap['club_id'];
				}
			}
		}
		else
		{
			$groupByContainer = FALSE;
		}

		/* Get results */
		foreach ( $classes as $class )
		{
			$where	= array();
			$join	= NULL;

			if( is_subclass_of( $class, '\IPS\Content\Comment' ) )
			{
				/* We're going to need a subquery... */
				$parentClass = $class::$itemClass;

				$where[] = array( 
					$class::$databasePrefix . $class::$databaseColumnMap['item'] . " IN(" .					
					(string) \IPS\Db::i()->select( $parentClass::$databasePrefix . $parentClass::$databaseColumnId, $parentClass::$databaseTable, array( \IPS\Db::i()->in( $parentClass::$databasePrefix . $parentClass::$databaseColumnMap['container'], array_keys( $clubNodes[ $classMap[ $class ] ] ) ) ) )
					. ")"
				);

				if( mb_strpos( $chart->identifier, 'byclub' ) !== FALSE )
				{
					$groupByContainer = $parentClass::$databaseTable . '.' . $parentClass::$databasePrefix . $parentClass::$databaseColumnMap['container'];
					$join = array( $parentClass::$databaseTable, $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['item'] . '=' . $parentClass::$databaseTable . '.' . $parentClass::$databasePrefix . $parentClass::$databaseColumnId );
				}

				/* We need to account for topic + first post not counting as two posts */
				if( $parentClass::$firstCommentRequired === TRUE AND isset( $class::$databaseColumnMap['first'] ) )
				{
					$where[] = array( $class::$databasePrefix . $class::$databaseColumnMap['first'] . '=?', 0 );
				}
			}
			else
			{
				$where[] = array( \IPS\Db::i()->in( $class::$databasePrefix . $class::$databaseColumnMap['container'], array_keys( $clubNodes[ $classMap[ $class ] ] ) ) );

				if( mb_strpos( $chart->identifier, 'byclub' ) !== FALSE )
				{
					$groupByContainer = $class::$databasePrefix . $class::$databaseColumnMap['container'];
				}
			}

			$stmt = $this->getSqlResults(
				$class::$databaseTable,
				$class::$databasePrefix . ( isset( $class::$databaseColumnMap['updated'] ) ? $class::$databaseColumnMap['updated'] : $class::$databaseColumnMap['date'] ),
				$class::$databasePrefix . $class::$databaseColumnMap['author'],
				$chart,
				$where,
				$groupByContainer,
				$join
			);

			foreach( $stmt as $row )
			{
				if( !isset( $results[ $row['time'] ] ) )
				{
					$results[ $row['time'] ] = array( 
						'time' => $row['time']
					);

					if( mb_strpos( $chart->identifier, 'byclub' ) !== FALSE )
					{
						foreach( \IPS\Member\Club::clubs( NULL, NULL, 'name' ) as $club )
						{
							$results[ $row['time'] ][ $club->name ] = 0;
						}
					}
					else
					{
						foreach( \IPS\Member\Club::availableNodeTypes( NULL ) as $nodeType )
						{
							$contentClass = $nodeType::$contentItemClass;
							$results[ $row['time'] ][ \IPS\Member::loggedIn()->language()->get( $contentClass::$title . '_pl' ) ] = 0;
						}
					}
				}

				$classType = $classMap[ $class ];

				if( mb_strpos( $chart->identifier, 'byclub' ) !== FALSE )
				{
					$results[ $row['time'] ][ \IPS\Member\Club::load( $clubNodeMap[ $class::$databaseTable . '-' . $row['container'] ] )->name ] += $row['total'];
				}
				else
				{
					$contentClass = $classMap[ $class ]::$contentItemClass;
					$results[ $row['time'] ][ \IPS\Member::loggedIn()->language()->get( $contentClass::$title . '_pl' ) ] += $row['total'];
				}
			}
		}

		return $results;
	}

	/**
	 * Get SQL query/results
	 *
	 * @note Consolidated to reduce duplicated code
	 * @param	string		$table				Database table
	 * @param	string		$date				Date column
	 * @param	string		$author				Author column
	 * @param	object		$chart				Chart
	 * @param	array		$where				Where clause
	 * @param	bool|string	$groupByContainer	If a string is provided, it must be a column name to group by in addition to time
	 * @param	NULL|array	$join				Join data, if needed (only used when $groupByContainer is set)
	 * @return	array
	 */
	protected function getSqlResults( $table, $date, $author, $chart, $where = array(), $groupByContainer = FALSE, $join = NULL )
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

		$where[]	= array( "{$date}>?", 0 );

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

		if( $groupByContainer !== FALSE )
		{
			$stmt = \IPS\Db::i()->select( "DATE_FORMAT( {$fromUnixTime}, '{$timescale}' ) AS time, COUNT(*) as total, {$groupByContainer} as container", $table, $where, 'time ASC', NULL, array( 'time', 'container' ) );

			if( $join !== NULL )
			{
				$stmt = $stmt->join( ...$join );
			}
		}
		else
		{
			$stmt = \IPS\Db::i()->select( "DATE_FORMAT( {$fromUnixTime}, '{$timescale}' ) AS time, COUNT(*) as total", $table, $where, 'time ASC', NULL, array( 'time' ) );
		}

		return $stmt;
	}
}