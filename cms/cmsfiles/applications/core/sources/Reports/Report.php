<?php
/**
 * @brief		Report Index Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Jul 2013
 */

namespace IPS\core\Reports;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Report Model
 */
class _Report extends \IPS\Content\Item implements \IPS\Content\ReadMarkers
{
	/**
	 * @brief	Const	No type selected
	 */
	const	TYPE_MESSAGE = 0;
	 
	 
	/* !\IPS\Patterns\ActiveRecord */
	
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_rc_index';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = '';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	Database ID Fields
	 */
	protected static $databaseIdFields = array( 'content_id' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/* !\IPS\Content\Item */
	
	/**
	 * @brief	Application
	 */
	public static $application = 'core';
	
	/**
	 * @brief	Application
	 */
	public static $module = 'modcp';

	/**
	 * @brief	Allow the title to be editable via AJAX
	 */
	public $editableTitle	= FALSE;
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'date'			=> 'first_report_date',
		'author'		=> 'first_report_by',
		'author_count'	=> 'num_reports',
		'title'			=> 'title',
		'last_comment'	=> 'last_updated',
		'num_comments'	=> 'num_comments',
	);
	
	/**
	 * @brief	Language prefix for forms
	 */
	public static $formLangPrefix = 'report_';
	
	/**
	 * @brief	Comment Class
	 */
	public static $commentClass = 'IPS\core\Reports\Comment';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'report';

	/**
	 * @brief	[Content\Item]	Include these items in trending content
	 */
	public static $includeInTrending = FALSE;

