<?php
/**
 * @brief		Support Reports
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		23 Apr 2014
 */

namespace IPS\nexus\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Reports
 */
class _reports extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'reports_manage' );
		parent::execute();
	}

	/**
	 * Dashboard
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Tabs */
		$tabs = array(
			'overview' 			=> 'overview',
			'replies'	 		=> 'latest_replies',
			'feedback_ratings' 	=> 'feedback_ratings',
			'latest_feedback'	=> 'latest_feedback',
		);
		$activeTab = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'staff_productivity';
		$activeTabContents = '';
		
		/* Staff Productivity */
		if ( $activeTab === 'overview' )
		{
			/* Filters */
			$filters = array(
				'last_24_hours'	=> \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp(),
				'last_7_days'	=> \IPS\DateTime::create()->sub( new \DateInterval( 'P7D' ) )->getTimestamp(),
				'last_30_days'	=> \IPS\DateTime::create()->sub( new \DateInterval( 'P30D' ) )->getTimestamp(),
			);
			
			/* Set default values */
			$data = array();
			foreach ( \IPS\nexus\Support\Request::staff() as $staffId => $name )
			{
				$data[ $staffId ] = array( 'name' => $name, 'reply_count' => 0 );
				if ( \IPS\Settings::i()->nexus_support_satisfaction )
				{
					$data[ $staffId ]['rating_count'] = 0;
					$data[ $staffId ]['rating_average'] = 0;
				}
			}
			
			/* Get their replies */
			$where = array( array( \IPS\Db::i()->in( 'reply_member', array_keys( $data ) ) ) );
			if ( isset( \IPS\Request::i()->filter ) and array_key_exists( \IPS\Request::i()->filter, $filters ) )
			{
				$where[] = array( 'reply_date>?', $filters[ \IPS\Request::i()->filter ] );
			}
			foreach ( \IPS\Db::i()->select( 'COUNT(*) AS count, reply_member', 'nexus_support_replies', $where, NULL, NULL, 'reply_member' ) as $row )
			{
				$data[ $row['reply_member'] ]['reply_count'] = $row['count'];
			}
			
			/* And their ratings */
			if ( \IPS\Settings::i()->nexus_support_satisfaction )
			{
				$where = array( \IPS\Db::i()->in( 'rating_staff', array_keys( $data ) ) );
				if ( isset( \IPS\Request::i()->filter ) and array_key_exists( \IPS\Request::i()->filter, $filters ) )
				{
					$where[] = array( 'rating_date>?', $filters[ \IPS\Request::i()->filter ] );
				}
				foreach ( \IPS\Db::i()->select( 'COUNT(*) AS count, AVG(rating_rating) AS rating, rating_staff', 'nexus_support_ratings', $where, NULL, NULL, 'rating_staff' ) as $row )
				{
					$data[ $row['rating_staff'] ]['rating_count'] = $row['count'];
					$data[ $row['rating_staff'] ]['rating_average'] = $row['rating'];
				}
			}
						
			/* Build the table */
			$staffProductivityTable = new \IPS\Helpers\Table\Custom( $data, \IPS\Http\Url::internal( 'app=nexus&module=support&controller=reports&tab=overview' ) );
			$staffProductivityTable->langPrefix = 'staff_';
			$staffProductivityTable->widths = \IPS\Settings::i()->nexus_support_satisfaction ? array( 'name' => 50, 'reply_count' => 25, 'rating_average' => 25 ) : array( 'name' => 75, 'reply_count' => 25 );
			if ( \IPS\Settings::i()->nexus_support_satisfaction )
			{
				$staffProductivityTable->parsers['rating_average'] = function( $val, $row ) {
					return \IPS\Theme::i()->getTemplate('supportreports')->averageRatingCell( $val, $row['rating_count'] );
				};
				$staffProductivityTable->include = array( 'name', 'reply_count', 'rating_average' );
			}
			$staffProductivityTable->sortBy = $staffProductivityTable->sortBy ?: 'reply_count';
			$staffProductivityTable->quickSearch = 'name';
			
			/* Specify the filters and search options */
			$staffProductivityTable->filters = $filters;
	
			/* Buttons for each member */
			$staffProductivityTable->rowButtons = function( $row, $id )
			{
				return array(
					'report'	=> array(
						'icon'		=> 'search',
						'title'		=> 'view_report',
						'link'		=> \IPS\Http\Url::internal('app=nexus&module=support&controller=reports&do=staff')->setQueryString( 'id', $id ),
					),
				);	
			};
			
			/* Output */
			$activeTabContents = (string) $staffProductivityTable;
		}
		
		/* Replies */
		elseif ( $activeTab === 'replies' )
		{
			$staffRepliesChart	= new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( 'app=nexus&module=support&controller=reports&tab=replies' ), 'nexus_support_replies', 'reply_date', '',
				array(
					'vAxis'		=> array( 'title' => \IPS\Member::loggedIn()->language()->addToStack('replies_made') ),
					'height'	=> 700,
					'backgroundColor' 	=> '#ffffff',
					'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde', '#b09be4', '#eec766', '#9fc973', '#e291bf', '#55c1a6', '#5fb9da' ),
					'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
					'lineWidth'			=> 1,
					'areaOpacity'		=> 0.4
				),
				'AreaChart', 'daily', array( 'start' => \IPS\DateTime::create()->sub( new \DateInterval( 'P1M' ) ), 'end' => 0 )
			);
			$staffRepliesChart->where[] = array( 'reply_type=?', \IPS\nexus\Support\Reply::REPLY_STAFF );
			$staffRepliesChart->groupBy	= 'reply_member';
			foreach( \IPS\nexus\Support\Request::staff() as $id => $name )
			{
				$staffRepliesChart->addSeries( $name, 'number', 'COUNT(*)', TRUE, $id );
			}
			
			$activeTabContents = (string) $staffRepliesChart;
		}
		
		/* Replies */
		elseif ( $activeTab === 'feedback_ratings' )
		{
			$staffRatingsChart	= new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( 'app=nexus&module=support&controller=reports&tab=feedback_ratings' ), 'nexus_support_ratings', 'rating_date', '',
				array(
					'vAxis'		=> array( 'title' => \IPS\Member::loggedIn()->language()->addToStack('average_rating'), 'viewWindow' => array( 'min' => 0, 'max' => 5 ) ),
					'height'	=> 700,
					'backgroundColor' 	=> '#ffffff',
					'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde', '#b09be4', '#eec766', '#9fc973', '#e291bf', '#55c1a6', '#5fb9da' ),
					'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
					'lineWidth'			=> 1,
					'areaOpacity'		=> 0.4
				),
				'ColumnChart', 'monthly', array( 'start' => \IPS\DateTime::create()->sub( new \DateInterval( 'P1M' ) ), 'end' => \IPS\DateTime::create() )
			);
			$staffRatingsChart->plotZeros = FALSE;
			$staffRatingsChart->groupBy	= 'rating_staff';
			foreach( \IPS\nexus\Support\Request::staff() as $id => $name )
			{
				$staffRatingsChart->addSeries( $name, 'number', 'AVG(rating_rating)', TRUE, $id );
			}
			
			$activeTabContents = (string) $staffRatingsChart;
		}
		
		/* Latest Feedback */
		elseif ( $activeTab === 'latest_feedback' )
		{
			$table = new \IPS\Helpers\Table\Db( 'nexus_support_ratings', \IPS\Http\Url::internal('app=nexus&module=support&controller=reports&tab=latest_feedback') );
			$table->joins = array(
				array(
					'from'		=> 'nexus_support_replies',
					'where'		=> 'reply_id=rating_reply'
				),
				array(
					'from'		=> 'nexus_support_requests',
					'where'		=> 'r_id=reply_request'
				)
			);
			$table->sortBy = 'rating_date';
			$table->sortDirection = 'desc';
			$table->parsers = array(
				'reply_post'	=> function( $val )
				{
					return $val;
				}
			);
			
			$table->tableTemplate = array( \IPS\Theme::i()->getTemplate('support'), 'latestFeedback' );
			
			$activeTabContents = (string) $table;
		}
										
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('performance_reports');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=nexus&module=support&controller=reports" ) );
		}
	}
	
	/**
	 * Staff Report
	 *
	 * @return	void
	 */
	protected function staff()
	{
		/* Load */
		$id = \IPS\Request::i()->id;
		if ( !array_key_exists( $id, \IPS\nexus\Support\Request::staff() ) )
		{
			\IPS\Output::i()->error( 'node_error', '2X207/1', 404, '' );
		}
		$staff = \IPS\Member::load( $id );
		
		/* Tabs */
		$tabs = array(
			'productivity' 		=> 'productivity',
			'latest_replies'	=> 'latest_replies',
			'feedback_ratings'	=> 'feedback_ratings',
			'latest_feedback'	=> 'latest_feedback'
		);
		$activeTab = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'productivity';
		$activeTabContents = '';
		
		/* Daily Productivity */
		if ( $activeTab === 'productivity' )
		{			
			/* Date Range */
			$where = array( array( 'reply_type=?', \IPS\nexus\Support\Reply::REPLY_STAFF ) );
			$timeframe = isset( \IPS\Request::i()->timeframe ) ? \IPS\Request::i()->timeframe : 'last_30_days';
			switch ( $timeframe )
			{
				case 'last_24_hours':
					$daysInTimeframe = 1;
					break;
				case 'last_7_days':
					$daysInTimeframe = 7;
					break;
				case 'last_30_days':
					$daysInTimeframe = 30;
					break;
			}
			$where[] = array( 'reply_date>?', \IPS\DateTime::ts( time() )->sub( new \DateInterval( "P{$daysInTimeframe}D" ) )->getTimestamp() );
						
			/* Init */
			$numberOfStaffMembers = \IPS\Db::i()->select( 'COUNT(DISTINCT reply_member)', 'nexus_support_replies', $where )->first();
			$now = \IPS\DateTime::ts( time() );
			$myOffset = $now->getTimezone()->getOffset( $now ) / 3600;
			
			/* Group */
			$span = isset( \IPS\Request::i()->span ) ? \IPS\Request::i()->span : 'day';
			$group = $span === 'day' ? '%H' : '%w';
			
			/* Type */
			$type = isset( \IPS\Request::i()->type ) ? \IPS\Request::i()->type : 'total';
			
			/* Get average replies by hour for this staff member */
			$thisStaffMember = array();
			$thisStaffMemberCount = array();
			foreach( \IPS\Db::i()->select( "COUNT(*) AS count, reply_date AS unixtime, DATE_FORMAT( FROM_UNIXTIME( reply_date ), '{$group}' ) AS hour", 'nexus_support_replies', array_merge( $where, array( array( 'reply_member=?', $staff->member_id ) ) ), NULL, NULL, array( 'hour', 'reply_date' ) ) as $row )
			{			
				$_group = $span === 'day' ? \IPS\DateTime::ts( $row['unixtime'] )->format('G') : \IPS\DateTime::ts( $row['unixtime'] )->format('w');
				
				if ( !isset( $thisStaffMemberCount[ $_group ] ) )
				{
					$thisStaffMemberCount[ $_group ] = 0;
				}
				
				$thisStaffMemberCount[ $_group ] = $thisStaffMemberCount[ $_group ] + $row['count'];
			}
			
			foreach( $thisStaffMemberCount AS $_group => $count )
			{
				$thisStaffMember[ $_group ] = round( ( $type === 'average' ) ? ( $count / $daysInTimeframe ) : $count, 1 );
			}
			
			/* Get average replies by hour for all staff members */
			if ( $numberOfStaffMembers > 1 )
			{
				$allStaffMembers = array();
				$allStaffMembersCount = array();
				foreach( \IPS\Db::i()->select( "COUNT(*) AS count, reply_date AS unixtime, DATE_FORMAT( FROM_UNIXTIME( reply_date ), '{$group}' ) AS hour", 'nexus_support_replies', $where, NULL, NULL, array( 'hour', 'reply_date' ) ) as $row )
				{
					$_group = $span === 'day' ? \IPS\DateTime::ts( $row['unixtime'] )->format('G') : \IPS\DateTime::ts( $row['unixtime'] )->format('w');
					
					if ( !isset( $allStaffMembersCount[ $_group ] ) )
					{
						$allStaffMembersCount[ $_group ] = 0;
					}
					
					$allStaffMembersCount[ $_group ] = $allStaffMembersCount[ $_group ] + $row['count'];
				}
				
				foreach( $allStaffMembersCount AS $_group => $count )
				{
					$allStaffMembers[ $_group ] = round( ( ( $type === 'average' ) ? ( $count / $daysInTimeframe ) : $count ) / $numberOfStaffMembers, 1 );
				}
			}
										
			/* Build Chart */
			$chart = new \IPS\Helpers\Chart;
			$chart->addHeader( \IPS\Member::loggedIn()->language()->addToStack('hour'), 'string' );
			$chart->addHeader( $staff->name, 'number' );
			if ( $numberOfStaffMembers > 1 )
			{
				$chart->addHeader( \IPS\Member::loggedIn()->language()->addToStack('all_staff_average'), 'number' );
			}
			if ( $span === 'day' )
			{
				foreach ( range( 0 - $myOffset, 23 - $myOffset ) as $hour )
				{
					$timestamp = mktime( 0, 0, 0 ) + ( $hour * 3600 );
													
					if ( $numberOfStaffMembers > 1 )
					{				
						$chart->addRow( array(
							\IPS\DateTime::ts( $timestamp )->localeTime( FALSE, FALSE ),
							isset( $thisStaffMember[ $hour + $myOffset ] ) ? $thisStaffMember[ $hour + $myOffset ] : 0,
							isset( $allStaffMembers[ $hour + $myOffset ] ) ? $allStaffMembers[ $hour + $myOffset ] : 0
						) );
					}
					else
					{
						$chart->addRow( array(
							\IPS\DateTime::ts( $timestamp )->localeTime( FALSE, FALSE ),
							isset( $thisStaffMember[ $hour + $myOffset ] ) ? $thisStaffMember[ $hour + $myOffset ] : 0
						) );
					}
				}
			}
			else
			{
				foreach ( range( 0, 6 ) as $day )
				{
					if ( $numberOfStaffMembers > 1 )
					{				
						$chart->addRow( array(
							\IPS\Member::loggedIn()->language()->addToStack("weekday_{$day}"),
							isset( $thisStaffMember[ $day ] ) ? $thisStaffMember[ $day ] : 0,
							isset( $allStaffMembers[ $day ] ) ? $allStaffMembers[ $day ] : 0
						) );
					}
					else
					{
						$chart->addRow( array(
							\IPS\Member::loggedIn()->language()->addToStack("weekday_{$day}"),
							isset( $thisStaffMember[ $day ] ) ? $thisStaffMember[ $day ] : 0
						) );
					}
				}
			}
			
			/* Display */
			$activeTabContents = \IPS\Theme::i()->getTemplate('supportreports')->timeChart( 
				$chart->render( 'SteppedAreaChart', array(
					'legend' 			=> array( 'position' => 'none' ),
		 			'areaOpacity'		=> 0.4,
		 			'lineWidth'			=> 1,
		 			'backgroundColor' 	=> '#ffffff',
		 			'colors'			=> array( '#10967e' ),
		 			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				) ), 
				\IPS\Http\Url::internal( "app=nexus&module=support&controller=reports&do=staff&id={$staff->member_id}&tab=productivity" )->setQueryString( array(
					'span'		=> $span,
					'timeframe'	=> $timeframe,
					'type'		=> $type
				) ) 
			);
		}
		
		/* Latest Replies */
		elseif ( $activeTab === 'latest_replies' )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'support.css', 'nexus', 'admin' ) );
			$table = new \IPS\Helpers\Table\Content( 'IPS\nexus\Support\Reply', \IPS\Http\Url::internal( "app=nexus&module=support&controller=reports&do=staff&id={$staff->member_id}&tab=latest_replies" ), array( array( 'reply_type=? AND reply_member=?', \IPS\nexus\Support\Reply::REPLY_STAFF, $staff->member_id ) ) );
			$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'front' ), 'table' );
			$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'supportreports' ), 'supportReplyRows' );
			$table->sortBy = 'reply_date';
			$activeTabContents = (string) $table;
		}
		
		/* Feedback */
		elseif ( $activeTab === 'feedback_ratings' )
		{
			$staffRatingsChart	= new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( "app=nexus&module=support&controller=reports&do=staff&id={$staff->member_id}&tab=feedback_ratings" ), 'nexus_support_ratings', 'rating_date', '',
				array(
					'vAxis'		=> array( 'title' => \IPS\Member::loggedIn()->language()->addToStack('average_rating'), 'viewWindow' => array( 'min' => 0, 'max' => 5 ) ),
					'legend'	=> 'none'
				),
				'LineChart', 'monthly'
			);
			$staffRatingsChart->plotZeros = FALSE;
			$staffRatingsChart->addSeries( $staff->name, 'number', 'AVG(rating_rating)', FALSE );
			
			$activeTabContents = (string) $staffRatingsChart;
		}
		
		/* Latest Feedback */
		elseif ( $activeTab === 'latest_feedback' )
		{
			$table = new \IPS\Helpers\Table\Db( 'nexus_support_ratings', \IPS\Http\Url::internal("app=nexus&module=support&controller=reports&do=staff&id={$staff->member_id}&tab=latest_feedback"), array( 'rating_staff=?', $staff->member_id ) );
			$table->joins = array(
				array(
					'from'		=> 'nexus_support_replies',
					'where'		=> 'reply_id=rating_reply'
				),
				array(
					'from'		=> 'nexus_support_requests',
					'where'		=> 'r_id=reply_request'
				)
			);
			$table->sortBy = 'rating_date';
			$table->sortDirection = 'desc';
			$table->parsers = array(
				'reply_post'	=> function( $val )
				{
					return $val;
				}
			);
			
			$table->tableTemplate = array( \IPS\Theme::i()->getTemplate('support'), 'latestFeedback' );
			
			$activeTabContents = (string) $table;
		}
		
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
		}
		else
		{
			\IPS\Output::i()->title = $staff->name;
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=nexus&module=support&controller=reports&do=staff&id={$staff->member_id}" ) );
		}
	}
}