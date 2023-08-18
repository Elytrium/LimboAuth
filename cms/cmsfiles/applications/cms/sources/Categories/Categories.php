<?php
/**
 * @brief		Categories Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		8 April 2014
 */

namespace IPS\cms;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief Categories Model
 */
class _Categories extends \IPS\Node\Model implements \IPS\Node\Permissions
{
	use \IPS\Node\Statistics, \IPS\Content\ViewUpdates;

	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons = array();
	
	/**
	 * @brief	[Records] Custom Database Id
	 */
	public static $customDatabaseId = NULL;
	
	/**
	 * @brief	[Records] Content item class
	 */
	public static $contentItemClass = NULL;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'cms_database_categories';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'category_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';

	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array('category_furl_name', 'category_full_path');
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = 'parent_id';
	
	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[Node] Show forms modally?
	 */
	public static $modalForms = FALSE;
	
	/**
	 * @brief	[Node] Sortable?
	 */
	public static $nodeSortable = TRUE;
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'r__categories';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'content_cat_name_';

	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_desc';

	/**
	 * @breif   Used by the dataLayer, overwritten by \IPS\cms\Application
	 */
	public static $contentArea = 'pages_database';

	/**
	 * @breif   Used by the dataLayer, overwritten by \IPS\cms\Application
	 */
	public static $containerType = 'pages_database_category';
	
	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 array(
	 'app'		=> 'core',				// The application key which holds the restrictrions
	 'module'	=> 'foo',				// The module key which holds the restrictions
	 'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 'add'			=> 'foo_add',
	 'edit'			=> 'foo_edit',
	 'permissions'	=> 'foo_perms',
	 'delete'		=> 'foo_delete'
	 ),
	 'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
			'app'		=> 'cms',
			'module'	=> 'databases',
			'prefix' 	=> 'categories_'
	);
	
	/**
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = 'cms';
	
	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = NULL;
	
	/**
	 * @brief	The map of permission columns
	 */
	public static $permissionMap = array(
			'view' 				=> 'view',
			'read'				=> 2,
			'add'				=> 3,
			'edit'				=> 4,
			'reply'				=> 5,
			'review'            => 7,
			'rate'				=> 6
	);
	
	/**
	 * @brief	[Node] Moderator Permission
	 */
	public static $modPerm = 'cms';
	
	/**
	 * @brief	[Node] Prefix string that is automatically prepended to permission matrix language strings
	 */
	public static $permissionLangPrefix = 'perm_content_category_';
	
	/**
	 * @brief	[Page] Loaded pages from paths
	 */
	protected static $loadedCatsFromPath = array();

	/**
	 * @brief 	[Records] Database objects
	 */
	protected static $database = array();

	/**
	 * @brief   Latest posted record
	 */
	protected static $latestRecordAdded = NULL;

	/**
	 * Returns a database category object (or NULL) based on the path
	 *
	 * @param	string	$path		Path /like/this/maybearecordhere-r1234
	 * @param   int		$databaseId	Database ID to look up
	 * @return	NULL|\IPS\cms\Categories object
	 */
	public static function loadFromPath( $path, $databaseId=NULL )
	{
		$path = trim( $path, '/' );
	
		if ( ! array_key_exists( $path, static::$loadedCatsFromPath ) )
		{
			static::$loadedCatsFromPath[ $path ] = NULL;
				
			/* Try the simplest option */
			try
			{
				$where = ( $databaseId === NULL ) ? NULL : array( 'category_database_id=?', $databaseId );
				static::$loadedCatsFromPath[ $path ] =  static::load( $path, 'category_full_path', $where );
			}
			catch ( \OutOfRangeException $e )
			{
				/* May contain a record name */
				$where = ( $databaseId === NULL ) ? array( '? LIKE CONCAT( TRIM(TRAILING \'/\' FROM category_full_path), \'/\', \'%\')', rtrim( $path, '/' ) . '/' ) : array( 'category_database_id=? AND ? LIKE CONCAT( TRIM(TRAILING \'/\' FROM category_full_path), \'/\', \'%\')', $databaseId, rtrim( $path, '/' ) . '/' );

				foreach(
					\IPS\Db::i()->select(
						static::$databaseTable . '.*, core_permission_index.perm_id, core_permission_index.perm_view, core_permission_index.perm_2, core_permission_index.perm_3, core_permission_index.perm_4, core_permission_index.perm_5, core_permission_index.perm_6, core_permission_index.perm_7',
						static::$databaseTable,
						$where,
						'LENGTH(category_full_path) DESC'
					)->join(
							'core_permission_index',
							array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnId, static::$permApp, static::$permType )
					)
					as $meow )
				{
					static::$loadedCatsFromPath[ $path ] = static::constructFromData( $meow );

					break;
				}
			}
		}
	
		return static::$loadedCatsFromPath[ $path ];
	}

	/**
	 * Returns the database parent
	 *
	 * @return \IPS\cms\Databases
	 */
	public static function database()
	{
		if ( !isset( static::$database[ static::$customDatabaseId ] ) )
		{
			static::$database[ static::$customDatabaseId ] = \IPS\cms\Databases::load( static::$customDatabaseId );
		}

		return static::$database[ static::$customDatabaseId ];
	}
	
	/**
	 * Test to see if this is a valid container ID
	 *
	 * @param	int		$id		Container ID
	 * @return	boolean
	 */
	public static function isValidContainerId( $id )
	{
		if ( static::$containerIds === NULL )
		{
			static::$containerIds = iterator_to_array( \IPS\Db::i()->select( 'category_id', static::$databaseTable, array( array( 'category_database_id=?', static::$customDatabaseId ) ) ) );
		}

		return \in_array( $id, static::$containerIds );
	}

	/**
	 * @brief Cache of categories we've fetched
	 */
	protected static $cache = array();
	
	/**
	 * Fetch All Root Nodes
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @param	array|NULL			$limit				Limit/offset to use, or NULL for no limit (default)
	 * @return	array
	 */
	public static function roots( $permissionCheck='view', $member=NULL, $where=array(), $limit=NULL )
	{
		if ( static::$customDatabaseId !== NULL )
		{
			$where[] = array( 'category_database_id=?', static::$customDatabaseId );
		}
		
		return parent::roots( $permissionCheck, $member, $where, $limit );
	}
	
	/**
	 * [Node] Fetch Child Nodes
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULL to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	bool				$subnodes			Include subnodes? NULL to *only* check subnodes
	 * @param	array|NULL			$skip				Children IDs to skip
	 * @param	mixed				$_where				Additional WHERE clause
	 * @return	array
	 */
	public function children( $permissionCheck='view', $member=NULL, $subnodes=TRUE, $skip=null, $_where=array() )
	{
		$permissionCheck = ( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation === 'admin' ) ? NULL : $permissionCheck;
		
		return parent::children( $permissionCheck, $member, $subnodes, $skip, $_where );
	}
	
	/**
	 * Resets a category path
	 *
	 * @param 	int 	$categoryId		Category ID to reset
	 * @return	void
	 */
	public static function resetPath( $categoryId )
	{
		try
		{
			$category = static::load( $categoryId );
		}
		catch ( \OutOfRangeException $ex )
		{
			throw new \OutOfRangeException;
		}
	
		$category->setFullPath();
	}

	/**
	 * Ensure there aren't any collision issues.
	 *
	 * @param   string  $path   Path to check
	 * @return  boolean
	 */
	static public function isFurlCollision( $path )
	{
		$path  = trim( $path , '/');
		$bits  = explode( '/', $path );
		$root  = $bits[0];
		
		/* _ is here due to IP.Board 3.x using that to denote the articles database (eg. articles.html/_/category/record-r1/), which we need to redirect from for records and articles. */
		if ( \in_array( $root, array( 'submit', '_' ) ) )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Set the "extra" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_fields( $value )
	{
		$this->_data['fields'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "extra" field
	 *
	 * @return array
	 */
	public function get_fields()
	{
		return ( $this->_data['fields'] === '*' OR $this->_data['fields'] === NULL ) ? '*' : json_decode( $this->_data['fields'], TRUE );
	}
	
	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		parent::__clone();
		
		if( $this->skipCloneDuplication === TRUE )
		{
			return;
		}
		
		$this->furl_name .= '_' . $this->id;
		$this->save();

		$this->setFullPath();
	}

	/**
	 * Retrieve an array of IDs a member has posted in.
	 *
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in member)
	 * @param	array|NULL			$inSet	If supplied, checks will be restricted to only the ids provided
	 * @param   array|NULL          $additionalWhere    Additional where clause
	 * @param	array|NULL			$commentJoinWhere	Additional join clause for comments table
	 * @return	array				An array of content item ids
	 */
	public function contentPostedIn( $member=NULL, $inSet=NULL, $additionalWhere=NULL, $commentJoinWhere=NULL )
	{
		$database = \IPS\cms\Databases::load( $this->database_id );
		
		if ( $database->forum_record and $database->forum_forum )
		{
			return array();
		}
		
		/* What about local category forum sync? */
		if ( $this->forum_record and $this->forum_forum )
		{
			return array();
		}
		
		$contentItemClass = static::$contentItemClass;
		$commentClass     = $contentItemClass::$commentClass;

		return parent::contentPostedIn( $member, $inSet, NULL, $commentClass::commentWhere() );
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$database = \IPS\cms\Databases::load( \IPS\Request::i()->database_id );

		/* Build form */
		$form->addTab( 'content_content_form_tab__config' );
		
		$form->add( new \IPS\Helpers\Form\Translatable( 'category_name', NULL, TRUE, array(
				'app'		  => 'cms',
				'key'		  => ( $this->id ? "content_cat_name_" .  $this->id : NULL )
		) ) );
		
		if ( ! $this->id )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'category_furl_name_choice', FALSE, FALSE, array(
					'togglesOn' => array('category_furl_name')
			), NULL, NULL, NULL, 'category_furl_name_choice' ) );
		}
		
		$form->add( new \IPS\Helpers\Form\Text( 'category_furl_name', $this->furl_name, FALSE, array(), function( $val )
		{
			/* Make sure key is unique */
			if ( empty( $val ) )
			{
				return true;
			}
			
			if ( \IPS\Request::i()->category_parent_id == 0 and \IPS\cms\Categories::isFurlCollision( $val ) )
			{
				throw new \InvalidArgumentException('content_cat_furl_collision');
			}
			
			try
			{
				$cat = \IPS\Db::i()->select( '*', 'cms_database_categories', array( 'category_database_id=? and category_parent_id=? and category_furl_name=?', \IPS\Request::i()->database_id, \IPS\Request::i()->category_parent_id, $val ) )->first();
			}
			catch( \UnderflowException $ex )
			{
				/* Nuffink matches */
				return true;
			}
			
			if ( isset( \IPS\Request::i()->id ) )
			{
				if ( $cat['category_id'] != \IPS\Request::i()->id )
				{
					throw new \InvalidArgumentException('content_cat_furl_not_unique');
				}
			}
			else
			{
				throw new \InvalidArgumentException('content_cat_furl_not_unique');
			}
			
			return true;
		}, NULL, NULL, 'category_furl_name' ) );
		
		$form->add( new \IPS\Helpers\Form\Translatable( 'category_description', NULL, FALSE, array(
				'app'		  => 'cms',
				'key'		  => ( $this->id ? "content_cat_name_" .  $this->id . "_desc" : NULL )
		) ) );

		$class = \get_called_class();

		$form->add( new \IPS\Helpers\Form\Node( 'category_parent_id', ( ! $this->id ) ? ( isset( \IPS\Request::i()->parent ) ? \IPS\Request::i()->parent : 0 ) : $this->parent_id, FALSE, array(
			'class'		      => '\IPS\cms\Categories' . $database->id,
			'disabled'	      => false,
			'zeroVal'         => 'node_no_parent',
			'permissionCheck' => function( $node ) use ( $class )
			{
				if( isset( $class::$subnodeClass ) AND $class::$subnodeClass AND $node instanceof $class::$subnodeClass )
				{
					return FALSE;
				}
					
				return !isset( \IPS\Request::i()->id ) or ( $node->id != \IPS\Request::i()->id and !$node->isChildOf( $node::load( \IPS\Request::i()->id ) ) );
			}
		) ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_show_records', $this->id ? $this->show_records : TRUE, FALSE, array(), NULL, NULL, NULL, 'category_show_records' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_allow_rating', $this->allow_rating, FALSE, array(), NULL, NULL, NULL, 'category_allow_rating' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_has_perms', $this->has_perms, FALSE, array(), NULL, NULL, NULL, 'category_has_perms' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_can_view_others', $this->id ? $this->can_view_others : TRUE, FALSE, array(), NULL, NULL, NULL, 'category_can_view_others' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'allow_anonymous', $this->id ? $this->allow_anonymous : FALSE, FALSE, array() ) );
		
		$form->addHeader('cms_categories_header_display');
		$templatesList     = array( 0 => \IPS\Member::loggedIn()->language()->addToStack('cms_categories_use_database') );
		$templatesDisplay  = array( 0 => \IPS\Member::loggedIn()->language()->addToStack('cms_categories_use_database') );

		foreach( \IPS\cms\Templates::getTemplates( \IPS\cms\Templates::RETURN_DATABASE + \IPS\cms\Templates::RETURN_DATABASE_AND_IN_DEV ) as $template )
		{
			$title = \IPS\cms\Templates::readableGroupName( $template->group );

			switch( $template->original_group )
			{
				case 'listing':
					$templatesList[ $template->group ] = $title;
					break;
				case 'display':
					$templatesDisplay[ $template->group ] = $title;
					break;
			}
		}

		$form->add( new \IPS\Helpers\Form\Select( 'category_template_listing', ( ( $this->id and $this->template_listing ) ? $this->template_listing : '0' ), FALSE, array( 'options' => $templatesList ) ) );
		$form->add( new \IPS\Helpers\Form\Select( 'category_template_display', ( ( $this->id and $this->template_display ) ? $this->template_display : '0' ), FALSE, array( 'options' => $templatesDisplay ) ) );

		$form->addTab( 'content_content_form_header__meta' );
		
		$form->add( new \IPS\Helpers\Form\Text( 'category_page_title',  $this->page_title, FALSE, array(), NULL, NULL, NULL ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'category_meta_keywords', $this->meta_keywords, FALSE, array(), NULL, NULL, NULL, 'category_meta_keywords' ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'category_meta_description', $this->meta_description, FALSE, array(), NULL, NULL, NULL, 'category_meta_description' ) );

		if ( \IPS\Application::appIsEnabled( 'forums' ) )
		{
			$form->addTab( 'content_content_form_tab__forum' );
			
			$form->add( new \IPS\Helpers\Form\YesNo( 'category_forum_override', ( $this->id ? $this->forum_override : NULL ), FALSE, array(
					'togglesOn' => array(
						'database_forum_record',
						'database_forum_comments',
						'database_forum_forum',
						'database_forum_prefix',
						'database_forum_suffix',
						'database_forum_delete'	
					)
			), NULL, NULL, NULL, 'category_forum_override' ) );

			try
			{
				\IPS\Db::i()->select( '*', 'core_queue', [ "`app`=? AND `key`=? AND `data` LIKE CONCAT( '%', ?, '%' )", 'cms', 'MoveComments', 'databaseID":' . $database->id ] )->first();
				\IPS\Member::loggedIn()->language()->words['database_forum_record_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'database_forum_comments_in_progress' );
				$disabled = true;
			}
			catch( \UnderflowException $e )
			{
				$disabled = FALSE;
			}
			
			$form->add( new \IPS\Helpers\Form\YesNo( 'database_forum_record', $this->id ? $this->forum_record : FALSE, FALSE, array( 'togglesOn' => array(
					'database_forum_comments',
					'database_forum_forum',
					'database_forum_prefix',
					'database_forum_suffix',
					'database_forum_delete'
			),
				'disabled' => $disabled ), NULL, NULL, NULL, 'database_forum_record' ) );

			$form->add( new \IPS\Helpers\Form\YesNo( 'database_forum_comments', $this->id ? $this->forum_comments : FALSE, FALSE, array( 'disabled' => $disabled ), NULL, NULL, NULL, 'database_forum_comments' ) );

			$form->add( new \IPS\Helpers\Form\Node( 'database_forum_forum', $this->id ? $this->forum_forum : NULL, FALSE, array(
					'class'		      => '\IPS\forums\Forum',
					'disabled'	      => false,
					'permissionCheck' => function( $node )
					{
						return $node->sub_can_post;
					}
			), function( $val )
			{
				if ( ! $val and \IPS\Request::i()->category_forum_override and \IPS\Request::i()->database_forum_record_checkbox )
				{
					throw new \InvalidArgumentException('cms_database_no_forum_selected');
				}
				return true;
			}, NULL, NULL, NULL, 'database_forum_forum' ) );
				
			$form->add( new \IPS\Helpers\Form\Text( 'database_forum_prefix',  $this->id ? $this->forum_prefix: '', FALSE, array( 'trim' => FALSE ), NULL, NULL, NULL, 'database_forum_prefix' ) );
			$form->add( new \IPS\Helpers\Form\Text( 'database_forum_suffix',  $this->id ? $this->forum_suffix: '', FALSE, array( 'trim' => FALSE ), NULL, NULL, NULL, 'database_forum_suffix' ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'database_forum_delete', $this->id ? $this->forum_delete : FALSE, FALSE, array(), NULL, NULL, NULL, 'database_forum_delete' ) );
		}
		
		$form->addTab( 'content_content_form_header__fields' );
		
		$cats		= $this->id ? ( $this->fields === NULL ? '*' : $this->fields ) : '*';
		$options	= array();
		$fieldClass	= 'IPS\cms\Fields' . $database->id;

		foreach( $fieldClass::data( NULL, NULL, $fieldClass::FIELD_SKIP_TITLE_CONTENT ) as $field )
		{
			if ( $field->id !== $database->field_title AND $field->id !== $database->field_content )
			{
				$options[ $field->id ] = $field->_title;
			}		
		}

		if ( \count( $options ) )
		{
			$form->add( new \IPS\Helpers\Form\Select( 'category_fields', $cats, FALSE, array(
				'multiple'  => true,
				'unlimited' => '*',
				'options'   => $options,
			), NULL, NULL, NULL, 'category_fields' ) );
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
		if ( ! $this->database_id )
		{
			if ( isset( $values['category_database_id'] ) )
			{
				$this->database_id = $values['category_database_id'];
			}
			else if ( isset( \IPS\Request::i()->database_id ) )
			{
				$this->database_id = \IPS\Request::i()->database_id;
			}

			$values['category_database_id'] = $this->database_id;
		}
		
		/* Need this for later */
		$_new = $this->_new;

		if ( ! $this->id )
		{
			$this->_updatePaths = TRUE;
			$this->save();
		}

		if ( isset( $values['category_name'] ) AND \is_array( $values['category_name'] ) )
		{
			$name = $values['category_name'][ \IPS\Lang::defaultLanguage() ];
		}
		else if( isset( $values['category_name'] ) )
		{
			$name = $values['category_name'];
		}

		/* Save the name and description */
		if( isset( $values['category_name'] ) )
		{
			\IPS\Lang::saveCustom( 'cms', 'content_cat_name_' . $this->id, $values['category_name'] );
		}

		if( isset( $values['category_description'] ) )
		{
			\IPS\Lang::saveCustom( 'cms', 'content_cat_name_' . $this->id . '_desc', $values['category_description'] );
			unset( $values['category_description'] );
		}
		
		if ( isset( $name ) AND empty( $values['category_furl_name'] ) )
		{
			$ok  = FALSE;
		
			try
			{
				$cat = \IPS\cms\Categories::load( \IPS\Http\Url\Friendly::seoTitle( $name ), 'category_furl_name' );
				
				/* We have a cat, is it in a different database? */
				if ( $cat->database_id != \IPS\Request::i()->database_id )
				{
					$ok = TRUE;
				}
					
				if ( isset( \IPS\Request::i()->id ) )
				{
					if ( $cat->id == \IPS\Request::i()->id )
					{
						$ok = TRUE;
					}
				}
			}
			catch ( \OutOfRangeException $e )
			{
				$ok = TRUE;
			}

			if ( \IPS\Request::i()->category_parent_id == 0 and \IPS\cms\Categories::isFurlCollision( \IPS\Http\Url\Friendly::seoTitle( $name ) ) )
			{
				$ok = FALSE;
			}

			if ( $ok === TRUE )
			{
				$values['furl_name'] = \IPS\Http\Url\Friendly::seoTitle( $name );
			}
			else
			{
				$values['furl_name'] = $this->id . '_' . \IPS\Http\Url\Friendly::seoTitle( $name );
			}
		}
		else if( isset( $values['category_furl_name'] ) )
		{
			$values['furl_name'] = \IPS\Http\Url\Friendly::seoTitle( $values['category_furl_name'] );
			
			/* We cannot have numeric furl_names 'cos you could do page/2/ and it will confuse SEO pagination. This is not possible with other areas as it is always /id-furl/ */
			if ( \is_numeric( $values['furl_name'] ) )
			{
				$values['furl_name'] = 'n' . $values['furl_name'];
			}
		}

		if( array_key_exists( 'category_furl_name_choice', $values ) )
		{
			unset( $values['category_furl_name_choice'] );
		}
		
		if ( isset( $values['category_parent_id'] ) AND ( ! empty( $values['category_parent_id'] ) OR $values['category_parent_id'] === 0 ) )
		{
			$values['category_parent_id'] = ( $values['category_parent_id'] === 0 ) ? 0 : $values['category_parent_id']->id;
		}

		if ( $this->furl_name !== $values['furl_name'] or $this->parent_id !== $values['category_parent_id'] )
		{
			$this->_updatePaths = TRUE;
		}

		if ( isset( $values['category_template_listing'] ) )
		{
			$values['category_template_listing'] = ( $values['category_template_listing'] !== '_none_' ) ? $values['category_template_listing'] : NULL;
		}

		if ( isset( $values['category_template_display'] ) )
		{
			$values['category_template_display'] = ( $values['category_template_display'] !== '_none_' ) ? $values['category_template_display'] : NULL;
		}

		if ( \IPS\Application::appIsEnabled( 'forums' ) )
		{
			$values['forum_override'] = isset( $values['category_forum_override'] ) ? $values['category_forum_override'] : 0;
			unset( $values['category_forum_override'] );
			
			foreach( array( 'forum_record', 'forum_comments', 'forum_prefix', 'forum_suffix', 'forum_delete' ) as $field )
			{
				if ( array_key_exists( 'database_' . $field, $values ) )
				{
					$values[ $field ] = $values[ 'database_' . $field ];
					unset( $values[ 'database_' . $field ] );
				}
			}
			
			/* Are we changing where comments go? */
			if ( !$_new AND ( (int) $this->forum_record != (int) $values['forum_record'] OR (int) $this->forum_comments != (int) $values['forum_comments'] ) )
			{
				\IPS\Task::queue( 'cms', 'MoveComments', array(
					'databaseId'		=> $this->database()->id,
					'categoryId'		=> $this->_id,
					'to'				=> ( $values['forum_comments'] AND $values['forum_record'] ) ? 'forums' : 'pages',
					'deleteTopics'		=> (bool) ( !$values['forum_record'] )
				), 1, array( 'databaseId', 'to', 'categoryId' ) );
			}

			if ( isset( $values['database_forum_forum'] ) )
			{
				$values['forum_forum'] = ( !$values['database_forum_forum'] ) ? 0 : $values['database_forum_forum']->id;
				unset( $values['database_forum_forum'] );
			}
		}

		$values['category_allow_rating']	= ( isset( $values['category_allow_rating'] ) ) ? (int) $values['category_allow_rating'] : 0;
		$values['category_has_perms']		= ( isset( $values['category_has_perms'] ) ) ? (int) $values['category_has_perms'] : 0;
		$values['forum_record']				= ( isset( $values['forum_record'] ) ) ? (int) $values['forum_record'] : 0;
		$values['forum_comments']			= ( isset( $values['forum_comments'] ) ) ? (int) $values['forum_comments'] : 0;
		$values['forum_delete']				= ( isset( $values['forum_delete'] ) ) ? (int) $values['forum_delete'] : 0;

		return $values;
	}

	/**
	 * [Node] Perform actions after saving the form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function postSaveForm( $values )
	{
		$this->save();

		/* Clone permissions from the database */
		if ( ! $this->has_perms )
		{
			$this->cloneDatabasePermissions();
		}

		if ( $this->_updatePaths )
		{
			$this->setFullPath();
		}
	}

	/**
	 * Clone permissions from the parent database
	 * 
	 * @return void
	 */
	public function cloneDatabasePermissions()
	{
		$catPerms = $this->permissions(); /* Called to ensure it has a perm row */
		$dbPerms  = \IPS\cms\Databases::load( $this->database_id )->permissions();

		$this->_permissions = array_merge( $dbPerms, array( 'perm_id' => $catPerms['perm_id'], 'perm_type_id' => $this->_id, 'perm_type' => 'categories_' . $this->database_id ) );

		if( $this->_permissions['perm_view'] === NULL )
		{
			$this->_permissions['perm_view'] = '';
		}

		\IPS\Db::i()->update( 'core_permission_index', $this->_permissions, array( 'perm_id=?', $catPerms['perm_id'] ) );

		/* Update tags permission cache */
		if ( isset( static::$permissionMap['read'] ) )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_text' => $dbPerms[ 'perm_' . static::$permissionMap['read'] ] ), array( 'tag_perm_aap_lookup=?', md5( static::$permApp . ';' . static::$permType . ';' . $this->_id ) ) );
		}
	}
	
	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		$database = \IPS\cms\Databases::load( $this->database_id );
		if ( !$database->cat_index_type and $database->numberOfCategories() <= 1 )
		{
			return FALSE;
		}
		return parent::canDelete();
	}

	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		if ( !$this->_new AND isset( $this->changed['can_view_others'] ) )
		{
			$this->updateSearchIndexPermissions();
		}
		parent::save();
	}

	/**
	 *  Delete
	 *
	 * @return void
	 */
	public function delete()
	{
		/* Remove tags, if any */
		$aap = md5( 'content;categories;' . $this->id );

		\IPS\Db::i()->delete( 'core_tags', array( 'tag_aap_lookup=?', $aap ) );
		\IPS\Db::i()->delete( 'core_tags_perms', array( 'tag_perm_aap_lookup=?', $aap ) );

		/* Delete category follows */
		\IPS\Db::i()->delete( 'core_follow', array( "follow_app=? AND follow_area=? AND follow_rel_id=?", 'cms', 'categories' . $this->database()->id, $this->_id ) );

		parent::delete();
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * @brief	Cached title
	 */
	protected $_catTitle = NULL;
	
	/**
	 * @brief	Cached title for strip tags version
	 */
	protected $_catTitleLangKey = NULL;
	
	/**
	 * @brief	Last comment time
	 */
	protected $_lastCommentTime = FALSE;

	/**
	 * @brief   Permissions mashed up with db
	 */
	protected $_permsMashed = FALSE;

	/**
	 * @brief   FURL changed
	 */
	protected $_updatePaths = FALSE;
	
	/**
	 * Disabled permissions
	 * Allow node classes to define permissions that are unselectable in the permission matrix
	 *
	 * @return array	array( {group_id} => array( 'read', 'view', 'perm_7' );
	 */
	public function disabledPermissions()
	{
		$database  = \IPS\cms\Databases::load( $this->database_id );
		$dbPerms   = $database->permissions();
		$disabled  = array();
		
		foreach( array( 'view', 2, 3, 4, 5, 6, 7 ) as $perm )
		{
			/* Remove unticked database permissions */
			if ( $dbPerms['perm_' . $perm ] != '*' )
			{
				$db = explode( ',', $dbPerms['perm_' . $perm ] );
				
				foreach ( \IPS\Member\Group::groups() as $group )
				{
					if ( ! \in_array( $group->g_id, $db ) )
					{
						$disabled[ $group->g_id ][] = $perm;
					}
				}
			}
		}

		try
		{
			$guestGroup = \IPS\Member\Group::load( \IPS\Settings::i()->guest_group );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \UnderflowException( 'invalid_guestgroup_admin', 199 );
		}

		if( !$this->can_view_others )
		{
			$disabled[ $guestGroup->g_id ] = array( 'view', 2, 3, 4, 5, 6, 7 );
		}

		return $disabled;
	}
	
	/**
	 * Get permissions
	 *
	 * @return	array
	 */
	public function permissions()
	{
		/* Let the ACP/Setup use normal permissions or it'll get messy */
		if ( !\IPS\Dispatcher::hasInstance() OR \IPS\Dispatcher::i()->controllerLocation !== 'front' )
		{
			$this->_permsMashed = true;
			return parent::permissions();
		}

		if ( ! $this->_permsMashed )
		{
			/* Make sure we have perms */
			if ( ! $this->_permissions )
			{
				parent::permissions();
			}

			$database  = \IPS\cms\Databases::load( $this->database_id );
			$dbPerms   = $database->permissions();
			$savePerms = $this->_permissions;

			foreach( array( 'view', 2, 3, 4, 5, 6, 7 ) as $perm )
			{
				/* Make sure category permission cannot be better than database permissions */
				if ( $dbPerms['perm_' . $perm ] != $savePerms['perm_' . $perm ] )
				{
					/* Category using *? Use database instead */
					if ( $savePerms['perm_' . $perm ] == '*' )
					{
						$savePerms['perm_' . $perm ] = $dbPerms['perm_' . $perm ];
					}
					else if ( $dbPerms['perm_' . $perm ] == '*' )
					{
						/* That's fine, cat is going to be less permissive than * */
						continue;
					}
					else
					{
						/* Make sure that groups not in the database are not in here too */
						$db  = explode( ',', $dbPerms['perm_' . $perm ] );
						$cat = explode( ',', $savePerms['perm_' . $perm ] );

						$savePerms['perm_' . $perm ] = implode( ',', array_intersect( $db, $cat ) );
					}
				}
			}

			$savePerms['perm_2'] = $this->readPermissionMergeWithPage( $savePerms );

			$this->_permissions = $savePerms;
			$this->_permsMashed = TRUE;
		}

		return $this->_permissions;
	}

	/**
	 * Return least favourable permissions based on category and page
	 *
	 * @param   array   $perms      Array of perms
	 *
	 * @return string
	 */
	public function readPermissionMergeWithPage( $perms=NULL )
	{
		$database = \IPS\cms\Databases::load( $this->database_id );

		/* Now check against the page */
		if ( $database->page_id )
		{
			try
			{
				$page      = \IPS\cms\Pages\Page::load( $database->page_id );
				$pagePerms = $page->permissions();
				$catPerms  = ( $perms ) ? $perms : $this->permissions();

				if ( $pagePerms['perm_view'] === '*' )
				{
					return $catPerms['perm_2'];
				}
				else if ( $catPerms['perm_2'] === '*' )
				{
					return $pagePerms['perm_view'];
				}
				else
				{
					return implode( ',', array_intersect( explode( ',', $pagePerms['perm_view'] ), explode( ',', $catPerms['perm_2'] ) ) );
				}
			}
			catch ( \OutOfRangeException $ex )
			{

			}
		}

		return $this->_permissions['perm_2'];
	}

	/**
	 * Get URL
	 *
	 * @return	\IPS\Http\Url|NULL
	 */
	public function url()
	{
		if( $this->_url === NULL )
		{
			if ( \IPS\cms\Pages\Page::$currentPage and \IPS\cms\Databases::load( $this->database_id )->page_id == \IPS\cms\Pages\Page::$currentPage->id )
			{
				$pagePath = \IPS\cms\Pages\Page::$currentPage->full_path;
			}
			else
			{
				try
				{
					$pagePath = \IPS\cms\Pages\Page::loadByDatabaseId( $this->database_id )->full_path;
				}
				catch( \OutOfRangeException $e )
				{
					return NULL;
				}
			}
			
			$catPath  = $this->full_path;

			if ( \IPS\cms\Databases::load( $this->database_id )->use_categories )
			{
				$this->_url = \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . $pagePath . '/' . $catPath, 'front', 'content_page_path', $this->furl_name );
			}
			else
			{
				$this->_url = \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . $pagePath, 'front', 'content_page_path', $this->furl_name );
			}
		}

		return $this->_url;
	}
	
	/**
	 * Get URL from index data
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @return	\IPS\Http\Url
	 */
	public static function urlFromIndexData( $indexData, $itemData, $containerData )
	{
		$recordClass = $indexData['index_class'];
		if ( \in_array( 'IPS\Content\Comment', class_parents( $recordClass ) ) )
		{
			$recordClass = $recordClass::$itemClass;
		}
		if ( $recordClass::$pagePath === NULL )
		{
			$recordClass::$pagePath = \IPS\Db::i()->select( array( 'page_full_path' ), 'cms_pages', array( 'page_id=?', $recordClass::database()->page_id ) )->first();
		}
				
		if ( $recordClass::database()->use_categories )
		{
			return \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . $recordClass::$pagePath . '/' . $itemData['extra'], 'front', 'content_page_path', $itemData['extra'] );
		}
		else
		{
			return \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . $recordClass::$pagePath, 'front', 'content_page_path', '' );
		}
	}
	
	/**
	 * Get title from index data
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	bool		$escape			If the title should be escaped for HTML output
	 * @return	\IPS\Http\Url
	 */
	public static function titleFromIndexData( $indexData, $itemData, $containerData, $escape = TRUE )
	{
		$recordClass = $indexData['index_class'];
		if ( \in_array( 'IPS\Content\Comment', class_parents( $recordClass ) ) )
		{
			$recordClass = $recordClass::$itemClass;
		}
		
		if ( $recordClass::database()->use_categories )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $indexData['index_container_id'], NULL, $escape ? array( 'escape' => $escape ) : array() );
		}
		else
		{
			return $escape ? $recordClass::database()->_title : $recordClass::database()->getTitleForLanguage( \IPS\Member::loggedIn()->language() );
		}
	}
	
	/**
	 * Get Page Title for use in `<title>` tag
	 *
	 * @return	string
	 */
	public function pageTitle()
	{
		if ( $this->page_title )
		{
			return $this->page_title;
		}
		
		return $this->database()->pageTitle();
	}

	/**
	 * Get title of category
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		/* If the DB is in a page, and we're not using categories, then return the page title, not the category title for continuity */
		$database = \IPS\cms\Databases::load( $this->database_id );
		if ( ! $database->use_categories )
		{
			if ( ! $this->_catTitle )
			{
				if ( $database->use_as_page_title )
				{
					$this->_catTitle = $database->_title;
				}
				else
				{
					try
					{
						$page = \IPS\cms\Pages\Page::loadByDatabaseId( $this->database_id );
						$this->_catTitle = $page->_title;
					}
					catch ( \OutOfRangeException $e )
					{
						$this->_catTitle = parent::get__title();
					}
				}
			}

			return $this->_catTitle;
		}
		else
		{
			return parent::get__title();
		}
	}

	/**
	 * Get the title for a node using the specified language object
	 * This is commonly used where we cannot use the logged in member's language, such as sending emails
	 *
	 * @param	\IPS\Lang	$language	Language object to fetch the title with
	 * @param	array 		$options	What options to use for language parsing
	 * @return	string
	 */
	public function getTitleForLanguage( $language, $options=array() )
	{
		if ( ! \IPS\cms\Databases::load( $this->database_id )->use_categories )
		{
			return $language->addToStack( 'content_db_' . $this->database_id, NULL, $options );
		}
		
		return parent::getTitleForLanguage( $language, $options );
	}
	
	/**
	 * [Node] Get Title language key, not added to a language stack
	 *
	 * @return	string|null
	 */
	protected function get__titleLanguageKey()
	{
		/* If the DB is in a page, and we're not using categories, then return the page title, not the category title for continuity */
		if ( ! \IPS\cms\Databases::load( $this->database_id )->use_categories )
		{
			if ( ! $this->_catTitleLangKey )
			{
				try
				{
					$page = \IPS\cms\Pages\Page::loadByDatabaseId( $this->database_id );
					$this->_catTitleLangKey = $page->_titleLanguageKey;
				}
				catch( \OutOfRangeException $e )
				{
					$this->_catTitleLangKey = parent::get__titleLanguageKey();
				}
			}

			return $this->_catTitleLangKey;
		}
		else
		{
			return parent::get__titleLanguageKey();
		}
	}

	/**
	 * [Node] Get Description
	 *
	 * @return	string|null
	 */
	protected function get__description()
	{
		if ( ! static::database()->use_categories )
		{
			return static::database()->_description;
		}

		return ( \IPS\Member::loggedIn()->language()->addToStack('content_cat_name_' . $this->id . '_desc') === 'content_cat_name_' . $this->id . '_desc' ) ? $this->description : \IPS\Member::loggedIn()->language()->addToStack('content_cat_name_' . $this->id . '_desc');
	}

	/**
	 * Get number of items
	 *
	 * @return	int
	 */
	protected function get__items()
	{
		if ( ! $this->can_view_others and !\IPS\Member::loggedIn()->modPermission( 'can_content_view_others_records' ) )
		{
			return \IPS\Db::i()->select('count(*)', 'cms_custom_database_' . $this->database_id, array("record_future_date=0 AND category_id=? AND record_approved=1 AND member_id=?", $this->id, \IPS\Member::loggedIn()->member_id) )->first();
		}
		
		return (int) $this->records;
	}
	
	/**
	 * Set number of items
	 *
	 * @param	int	$val	Items
	 * @return	int
	 */
	protected function set__items( $val )
	{
		$this->records = (int) $val;
	}

	/**
	 * Get number of reviews
	 *
	 * @return	int
	 */
	protected function get__reviews()
	{
		return (int) $this->record_reviews;
	}

	/**
	 * Set number of reviews
	 *
	 * @param	int	$val	Comments
	 * @return	int
	 */
	protected function set__reviews( $val )
	{
		$this->record_reviews = (int) $val;
	}

	/**
	 * Get number of unapproved reviews
	 *
	 * @return	int
	 */
	protected function get__unapprovedReviews()
	{
		return (int) $this->record_reviews_queued;
	}

	/**
	 * Set number of unapproved reviews
	 *
	 * @param	int	$val	Comments
	 * @return	int
	 */
	protected function set__unapprovedReviews( $val )
	{
		$this->record_reviews_queued = (int) $val;
	}

	/**
	 * Get number of comments
	 *
	 * @return	int
	 */
	protected function get__comments()
	{
		return (int) $this->record_comments;
	}
	
	/**
	 * Set number of items
	 *
	 * @param	int	$val	Comments
	 * @return	int
	 */
	protected function set__comments( $val )
	{
		$this->record_comments = (int) $val;
	}
	
	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @return	int
	 */
	protected function get__unapprovedItems()
	{
		return $this->records_queued;
	}
	
	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @return	int
	 */
	protected function get__unapprovedComments()
	{
		return $this->record_comments_queued;
	}
	
	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @param	int	$val	Unapproved Items
	 * @return	void
	 */
	protected function set__unapprovedItems( $val )
	{
		$this->records_queued = $val;
	}

	/**
	 * [Node] Get number of future publishing items
	 *
	 * @return	int
	 */
	protected function get__futureItems()
	{
		return $this->records_future;
	}

	/**
	 * [Node] Get number of future content items
	 *
	 * @param	int	$val	Future Items
	 * @return	void
	 */
	protected function set__futureItems( $val )
	{
		$this->records_future = $val;
	}
	
	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @param	int	$val	Unapproved Comments
	 * @return	void
	 */
	protected function set__unapprovedComments( $val )
	{
		$this->record_comments_queued = $val;
	}

	/**
	 * Get the template listing template
	 *
	 * @return  string      Templateg group
	 */
	public function get__template_listing()
	{
		if ( $this->template_listing AND static::database()->use_categories )
		{
			return $this->template_listing;
		}

		return static::database()->template_listing;
	}

	/**
	 * Get the template display template
	 *
	 * @return  string      Templateg group
	 */
	public function get__template_display()
	{
		if ( $this->template_display AND static::database()->use_categories )
		{
			return $this->template_display;
		}

		return static::database()->template_display;
	}
	
	/**
	 * Set last comment
	 *
	 * @param	\IPS\Content\Comment	$comment	The latest comment or NULL to work it out
	 * @return	void
	 */
	public function setLastComment( \IPS\Content\Comment $comment=NULL )
	{
		$database = \IPS\cms\Databases::load( $this->database_id );

		/* Make sure it wasn't a comment added to a hidden record */
		if ( $comment !== NULL )
		{
			if ( $comment->item()->hidden() OR $comment->item()->isFutureDate() )
			{
				$comment = NULL;
			}
		}

		if ( $comment === NULL )
		{   
			try
			{
				$recordClass  = '\IPS\cms\Records' . $this->database_id;
				$commentClass = '\IPS\cms\Records\Comment' . $this->database_id;
				$comment      = NULL;

				if ( static::$latestRecordAdded === NULL )
				{
					static::$latestRecordAdded = $recordClass::constructFromData(
						\IPS\Db::i()->select(
							'*',
							'cms_custom_database_' . $this->database_id,
							array( 'category_id=? AND record_approved=1 AND record_future_date=0', $this->id ),
							'record_last_comment DESC, primary_id_field DESC', /* Just in case RSS imports the exact same time */
							array( 0, 1 ),
							NULL,
							NULL,
							\IPS\Db::SELECT_FROM_WRITE_SERVER
						)->first()
					);
				}

				if ( static::$latestRecordAdded->record_comments )
				{
					if ( static::$latestRecordAdded->record_comments AND ( $database->_comment_bump & \IPS\cms\Databases::BUMP_ON_COMMENT ) )
					{
						if ( static::$latestRecordAdded->useForumComments() )
						{
							$syncRecord = static::$latestRecordAdded;
							
							try
							{
								$comment = $syncRecord->comments( 1, 0, 'date', 'desc', NULL, FALSE );
							}
							catch( \Exception $e ) { }
						}
						else
						{
							try
							{
								$comment = $commentClass::constructFromData( \IPS\Db::i()->select( '*', 'cms_database_comments', array( 'comment_record_id=? AND comment_approved=1', \IPS\Db::i()->select( 'primary_id_field', 'cms_custom_database_' . $this->database_id, array( 'category_id=? AND record_approved=1', $this->id ), 'record_last_comment DESC', 1 )->first() ), 'comment_date DESC', 1, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first() );
							}
							catch( \UnderflowException $e ) { }
						}
					}

					if ( $comment and ( static::$latestRecordAdded->record_last_comment > $comment->mapped('date') ) )
					{
						$comment = NULL;

					}
				}
			}
			catch ( \UnderflowException $e )
			{
				$this->last_record_date   = 0;
				$this->last_record_member = 0;
				$this->last_record_name = '';
				$this->last_title = NULL;
				$this->last_record_id = 0;
				$this->last_poster_anon = 0;
				return;
			}
		}

		if ( $comment !== NULL and ( $database->_comment_bump & \IPS\cms\Databases::BUMP_ON_COMMENT ) )
		{
			$this->last_record_date     = $comment->mapped('date');
			$this->last_record_member   = \intval( $comment->author()->member_id );
			$this->last_record_name     = $comment->author()->member_id ? $comment->author()->name : NULL;
			$this->last_record_seo_name = \IPS\Http\Url\Friendly::seoTitle( $this->last_poster_name );
			$this->last_title           = mb_substr( $comment->item()->mapped('title'), 0, 255 );
			$this->last_seo_title       = \IPS\Http\Url\Friendly::seoTitle( $this->last_title );
			$this->last_record_id       = $comment->item()->_id;
			$this->last_poster_anon		= $comment->isAnonymous();
		}
		else if ( static::$latestRecordAdded !== NULL )
		{
			$this->last_record_date     = static::$latestRecordAdded->record_saved;
			$this->last_record_member   = static::$latestRecordAdded->member_id;
			$this->last_record_name     = static::$latestRecordAdded->member_id ? static::$latestRecordAdded->record_last_comment_name : NULL;
			$this->last_title           = mb_substr( static::$latestRecordAdded->_title, 0, 255 );
			$this->last_seo_title       = \IPS\Http\Url\Friendly::seoTitle( mb_substr( static::$latestRecordAdded->_title, 0, 255 ) );
			$this->last_record_id       = static::$latestRecordAdded->_id;
			$this->last_poster_anon		= static::$latestRecordAdded->isAnonymous();
		}
		
		$this->records        = \IPS\Db::i()->select( 'COUNT(*)', 'cms_custom_database_' . $this->database_id, array( 'record_approved=1 AND record_future_date=0 AND category_id=?', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$this->records_queued = \IPS\Db::i()->select( 'COUNT(*)', 'cms_custom_database_' . $this->database_id, array( 'record_approved=0 AND category_id=?', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$this->records_future = \IPS\Db::i()->select( 'COUNT(*)', 'cms_custom_database_' . $this->database_id, array( 'record_future_date=1 AND category_id=?', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();

		static::$latestRecordAdded = NULL;
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
		$member = $member ?: \IPS\Member::loggedIn();
		if( !$this->can_view_others and !$member->modPermission( 'can_content_view_others_records' ) )
		{
			try
			{
				$select = \IPS\Db::i()->select('record_last_comment', 'cms_custom_database_' . $this->database_id, array("record_future_date=0 AND category_id=? AND record_approved=1 AND member_id=?", $this->id, $member->member_id ), 'record_last_comment DESC', 1 )->first();
			}
			catch ( \UnderflowException $e )
			{
				return NULL;
			}

			return $select ?  \IPS\DateTime::ts( $select ) : NULL;
		}

		return $this->last_record_date ? \IPS\DateTime::ts( $this->last_record_date ) : NULL;
	}
	
	/**
	 * Get last post data
	 *
	 * @return	array|NULL
	 */
	public function lastPost()
	{
		$result = NULL;
		$RecordsClass = static::$contentItemClass;

		/* This category does not allow you to see records from other users... */
		if( $this->can_view_others or \IPS\Member::loggedIn()->modPermission( 'can_content_view_others_records' ) )
		{
			if ( $this->last_record_date )
			{
				try
				{
					$result = array( 'author' => \IPS\Member::load( $this->last_record_member ), 'record_url' => $RecordsClass::load( $this->last_record_id )->url(), 'record_title' => $this->last_title, 'date' => $this->last_record_date );

					if ( !$this->last_record_member AND $this->last_record_name )
					{
						$result[ 'author' ]->name = $this->last_record_name;
					}
				}
				catch ( \OutOfRangeException $e )
				{
				}
			}
		}
		else
		{
			try
			{
				$record = $RecordsClass::constructFromData( \IPS\Db::i()->select('*', 'cms_custom_database_' . $this->database_id, array("record_future_date=0 AND category_id=? AND record_approved=1 AND member_id=?", $this->id, \IPS\Member::loggedIn()->member_id), 'record_last_comment DESC', 1 )->first() );
				$result = array( 'author' => $record->author(), 'record_url' => $record->url(), 'record_title' => $record->_title, 'date' => $record->record_last_comment );
			}
			catch ( \Exception $e )
			{
			}
		}

		foreach( $this->children() as $child )
		{
			if ( $childLastPost = $child->lastPost() )
			{
				if ( !$result or $childLastPost['date'] > $result['date'] )
				{
					$result = $childLastPost;
				}
			}
		}

		return $result;
	}

	/**
	 * Resets a folder path
	 *
	 * @return	void
	 */
	public function setFullPath()
	{
		$this->full_path = $this->furl_name;

		if ( $this->parent_id )
		{
			$parentId = $this->parent_id;
			$failSafe = 0;
			$path     = array();

			while( $parentId != 0 )
			{
				if ( $failSafe > 50 )
				{
					break;
				}

				try
				{
					$parent = static::load( $parentId );

					if ( ! $parent->furl_name )
					{
						$parent->furl_name = \IPS\Http\Url\Friendly::seoTitle( $parent->name );
					}

					$parentId = $parent->parent_id;
					$path[]   = $parent->furl_name;
				}
				catch( \OutOfRangeException $e )
				{
					break;
				}

				$failSafe++;
			}

			krsort( $path );
			$path[] = $this->furl_name;

			$this->full_path = trim( implode( '/', $path ), '/' );
		}

		$this->save();

		foreach ( $this->children( NULL ) as $child )
		{
			$child->setFullPath();
		}
	}
	
	/**
	 * [Node] Does the currently logged in user have permission to edit permissions for this node?
	 *
	 * @return	bool
	 */
	public function canManagePermissions()
	{
		$can = parent::canManagePermissions();
		
		return ( $can === FALSE ) ? FALSE : (boolean) $this->has_perms;
	}
	
	/**
	 * Get which permission keys can access all records in a category which
	 * can normally only show records to the author
	 * 
	 * @return	array
	 */
	public function permissionsThatCanAccessAllRecords()
	{
		$normal		= $this->searchIndexPermissions();
		$return		= array();
		$members	= array();
		
		foreach ( \IPS\Db::i()->select( '*', 'core_moderators' ) as $moderator )
		{
			if ( $moderator['perms'] === '*' or \in_array( 'can_content_view_others_records', explode( ',', $moderator['perms'] ) ) )
			{
				if( $moderator['type'] === 'g' )
				{
					$return[] = $moderator['id'];
				}
				else
				{
					$members[] = "m{$moderator['id']}";
				}
			}
		}
		
		$return = ( $normal == '*' ) ? array_unique( $return ) : array_intersect( explode( ',', $normal ), array_unique( $return ) );
	
		if( \count( $members ) )
		{
			$return = array_merge( $return, $members );
		}
		
		return $return;
	}
	
	/**
	 * Update search index permissions
	 *
	 * @return  void
	 */
	protected function updateSearchIndexPermissions()
	{
		if ( $this->can_view_others )
		{
			return parent::updateSearchIndexPermissions();
		}
		else
		{
			$permissions = implode( ',', $this->permissionsThatCanAccessAllRecords() );
			\IPS\Content\Search\Index::i()->massUpdate( 'IPS\cms\Records' . static::database()->_id, $this->_id, NULL, $permissions, NULL, NULL, NULL, NULL, NULL, TRUE );
			\IPS\Content\Search\Index::i()->massUpdate( 'IPS\cms\Records\Comment' . static::database()->_id, $this->_id, NULL, $permissions, NULL, NULL, NULL, NULL, NULL, TRUE );
		}
	}
	
	/**
	 * Get the filter cookie for this category
	 *
	 * @return array|null
	 */
	public function getFilterCookie()
	{
		if ( isset( \IPS\Request::i()->cookie['cms_filters'] ) )
		{
			$saved = json_decode( \IPS\Request::i()->cookie['cms_filters'], TRUE );

			if ( array_key_exists( $this->id, $saved ) and \count( $saved[ $this->id ] ) )
			{
				return $saved[ $this->id ];
			}
		}

		return NULL;
	}

	/**
	 * Save filter cookie for this category
	 *
	 * @param   array|FALSE  $values Filter values to save (array) or FALSE to remove cookie
	 * @return null
	 */
	public function saveFilterCookie( $values )
	{
		$cookie = ( isset( \IPS\Request::i()->cookie['cms_filters'] ) ) ? json_decode( \IPS\Request::i()->cookie['cms_filters'], TRUE ) : array();

		if ( $values === FALSE )
		{
			if ( array_key_exists( $this->id, $cookie ) )
			{
				unset( $cookie[ $this->id ] );
			}
		}
		else
		{
			/* We only want to include ones where we have actually specified values to filter on */
			$toSave = array();
			foreach( $values AS $key => $data )
			{
				if ( \is_numeric( $key ) or $key == 'cms_record_i_started' )
				{
					$toSave[$key] = $data;
				}
			}
			
			$cookie[ $this->id ] = $toSave;
		}

		\IPS\Request::i()->setCookie( 'cms_filters', json_encode( $cookie ), \IPS\DateTime::create()->add( new \DateInterval( 'P7D' ) ) );
	}

    /**
     * [Node] Get meta description
     *
     * @return	string
     */
    public function metaDescription()
    {
        return $this->meta_description ?: static::database()->metaDescription();
    }

	/**
	 * [Node] Get meta title
	 *
	 * @return	string
	 */
	public function metaTitle()
	{
		return $this->page_title ?: static::database()->metaTitle();
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
		$_member = $member ?: \IPS\Member::loggedIn();

		if ( !$_member->member_id and !$this->can_view_others )
		{
			return FALSE;
		}

		return parent::can( $permission, $member, $considerPostBeforeRegistering );
	}

	/**
	 * Search
	 *
	 * @param	string		$column	Column to search
	 * @param	string		$query	Search query
	 * @param	string|null	$order	Column to order by
	 * @param	mixed		$where	Where clause
	 * @return	array
	 */
	public static function search( $column, $query, $order, $where=array() )
	{
		$results = parent::search( $column, $query, $order, $where );

		return array_filter( $results, function( $node ){
			return $node->database_id == static::$customDatabaseId;
		} );
	}

	/**
	 * Get the properties that can be added to the datalayer for this key
	 *
	 * @return  array
	 */
	public function getDataLayerProperties()
	{
		if ( empty( $this->_dataLayerProperties ) )
		{
			$db = $this->database();

			if ( $db->use_categories )
			{
				$properties = parent::getDataLayerProperties();
				$properties['content_area'] = static::$contentArea;
				$properties['container_type'] = static::$containerType;
			}
			else
			{
				$properties = $db->getDataLayerProperties();
			}

			$this->_dataLayerProperties = $properties;
		}

		return $this->_dataLayerProperties;
	}
}