	/**
	 * Should IndexNow be skipped for this item? Can be used to prevent that Private Messages,
	 * Reports and other content which is never going to be visible to guests is triggering the requests.
	 * @var bool
	 */
	public static bool $skipIndexNow = TRUE;
	
	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return FALSE;
	}
	
	/**
	 * Load by class and content_id
	 *
	 * @param	string	$class	Class to load
	 * @param	int		$id		ID to load
	 * @return	static
	 * @throws	\OutofRangeException
	 */
	public static function loadByClassAndId( $class, $id )
	{
		try
		{
			return static::constructFromData( \IPS\Db::i()->select( '*', 'core_rc_index', array( 'class=? and content_id=?', $class, $id ) )->first() );
		}
		catch ( \UnderflowException $e )
		{
			throw new \OutofRangeException;
		}
	}
	
	/**
	 * Get mapped value
	 *
	 * @param	string	$key	date,content,ip_address,first
	 * @return	mixed
	 */
	public function mapped( $key )
	{
		/* Get the reported content items title */
		if ( $key === 'title' )
		{
			try
			{
				$class = $this->_data['class'];
				$thing = $class::load( $this->_data['content_id'] );
				$item = ( $thing instanceof \IPS\Content\Comment ) ? $thing->item() : $thing;

				if( isset( $item::$databaseColumnMap['content'] ) AND $item::$databaseColumnMap['content'] == $item::$databaseColumnMap['title'] )
				{
					$title = trim( mb_substr( strip_tags( $item->mapped( 'title' ) ), 0, 85 ) );
					return $title ?: \IPS\Member::loggedIn()->language()->addToStack('report_no_title_available'); 
				}
				else
				{
					$title = trim( strip_tags( $item->mapped( 'title' ) ) );
					return $title ?: \IPS\Member::loggedIn()->language()->addToStack('report_no_title_available');
				}
			}
			catch ( \Exception $e )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'unknown' );
			}
		}

		return parent::mapped( $key );
	}

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();

	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action=NULL )
	{
		$_key	= md5( $action );

		if( !isset( $this->_url[ $_key ] ) )
		{
			$this->_url[ $_key ] = \IPS\Http\Url::internal( "app=core&module=modcp&tab=reports&action=view&id={$this->id}", 'front', 'modcp_report' );
		
			if ( $action )
			{
				$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( 'action', $action );
			}
		}
	
		return $this->_url[ $_key ];
	}
	
	/* !\IPS\Helpers\Table */
		
	/**
	 * Method to add extra data to objects in this
	 * class when displaying in a table view
	 *
	 * @param	array	$rows	Array of objects of this class
	 * @return	void
	 */
	public static function tableGetRows( $rows )
	{
		$types = array();
		
		foreach ( $rows as $row )
		{
			$types[ $row->class ][ $row->content_id ] = $row;
		}

		foreach ( $types as $class => $objects )
		{
			if ( \in_array( 'IPS\Content\Comment', class_parents( $class ) ) )
			{
				$itemClass = $class::$itemClass;
				$databaseTable = $class::$databaseTable;
				$itemDatabaseTable = $itemClass::$databaseTable;
				$itemTitleField = $itemClass::$databaseColumnMap['title']; # Strange PHP issue can cause this to be lost when added to the query below.
				
				foreach( \IPS\Db::i()->select(
					"{$databaseTable}.{$class::$databasePrefix}{$class::$databaseColumnId} as commentId, {$databaseTable}.{$class::$databasePrefix}{$class::$databaseColumnMap['item']} AS itemId, {$itemClass::$databaseTable}.{$itemClass::$databasePrefix}{$itemTitleField} AS title",
					$databaseTable,
					\IPS\Db::i()->in( $databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId, array_keys( $objects ) )
				)->join(
					$itemDatabaseTable,
					"{$itemDatabaseTable}.{$itemClass::$databasePrefix}{$itemClass::$databaseColumnId}={$databaseTable}.{$class::$databasePrefix}{$class::$databaseColumnMap['item']}"
				)->setKeyField( 'commentId' ) as $k => $data
				)
				{
					$objects[ $k ]->_data = array_merge( $objects[ $k ]->_data, $data );
				}
			}
			elseif ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) )
			{
				foreach( \IPS\Db::i()->select(
					"{$class::$databasePrefix}{$class::$databaseColumnId} as itemId, {$class::$databasePrefix}{$class::$databaseColumnMap['title']} AS title",
					$class::$databaseTable,
					\IPS\Db::i()->in( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId, array_keys( $objects ) )
				)->setKeyField( 'itemId' ) as $k => $data
				)
				{
					$objects[ $k ]->_data = array_merge( $objects[ $k ]->_data, $data );
				}
			}
		}
	}
	
	/**
	 * Method to get description for table view
	 *
	 * @return	string
	 */
	public function tableDescription()
	{
		$className = $this->class;
		try
		{
			$reportedContent = $className::load( $this->content_id );

			if( $reportedContent instanceof \IPS\Content\Comment )
			{
				$container = ( $reportedContent->item()->containerWrapper() !== NULL ) ? $reportedContent->item()->container() : NULL;
			}
			else
			{
				$container = ( $reportedContent->containerWrapper() !== NULL ) ? $reportedContent->container() : NULL;
			}
		}
		catch ( \OutOfRangeException $ex )
		{
			$container = NULL;
		}

		return \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' )->reportTableDescription( $className, $this, $container );
	}

	/**
	 * Get content table states
	 *
	 * @return string
	 */
	public function tableStates()
	{
		$states = explode( ' ', parent::tableStates() );
		$states[] = "report_status_" . $this->status;

		return implode( ' ', $states );
	}
	
	/**
	 * Stats for table view
	 *
	 * @param	bool	$includeFirstCommentInCommentCount	Whether or not to include the first comment in the comment count
	 * @return	array
	 */
	public function stats( $includeFirstCommentInCommentCount=TRUE )
	{
		return array_merge( parent::stats( $includeFirstCommentInCommentCount ), array( 'num_reports' => $this->num_reports ) );
	}
	
	/**
	 * Icon for table view
	 *
	 * @return	array
	 */
	public function tableIcon()
	{
		return \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' )->reportToggle( $this );
	}

	/**
	 * Gets a special class for the row
	 *
	 * @return	string
	 */
	public function tableClass()
	{
		switch ( $this->status )
		{
			case 2:
				return 'warning';
			break;
			case 1:
				return 'new';
			break;
		}

		return '';
	}
	
	/**
	 * Do Moderator Action
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string|NULL			$reason	Reason (for hides)
	 * @param	bool				$immediately	Delete immediately
	 * @return	void
	 * @throws	\OutOfRangeException|\InvalidArgumentException|\RuntimeException
	 */
	public function modAction( $action, \IPS\Member $member = NULL, $reason = NULL, $immediately = FALSE )
	{
		if ( mb_substr( $action, 0, -1 ) === 'report_status_' )
		{
			$this->status = mb_substr( $action, -1 );
			$this->save();

			/* Post a comment on the report */
			$content = \IPS\Member::loggedIn()->language()->addToStack( 'update_report_status_content', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'report_status_' . $this->status ) ) ) );
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $content );

			$comment = \IPS\core\Reports\Comment::create( $this, $content, TRUE, NULL, NULL, \IPS\Member::loggedIn(), new \IPS\DateTime );
			$comment->save();

			/* And add to the moderator log */
			\IPS\Session::i()->modLog( 'modlog__action_update_report_status', array( $this->url()->__toString() => FALSE ) );
		}
		else
		{
			return parent::modAction( $action, $member, $reason, $immediately );
		}
	}
	
	/**
	 * Return any custom multimod actions this content item class supports
	 *
	 * @return	array
	 */
	public function customMultimodActions()
	{
		return array_diff( array( 'report_status_1', 'report_status_2', 'report_status_3', ), array( 'report_status_' . $this->status ) );
	}

	/**
	 * Return any available custom multimod actions this content item class supports
	 *
	 * @note	Return in format of array( array( 'action' => ..., 'icon' => ..., 'language' => ... ) )
	 * @return	array
	 */
	public static function availableCustomMultimodActions()
	{
		return array(
			array(
				'groupaction'	=> 'report_status',
				'icon'			=> 'flag',
				'grouplabel'	=> 'mark_as',
				'action'		=> array(
					array(
						'action'	=> 'report_status_1',
						'icon'		=> 'flag',
						'label'		=> 'report_status_1'
					),
					array(
						'action'	=> 'report_status_2',
						'icon'		=> 'exclamation-triangle',
						'label'		=> 'report_status_2'
					),
					array(
						'action'	=> 'report_status_3',
						'icon'		=> 'check-circle',
						'label'		=> 'report_status_3'
					)
				)
			)
		);
	}
	
	/* !\IPS\core\Reports\report */
	
	/**
	 * Get reports
	 *
	 * @param	string|NULL	$filterByType	Report type to filter by, or NULL to not filter by type
	 * @return	array
	 */
	public function reports( $filterByType=NULL)
	{
		$where = array( array( 'rid=?', $this->id ) );
		
		if ( $filterByType )
		{
			$where[] = array( 'report_type=?', $filterByType );
		}
		
		return iterator_to_array( \IPS\Db::i()->select( '*', 'core_rc_reports', $where, 'date_reported' ) );
	}
	
	/**
	 * Rebuild
	 *
	 * @return	void
	 */
	public function rebuild()
	{
		$numReports = \IPS\Db::i()->select( 'COUNT(*)', 'core_rc_reports', array( 'rid=?', $this->id ) )->first();
		if ( !$numReports )
		{
			$this->delete();
		}
		$this->num_reports = $numReports;
		
		$numComments = \IPS\Db::i()->select( 'COUNT(*)', 'core_rc_comments', array( 'rid=?', $this->id ) )->first();
		$this->num_comments = $numComments;
		
		$this->save();
	}
	
	/**
	 * Delete Report
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();
	
		\IPS\Db::i()->delete( 'core_rc_reports', array( 'rid=?', $this->id ) );
		\IPS\Db::i()->delete( 'core_automatic_moderation_pending', array( 'pending_object_class=? and pending_object_id=?', $this->class, $this->content_id ) );
	}
	
	/**
	 * Lock auto moderation to prevent auto moderation from changing the status again
	 *
	 * @return void
	 */
	public function lockAutoModeration()
	{
		$this->auto_moderation_exempt = 1;
		$this->save();
	}
	
	/**
	 * Lock auto moderation to prevent auto moderation from changing the status again
	 *
	 * @return bool
	 */
	public function isAutoModerationLocked()
	{
		return (boolean) $this->auto_moderation_exempt;
	}
	
	/**
	 * Run any automatic moderation
	 *
	 * @return bool|void
	 */
	public function runAutomaticModeration()
	{
		if ( ! \IPS\Settings::i()->automoderation_enabled )
		{
			return FALSE;
		}
		
		/* If it is auto moderation locked, skip it */
		if ( $this->isAutoModerationLocked() )
		{
			return FALSE;
		}
		
		$className = $this->class;
		try
		{
			$reportedContent = $className::load( $this->content_id );
		}
		catch ( \OutOfRangeException $ex )
		{
			/* No content, no moderation, no cry */
			return FALSE;
		}

		/* Is automatic moderation supported for this content type? */
		if ( !( $reportedContent instanceof \IPS\Content\Hideable ) )
		{
			return FALSE;
		}

		/* Fetch a count of report flags so far */
		$typeCounts = $this->getReportTypeCounts();
		$ruleToUse  = NULL;
		
		/* Loop over all group promotion rules and get the last one that matches us */
		foreach( \IPS\core\Reports\Rules::roots() as $rule )
		{
			if( $rule->enabled and $rule->matches( $reportedContent->author(), $typeCounts ) )
			{
				$ruleToUse = $rule->id;
			}
		}

		/* If there's no rule, return now */
		if( $ruleToUse === NULL )
		{
			/* It is possible a few reports have been removed so the threshold is no longer met, delete any pending rows if this is the case */
			\IPS\Db::i()->delete( 'core_automatic_moderation_pending', array( 'pending_object_class=? and pending_object_id=?', $className, $this->content_id ) );
		}
		else
		{
			/* Log the bad boy for actioning later. A small delay allows users to retract their warning */
			\IPS\Db::i()->replace( 'core_automatic_moderation_pending', array(
				'pending_object_class' => $className,
				'pending_object_id'    => $this->content_id,
				'pending_report_id'	   => $this->id,
				'pending_added'		   => time(),
				'pending_rule_id'	   => $ruleToUse
			) );
		}
	}
	
	/**
	 * Fetch the report type counts
	 *
	 * @param	boolean	$totalOnly		Return either an int of the total counts, or an array with the breakdown
	 * @return array( 1 => 10, 2 => 3 )|INT
	 */
	public function getReportTypeCounts( $totalOnly=false )
	{
		$typeCounts = array();
		$total = 0;
		$seen = array();
		foreach( \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'rid=? and report_type > 0', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER ) as $row )
		{
			if ( isset( $seen[ $row['report_by'] ] ) )
			{
				continue;
			}
			
			$seen[ $row['report_by'] ] = true;
			
			$typeCounts[ $row['report_type'] ][ $row['report_by'] ] = true;
		}
		
		$return = array();
		foreach( array_keys( \IPS\core\Reports\Types::roots() ) as $type )
		{
			if ( isset( $typeCounts[ $type ] ) )
			{
				$return[ $type ] = \count( $typeCounts[ $type ] );
				$total += \count( $typeCounts[ $type ] );
			}
			else
			{
				$return[ $type ] = 0;
			}
		}
		
		return $totalOnly ? $total : $return;
	}
	
	/**
	 * Return the filters that are available for selecting table rows
	 *
	 * @return	array
	 */
	public static function getTableFilters()
	{
		return array(
			'read', 'unread', 'report_status_1', 'report_status_2', 'report_status_3'
		);
	}

	/**
	 * Build the where clause for finding reports
	 *
	 * @param \IPS\Member|null $member	Member to base permissions on
	 * @return array
	 */
	public static function where( \IPS\Member $member=NULL ): array
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$classWhere = [];
		$extensionWhere = [];
		$viewPermIds = [];
		$perms = $member->modPermissions();

		foreach( \IPS\Db::i()->select( 'app, perm_id', 'core_permission_index', \IPS\Db::i()->findInSet( 'perm_view', $member->permissionArray() ) . " OR perm_view='*'" ) as $perm )
		{
			$viewPermIds[ $perm['app'] ][] = $perm['perm_id'];
		}

		foreach ( array_merge( ['IPS\core\Messenger\Conversation', 'IPS\core\Messenger\Message'], array_values( \IPS\Content::routedClasses( FALSE, TRUE ) ) ) as $class )
		{
			/* Got nodes? */
			$item = NULL;
			if ( is_subclass_of( $class, "IPS\Content\Item" ) )
			{
				$item = $class;
			}
			else if ( is_subclass_of( $class, "IPS\Content\Comment" ) or is_subclass_of( $class, "IPS\Content\Review" ) )
			{
				if ( isset( $class::$itemClass ) )
				{
					$item = $class::$itemClass;
				}
			}

			if ( ! $item )
			{
				continue;
			}

			$extensionWhereClause = '';
			if ( isset( $item::$containerNodeClass ) )
			{
				$container = $item::$containerNodeClass;
				if ( isset( $container::$modPerm ) )
				{
					if ( isset( $perms[$container::$modPerm] ) and $perms[$container::$modPerm] != '*' and $perms[$container::$modPerm] != '-1')
					{
						$extensionWhereClause = ' AND ' . \IPS\Db::i()->in( 'node_id', $perms[$container::$modPerm] );
					}
				}
			}

			$workingClass = $class;
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
				if ( !isset( $permIds[ $class::$application ][ $workingClass::$permissionMap['read'] ] ) )
				{
					$permIds[ $class::$application ][ $workingClass::$permissionMap['read'] ] = iterator_to_array( \IPS\Db::i()->select( 'perm_id', 'core_permission_index', "(" . \IPS\Db::i()->findInSet( 'perm_' . $workingClass::$permissionMap['read'], \IPS\Member::loggedIn()->permissionArray() ) . " OR perm_" . $workingClass::$permissionMap['read'] . "='*' ) AND app='{$class::$application}'" ) );
				}

				if( isset( $viewPermIds[ $class::$application ] ) AND !empty( $permIds[ $class::$application ][ $workingClass::$permissionMap['read'] ] ) )
				{
					$classWhere[] = "( ( class='" . str_replace( '\\', '\\\\', $class ) . "' AND ( perm_id IN(" . implode( ',', array_intersect( $viewPermIds[ $class::$application ], $permIds[ $class::$application ][ $workingClass::$permissionMap['read'] ] ) ) . ") ) OR perm_id IS NULL )" . $extensionWhereClause . ")";
				}
			}
			else
			{
				if ( isset( $class::$application ) and isset( $viewPermIds[ $class::$application ] ) )
				{
					$classWhere[] = "( ( class='" . str_replace( '\\', '\\\\', $class ) . "' AND ( perm_id IN(" . implode( ',', $viewPermIds[ $class::$application ] ) . ") ) OR perm_id IS NULL )" . $extensionWhereClause . ")";
				}
				else
				{
					$classWhere[] = "( class='" . str_replace( '\\', '\\\\', $class ) . "' )";
				}
			}
		}

		return [ '(' . implode( ' OR ', array_values( $classWhere ) ) . ')' ];
	}

	/**
	 * Can view this entry
	 *
	 * @param	\IPS\Member|NULL	$member		The member or NULL for currently logged in member.
	 * @return	bool
	 */
	public function canView( $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		$return = parent::canView($member);

		if ( $return AND $member->modPermission('can_view_reports') )
		{
			return TRUE;
		}
		return FALSE;
	}
}
