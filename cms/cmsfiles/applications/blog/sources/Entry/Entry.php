<?php
/**
 * @brief		Entry Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		3 Mar 2014
 */

namespace IPS\blog;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Entry Model
 */
class _Entry extends \IPS\Content\Item implements
	\IPS\Content\Pinnable, \IPS\Content\Lockable, \IPS\Content\Hideable, \IPS\Content\Featurable,
	\IPS\Content\Tags,
	\IPS\Content\Followable,
	\IPS\Content\Shareable,
	\IPS\Content\ReadMarkers,
	\IPS\Content\Polls,
	\IPS\Content\Ratings,
	\IPS\Content\EditHistory,
	\IPS\Content\Searchable,
	\IPS\Content\Embeddable,
	\IPS\Content\FuturePublishing,
	\IPS\Content\MetaData,
	\IPS\Content\Anonymous
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable, \IPS\Content\ViewUpdates, \IPS\Content\Statistics;
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	Application
	 */
	public static $application = 'blog';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'blogs';
	
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'blog_entries';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'entry_';
		
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'author'				=> 'author_id',
		'author_name'			=> 'author_name',
		'content'				=> 'content',
		'container'				=> 'blog_id',
		'date'					=> 'date',
		'updated'				=> 'last_update',
		'title'					=> 'name',
		'num_comments'			=> 'num_comments',
		'unapproved_comments'	=> 'queued_comments',
		'hidden_comments'		=> 'hidden_comments',
		'last_comment_by'		=> 'last_comment_mid',
		'last_comment'			=> 'last_update',	// Same as updated above
		'views'					=> 'views',
		'approved'				=> 'hidden',
		'pinned'				=> 'pinned',
		'poll'					=> 'poll_state',
		'featured'				=> 'featured',
		'ip_address'			=> 'ip_address',
		'locked'				=> 'locked',
		'cover_photo'			=> 'cover_photo',
		'cover_photo_offset'	=> 'cover_offset',
		'is_future_entry'		=> 'is_future_entry',
        'future_date'           => 'publish_date',
		'status'				=> 'status',
        'meta_data'				=> 'meta_data',
		'edit_time'				=> 'edit_time',
		'edit_member_name'    	=> 'edit_name',
		'edit_show'				=> 'append_edit',
		'edit_reason'			=> 'edit_reason',
		'is_anon'				=> 'is_anon',
		'last_comment_anon'		=> 'last_poster_anon',
	);
	
	/**
	 * @brief	Title
	*/
	public static $title = 'blog_entry';
	
	/**
	 * @brief	Node Class
	 */
	public static $containerNodeClass = 'IPS\blog\Blog';
	
	/**
	 * @brief	[Content\Item]	Comment Class
	 */
	public static $commentClass = 'IPS\blog\Entry\Comment';
	
	/**
	 * @brief	[Content\Item]	First "comment" is part of the item?
	 */
	public static $firstCommentRequired = FALSE;
	
	/**
	 * @brief	[Content\Comment]	Language prefix for forms
	 */
	public static $formLangPrefix = 'blog_entry_';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'file-text';
	
	/**
	 * @brief	The map of permission columns
	 */
	public static $permissionMap = array(
			'view' 				=> 'view',
			'read'				=> 2,
			'add'				=> 3,
			'reply'				=> 4,
	);
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'blog-entry';
	
	/**
	 * @brief	[CoverPhoto]	Storage extension
	 */
	public static $coverPhotoStorageExtension = 'blog_Entries';
	
	/**
	 * @brief	Use a default cover photo
	 */
	public static $coverPhotoDefault = true;
	
	/**
	 * Set the title
	 *
	 * @param	string	$name	Title
	 * @return	void
	 */
	public function set_name( $name )
	{
		$this->_data['name'] = $name;
		$this->_data['name_seo'] = \IPS\Http\Url\Friendly::seoTitle( $name );
	}

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_name_seo()
	{
		if( !$this->_data['name_seo'] )
		{
			$this->name_seo	= \IPS\Http\Url\Friendly::seoTitle( $this->name );
			$this->save();
		}

		return $this->_data['name_seo'] ?: \IPS\Http\Url\Friendly::seoTitle( $this->name );
	}

	/**
	 * Get the album HTML, if there is one associated
	 *
	 * @return	string
	 */
	public function get__album()
	{
		if( \IPS\Application::appIsEnabled( 'gallery' ) AND $this->gallery_album )
		{
			try
			{
				$album = \IPS\gallery\Album::loadAndCheckPerms( $this->gallery_album );
	
				$gallery = \IPS\Application::load( 'gallery' );
				$gallery::outputCss();
	
				return \IPS\Theme::i()->getTemplate( 'browse', 'gallery', 'front' )->miniAlbum( $album );
			}
			catch( \OutOfRangeException $e ){}
			catch( \UnderflowException $e ){}
		}
	
		return '';
	}
	
	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();
	
	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=blog&module=blogs&controller=entry&id=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'blog_entry';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'name_seo';

	/**
	 * @brief	Category
	 */
	protected $category;
	
	/**
	 * Can view this entry
	 *
	 * @param	\IPS\Member|NULL	$member		The member or NULL for currently logged in member.
	 * @return	bool
	 */
	public function canView( $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$return = parent::canView( $member );

		if ( $this->status == 'draft' AND !static::canViewHiddenItems( $member, $this->container() ) AND !\in_array( $this->container()->id, array_keys( \IPS\blog\Blog::loadByOwner( $member ) ) ) )
		{
			$return = FALSE;
			if ( ( $club = $this->container()->club() AND \in_array( $club->memberStatus( \IPS\Member::loggedIn() ), array( \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_MODERATOR ) ) ) )
			{
				$return = TRUE;
			}
		}
		
		/* Is this a future publish entry and we are the owner of the blog? */
		if ( $this->status == 'draft' AND $this->is_future_entry == 1 AND \in_array( $this->container()->id, array_keys( \IPS\blog\Blog::loadByOwner( $member ) ) ) )
		{
			$return = TRUE;
		}
		
		/* Club blog */
		if ( $club = $this->container()->club() )
		{
			if ( !$club->canRead( $member ) )
			{
				return FALSE;
			}
		}

		/* Private blog */
		if( $this->container()->social_group != 0 AND $this->container()->owner()->member_id != $member->member_id )
		{
			/* This will throw an exception of the row does not exist */
			try
			{
				if( !$member->member_id )
				{
					return FALSE;
				}

				$member	= \IPS\Db::i()->select( '*', 'core_sys_social_group_members', array( 'group_id=? AND member_id=?', $this->container()->social_group, $member->member_id ) )->first();
			}
			catch( \UnderflowException $e )
			{
				return FALSE;
			}
		}
		
		return $return;
	}

	/**
	 * Unclaim attachments
	 *
	 * @return	void
	 */
	protected function unclaimAttachments()
	{
		\IPS\File::unclaimAttachments( 'blog_Entries', $this->id );
	}
	
	/**
	 * Get items with permisison check
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
		if ( \in_array( $permissionKey, array( 'view', 'read' ) ) )
		{
			$joinContainer = TRUE;
						
			$member = $member ?: \IPS\Member::loggedIn();
            if ( $member->member_id )
            {
                $where[] = array( '( blog_blogs.blog_member_id=' . $member->member_id . ' OR ( ' . \IPS\Content::socialGroupGetItemsWithPermissionWhere( 'blog_blogs.blog_social_group', $member ) . ' ) OR blog_blogs.blog_social_group IS NULL )' );
            }
            else
            {
                $where[] = array( "(" . \IPS\Content::socialGroupGetItemsWithPermissionWhere( 'blog_blogs.blog_social_group', $member ) . " OR blog_blogs.blog_social_group IS NULL )" );
            }
            
			if ( \IPS\Settings::i()->clubs )
			{
				$joins[] = array( 'from' => 'core_clubs', 'where' => 'core_clubs.id=blog_blogs.blog_club_id' );
				if ( $member->member_id )
				{
					if ( !$member->modPermission( 'can_access_all_clubs' ) )
					{
						$where[] = array( '( blog_blogs.blog_club_id IS NULL OR ' . \IPS\Db::i()->in( 'blog_blogs.blog_club_id', $member->clubs() ) . ' OR core_clubs.type=? OR core_clubs.type=?  OR core_clubs.type=?)', \IPS\Member\Club::TYPE_PUBLIC, \IPS\Member\Club::TYPE_READONLY, \IPS\Member\Club::TYPE_OPEN );
					}
				}
				else
				{
					$where[] = array( '( blog_blogs.blog_club_id IS NULL OR core_clubs.type=? OR core_clubs.type=? OR core_clubs.type=? )', \IPS\Member\Club::TYPE_PUBLIC, \IPS\Member\Club::TYPE_READONLY, \IPS\Member\Club::TYPE_OPEN );
				}
			}
		}
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
		$joinContainer = TRUE;
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$where = array( array( '( blog_blogs.blog_social_group IS NULL OR blog_blogs.blog_member_id=' . \IPS\Member::loggedIn()->member_id . ' OR ( ' . \IPS\Content::socialGroupGetItemsWithPermissionWhere( 'blog_blogs.blog_social_group', \IPS\Member::loggedIn() ) . ' ) )' ) );
		}
		else
		{
			$where = array( \IPS\Content::socialGroupGetItemsWithPermissionWhere( 'blog_blogs.blog_social_group', \IPS\Member::loggedIn() ) );
		}
		
		if ( \IPS\Settings::i()->clubs )
		{
			$joins[] = array( 'from' => 'core_clubs', 'where' => 'core_clubs.id=blog_blogs.blog_club_id' );
			if ( \IPS\Member::loggedIn()->member_id )
            {
				$where[] = array( '( blog_blogs.blog_club_id IS NULL OR ' . \IPS\Db::i()->in( 'blog_blogs.blog_club_id', \IPS\Member::loggedIn()->clubs() ) . ' OR core_clubs.type=? OR core_clubs.type=? )', \IPS\Member\Club::TYPE_PUBLIC, \IPS\Member\Club::TYPE_READONLY );
            }
            else
            {
				$where[] = array( '( blog_blogs.blog_club_id IS NULL OR core_clubs.type=? OR core_clubs.type=? )', \IPS\Member\Club::TYPE_PUBLIC, \IPS\Member\Club::TYPE_READONLY );
            }
		}

		return array_merge( parent::followWhere( $joinContainer, $joins ), $where );
	}
	
	/**
	 * Get elements for add/edit form
	 *
	 * @param	\IPS\Content\Item|NULL	$item		The current item if editing or NULL if creating
	 * @param	int						$container	Container (e.g. forum) ID, if appropriate
	 * @return	array
	 */
	public static function formElements( $item=NULL, \IPS\Node\Model $container=NULL )
	{
		$return = parent::formElements( $item, $container );
		$return['entry'] = new \IPS\Helpers\Form\Editor( 'blog_entry_content', $item ? $item->content : NULL, TRUE, array( 'app' => 'blog', 'key' => 'Entries', 'autoSaveKey' => ( $item === NULL ) ? 'blog-entry-' . $container->id : 'blog-edit-' . $item->id, 'attachIds' => ( $item === NULL ? NULL : array( $item->id ) ) ) );

		/* Edit Log Fields need to be under the editor */
		$editReason = NULL;
		if( isset( $return['edit_reason']) )
		{
			$editReason = $return['edit_reason'];
			unset( $return['edit_reason'] );
			$return['edit_reason'] = $editReason;
		}

		$logEdit = NULL;
		if( isset( $return['log_edit']) )
		{
			$logEdit = $return['log_edit'];
			unset( $return['log_edit'] );
			$return['log_edit'] = $logEdit;
		}

		/* Gallery album association */
		if( \IPS\Application::appIsEnabled( 'gallery' ) )
		{
			$return['album']	= new \IPS\Helpers\Form\Node( 'entry_gallery_album', ( $item AND $item->gallery_album ) ? $item->gallery_album : NULL, FALSE, array(
					'url'					=> \IPS\Http\Url::internal( 'app=blog&module=blogs&controller=submit', 'front', 'blog_submit' ),
					'class'					=> 'IPS\gallery\Album',
					'permissionCheck'		=> 'add',
			) );
		}

		$categories = \IPS\blog\Entry\Category::roots( NULL, NULL, array( 'entry_category_blog_id=?', $container->id ) );
		$choiceOptions = array( 0 => 'entry_category_choice_new' );
		$choiceToggles = array( 0 => array( 'blog_entry_new_category' ) );

		if( \count( $categories ) )
		{
			$choiceOptions[1] = 'entry_category_choice_existing';
			$choiceToggles[1] = array( 'entry_category_id' );
		}
		
		$return['entry_category_choice'] = new \IPS\Helpers\Form\Radio( 'entry_category_choice', ( ( $item AND $item->category_id ) or \IPS\Request::i()->cat ) ? 1 : 0, FALSE, array(
			'options' => $choiceOptions,
			'toggles' => $choiceToggles
		) );

		if( \count( $categories ) )
		{
			$options = array();
			foreach ( $categories as $category )
			{
				$options[ $category->id ] = $category->name;
			}

			$return[ 'entry_category_id' ] = new \IPS\Helpers\Form\Select( 'entry_category_id', ( $item AND $item->category_id ) ? $item->category_id : ( \IPS\Request::i()->cat ? \IPS\Request::i()->cat : NULL ), FALSE, array( 'options' => $options, 'parse' => 'normal' ), NULL, NULL, NULL, "entry_category_id" );
		}
		$return['blog_entry_new_category']	= new \IPS\Helpers\Form\Text( 'blog_entry_new_category', NULL, TRUE, array(), NULL, NULL, NULL, "blog_entry_new_category" );

		$return['image'] = new \IPS\Helpers\Form\Upload( 'blog_entry_cover_photo', ( ( $item AND $item->cover_photo ) ? \IPS\File::get( 'blog_Entries', $item->cover_photo ) : NULL ), FALSE, array( 'storageExtension' => 'blog_Entries', 'allowStockPhotos' => TRUE, 'image' => array( 'maxWidth' => 4800, 'maxHeight' => 4800 ), 'canBeModerated' => TRUE ) );
		
		$return['publish'] = new \IPS\Helpers\Form\YesNo( 'blog_entry_publish', $item ? $item->status : TRUE, FALSE, array( 'togglesOn' => array( 'blog_entry_date' ) ) );
		
		/* Publish date needs to go near the bottom */
		$date = NULL;
		if ( isset( $return['date'] ) )
		{
			$date = $return['date'];
			unset( $return['date'] );
			
			$return['date'] = $date;
		}
		
		/* Poll always needs to go on the end */
		$poll = NULL;
		if ( isset( $return['poll'] ) )
		{
			$poll = $return['poll'];
			unset( $return['poll'] );
			
			$return['poll'] = $poll;
		}

		
		return $return;
	}
	
	/**
	 * Process create/edit form
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	public function processForm( $values )
	{
		$new = $this->_new;

		parent::processForm( $values );
		
		if ( !$new )
		{
			$oldContent = $this->content;
		}
		$this->content	= $values['blog_entry_content'];
		
		$sendFilterNotifications = $this->checkProfanityFilters( FALSE, !$new, NULL, NULL, 'blog_Entries', $new ? ['blog-entry-' . $this->container()->id] : NULL, $values['blog_entry_cover_photo'] ? [ $values['blog_entry_cover_photo'] ] : [] );
		
		if ( !$new AND $sendFilterNotifications === FALSE )
		{
			$this->sendAfterEditNotifications( $oldContent );
		}
		
		$this->status = $values['blog_entry_publish'] ? 'published' : 'draft';
		
		if ( isset( $values['blog_entry_date'] ) )
		{
			$this->date = ( $values['blog_entry_date'] AND $values['blog_entry_publish'] ) ? $values['blog_entry_date']->getTimestamp() : time();
		}

		$this->cover_photo = (string) $values['blog_entry_cover_photo'];
		
		/* Gallery album association */
		if( \IPS\Application::appIsEnabled( 'gallery' ) AND $values['entry_gallery_album'] instanceof \IPS\gallery\Album )
		{
			$this->gallery_album = $values['entry_gallery_album']->_id;
		}
		else
		{
			$this->gallery_album = NULL;
		}
		
		if ( $this->date > time() )
		{
			$this->status = 'draft';
			$this->publish_date = $this->date;
		}

		if( $values['entry_category_choice'] == 1 and $values['entry_category_id'] )
		{
			$this->category_id = $values['entry_category_id'];
		}
		else
		{
			$newCategory = new \IPS\blog\Entry\Category;
			$newCategory->name = $values['blog_entry_new_category'];
			$newCategory->seo_name = \IPS\Http\Url\Friendly::seoTitle( $values['blog_entry_new_category'] );

			$newCategory->blog_id = $this->blog_id;
			$newCategory->save();

			$this->category_id = $newCategory->id;
		}
		
		/* Ping */
		$this->container()->ping();
	}
	
	/**
	 * Can a given member create this type of content?
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @param	bool		$showError	If TRUE, rather than returning a boolean value, will display an error
	 * @return	bool
	 */
	public static function canCreate( \IPS\Member $member, \IPS\Node\Model $container=NULL, $showError=FALSE )
	{
		parent::canCreate( $member, $container, $showError );
		
		if ( $member->member_id AND $member->checkPostsPerDay() === FALSE )
		{
			if ( $showError )
			{
				\IPS\Output::i()->error( 'posts_per_day_error', '1B203/2', 403, '' );
			}
			else
			{
				return FALSE;
			}
		}
		
		$return = TRUE;

		$blogs = \IPS\blog\Blog::loadByOwner( $member );

		if ( $container )
		{
			if ( $club = $container->club() )
			{
				$return = $club->isModerator( $member );
				$error = 'no_module_permission';
			}
			elseif ( !\in_array( $container->id, array_keys( $blogs ) ) )
			{
				$return = FALSE;
				$error = 'no_module_permission';
			}
			
			if ( $container->disabled )
			{
				$return = FALSE;
				$error = 'no_module_permission';
			}
		}
		else
		{
			if( !\count( $blogs ) )
			{
				$return = FALSE;
				$error = 'no_module_permission';
			}
		}
				
		/* Return */
		if ( $showError and !$return )
		{
			\IPS\Output::i()->error( $error, '1B203/1', 403, '' );
		}
		
		return $return;
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
		parent::processAfterCreate( $comment, $values );

		\IPS\File::claimAttachments( 'blog-entry-' . $this->container()->id, $this->id );

		if ( $this->status == 'published' )
		{
			$blog						= $this->container();
			$lastUpdateColumn			= $blog::$databaseColumnMap['date'];
			$blog->$lastUpdateColumn	= time();
			$blog->save();
		}
	}

	/**
	 * Syncing to run when publishing something previously pending publishing
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onPublish( $member )
	{
		$this->status = 'published';
		$this->save();
		
		parent::onPublish( $member );
		
		/* The blog system is slightly different from the \Content future entry stuff. Future entries are treated as drafts,
			so do count towards entries but parent::onPublish will try and increment item count again after publish */
		$this->container()->resetCommentCounts();
		$this->container()->save();
	}
	
	/**
	 * Syncing to run when unpublishing an item (making it a future dated entry when it was already published)
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onUnpublish( $member )
	{
		$this->status = 'draft';
		$this->save();
		
		parent::onUnpublish( $member );
		
		/* The blog system is slightly different from the \Content future entry stuff. Future entries are treated as drafts,
			so do count towards entries but parent::onUnpublish will try and decrement item count after unpublish */
		$this->container()->resetCommentCounts();
		$this->container()->save();
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
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( parent::canComment( $member, $considerPostBeforeRegistering ) )
		{
			if ( $member->checkPostsPerDay() === FALSE )
			{
				return FALSE;
			}
			elseif ( $member->group['g_blog_allowcomment'] )
			{
				return TRUE;
			}
			elseif ( !$member->member_id and $considerPostBeforeRegistering and \IPS\Settings::i()->post_before_registering and \IPS\Login::registrationType() != 'disabled' )
			{
				return (bool) \IPS\Member\Group::load( \IPS\Settings::i()->member_group )->g_blog_allowcomment;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Can set items to be published in the future?
	 *
	 * @param	\IPS\Member|NULL	    $member	        The member to check for (NULL for currently logged in member)
	 * @param   \IPS\Node\Model|null    $container      Container
	 * @return	bool
	 */
	public static function canFuturePublish( $member=NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return (boolean) $member->member_id > 0;
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
		$member = $member ?: \IPS\Member::loggedIn();
		$result = parent::modPermission( $type, $member, $container );
		
		if ( $result !== TRUE )
		{
			if ( \in_array( $type, array( 'edit', 'delete', 'lock', 'unlock' ) ) and $container and $container->member_id === $member->member_id )
			{
				$result = $member->group['g_blog_allowownmod'];
			}
		}
		
		return $result;
	}

	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();

		$this->coverPhoto()->delete();
	}

	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'global', 'blog', 'front' ), 'rows' );
	}

	/**
	 * WHERE clause for getting items for digest (permissions are already accounted for)
	 *
	 * @return	array
	 */
	public static function digestWhere(): array
	{
		return array( array( 'blog_entries.entry_is_future_entry=0 AND blog_entries.entry_status!=?', 'draft' ) );
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
        if ( $this->status == 'draft' )
        {
            return '0';
        }
        
        return parent::searchIndexPermissions();
    }

	/**
	 * WHERE clause for getting items for sitemap (permissions are already accounted for)
	 *
	 * @return	array
	 */
	public static function sitemapWhere()
	{
		return array( array( 'blog_entries.entry_is_future_entry=0 AND blog_entries.entry_status!=?', 'draft' ) );
	}
    
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL				$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int							id				ID number
	 * @apiresponse	string						title			Title
	 * @apiresponse	\IPS\blog\Blog				blog			Blog
	 * @apiresponse	\IPS\Member					author			The member that created the entry
	 * @apiresponse	bool						draft			If this entry is a draft
	 * @apiresponse	datetime					date			Date
	 * @apiresponse	string						entry			Entry content
	 * @apiresponse	int							comments		Number of comments
	 * @apiresponse	int							views			Number of posts
	 * @apiresponse	string						prefix			The prefix tag, if there is one
	 * @apiresponse	[string]					tags			The tags
	 * @apiresponse	bool						locked			Entry is locked
	 * @apiresponse	bool						hidden			Entry is hidden
	 * @apiresponse	bool						future			Will be published at a future date?
	 * @apiresponse	bool						pinned			Entry is pinned
	 * @apiresponse	bool						featured		Entry is featured
	 * @apiresponse	\IPS\Poll					poll			Poll data, if there is one
	 * @apiresponse	string						url				URL
	 * @apiresponse	float						rating			Average Rating
	 * @apiresponse	\IPS\blog\Entry\Category	category		Category
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'id'			=> $this->id,
			'title'			=> $this->name,
			'blog'			=> $this->container()->apiOutput( $authorizedMember ),
			'author'		=> $this->author()->apiOutput( $authorizedMember ),
			'draft'			=> $this->status == 'draft',
			'date'			=> \IPS\DateTime::ts( $this->date )->rfc3339(),
			'entry'			=> \IPS\Text\Parser::removeLazyLoad( $this->content() ),
			'comments'		=> $this->num_comments,
			'views'			=> $this->views,
			'prefix'		=> $this->prefix(),
			'tags'			=> $this->tags(),
			'locked'		=> (bool) $this->locked(),
			'hidden'		=> (bool) $this->hidden(),
			'future'		=> $this->isFutureDate(),
			'pinned'		=> (bool) $this->mapped('pinned'),
			'featured'		=> (bool) $this->mapped('featured'),
			'poll'			=> $this->poll_state ? \IPS\Poll::load( $this->poll_state )->apiOutput( $authorizedMember ) : null,
			'url'			=> (string) $this->url(),
			'rating'		=> $this->averageRating(),
			'category'		=> $this->category_id ? $this->category()->apiOutput() : NULL,
		);
	}
	
	/**
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'entry_id';
	}
	
	/**
	 * Supported Meta Data Types
	 *
	 * @return	array
	 */
	public static function supportedMetaDataTypes()
	{
		return array( 'core_FeaturedComments', 'core_ContentMessages' );
	}
	
	/**
	 * Can Feature a Comment
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for currently logged in
	 * @return	bool
	 */
	public function canFeatureComment( \IPS\Member $member = NULL )
	{
		$return = parent::canFeatureComment( $member );
		
		if ( $return === FALSE )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			
			if ( $member->member_id AND $member->member_id === $this->author()->member_id AND $member->group['g_blog_allowownmod'] )
			{
				$return = TRUE;
			}
		}
		
		return $return;
	}
	
	/**
	 * Can Unfeature a Comment
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for currently logged in
	 * @return	bool
	 */
	public function canUnfeatureComment( \IPS\Member $member = NULL )
	{
		$return = parent::canUnfeatureComment( $member );
		
		if ( $return === FALSE )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			
			if ( $member->member_id AND $member->member_id === $this->author()->member_id AND $member->group['g_blog_allowownmod'] )
			{
				$return = TRUE;
			}
		}
		
		return $return;
	}

	/**
	 * Returns the content images
	 *
	 * @param	int|null	$limit				Number of attachments to fetch, or NULL for all
	 * @param	bool		$ignorePermissions	If set to TRUE, permission to view the images will not be checked
	 * @return	array|NULL
	 * @throws	\BadMethodCallException
	 */
	public function contentImages( $limit = NULL, $ignorePermissions = FALSE )
	{
		$idColumn = static::$databaseColumnId;
		$internal = NULL;
		$attachments = array();
		
		if ( isset( static::$databaseColumnMap['content'] ) )
		{
			$internal = \IPS\Db::i()->select( 'attachment_id', 'core_attachments_map', array( 'location_key=? and id1=?', 'blog_Entries', $this->$idColumn ) );
		}

		if ( $internal )
		{
			foreach( \IPS\Db::i()->select( '*', 'core_attachments', array( array( 'attach_id IN(?)', $internal ), array( 'attach_is_image=1' ) ), 'attach_id ASC', $limit ) as $row )
			{
				$attachments[] = array( 'core_Attachment' => $row['attach_location'] );
			}
		}

		/* Does the blog entry have a cover photo? */
		if( $this->cover_photo )
		{
			$attachments[] = array( 'blog_Entries' => $this->cover_photo );
		}

		/* And what about the blog itself? */
		if( $this->container()->cover_photo )
		{
			$attachments[] = array( 'blog_Blogs' => $this->container()->cover_photo );
		}

		/* IS there a club with a cover photo? */
		if ( \IPS\IPS::classUsesTrait( $this->container(), 'IPS\Content\ClubContainer' ) and $club = $this->container()->club() )
		{
			$attachments[] = array( 'core_Clubs' => $club->cover_photo );
		}
		
		return \count( $attachments ) ? \array_slice( $attachments, 0, $limit ) : NULL;
	}

	/**
	 * Is this a future entry?
	 *
	 * @return bool
	 */
	public function isFutureDate()
	{
		if ( $this->date > time() )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'blog', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'blog' )->embedEntry( $this, $this->container(), $this->url()->setQueryString( $params ) );
	}

	/**
	 * Get category
	 *
	 * @return	\IPS\blog\Entry\Category
	 * @throws	\OutOfRangeException
	 */
	public function category()
	{
		if ( $this->category === NULL )
		{
			$this->category	= \IPS\blog\Entry\Category::load( $this->category_id );
		}

		return $this->category;
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
		parent::move( $container, $keepLink );
		
		$this->category_id = NULL;
		$this->save();
	}
}