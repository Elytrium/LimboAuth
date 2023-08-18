<?php
/**
 * @brief		Popular things
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Oct 2016
 */

namespace IPS\core\modules\front\discover;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Most popular things
 */
class _popular extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Ensure that the rep system is enabled and the leaderboard is enabled */
		if ( ! \IPS\Settings::i()->reputation_enabled or ! \IPS\Settings::i()->reputation_leaderboard_on )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2C343/1', 403, '' );
		}
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/search.css' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/leaderboard.css' ) );
		
		if ( \IPS\Theme::i()->settings['responsive'] )
  		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/search_responsive.css' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/leaderboard_responsive.css' ) );
		}
		
		/* Make sure default tab is first */
		$tabs = array( \IPS\Settings::i()->reputation_leaderboard_default_tab );
		
		foreach( array( 'leaderboard', 'history', 'members' ) as $addTab )
		{
			if ( $addTab != \IPS\Settings::i()->reputation_leaderboard_default_tab )
			{
				$tabs[] = $addTab;
			}
		}

		if ( \IPS\Application::appIsEnabled('cloud') and \IPS\cloud\Application::featureIsEnabled('trending') )
		{
			$tabs[] = 'trending';
		}
		
		$activeTab = ( isset( \IPS\Request::i()->tab ) and \in_array(\IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : \IPS\Settings::i()->reputation_leaderboard_default_tab;
		
		/* Initiate the breadcrumb */
		\IPS\Output::i()->breadcrumb = array( array( \IPS\Http\Url::internal( "app=core&module=discover&controller=popular&tab=" . $activeTab, 'front', 'leaderboard_' . $activeTab ), \IPS\Member::loggedIn()->language()->addToStack('leaderboard_title') ) );
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'leaderboard_tabs_' . $activeTab );
		
		$content = $this->$activeTab();

		/* Data Layer Context Property */
		if ( \IPS\Settings::i()->core_datalayer_enabled AND ! \IPS\Request::i()->isAjax() )
		{
			\IPS\core\DataLayer::i()->addContextProperty( 'community_area', 'leaderboard', true );
		}

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( $content ), 200, 'text/html' );
		}
		else
		{
			/* Display */
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('popular')->tabs( $tabs, $activeTab, $content );
		}
	}
	
	/**
	 * View top members
	 *
	 * @return	void
	 */
	protected function members()
	{
		/* Get filters */
		$filters = array_merge( array( 'overview' => \IPS\Member::loggedIn()->language()->addToStack('overview') ), \IPS\Member::topMembersOptions( \IPS\Member::TOP_MEMBERS_FILTERS ) );
		$activeFilter = 'overview';
		if ( isset( \IPS\Request::i()->filter ) )
		{
			if ( array_key_exists( \IPS\Request::i()->filter, $filters ) )
			{
				$activeFilter = \IPS\Request::i()->filter;
			}
			else
			{
				$possibleFilter = 'IPS\\' . str_replace( '_', '\\', \IPS\Request::i()->filter );
				if ( array_key_exists( $possibleFilter, $filters ) )
				{
					$activeFilter = $possibleFilter;
				}
			}
		}
		
		/* Get results */
		if ( $activeFilter == 'overview' )
		{
			$output = \IPS\Theme::i()->getTemplate('popular')->topMembersOverview( \IPS\Member::topMembersOptions( \IPS\Member::TOP_MEMBERS_OVERVIEW ) );
		}
		else
		{
			$output = \IPS\Theme::i()->getTemplate('popular')->topMembersResults( $activeFilter, NULL, \IPS\Member::topMembers( $activeFilter, \IPS\Settings::i()->reputation_max_members ) );
		}

		/* Output */
		\IPS\Output::i()->linkTags['canonical'] = (string) \IPS\Http\Url::internal( "app=core&module=discover&controller=popular&tab=members", 'front', 'leaderboard_members' );
		if ( \IPS\Request::i()->isAjax() and \IPS\Request::i()->topMembers )
		{
			\IPS\Output::i()->json( array( 'rows' => $output, 'extraHtml' => $filters[ $activeFilter ] ) );
		}
		else
		{
			return \IPS\Theme::i()->getTemplate('popular')->topMembers( \IPS\Http\Url::internal( 'app=core&module=discover&controller=popular&tab=members', 'front', 'leaderboard_members' ), $filters, $activeFilter, $output );
		}
	}
	
	/**
	 * View past leaders
	 *
	 * @return	void
	 */
	protected function history()
	{
		$table = new \IPS\Helpers\Table\Db( 'core_reputation_leaderboard_history', \IPS\Http\Url::internal( "app=core&module=discover&controller=popular&tab=history", 'front', 'leaderboard_history' ) );
		$table->where = array( 'leader_position <= 3' );
		$table->limit = 21;
		$table->selects = array( 'leader_member_id, leader_position, leader_rep_total', 'ABS(leader_date - leader_position) as leader_date' );
		$table->joins = array( array( 'select' => 'core_members.*', 'from' => 'core_members', 'where' => 'core_reputation_leaderboard_history.leader_member_id=core_members.member_id' ) );
		$table->include = array( 'leader_member_id', 'leader_date', 'leader_position', 'leader_rep_total' );
		$table->noSort = array( 'leader_member_id', 'leader_position', 'leader_rep_total' );
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'popular', 'core', 'front' ), 'popularTable' );
		$table->rowsTemplate  = array( \IPS\Theme::i()->getTemplate( 'popular', 'core', 'front' ), 'popularRows' );
		$table->title = 'leaderboard_history';
		$table->mainColumn = 'leader_date';
		$table->sortBy = $table->sortBy ?: 'leader_date';
		$table->sortDirection = $table->sortDirection ?: 'DESC';
		
		/* Like or what? */
		if ( \IPS\Content\Reaction::isLikeMode() )
		{
			\IPS\Member::loggedIn()->language()->words['leader_rep_total'] = \IPS\Member::loggedIn()->language()->addToStack( 'leader_rep_total_likes' );
			\IPS\Member::loggedIn()->language()->words['leaderboard_history__desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'leaderboard_history_desc', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'leaderboard_history_likes') ) ) );
		}
		else
		{
			\IPS\Member::loggedIn()->language()->words['leader_rep_total'] = \IPS\Member::loggedIn()->language()->addToStack( 'leader_rep_total_rep' );
			\IPS\Member::loggedIn()->language()->words['leaderboard_history__desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'leaderboard_history_desc', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'leaderboard_history_rep') ) ) );
		}
		
		/* Parsers */
		$table->parsers = array(
			'leader_member_id' => function( $val, $row )
			{
				return \IPS\Member::constructFromData( $row );
			},
			'leader_date' => function( $val )
			{
				return \IPS\DateTime::ts( $val )->setTimezone( new \DateTimeZone( \IPS\Settings::i()->reputation_timezone ) );
			}
		);

		\IPS\Output::i()->linkTags['canonical'] = (string) \IPS\Http\Url::internal( "app=core&module=discover&controller=popular&tab=history", 'front', 'leaderboard_history' );
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( $table ), 200, 'text/html' );
		}
		else
		{
			if ( $table->page > 1 )
			{
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( \IPS\Output::i()->title, $table->page ) ) );
			}

			return (string) $table;
		}
	}
	
	/**
	 * View Popular list
	 *
	 * It's slightly cumbersome because we need to group, but we can only select columns in the group list, so we have to:
	 * 1) Fetch the lookup_hash and total_rep
	 * 2) Fetch the rep data so we can...
	 * 3) Fetch the search engine data and then...
	 * 4) Manually sort in PHP
	 *
	 * @return	void
	 */
	protected function leaderboard()
	{
		/* Figure out dates */
		$dates = array();
		$timezone = new \DateTimeZone( \IPS\Settings::i()->reputation_timezone );
		$endDate = \IPS\DateTime::ts( time() )->setTimezone( $timezone );

		$firstRepDate = \IPS\Db::i()->select( 'MIN(rep_date)', 'core_reputation_index' )->first();
		$firstIndexDate = \IPS\Content\Search\Index::i()->firstIndexDate();
		$appWhere = null;
		$descApp = \IPS\Member::loggedIn()->language()->addToStack( 'leaderboard_in_all_apps' );

		$dates[ 'oldest' ] = \IPS\DateTime::ts( ( $firstRepDate > $firstIndexDate ) ? $firstRepDate : $firstIndexDate );
		$oldestStamp = $dates[ 'oldest' ]->getTimeStamp();
		$date = $dates[ 'oldest' ];

		$aYearAgo = \IPS\DateTime::create()->setTimezone( $timezone )->sub( new \DateInterval( 'P1Y' ) );
		$month = \IPS\DateTime::create()->setTimezone( $timezone )->sub( new \DateInterval( 'P1M' ) )->setTime( 0, 0 );
		$week = \IPS\DateTime::create()->setTimezone( $timezone )->sub( new \DateInterval( 'P7D' ) )->setTime( 0, 0 );
		$today = \IPS\DateTime::create()->setTimezone( $timezone )->setTime( 0, 0 );

		if ( $aYearAgo->getTimeStamp() > $oldestStamp )
		{
			$dates[ 'year' ] = $aYearAgo;
		}

		if ( $month->getTimeStamp() > $oldestStamp )
		{
			$dates[ 'month' ] = $month;
		}

		if ( $week->getTimeStamp() > $oldestStamp )
		{
			$dates[ 'week' ] = $week;
		}

		if ( $today->getTimeStamp() > $oldestStamp )
		{
			$dates[ 'today' ] = $today;
		}

		/* Got a date? */
		if ( isset( \IPS\Request::i()->time ) and isset( $dates[ \IPS\Request::i()->time ] ) )
		{
			$date = $dates[ \IPS\Request::i()->time ];
		}
		else if ( isset( $dates[ 'month' ] ) )
		{
			/* Set the default to month */
			\IPS\Request::i()->time = 'month';
			$date = $dates[ 'month' ];
		}

		/* Applications */
		$classes = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', TRUE ) as $object )
		{
			$classes = array_merge( $object->classes, $classes );
		}

		$areas = array();
		foreach ( $classes as $item )
		{
			$commentClass = NULL;
			$reviewClass = NULL;

			if ( \IPS\IPS::classUsesTrait( $item, 'IPS\Content\Reactable' ) )
			{
				$areas[ $item::$application . '-' . $item::reactionType() ] = array( $item, \IPS\Member::loggedIn()->language()->addToStack( "{$item::$title}_pl" ) );
			}

			if ( isset( $item::$commentClass ) )
			{
				$commentClass = $item::$commentClass;
				if ( \IPS\IPS::classUsesTrait( $commentClass, 'IPS\Content\Reactable' ) )
				{
					$areas[ $item::$application . '-' . $commentClass::reactionType() ] = array( $commentClass, \IPS\Member::loggedIn()->language()->addToStack( "{$commentClass::$title}_pl" ) );
				}
			}

			if ( isset( $item::$reviewClass ) )
			{
				$reviewClass = $item::$reviewClass;
				if ( \IPS\IPS::classUsesTrait( $reviewClass, 'IPS\Content\Reactable' ) )
				{
					$areas[ $item::$application . '-' . $reviewClass::reactionType() ] = array( $reviewClass, \IPS\Member::loggedIn()->language()->addToStack( "{$reviewClass::$title}_pl" ) );
				}
			}
		}

		$form = new \IPS\Helpers\Form( 'popular_date', 'continue' );
		$form->class = 'ipsForm_vertical';
		$customStart = ( isset( \IPS\Request::i()->custom_date_start ) and \is_numeric( \IPS\Request::i()->custom_date_start ) ) ? (int) \IPS\Request::i()->custom_date_start : NULL;
		$customEnd = ( isset( \IPS\Request::i()->custom_date_end ) and \is_numeric( \IPS\Request::i()->custom_date_end ) ) ? (int) \IPS\Request::i()->custom_date_end : NULL;

		$form->add( new \IPS\Helpers\Form\DateRange( 'custom_date', array( 'start' => $customStart, 'end' => $customEnd ), FALSE, array( 'start' => array( 'min' => $dates[ 'oldest' ], 'time' => false ) ) ) );

		if ( $values = $form->values() )
		{
			$url = \IPS\Request::i()->url()->stripQueryString( 'time' );

			if ( isset( $values[ 'custom_date' ][ 'start' ] ) and $values[ 'custom_date' ][ 'start' ] instanceof \IPS\DateTime )
			{
				$url = $url->setQueryString( 'custom_date_start', $values[ 'custom_date' ][ 'start' ]->getTimeStamp() );
			}

			if ( isset( $values[ 'custom_date' ][ 'end' ] ) and $values[ 'custom_date' ][ 'end' ] instanceof \IPS\DateTime )
			{
				$url = $url->setQueryString( 'custom_date_end', $values[ 'custom_date' ][ 'end' ]->getTimeStamp() );
			}

			\IPS\Output::i()->redirect( $url );
		}
		else
		{
			if ( $customStart )
			{
				$date = \IPS\DateTime::ts( $customStart )->setTimezone( $timezone )->setTime( 0, 0, 1 );
			}

			if ( $customEnd )
			{
				$endDate = \IPS\DateTime::ts( $customEnd )->setTimezone( $timezone )->setTime( 23, 59, 59 );
			}
		}

		/* Do we want results for a specific app */
		$customApp = FALSE;

		if ( isset( \IPS\Request::i()->in ) and isset( $areas[ \IPS\Request::i()->in ] ) )
		{
			$appWhere = " AND rep_class='" . \IPS\Db::i()->escape_string( $areas[ \IPS\Request::i()->in ][ 0 ] ) . "'";
			$descApp = \IPS\Member::loggedIn()->language()->addToStack( 'leaderboard_in_app', FALSE, array( 'sprintf' => array( $areas[ \IPS\Request::i()->in ][ 1 ] ) ) );
			$customApp = TRUE;
		}
		else
		{
			$repAreas =  array();
			foreach( $areas as $area )
			{
				$repAreas[] = $area[0] ;
			}

			$appWhere = " AND " .  \IPS\Db::i()->in( 'rep_class', $repAreas );
		}
		
		$storeKey = NULL;
		$hashes   = NULL;
		if ( ! $customStart and ! $customEnd AND ! $customApp )
		{
			$storeKey = 'leaderHashes_' . \IPS\Request::i()->time . '-' . md5( implode( ',', \IPS\Member::loggedIn()->groups ) );
		
			if ( isset( \IPS\Data\Store::i()->$storeKey ) )
			{
				$stored = \IPS\Data\Store::i()->$storeKey;
				
				if ( isset( $stored['hashes'] ) and isset( $stored['time'] ) and $stored['time'] > ( time() - 900 ) )
				{
					$hashes = $stored['hashes'];
				}
			}
		}

		/* Get hashes and total rep */
		if ( $hashes === NULL )
		{
			/* Prevent race condition */
			if ( $storeKey )
			{
				\IPS\Data\Store::i()->$storeKey = array( 'time' => time(), 'hashes' => array() );
			}
			
			/* Get rep hashes */
			$inner = \IPS\Db::i()->select( 'class_type_id_hash, SUM(rep_rating) as total_rep', array( 'core_reputation_index', 'x' ),  array( 'rep_date BETWEEN ' . $date->getTimeStamp() . ' AND ' . $endDate->getTimeStamp() . $appWhere ), NULL, NULL, 'class_type_id_hash' );
			$repHashes = iterator_to_array( \IPS\Db::i()->select( 'class_type_id_hash, total_rep', $inner, NULL, 'x.total_rep desc', array( 0, 500 ) )->setKeyField('class_type_id_hash') );
				
			/* Now filter through permissions */
			$searchHashes = \IPS\Content\Search\Index::i()->hashesWithPermission( array_keys( $repHashes ), \IPS\Member::loggedIn(), 500 );
			
			/* Filter out rep hashes not in search results */
			$hashes = \array_slice( array_intersect_key( $repHashes, $searchHashes ), 0, 50 );
			
			if ( $storeKey )
			{
				\IPS\Data\Store::i()->$storeKey = array( 'time' => time(), 'hashes' => $hashes );
			}
		}
		
		$classes = array();
		$repData = array();
		$or   = array();
		$results = array();
		$preLoadMembers = array();
				
		if ( \count( $hashes ) )
		{
			/* Now get the reputation data */
			foreach( \IPS\Db::i()->select( '*', 'core_reputation_index', array( \IPS\Db::i()->in( 'class_type_id_hash', array_keys( $hashes ) ) ) ) as $data )
			{
				$data['total_rep'] = $hashes[ $data['class_type_id_hash'] ]['total_rep'];
				$repData[ $data['rep_class'] . '-' . $data['type_id'] ] = $data;
				$classes[ $data['rep_class'] ][] = $data['type_id'];
			}
			
			foreach( $classes as $class => $ids )
			{
				$or[] = \IPS\Content\Search\ContentFilter::initWithSpecificClass( $class )->onlyInIds( $ids );
			}
			
			/* Query and manually sort */			
			$sorted = array();
			$search = \IPS\Content\Search\Query::init();
			
			/* Set the result to get as 50, as we pass in 50 unique hashes and the default is only 25, which means some may be missed as they are not sorted by rep count until after fetching */
			$search->resultsToGet = 50;
			
			$array = $search->filterByContent( $or )->search()->getArrayCopy();
			
			foreach( $array as $index => $data )
			{
				if ( isset( $repData[ $data['index_class'] . '-' . $data['index_object_id'] ] ) )
				{
					$data['rep_data'] = $repData[ $data['index_class'] . '-' . $data['index_object_id'] ];
					$sorted[ $data['rep_data']['total_rep'] . '.' . $data['index_date_updated'] . '.'. $index ] = $data;
				}
			}
			unset( $array );
			krsort( $sorted, SORT_NUMERIC );
			
			$results = new \IPS\Content\Search\Results( $sorted, \count( $sorted ) );
			
			/* Load data we need like the authors, etc */
			$results->init();
		}
		
		/* Get top rated contributors */
		$topContributors = array();

		$innerQueryWhere = array();
		$innerQueryWhere[] = array( 'member_received>0 ' . $appWhere . ' and rep_date BETWEEN ' . \intval( $date->getTimeStamp() ) . ' AND ' . \intval( $endDate->getTimeStamp() ) );

		if( \IPS\Settings::i()->leaderboard_excluded_groups )
		{
			$innerQueryWhere[] = \IPS\Db::i()->in( 'member_group_id', explode( ',', \IPS\Settings::i()->leaderboard_excluded_groups ), TRUE );

			$innerQuery = \IPS\Db::i()->select( 'core_reputation_index.member_received as themember, SUM(rep_rating) as rep', 'core_reputation_index', $innerQueryWhere, NULL, NULL, 'themember' )->join( 'core_members', array( 'core_reputation_index.member_received = core_members.member_id' ) );
		}
		else
		{
			$innerQuery = \IPS\Db::i()->select( 'core_reputation_index.member_received as themember, SUM(rep_rating) as rep', 'core_reputation_index', $innerQueryWhere, NULL, NULL, 'themember' );
		}

		foreach( \IPS\Db::i()->select( 'themember, rep', array( $innerQuery, 'in' ), NULL, 'rep DESC', 4 )->setKeyField('themember')->setValueField('rep') as $member => $rep )
		{
			$topContributors[ $member ] = $rep;
		}
		
		if ( \count( $topContributors ) )
		{
			$preLoadMembers = array_merge( $preLoadMembers, array_keys( $topContributors ) );
		}
		
		/* Load their data */
		if ( \count( $preLoadMembers ) )
		{
			foreach ( \IPS\Db::i()->select( '*', 'core_members', \IPS\Db::i()->in( 'member_id', array_unique( $preLoadMembers ) ) ) as $member )
			{
				\IPS\Member::constructFromData( $member );
			}
		}
		
		/* Work out the description for popular content */
		if ( \IPS\Content\Reaction::isLikeMode() )
		{
			$popularResultsSingle = 'popular_results_single_desc';
			$popularResultsMany = 'popular_results_desc';
		}
		else
		{
			$popularResultsSingle = 'popular_results_single_desc_rep';
			$popularResultsMany = 'popular_results_desc_rep';
		}
		
		$description = \IPS\Member::loggedIn()->language()->addToStack( ( $date->localeDate() == $endDate->localeDate() ) ? $popularResultsSingle : $popularResultsMany, NULL, array( 'sprintf' => array( $date->localeDate(), $descApp ) ) );
		
		/* Are our offsets different? */
		$tzOffsetDifference = NULL;
		try
		{
			if ( \IPS\DateTime::ts( time() )->getOffset() != \IPS\DateTime::ts( time() )->setTimezone( $timezone )->getOffset() )
			{
				$tzOffsetDifference = \IPS\DateTime::ts( time() )->setTimezone( $timezone )->format('P');
			}
		}
		catch( \Exception $ex ) { }

		\IPS\Output::i()->linkTags['canonical'] = (string) \IPS\Http\Url::internal( "app=core&module=discover&controller=popular&tab=leaderboard", 'front', 'leaderboard_leaderboard' );

		/* If there are no results tell search engines not to index the page */
		if( !\count( $results ) )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}

		/* Display */
		return \IPS\Theme::i()->getTemplate('popular')->popularWrapper( $results, $areas, $topContributors, $dates, $description, $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) ), $tzOffsetDifference );
	}
}