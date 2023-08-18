<?php
/**
 * @brief		Module Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Application;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Node class for Modules
 */
class _Module extends \IPS\Node\Model implements \IPS\Node\Permissions
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_modules';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'sys_module_';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'sys_module_key' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
		
	/**
	 * @brief	[Node] Parent Node ID Database Column
	 */
	public static $parentNodeColumnId = 'application';
	
	/**
	 * @brief	[Node] Parent Node Class
	 */
	public static $parentNodeClass = 'IPS\Application';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'applications_and_modules';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[Node] Enabled/Disabled Column
	 */
	public static $databaseColumnEnabledDisabled = 'visible';
	
	/**
	* @brief	[Node] App for permission index
	*/
	public static $permApp = 'core';
		
	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = 'module';
	
	/**
	 * @brief	[Node] Prefix string that is automatically prepended to permission matrix language strings
	 */
	public static $permissionLangPrefix = 'module_';
	
	/**
	 * @brief	[Node] ACP Restrictions
	 */
	protected static $restrictions = array( 'app' => 'core', 'module' => 'applications', 'all' => 'module_manage' );

	/**
	 * @brief	[Node] Sortable?
	 */
	public static $nodeSortable = FALSE;
	
	/**
	 * @brief	All modules
	 */
	protected static $modules 	= NULL;
	
	/**
	 * Get Modules
	 *
	 * @return	array
	 */
	public static function modules()
	{
		if( static::$modules === NULL )
		{
			static::$modules = array();
			foreach ( static::getStore() as $row )
			{
				static::$modules[ $row['sys_module_application'] ][ $row['sys_module_area'] ][ $row['sys_module_key'] ] = static::constructFromData( $row );
			}
		}
		
		return static::$modules;
	}

	/**
	 * Get data store
	 *
	 * @return	array
	 * @note	Note that all records are returned, even disabled report rules. Enable status needs to be checked in userland code when appropriate.
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->modules ) )
		{
			\IPS\Data\Store::i()->modules = iterator_to_array( \IPS\Db::i()->select( '*', 'core_modules', NULL, 'sys_module_position' )->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=core_modules.sys_module_id", 'core', 'module' ) ) );
		}
		
		return \IPS\Data\Store::i()->modules;
	}
	
	/**
	 * Get a module
	 *
	 * @param	string		$app
	 * @param	string		$key
	 * @param	string|NULL	$area
	 * @return	\IPS\Application\Module
	 * @throws	\OutOfRangeException
	 */
	public static function get( $app, $key, $area=NULL )
	{
		$modules = static::modules();
		if ( isset( $modules[ $app ] ) )
		{
			$area = $area ?: \IPS\Dispatcher::i()->controllerLocation;
			if ( isset( $modules[ $app ][ $area ] ) )
			{
				if ( isset( $modules[ $app ][ $area ][ $key ] ) )
				{
					return $modules[ $app ][ $area ][ $key ];
				}
			}
		}
		
		throw new \OutOfRangeException;
	}
	
	/**
	 * Set module as default for this area
	 *
	 * @return void
	 */
	public function setAsDefault()
	{
		\IPS\Db::i()->update( 'core_modules', array( 'sys_module_default' => 0 ), array( 'sys_module_area=? AND sys_module_application=?', $this->area, $this->application ) );
		\IPS\Db::i()->update( 'core_modules', array( 'sys_module_default' => 1 ), array( 'sys_module_id=?', $this->id ) );
		unset( \IPS\Data\Store::i()->modules );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();
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
	public static function search( $column, $query, $order=NULL, $where=array() )
	{
		if ( $column === '_title' )
		{
			$return = array();
			foreach( \IPS\Member::loggedIn()->language()->words as $k => $v )
			{
				if ( preg_match( '/^module__([a-z]*)_([a-z]*)$/', $k, $matches ) and mb_strpos( mb_strtolower( $v ), mb_strtolower( $query ) ) !== FALSE )
				{
					try
					{
						$module = static::load( $matches[2], 'sys_module_key', \count( $where ) ? array_merge( array( array( 'sys_module_application=? and sys_module_area=?', $matches[1], 'front' ) ), array( $where ) ) : array( array( 'sys_module_application=?', $matches[1] ) ) );
						$return[ $module->_id ] = $module;
					}
					catch ( \OutOfRangeException $e ) { }
				}
			}
			return $return;
		}
		return parent::search( $column, $query, $order, $where );
	}

	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 *
	 * @code
	 	array(
	 		array(
	 			'icon'	=>	array(
	 				'icon.png'			// Path to icon
	 				'core'				// Application icon belongs to
	 			),
	 			'title'	=> 'foo',		// Language key to use for button's title parameter
	 			'link'	=> \IPS\Http\Url::internal( 'app=foo...' )	// URI to link to
	 			'class'	=> 'modalLink'	// CSS Class to use on link (Optional)
	 		),
	 		...							// Additional buttons
	 	);
	 * @endcode
	 * @param	string	$url	Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = array();

		if( $this->canManagePermissions() )
		{
			$buttons['permissions'] = array(
				'icon'	=> 'lock',
				'title'	=> 'permissions',
				'link'	=> "{$url}&do=permissions&id={$this->_id}" . ( $subnode ? '&subnode=1' : '' ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('permissions') )
			);
		}

		$buttons['default']	= array(
			'icon'		=> $this->default ? 'star' : 'star-o',
			'title'		=> 'make_default_module',
			'link'		=> $url->csrf() . "&do=setDefaultModule&id={$this->_id}&default=1",
		);

		return $buttons;
	}
	
	/**
	 * [Node] Get Node Title
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		$key = "module__{$this->application}_{$this->key}";
		return \IPS\Member::loggedIn()->language()->addToStack( $key );
	}
	
	/**
	 * [Node] Get the title to store in the log
	 *
	 * @return	string|null
	 */
	public function titleForLog()
	{
		try
		{ 
			return \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( "module__{$this->application}_{$this->key}" );
		}
		catch ( \UnderflowException $e )
		{
			return $this->_title;
		}
	}
	
	/**
	 * [Node] Get Node Icon
	 *
	 * @return	string
	 */
	protected function get__icon()
	{
		return 'cube';
	}
			
	/**
	 * [Node] Get whether or not this node is locked to current enabled/disabled status
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__locked()
	{
		return $this->protected;
	}
	
	/**
	 * [Node] Does the currently logged in user have permission to add a child node?
	 *
	 * @return	bool
	 * @note	Modules don't really have "child nodes".  Controllers are not addable via the ACP.
	 */
	public function canAdd()
	{
		return false;
	}
	
	/**
	 * [Node] Does the currently logged in user have permission to edit permissions for this node?
	 *
	 * @return	bool
	 */
	public function canManagePermissions()
	{
		if ( $this->protected )
		{
			return FALSE;
		}
		
		return parent::canManagePermissions();
	}

	/**
	 * [Node] Does this node have children?
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	bool				$subnodes			Include subnodes?
	 * @param	mixed				$_where				Additional WHERE clause
	 * @return	bool
	 */
	public function hasChildren( $permissionCheck='view', $member=NULL, $subnodes=TRUE, $_where=array() )
	{
		return false;
	}
	
	/**
	 * [Node] Fetch Child Nodes
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULL to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	bool				$subnodes			Include subnodes?
	 * @param	array|NULL			$skip				Children IDs to skip
	 * @param	mixed				$_where				Additional WHERE clause
	 * @return	array
	 */
	public function children( $permissionCheck='view', $member=NULL, $subnodes=TRUE, $skip=null, $_where=array() )
	{
		return array();
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form ){}

	/**
	 * Save
	 *
	 * @param	bool	$skipMember		Skip clearing member cache clearing
	 * @return	void
	 */
	public function save( $skipMember=FALSE )
	{
		$new = $this->_new;

		$this->_skipClearingMenuCache = $skipMember;
		
		parent::save();

		$this->_skipClearingMenuCache = FALSE;
		
		if ( $new )
		{
			/* There is a unique constraint against app + perm_type + perm_type_id, so we use replace() instead of insert()
				in case there is already a row in the database for this constraint */
			\IPS\Db::i()->replace( 'core_permission_index', array(
					'app'			=> 'core',
					'perm_type'		=> 'module',
					'perm_type_id'	=> $this->id,
					'perm_view'		=> '*',
			) );
		}
	}

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'modules' );

	/**
	 * @brief Skip clearing create menu cache
	 */
	protected $_skipClearingMenuCache = FALSE;

	/**
	 * Clear any defined caches
	 *
	 * @param	bool	$removeMultiton		Should the multiton record also be removed?
	 * @return void
	 */
	public function clearCaches( $removeMultiton=FALSE )
	{
		parent::clearCaches( $removeMultiton );

		if( $this->_skipClearingMenuCache === FALSE )
		{
			\IPS\Member::clearCreateMenu();
		}
	}
}