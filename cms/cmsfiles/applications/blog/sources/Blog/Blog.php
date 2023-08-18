<?php
/**
 * @brief		Blog Node
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
 * Blog Node
 */
class _Blog extends \IPS\Node\Model implements \IPS\Node\Ratings, \IPS\Content\Embeddable
{
	use \IPS\Content\ClubContainer, \IPS\Content\ViewUpdates;
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'blog_blogs';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'blog_';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'author'				=> 'member_id',
		'title'					=> 'name',
		'views'					=> 'num_views',
		'pinned'				=> 'pinned',
		'featured'				=> 'featured',
		'date'					=> 'last_edate',
		'cover_photo'			=> 'cover_photo',
		'cover_photo_offset'	=> 'cover_photo_offset'
	);
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'blogs';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'blogs_blog_';
	
	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_desc';

	/**
	 * @brief	[Node] Parent Node ID Database Column
	 */
	public static $parentNodeColumnId = 'category_id';
	
	/**
	 * @brief	[Node] Moderator Permission
	 */
	public static $modPerm = 'blogs';

	/**
	 * @brief	[Node] Maximum results to display at a time in any node helper form elements. Useful for user-submitted node types when there may be a lot. NULL for no limit.
	 */
	public static $maxFormHelperResults = 2000;
	
	/**
	 * @brief	Content Item Class
	 */
	public static $contentItemClass = 'IPS\blog\Entry';

	/**
	 * @brief	Category
	 */
	protected $category;
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'file-text';
	
	/**
	* @brief	[Node] If the node can be "owned", the owner "type" (typically "member" or "group") and the associated database column
	*/
	public static $ownerTypes = array( 'member' => 'member_id', 'group' => array( 'ids' => 'groupblog_ids', 'name' => 'groupblog_name' ) );
	
	/**
	 * @brief	[Node] By mapping appropriate columns (rating_average and/or rating_total + rating_hits) allows to cache rating values
	 */
	public static $ratingColumnMap	= array(
			'rating_average'	=> 'rating_average',
			'rating_total'		=> 'rating_total',
			'rating_hits'		=> 'rating_count',
	);
	
	/**
	 * @brief	Cover Photo Storage Extension
	 */
	public static $coverPhotoStorageExtension = 'blog_Blogs';
	
	/**
	 * @brief	Use a default cover photo
	 */
	public static $coverPhotoDefault = true;

	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		$return = parent::basicDataColumns();
		$return[] = 'blog_member_id';
		return $return;
	}

	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'browse', 'blog', 'front' ), 'rows' );
	}
	
	/**
	 * Can create blog?
	 *
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public static function canCreate( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( $member->member_id and $member->group['g_blog_allowlocal'] )
		{
			if ( $member->group['g_blog_maxblogs'] )
			{
				return ( \IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs', array( 'blog_member_id=?', $member->member_id ) )->first() < $member->group['g_blog_maxblogs'] );
			}
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Check permissions on any node
	 *
	 * For example - can be used to check if the user has
	 * permission to create content in any node to determine
	 * if there should be a "Submit" button
	 *
	 * @param	mixed								$permission						A key which has a value in static::$permissionMap['view'] matching a column ID in core_permission_index
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member							The member or group to check (NULL for currently logged in member)
	 * @param	array								$where							Additional WHERE clause
	 * @param	bool								$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 * @throws	\OutOfBoundsException	If $permission does not exist in static::$permissionMap
	 */
	public static function canOnAny( $permission, $member=NULL, $where = array(), $considerPostBeforeRegistering = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Posts per day - we need to do this independently here, because Blogs do not implement the Permissions interface */
		if ( \in_array( $permission, array( 'add', 'reply' ) ) )
		{
			$checkPostsPerDay = TRUE;
			if ( isset( static::$contentItemClass ) )
			{
				$contentClass = static::$contentItemClass;
				$checkPostsPerDay = $contentClass::$checkPostsPerDay;
			}
			
			if ( $checkPostsPerDay === TRUE AND $member->checkPostsPerDay() === FALSE )
			{
				return FALSE;
			}
		}
		
		/* This will always return true, but we always want to bubble up anyway */
		return parent::canOnAny( $permission, $member, $where, $considerPostBeforeRegistering );
	}

	/**
	 * Check permissions
	 *
	 * @param	mixed								$permission						A key which has a value in static::$permissionMap['view'] matching a column ID in core_permission_index
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member							The member or group to check (NULL for currently logged in member)
	 * @param	bool								$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 * @throws	\OutOfBoundsException	If $permission does not exist in static::$permissionMap
	 */
	public function can( $permission, $member=NULL, $considerPostBeforeRegistering = TRUE )
	{		
		/* Load member */
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}
		
		/* If the member has hit their posting limits, then just stop here. */
		switch( $permission )
		{
			case 'add':
			case 'reply':
				if ( $member->checkPostsPerDay() === FALSE )
				{
					return FALSE;
				}
				break;
		}
		
		if ( $club = $this->club() )
		{
			switch ( $permission )
			{
				case 'add':
					return $club->isModerator( $member );
					
				case 'view':
				case 'read':
				default:
					if( $this->isPublic() )
					{
						return TRUE;
					}
					return $club->canRead( $member );
			}
		}

		if ( $this->social_group )
		{
			if ( !$member->member_id )
			{
				return FALSE;
			}
			
			if ( $member->member_id !== $this->member_id )
			{
				try
				{
					\IPS\Db::i()->select( '*', 'core_sys_social_group_members', array( 'group_id=? AND member_id=?', $this->social_group, $member->member_id ) )->first();
				}
				catch ( \UnderflowException $e )
				{
					return FALSE;
				}
			}
		}
		
		if ( $permission === 'add' )
		{
			if ( !$member->member_id )
			{
				return FALSE;
			}
			elseif ( $member->member_id === $this->member_id )
			{
				return TRUE;
			}
			else
			{
				if ( $this->groupblog_ids )
				{
					return $member->inGroup( explode( ',', $this->groupblog_ids ) );
				}
				else
				{
					return FALSE;
				}
			}
		}
		
		return parent::can( $permission, $member, $considerPostBeforeRegistering );
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
		$return = parent::searchIndexPermissions();
		
		if ( $club = $this->club() )
		{
			if ( $club->type == $club::TYPE_PUBLIC or $club->type == $club::TYPE_READONLY or $club->type == $club::TYPE_OPEN)
			{
				return '*';
			}
			else
			{
				return "cm,c{$club->id}";
			}
		}
		elseif ( $this->social_group )
		{
			$return = ( $return === '*' ) ? array() : explode( ',', $return );
			
			if ( $this->member_id )
			{
				$return[] = "m{$this->member_id}";
			}
			$return[] = "s{$this->social_group}";
			
			$return = implode( ',', array_unique( $return ) );
		}
		
		return $return;
	}
	
	/**
	 * Additional WHERE clauses for Follow view
	 *
	 * @param	array	$joins	Joins
	 * @return	array
	 */
	public static function followWhere( &$joins )
	{
		$where = array();
		
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$where[] = array( '( blog_blogs.blog_social_group IS NULL OR blog_blogs.blog_member_id=' . \IPS\Member::loggedIn()->member_id . ' OR ( ' . \IPS\Content::socialGroupGetItemsWithPermissionWhere( 'blog_blogs.blog_social_group', \IPS\Member::loggedIn() ) . ' ) )' );
		}
		else
		{
			$where[] = \IPS\Content::socialGroupGetItemsWithPermissionWhere( 'blog_blogs.blog_social_group', \IPS\Member::loggedIn() );
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
				
		return $where;
	}
		
	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		foreach ( \IPS\Db::i()->select( 'data', 'core_queue', array( 'app=? AND `key`=?', 'core', 'DeleteOrMoveContent' ) ) as $row )
		{
			$data = json_decode( $row, TRUE );
			if ( $data['class'] === \get_class( $this ) and $data['id'] == $this->_id )
			{
				return FALSE;
			}
		}
		
		return static::restrictionCheck( 'delete' ) or ( $this->member_id === \IPS\Member::loggedIn()->member_id and \IPS\Member::loggedIn()->group['g_blog_allowdelete'] );
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @param	bool				$public	Whether this is a public form or not
	 * @return	void
	 */
	public function form( &$form, $public=FALSE )
	{
		if( $public )
		{
			$form->addTab( 'blog_settings' );
			
			if ( !$this->id OR \IPS\Member::loggedIn()->modPermission('can_mod_blogs') )
			{
				$form->add( new \IPS\Helpers\Form\Node( 'blog_category_id', $this->id ? $this->category_id : NULL, TRUE, array(
					'class'				=> '\IPS\blog\Category',
					'subnodes'	=> FALSE,
				), NULL, NULL, NULL, 'blog_category_id' ) );
			}

			$form->add( new \IPS\Helpers\Form\Text( 'blog_name', $this->id ? $this->_title : NULL, TRUE, array(), NULL, NULL, NULL, 'blog_name' ) );
			$form->add( new \IPS\Helpers\Form\Editor( 'blog_desc', $this->id ? $this->description : NULL, FALSE, array( 'app' => 'blog', 'key' => 'Blogs', 'autoSaveKey' => ( $this->id ? "blogs-blog-{$this->id}" : "blogs-new-blog" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'description' ) : NULL, 'minimize' => 'blog_desc_placeholder' ), NULL, NULL, NULL, 'blog_desc_wrap' ) );

			/* Sidebar */
			if ( \IPS\Settings::i()->blog_enable_sidebar )
			{
				$form->add( new \IPS\Helpers\Form\YesNo( 'blog_sidebar_enabled', $this->sidebar ? TRUE : FALSE, FALSE, array( 'togglesOn' => array( 'blog_sidebar_wrap' ) ) ) );
				$form->add( new \IPS\Helpers\Form\Editor( 'blog_sidebar', $this->id ? $this->sidebar : NULL, FALSE, array( 'app' => 'blog', 'key' => 'Blogs', 'autoSaveKey' => ( $this->id ? "blogs-blogsidebar-{$this->id}" : "blogs-new-blog-sidebar" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'sidebar' ) : NULL ), NULL, NULL, NULL, 'blog_sidebar_wrap' ) );
			}
		}
		else
		{
			if( $this->id )
			{
				$form->add( new \IPS\Helpers\Form\Node( 'blog_category_id', $this->category_id, TRUE, array(
					'class'				=> '\IPS\blog\Category',
					'subnodes'	=> FALSE,
				), NULL, NULL, NULL, 'blog_category_id' ) );
			}
			else
			{
				$form->hiddenValues['blog_category_id'] = \IPS\Request::i()->parent;
			}

			$groups = array();
			foreach ( \IPS\Member\Group::groups() as $k => $v )
			{
				$groups[ $k ] = $v->name;
			}

			$id = $this->id ?: 'new';
	
			$form->add( new \IPS\Helpers\Form\Radio( 'blog_type', ( $this->id AND $this->groupblog_ids ) ? 'group' : 'member', TRUE, array(
					'options' => array(
							'member' 	=> 'blog_type_normal',
							'group' 	=> 'blog_type_group'
					),
					'toggles'	=> array(
							'member'	=> array( 'blog_member_id', 'blog_name', 'blog_desc_wrap' ),
							'group'		=> array( 'blog_groupblog_ids', 'blog_groupblog_name', 'blog_name_group', 'blog_desc_group_wrap' )
					)
			) ) );
			
			$form->add( new \IPS\Helpers\Form\Member( 'blog_member_id', $this->member_id ? \IPS\Member::load( $this->member_id ) : NULL, FALSE, array(), function( $member ) use ( $form )
			{
				if ( \IPS\Request::i()->blog_type === 'member' )
				{
					if( !\is_object( $member ) or !$member->member_id )
					{
						throw new \InvalidArgumentException( 'no_blog_author_selected' );
					}
				}
			},
			NULL, NULL, 'blog_member_id' ) );

			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'blog_groupblog_ids', $this->id ? explode( ',', $this->groupblog_ids ) : array(), FALSE, array( 'options' => $groups, 'multiple' => TRUE ), NULL, NULL, NULL, 'blog_groupblog_ids' ) );
			$form->add( new \IPS\Helpers\Form\Translatable( 'blog_groupblog_name', NULL, FALSE, array( 'app' => 'blog', 'key' => ( $this->id ? "blogs_groupblog_name_{$this->id}" : NULL ) ), function( $value ) {
				if ( \IPS\Request::i()->blog_type === 'group' )
				{
					$hasTitle = FALSE;

					foreach( $value as $_value )
					{
						if( $_value )
						{
							$hasTitle = TRUE;
							break;
						}
					}

					if( !$hasTitle )
					{
						throw new \InvalidArgumentException( 'form_required' );
					}
				}
			}, NULL, NULL, 'blog_groupblog_name' ) );

			/* Owned blogs */
			$form->add( new \IPS\Helpers\Form\Text( 'blog_name', $this->id ? $this->_title : NULL, FALSE, array(), function( $value ) {
				if ( \IPS\Request::i()->blog_type === 'member' AND !$value )
				{
					throw new \InvalidArgumentException( 'form_required' );
				}
			}, NULL, NULL, 'blog_name' ) );
			$form->add( new \IPS\Helpers\Form\Editor( 'blog_desc', $this->id ? $this->description : NULL, FALSE, array( 'app' => 'blog', 'key' => 'Blogs', 'autoSaveKey' => ( $this->id ? "blogs-blog-{$this->id}m" : "blogs-new-blog" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'description' ) : NULL, 'minimize' => 'blog_desc_placeholder' ), NULL, NULL, NULL, 'blog_desc_wrap' ) );

			/* Group blogs - only one or the other will show at any given time */
			$form->add( new \IPS\Helpers\Form\Translatable( 'blog_name_group', ( $this->id AND !$this->groupblog_ids ) ? $this->_title : NULL, FALSE, array( 'app' => 'blog', 'key' => ( $this->id ? "blogs_blog_{$this->id}" : NULL ) ), function( $value ) {
				if ( \IPS\Request::i()->blog_type === 'group' )
				{
					$hasTitle = FALSE;

					foreach( $value as $_value )
					{
						if( $_value )
						{
							$hasTitle = TRUE;
							break;
						}
					}

					if( !$hasTitle )
					{
						throw new \InvalidArgumentException( 'form_required' );
					}
				}
			}, NULL, NULL, 'blog_name_group' ) );
			$form->add( new \IPS\Helpers\Form\Translatable( 'blog_desc_group', NULL, FALSE, array( 'app' => 'blog', 'key' => ( $this->id ? "blogs_blog_{$this->id}_desc" : NULL ), 'editor' => array( 'app' => 'blog', 'key' => 'Blogs', 'autoSaveKey' => ( $this->id ? "blogs-blog-{$this->id}-group" : "blogs-new-blog-group" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'description' ) : NULL, 'minimize' => 'blog_desc_placeholder' ) ), NULL, NULL, NULL, 'blog_desc_group_wrap' ) );

			/* Sidebar */
			if ( \IPS\Settings::i()->blog_enable_sidebar )
			{
				$form->add( new \IPS\Helpers\Form\YesNo( 'blog_sidebar_enabled', $this->sidebar ? TRUE : FALSE, FALSE, array( 'togglesOn' => array( 'blog_sidebar_wrap' ) ) ) );
				$form->add( new \IPS\Helpers\Form\Editor( 'blog_sidebar', $this->id ? $this->sidebar : NULL, FALSE, array( 'app' => 'blog', 'key' => 'Blogs', 'autoSaveKey' => ( $this->id ? "blogs-blogsidebar-{$this->id}" : "blogs-new-blog-sidebar" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'sidebar' ) : NULL ), NULL, NULL, NULL, 'blog_sidebar_wrap' ) );
			}
		}

		if ( \IPS\Member::loggedIn()->group['g_blog_allowprivate'] )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'blog_privacy', $this->social_group ? 'private' : 'open', FALSE, array(
				'options' => array(
					'open' 		=> 'blog_privacy_open',
					'private' 	=> 'blog_privacy_private'
				),
				'toggles'	=> array(
					'private'		=> array( 'blog_social_group' )
				)
			) ) );
			$form->add( new \IPS\Helpers\Form\SocialGroup( 'blog_social_group', $this->social_group, NULL, array( 'owner' => $this->owner() ), NULL, NULL, NULL, 'blog_social_group' ) );
		}
		
		if( \IPS\Settings::i()->blog_allow_rss )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'blog_enable_rss', $this->id ? $this->settings['allowrss'] : TRUE ) );
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'allow_anonymous_comments', $this->id ? $this->allow_anonymous : FALSE, FALSE, array() ) );
		
		/* Categories */
		if ( $public and $this->id )
		{
			$form->addTab( 'blog_entry_categories' );
			$form->addHtml( \IPS\Theme::i()->getTemplate( 'view', 'blog', 'front' )->manageCategories( $this ) );
		}
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( isset( $values['blog_category_id'] ) and \is_object( $values[ 'blog_category_id' ] ) )
		{
			$values[ 'blog_category_id' ] = \intval( $values[ 'blog_category_id' ]->id );
		}

		if( isset( $values['blog_type'] ) and $values['blog_type'] == 'member' and isset( $values['blog_member_id'] ) and \is_object( $values['blog_member_id'] ) )
		{
			$values['blog_member_id']->create_menu = NULL;
			$values['blog_member_id']->save();
			$values['blog_member_id']		= $values['blog_member_id']->member_id;
			$values['blog_groupblog_ids']	= '';
			$this->member_id				= $values['blog_member_id'];
		}
		else if ( !$this->id and !isset( $values['blog_type'] ) )
		{
			$values['blog_member_id'] = \IPS\Member::loggedIn()->member_id;
			\IPS\Member::loggedIn()->create_menu = NULL;
			\IPS\Member::loggedIn()->save();
			$this->member_id = $values['blog_member_id'];
		}
		else if ( isset( $values['blog_type'] ) )
		{
			$this->member_id = 0;
		}
		
		if( isset( $values['blog_type'] ) )
		{
			unset( $values['blog_type'] );
		}
		
		if( isset( $values['allow_anonymous_comments'] ) )
		{
			$values['allow_anonymous'] = ( $values['allow_anonymous_comments'] ? 2 : 0 );
			unset( $values['allow_anonymous_comments'] );
		}

		$this->massUpdateIndex = FALSE;
		if ( isset( $values['blog_privacy'] ) and $values['blog_privacy'] === 'private' )
		{
			if ( $this->id and !$this->social_group )
			{
				$this->massUpdateIndex = TRUE;
			}
		}
		else
		{
			if ( $this->id and $this->social_group )
			{
				$this->massUpdateIndex = TRUE;

				\IPS\Db::i()->delete( 'core_sys_social_groups', array( 'group_id=?', $this->social_group ) );
				\IPS\Db::i()->delete( 'core_sys_social_group_members', array( 'group_id=?', $this->social_group ) );
			}
			
			$values['blog_social_group'] = NULL;
		}

		if( isset($values['blog_privacy'] ) )
		{
			unset( $values['blog_privacy'] );
		}

		if ( !$this->id )
		{
			$this->category_id = $values[ 'blog_category_id' ];
			$this->save();

			if( $values['blog_member_id'] )
			{
				\IPS\File::claimAttachments( 'blogs-new-blog', $this->id, NULL, 'description' );
			}
			else
			{
				\IPS\File::claimAttachments( 'blogs-new-blog-group', $this->id, NULL, 'description', TRUE );
			}
		}

		/* If this is not a member blog we store the languages in the language system */
		if( !$this->member_id )
		{
			foreach ( array( 'blog_name_group' => "blogs_blog_{$this->id}", 'blog_desc_group' => "blogs_blog_{$this->id}_desc", 'blog_groupblog_name' => "blogs_groupblog_name_{$this->id}" ) as $fieldKey => $langKey )
			{
				if ( isset( $values[ $fieldKey ] ) )
				{
					\IPS\Lang::saveCustom( 'blog', $langKey, $values[ $fieldKey ] );
		
					if ( $fieldKey === 'blog_name' )
					{
						$values['seo_name'] = \IPS\Http\Url\Friendly::seoTitle( ( \is_array( $values[ $fieldKey ] ) ) ? $values[ $fieldKey ][ \IPS\Lang::defaultLanguage() ] : $values[ $fieldKey ] );
					}
		
					unset( $values[ $fieldKey ] );
				}
			}
			
			if( array_key_exists( 'blog_desc', $values ) )
			{
				unset( $values['blog_desc'] );
			}
		}
		else
		{
			/* If we're changing the author of the blog update the feed */
			if( isset( $values['blog_member_id'] ) )
			{
				try
				{
					$feed = \IPS\core\Rss\Import::constructFromData( \IPS\Db::i()->select( '*', 'core_rss_import', array( 'rss_import_class=? AND rss_import_node_id=?', 'IPS\\blog\\Entry', $this->id ) )->first() );
				}
				catch ( \UnderflowException $e )
				{
					$feed = NULL;
				}

				if( $feed )
				{
					$feed->member = $values['blog_member_id'];
					$feed->save();
				}
			}
			
			\IPS\Lang::saveCustom( 'blog', "blogs_blog_{$this->id}", $values['blog_name'] );
			
			/* This is here in case an admin changes a group blog to a member blog */
			\IPS\Lang::deleteCustom( 'blog', "blogs_groupblog_name_{$this->id}" );

			$values['seo_name'] = \IPS\Http\Url\Friendly::seoTitle( $values['blog_name'] );
		}
		
		if( array_key_exists( 'blog_name', $values ) )
		{
			unset( $values['blog_name'] );
		}

		if( array_key_exists( 'blog_name_group', $values ) )
		{
			unset( $values['blog_name_group'] );
		}

		if( array_key_exists( 'blog_desc_group', $values ) )
		{
			unset( $values['blog_desc_group'] );
		}

		if( array_key_exists( 'blog_groupblog_name', $values ) )
		{
			unset( $values['blog_groupblog_name'] );
		}

		if( array_key_exists( 'blog_enable_rss', $values ) )
		{
			$values['settings'] =  array( 'allowrss' => $values['blog_enable_rss'] );
			unset( $values['blog_enable_rss'] );
		}

		if( array_key_exists( 'blog_sidebar_enabled', $values ) )
		{
			$values['blog_sidebar'] = $values['blog_sidebar_enabled'] ? $values['blog_sidebar'] : NULL;
			unset( $values['blog_sidebar_enabled'] );
		}

		/* Send to parent */
		if( array_key_exists( 'blog_member_id', $values ) )
		{
			unset( $values['blog_member_id'] );
		}

		return $values;
	}

	/**
	 * [Node] Get the title to store in the log
	 *
	 * @return	string|null
	 */
	public function titleForLog()
	{
		if ( !$this->member_id )
		{
			try
			{
				return \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( static::$titleLangPrefix . $this->_id );
			}
			catch( \UnderflowException $e )
			{
				/* If we're changing from a member blog to a group blog, the language string won't exist yet */
				return $this->_title;
			}
		}
		else
		{
			return $this->_title;
		}
	}

	/**
	 * @brief	Mass update search index after changes
	 */
	protected $massUpdateIndex	= FALSE;

	/**
	 * [Node] Perform actions after saving the form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function postSaveForm( $values )
	{
		/* Update index? */
		if ( $this->massUpdateIndex )
		{
			\IPS\Content\Search\Index::i()->massUpdate( 'IPS\blog\Entry', $this->id, NULL, $this->searchIndexPermissions() );
		}

		/* If this was a new blog, fire the webhook */
		if ( $this->newBlog )
		{
			\IPS\Api\Webhook::fire( 'blogBlog_create', $this, $this->webhookFilters() );
		}
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_seo_name()
	{
		/* If we have the seo_name, just return it - don't query the language string */
		if( isset( $this->_data['seo_name'] ) and $this->_data['seo_name'] )
		{
			return $this->_data['seo_name'];
		}

		/* The seo_name isn't set, so let's fix that real quick and return it */
		$title = $this->member_id ? $this->name : \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'blogs_blog_' . $this->id );

		$seoTitle = \IPS\Http\Url::seoTitle( $title );
		$this->seo_name	= $seoTitle;
		$this->save();

		return $seoTitle;
	}

	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=blog&module=blogs&controller=view&id=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'blogs_blog';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'seo_name';
	
	/**
	 * @brief	Cached latest entry
	 */
	protected $latestEntry = NULL;
	
	/**
	 * Set last comment
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The latest comment or NULL to work it out
	 * @return	void
	 * @note	Like Gallery, we actually want to set this to the last entry rather than comment. As such $comment is ignored.
	 */
	public function setLastComment( \IPS\Content\Comment $comment=NULL )
	{
		$lastUpdateColumn	= static::$databaseColumnMap['date'];
		
		if ( $latestEntry = $this->latestEntry() )
		{
			$this->$lastUpdateColumn = $latestEntry->date;
		}
		else
		{
			$this->$lastUpdateColumn = 0;
		}
		
		$this->save();
	}

	/**
	 * Get latest entry
	 *
	 * @return	\IPS\blog\Entry|NULL
	 */
	public function latestEntry()
	{
		if( $this->latestEntry !== NULL )
		{
			return $this->latestEntry;
		}

		try
		{
			/* @note entry_hidden is flipped to map to "approved" and that this method will always only return the latest, visible, entry. */
			$this->latestEntry = \IPS\blog\Entry::constructFromData( \IPS\Db::i()->select( '*', 'blog_entries', array( 'entry_blog_id=? AND entry_is_future_entry=0 AND entry_hidden=1 AND entry_status!=?', $this->_id, 'draft' ), 'entry_date DESC', 1 )->first() );
			return $this->latestEntry;
		}
		catch ( \UnderflowException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Contributors
	 *
	 * @return	array
	 */
	public function contributors()
	{
		$contributors = array();
		
		try 
		{
			/* Get member IDs and contributions count */
			$select = \IPS\Db::i()->select(
					"entry_author_id, count( entry_id ) as contributions",
					'blog_entries',
					array( "entry_blog_id=? AND entry_author_id !=? AND entry_hidden!=?", $this->id, 0, -2 ),
					"contributions DESC",
					NULL,
					array( 'entry_author_id' )
			)->setKeyField( 'entry_author_id' )->setValueField( 'contributions' );

			/* Get the member ids to load them in one query */
			$memberIds	= array();
			$members	= array();

			foreach( $select as $member => $contributions )
			{
				$memberIds[] = $member;
			}

			if( \count( $memberIds ) )
			{
				foreach( \IPS\Db::i()->select( '*', 'core_members', 'member_id IN(' . implode( ',', $memberIds ) . ')' ) as $member )
				{
					$members[ $member['member_id'] ] = \IPS\Member::constructFromData( $member );
				}
			}

			/* Get em! */
			foreach( $select as $member => $contributions )
			{
				$contributors[] = array( 'member' => $members[ $member ], 'contributions' => $contributions );
			}
		}
		catch ( \UnderflowException $e ) {}

		return $contributors;
	}
	
	/**
	 * Retrieve recent entries
	 *
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public function get__recentEntries()
	{
		return \IPS\blog\Entry::getItemsWithPermission( array( array( 'entry_blog_id=? AND entry_is_future_entry=0 AND entry_status!=?', $this->id, 'draft' ) ), NULL, 5 );
	}
	
	/**
	 * [Node] Get number of content items
	 *
	 * @return	int
	 */
	protected function get__items()
	{
		return $this->count_entries;
	}
	
	/**
	 * [Node] Get number of content comments
	 *
	 * @return	int
	 */
	protected function get__comments()
	{
		return $this->count_comments;
	}
	
	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @return	int
	 */
	protected function get__unnapprovedItems()
	{
		return $this->count_entries_hidden;
	}
	
	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @return	int
	 */
	protected function get__unapprovedComments()
	{
		return $this->count_comments_hidden;
	}
	
	/**
	 * Set number of items
	 *
	 * @param	int	$val	Items
	 * @return	void
	 */
	protected function set__items( $val )
	{
		$this->count_entries = (int) $val;
	}
	
	/**
	 * Set number of items
	 *
	 * @param	int	$val	Comments
	 * @return	void
	 */
	protected function set__comments( $val )
	{
		$this->count_comments = (int) $val;
	}
	
	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @param	int	$val	Unapproved Items
	 * @return	void
	 */
	protected function set__unapprovedItems( $val )
	{
		$this->count_entries_hidden = $val;
	}
	
	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @param	int	$val	Unapproved Comments
	 * @return	void
	 */
	protected function set__unapprovedComments( $val )
	{
		$this->count_comments_hidden = $val;
	}
	
	/**
	 * [Node] Get number of future publishing items
	 *
	 * @return	int
	 */
	protected function get__futureItems()
	{
		return $this->count_entries_future;
	}
	
	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @param	int	$val	Unapproved Items
	 * @return	void
	 */
	protected function set__futureItems( $val )
	{
		$this->count_entries_future = ( $val > 0 ) ? $val : 0;
	}
	
	/**
	 * Returns the title
	 *
	 * @return string
	 */
	protected function get_description()
	{
		return $this->member_id ? $this->desc : \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $this->_id . static::$descriptionLangSuffix );
	}
	
	/**
	 * Get settings
	 *
	 * @return	array
	 */
	public function get_settings()
	{
		$settings = isset( $this->_data['settings'] ) ? json_decode( $this->_data['settings'], TRUE ) : array();

		return $settings;
	}
	
	/**
	 * Set settings
	 *
	 * @param	array	$values	Values
	 * @return	void
	 */
	public function set_settings( $values )
	{
		$this->_data['settings'] = json_encode( $values );
	}
	
	/**
	 * Ping Ping-o-matic
	 *
	 * @return	void
	 */
	public function ping()
	{		
		$xml = \IPS\Xml\SimpleXML::create('methodCall');
	
		$methodName = $xml->addChild( 'methodName', 'weblogUpdates.ping' );
		$params = $xml->addChild( 'params' );
 		$params->addChild( 'param' )->addChild( 'value', $this->_title );
 		$params->addChild( 'param' )->addChild( 'value', $this->url() );
		
		try
		{
	 		\IPS\Http\Url::external( 'http://rpc.pingomatic.com/RPC2' )
			->request()
			->setHeaders( array( 'Content-Type' => 'text/xml', 'User-Agent' => "InvisionCommunity/" . \IPS\Application::load('core')->long_version ) )
			->post( $xml->asXML() );
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			\IPS\Log::log( $e, 'pingomatic' );
		}
	}

	/**
	 * Get template for node tables
	 *
	 * @return	callable
	 */
	public static function nodeTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'browse', 'blog' ), 'rows' );
	}

	/**
	 * @brief	Remember if this is a new blog so we can fire the webhook if so
	 */
	protected $newBlog	= NULL;
	
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		if( $this->newBlog === NULL )
		{
			$this->newBlog = $this->_new;
		}
		
		parent::save();
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Delete RSS Imports */
		try
		{
			$import = \IPS\core\Rss\Import::constructFromData( \IPS\Db::i()->select( '*', 'core_rss_import', array( "rss_import_class=? AND rss_import_node_id=?", 'IPS\\blog\\Entry', $this->_id ) )->first() );
			$import->delete();
		}
		catch( \UnderflowException $e ) { }
		
		/* Delete Language Strings */
		foreach ( array( 'blog_groupblog_name' => "blogs_groupblog_name_{$this->id}" ) as $fieldKey => $langKey )
		{
			\IPS\Lang::deleteCustom( 'blog', $langKey );
		}
		
		/* Reset create menu */
		$member = \IPS\Member::load( $this->member_id );
		$member->create_menu = NULL;
		$member->save();
		
		/* Unclaim Attachments */
		\IPS\File::unclaimAttachments( 'blog_Blogs', $this->id );

		$this->coverPhotoFile()?->delete();
		
		/* Delete Follows */
		\IPS\Db::i()->delete( 'core_follow', array( "follow_app=? AND follow_area=? AND follow_rel_id=?", 'blog', 'blog', $this->_id ) );
		
		/* Delete Entry categories */
		\IPS\Db::i()->delete( 'blog_entry_categories', array( "entry_category_blog_id = ?", $this->_id ) );
		
		return parent::delete();
	}
	
	/**
	 * Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	public function coverPhoto()
	{
		$photo = parent::coverPhoto();
        $photo->overlay = \IPS\Theme::i()->getTemplate('view', 'blog', 'front')->coverPhotoOverlay($this);
		return $photo;
	}

	/**
	 * Produce a random hex color for a background
	 *
	 * @return string
	 */
	public function coverPhotoBackgroundColor()
	{
		return $this->staticCoverPhotoBackgroundColor( $this->titleForLog() );
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
		return \IPS\Theme::i()->getTemplate( 'global', 'blog' )->embedBlogs( $this, $this->url()->setQueryString( $params ) );
	}

	/**
	 * [Node] Get content table meta description 
	 *
	 * @return	string
	 */
	public function metaDescription()
	{
		if( $this->member_id )
		{
			return strip_tags( $this->desc );
		}

		return parent::metaDescription();
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int						id			ID number
	 * @apiresponse	string					name		Name
	 * @apiresponse	string					description	Description
	 * @apiresponse	\IPS\Member				owner		If the blog is owned by a single member, the member
	 * @apiresponse	[\IPS\Member\Group]		groups		If the blog is owned by groups, an array of the groups
	 * @apiresponse	bool					pinned		If the blog is pinned
	 * @apiresponse	int						entries		Number of entries
	 * @apiresponse	int						comments	Number of comments
	 * @apiresponse	string					url			URL
	 * @apiresponse	\IPS\blog\Category|NULL	category	Category, or NULL for club blogs
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		$groups = array();
		if ( $this->groupblog_ids )
		{
			foreach ( array_filter( explode( ',', $this->groupblog_ids ) ) as $groupId )
			{
				try
				{
					$groups[] = \IPS\Member\Group::load( $groupId )->apiOutput( $authorizedMember );
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}
		
		$return = array(
			'id'			=> $this->id,
			'name'			=> $this->_title,
			'description'	=> $this->member_id ? \IPS\Text\Parser::removeLazyLoad( $this->desc ) : \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $this->_id . static::$descriptionLangSuffix , NULL, array( 'removeLazyLoad' => TRUE ) ),
			'owner'			=> $this->member_id ? $this->owner()->apiOutput( $authorizedMember ) : null,
			'groups'		=> $groups,
			'pinned'		=> (bool) $this->pinned,
			'entries'		=> $this->count_entries,
			'comments'		=> $this->count_comments,
			'url'			=> (string) $this->url(),
			'category'		=> NULL
		);

		try
		{
			$return['category'] = $this->category()->apiOutput();
		}
		catch( \OutOfRangeException $e ){}

		return $return;
	}
	
	/**
	 * Permission Types
	 *
	 * @return	array
	 */
	public function permissionTypes()
	{
		return array( 'view' => 'view', 'add' => 'add' );
	}
	
	/* !Clubs */
	
	/**
	 * Get front-end language string
	 *
	 * @return	string
	 */
	public static function clubFrontTitle()
	{
		return 'blogs_sg';
	}
	
	/**
	 * Set form for creating a node of this type in a club
	 *
	 * @param	\IPS\Helpers\Form	$form	Form object
	 * @return	void
	 */
	public function clubForm( \IPS\Helpers\Form $form, \IPS\Member\Club $club )
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_view.js', 'blog', 'front' ) );

		$form->addTab( 'blog_settings' );
		$itemClass = static::$contentItemClass;
		$form->add( new \IPS\Helpers\Form\Text( 'club_node_name', $this->_id ? $this->_title : \IPS\Member::loggedIn()->language()->addToStack( 'blogs_sg' ), TRUE, array( 'maxLength' => 255 ) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'club_node_description', $this->_id ? \IPS\Member::loggedIn()->language()->get( static::$titleLangPrefix . $this->_id . '_desc' ) : NULL, FALSE, array( 'app' => 'blog', 'key' => 'Blogs', 'autoSaveKey' => ( $this->id ? "blogs-blog-{$this->id}" : "blogs-new-blog" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'description' ) : NULL, 'minimize' => 'blog_desc_placeholder' ) ) );
		if( \IPS\Settings::i()->blog_allow_rss )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'blog_enable_rss', $this->id ? $this->settings['allowrss'] : TRUE ) );
		}
		
		if( $club->type == 'closed' )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'club_node_public', $this->id ? $this->isPublic() : 0, TRUE, array( 'options' => array( '0' => 'club_node_public_no', '1' => 'club_node_public_view', '2' => 'club_node_public_participate' ) ) ) );
		}

		/* Categories */
		if ( $this->id )
		{
			$form->addTab( 'blog_entry_categories' );
			$form->addHtml( \IPS\Theme::i()->getTemplate( 'view', 'blog', 'front' )->manageCategories( $this ) );
		}

	}
	
	/**
	 * Class-specific routine when saving club form
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @param	array				$values	Values
	 * @return	void
	 */
	public function _saveClubForm( \IPS\Member\Club $club, $values )
	{
		if( \IPS\Settings::i()->blog_allow_rss )
		{
			$settings = $this->settings;
			$settings['allowrss'] = $values['blog_enable_rss'];
			$this->settings = $settings;
		}
		
		if ( $values['club_node_name'] )
		{
			$this->seo_name	= \IPS\Http\Url\Friendly::seoTitle( $values['club_node_name'] );
		}
		
		if ( !$this->_id )
		{
			$this->save();
			\IPS\File::claimAttachments( 'blogs-new-blog', $this->id, NULL, 'description' );
		}
	}
	
	/**
	 * Set the permission index permissions to a specific club
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @return  void
	 */
	public function setPermissionsToClub( \IPS\Member\Club $club )
	{
		// Deliberately do nothing, Blog handles permissions differently
	}
	
	/**
	 * Fetch All Nodes in Clubs
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @return	array
	 */
	public static function clubNodes( $permissionCheck='view', $member=NULL, $where=array() )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$where[] = array( 'blog_club_id IS NOT NULL' );
		
		if ( $member->modPermission('can_access_all_clubs') )
		{
			return static::nodesWithPermission( NULL, $member, $where );
		}
		else
		{
			if ( $permissionCheck === 'add' )
			{
				$statuses = array( \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_MODERATOR );
			}
			else
			{
				$statuses = array( \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_MEMBER );
			}
			$where[] = array( \IPS\Db::i()->in( 'status', $statuses ) );
			
			return iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'blog_blogs', $where )->join( 'core_clubs_memberships', array( 'club_id=blog_club_id AND member_id=?', $member->member_id ) ), \get_called_class() ) );
		}
	}

	/**
	 * Get category
	 *
	 * @return	\IPS\Node\Model
	 * @throws	\OutOfRangeException|\BadMethodCallException
	 */
	public function category()
	{
		if( !$this->category_id )
		{
			throw new \OutOfRangeException;
		}

		if ( $this->category === NULL )
		{
			$this->category	= \IPS\blog\Category::load( $this->category_id );
		}

		return $this->category;
	}
	
	/**
	 * Get last comment time
	 *
	 * @note	This should return the last comment time for this node only, not for children nodes
	 * @param   \IPS\Member|NULL    $member         MemberObject
	 * @return	\IPS\DateTime|NULL
	 */
	public function getLastCommentTime( \IPS\Member $member = NULL )
	{
		return $this->last_edate ? \IPS\DateTime::ts( $this->last_edate ) : NULL;
	}

	/**
	 * @brief   The class of the ACP \IPS\Node\Controller that manages this node type
	 */
	protected static $acpController = "IPS\\calendar\\modules\\admin\\calendars\\calendars";


	/**
	 * Get the URL of the AdminCP page for this node
	 *
	 * @param   string|NULL  $do The "do" query parameter of the url (e.g. 'form', 'permissions', etc).
	 *
	 * @return \IPS\Http\Url | NULL
	 */
	public function acpUrl( $do="form" )
	{
		try
		{
			$parentCol = static::$parentNodeColumnId;
			$parent = \IPS\blog\Category::load( $this->$parentCol );
			$url = parent::acpUrl( $do );
			if ( $url !== NULL )
			{
				$this->_acpUrls[$do] = $url->setQueryString([ 'subnode' => $this->$parentCol ]);
			}
		}
		catch ( \OutOfRangeException $e )
		{
			$this->_acpUrls[$do] = NULL;
		}
		return $this->_acpUrls[$do];
	}
}
