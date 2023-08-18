<?php
/**
 * @brief		Moderator Control Panel Extension: Reports
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Oct 2013
 */

namespace IPS\core\extensions\core\ModCp;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Report Center
 */
class _Reports extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\core\Reports\Report';
	
	/**
	 * Returns the primary tab key for the navigation bar
	 *
	 * @return	string
	 */
	public function getTab()
	{
		/* Check Permissions */
		if ( ! \IPS\Member::loggedIn()->modPermission('can_view_reports') )
		{
			return null;
		}
		
		return 'reports';
	}
		
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{		
		if ( !\IPS\Member::loggedIn()->modPermission( 'can_view_reports' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C139/1', 403, '' );
		}
		
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/modcp.css' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/modcp_responsive.css' ) );
		}
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_modcp.js', 'core' ) );
		
		parent::execute();
	}
	
	/**
	 * Overview
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Make sure we're only loading reports where we have permission to view the content */
		$where = [ \IPS\core\Reports\Report::where() ];

		/* Make sure we're loading the correct statuses */
		if( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->overview ) )
		{
			$where[] = array( "status IN( 1,2 )" );
		}

		/* Create table */
		$table = new \IPS\Helpers\Table\Content( '\IPS\core\Reports\Report', \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=reports', NULL, 'modcp_reports' ), $where );
		$table->sortBy = $table->sortBy ?: 'first_report_date';
		$table->sortDirection = 'desc';
		
		/* Title is a special case in the Report center class that isn't available in the core_rc_index table so attempting to sort on it throws an error and does nothing */
		unset( $table->sortOptions['title'] );
		
		$table->filters = [
			'filter_report_status_1' => array( 'status=1' )
		];

		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter') as $app => $classes )
		{
			$appKey = explode('_', $app)[0];
			$apps[ $appKey ] = $classes;
		}

		foreach( $apps as $appKey => $data )
		{
			$classes = $data->classes;
			foreach( $classes as $class )
			{
				if ( is_subclass_of( $class, "IPS\Content\Item" ) )
				{
					$classes[] = $class::$commentClass;

					if ( isset( $class::$reviewClass ) )
					{
						$classes[] = $class::$reviewClass;
					}
				}
			}

			$table->filters['filter_report_status_1_' . $appKey ] = [ 'status=1 and ' . \IPS\Db::i()->in( 'class', $classes ) ];
			\IPS\Member::loggedIn()->language()->words[ 'filter_report_status_1_' . $appKey ] = \IPS\Member::loggedIn()->language()->addToStack( 'filter_report_status_1_app', NULL, [ 'sprintf' => [ \IPS\Application::applications()[ $appKey ]->_title ] ] );
		}

		$table->filters['filter_report_status_2'] = [ 'status=2' ];
		$table->filters['filter_report_status_3'] = [ 'status=3' ];
		$table->title = \IPS\Member::loggedIn()->language()->addToStack( 'report_center_header' );

		if ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->overview ) )
		{
			$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'modcp', 'core' ), 'reportListOverview' );
			\IPS\Output::i()->json( array( 'data' => (string) $table ) );
		}
		else
		{
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'modcp_reports' ) );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_reports' );
			return  \IPS\Theme::i()->getTemplate( 'modcp' )->reportList( (string) $table );
		}
	}

	/**
	 * View a report
	 *
	 * @return	void
	 */
	public function view()
	{
		/* Load Report */
		try
		{
			$report = \IPS\core\Reports\Report::loadAndCheckPerms( \IPS\Request::i()->id );
			$report->markRead();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C139/3', 404, '' );
		}
		
		/* Check permission. We do it this way rather than loadAndCheckPerms() since we need to know if the user *had* permission if the content has been deleted */
		$allowedPermIds = iterator_to_array( \IPS\Db::i()->select( 'perm_id', 'core_permission_index', \IPS\Db::i()->findInSet( 'perm_view', \IPS\Member::loggedIn()->permissionArray() ) . " OR perm_view='*'" ) );
		$workingClass = $report->class;
		if ( isset( $workingClass::$itemClass ) )
		{
			$workingClass = $workingClass::$itemClass;
		}
		if ( isset( $workingClass::$containerNodeClass ) )
		{
			$workingClass = $workingClass::$containerNodeClass;
		}
		if ( isset( $workingClass::$permissionMap ) and isset( $workingClass::$permissionMap['read'] ) and $workingClass::$permissionMap['read'] !== 'view' )
		{
			$allowedPermIds = array_intersect( $allowedPermIds, iterator_to_array( \IPS\Db::i()->select( 'perm_id', 'core_permission_index', \IPS\Db::i()->findInSet( 'perm_' . $workingClass::$permissionMap['read'], \IPS\Member::loggedIn()->permissionArray() ) . " OR perm_" . $workingClass::$permissionMap['read'] . "='*'" ) ) );
		}

		if ( \in_array( 'IPS\Content\Permissions', class_implements( $workingClass ) ) and !\in_array( $report->perm_id, $allowedPermIds ) )
		{
			\IPS\Output::i()->error( 'node_error_no_perm', '2C139/5', 403, '' );
		}
		
		/* Setting status? */
		if( isset( \IPS\Request::i()->setStatus ) and \in_array( \IPS\Request::i()->setStatus, range( 1, 3 ) ) )
		{
			\IPS\Session::i()->csrfCheck();

			$report->status = (int) \IPS\Request::i()->setStatus;
			$report->save();

			/* Post a comment on the report */
			$content = \IPS\Member::loggedIn()->language()->addToStack( 'update_report_status_content', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'report_status_' . $report->status ) ) ) );
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $content );

			$comment = \IPS\core\Reports\Comment::create( $report, $content, TRUE, NULL, NULL, \IPS\Member::loggedIn(), new \IPS\DateTime );
			$comment->save();

			/* And add to the moderator log */
			\IPS\Session::i()->modLog( 'modlog__action_update_report_status', array( $report->url()->__toString() => FALSE ) );

			\IPS\Output::i()->redirect( $report->url() );
		}

		/* Deleting? */
		if( isset( \IPS\Request::i()->_action ) and \IPS\Request::i()->_action == 'delete' and $report->canDelete() )
		{
			\IPS\Session::i()->csrfCheck();
			
			$report->delete();
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=reports', NULL, 'modcp_reports' ) );
		}

		/* Load */
		$comment = NULL;
		$item = NULL;
		$ref = NULL;
		$delLog = NULL;
		try
		{
			$reportClass = $report->class;
			$thing = $reportClass::load( $report->content_id );
			if ( $thing instanceof \IPS\Content\Comment )
			{
				$comment = $thing;
				$item = $comment->item();
				
				$class = $report->class;
				$itemClass = $class::$itemClass;
				$ref = $thing->warningRef();
			}
			else
			{
				$item = $thing;
				$itemClass = $report->class;
				$ref = $thing->warningRef();
			}
			
			$hidden = $thing->hidden();
			$contentToCheck = $thing;
			if ( ( $thing instanceof \IPS\Content\Comment ) AND $hidden !== -2 )
			{
				$hidden = $thing->item()->hidden();
				$contentToCheck = $thing->item();
			}
			
			if ( $hidden === -2 )
			{
				try
				{
					$delLog = \IPS\core\DeletionLog::loadFromContent( $contentToCheck );
				}
				catch( \OutOfRangeException $e ) {}
			}
		}
		catch ( \OutOfRangeException $e ) { }
		
		/* Next/Previous Links */
		$permSubQuery = \IPS\Db::i()->select( 'perm_id', 'core_permission_index', \IPS\Db::i()->findInSet( 'perm_view', array_merge( array( \IPS\Member::loggedIn()->member_group_id ) , array_filter( explode( ',', \IPS\Member::loggedIn()->mgroup_others ) ) ) ) . " or perm_view='*'" );

		$prevReport	= NULL;
		$prevItem	= NULL;
		$nextReport	= NULL;
		$nextItem	= NULL;
		
		/* Prev */
		try
		{
			$prevReport = \IPS\Db::i()->select( 'id, class, content_id', 'core_rc_index', array( '( perm_id IN (?) OR perm_id IS NULL ) AND first_report_date>?', $permSubQuery, $report->first_report_date ), 'first_report_date ASC', 1 )->first();
			
			try
			{
				$reportClass = $prevReport['class'];
				$prevItem = $reportClass::load( $prevReport['content_id'] );
				
				if ( $prevItem instanceof \IPS\Content\Comment )
				{
					$prevItem = $prevItem->item();
				}
			}
			catch (\OutOfRangeException $e) {}
		}
		catch ( \UnderflowException $e ) {}
		
		/* Next */
		try
		{
			$nextReport = \IPS\Db::i()->select( 'id, class, content_id', 'core_rc_index', array( '( perm_id IN (?) OR perm_id IS NULL ) AND first_report_date<?', $permSubQuery, $report->first_report_date ), 'first_report_date DESC', 1 )->first();

			try
			{
				$reportClass = $nextReport['class'];
				$nextItem = $reportClass::load( $nextReport['content_id'] );

				if ( $nextItem instanceof \IPS\Content\Comment )
				{
					$nextItem = $nextItem->item();
				}
			}
			catch (\OutOfRangeException $e) {}
		}
		catch ( \UnderflowException $e ) {}

		/* Display */
		if ( \IPS\Request::i()->isAjax() and !isset( \IPS\Request::i()->_contentReply ) and !isset( \IPS\Request::i()->getUploader ) AND !isset( \IPS\Request::i()->page ) AND !isset( \IPS\Request::i()->_previewField ) )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'modcp' )->reportPanel( $report, $comment, $ref );
		}
		else
		{
			$sprintf = $item ? htmlspecialchars( $item->mapped('title'), ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ) : \IPS\Member::loggedIn()->language()->addToStack('content_deleted');
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_reports_view', FALSE, array( 'sprintf' => array( $sprintf ) ) );
			\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=reports', 'front', 'modcp_reports' ), \IPS\Member::loggedIn()->language()->addToStack( 'modcp_reports' ) );
			\IPS\Output::i()->breadcrumb[] = array( NULL, $item ? $item->mapped('title') : \IPS\Member::loggedIn()->language()->addToStack( 'content_deleted' ) );
			return \IPS\Theme::i()->getTemplate( 'modcp' )->report( $report, $comment, $item, $ref, $prevReport, $prevItem, $nextReport, $nextItem, $delLog );
		}
	}
	
	/**
	 * Redirect to the original content for a report
	 *
	 * @return	void
	 */
	public function find()
	{		
		try
		{
			$report = \IPS\core\Reports\Report::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C139/4', 404, '' );
		}
		
		$reportClass = $report->class;
		$comment = $reportClass::load( $report->content_id );
		$url = \IPS\Request::i()->parent ? $comment->item()->url() : $comment->url();
		$url = $url->setQueryString( '_report', $report->id );
		
		\IPS\Output::i()->redirect( $url );
	}
	
	/**
	 * Return a comment URL
	 *
	 * @return \IPS\Http\Url
	 */
	 public function findComment()
	 {
		try
		{
			$report = \IPS\core\Reports\Report::loadAndCheckPerms( \IPS\Request::i()->id );
			$comment = \IPS\core\Reports\Comment::load( \IPS\Request::i()->comment );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C139/4', 404, '' );
		}
		
		$url = $report->url()->setQueryString( 'activeTab', 'comments' );
		
		$idColumn = \IPS\core\Reports\Report::$databaseColumnId;
		$commentIdColumn = \IPS\core\Reports\Comment::$databaseColumnId;
		$position = \IPS\Db::i()->select( 'COUNT(*)', 'core_rc_comments', array( "rid=? AND id<=?", $report->$idColumn, $comment->$commentIdColumn ) )->first();
		
		$page = ceil( $position / $report::getCommentsPerPage() );
		if ( $page != 1 )
		{
			$url = $url->setPage( 'page', $page );
		}
		
		
		\IPS\Output::i()->redirect( $url->setFragment( 'comment-' . $comment->$commentIdColumn ) );
	 }
}