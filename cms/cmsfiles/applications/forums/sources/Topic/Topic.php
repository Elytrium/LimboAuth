<?php
/**
 * @brief		Topic Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		8 Jan 2014
 */

namespace IPS\forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Topic Model
 */
class _Topic extends \IPS\Content\Item implements
	\IPS\Content\Permissions,
	\IPS\Content\Pinnable, \IPS\Content\Lockable, \IPS\Content\Hideable, \IPS\Content\Featurable,
	\IPS\Content\Tags,
	\IPS\Content\Followable,
	\IPS\Content\Shareable,
	\IPS\Content\ReadMarkers,
	\IPS\Content\Polls, \SplObserver,
	\IPS\Content\Ratings,
	\IPS\Content\Searchable,
	\IPS\Content\Embeddable,
	\IPS\Content\MetaData,
	\IPS\Content\Anonymous,
	\IPS\Content\FuturePublishing
{
	use \IPS\forums\Topic\LiveTopic, \IPS\Content\Reactable, \IPS\Content\Reportable, \IPS\Content\Statistics, \IPS\Content\ViewUpdates, \IPS\Content\Solvable { toggleSolveComment as protected _toggleSolveComment; }
	
	/**
	 * @brief	Not archived
	 */
	const ARCHIVE_NOT = 0;

	/**
	 * @brief	Archiving completed
	 */
	const ARCHIVE_DONE = 1;

	/**
	 * @brief	In the process of being archived
	 */
	const ARCHIVE_WORKING = 2;

	/**
	 * @brief	Excluded from archiving
	 */
	const ARCHIVE_EXCLUDE = 3;

	/**
	 * @brief	Flagged to restore from archive
	 */
	const ARCHIVE_RESTORE = 4;
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'tid';

	/**
	 * @brief	Application
	 */
	public static $application = 'forums';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'forums';

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'forums_topics';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = '';
			
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'author'				=> 'starter_id',
		'author_name'			=> 'starter_name',
		'container'				=> 'forum_id',
		'date'					=> 'start_date',
		'title'					=> 'title',
		'num_comments'			=> 'posts',
		'unapproved_comments'	=> 'topic_queuedposts',
		'hidden_comments'		=> 'topic_hiddenposts',
		'first_comment_id'		=> 'topic_firstpost',
		'last_comment'			=> array( 'last_post', 'last_real_post' ),
		'last_comment_by'		=> 'last_poster_id',
		'last_comment_name'		=> 'last_poster_name',
		'views'					=> 'views',
		'approved'				=> 'approved',
		'pinned'				=> 'pinned',
		'poll'					=> 'poll_state',
		'status'				=> 'state',
		'moved_to'				=> 'moved_to',
		'moved_on'				=> 'moved_on',
		'featured'				=> 'featured',
		'state'					=> 'state',
		'updated'				=> 'last_post',
		'meta_data'				=> 'topic_meta_data',
		'solved_comment_id'		=> 'topic_answered_pid',
		'is_anon'				=> 'is_anon',
		'last_comment_anon'		=> 'last_poster_anon',
		'is_future_entry'		=> 'is_future_entry',
		'future_date'           => 'publish_date',
	);

	/**
	 * @brief	Title
	 */
	public static $title = 'topic';
	
	/**
	 * @brief	Node Class
	 */
	public static $containerNodeClass = 'IPS\forums\Forum';
	
	/**
	 * @brief	[Content\Item]	Comment Class
	 */
	public static $commentClass = 'IPS\forums\Topic\Post';

	/**
	 * @brief	Archived comment class
	 */
	public static $archiveClass = 'IPS\forums\Topic\ArchivedPost';

	/**
	 * @brief	[Content\Item]	First "comment" is part of the item?
	 */
	public static $firstCommentRequired = TRUE;
	
	/**
	 * @brief	[Content\Comment]	Language prefix for forms
	 */
	public static $formLangPrefix = 'topic_';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'comments';
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'topic';
	
	/**
	 * @brief	Hover preview
	 */
	public $tableHoverUrl = TRUE;

	/**
	 * @brief Setting name for show_meta
	 */
	public static $showMetaSettingKey = 'forums_topics_show_meta';
	
	/**
	 * Callback from \IPS\Http\Url\Inernal::correctUrlFromVerifyClass()
	 *
	 * This is called when verifying the *the URL currently being viewed* is correct, before calling self::loadFromUrl()
	 * Can be used if there is a more effecient way to load and cache the objects that will be used later on that page
	 *
	 * @param	\IPS\Http\Url	$url	The URL of the page being viewed, which belongs to this class
	 * @return	void
	 */
	public static function preCorrectUrlFromVerifyClass( \IPS\Http\Url $url )
	{
		\IPS\forums\Forum::loadIntoMemory();
	}

	/**
	 * Check the request for legacy parameters we may need to redirect to
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkForLegacyParameters()
	{
		/* Check for any changes in the parent, i.e. st=20 */
		$url = parent::checkForLegacyParameters();

		$paramsToSet	= array();
		$paramsToUnset	= array();

		/* view=findpost needs to go to do=findComment */
		if( isset( \IPS\Request::i()->view ) AND \IPS\Request::i()->view == 'findpost' )
		{
			$paramsToSet['do']		= 'findComment';
			$paramsToUnset[]		= 'view';
		}

		/* p=123 needs to go to comment=123 */
		if( isset( \IPS\Request::i()->p ) )
		{
			$paramsToSet['do']		= 'findComment';
			$paramsToSet['comment']		= \IPS\Request::i()->p;
			$paramsToUnset[]		= 'p';
		}

		/* Did we have any? */
		if( \count( $paramsToSet ) )
		{
			if( $url === NULL )
			{
				$url = $this->url();
			}

			if( \count( $paramsToUnset ) )
			{
				$url = $url->stripQueryString( $paramsToUnset );
			}

			$url = $url->setQueryString( $paramsToSet );

			return $url;
		}

		return $url;
	}

	/**
	 * Set custom posts per page setting
	 *
	 * @return int
	 */
	public static function getCommentsPerPage()
	{
		return \IPS\Settings::i()->forums_posts_per_page;
	}
	
	/**
	 * Get comment count
	 *
	 * @return	int
	 */
	public function commentCount()
	{
		$count = parent::commentCount();
		
		if ( $this->isQuestion() )
		{
			$count--;
		}
		
		return $count;
	}

	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 * @see		\IPS\forums\Topic\Post::incrementPostCount()
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return FALSE;
	}

	/**
	 * Get items with permission check
	 *
	 * @param	array		$where				Where clause
	 * @param	string		$order				MySQL ORDER BY clause (NULL to order by date)
	 * @param	int|array	$limit				Limit clause
	 * @param	string|NULL	$permissionKey		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index or NULL to ignore permissions
	 * @param	mixed		$includeHiddenItems	Include hidden items? NULL to detect if currently logged in member has permission, -1 to return public content only, TRUE to return unapproved content and FALSE to only return unapproved content the viewing member submitted
	 * @param	int			$queryFlags			Select bitwise flags
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinComments		If true, will join comment data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinReviews		If true, will join review data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$countOnly			If true will return the count
	 * @param	array|null	$joins				Additional arbitrary joins for the query
	 * @param	mixed		$skipPermission		If you are getting records from a specific container, pass the container to reduce the number of permission checks necessary or pass TRUE to skip conatiner-based permission. You must still specify this in the $where clause
	 * @param	bool		$joinTags			If true, will join the tags table
	 * @param	bool		$joinAuthor			If true, will join the members table for the author
	 * @param	bool		$joinLastCommenter	If true, will join the members table for the last commenter
	 * @param	bool		$showMovedLinks		If true, moved item links are included in the results
	 * @param	array|null	$location			Array of item lat and long
	 * @return	\IPS\Patterns\ActiveRecordIterator|int
	 */
	public static function getItemsWithPermission( $where=array(), $order=NULL, $limit=10, $permissionKey='read', $includeHiddenItems=\IPS\Content\Hideable::FILTER_AUTOMATIC, $queryFlags=0, \IPS\Member $member=NULL, $joinContainer=FALSE, $joinComments=FALSE, $joinReviews=FALSE, $countOnly=FALSE, $joins=NULL, $skipPermission=FALSE, $joinTags=TRUE, $joinAuthor=TRUE, $joinLastCommenter=TRUE, $showMovedLinks=FALSE, $location=NULL )
	{
		$where = static::getItemsWithPermissionWhere( $where, $permissionKey, $member, $joinContainer, $skipPermission );
		return parent::getItemsWithPermission( $where, $order, $limit, $permissionKey, $includeHiddenItems, $queryFlags, $member, $joinContainer, $joinComments, $joinReviews, $countOnly, $joins, $skipPermission, $joinTags, $joinAuthor, $joinLastCommenter, $showMovedLinks );
	}
	
	/**
	 * Additional WHERE clauses for Follow view
	 *
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	array		$joins				Other joins
	 * @return	array
	 */
	public static function followWhere( &$joinContainer, &$joins )
	{
		$joinContainer = FALSE;
		return array_merge( parent::followWhere( $joinContainer, $joins ), static::getItemsWithPermissionWhere( array(), 'read', NULL, $joinContainer ) );
	}
	
	/**
	 * WHERE clause for getItemsWithPermission
	 *
	 * @param	array		$where				Current WHERE clause
	 * @param	string		$permissionKey		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	mixed		$skipPermission		If you are getting records from a specific container, pass the container to reduce the number of permission checks necessary or pass TRUE to skip container-based permission. You must still specify this in the $where clause
	 * @return	array
	 */
	public static function getItemsWithPermissionWhere( $where, $permissionKey, $member, &$joinContainer, $skipPermission=FALSE )
	{
		/* Don't show topics in password protected forums */
		if ( !$skipPermission and \in_array( $permissionKey, array( 'view', 'read' ) ) )
		{
			$joinContainer = TRUE;
			$member = $member ?: \IPS\Member::loggedIn();
			$containerClass = static::$containerNodeClass;
			
			if ( $containerClass::customPermissionNodes() )
			{		
				$whereString = 'forums_forums.password=? OR ' . \IPS\Db::i()->findInSet( 'forums_forums.password_override', $member->groups );
				$whereParams = array( NULL );
				if ( \IPS\Dispatcher::hasInstance() AND $member->member_id === \IPS\Member::loggedIn()->member_id )
				{
					foreach ( \IPS\Request::i()->cookie as $k => $v )
					{
						if ( mb_substr( $k, 0, 13 ) === 'ipbforumpass_' )
						{
							$whereString .= ' OR ( forums_forums.id=? AND MD5(forums_forums.password)=? )';
							$whereParams[] = (int) mb_substr( $k, 13 );
							$whereParams[] = $v;
						}
					}
				}
				
				$where['container'][] = array_merge( array( '( ' . $whereString . ' )' ), $whereParams );
			}
		}
				
		/* Don't show topics from forums in which topics only show to the poster */
		if ( $skipPermission !== TRUE and \in_array( $permissionKey, array( 'view', 'read' ) ) )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			if ( $skipPermission instanceof \IPS\forums\Forum )
			{
				if ( !$skipPermission->can_view_others )
				{
					if ( !$member->member_id )
					{
						return array( '1=0' );
					}
					if ( $club = $skipPermission->club() )
					{
						if ( !$club->isModerator( $member ) )
						{
							$where['item'][] = array( 'forums_topics.starter_id=?', $member->member_id );
						}
					}
					elseif ( !$member->modPermission('can_read_all_topics') or ( \is_array( $member->modPermission('forums') ) and !\in_array( $skipPermission->_id, $member->modPermission('forums') ) ) )
					{
						$where['item'][] = array( 'forums_topics.starter_id=?', $member->member_id );
					}
				}
			}
			elseif ( !$member->modPermission('can_read_all_topics') or \is_array( $member->modPermission('forums') ) or !$member->modPermission('can_access_all_clubs') )
			{
				$joinContainer = TRUE;
				
				if ( !$member->member_id )
				{
					$where[] = array( 'forums_forums.can_view_others=1' );
				}
				else
				{
					$whereClause = array( 'forums_forums.can_view_others=1 OR forums_topics.starter_id=?', (int) $member->member_id );
					$ors = array();
					
					if ( $member->modPermission('can_read_all_topics') )
					{
						$forums = $member->modPermission('forums');
						if ( isset( $forums ) and \is_array( $forums ) )
						{
							$ors[] = '( forums_forums.club_id IS NULL AND ' . \IPS\Db::i()->in( 'forums_topics.forum_id', $forums ) . ')';
						}
						else
						{
							$ors[] = 'forums_forums.club_id IS NULL';
						}
					}
					if ( $member->modPermission('can_access_all_clubs') )
					{
						$ors[] = 'forums_forums.club_id IS NOT NULL';
					}
					elseif ( $moderatorClubIds = $member->clubs( FALSE, TRUE ) )
					{
						$ors[] = \IPS\Db::i()->in( 'forums_forums.club_id', $moderatorClubIds );
					}
					
					if ( $ors )
					{
						$whereClause[0] = "( {$whereClause[0]} OR " . implode( ' OR ', $ors ) . ' )';
					}
					else
					{
						$whereClause[0] = "( {$whereClause[0]} )";
					}
					
					$where[] = $whereClause;
				}				
			}
		}

		/* Don't show topics in forums we can't view because our post count is too low */
		if ( !$skipPermission and \in_array( $permissionKey, array( 'view', 'read' ) ) )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$joinContainer = TRUE;
			$where['container'][] = array( 'forums_forums.min_posts_view<=?', $member->member_posts );
		}
		
		/* Return */
		return $where;
	}
	
	/**
	 * Total item \count(including children)
	 *
	 * @param	\IPS\Node\Model	$container			The container
	 * @param	bool			$includeItems		If TRUE, items will be included (this should usually be true)
	 * @param	bool			$includeComments	If TRUE, comments will be included
	 * @param	bool			$includeReviews		If TRUE, reviews will be included
	 * @param	int				$depth				Used to keep track of current depth to avoid going too deep
	 * @return	int|NULL|string	When depth exceeds 10, will return "NULL" and initial call will return something like "100+"
	 * @note	This method may return something like "100+" if it has lots of children to avoid exahusting memory. It is intended only for display use
	 * @note	This method includes counts of hidden and unapproved content items as well
	 */
	public static function contentCount( \IPS\Node\Model $container, $includeItems=TRUE, $includeComments=FALSE, $includeReviews=FALSE, $depth=0 )
	{
		return parent::contentCount( $container, FALSE, TRUE, $includeReviews, $depth );
	}

	/**
	 * Total item, items only \count(including children)
	 *
	 * @param	\IPS\Node\Model	$container			The container
	 * @param	bool			$includeItems		If TRUE, items will be included (this should usually be true)
	 * @param	bool			$includeComments	If TRUE, comments will be included
	 * @param	bool			$includeReviews		If TRUE, reviews will be included
	 * @param	int				$depth				Used to keep track of current depth to avoid going too deep
	 * @return	int|NULL|string	When depth exceeds 10, will return "NULL" and initial call will return something like "100+"
	 * @note	This method may return something like "100+" if it has lots of children to avoid exahusting memory. It is intended only for display use
	 * @note	This method includes counts of hidden and unapproved content items as well
	 */
	public static function contentCountItemsOnly( \IPS\Node\Model $container )
	{
		return parent::contentCount( $container, TRUE, FALSE, FALSE, 0 );
	}
	
	/**
	 * Get elements for add/edit form
	 *
	 * @param	\IPS\Content\Item|NULL	$item		The current item if editing or NULL if creating
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @return	array
	 */
	public static function formElements( $item=NULL, \IPS\Node\Model $container=NULL )
	{
		$formElements = parent::formElements( $item, $container );
		
		/* Password protected */
		if ( $container !== NULL AND !$container->loggedInMemberHasPasswordAccess() )
		{
			$password = $container->password;
			$formElements['password'] = new \IPS\Helpers\Form\Password( 'password', NULL, TRUE, array(), function( $val ) use ( $password )
			{
				if ( $val != $password )
				{
					throw new \DomainException( 'forum_password_bad' );
				}
			} );
		}

		/* Build the topic state toggles */
		$options = array();
		$toggles = array();
		$current = array();
		if ( static::modPermission( 'lock', NULL, $container ) )
		{
			$options['lock'] = 'create_topic_locked';
			$toggles['lock'] = array( 'create_topic_locked' );
			if( $item and $item->locked() )
			{
				$current[] = 'lock';
			}
		}
		
		if ( static::modPermission( 'pin', NULL, $container ) )
		{
			$options['pin'] = 'create_topic_pinned';
			$toggles['pin'] = array( 'create_topic_pinned' );
			if( $item and $item->mapped('pinned') )
			{
				$current[] = 'pin';
			}
		}
		$canHide = ( $item ) ? $item->canHide() : ( \IPS\Member::loggedIn()->group['g_hide_own_posts'] == '1' or \in_array( 'IPS\forums\Topics', explode( ',', \IPS\Member::loggedIn()->group['g_hide_own_posts'] ) ) );
		if ( static::modPermission( 'hide', NULL, $container ) or $canHide )
		{
			$options['hide'] = 'create_topic_hidden';
			$toggles['hide'] = array( 'create_topic_hidden' );
			if( $item and $item->hidden() === -1 )
			{
				$current[] = 'hide';
			}
		}
		
		if ( static::modPermission( 'feature', NULL, $container ) )
		{
			$options['feature'] = 'create_topic_featured';
			$toggles['feature'] = array( 'create_topic_featured' );
			if( $item and $item->mapped('featured') )
			{
				$current[] = 'feature';
			}
		}

		if ( \count( $options ) or \count( $toggles ) )
		{
			$formElements['topic_state'] = new \IPS\Helpers\Form\CheckboxSet( 'topic_create_state', $current, FALSE, array(
				'options' 	=> $options,
				'toggles'	=> $toggles,
				'multiple'	=> TRUE
			) );	
		}		
		
		if ( static::modPermission( 'lock', NULL, $container ) )
		{
			/* Add lock/unlock options */
			if ( static::modPermission( 'unlock', NULL, $container ) )
			{
				$formElements['topic_open_time'] = new \IPS\Helpers\Form\Date( 'topic_open_time', ( $item and $item->topic_open_time ) ? \IPS\DateTime::ts( $item->topic_open_time ) : NULL, FALSE, array( 'time' => TRUE ) );
			}
			$formElements['topic_close_time'] = new \IPS\Helpers\Form\Date( 'topic_close_time', ( $item and $item->topic_close_time ) ? \IPS\DateTime::ts( $item->topic_close_time ) : NULL, FALSE, array( 'time' => TRUE ) );
		}

		/* Poll always needs to go on the end */
		if ( isset( $formElements['poll'] ) )
		{
			$poll = $formElements['poll'];
			unset( $formElements['poll'] );
			$formElements['poll'] = $poll;
		}

		return $formElements;
	}
	
	/**
	 * Process create/edit form
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	public function processForm( $values )
	{
		parent::processForm( $values );
		
		if ( isset( $values['password'] ) )
		{
			/* Set Cookie */
			$this->container()->setPasswordCookie( $values['password'] );
		}

		/* Moderator actions */
		if ( isset( $values['topic_create_state'] ) )
		{
			if ( static::modPermission( 'lock', NULL, $this->container() ) )
			{
				$this->state = ( \in_array( 'lock', $values['topic_create_state'] ) ) ? 'closed' : 'open';
			}
			
			if ( static::modPermission( 'pin', NULL, $this->container() ) )
			{
				$this->pinned = ( \in_array( 'pin', $values['topic_create_state'] ) ) ? 1 : 0;
			}

			if ( static::modPermission( 'feature', NULL, $this->container() ) )
			{
				$this->featured = ( \in_array( 'feature', $values['topic_create_state'] ) ) ? 1 : 0;
			}
		}

		if ( static::modPermission( 'lock', NULL, $this->container() ) )
		{
			/* Set open/close time */
			$this->topic_open_time = !empty( $values['topic_open_time'] ) ? $values['topic_open_time']->getTimestamp() : 0;
			$this->topic_close_time = !empty( $values['topic_close_time'] ) ? $values['topic_close_time']->getTimestamp() : 0;
			
			if( isset( $values['topic_create_state'] ) and !\in_array( 'lock', $values['topic_create_state'] ) )
			{
				$this->state = 'open';
			}

			/* If open time is before close time, close now */
			if ( $this->topic_open_time and $this->topic_close_time and $this->topic_open_time < $this->topic_close_time )
			{
				$this->state = 'closed';
			}

			/* If we specified an unlock time, but no lock time, make sure the topic is locked */
			if ( $this->topic_open_time and !$this->topic_close_time )
			{
				$this->state = 'closed';
			}
		}
	}
	
	/**
	 * Process created object AFTER the object has been created
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The first comment
	 * @param	array						$values		Values from form
	 * @return	void
	 */
	protected function processAfterCreate( $comment, $values )
	{
		$this->processAfterCreateOrEdit( $values );
		
		parent::processAfterCreate( $comment, $values );
	}
	
	/**
	 * Process after the object has been edited on the front-end
	 *
	 * @param	array	$values		Values from form
	 * @return	void
	 */
	public function processAfterEdit( $values )
	{
		$this->processAfterCreateOrEdit( $values );
		
		/* Initial Comment */
		parent::processAfterEdit( $values );
		
		/* Topic changed? */
		if ( ! $this->hidden() and ( $this->tid === $this->container()->last_id ) )
		{
			$this->container()->seo_last_title = $this->title_seo;
			$this->container()->last_title     = $this->title;
			$this->container()->save();
			
			foreach( $this->container()->parents() AS $parent )
			{
				if ( ( $this->tid === $parent->last_id ) and ( $this->title_seo !== $parent->seo_last_title ) )
				{
					$parent->seo_last_title		= $this->title_seo;
					$parent->last_title			= $this->title;
					$parent->save();
				}
			}
		}
	}
	
	/**
	 * Process after the object has been edited or created on the front-end
	 *
	 * @param	array	$values		Values from form
	 * @return	void
	 */
	protected function processAfterCreateOrEdit( $values )
	{
		/* Moderator actions */
		if ( isset( $values['topic_create_state'] ) )
		{
			if( \in_array( 'hide', $values['topic_create_state'] ) )
			{
				if ( $this->canHide() )
				{
					$this->hide( NULL );
				}
			}
			elseif( $this->hidden() and $this->hidden() !== 1 and $this->canUnhide() )
			{
				$this->unhide( NULL );
			}
		}
	}

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();
	
	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=forums&module=forums&controller=topic&id=';
	
	/**
	 * @brief	URL Template
	 */
	public static $urlTemplate = 'forums_topic';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'title_seo';

	/**
	 * Stats for table view
	 *
	 * @param	bool	$includeFirstCommentInCommentCount	Determines whether the first comment should be inlcluded in the comment \count(e.g. For "posts", use TRUE. For "replies", use FALSE)
	 * @return	array
	 */
	public function stats( $includeFirstCommentInCommentCount=TRUE )
	{
		if ( $this->popular_time !== NULL and $this->popular_time > time() )
		{
			$this->hotStats[] = 'forums_comments';
			$this->hotStats[] = 'answers_no_number';
		}
		
		$stats = parent::stats( $includeFirstCommentInCommentCount );

		if( !$includeFirstCommentInCommentCount )
		{
			if( isset( $stats['comments'] ) )
			{
				$stats = array_reverse( $stats );

				if( $this->container()->forums_bitoptions['bw_enable_answers'] )
				{
					$stats['answers_no_number']	= $stats['comments'];
				}
				else
				{
					$stats['forums_comments']	= $stats['comments'];
				}

				unset( $stats['comments'] );
				$stats = array_reverse( $stats );
			}
		}

		return $stats;
	}

	/**
	 * Set the new popular time if needed
	 *
	 */
	public function rebuildPopularTime()
	{
		$popularNowSettings = json_decode( \IPS\Settings::i()->forums_popular_now, TRUE );

		if ( $popularNowSettings['posts'] and $popularNowSettings['minutes'] )
		{
			$popularNowInterval = new \DateInterval( 'PT' . $popularNowSettings['minutes'] . 'M' );

			$comments = iterator_to_array( new \IPS\Patterns\ActiveRecordIterator(
				\IPS\Db::i()->select( '*', 'forums_posts', array( 'queued IN(0,2) AND post_date >? AND topic_id=?', \IPS\DateTime::create()->sub( $popularNowInterval )->getTimestamp(), $this->tid ), 'post_date DESC', NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER ),
				'IPS\\forums\\Topic\\Post'
			) );
			if ( \count( $comments ) >= $popularNowSettings['posts'] )
			{
				$commentToBasePopularNowTimeOff = \array_slice( $comments, ( $popularNowSettings['posts'] - 1 ), 1 );
				$commentToBasePopularNowTimeOff = array_pop( $commentToBasePopularNowTimeOff );

				$this->popular_time = \IPS\DateTime::ts( $commentToBasePopularNowTimeOff->post_date )->add( $popularNowInterval )->getTimestamp();
				$this->save();
			}
			elseif ( $this->popular_time !== NULL )
			{
				$this->popular_time = NULL;
				$this->save();
			}
		}
	}
	
	/**
	 * Set name
	 *
	 * @param	string	$title	Title
	 * @return	void
	 */
	public function set_title( $title )
	{
		$this->_data['title'] = $title;
		$this->_data['title_seo'] = \IPS\Http\Url\Friendly::seoTitle( $title );
	}

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_title_seo()
	{
		if( !$this->_data['title_seo'] )
		{
			$this->title_seo	= \IPS\Http\Url\Friendly::seoTitle( $this->title );
			$this->save();
		}

		return $this->_data['title_seo'] ?: \IPS\Http\Url\Friendly::seoTitle( $this->title );
	}

	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		if ( !parent::canView( $member ) )
		{
			return FALSE;
		}
		
		if ( $minPostsToView = $this->container()->min_posts_view )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			if ( $minPostsToView > $member->member_posts )
			{
				return FALSE;
			}
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $member != $this->author() and !$this->container()->memberCanAccessOthersTopics( $member ) )
		{
			return FALSE;
		}
				
		return TRUE;
	}
	
	/**
	 * Search Index Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function searchIndexPermissions()
	{
		$return = $this->container()->searchIndexPermissions();
		
		if ( !$this->container()->can_view_others )
		{
			/* If the search index permissions are empty, just return now because no one can see content in this forum */
			if( !$return )
			{
				return $return;
			}

			$return = $this->container()->permissionsThatCanAccessAllTopics();

			if ( $this->starter_id )
			{
				$return[] = "m{$this->starter_id}";
			}

			$return = implode( ',', $return );
		}
				
		return $return;
	}
			
	/**
	 * Can Rate?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function canRate( \IPS\Member $member = NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return $this->container()->forum_allow_rating and parent::canRate( $member );
	}
	
	/**
	 * Can create polls?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canCreatePoll( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canCreatePoll( $member, $container ) and ( $container === NULL or $container->allow_poll );
	}
	
	/**
	 * SplObserver notification that poll has been voted on
	 *
	 * @param	\SplSubject	$subject	Subject
	 * @return	void
	 */
	public function update( \SplSubject $subject )
	{
		$this->last_vote = time();
		
		$this->save();
	}
	
	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'forums.css', 'forums', 'front' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'forums_responsive.css', 'forums', 'front' ) );
		return array( \IPS\Theme::i()->getTemplate( 'global', 'forums', 'front' ), 'rows' );
	}

	/**
	 * Table: Get rows
	 *
	 * @param	array	$rows	Rows to show
	 * @return	void
	 */
	public static function tableGetRows( $rows )
	{
		$openIds = array();
		$closeIds = array();
		$timeNow = time();
		
		foreach ( $rows as $topic )
		{
			if ( $topic->state != 'link' )
			{
				$locked = $topic->locked();
				if ( $locked and $topic->topic_open_time and $topic->topic_open_time < $timeNow )
				{
					$openIds[] = $topic->tid;
					$topic->state = 'open';
				}
				if ( !$locked and $topic->topic_close_time and $topic->topic_close_time < $timeNow )
				{
					$closeIds[] = $topic->tid;
					$topic->state = 'closed';
				}
			}
		}

        if ( !empty( $openIds ) )
        {
            \IPS\Db::i()->update( 'forums_topics', array( 'state' => 'open', 'topic_open_time' => 0 ), \IPS\Db::i()->in( 'tid', $openIds ) );
        }
        if ( !empty( $closeIds ) )
        {
            \IPS\Db::i()->update( 'forums_topics', array( 'state' => 'closed', 'topic_close_time' => 0 ), \IPS\Db::i()->in( 'tid', $closeIds ) );
        }
	}

	/**
	 * Should new items be moderated?
	 *
	 * @param	\IPS\Member		$member							The member posting
	 * @param	\IPS\Node\Model	$container						The container
	 * @param	bool			$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public static function moderateNewItems( \IPS\Member $member, \IPS\Node\Model $container = NULL, $considerPostBeforeRegistering = FALSE )
	{
		if ( $container and ( $container->preview_posts == 1 or $container->preview_posts == 2 ) and !$member->group['g_avoid_q'] )
		{
			return !static::modPermission( 'approve', $member, $container );
		}

		return parent::moderateNewItems( $member, $container, $considerPostBeforeRegistering );
	}

	/**
	 * Should new comments be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewComments( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		if ( ( $this->container()->preview_posts == 1 or $this->container()->preview_posts == 3 ) and !$member->group['g_avoid_q'] )
		{
			return TRUE;
		}
		
		return parent::moderateNewComments( $member, $considerPostBeforeRegistering );
	}
	
	/**
	 * Move
	 *
	 * @param	\IPS\Node\Model	$container	Container to move to
	 * @param	bool			$keepLink	If TRUE, will keep a link in the source
	 * @return	void
	 */
	public function move( \IPS\Node\Model $container, $keepLink=FALSE )
	{
		if(	!$container->sub_can_post or $container->redirect_url )
		{
			throw new \InvalidArgumentException;
		}

		parent::move( $container, $keepLink );
		\IPS\Db::i()->update( 'forums_question_ratings', array( 'forum' => $container->_id ), array( 'topic=?', $this->tid ) );

		/* While you can't normally move archived topics, when you mass manage content from the AdminCP by using the menu next to the forum,
			this still allows topics to be moved. If we don't update the archive forum database the forum counts will be off */
		if( $this->isArchived() )
		{
			try
			{
				\IPS\forums\Topic\ArchivedPost::db()->update( 'forums_archive_posts', array( 'archive_forum_id' => $container->_id ), array( 'archive_topic_id=?', $this->tid ) );
			}
			/* catch db exceptions if e.g. if the connection credentials didn't work or if the database doesn't exist anymore */
			catch ( \IPS\Db\Exception $e ){}
		}
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();

		if( \in_array( $this->archive_status, array( static::ARCHIVE_DONE, static::ARCHIVE_WORKING, static::ARCHIVE_RESTORE ) ) )
		{
			try
			{
				\IPS\forums\Topic\ArchivedPost::db()->delete( 'forums_archive_posts', array( 'archive_topic_id=?', $this->tid ) );
			}
			/* catch db exceptions if e.g. if the connection credentials didn't work or if the database doesn't exist anymore */
			catch ( \IPS\Db\Exception $e ){}
		}

		\IPS\Db::i()->delete( 'forums_question_ratings', array( 'topic=?', $this->tid ) );
		\IPS\Db::i()->delete( 'forums_answer_ratings', array( 'topic=?', $this->tid ) );

		/* Delete any moved topic links that point to this topic - moved_on>? is for query optimisation purposes */
		\IPS\Db::i()->delete( 'forums_topics', array( "moved_on>? AND moved_to LIKE CONCAT( ?, '%' ) AND state=?", 0, $this->tid . '&', 'link' ) );
	}

	/**
	 * Merge other items in (they will be deleted, this will be kept)
	 *
	 * @param	array	$items	Items to merge in
	 * @param	bool	$keepLinks	Retain redirect links for the items that were merge in
	 * @return	void
	 */
	public function mergeIn( array $items, $keepLinks=FALSE )
	{
		/* If this is a QA forum we need to make sure we only have one best answer (at most) post-merge */
		if( $this->isQuestion() )
		{
			/* Combine question ratings */
			$itemTids = array();

			foreach( $items as $item )
			{
				/* We will use UPDATE IGNORE for the update queries, as duplicate key errors could result if we upvoted both topics */
				\IPS\Db::i()->update( 'forums_question_ratings', array( 'forum' => $this->forum_id, 'topic' => $this->tid ), array( 'topic=?', $item->tid ), array(), NULL, \IPS\Db::IGNORE );
				\IPS\Db::i()->update( 'forums_answer_ratings', array( 'topic' => $this->tid ), array( 'topic=?', $item->tid ), array(), NULL, \IPS\Db::IGNORE );

				$itemTids[] = $item->tid;
			}

			/* If any rows still exist for the old topic, that means it was a duplicate and we can remove */
			\IPS\Db::i()->delete( 'forums_question_ratings', "topic IN(" . implode( ',', $itemTids ) . ")" );
			\IPS\Db::i()->delete( 'forums_answer_ratings', "topic IN(" . implode( ',', $itemTids ) . ")" );

			/* And then update the topic with the new question rating count */
			\IPS\Db::i()->update( 'forums_topics', array( 'question_rating' =>
				\IPS\Db::i()->select( 'SUM(rating)', 'forums_question_ratings', array( 'topic=?', $this->tid ) )
				), array( 'tid=?', $this->tid ) );

			/* Does this topic already have a best answer? */
			if( $this->topic_answered_pid )
			{
				/* Then we need to make sure none of the items also has a best answer */
				foreach( $items as $item )
				{
					/* Reset best answer for this topic */
					if( $item->topic_answered_pid )
					{
						try
						{
							$post = \IPS\forums\Topic\Post::load( $item->topic_answered_pid );
							$post->post_bwoptions['best_answer'] = FALSE;
							$post->save();
						}
						catch( \OutOfRangeException $e ){}
					}
				}
			}
			/* The topic doesn't have a best answer, but we still need to make sure we only have one best answer total post-merge */
			else
			{
				$bestAnswerSeen	= FALSE;

				foreach( $items as $item )
				{
					if( $item->topic_answered_pid )
					{
						/* Have we seen a best answer yet? If not, then we're ok. */
						if( $bestAnswerSeen === FALSE )
						{
							/* This topic had no best answer flag set, so set it now */
							$this->topic_answered_pid = $item->topic_answered_pid;
							$this->save();

							$bestAnswerSeen = TRUE;
							continue;
						}

						/* If we have though, reset any others */
						try
						{
							$post = \IPS\forums\Topic\Post::load( $item->topic_answered_pid );
							$post->post_bwoptions['best_answer'] = FALSE;
							$post->save();
						}
						catch( \OutOfRangeException $e ){}
					}
				}
			}
		}

		/* Update popular time */
		$this->rebuildPopularTime();

		return parent::mergeIn( $items, $keepLinks );
	}

	/**
	 * Hide
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @param	string					$reason	Reason
	 * @return	void
	 */
	public function hide( $member, $reason = NULL )
	{
		/* Hide any moved topic links that point to this topic - moved_on>? is for query optimisation purposes */
		foreach( \IPS\Db::i()->select( '*', 'forums_topics', array( "moved_on>? AND moved_to LIKE CONCAT( ?, '%' ) AND state=?", 0, $this->tid . '&', 'link' ) ) as $link )
		{
			\IPS\forums\Topic::constructFromData( $link )->hide( $member, $reason );
		}

		return parent::hide( $member, $reason );
	}

	/**
	 * Unhide
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function unhide( $member )
	{
		/* Unhide any moved topic links that point to this topic - moved_on>? is for query optimisation purposes */
		foreach( \IPS\Db::i()->select( '*', 'forums_topics', array( "moved_on>? AND moved_to LIKE CONCAT( ?, '%' ) AND state=?", 0, $this->tid . '&', 'link' ) ) as $link )
		{
			\IPS\forums\Topic::constructFromData( $link )->unhide( $member );
		}

		return parent::unhide( $member );
	}
	
	/**
	 * Can promote this comment/item?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	boolean
	 */
	public function canPromoteToSocialMedia( $member=NULL )
	{
		return parent::canPromoteToSocialMedia( $member ) and $this->container()->can_view_others;
	}

	/* !Saved Actions */
	
	/**
	 * Get available saved actions for this topic
	 *
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in)
	 * @return	array
	 */
	public function availableSavedActions( \IPS\Member $member = NULL )
	{
		return \IPS\forums\SavedAction::actions( $this->container(), $member );
	}
		
	/**
	 * Do Moderator Action
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string|NULL			$reason	Reason (for hides)
	 * @param	bool				$immediately Delete Immediately
	 * @return	void
	 * @throws	\OutOfRangeException|\InvalidArgumentException|\RuntimeException
	 */
	public function modAction( $action, \IPS\Member $member = NULL, $reason = NULL, $immediately = FALSE )
	{
		if ( mb_substr( $action, 0, 12 ) === 'savedAction-' )
		{
			$action = \IPS\forums\SavedAction::load( \intval( mb_substr( $action, 12 ) ) );
			$action->runOn( $this );
			
			/* Log */
			\IPS\Session::i()->modLog( 'modlog__saved_action', array( 'forums_mmod_' . $action->mm_id => TRUE, $this->url()->__toString() => FALSE, $this->mapped( 'title' ) => FALSE ), $this );
		}
		
		parent::modAction( $action, $member, $reason, $immediately );
		
		/* Prevent topics with an open time re-opening again after being locked */
		if ( $action == 'lock' )
		{
			$this->topic_open_time = 0;
			$this->save();
		}

		/* And prevent it from relocking if we are unlocking */
		if( $action == 'unlock' )
		{
			$this->topic_close_time = 0;
			$this->save();
		}
	}
	
	/* !Tags */
	
	/**
	 * Can tag?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canTag( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canTag( $member, $container ) and ( $container === NULL or !$container->forums_bitoptions['bw_disable_tagging'] );
	}
	
	/**
	 * Can use prefixes?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canPrefix( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canPrefix( $member, $container ) and ( $container === NULL or !$container->forums_bitoptions['bw_disable_prefixes'] );
	}
	
	/**
	 * Defined Tags
	 *
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	array
	 */
	public static function definedTags( \IPS\Node\Model $container = NULL )
	{
		if ( $container and $container->tag_predefined )
		{
			$return = explode( ',', $container->tag_predefined );
			if ( \IPS\Settings::i()->tags_alphabetical )
			{
				natcasesort( $return );
			}
			
			return $return;
		}
		
		return parent::definedTags( $container );
	}
	
	/* !Questions & Answers */

	/**
	 * Any container has solvable enabled?
	 *
	 * @return	boolean
	 */
	public static function anyContainerAllowsSolvable()
	{
		return (bool) \IPS\Db::i()->select( 'COUNT(*)', 'forums_forums', '(' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers' ) . ') OR ( ' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers_moderator' ) . ' )' )->first();
	}
	
	
	/**
	 * Container has solvable enabled
	 *
	 * @return	string
	 */
	public function containerAllowsSolvable()
	{
		return $this->container()->forums_bitoptions['bw_enable_answers_moderator'];
	}

	/**
	 * Container has solvable enabled
	 *
	 * @return	string
	 */
	public function containerAllowsMemberSolvable()
	{
		return ( $this->containerAllowsSolvable() AND $this->container()->forums_bitoptions['bw_enable_answers_member'] );
	}
	
	/**
	 * Toggle the solve value of a comment
	 *
	 * @param 	int		$commentId	The comment ID
	 * @param 	boolean	$value		TRUE/FALSE value
	 * @param	\IPS\Member	$member	The member (null for currently logged in member)
	 */
	public function toggleSolveComment( $commentId, $value, \IPS\Member $member = NULL )
	{
		if ( $value )
		{
			$post = \IPS\forums\Topic\Post::load( $commentId );
			$post->author()->achievementAction( 'forums', 'AnswerMarkedBest', $post );
		}
		
		return $this->_toggleSolveComment( $commentId, $value, $member );
	}

	/**
	 * Is this topic a question? \IPS\forums\Forum::$modPerm
	 *
	 * @return	bool
	 */
	public function isQuestion()
	{
		return $this->container()->forums_bitoptions['bw_enable_answers'];
	}
	
	/**
	 * Can user set the best answer?
	 *
	 * @param	\IPS\Member	$member	The member (null for currently logged in member)
	 * @return	bool
	 */
	public function canSetBestAnswer( \IPS\Member $member = NULL )
	{
		/* Archived topics cannot be modified */
		if ( $this->isArchived() )
		{
			return FALSE;
		}

		$member = $member ?: \IPS\Member::loggedIn();

		/* If we asked this question, we can set the best answer */
		if ( $member == $this->author() and $this->container()->forums_bitoptions['bw_enable_answers_member'] )
		{
			return TRUE;
		}

		/* Or if we're a moderator */
		if
		(
			$member->modPermission( 'can_set_best_answer' )
			and
			(
				( $member->modPermission( \IPS\forums\Forum::$modPerm ) === TRUE or $member->modPermission( \IPS\forums\Forum::$modPerm ) === -1 )
				or
				(
					\is_array( $member->modPermission( \IPS\forums\Forum::$modPerm ) )
					and
					\in_array( $this->container()->_id, $member->modPermission( \IPS\forums\Forum::$modPerm ) )
				)
			)
		) {
			return TRUE;
		}
		
		/* Otherwise no */
		return FALSE;
	}
	
	/**
	 * @brief	Answer Votes
	 */
	protected $answerVotes = array();
	
	/**
	 * Answer Votes
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	array
	 */
	public function answerVotes( \IPS\Member $member )
	{
		if ( !isset( $this->answerVotes[ $member->member_id ] ) )
		{
			$this->answerVotes[ $member->member_id ] = iterator_to_array(
				\IPS\Db::i()->select( 'post,rating', 'forums_answer_ratings', array( 'topic=? AND `member`=?', $this->tid, $member->member_id ) )
				->setKeyField( 'post' )
				->setValueField( 'rating' )
			);
		}
		
		return $this->answerVotes[ $member->member_id ];
	}
	
	/**
	 * Get Best Answer
	 *
	 * @return	\IPS\forums\Topic\Post|NULL
	 */
	public function bestAnswer()
	{
		if ( $this->topic_answered_pid )
		{
			try
			{
				return \IPS\forums\Topic\Post::load( $this->topic_answered_pid );
			}
			catch ( \OutOfRangeException $e ){}
		}
		return NULL;
	}
	
	/**
	 * Can the user rate answers?
	 *
	 * @param	int					$rating		1 for positive, -1 for negative, 0 for either
	 * @param	\IPS\Member|NULL	$member		The member (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\InvalidArgumentException
	 */
	public function canVote( $rating=0, $member=NULL )
	{
		/* Is $rating valid */
		if ( !\in_array( $rating, array( -1, 0, 1 ) ) )
		{
			throw new \InvalidArgumentException;
		}
				
		/* Guests can't vote */
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		/* Can't vote your own answers */
		if ( $member == $this->author() )
		{
			return FALSE;
		}
		
		/* Check the forum settings */
		if ( $this->container()->qa_rate_questions !== NULL and $this->container()->qa_rate_questions != '*' and !$member->inGroup( explode( ',', $this->container()->qa_rate_questions ) ) )
		{
			return FALSE;
		}

		/* Downvoting disabled? */
		if ( $rating === -1 and !\IPS\Settings::i()->forums_questions_downvote and ( !isset( $ratings[ $member->member_id ] ) or $ratings[ $member->member_id ] != 1 ) )
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * @brief	Votes
	 */
	protected $votes = NULL;
	
	/**
	 * Votes
	 *
	 * @return	array
	 */
	public function votes()
	{
		if ( $this->votes === NULL )
		{
			$this->votes = iterator_to_array(
				\IPS\Db::i()->select( '`member`,rating', 'forums_question_ratings', array( 'topic=?', $this->tid ) )
				->setKeyField( 'member' )
				->setValueField( 'rating' )
			);
		}
		
		return $this->votes;
	}
	
	/**
	 * Clear Votes Cache
	 *
	 * @return	void
	 * @note	This is necessary so that when voting on a question or answer, the cached votes ($votes and $answerVotes) are reloaded properly.
	 */
	public function clearVotes()
	{
		$this->votes		= NULL;
		$this->answerVotes	= array();
	}
	
	/**
	 * [ActiveRecord]	Save
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
		$this->clearVotes();
	}
	
	/**
	 * Indefinite Article
	 *
	 * @param	array			$containerData	Data about the container
	 * @param	\IPS\Lang|NULL	$lang	The language to use, or NULL for the language of the currently logged in member
	 * @return	string
	 */
	public static function _indefiniteArticle( array $containerData = NULL, \IPS\Lang $lang = NULL )
	{
		if ( !$containerData )
		{
			return parent::_indefiniteArticle( $containerData, $lang );
		}
		
		$bitOptions = ( $containerData['forums_bitoptions'] instanceof \IPS\Patterns\Bitwise ) ? $containerData['forums_bitoptions'] : new \IPS\Patterns\Bitwise( array( 'forums_bitoptions' => $containerData['forums_bitoptions'] ), \IPS\forums\Forum::$bitOptions['forums_bitoptions'] );
		
		if ( $bitOptions['bw_enable_answers'] )
		{
			$lang = $lang ?: \IPS\Member::loggedIn()->language();
			return $lang->addToStack( '__indefart_question', FALSE );
		}
		else
		{
			return parent::_indefiniteArticle( $containerData, $lang );
		}
	}
	
	/**
	 * Definite Article
	 *
	 * @param	array			$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	\IPS\Lang|NULL	$lang			The language to use, or NULL for the language of the currently logged in member
	 * @param	array			$options		Options to pass to \IPS\Lang::addToStack
	 * @param	integer|boolean	$count			Number of items. If not false, pluralized version of phrase will be used.
	 * @return	string
	 */
	public static function _definiteArticle( array $containerData = NULL, \IPS\Lang $lang = NULL, $options = array(), $count = FALSE )
	{
		if( $containerData !== NULL )
		{
			$bitOptions = ( $containerData['forums_bitoptions'] instanceof \IPS\Patterns\Bitwise ) ? $containerData['forums_bitoptions'] : new \IPS\Patterns\Bitwise( array( 'forums_bitoptions' => $containerData['forums_bitoptions'] ), \IPS\forums\Forum::$bitOptions['forums_bitoptions'] );
			
			if ( $bitOptions['bw_enable_answers'] )
			{
				$lang = $lang ?: \IPS\Member::loggedIn()->language();
				
				if( $count === TRUE || \is_int( $count ) )
				{
					if( \is_int( $count ) )
					{
						$options['pluralize'] = array( $count );
					}

					return $lang->addToStack( '__defart_question_plural', FALSE, $options );
				}

				return $lang->addToStack( '__defart_question', FALSE, $options );
			}
		}
		
		return parent::_definiteArticle( $containerData, $lang, $options, $count );
	}
	
	/* !Sitemap */
	
	/**
	 * WHERE clause for getting items for sitemap (permissions are already accounted for)
	 *
	 * @return	array
	 */
	public static function sitemapWhere()
	{
		return array( array( 'forums_forums.ipseo_priority<>?', 0 ) );
	}
	
	/**
	 * Sitemap Priority
	 *
	 * @return	int|NULL	NULL to use default
	 */
	public function sitemapPriority()
	{
		$priority = $this->container()->ipseo_priority;
		if ( $priority === NULL or $priority == -1 )
		{
			return NULL;
		}
		return $priority;
	}
	
	/* !Archiving */
	
	/**
	 * Is archived?
	 *
	 * @return	bool
	 */
	public function isArchived()
	{
		return \in_array( $this->topic_archive_status, array( static::ARCHIVE_DONE, static::ARCHIVE_WORKING, static::ARCHIVE_RESTORE ) );
	}
	
	/**
	 * Can unarchive?
	 *
	 * @param	\IPS\Member\NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnarchive( $member=NULL )
	{
		if ( $this->isArchived() and $this->topic_archive_status !== static::ARCHIVE_RESTORE )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			return $member->hasAcpRestriction( 'forums', 'forums', 'archive_manage' );
		}
		return FALSE;
	}

	/**
	 * Should this topic be archived again?
	 *
	 * @param \IPS\Member\NULL $member	The member (NULL for currently logged in member)
	 * @return bool
	 */
	public function canRemoveArchiveExcludeFlag( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		if ( $member->hasAcpRestriction( 'forums', 'forums', 'archive_manage' ) AND $this->topic_archive_status == static::ARCHIVE_EXCLUDE )
		{
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * Unarchive confirm message
	 *
	 * @return	string
	 */
	public function unarchiveBlurb()
	{
		$taskData = \IPS\Db::i()->select( '*', 'core_tasks', array( '`key`=? AND app=?', 'archive', 'forums' ) )->first();
		
		$time = \IPS\DateTime::ts( $taskData['next_run'] );
		$postsToBeUnarchived = \IPS\Db::i()->select( 'SUM(posts) + count(*)', 'forums_topics', array( 'topic_archive_status=?', static::ARCHIVE_RESTORE ) )->first();

		if ( $postsToBeUnarchived AND $postsToBeUnarchived > \IPS\forums\tasks\unarchive::PROCESS_PER_BATCH )
		{
			$total = $postsToBeUnarchived / \IPS\forums\tasks\unarchive::PROCESS_PER_BATCH;
			$interval = new \DateInterval( $taskData['frequency'] );
			foreach ( range( 1, $total ) as $i )
			{
				$time->add( $interval );
			}
		}
		
		return \IPS\Member::loggedIn()->language()->addToStack( 'unarchive_confirm', FALSE, array( 'pluralize' => array( ceil( ( $time->getTimestamp() - time() ) / 60 ) ) ) );
	}
	
	/**
	 * Can comment?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canComment( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canComment( $member, $considerPostBeforeRegistering );
	}
	
	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canEdit( $member );
	}
	
	/**
	 * Can feature?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canFeature( $member=NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canFeature( $member );
	}
	
	/**
	 * Can unfeature?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnfeature( $member=NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canUnfeature( $member );
	}

	/**
	 * Can lock?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canLock( $member=NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canLock( $member );
	}
	
	/**
	 * Can unlock?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnlock( $member=NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canUnlock( $member );
	}
	
	/**
	 * Can hide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canHide( $member=NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canHide( $member );
	}
	
	/**
	 * Can unhide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnhide( $member=NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canUnhide( $member );
	}
	
	/**
	 * Can move?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canMove( $member=NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canMove( $member );
	}
	
	/**
	 * Can merge?
	 *
	 * @param	\IPS\Member|NULL	$member The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canMerge( $member=NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		if ( $this->moved_to )
		{
			return FALSE;
		}
		
		return parent::canMerge( $member );
	}
	
	/**
	 * Comment Multimod Actions
	 *
	 * @param	\IPS\Member|NULL	$member The member to check for (NULL for currently logged in member)
	 * @return	array
	 */
	public function commentMultimodActions( \IPS\Member $member=NULL )
	{
		if ( $this->isArchived() )
		{
			return array();
		}
		
		return parent::commentMultimodActions( $member );
	}
	
	/**
	 * Can Feature a Comment
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for currently logged in
	 * @return	bool
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function canFeatureComment( \IPS\Member $member = NULL )
	{
		if ( $this->isArchived() OR $this->isQuestion() )
		{
			return FALSE;
		}
		
		return parent::canFeatureComment( $member );
	}
	
	/**
	 * Can perform an action on a message
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for currently logged in
	 * @return	bool
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function canOnMessage( $action, \IPS\Member $member = NULL )
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canOnMessage( $action, $member );
	}
	
	/**
	 * Can toggle item-level moderation?
	 *
	 * @param	\IPS\Member|NULL		$member
	 * @return	bool
	 */
	public function canToggleItemModeration( ?\IPS\Member $member = NULL ): bool
	{
		if ( $this->isArchived() )
		{
			return FALSE;
		}
		
		return parent::canToggleItemModeration( $member );
	}
	
	/**
	 * Get comments
	 *
	 * @param	int|NULL			$limit					The number to get (NULL to use static::getCommentsPerPage())
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The column to order by
	 * @param	string				$orderDirection			"asc" or "desc"
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHiddenComments	Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	mixed				$extraWhereClause	Additional where clause(s) (see \IPS\Db::build for details)
	 * @param	bool|NULL			$bypassCache			Used in cases where comments may have already been loaded i.e. splitting comments on an item.
	 * @param	bool				$includeDeleted			Include Deleted Content
	 * @param	bool|NULL			$canViewWarn			TRUE to include Warning information, NULL to determine automatically based on moderator permissions.
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public function comments( $limit=NULL, $offset=NULL, $order='date', $orderDirection='asc', $member=NULL, $includeHiddenComments=NULL, $cutoff=NULL, $extraWhereClause=NULL, $bypassCache=FALSE, $includeDeleted=FALSE, $canViewWarn=NULL )
	{
		static $comments	= array();
		$idField			= static::$databaseColumnId;
		$_hash				= md5( $this->$idField . json_encode( \func_get_args() ) );

		if( !$bypassCache and isset( $comments[ $_hash ] ) )
		{
			return $comments[ $_hash ];
		}
		
		$includeWarnings	= $canViewWarn;
		$commentClass		= NULL;

		if ( $this->isArchived() )
		{
			/* We need to set $commentClass to the archive class, otherwise the includeHidden checks in _comments fail, as they verify $class == static::$commentClass */
			$class			= static::$archiveClass;
			$commentClass	= static::$commentClass;

			static::$commentClass = $class;

			$includeWarnings = FALSE;

			if( $extraWhereClause !== NULL )
			{
				if( \is_array( $extraWhereClause ) )
				{
					foreach( $extraWhereClause as $k => $v )
					{
						$extraWhereClause[ $k ]	= preg_replace( "/^author_id /", "archive_author_id ", $v );
					}
				}
				else
				{
					$extraWhereClause	= preg_replace( "/^author_id /", "archive_author_id ", $extraWhereClause );
				}
			}
		}
		else
		{
			$class = static::$commentClass;
		}
		
		try 
		{
			$comments[ $_hash ]	= $this->_comments( $class, $limit ?: static::getCommentsPerPage(), $offset, ( isset( $class::$databaseColumnMap[ $order ] ) ? ( $class::$databasePrefix . $class::$databaseColumnMap[ $order ] ) : $order ) . ' ' . $orderDirection, $member, $includeHiddenComments, $cutoff, $includeWarnings, $extraWhereClause, $includeDeleted );
		}
		catch( \IPS\Db\Exception $e )
		{
			$post = new \IPS\forums\Topic\Post;
			$post->topic_id = $this->tid;
			$post->post = '<p><em>' . \IPS\Member::loggedIn()->language()->addToStack('archived_topic_missing_posts') . '</em></p>';
			$post->post_date = $this->start_date;
			$post->author_id = $this->starter_id;
			
			if ( \IPS\Member::loggedIn()->isAdmin() )
			{
				$post->post .= "<p>" . \IPS\Member::loggedIn()->language()->addToStack('archived_topic_missing_posts_admin') . "</p><p><strong>{$e->getMessage()}<br><textarea>" . var_export( $e, TRUE ) . '</textarea></p>';
			}

			$comments[ $_hash ] = array( $post );
		}
		
		/* Restore comment class now */
		if( $commentClass )
		{
			static::$commentClass	= $commentClass;
		}
		return $comments[ $_hash ];
	}
	
	/**
	 * Resync the comments/unapproved comment counts
	 *
	 * @param	string	$commentClass	Override comment class to use
	 * @return void
	 */
	public function resyncCommentCounts( $commentClass=NULL )
	{
		return parent::resyncCommentCounts( $this->isArchived() ? static::$archiveClass : NULL );
	}
	
	/**
	 * Return the first comment on the item
	 *
	 * @return \IPS\Content\Comment|NULL
	 */
	public function firstComment()
	{
		if ( $this->isArchived() )
		{
			try 
			{
				return parent::firstComment();
			}
			catch( \IPS\Db\Exception $e )
			{

			}
		}
		else
		{
			return parent::firstComment();
		}
	}
	
	/**
	 * Check Moderator Permission
	 *
	 * @param	string						$type		'edit', 'hide', 'unhide', 'delete', etc.
	 * @param	\IPS\Member|NULL			$member		The member to check for or NULL for the currently logged in member
	 * @param	\IPS\Node\Model|NULL		$container	The container
	 * @return	bool
	 */
	public static function modPermission( $type, \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{		
		/* Load Member */
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Compatibility checks */
		if ( \in_array( $type, array( 'use_saved_actions', 'set_best_answer' ) ) )
		{
			$containerClass = \get_class( $container );
			$title = static::$title;
			if
			(
				$member->modPermission( $containerClass::$modPerm ) === -1
				or
				(
					\is_array( $member->modPermission( $containerClass::$modPerm ) )
					and
					\in_array( $container->_id, $member->modPermission( $containerClass::$modPerm ) )
				)
			)
			{
				return TRUE;
			}
		}
		
		return parent::modPermission( $type, $member, $container );
	}

	/**
	 * Mark as read
	 *
	 * @param	\IPS\Member|NULL	$member					The member (NULL for currently logged in member)
	 * @param	int|NULL			$time					The timestamp to set (or NULL for current time)
	 * @param	mixed				$extraContainerWhere	Additional where clause(s) (see \IPS\Db::build for details)
	 * @param	bool				$force					Mark as unread even if we already appear to be unread?
	 * @return	void
	 */
	public function markRead( \IPS\Member $member = NULL, $time = NULL, $extraContainerWhere = NULL, $force = FALSE )
	{
        $member = $member ?: \IPS\Member::loggedIn();
        $time	= $time ?: time();

        if ( !$this->container()->memberCanAccessOthersTopics( $member ) )
        {
            $extraContainerWhere = array( 'starter_id = ?', $member->member_id );
        }

        parent::markRead( $member, $time, $extraContainerWhere, $force );
    }

	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int						id				ID number
	 * @apiresponse	string					title			Title
	 * @apiresponse	\IPS\forums\Forum		forum			Forum
	 * @apiresponse	int						posts			Number of posts
	 * @apiresponse	int						views			Number of views
	 * @apiresponse	string					prefix			The prefix tag, if there is one
	 * @apiresponse	[string]				tags			The tags
	 * @apiresponse	\IPS\forums\Topic\Post	firstPost		The first post in the topic
	 * @apiresponse	\IPS\forums\Topic\Post	lastPost		The last post in the topic
	 * @apiresponse	\IPS\forums\Topic\Post	bestAnswer		The best answer, if this is a question and there is one
	 * @apiresponse	bool					locked			Topic is locked
	 * @apiresponse	bool					hidden			Topic is hidden
	 * @apiresponse	bool					pinned			Topic is pinned
	 * @apiresponse	bool					featured		Topic is featured
	 * @apiresponse	bool					archived		Topic is archived
	 * @apiresponse	\IPS\Poll				poll			Poll data, if there is one
	 * @apiresponse	string					url				URL
	 * @apiresponse	float					rating			Average Rating
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		$firstPost = $this->comments( 1, 0, 'date', 'asc' );
		$lastPost = $this->comments( 1, 0, 'date', 'desc' );
		$bestAnswer = $this->bestAnswer();
		return array(
			'id'			=> $this->tid,
			'title'			=> $this->title,
			'forum'			=> $this->container()->apiOutput( $authorizedMember ),
			'posts'			=> $this->posts,
			'views'			=> $this->views,
			'prefix'		=> $this->prefix(),
			'tags'			=> $this->tags(),
			'firstPost'		=> $firstPost ? $firstPost->apiOutput( $authorizedMember ) : null,
			'lastPost'		=> $lastPost ? $lastPost->apiOutput( $authorizedMember ) : null,
			'bestAnswer'	=> $bestAnswer ? $bestAnswer->apiOutput( $authorizedMember ) : null,
			'locked'		=> (bool) $this->locked(),
			'hidden'		=> (bool) $this->hidden(),
			'pinned'		=> (bool) $this->mapped('pinned'),
			'featured'		=> (bool) $this->mapped('featured'),
			'archived'		=> (bool) $this->isArchived(),
			'poll'			=> $this->poll_state ? \IPS\Poll::load( $this->poll_state )->apiOutput( $authorizedMember ) : null,
			'url'			=> (string) $this->url(),
			'rating'		=> $this->averageRating(),
			'is_future_entry'	=> $this->is_future_entry,
			'publish_date'	=> $this->publish_date ? \IPS\DateTime::ts( $this->publish_date )->rfc3339() : NULL
		);
	}

	/**
	 * Returns the content
	 *
	 * @return	string
	 * @throws	\BadMethodCallException
	 * @note	This is overridden for performance reasons - selecting a post by a PID is more efficient than select * from posts order by date desc limit 1
	 */
	public function content()
	{
		$firstComment = $this->firstComment();
		return $firstComment ? $firstComment->content() : '';
	}
	
	/**
	 * Supported Meta Data Types
	 *
	 * @return	array|NULL
	 */
	public static function supportedMetaDataTypes()
	{
		return array( 'core_FeaturedComments', 'core_ContentMessages', 'core_ItemModeration' );
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'forums', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'forums' )->embedTopic( $this, $this->url()->setQueryString( $params ) );
	}
	
	/* ! Reactions */
	
	/**
	 * Reaction type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'tid';
	}
	
	/**
	 * Reaction Where Clause
	 *
	 * @param	\IPS\Content\Reaction|array|int|NULL	$reactions			This can be any one of the following: An \IPS\Content\Reaction object, an array of \IPS\Content\Reaction objects, an integer, or an array of integers, or NULL
	 * @param	bool									$enabledTypesOnly 	If TRUE, only reactions of the enabled reaction types will be included (must join core_reactions)
	 * @return	array
	 */
	public function getReactionWhereClause( $reactions = NULL, $enabledTypesOnly=TRUE )
	{
		$idColumn = static::$databaseColumnId;
		$class = static::reactionClass();
		$where = array( array( 'rep_class=? and item_id=?', $class::$commentClass, $this->$idColumn ) );
		
		if ( $enabledTypesOnly )
		{
			$where[] = array( 'reaction_enabled=1' );
		}
		
		if ( $reactions !== NULL )
		{
			if ( !\is_array( $reactions ) )
			{
				$reactions = array( $reactions );
			}
			
			$in = array();
			foreach( $reactions AS $reaction )
			{
				if ( $reaction instanceof \IPS\Content\Reaction )
				{
					$in[] = $reaction->id;
				}
				else
				{
					$in[] = $reaction;
				}
			}
			
			if ( \count( $in ) )
			{
				$where[] = array( \IPS\Db::i()->in( 'reaction', $in ) );
			}
		}
		
		return $where;
	}

	/**
	 * Show the topic summary feature?
	 *
	 * @param	$key	string		Key to check (topPost, popularDays, uploads)
	 * @return boolean
	 */
	public function showSummaryFeature( $key )
	{
		if ( \IPS\Settings::i()->forums_topic_activity_features )
		{
			$features = json_decode( \IPS\Settings::i()->forums_topic_activity_features, TRUE );
			if ( $features and \in_array( $key, $features ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Show the topic summary on desktop? (if so where)
	 *
	 * @return string [sidebar,post]
	 */
	public function showSummaryOnDesktop()
	{
		/* Hide the summary for future topics */
		if ( $this->isFutureDate() )
		{
			return FALSE;
		}
		if ( ! \IPS\Settings::i()->forums_topics_activity_pages_show OR ( (int) \IPS\Settings::i()->forums_topics_activity_pages_show <= (int) $this->commentPageCount() ) )
		{
			$viewSettings = json_decode( \IPS\Settings::i()->forums_topic_activity, TRUE );
			if ( $viewSettings and \in_array( 'desktop', $viewSettings ) and isset( \IPS\Settings::i()->forums_topic_activity_desktop ) )
			{
				return \IPS\Settings::i()->forums_topic_activity_desktop;
			}
		}

		return FALSE;
	}

	/**
	 * Show the topic summary on mobile?
	 *
	 * @return boolean
	 */
	public function showSummaryOnMobile()
	{
		/* Hide the summary for future topics */
		if ( $this->isFutureDate() )
		{
			return FALSE;
		}
		if ( ! \IPS\Settings::i()->forums_topics_activity_pages_show OR ( (int) \IPS\Settings::i()->forums_topics_activity_pages_show <= (int) $this->commentPageCount() ) )
		{
			$viewSettings = json_decode( \IPS\Settings::i()->forums_topic_activity, TRUE );
			if ( $viewSettings and \in_array( 'mobile', $viewSettings ) )
			{
				return TRUE;
			}
		}
		
		return FALSE;
	}

	/**
	 * We need to force an index so that super long topics load when fetching IDs before running the main query
	 *
	 * @return string|null
	 */
	 /**
	   * We need to force an index so that super long topics load when fetching IDs before running the main query
	   *
	   * @return string
	   */
	  protected function forceIndexForPaginatedIds()
	  {
		  if ( ! $this->isArchived() )
		  {
			  return 'first_post';
		  }
		  
		  return NULL;
	  }
	  
	  /**
	   * WHERE clause for getting items for ACP overview statistics
	   *
	   * @return	array
	   */
	  public static function overviewStatisticsWhere()
	  {
		  return array( array( \IPS\Db::i()->in( 'state', array( 'link', 'merged' ), TRUE ) ) );
	  }
}