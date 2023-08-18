<?php
/**
 * @brief		Node Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Node;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Node Model
 */
abstract class _Model extends \IPS\Patterns\ActiveRecord
{
	/* !Abstract Properties */

	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = NULL;

	/**
	 * @brief	[Node] Parent ID Root Value
	 * @note	This normally doesn't need changing though some legacy areas use -1 to indicate a root node
	 */
	public static $databaseColumnParentRootValue = 0;

	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = NULL;

	/**
	 * @brief	[Node] Automatically set position for new nodes
	 */
	public static $automaticPositionDetermination = TRUE;

	/**
	 * @brief	[Node] Enabled/Disabled Column
	 */
	public static $databaseColumnEnabledDisabled = NULL;

	/**
	 * @brief	[Node] If the node can be "owned", the owner "type" (typically "member" or "group") and the associated database column
	 */
	public static $ownerTypes = NULL;

	/**
	 * @brief	[Node] Sortable?
	 */
	public static $nodeSortable = TRUE;

	/**
	 * @brief	[Node] Subnode class
	 */
	public static $subnodeClass = NULL;

	/**
	 * @brief	[Node] Show forms modally?
	 */
	public static $modalForms = FALSE;

	/**
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = NULL;

	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = NULL;

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}" as the key
	 */
	public static $titleLangPrefix = NULL;

	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = NULL;

	/**
	 * @brief	[Node] Prefix string that is automatically prepended to permission matrix language strings
	 */
	public static $permissionLangPrefix = '';

	/**
	 * @brief	[Node] By mapping appropriate columns (rating_average and/or rating_total + rating_hits) allows to cache rating values
	 */
	public static $ratingColumnMap	= array();

	/**
	 * @brief	[Node] Maximum results to display at a time in any node helper form elements. Useful for user-submitted node types when there may be a lot. NULL for no limit.
	 */
	public static $maxFormHelperResults = NULL;

	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	array(
	'app'		=> 'core',				// The application key which holds the restrictrions
	'module'	=> 'foo',				// The module key which holds the restrictions
	'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	'add'					=> 'foo_add',
	'edit'					=> 'foo_edit',
	'permissions'			=> 'foo_perms',
	'massManageContent'		=> 'foo_massManageContent',
	'delete'				=> 'foo_delete'
	),
	'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/massManageContent/delete"
	 * @endcode
	 */
	protected static $restrictions = NULL;

	/* !Static Methods */

	/**
	 * @brief	Cache for roots
	 */
	protected static $rootsResult = array();

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
		/* Will we need to check permissions? */
		$usingPermssions = ( \in_array( 'IPS\Node\Permissions', class_implements( \get_called_class() ) ) and $permissionCheck !== NULL );
		if ( $usingPermssions )
		{
			$member = $member ?: \IPS\Member::loggedIn();
		}
				
		/* Specify that we only want the ones without a parent */
		if( static::$databaseColumnParent !== NULL )
		{
			$where[] = array( static::$databasePrefix . static::$databaseColumnParent . '=?', static::$databaseColumnParentRootValue );
		}
		
		/* And aren't in clubs */
		if ( \IPS\IPS::classUsesTrait( \get_called_class(), 'IPS\Content\ClubContainer' ) )
		{
			$where[] = array( static::$databasePrefix . static::clubIdColumn() . ' IS NULL' );
		}
		
		/* Have we got a cached result we can use? */
		if ( $usingPermssions )
		{
			$cacheKey = md5( \get_called_class() . $permissionCheck . $member->member_id . json_encode( $where ) . json_encode( $limit ) );
		}
		else
		{
			$cacheKey = md5( \get_called_class() . $permissionCheck . json_encode( $where ) . json_encode( $limit ) );
		}
		
		if( isset( static::$rootsResult[ $cacheKey ] ) )
		{
			return static::$rootsResult[ $cacheKey ];
		}
		
		/* Fetch */
		$nodes = static::nodesWithPermission( $usingPermssions ? $permissionCheck : NULL, $member, $where, NULL, $limit );

		/* Set cache */
		static::$rootsResult[ $cacheKey ] = $nodes;

		/* Return */
		return static::$rootsResult[ $cacheKey ];
	}
	
	/**
	 * Fetch All Root Nodes
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @param	string				$order				ORDER BY clause
	 * @param	array|NULL			$limit				Limit/offset to use, or NULL for no limit (default)
	 * @return	array
	 */
	protected static function nodesWithPermission( $permissionCheck, $member, $where=array(), $order=NULL, $limit=NULL )
	{
		/* Permission check? */
		if ( $permissionCheck )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$where[] = array( '(' . \IPS\Db::i()->findInSet( 'core_permission_index.perm_' . static::$permissionMap[ $permissionCheck ], $member->permissionArray() ) . ' OR ' . 'core_permission_index.perm_' . static::$permissionMap[ $permissionCheck ] . '=? )', '*' );
			if ( static::$databaseColumnEnabledDisabled )
			{
				$where[] = array( static::$databasePrefix . static::$databaseColumnEnabledDisabled . '=1' );
			}
		}
		
		/* Specify the order */
		if( $order == NULL and static::$databaseColumnOrder !== NULL )
		{
			$order = static::$databasePrefix . static::$databaseColumnOrder;
		}
		
		/* Select */
		$select = \IPS\Db::i()->select( '*', static::$databaseTable, $where, $order, $limit );
		if ( $permissionCheck )
		{
			$select->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnId, static::$permApp, static::$permType ) );
		}
		$select->setKeyField( static::$databasePrefix . static::$databaseColumnId );

		/* Fetch */
		$nodes = array();
		foreach( $select as $k => $data )
		{
			try
			{
				$nodes[ $k ] = static::constructFromData( $data );
			}
			catch ( \Exception $e ) { }
		}

		/* Return */
		return $nodes;
	}

	/**
	 * Get a count of all nodes
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULL to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @return	array
	 */
	public static function countWhere( $permissionCheck='view', $member=NULL, $where=array() )
	{
		/* Permission check? */
		$usingPermssions = ( \in_array( 'IPS\Node\Permissions', class_implements( \get_called_class() ) ) and $permissionCheck !== NULL );
		if ( $usingPermssions )
		{
			$member = $member ?: \IPS\Member::loggedIn();

			$where[] = array( '(' . \IPS\Db::i()->findInSet( 'core_permission_index.perm_' . static::$permissionMap[ $permissionCheck ], $member->groups ) . ' OR ' . 'core_permission_index.perm_' . static::$permissionMap[ $permissionCheck ] . '=? )', '*' );
			if ( static::$databaseColumnEnabledDisabled )
			{
				$where[] = array( static::$databasePrefix . static::$databaseColumnEnabledDisabled . '=1' );
			}
		}

		/* Select */
		$select = \IPS\Db::i()->select( 'COUNT(*)', static::$databaseTable, $where );
		if ( $usingPermssions )
		{
			$select->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnId, static::$permApp, static::$permType ) );
		}

		/* Return */
		return $select->first();
	}

	/**
	 * Fetch All Root Nodes as array
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @return	array
	 */
	public static function rootsAsArray( $permissionCheck='view', $member=NULL, $where=array() )
	{
		$return = array();
		foreach ( static::roots( $permissionCheck, $member, $where ) as $node )
		{
			$return[ $node->_id ] = $node->_title;
		}
		return $return;
	}

	/**
	 * @brief	Cache for owned noded
	 */
	protected static $ownedNodesCache = array();

	/**
	 * Fetch all nodes owned by a given user
	 *
	 * @param	\IPS\Member|NULL	$member		The member whose nodes to load
	 * @param	array				$where		Initial where clause
	 * @return	array
	 * @throws	\RuntimeException
	 */
	public static function loadByOwner( $member=NULL, $where=array() )
	{
		/* Can these nodes even be owned? */
		if( static::$ownerTypes === NULL )
		{
			throw new \RuntimeException;
		}

		/* Load member */
		$member = $member === NULL ? \IPS\Member::loggedIn() : $member;

		if( \is_int( $member ) )
		{
			$member	= \IPS\Member::load( $member );
		}

		/* Check the cache first */
		if( isset( static::$ownedNodesCache[ md5( \get_called_class() . $member->member_id . json_encode( $where ) ) ] ) )
		{
			return static::$ownedNodesCache[ md5( \get_called_class() . $member->member_id . json_encode( $where ) ) ];
		}

		/* Specify the order */
		$order = NULL;
		if( static::$databaseColumnOrder !== NULL )
		{
			$order = static::$databasePrefix . static::$databaseColumnOrder;
		}

		/* Select */
		if( isset( static::$ownerTypes['member'] ) and isset( static::$ownerTypes['group'] ) )
		{
			$where[] = array( '(' . \IPS\Db::i()->findInSet( static::$databasePrefix . static::$ownerTypes['group']['ids'], $member->groups ) . ' OR ' . static::$databasePrefix . static::$ownerTypes['member'] . '=? )', $member->member_id );
		}
		elseif( isset( static::$ownerTypes['member'] ) )
		{
			$where[] = array( static::$databasePrefix . static::$ownerTypes['member'] . '=?', $member->member_id );
		}
		else
		{
			$where[] = array( \IPS\Db::i()->findInSet( static::$databasePrefix . static::$ownerTypes['group']['ids'], $member->groups ) );
		}

		$select = \IPS\Db::i()->select( '*', static::$databaseTable, $where, $order );

		$select->setKeyField( static::$databasePrefix . static::$databaseColumnId );

		/* Fetch */
		$nodes = array();
		foreach( $select as $k => $data )
		{
			$nodes[ $k ] = static::constructFromData( $data );
		}

		/* Set cache */
		static::$ownedNodesCache[ md5( \get_called_class(). $member->member_id  . json_encode( $where ) ) ] = $nodes;

		/* Return */
		return static::$ownedNodesCache[ md5( \get_called_class(). $member->member_id  . json_encode( $where ) ) ];
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
		if ( $column === '_title' AND static::$titleLangPrefix !== NULL )
		{
			$return = array();
			foreach ( \IPS\Member::loggedIn()->language()->searchCustom( static::$titleLangPrefix, $query ) as $key => $value )
			{
				try
				{
					$return[ $key ] = static::load( $key );
				}
				catch ( \OutOfRangeException $e ) { }
			}

			return $return;
		}

		$nodes = array();
		foreach( \IPS\Db::i()->select( '*', static::$databaseTable, array_merge( array( array( "{$column} LIKE CONCAT( '%', ?, '%' )", $query ) ), $where ), $order ) as $k => $data )
		{
			$nodes[ $k ] = static::constructFromData( $data );
		}
		return $nodes;
	}

	/**
	 * Last Poster ID Column
	 */
	protected static $lastPosterIdColumn;

	/**
	 * Load into memory (taking permissions into account)
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	array				$where				Additional where clause
	 * @return	void
	 */
	public static function loadIntoMemory( $permissionCheck='view', $member=NULL, $where = array() )
	{
		/* Init */
		$member = $member ?: \IPS\Member::loggedIn();
		$cacheKey = md5( $permissionCheck . $member->member_id . TRUE . json_encode( NULL ) . json_encode( array() ) );
		$rootsCacheKey = md5( \get_called_class() . $permissionCheck . $member->member_id . json_encode( array() ) );

		/* Exclude disabled */
		if ( static::$databaseColumnEnabledDisabled )
		{
			$where[] = array( static::$databasePrefix . static::$databaseColumnEnabledDisabled . '=1' );
		}

		/* Run query */
		$order = static::$databaseColumnOrder !== NULL ? static::$databasePrefix . static::$databaseColumnOrder : NULL;
		if ( \in_array( 'IPS\Node\Permissions', class_implements( \get_called_class() ) ) and $permissionCheck !== NULL )
		{
			$where[] = array( '(' . \IPS\Db::i()->findInSet( 'perm_' . static::$permissionMap[ $permissionCheck ], $member->permissionArray() ) . ' OR ' . 'perm_' . static::$permissionMap[ $permissionCheck ] . '=? )', '*' );

			$select = \IPS\Db::i()->select( '*', static::$databaseTable, $where, $order, NULL, NULL, NULL, \IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS )
				->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnId, static::$permApp, static::$permType ) );
		}
		else
		{
			$select = \IPS\Db::i()->select( '*', static::$databaseTable, NULL, $order, NULL, NULL, NULL, \IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS );
		}

		/* Join last poster */
		if ( static::$lastPosterIdColumn )
		{
			$select->join( 'core_members', 'core_members.member_id=' . static::$databaseTable . '.' . static::$databasePrefix . static::$lastPosterIdColumn );
		}

		/* Put into a tree */
		$childrenResults = array();
		foreach ( $select as $row )
		{
			/* If the class does not implement permissions or last poster ID nest the result */
			if( !isset( $row[ static::$databaseTable ] ) )
			{
				$row[ static::$databaseTable ] = $row;
			}

			/* If we have member data, store it to prevent an extra query later */
			if ( isset( $row['core_members'] ) )
			{
				\IPS\Member::constructFromData( $row['core_members'], FALSE );
			}

			/* Create object */
			$obj = static::constructFromData( isset( $row['core_permission_index'] ) ? array_merge( $row[ static::$databaseTable ], $row['core_permission_index'] ) : $row[ static::$databaseTable ], FALSE );

			/* Put into tree */
			$obj->_childrenResults[ $cacheKey ] = array();
			if ( static::$databaseColumnParent === NULL or $row[ static::$databaseTable ][ static::$databasePrefix . static::$databaseColumnParent ] === static::$databaseColumnParentRootValue )
			{
				static::$rootsResult[ $rootsCacheKey ][ $obj->_id ] = $obj;
			}
			else
			{
				$childrenResults[ $row[ static::$databaseTable ][ static::$databasePrefix . static::$databaseColumnParent ] ][ $obj->_id ] = $obj;
			}
		}

		/* And set the multitons */
		foreach ( $childrenResults as $parentId => $children )
		{
			if( isset( static::$multitons[ $parentId ] ) )
			{
				static::$multitons[ $parentId ]->_childrenResults[ $cacheKey ] = $children;
			}
		}
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * Get URL
	 *
	 * @return	\IPS\Http\Url
	 * @throws	\BadMethodCallException
	 */
	public function url()
	{
		if ( isset( static::$urlBase ) and isset( static::$urlTemplate ) and isset( static::$seoTitleColumn ) )
		{
			if( $this->_url === NULL )
			{
				$seoTitleColumn = static::$seoTitleColumn;
				$this->_url = \IPS\Http\Url::internal( static::$urlBase . $this->_id, 'front', static::$urlTemplate, array( $this->$seoTitleColumn ) );
			}

			return $this->_url;
		}
		throw new \BadMethodCallException;
	}

	/**
	 * @brief	Cached AdminCP urls
	 */
	protected $_acpUrls	= array();

	/**
	 * @brief   The class of the ACP \IPS\Node\Controller that manages this node type
	 */
	protected static $acpController = NULL;

	/**
	 * Get the URL of the AdminCP page for this node
	 *
	 * @param   string|NULL  $do The "do" query parameter of the url (e.g. 'form', 'permissions', etc).
	 *
	 * @return \IPS\Http\Url | NULL
	 */
	public function acpUrl( $do="form" )
	{
		if ( !isset( $this->acpUrls[$do] ) AND static::$acpController !== NULL )
		{
			/* Does the method exist? */
			if ( method_exists( static::$acpController, $do ) )
			{
				$bits = explode( "\\", static::$acpController );
				$acpUrlBase = "app={$bits[1]}&module={$bits[4]}&controller={$bits[5]}";
				$this->_acpUrls[$do] = \IPS\Http\Url::internal( $acpUrlBase )->setQueryString( array( 'do' => $do, 'id' => $this->_id ) );
			}
		}
		return $this->_acpUrls[$do] ?? NULL;
	}

	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		$return = array( static::$databasePrefix . static::$databaseColumnId, static::$databasePrefix . static::$seoTitleColumn );
		
		/* Using colorize trait? We can't check the object but we can check the added class variable */
		if ( isset( static::$featureColumnName ) )
		{
			$return[] = static::$databasePrefix . static::$featureColumnName;
		}
		
		return $return;
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
		return \IPS\Http\Url::internal( static::$urlBase . $indexData['index_container_id'], 'front', static::$urlTemplate, array( $containerData[ static::$databasePrefix . static::$seoTitleColumn ] ) );
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
		if ( $indexData['index_club_id'] and isset( $containerData['_club'] ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'club_container_title', FALSE, array( 'sprintf' => array( $containerData['_club']['name'], \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $indexData['index_container_id'], 'NULL', $escape ? array( 'escape' => TRUE ) : array() ) ) ) );
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $indexData['index_container_id'], NULL, $escape ? array( 'escape' => TRUE ) : array() );
		}
	}

	/**
	 * [Node] Fetches the only node if only one exists
	 * One Node to rule them all, One Node to find them, One Node to bring them all and in the darkness bind them
	 *
	 * @param	array       $properties				Array of property=>value i.e. array( 'redirect_url' => FALSE, 'password' => FALSE );
	 * @param	bool        $returnRoots			Enable to check and return the root node
	 * @param	bool        $subNodes				Enable to check subnodes
	 * @return	\IPS\Node\Model|NULL
	 */
	public static function theOnlyNode( $properties=array(), $returnRoots=TRUE, $subNodes=TRUE )
	{
		if ( \count( static::roots() ) === 1 )
		{
			foreach ( static::roots() as $root )
			{
				if ( $root->childrenCount( 'view', NULL, $subNodes ) === 1 )
				{
					foreach ( $root->children( 'view', NULL, $subNodes ) as $node )
					{
						/* Check properties */
						foreach( $properties as $name => $bool )
						{
							if( $node->$name != $bool )
							{
								continue 2;
							}
						}

						/* If we're just checking root objects, we don't want to return the child */
						if( $returnRoots )
						{
							return NULL;
						}

						return $node;
					}
				}
				/* There are no children */
				elseif( $root->childrenCount( 'view', NULL, $subNodes ) === 0 and $returnRoots )
				{
					return $root;
				}
			}
		}

		return NULL;
	}

	/**
	 * [Node] Get the title to store in the log
	 *
	 * @return	string|null
	 */
	public function titleForLog()
	{
		if ( static::$titleLangPrefix )
		{
			try
			{
				return \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( static::$titleLangPrefix . $this->_id );
			}
			catch ( \UnderflowException $e )
			{
				return static::$titleLangPrefix . $this->_id;
			}
		}
		else
		{
			return $this->_title;
		}
	}

	/* !Getters */

	/**
	 * [Node] Get ID Number
	 *
	 * @return	int
	 */
	protected function get__id()
	{
		$idColumn = static::$databaseColumnId;
		return $this->$idColumn;
	}

	/**
	 * [Node] Get Title
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		if ( static::$titleLangPrefix )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $this->_id, NULL, array( 'escape' => TRUE ) );
		}

		return '';
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
		if ( static::$titleLangPrefix )
		{
			return $language->addToStack( static::$titleLangPrefix . $this->_id, NULL, $options );
		}

		return '';
	}
	
	/**
	 * [Node] Get Title language key, not added to a language stack
	 *
	 * @return	string|null
	 */
	protected function get__titleLanguageKey()
	{
		if ( static::$titleLangPrefix )
		{
			return static::$titleLangPrefix . $this->_id;
		}
		return '';
	}

	/**
	 * Get HTML formatted title. Allows apps or nodes to format the title, such as adding different colours, etc
	 *
	 * @return	string
	 */
	public function get__formattedTitle()
	{
		return $this->_title;
	}

	/**
	 * [Node] Get Node Description
	 *
	 * @return	string|null
	 */
	protected function get__description()
	{
		return NULL;
	}

	/**
	 * [Node] Get content table description
	 *
	 * @return	string
	 */
	protected function get_description()
	{
		if ( static::$titleLangPrefix and static::$descriptionLangSuffix )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $this->id . static::$descriptionLangSuffix );
		}
		return NULL;
	}

	/**
	 * [Node] Get content table meta description
	 *
	 * @return	string
	 */
	public function metaDescription()
	{
		if ( static::$titleLangPrefix and static::$descriptionLangSuffix )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $this->id . static::$descriptionLangSuffix, FALSE, array( 'striptags' => TRUE, 'escape' => TRUE, 'removeNewlines' => TRUE ) );
		}
		return NULL;
	}

	/**
	 * [Node] Get content table meta title
	 *
	 * @return	string
	 */
	public function metaTitle()
	{
		return $this->_title;
	}

	/**
	 * [Node] Return the custom badge for each row
	 *
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	protected function get__badge()
	{
		if ( $this->deleteOrMoveQueued() === TRUE )
		{
			return array(
				0	=> 'ipsBadge ipsBadge_intermediary',
				1	=> 'node_move_delete_queued',
			);
		}

		return NULL;
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe', the 'fa fa-' is added automatically so you do not need this here)
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return NULL;
	}

	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		if ( $col = static::$databaseColumnEnabledDisabled )
		{
			return (bool) $this->$col;
		}
		return NULL;
	}

	/**
	 * [Node] Set whether or not this node is enabled
	 *
	 * @param	bool|int	$enabled	Whether to set it enabled or disabled
	 * @return	void
	 */
	protected function set__enabled( $enabled )
	{
		if ( $col = static::$databaseColumnEnabledDisabled )
		{
			$this->$col = $enabled;
		}
	}

	/**
	 * [Node] Get whether or not this node is locked to current enabled/disabled status
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__locked()
	{
		return NULL;
	}

	/**
	 * [Node] Get a lang string to display when hovering over the locked toggle, or null to display nothing
	 *
	 * @return string|null
	 */
	protected function get__lockedLang()
	{
		return null;
	}

	/**
	 * [Node] Get position
	 *
	 * @return	int
	 */
	protected function get__position()
	{
		$orderColumn = static::$databaseColumnOrder;
		return $this->$orderColumn;
	}

	/**
	 * [Node] Get number of content items
	 *
	 * @return	int
	 */
	protected function get__items()
	{
		return NULL;
	}

	/**
	 * Set number of items
	 *
	 * @param	int	$val	Items
	 * @return	void
	 */
	protected function set__items( $val )
	{

	}

	/**
	 * [Node] Get number of content comments
	 *
	 * @return	int
	 */
	protected function get__comments()
	{
		return NULL;
	}

	/**
	 * Set number of content comments
	 *
	 * @param	int	$val	Comments
	 * @return	void
	 */
	protected function set__comments( $val )
	{

	}

	/**
	 * [Node] Get number of content reviews
	 *
	 * @return	int
	 */
	protected function get__reviews()
	{
		return NULL;
	}

	/**
	 * Set number of content reviews
	 *
	 * @param	int	$val	Reviews
	 * @return	void
	 */
	protected function set__reviews( $val )
	{

	}

	/**
	 * [Node] Get number of future publishing items
	 *
	 * @return	int
	 */
	protected function get__futureItems()
	{
		return NULL;
	}

	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @return	int
	 */
	protected function get__unnapprovedItems()
	{
		return NULL;
	}

	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @return	int
	 */
	protected function get__unapprovedComments()
	{
		return NULL;
	}

	/**
	 * [Node] Get number of unapproved content reviews
	 *
	 * @return	int
	 */
	protected function get__unapprovedReviews()
	{
		return NULL;
	}

	/**
	 * Get sort key
	 *
	 * @return	string
	 */
	public function get__sortBy()
	{
		return NULL;
	}

	/**
	 * Get sort order
	 *
	 * @return	string
	 */
	public function get__sortOrder()
	{
		foreach ( array( 'title', 'author_name', 'last_comment_name' ) as $k )
		{
			$contentItemClass = static::$contentItemClass;
			if ( isset( $contentItemClass::$databaseColumnMap[ $k ] ) and $this->_sortBy === $contentItemClass::$databaseColumnMap[ $k ] )
			{
				return 'ASC';
			}
		}

		return 'DESC';
	}

	/**
	 * Get default filter
	 *
	 * @return	string
	 */
	public function get__filter()
	{
		return NULL;
	}

	/**
	 * [Node] Return the owner if this node can be owned
	 *
	 * @throws	\RuntimeException
	 * @return	\IPS\Member|null
	 */
	public function owner()
	{
		if( static::$ownerTypes === NULL OR ( static::$ownerTypes['member'] === NULL and static::$ownerTypes['group'] === NULL ) )
		{
			throw new \RuntimeException;
		}

		if ( static::$ownerTypes['member'] )
		{
			$column	= static::$ownerTypes['member'];
			if( $this->$column )
			{
				return \IPS\Member::load( $this->$column );
			}
		}

		return NULL;
	}

	/**
	 * Set last comment
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The latest comment or NULL to work it out
	 * @return	int
	 */
	public function setLastComment( \IPS\Content\Comment $comment=NULL )
	{
		// Don't do anything by default, but nodes could extract data
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
		return NULL;
	}

	/**
	 * Set last review
	 *
	 * @param	\IPS\Content\Review|NULL	$review	The latest review or NULL to work it out
	 * @return	int
	 */
	public function setLastReview( \IPS\Content\Review $review=NULL )
	{
		// Don't do anything by default, but nodes could extract data
	}

	/* !Parent/Children/Siblings */

	/**
	 * [Node] Get Parent
	 *
	 * @return	static|null
	 */
	public function parent()
	{
		if ( isset( static::$parentNodeClass ) )
		{
			$parentNodeClass = static::$parentNodeClass;
			$parentColumn = static::$parentNodeColumnId;
			if( $this->$parentColumn )
			{
				return $parentNodeClass::load( $this->$parentColumn );
			}
		}

		if( static::$databaseColumnParent !== NULL )
		{
			$parentColumn = static::$databaseColumnParent;
			if( $this->$parentColumn !== static::$databaseColumnParentRootValue )
			{
				return static::load( $this->$parentColumn );
			}
		}

		return NULL;
	}

	/**
	 * [Node] Get parent list
	 *
	 * @return	\SplStack
	 */
	public function parents()
	{
		$stack = new \SplStack;

		$working = $this;
		while ( $working = $working->parent() )
		{
			if( ! $working instanceof \IPS\Node\Model )
			{
				return $stack;
			}

			$stack->push( $working );
		}

		return $stack;
	}

	/**
	 * Is this node a child (or sub child, or sub-sub-child etc) of another node?
	 *
	 * @param	\IPS\Node\Model	$node	The node to check
	 * @return	bool
	 */
	public function isChildOf( \IPS\Node\Model $node )
	{
		foreach ( $this->parents() as $parent )
		{
			if ( $parent == $node )
			{
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * [Node] Does this node have children?
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	bool				$subnodes			Include subnodes? NULL to *only* check subnodes
	 * @param	mixed				$_where				Additional WHERE clause
	 * @return	bool
	 */
	public function hasChildren( $permissionCheck='view', $member=NULL, $subnodes=TRUE, $_where=array() )
	{
		return ( $this->childrenCount( $permissionCheck, $member, $subnodes, $_where ) > 0 );
	}

	/**
	 * @brief	Cache for get__children
	 * @see		\IPS\Node\Model::get__children
	 */
	protected $_childrenResults = array();

	/**
	 * [Node] Get Number of Children
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	bool				$subnodes			Include subnodes? NULL to *only* check subnodes
	 * @param	mixed				$_where				Additional WHERE clause
	 * @return	int
	 */
	public function childrenCount( $permissionCheck='view', $member=NULL, $subnodes=TRUE, $_where=array() )
	{
		/* We almost universally need the children after getting the count, so let's just cut to the chase and run one query instead of 2 */
		return \count( $this->children( $permissionCheck, $member, $subnodes, NULL, $_where ) );
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
		$children = array();
		
		if ( $permissionCheck !== NULL )
		{
			$member = $member ?: \IPS\Member::loggedIn();
		}

		/* Load member */
		if ( $permissionCheck !== NULL AND \in_array( 'IPS\Node\Permissions', class_implements( $this ) ) )
		{
			$cacheKey	= md5( $permissionCheck . $member->member_id . $subnodes . json_encode( $skip ) . json_encode( $_where ) );
		}
		else
		{
			$cacheKey	= md5( $subnodes . json_encode( $skip ) . json_encode( $_where ) );
		}
		if( isset( $this->_childrenResults[ $cacheKey ] ) )
		{
			return $this->_childrenResults[ $cacheKey ];
		}

		/* What's our ID? */
		$idColumn = static::$databaseColumnId;

		/* True children */
		if( $subnodes !== NULL and static::$databaseColumnParent !== NULL )
		{
			/* Specify our parent ID */
			$where = $_where;
			$where[] = array( static::$databasePrefix . static::$databaseColumnParent . '=?', $this->$idColumn );

			if ( \is_array( $skip ) and \count( $skip ) )
			{
				$where[] = array( '( ! ' . \IPS\Db::i()->in( static::$databasePrefix . static::$databaseColumnId, $skip ) . ' )' );
			}

			/* Permission check? */
			if ( $this instanceof \IPS\Node\Permissions and $permissionCheck !== NULL )
			{

				$where[] = array( '(' . \IPS\Db::i()->findInSet( 'perm_' . static::$permissionMap[ $permissionCheck ], $member->groups ) . ' OR ' . 'perm_' . static::$permissionMap[ $permissionCheck ] . '=? )', '*' );
				if ( static::$databaseColumnEnabledDisabled )
				{
					$where[] = array( static::$databasePrefix . static::$databaseColumnEnabledDisabled . '=1' );
				}

				$select = \IPS\Db::i()->select( '*', static::$databaseTable, $where, static::$databaseColumnOrder ? ( static::$databasePrefix . static::$databaseColumnOrder ) : NULL )->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnId, static::$permApp, static::$permType ) );
			}
			/* Nope - normal */
			else
			{
				$select = \IPS\Db::i()->select( '*', static::$databaseTable, $where, static::$databaseColumnOrder ? ( static::$databasePrefix . static::$databaseColumnOrder ) : NULL );
			}

			/* Get em! */
			foreach( $select as $row )
			{
				$row = static::constructFromData( $row );

				if ( $row instanceof \IPS\Node\Permissions and $permissionCheck !== NULL )
				{
					if( $row->can( $permissionCheck ) )
					{
						$children[]	= $row;
					}
				}
				else
				{
					$children[] = $row;
				}
			}
		}

		/* Subnodes */
		if( ( $subnodes === TRUE or $subnodes === NULL ) and static::$subnodeClass !== NULL )
		{
			$subnodeClass = static::$subnodeClass;

			/* Specify our parent node ID */
			$where = $_where;
			$where[] = array( $subnodeClass::$databasePrefix . $subnodeClass::$parentNodeColumnId . '=?', $this->$idColumn );

			/* If our subnodes can have children themselves, we only want the root ones */
			if( $subnodeClass::$databaseColumnParent !== NULL )
			{
				$where[] = array( $subnodeClass::$databasePrefix . $subnodeClass::$databaseColumnParent . '=?', $subnodeClass::$databaseColumnParentRootValue );
			}

			/* Permission check? */
			if ( \in_array( 'IPS\Node\Permissions', class_implements( $subnodeClass ) ) and $permissionCheck !== NULL )
			{
				$where[] = array( '(' . \IPS\Db::i()->findInSet( 'perm_' . $subnodeClass::$permissionMap[ $permissionCheck ], $member->groups ) . ' OR ' . 'perm_' . $subnodeClass::$permissionMap[ $permissionCheck ] . '=? )', '*' );
				if ( $subnodeClass::$databaseColumnEnabledDisabled )
				{
					$where[] = array( $subnodeClass::$databasePrefix . $subnodeClass::$databaseColumnEnabledDisabled . '=1' );
				}

				$select =\IPS\Db::i()->select( '*', $subnodeClass::$databaseTable, $where, $subnodeClass::$databaseColumnOrder ? ( $subnodeClass::$databasePrefix . $subnodeClass::$databaseColumnOrder ) : NULL )->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . $subnodeClass::$databaseTable . "." . $subnodeClass::$databasePrefix . $subnodeClass::$databaseColumnId, $subnodeClass::$permApp, $subnodeClass::$permType ) );
			}
			/* Nope - normal */
			else
			{
				$select = \IPS\Db::i()->select( '*', $subnodeClass::$databaseTable, $where, $subnodeClass::$databaseColumnOrder ? ( $subnodeClass::$databasePrefix . $subnodeClass::$databaseColumnOrder ) : NULL );
			}

			/* Get em! */
			foreach( $select as $row )
			{
				$row = $subnodeClass::constructFromData( $row );

				if ( $row instanceof \IPS\Node\Permissions and $permissionCheck !== NULL )
				{
					if( $row->can( $permissionCheck ) )
					{
						$children[]	= $row;
					}
				}
				else
				{
					$children[] = $row;
				}
			}
		}

		$this->_childrenResults[ $cacheKey ]	= $children;

		/* Return */
		return $children;
	}

	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 *
	 * @code
	array(
	array(
	'icon'	=>	'plus-circle', // Name of FontAwesome icon to use
	'title'	=> 'foo',		// Language key to use for button's title parameter
	'link'	=> \IPS\Http\Url::internal( 'app=foo...' )	// URI to link to
	'class'	=> 'modalLink'	// CSS Class to use on link (Optional)
	),
	...							// Additional buttons
	);
	 * @endcode
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = array();

		if ( $subnode )
		{
			$url = $url->setQueryString( array( 'subnode' => 1 ) );
		}

		if( $this->canAdd() )
		{
			$buttons['add'] = array(
				'icon'	=> 'plus-circle',
				'title'	=> static::$nodeTitle . '_add_child',
				'link'	=> $url->setQueryString( array( 'subnode' => (int) isset( static::$subnodeClass ), 'do' => 'form', 'parent' => $this->_id ) ),
				'data'	=> ( static::$modalForms ? array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add') ) : array() )
			);
		}

		if( $this->canEdit() )
		{
			$buttons['edit'] = array(
				'icon'	=> 'pencil',
				'title'	=> 'edit',
				'link'	=> $url->setQueryString( array( 'do' => 'form', 'id' => $this->_id ) ),
				'data'	=> ( static::$modalForms ? array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') ) : array() ),
				'hotkey'=> 'e return'
			);
		}

		if( $this->canManagePermissions() )
		{
			$buttons['permissions'] = array(
				'icon'	=> 'lock',
				'title'	=> 'permissions',
				'link'	=> $url->setQueryString( array( 'do' => 'permissions', 'id' => $this->_id ) ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('permissions') )
			);
		}

		if( $this->canCopy() )
		{
			$copyUrl = $url->setQueryString( array( 'do' => 'copy', 'id' => $this->_id ) );
			$willDisplayModal = ( $this->hasChildren( NULL, NULL, FALSE ) );
			$buttons['copy'] = array(
				'icon'	=> 'files-o',
				'title'	=> 'copy',
				'link'	=> $willDisplayModal ? $copyUrl : $copyUrl->csrf(),
				'data' => $willDisplayModal ? array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('copy') ) : array()
			);
		}

		if( $this->canMassManageContent() )
		{
			$buttons['content'] = array(
				'icon'	=> 'arrow-right',
				'title'	=> 'mass_manage_content',
				'link'	=> $url->setQueryString( array( 'do' => 'massManageContent', 'id' => $this->_id, '_new' => 1 ) ),
				'hotkey'=> 'm'
			);
		}
		
		if( $this->canDelete() )
		{
			$buttons['delete'] = array(
				'icon'	=> 'times-circle',
				'title'	=> 'delete',
				'link'	=> $url->setQueryString( array( 'do' => 'delete', 'id' => $this->_id ) )->csrf(),
				'data' 	=> ( $this->hasChildren( NULL, NULL, TRUE ) or $this->showDeleteOrMoveForm() ) ? array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('delete') ) : array( 'delete' => '' ),
				'hotkey'=> 'd'
			);
		}

		return $buttons;
	}

	/* !ACP Restrictions */

	/**
	 * ACP Restrictions Check
	 *
	 * @param	string	$key	Restriction key to check
	 * @return	bool
	 */
	protected static function restrictionCheck( $key )
	{
		if( !\IPS\Member::loggedIn()->isAdmin() )
		{
			return FALSE;
		}

		if ( static::$restrictions !== NULL )
		{
			$_key = NULL;
			if ( isset( static::$restrictions['prefix'] ) )
			{
				$_key = static::$restrictions['prefix'] . $key;
			}
			if ( isset( static::$restrictions['map'][ $key ] ) )
			{
				$_key = static::$restrictions['map'][ $key ];
			}
			elseif ( isset( static::$restrictions['all'] ) )
			{
				$_key = static::$restrictions['all'];
			}

			if ( $_key === NULL )
			{
				return FALSE;
			}
			
			return \IPS\Member::loggedIn()->hasAcpRestriction( static::$restrictions['app'], static::$restrictions['module'], $_key );
		}

		return TRUE;
	}

	/**
	 * [Node] Does the currently logged in user have permission to add aa root node?
	 *
	 * @return	bool
	 */
	public static function canAddRoot()
	{
		return static::restrictionCheck( 'add' );
	}

	/**
	 * [Node] Does the currently logged in user have permission to add a child node to this node?
	 *
	 * @return	bool
	 */
	public function canAdd()
	{
		/* If there is no parent/child relationship and no subnode class, you can't add a child */
		if( static::$databaseColumnParent === NULL AND static::$subnodeClass === NULL )
		{
			return FALSE;
		}

		if ( $this->deleteOrMoveQueued() === TRUE )
		{
			return FALSE;
		}

		return static::restrictionCheck( 'add' );
	}

	/**
	 * [Node] Does the currently logged in user have permission to edit this node?
	 *
	 * @return	bool
	 */
	public function canEdit()
	{
		/* If the node is being deleted or moved, it cannot be edited */
		if ( $this->deleteOrMoveQueued() === TRUE )
		{
			return FALSE;
		}

		/* If we have permission to edit then we can edit (of course) */
		if( static::restrictionCheck( 'edit' ) )
		{
			return TRUE;
		}

		/* Do we "own" this node? */
		if( static::$ownerTypes !== NULL AND isset( static::$ownerTypes['member'] ) and static::$ownerTypes['member'] !== NULL )
		{
			$column	= static::$ownerTypes['member'];

			if( $this->$column and $this->$column == \IPS\Member::loggedIn()->member_id )
			{
				return TRUE;
			}
		}

		/* Does a group we belong to "own" this node? */
		if( static::$ownerTypes !== NULL AND isset( static::$ownerTypes['group'] ) and static::$ownerTypes['group'] !== NULL )
		{
			$column	= static::$ownerTypes['group']['ids'];

			$value = $this->$column;
			if( \count( array_intersect( explode( ",", $value ), \IPS\Member::loggedIn()->groups ) ) )
			{
				return TRUE;
			}
		}

		/* Does the node belong to a club that we are a leader of? */
		if ( \IPS\IPS::classUsesTrait( \get_called_class(), 'IPS\Content\ClubContainer' ) AND $this->club() )
		{
			if ( $this->club()->isLeader() )
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * [Node] Does the currently logged in user have permission to copy this node?
	 *
	 * @return	bool
	 */
	public function canCopy()
	{
		if ( $this->deleteOrMoveQueued() === TRUE )
		{
			return FALSE;
		}

		return ( !$this->parent() and static::canAddRoot() ) or ( $this->parent() and $this->parent()->canAdd() );
	}

	/**
	 * [Node] Does the currently logged in user have permission to edit permissions for this node?
	 *
	 * @return	bool
	 */
	public function canManagePermissions()
	{
		if ( $this->deleteOrMoveQueued() === TRUE )
		{
			return FALSE;
		}

		return ( static::$permApp !== NULL and static::$permType !== NULL and static::restrictionCheck( 'permissions' ) );
	}
	
	/**
	 * [Node] Does the currently logged in user have permission to mass move/delete content in this node?
	 *
	 * @return	bool
	 */
	public function canMassManageContent()
	{
		if ( !isset( static::$contentItemClass ) )
		{
			return FALSE;
		}
		
		if ( $this->deleteOrMoveQueued() === TRUE )
		{
			return FALSE;
		}

		if( static::restrictionCheck( 'massManageContent' ) )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		if ( $this->deleteOrMoveQueued() === TRUE )
		{
			return FALSE;
		}

		if( static::restrictionCheck( 'delete' ) )
		{
			return TRUE;
		}

		if( static::$ownerTypes !== NULL AND static::$ownerTypes['member'] !== NULL )
		{
			$column	= static::$ownerTypes['member'];

			if( $this->$column == \IPS\Member::loggedIn()->member_id )
			{
				return TRUE;
			}
		}

		if( static::$ownerTypes !== NULL AND static::$ownerTypes['group'] !== NULL )
		{
			$column	= static::$ownerTypes['group']['ids'];

			$value = $this->$column;
			if( \count( array_intersect( explode( ",", $value ), \IPS\Member::loggedIn()->groups ) ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}
	
	/**
	 * @brief Constant allow anonymous items
	 */
	const ANON_ITEMS = 1;
	
	/**
	 * @brief Constant allow anonymous comments
	 */
	const ANON_COMMENTS = 2;

	/**
	 * [Node] Does the currently logged in user have permission to post anonymously in this node?
	 *
	 * @param	 int 				$where		What are we checking, 0 => means don't check it, 2 means comments only
	 * @param	\IPS\Member|NULL 	$member		The member posting anonymously or NULL for logged in member
	 * @return    bool
	 */
	public function canPostAnonymously( $where = 0, \IPS\Member $member = NULL ): bool
	{
		if ( ! isset( static::$contentItemClass ) or ! \in_array( 'IPS\Content\Anonymous', class_implements( static::$contentItemClass ) ) )
		{
			return FALSE;
		}

		$member = $member ?: \IPS\Member::loggedIn();

		if( ! $member->member_id or ! $member->group['gbw_can_post_anonymously'] )
		{
			return FALSE;
		}
		
		return $this->isAnonymousContentSupported( $where );
	}

	/**
	 * Does the current node support anonymous content?
	 *
	 * @param int $where		What are we checking, 0 => means don't check it, 2 means comments only
	 * @return bool
	 */
	public function isAnonymousContentSupported( int $where = 0  ) : bool
	{
		/* 1 means both items and comments */
		if ( $this->allow_anonymous === 1 )
		{
			return TRUE;
		}

		/* We are not checking the where, so either is fine */
		if ( $where === 0 and $this->allow_anonymous > 0 )
		{
			return TRUE;
		}

		/* 2 means comments only */
		if ( $where === static::ANON_COMMENTS and $this->allow_anonymous === 2 )
		{
			return TRUE;
		}
		return FALSE;
	}

	/* !Front-end permissions */

	/**
	 * @brief	The map of permission columns
	 */
	public static $permissionMap = array( 'view' => 'view' );

	/**
	 * @brief	Permissions
	 */
	protected $_permissions = NULL;

	/**
	 * @brief	Permissions when we first loaded them from the DB
	 */
	protected $_originalPermissions = NULL;

	/**
	 * Construct Load Query
	 *
	 * @param	int|string	$id					ID
	 * @param	string		$idField			The database column that the $id parameter pertains to
	 * @param	mixed		$extraWhereClause	Additional where clause(s)
	 * @return	\IPS\Db\Select
	 */
	protected static function constructLoadQuery( $id, $idField, $extraWhereClause )
	{
		if ( \in_array( 'IPS\Node\Permissions', class_implements( \get_called_class() ) ) )
		{
			$where = array( array( static::$databaseTable . '.' . $idField . '=?', $id ) );
			if( $extraWhereClause !== NULL )
			{
				if ( !\is_array( $extraWhereClause ) or !\is_array( $extraWhereClause[0] ) )
				{
					$extraWhereClause = array( $extraWhereClause );
				}
				$where = array_merge( $where, $extraWhereClause );
			}

			return \IPS\Db::i()->select(
				static::$databaseTable . '.*, core_permission_index.perm_id, core_permission_index.perm_view, core_permission_index.perm_2, core_permission_index.perm_3, core_permission_index.perm_4, core_permission_index.perm_5, core_permission_index.perm_6, core_permission_index.perm_7',
				static::$databaseTable,
				$where
			)->join(
				'core_permission_index',
				array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnId, static::$permApp, static::$permType )
			);
		}
		else
		{
			return parent::constructLoadQuery( $id, $idField, $extraWhereClause );
		}
	}

	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		if ( \in_array( 'IPS\Node\Permissions', class_implements( \get_called_class() ) ) )
		{
			/* Does that exist in the multiton store? */
			$obj = NULL;
			if ( isset( static::$databaseColumnId ) )
			{
				$idField = static::$databasePrefix . static::$databaseColumnId;
				$id = $data[ $idField ];

				if( isset( static::$multitons[ $id ] ) )
				{
					if ( !$updateMultitonStoreIfExists )
					{
						return static::$multitons[ $id ];
					}
					$obj = static::$multitons[ $id ];
				}
			}

			/* Initiate an object */
			if ( !$obj )
			{
				$classname = \get_called_class();
				$obj = new $classname;
				$obj->_new  = FALSE;
				$obj->_data = array();
			}

			/* Import data */
			$databasePrefixLength = \strlen( static::$databasePrefix );
			foreach ( $data as $k => $v )
			{
				if ( \in_array( $k, array( 'perm_id', 'perm_view', 'perm_2', 'perm_3', 'perm_4', 'perm_5', 'perm_6', 'perm_7' ) ) )
				{
					$obj->_permissions[ $k ] = $v;
				}
				else
				{
					if( static::$databasePrefix AND mb_strpos( $k, static::$databasePrefix ) === 0 )
					{
						$k = \substr( $k, $databasePrefixLength );
					}

					$obj->_data[ $k ] = $v;
				}
			}
			$obj->changed = array();
			$obj->_originalPermissions = $obj->_permissions;

			/* Init */
			if ( method_exists( $obj, 'init' ) )
			{
				$obj->init();
			}

			/* If it doesn't exist in the multiton store, set it */
			if( isset( static::$databaseColumnId ) and !isset( static::$multitons[ $id ] ) )
			{
				static::$multitons[ $id ] = $obj;
			}

			/* Return */
			return $obj;
		}
		else
		{
			return parent::constructFromData( $data, $updateMultitonStoreIfExists );
		}
	}

	/**
	 * Load and check permissions
	 *
	 * @param	mixed	$id		ID
	 * @param	string	$perm	Permission Key
	 * @return	static
	 * @throws	\OutOfRangeException
	 */
	public static function loadAndCheckPerms( $id, $perm='view' )
	{
		$obj = static::load( $id );

		if ( !$obj->can( $perm ) )
		{
			throw new \OutOfRangeException;
		}

		return $obj;
	}

	/**
	 * The permission key or function used when building a node selector
	 * in search or stream functions.
	 *
	 * @return string|callable function
	 */
	public static function searchableNodesPermission()
	{
		return 'view';
	}

	/**
	 * Return either NULL for no restrictions, or a list of container IDs we cannot search in because of app specific permissions and configuration
	 * You do not need to check for 'view' permissions against the logged in member here. The Query search class does this for you.
	 * This method is intended for more complex set up items, like needing to have X posts to see a forum, etc.
	 * This is used for search and the activity stream.
	 * We return a list of IDs and not node objects for memory efficiency.
	 *
	 * return 	null|array
	 */
	public static function unsearchableNodeIds()
	{
		return NULL;
	}

	/**
	 * Set the permission index permissions
	 *
	 * @param	array	$insert	Permission data to insert
	 * @return  void
	 */
	public function setPermissions( $insert )
	{
		/* Delete current rows */
		\IPS\Db::i()->delete( 'core_permission_index', array( 'app=? AND perm_type=? AND perm_type_id=?', static::$permApp, static::$permType, $this->_id ) );

		/* Insert */
		$permId = \IPS\Db::i()->insert( 'core_permission_index', $insert );
		
		/* Update tags permission cache */
		if ( isset( static::$permissionMap['read'] ) )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_text' => $insert[ 'perm_' . static::$permissionMap['read'] ] ), array( 'tag_perm_aap_lookup=?', md5( static::$permApp . ';' . static::$permType . ';' . $this->_id ) ) );
		}

		/* Make sure this object resets the permissions internally */
		$this->_permissions = array_merge( array( 'perm_id' => $permId ), $insert );
		
		/* Update search index */
		$this->updateSearchIndexPermissions();

		/* Update delete logs */
		$this->updateDeleteLogPermissions();
	}

	/**
	 * Update delete log permissions
	 * 
	 * @return	void
	 */
	protected function updateDeleteLogPermissions()
	{
		if ( isset( static::$contentItemClass ) )
		{
			$contentItemClass = static::$contentItemClass;
			if ( \in_array( 'IPS\Content\Hideable', class_implements( $contentItemClass ) ) )
			{
				\IPS\core\DeletionLog::updateNodePermissions( \get_called_class(), $this->_id, $this->deleteLogPermissions() );
			}
		}
	}

	/**
	 * Deletion log Permissions
	 * Usually, this is the same as searchIndexPermissions. However, some applications may restrict searching but
	 * still want to allow delayed deletion log viewing and searching
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function deleteLogPermissions()
	{
		try
		{
			return $this->searchIndexPermissions();
		}
		/* The container may not exist */
		catch( \OutOfRangeException $e )
		{
			return '';
		}
	}

	/**
	 * Update search index permissions
	 *
	 * @return  void
	 */
	protected function updateSearchIndexPermissions()
	{
		if ( isset( static::$contentItemClass ) )
		{
			$contentItemClass = static::$contentItemClass;
			if ( \in_array( 'IPS\Content\Searchable', class_implements( $contentItemClass ) ) )
			{
				\IPS\Content\Search\Index::i()->massUpdate( $contentItemClass, $this->_id, NULL, $this->searchIndexPermissions() );
			}
			foreach ( array( 'commentClass', 'reviewClass' ) as $class )
			{
				if ( isset( $contentItemClass::$$class ) )
				{
					$className = $contentItemClass::$$class;
					if ( \in_array( 'IPS\Content\Searchable', class_implements( $className ) ) )
					{
						\IPS\Content\Search\Index::i()->massUpdate( $className, $this->_id, NULL, $this->searchIndexPermissions() );
					}
				}
			}
		}
	}

	/**
	 * @brief	Cached canOnAny permission check
	 */
	protected static $_canOnAny	= array();

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
		/* If this is not permission-dependant, return TRUE */
		if ( !\in_array( 'IPS\Node\Permissions', class_implements( \get_called_class() ) ) )
		{
			return TRUE;
		}

		/* Check it exists */
		if ( !isset( static::$permissionMap[ $permission ] ) )
		{
			throw new \OutOfBoundsException;
		}

		/* Load member */
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}

		/* Restricted */
		if ( $member->restrict_post )
		{
			return FALSE;
		}
		
		/* Posts per day */
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

		$_key = md5( \get_called_class() . json_encode( $where ) );

		/* Have we already cached the check? */
		if( isset( static::$_canOnAny[ $_key ][ $permission ] ) )
		{
			return static::$_canOnAny[ $_key ][ $permission ];
		}
		
		/* Construct permission check for query */
		$groupsToCheck = $member->groups;

		/* Work out which to use taking Post Before Registering into consideration and return */
		$considerPostBeforeRegistering = ( $considerPostBeforeRegistering and \IPS\Settings::i()->post_before_registering and \IPS\Login::registrationType() != 'disabled' );
		if ( !$member->member_id and $considerPostBeforeRegistering )
		{
			$groupsToCheck[] = \IPS\Settings::i()->member_group;
		}
		
		if ( \IPS\Settings::i()->clubs AND \IPS\Settings::i()->club_nodes_in_apps AND \IPS\IPS::classUsesTrait( \get_called_class(), 'IPS\Content\ClubContainer' ) )
		{
			$clubWhere = array();
			foreach( $member->clubs() AS $club )
			{
				$clubWhere[] = "core_permission_index.perm_" . static::$permissionMap[ $permission ] . "='cm,c{$club}'";
			}
			if ( \count( $clubWhere ) )
			{
				$where[] = array( \IPS\Db::i()->findInSet( 'core_permission_index.perm_' . static::$permissionMap[ $permission ], $groupsToCheck ) . ' OR core_permission_index.perm_' . static::$permissionMap[ $permission ] . "='*' OR core_permission_index.perm_" . static::$permissionMap[ $permission ] . "='ca' OR " . implode( ' OR ', $clubWhere ) );
			}
			else
			{
				$where[] = array( \IPS\Db::i()->findInSet( 'core_permission_index.perm_' . static::$permissionMap[ $permission ], $groupsToCheck ) . ' OR core_permission_index.perm_' . static::$permissionMap[ $permission ] . "='*' OR core_permission_index.perm_" . static::$permissionMap[ $permission ] . "='ca'" );
			}
		}
		else
		{
			$where[] = array( \IPS\Db::i()->findInSet( 'core_permission_index.perm_' . static::$permissionMap[ $permission ], $groupsToCheck ) . ' OR core_permission_index.perm_' . static::$permissionMap[ $permission ] . "='*'" );
		}
		
		/* Run query and return */
		if ( static::$databaseColumnEnabledDisabled )
		{
			$where[] = array( static::$databasePrefix . static::$databaseColumnEnabledDisabled . '=1' );
		}
		
		static::$_canOnAny[ $_key ][ $permission ]	= (bool) \IPS\Db::i()->select( 'COUNT(*)', static::$databaseTable, $where )
			->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnId, static::$permApp, static::$permType ) )
			->first();

		return static::$_canOnAny[ $_key ][ $permission ];
	}

	/**
	 * Disabled permissions
	 * Allow node classes to define permissions that are unselectable in the permission matrix
	 *
	 * @return array	array( {group_id} => array( 'read', 'view', 'perm_7' );
	 * @throws UnderflowException (if guest group ID is invalid)
	 */
	public function disabledPermissions()
	{
		return array();
	}

	/**
	 * Permission Types
	 *
	 * @return	array
	 */
	public function permissionTypes()
	{
		return static::$permissionMap;
	}

	/**
	 * Get permissions
	 *
	 * @return	array
	 */
	public function permissions()
	{
		if ( $this->_permissions === NULL )
		{
			try
			{
				$this->_permissions = \IPS\Db::i()->select( array( 'perm_id', 'perm_view', 'perm_2', 'perm_3', 'perm_4', 'perm_5', 'perm_6', 'perm_7' ), 'core_permission_index', array( "app=? AND perm_type=? AND perm_type_id=?", static::$permApp, static::$permType, $this->_id ) )->first();
			}
			catch ( \UnderflowException $e )
			{
				$permId = \IPS\Db::i()->insert( 'core_permission_index', array(
					'app'			=> static::$permApp,
					'perm_type'		=> static::$permType,
					'perm_type_id'	=> $this->_id,
					'perm_view'		=> ''
				) );
				
				$this->_permissions = array( 'perm_id' => $permId, 'perm_view' => '', 'perm_2' => NULL, 'perm_3' => NULL, 'perm_4' => NULL, 'perm_5' => NULL, 'perm_6' => NULL, 'perm_7' => NULL );
			}
		}
		return $this->_permissions;
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
		if( $this instanceof \IPS\Node\Permissions )
		{
			/* Compare both read and view */
			$result	= static::_getPermissions( $this );

			/* And then loop up the parents too... */
			foreach ( $this->parents() as $parent )
			{
				$parentResult = static::_getPermissions( $parent );

				if( $result == '*' )
				{
					$result	= $parentResult;
				}
				else if( $parentResult != '*' )
				{
					$result	= implode( ',', array_intersect( explode( ',', $result ), explode( ',', $parentResult ) ) );
				}
			}

			return $result;
		}
		return '*';
	}

	/**
	 * Retrieve the computed permissions
	 *
	 * @param	\IPS\Node\Model	$node	Node
	 * @return	string
	 */
	protected static function _getPermissions( $node )
	{
		$permissions = $node->permissions();
		$permissionTypes = $node->permissionTypes();

		/* Compare both read and view */

		if( !isset( $permissionTypes['read'] ) )
		{
			return $permissions[ 'perm_' . $permissionTypes['view'] ];
		}

		if( $permissions[ 'perm_' . $permissionTypes['view'] ] == '*' )
		{
			return $permissions[ 'perm_' . $permissionTypes['read'] ];
		}
		else if( $permissions[ 'perm_' . $permissionTypes['read'] ] == '*' )
		{
			return $permissions[ 'perm_' . $permissionTypes['view'] ];
		}
		else
		{
			return implode( ',', array_intersect( explode( ',', $permissions[ 'perm_' . $permissionTypes['view'] ] ), explode( ',', $permissions[ 'perm_' . $permissionTypes['read'] ] ) ) );
		}
	}

	/**
	 * Populate the Permission Matrix for the Permissions extension
	 *
	 * @param	array					Our current rows array we need to populate.
	 * @param	\IPS\Node\Model			The node to merge in.
	 * @param	\IPS\Member\Group|int	The group currently being adjusted.
	 * @param	array					Current permissions.
	 * @param	int						Our current depth level
	 * @return	array
	 * @throws
	 *	@li	BadMethodCallException
	 *  @li UnderflowException (if guest group ID is invalid)
	 */
	public static function populatePermissionMatrix( &$rows, $node, $group, $current, $level=0 )
	{
		if ( !\in_array( 'IPS\Node\Permissions', class_implements( $node ) ) )
		{
			throw new \BadMethodCallException;
		}

		$group = ( $group instanceof \IPS\Member\Group ) ? $group->g_id : $group;

		$rows[ $node->_id ] = array( '_level' => $level, 'label' => $node->_title );

		$disabledPermissions = $node->disabledPermissions();
		foreach( $node->permissionTypes() AS $k => $v )
		{
			$value = ( ( isset( $current[ $node->_id ] ) ) AND ( $current[ $node->_id ]['perm_' . $v ] === '*' OR \in_array( $group, explode( ',', $current[ $node->_id ]['perm_' . $v ] ) ) ) );

			$disabled = FALSE;
			if ( array_key_exists( $group, $disabledPermissions ) and \is_array( $disabledPermissions[ $group ] ) )
			{
				$disabled = \in_array( $v, array_values( $disabledPermissions[ $group ] ) );
			}

			if ( $disabled === FALSE )
			{
				$disabled = ( $group == \IPS\Settings::i()->guest_group AND \in_array( $k, array('review', 'rate' ) ) ) ? TRUE : FALSE;
			}

			if ( $disabled )
			{
				$value = NULL;
			}

			$rows[ $node->_id ] = array_merge( $rows[ $node->_id ], array( static::$permissionLangPrefix . 'perm__' . $k => $value ) );
		}

		if ( $node->hasChildren( NULL ) === TRUE )
		{
			$level++;
			foreach( $node->children( NULL ) AS $child )
			{
				static::populatePermissionMatrix( $rows, $child, $group, $current, $level );
			}
			$level--;
		}
	}
	
	/**
	 * Can View
	 *
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member			The member or group to check (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\OutOfRangeException
	 * @note	This is just a quick wrapper to brings things consistent between Content Items and Nodes for things like Reactions, which may support both
	 */
	public function canView( $member=NULL )
	{
		return $this->can( 'view', $member );
	}

	/**
	 * Return if this node has custom permissions
	 *
	 * @return null|array
	 */
	public static function customPermissionNodes()
	{
		return NULL;
	}

	/**
	 * @brief	Cached permission checks
	 */
	protected $cachedPermissionChecks = array();

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
		$_key = md5( $permission . ( $member ? ( ( $member instanceof \IPS\Member\Group ) ? $member->g_id : $member->member_id . '-' . $member->member_group_id ) : \IPS\Member::loggedIn()->member_id . '-' . \IPS\Member::loggedIn()->member_group_id ) . (int) $considerPostBeforeRegistering );

		if( isset( $this->cachedPermissionChecks[ $_key ] ) )
		{
			return $this->cachedPermissionChecks[ $_key ];
		}

		/* If it's disabled, return FALSE */
		if ( $this->_enabled === FALSE )
		{
			$this->cachedPermissionChecks[ $_key ] = FALSE;
			return $this->cachedPermissionChecks[ $_key ];
		}

		/* If this is not permission-dependant, return TRUE */
		if ( !( $this instanceof \IPS\Node\Permissions ) )
		{
			$this->cachedPermissionChecks[ $_key ] = TRUE;
			return $this->cachedPermissionChecks[ $_key ];
		}

		/* Check it exists */
		if ( !isset( static::$permissionMap[ $permission ] ) )
		{
			throw new \OutOfBoundsException;
		}

		/* Load member */
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}

		/* If this is an owned node, we don't have permission if we don't own it */
		if( static::$ownerTypes !== NULL AND static::$ownerTypes['member'] !== NULL AND \in_array( $permission, array( 'add', 'edit', 'delete' ) ) )
		{
			if( $member instanceof \IPS\Member\Group )
			{
				$this->cachedPermissionChecks[ $_key ] = FALSE;
				return $this->cachedPermissionChecks[ $_key ];
			}

			$column	= static::$ownerTypes['member'];

			if( $member->member_id !== $this->$column )
			{
				$this->cachedPermissionChecks[ $_key ] = FALSE;
				return $this->cachedPermissionChecks[ $_key ];
			}
		}
		
		/* If this is a club, we need to make sure we can view the module */
		if ( \in_array( $permission, array( 'view', 'read' ) ) )
		{
			/* If this is a club node, make sure we can access the clubs module */
			if ( \IPS\IPS::classUsesTrait( \get_called_class(), 'IPS\Content\ClubContainer' ) AND $this->club() )
			{
				if ( !$member->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
				{
					$this->cachedPermissionChecks[ $_key ] = FALSE;
					return $this->cachedPermissionChecks[ $_key ];
				}
			}
		}

		/* If we are checking view permissions, make sure we can view parent too */
		if( $permission == 'view' )
		{
			try
			{
				foreach( $this->parents() as $parent )
				{
					if( !$parent->can( $permission, $member ) )
					{
						$this->cachedPermissionChecks[ $_key ] = FALSE;
						return $this->cachedPermissionChecks[ $_key ];
					}
				}
			}
				/* If parent or parents do not exist, we cannot view - happens sometimes with upgrades due to old bugs */
			catch( \OutOfRangeException $e )
			{
				$this->cachedPermissionChecks[ $_key ] = FALSE;
				return $this->cachedPermissionChecks[ $_key ];
			}
		}

		/* If we're checking add permissions - make sure we are not over our posts per day limit */
		if ( \in_array( $permission, array( 'add', 'reply', 'review' ) ) AND $member instanceof \IPS\Member )
		{
			$checkPostsPerDay = TRUE;
			if ( isset( static::$contentItemClass ) )
			{
				$contentClass = static::$contentItemClass;
				$checkPostsPerDay = $contentClass::$checkPostsPerDay;
			}
			
			if ( $checkPostsPerDay === TRUE AND $member->checkPostsPerDay() === FALSE )
			{
				$this->cachedPermissionChecks[ $_key ] = FALSE;
				return $this->cachedPermissionChecks[ $_key ];
			}
		}

		/* Get the node permissions */
		$permissions = $this->permissions();
		$relevantPermissionString = $permissions[ 'perm_' . static::$permissionMap[ $permission ] ];
		if ( $relevantPermissionString === '*' )
		{
			$this->cachedPermissionChecks[ $_key ] = TRUE;
			return $this->cachedPermissionChecks[ $_key ];
		}
		elseif ( !$relevantPermissionString )
		{
			$this->cachedPermissionChecks[ $_key ] = FALSE;
			return $this->cachedPermissionChecks[ $_key ];
		}
		$relevantPermissionArray = array_filter( explode( ',', $relevantPermissionString ) );
				
		/* Work out which to use taking Post Before Registering into consideration and return */
		$considerPostBeforeRegistering = ( $considerPostBeforeRegistering and \IPS\Settings::i()->post_before_registering and \IPS\Login::registrationType() != 'disabled' );
		if( $member instanceof \IPS\Member\Group )
		{
			if ( $considerPostBeforeRegistering and $member->g_id == \IPS\Settings::i()->guest_group )
			{
				$groupId = \IPS\Settings::i()->member_group;
			}
			else
			{
				$groupId = $member->g_id;
			}
			
			$this->cachedPermissionChecks[ $_key ] = \in_array( $groupId, $relevantPermissionArray );
			return $this->cachedPermissionChecks[ $_key ];
		}
		else
		{
			$canPermission = (bool) \count( array_intersect( $relevantPermissionArray, $member->permissionArray() ) );

			if ( !$canPermission AND $considerPostBeforeRegistering and !$member->member_id and \in_array( $permission, array( 'add', 'reply', 'review' ) ) )
			{
				$this->cachedPermissionChecks[ $_key ] = \in_array( \IPS\Settings::i()->member_group, $relevantPermissionArray );
			}
			else
			{
				$this->cachedPermissionChecks[ $_key ] = $canPermission;
			}

			return $this->cachedPermissionChecks[ $_key ];
		}
	}

	/**
	 * Return a list of groups that cannot see this node
	 *
	 * @return 	NULL|Array
	 */
	public function cannotViewGroups()
	{
		$groups = array();
		foreach( \IPS\Member\Group::groups() as $group )
		{
			if ( ! $this->can( 'view', $group, FALSE ) )
			{
				$groups[] = $group->name;
			}
		}

		return \count( $groups ) ? $groups : NULL;
	}
	
	/**
	 * Check Moderator Permission
	 *
	 * @param	string						$type		'edit', 'hide', 'unhide', 'delete', etc.
	 * @param	\IPS\Member|NULL			$member		The member to check for or NULL for the currently logged in member
	 * @param	string						$class		The class to check against
	 * @return	bool
	 */
	public function modPermission( $type, \IPS\Member $member, $class )
	{
		if ( isset( static::$modPerm ) )
		{
			$class = $class ?: static::$contentItemClass;
			$title = $class::$title;
			if ( $member->modPermission( "can_{$type}_{$title}" ) )
			{
				if ( $member->modPermission( static::$modPerm ) === -1 )
				{
					return TRUE;
				}
				if ( \is_array( $member->modPermission( static::$modPerm ) ) and \in_array( $this->_id, $member->modPermission( static::$modPerm ) ) )
				{
					return TRUE;
				}
			}
		}
		
		return FALSE;
	}

	/**
	 * @brief	Disable the copy button - useful when the forms are very distinctly different
	 */
	public $noCopyButton	= FALSE;

	/**
	 * [ActiveRecord] Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();

		if ( $this instanceof \IPS\Node\Permissions and $this->_permissions !== NULL and $this->_permissions != $this->_originalPermissions )
		{
			if ( !isset( $this->_permissions['perm_id'] ) )
			{
				foreach ( array( 'app' => static::$permApp, 'perm_type' => static::$permType, 'perm_type_id' => $this->_id ) as $k => $v )
				{
					if ( !isset( $this->_permissions[ $k ] ) )
					{
						$this->_permissions[ $k ] = $v;
					}
				}

				\IPS\Db::i()->replace( 'core_permission_index', $this->_permissions );
			}
			else
			{
				\IPS\Db::i()->update( 'core_permission_index', $this->_permissions, array( 'perm_id=?', $this->_permissions['perm_id'] ) );
			}
		}
	}
	
	/**
	 * Get where clause for a mass move/delete
	 *
	 * @param	array|null	$data	Additional filters to mass move by
	 * @return	array
	 */
	public function massMoveorDeleteWhere( $data=NULL )
	{
		$contentItemClass = static::$contentItemClass;

		$where = array();
		if ( isset( $data['additional'] ) AND \count( $data['additional'] ) )
		{
			/* Author */
			if ( isset( $data['additional']['author'] ) )
			{
				if ( \is_array( $data['additional']['author'] ) )
				{
					if ( \count( $data['additional']['author'] ) )
					{
						$where[] = array( \IPS\Db::i()->in( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['author'], $data['additional']['author'] ) );
					}
				}
				else
				{
					$where[] = array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['author'] . '=?', $data['additional']['author'] );
				}
			}
			
			/* Posted before */
			if ( isset( $data['additional']['date'] ) AND $data['additional']['date'] )
			{
				$where[] = array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['date'] . '<=?', $data['additional']['date'] );
			}
			
			/* Number of comments is less than */
			if ( isset( $data['additional']['num_comments'] ) AND $data['additional']['num_comments'] > 0 )
			{
				$where[] = array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['num_comments'].'<=?', $data['additional']['num_comments'] );
			}
			
			/* Last post was before */
			$lastCommentField = $contentItemClass::$databaseColumnMap['last_comment'];
			$field = \is_array( $lastCommentField ) ? array_pop( $lastCommentField ) : $lastCommentField;
			if ( isset( $data['additional']['no_comments'] ) AND $data['additional']['no_comments'] > 0 ) // Legacy, may still be in queue
			{
				$where[] = array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['num_comments'] . '<=? AND ' . $contentItemClass::$databasePrefix . $field . '<?', $contentItemClass::$firstCommentRequired ? 1 : 0, $data['additional']['no_comments'] );
			}
			if ( isset( $data['additional']['last_post'] ) AND $data['additional']['last_post'] )
			{
				$where[] = array( $contentItemClass::$databasePrefix . $field . '<=?', $data['additional']['last_post'] );
			}
			
			/* Locked/Unlocked */
			if ( isset( $data['additional']['state'] ) )
			{
				if ( isset( $contentItemClass::$databaseColumnMap['locked'] ) )
				{
					$where[] = array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['locked'].'=?', $data['additional']['state'] == 'locked' ? 1 : 0 );
				}
				else
				{
					$where[] = array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['status'].'=?', $data['additional']['state'] == 'locked' ? 'closed' : 'open' );
				}
			}
			
			/* Pinned/Unpinned */
			if ( isset( $data['additional']['pinned'] ) )
			{
				if ( $data['additional']['pinned'] === TRUE ) // Legacy, may still be in queue
				{
					$where[] = array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['pinned'].'!=?', 1 );
				}
				else
				{
					$where[] = array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['pinned'].'=?', $data['additional']['pinned'] );
				}
			}
			
			/* Featured/Unfeatured */
			if ( isset( $data['additional']['featured'] ) )
			{
				if ( $data['additional']['featured'] === TRUE ) // Legacy, may still be in queue
				{
					$where[] = array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['featured'].'!=?', 1 );
				}
				else
				{
					$where[] = array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['featured'].'=?', $data['additional']['featured'] );
				}
			}
		}
		
		return $where;
	}

	/**
	 * Mass move content items in this node to another node
	 *
	 * @param	\IPS\Node\Model|null	$node	New node to move content items to, or NULL to delete
	 * @param	array|null				$data	Additional filters to mass move by
	 * @return	NULL|int
	 */
	public function massMoveorDelete( $node=NULL, $data=NULL )
	{
		$select = $this->getContentItems( 100, 0, $this->massMoveorDeleteWhere( $data ) );

		if ( \count( $select ) )
		{
			foreach ( $select as $item )
			{
				if ( $node )
				{
					$item->move( $node );
				}
				else
				{
					$item->delete();
				}
			}

			return 100;
		}
		else
		{
			return NULL;
		}
	}

	/**
	 * Set the comment/approved/hidden counts
	 *
	 * @return void
	 */
	public function resetCommentCounts()
	{
		if ( !isset( static::$contentItemClass ) )
		{
			return false;
		}

		/* Update container */
		$itemClass 		 = static::$contentItemClass;
		$idColumn		 = static::$databaseColumnId;
		$itemIdColumn    = $itemClass::$databaseColumnId;
		$commentClass    = NULL;
		$reviewClass	 = NULL;

		/* If using comments or reviews, get the class too */
		if( isset( $itemClass::$commentClass ) )
		{
			$commentClass	= $itemClass::$commentClass;
		}

		if( isset( $itemClass::$reviewClass ) )
		{
			$reviewClass	= $itemClass::$reviewClass;
		}

		$containerWhere    = array( array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'] . '=?', $this->_id ) );
		$anyContainerWhere = $containerWhere;

		if ( \in_array( 'IPS\Content\Hideable', class_implements( $itemClass ) ) )
		{
			if ( isset( $itemClass::$databaseColumnMap['approved'] ) )
			{
				$containerWhere[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['approved'] . '=?', 1 );
			}
			elseif ( isset( $itemClass::$databaseColumnMap['hidden'] ) )
			{
				$containerWhere[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['hidden'] . '=?', 0 );
			}
		}
		if ( $this->_items !== NULL )
		{
			$this->_items = \IPS\Db::i()->select( 'COUNT(*)', $itemClass::$databaseTable, $containerWhere, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		}
		if ( $this->_comments !== NULL AND $commentClass !== NULL AND !isset( $itemClass::$databaseColumnMap['num_comments'] ) )
		{
			$commentWhere = array(
				array( $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . ' = ' . $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemIdColumn ),
				array( $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'] . '=?', $this->_id )
			);

			if ( \in_array( 'IPS\Content\Hideable', class_implements( $itemClass ) ) )
			{
				if ( isset( $itemClass::$databaseColumnMap['approved'] ) )
				{
					$commentWhere[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['approved'] . '=?', 1 );
				}
				elseif ( isset( $itemClass::$databaseColumnMap['hidden'] ) )
				{
					$commentWhere[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['hidden'] . '=?', 0 );
				}
			}

			if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
			{
				$commentWhere[] = array( $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 1 );
			}
			elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
			{
				$commentWhere[] = array( $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', 0 );
			}

			$this->_comments = \IPS\Db::i()->select( 'COUNT(*)', array(
				array( $commentClass::$databaseTable, $commentClass::$databaseTable ),
				array( $itemClass::$databaseTable, $itemClass::$databaseTable ),
			), $commentWhere, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		}
		/* If we have a cached value, use that instead */
		elseif( $this->_comments !== NULL AND $commentClass !== NULL AND isset( $itemClass::$databaseColumnMap['num_comments'] ) )
		{
			$commentWhere = array(
				array( $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'] . '=?', $this->_id )
			);

			if ( \in_array( 'IPS\Content\Hideable', class_implements( $itemClass ) ) )
			{
				if ( isset( $itemClass::$databaseColumnMap['approved'] ) )
				{
					$commentWhere[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['approved'] . '=?', 1 );
				}
				elseif ( isset( $itemClass::$databaseColumnMap['hidden'] ) )
				{
					$commentWhere[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['hidden'] . '=?', 0 );
				}
			}

			$this->_comments = (int) \IPS\Db::i()->select( "SUM({$itemClass::$databasePrefix}{$itemClass::$databaseColumnMap['num_comments']})", $itemClass::$databaseTable, $commentWhere, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		}

		if ( $this->_reviews !== NULL AND $reviewClass !== NULL )
		{
			$reviewWhere = array(
				array( $reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . ' = ' . $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemIdColumn ),
				array( $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'] . '=?', $this->_id )
			);

			if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
			{
				$reviewWhere[] = array( $reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '=?', 1 );
			}
			elseif ( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
			{
				$reviewWhere[] = array( $reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '=?', 0 );
			}

			$this->_reviews = \IPS\Db::i()->select( 'COUNT(*)', array(
				array( $reviewClass::$databaseTable, $reviewClass::$databaseTable ),
				array( $itemClass::$databaseTable, $itemClass::$databaseTable )
			), $reviewWhere, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		}

		if ( \in_array( 'IPS\Content\Hideable', class_implements( $itemClass ) ) )
		{
			if ( $this->_unapprovedItems !== NULL )
			{
				$hiddenContainerWhere = array( array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'] . '=?', $this->_id ) );

				if ( isset( $itemClass::$databaseColumnMap['approved'] ) )
				{
					$hiddenContainerWhere[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['approved'] . '=?', 0 );
				}
				elseif ( isset( $itemClass::$databaseColumnMap['hidden'] ) )
				{
					$hiddenContainerWhere[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['hidden'] . '=?', 1 );
				}

				$this->_unapprovedItems = \IPS\Db::i()->select( 'COUNT(*)', $itemClass::$databaseTable, $hiddenContainerWhere, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
			}

			if( $commentClass !== NULL AND \in_array( 'IPS\Content\Hideable', class_implements( $commentClass ) ) )
			{
				$commentWhere = array( array( $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . ' = ' . $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemIdColumn ) );
				if ( $this->_unapprovedComments !== NULL AND !isset( $itemClass::$databaseColumnMap['unapproved_comments'] ) )
				{
					if ( $itemClass::$firstCommentRequired )
					{
						/* Only look in non-hidden items otherwise this count will be added to */
						$commentWhere = array_merge( $commentWhere, $containerWhere );
					}
					else
					{
						$commentWhere = array_merge( $commentWhere, $anyContainerWhere );
					}

					if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
					{
						$commentWhere[] = array( $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 0 );
					}
					elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
					{
						$commentWhere[] = array( $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', 1 );
					}

					$this->_unapprovedComments = \IPS\Db::i()->select( 'COUNT(*)', array(
						array( $commentClass::$databaseTable, $commentClass::$databaseTable ),
						array( $itemClass::$databaseTable, $itemClass::$databaseTable )
					), $commentWhere, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
				}
				/* If we have a cached value, use that instead */
				elseif( $this->_unapprovedComments !== NULL AND isset( $itemClass::$databaseColumnMap['unapproved_comments'] ) )
				{
					$commentWhere = array(
						array( $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'] . '=?', $this->_id )
					);

					if ( \in_array( 'IPS\Content\Hideable', class_implements( $itemClass ) ) )
					{
						if ( isset( $itemClass::$databaseColumnMap['approved'] ) )
						{
							$commentWhere[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['approved'] . '=?', 1 );
						}
						elseif ( isset( $itemClass::$databaseColumnMap['hidden'] ) )
						{
							$commentWhere[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['hidden'] . '=?', 0 );
						}
					}

					$this->_unapprovedComments = (int) \IPS\Db::i()->select( "SUM({$itemClass::$databasePrefix}{$itemClass::$databaseColumnMap['unapproved_comments']})", $itemClass::$databaseTable, $commentWhere, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
				}
			}

			if( $reviewClass !== NULL AND \in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
			{
				$reviewWhere = array( array( $reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . ' = ' . $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemIdColumn ) );
				if ( $this->_unapprovedReviews !== NULL )
				{
					$reviewWhere = array_merge( $reviewWhere, $anyContainerWhere );

					if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
					{
						$reviewWhere[] = array( $reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '=?', 0 );
					}
					elseif ( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
					{
						$reviewWhere[] = array( $reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '=?', 1 );
					}

					$this->_unapprovedReviews = \IPS\Db::i()->select( 'COUNT(*)', array(
						array( $reviewClass::$databaseTable, $reviewClass::$databaseTable ),
						array( $itemClass::$databaseTable, $itemClass::$databaseTable )
					), $reviewWhere, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
				}
			}
		}
	}

	/**
	 * Retrieve the content item count
	 *
	 * @param	null|array	$data	Data array for mass move/delete
	 * @return	null|int
	 */
	public function getContentItemCount( $data=NULL )
	{
		if ( !isset( static::$contentItemClass ) )
		{
			return false;
		}

		$contentItemClass = static::$contentItemClass;
		$idColumn = static::$databaseColumnId;

		$where = array( array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['container'] . '=?', $this->$idColumn ) );

		if( $data )
		{
			$where = array_merge_recursive( $where, $this->massMoveorDeleteWhere( $data ) );
		}

		return (int) \IPS\Db::i()->select( 'COUNT(*)', $contentItemClass::$databaseTable, $where )->first();
	}

	/**
	 * Retrieve content items (if applicable) for a node.
	 *
	 * @param	int		$limit			The limit
	 * @param	int		$offset			The offset
	 * @param	array	$additional		Where Additional where clauses
	 * @param	int		$countOnly		If TRUE, will get the number of results
	 * @return	\IPS\Patterns\ActiveRecordIterator|int
	 * @throws	\BadMethodCallException
	 */
	public function getContentItems( $limit, $offset, $additionalWhere = array(), $countOnly=FALSE )
	{
		if ( !isset( static::$contentItemClass ) )
		{
			throw new \BadMethodCallException;
		}

		$contentItemClass = static::$contentItemClass;

		$where		= array();
		$where[]	= array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['container'] . '=?', $this->_id );

		if ( \count( $additionalWhere ) )
		{
			foreach( $additionalWhere AS $clause )
			{
				$where[] = $clause;
			}
		}
		
		if ( $countOnly )
		{
			return \IPS\Db::i()->select( 'COUNT(*)', $contentItemClass::$databaseTable, $where )->first();
		}
		else
		{
			$contentItemClass = static::$contentItemClass;
			$limit	= ( $offset !== NULL ) ? array( $offset, $limit ) : NULL;
			return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', $contentItemClass::$databaseTable, $where, $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnId, $limit ), $contentItemClass );
		}
	}

	/**
	 * Alter permissions for an individual group
	 *
	 * @param	int|\IPS\Member\Group	$group	Group to alter
	 * @param	array					$permissions	Array map of permission key => boolean value
	 * @return	void
	 */
	public function changePermissions( $group, $permissions )
	{
		/* Get our group ID */
		$groupId	= ( $group instanceof \IPS\Member\Group ) ? $group->g_id : (int) $group;

		/* Get all groups - we will need it to adjust permissions we are adding or taking away */
		$allGroups	= \IPS\Member\Group::groups();

		/* Set a flag so we know if we actually need to update anything later (i.e. in the search index) */
		$hasChange	= FALSE;

		/* Update permissions */
		foreach( $permissions as $permissionKey => $newValue )
		{
			if( !$this->_permissions )
			{
				$this->permissions();
			}

			$existing	= $this->_permissions[ 'perm_' . static::$permissionMap[ $permissionKey ] ];
			$updated	= array();

			/* Are we removing permission? */
			if( !$newValue )
			{
				if( $existing == '*' )
				{
					foreach( $allGroups as $_group )
					{
						if( $_group->g_id != $groupId )
						{
							$updated[]	= $_group->g_id;
						}
						else
						{
							/* This group was previously allowed and now it is not */
							$hasChange	= TRUE;
						}
					}
				}
				else if( $existing )
				{
					$existing	= explode( ',', $existing );

					foreach( $existing as $_existing )
					{
						if( $_existing != $groupId )
						{
							$updated[]	= $_existing;
						}
						else
						{
							/* This group was previously allowed and now it is not */
							$hasChange	= TRUE;
						}
					}
				}

				$updated	= implode( ',', $updated );
			}

			/* Or are we giving permission? */
			else
			{
				if( $existing != '*' )
				{
					$existing	= explode( ',', $existing );

					if( !\in_array( $groupId, $existing ) )
					{
						/* This group was previously not allowed and now it is */
						$hasChange	= TRUE;
					}

					$updated	= array_unique( array_merge( $existing, array( $groupId ) ) );
					$updated	= ( \count( $updated ) == \count( $allGroups ) ) ? '*' : implode( ',', $updated );
				}
			}

			if( !\is_array( $updated ) )
			{
				$this->_permissions[ 'perm_' . static::$permissionMap[ $permissionKey ] ]	= $updated;
			}
		}

		/* Save */
		$this->save();

		/* Update search index if anything has changed */
		if( $hasChange )
		{
			$this->updateSearchIndexPermissions();
		}
	}
	
	/**
	 * Can promote this comment/item?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	boolean
	 */
	public function canPromoteToSocialMedia( $member=NULL )
	{
		return \IPS\core\Promote::canPromote( $member );
	}
	
	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		if( $this->skipCloneDuplication === TRUE )
		{
			return;
		}

		if ( $this instanceof \IPS\Node\Permissions )
		{
			$this->_permissions = \IPS\Db::i()->select( array( 'perm_id', 'perm_view', 'perm_2', 'perm_3', 'perm_4', 'perm_5', 'perm_6', 'perm_7' ), 'core_permission_index', array( "app=? AND perm_type=? AND perm_type_id=?", static::$permApp, static::$permType, $this->_id ) )->first();
			unset( $this->_permissions['perm_id'] );
		}

		$oldId = $this->_id;

		parent::__clone();

		if ( static::$titleLangPrefix )
		{
			\IPS\Lang::saveCustom( ( static::$permApp !== NULL ) ? static::$permApp : 'core', static::$titleLangPrefix . $this->_id, iterator_to_array( \IPS\Db::i()->select( 'CONCAT(word_custom, \' ' . \IPS\Member::loggedIn()->language()->get('copy_noun') . '\') as word_custom, lang_id', 'core_sys_lang_words', array( 'word_key=?', static::$titleLangPrefix . $oldId ) )->setKeyField( 'lang_id' )->setValueField('word_custom') ) );
		}
		elseif ( method_exists( $this, 'get__title' ) and method_exists( $this, 'set__title' ) )
		{
			$this->_title = $this->_title . ' ' . \IPS\Member::loggedIn()->language()->get('copy_noun');
		}

		if( isset( static::$descriptionLangSuffix ) )
		{
			\IPS\Lang::saveCustom( ( static::$permApp !== NULL ) ? static::$permApp : 'core', static::$titleLangPrefix . $this->_id . static::$descriptionLangSuffix, iterator_to_array( \IPS\Db::i()->select( 'word_custom, lang_id', 'core_sys_lang_words', array( 'word_key=?', static::$titleLangPrefix . $oldId . static::$descriptionLangSuffix ) )->setKeyField( 'lang_id' )->setValueField('word_custom') ) );
		}

		if( isset( static::$databaseColumnOrder ) )
		{
			$orderColumn = static::$databaseColumnOrder;
			$order = \IPS\Db::i()->select( array( "MAX( `" . static::$databasePrefix . static::$databaseColumnOrder . "` )" ), static::$databaseTable, array() )->first();
			$this->$orderColumn = $order + 1;
		}

		$this->_items = 0;
		$this->_comments = 0;
		$this->_reviews = 0;
		foreach ( array( 'Items', 'Comments', 'Reviews' ) as $k )
		{
			$k = "_unapproved{$k}";
			if ( $this->$k !== NULL )
			{
				$this->$k = 0;
			}
		}
		$this->setLastComment();
		$this->setLastReview();
		$this->save();
	}

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( $this instanceof \IPS\Node\Permissions )
		{
			\IPS\Db::i()->delete( 'core_permission_index', array( "app=? AND perm_type=? AND perm_type_id=?", static::$permApp, static::$permType, $this->_id ) );
		}

		if ( $this instanceof \IPS\Node\Ratings )
		{
			\IPS\Db::i()->delete( 'core_ratings', array( "class=? AND item_id=?", \get_called_class(), $this->_id ) );
		}

		if( !empty( static::$permApp ) )
		{
			\IPS\Db::i()->delete( 'core_follow', array("follow_app=? AND follow_area=? AND follow_rel_id=?", static::$permApp, static::$permType, $this->_id ) );
			\IPS\Db::i()->delete( 'core_follow_count_cache', array( 'id=? AND class=?', $this->_id, \get_called_class() ) );

			/* Remove any entries in the promotions table */
			\IPS\Db::i()->delete( 'core_social_promote', array( 'promote_class=? AND promote_class_id=?', \get_called_class(), $this->_id ) );
		}

		/* Delete lang strings */
		if ( static::$titleLangPrefix )
		{
			\IPS\Lang::deleteCustom( ( static::$permApp !== NULL ) ? static::$permApp : 'core', static::$titleLangPrefix . $this->_id );
		}

		if( isset( static::$descriptionLangSuffix ) )
		{
			\IPS\Lang::deleteCustom( ( static::$permApp !== NULL ) ? static::$permApp : 'core', static::$titleLangPrefix . $this->_id . static::$descriptionLangSuffix );
		}

		/* Delete Rss Imports */
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_rss_import', array( 'rss_import_class=? AND rss_import_node_id=?', \get_called_class() , $this->id ) ), 'IPS\core\Rss\Import' ) as $import )
		{
			$import->delete();
		}
		return parent::delete();
	}

	/**
	 * @brief	Cache for current follow data, used on "My Followed Content" screen
	 */
	public $_followData;

	/**
	 * @param NULL|\IPS\Node\Model 	$node	Null or specific node
	 * @return void
	 */
	public static function populateFollowerCounts( $node=NULL )
	{
		$children = array();
		$nodeClass = NULL;
		if ( $node === NULL )
		{
			foreach ( static::roots() as $root )
			{
				$nodeClass = $root;
				break;
			}
		}
		else
		{
			$nodeClass = $node;
		}
		
		if ( $nodeClass === NULL )
		{
			return;
		}

		$nodes = array();

		if ( isset( $nodeClass::$contentItemClass ) )
		{
			if ( !\in_array( 'IPS\Content\Followable', class_implements( $nodeClass::$contentItemClass ) ) )
			{
				throw new \BadMethodCallException;
			}
		}

		$contentClass = $nodeClass::$contentItemClass;

		/* If we didn't pass any node, get the follower counts for the immediate children */
		if ( $node === NULL )
		{
			foreach ( static::roots() as $root )
			{
				if ( $root->hasChildren() )
				{
					foreach ( $root->children() as $childNode )
					{
						$nodes[$childNode->_id] = $childNode;
					}
				}
			}

			$followers = iterator_to_array( $contentClass::containerFollowerCounts( \array_values( $nodes ) ) );

			foreach ( static::roots() as $root )
			{
				if ( $root->hasChildren() )
				{
					foreach ( $root->children() as &$childNode )
					{
						if ( isset( $followers[$childNode->_id] ) )
						{
							$childNode->followerCount = $followers[$childNode->_id]['count'];
						}
					}
				}
			}
		}
		else
		{
			/* We passed a node, get the children followers */
			if ( $node->hasChildren() )
			{
				foreach ( $node->children() as $childNode )
				{
					$nodes[$childNode->_id] = $childNode;
				}
			}

			$followers = iterator_to_array( $contentClass::containerFollowerCounts( \array_values( $nodes ) ) );

			if ( $node->hasChildren() )
			{
				foreach ( $node->children() as &$childNode )
				{
					if ( isset( $followers[$childNode->_id] ) )
					{
						$childNode->followerCount = $followers[$childNode->_id]['count'];
					}
				}
			}
		}
	}

	/* !ACP forms */

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
	}

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		return $values;
	}

	/**
	 * [Node] Save Add/Edit Form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function saveForm( $values )
	{
		foreach ( $values as $k => $v )
		{
			if( $k == 'csrfKey' )
			{
				continue;
			}

			if ( isset( static::$databasePrefix ) and mb_substr( $k, 0, mb_strlen( static::$databasePrefix ) ) === static::$databasePrefix )
			{
				$k = mb_substr( $k, mb_strlen( static::$databasePrefix ) );
			}

			if ( \is_array( $v ) )
			{
				/* Handle bitoptions */
				if( \is_array( static::$bitOptions ) AND array_key_exists( $k, static::$bitOptions ) )
				{
					$options = $this->$k;
					foreach( $v as $_k => $_v )
					{
						$options[ $_k ]	= $_v;
					}
					$this->$k = $options;

					continue;
				}
				else if( !method_exists( $this, 'set_' . $k ) )
				{
					$v = implode( ',', $v );
				}
			}

			$this->$k = $v;
		}

		$this->save();
		$this->postSaveForm( $values );
	}

	/**
	 * [Node] Perform actions after saving the form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function postSaveForm( $values )
	{
	}

	/**
	 * Can a value be copied to this node?
	 *
	 * @return	bool
	 */
	public function canCopyValue( $key, $value )
	{
		if ( $key === static::$databasePrefix . static::$databaseColumnParent and $value )
		{
			if ( is_scalar( $value ) )
			{
				try
				{
					$value = static::load( $value );
				}
				catch ( \OutOfRangeException $e )
				{
					return TRUE;
				}
			}

			if ( $this->_id === $value->_id )
			{
				return FALSE;
			}

			foreach( $this->children( NULL ) as $obj )
			{
				if ( $obj->_id === $value->_id )
				{
					return FALSE;
				}
			}
		}

		return TRUE;
	}

	/**
	 * Should we show the form to delete or move content?
	 *
	 * @return bool
	 */
	public function showDeleteOrMoveForm()
	{
		/* Do we have any children or content? */
		$hasContent = FALSE;
		if ( isset( static::$contentItemClass ) )
		{
			$hasContent	= $this->getContentItemCount();
		}
		else if ( method_exists( $this, 'getItemCount' ) )
		{
			$hasContent = $this->getItemCount();
		}

		return (bool) $hasContent;
	}

	/**
	 * Form to delete or move content
	 *
	 * @param	bool	$showMoveToChildren	If TRUE, will show "move to children" even if there are no children
	 * @return	\IPS\Helpers\Form
	 */
	public function deleteOrMoveForm( $showMoveToChildren=FALSE )
	{
		$hasContent = FALSE;
		if ( isset( static::$contentItemClass ) )
		{
			$hasContent	= (bool) $this->getContentItemCount();
		}

		$form = new \IPS\Helpers\Form( 'delete_node_form', 'delete' );
		$form->addMessage( 'node_delete_blurb' );
		if ( $showMoveToChildren or $this->hasChildren( NULL, NULL, TRUE ) )
		{
			\IPS\Member::loggedIn()->language()->words['node_move_children'] = sprintf( \IPS\Member::loggedIn()->language()->get( 'node_move_children', FALSE ), \IPS\Member::loggedIn()->language()->addToStack( static::$nodeTitle, FALSE, array( 'strtolower' => TRUE) ) );
			$form->add( new \IPS\Helpers\Form\Node( 'node_move_children', 0, TRUE, array( 
				'class'			=> \get_class( $this ), 
				'disabled'		=> array( $this->_id ), 
				'disabledLang'	=> 'node_move_delete', 
				'zeroVal'		=> 'node_delete_children', 
				'subnodes'		=> FALSE,
				'permissionCheck'	=> function( $node )
				{
					return $node->canAdd();
				}
			) ) );
		}
		if ( $hasContent )
		{
			$contentItemClass	= static::$contentItemClass;
			$form->add( new \IPS\Helpers\Form\Node( 'node_move_content', 0, TRUE, array( 'class' => \get_class( $this ), 'disabled' => array( $this->_id ), 'disabledLang' => 'node_move_delete', 'zeroVal' => 'node_delete_content', 'subnodes' => FALSE, 'clubs' => TRUE, 'permissionCheck' => function( $node )
			{
				return array_key_exists( 'add', $node->permissionTypes() );
			} ) ) );
		}

		return $form;
	}
	
	/**
	 * Recursive Helper Method for queueing a node and all it's sub nodes, sub-sub nodes, sub-sub-sub nodes, etc.
	 *
	 * @param	\IPS\Node\Model		$node	The initial node
	 * @return	array
	 */
	protected static function _nodesToQueue( \IPS\Node\Model $node ): array
	{
		$return = array( $node );
		if ( $node->hasChildren( NULL ) )
		{
			foreach( $node->children( NULL ) AS $child )
			{
				$return = \array_merge( $return, static::_nodesToQueue( $child ) );
			}
		}
		return $return;
	}

	/**
	 * Handle submissions of form to delete or move content
	 *
	 * @param	array	$values			Values from form
	 * @return	void
	 */
	public function deleteOrMoveFormSubmit( $values )
	{
		if ( isset( $values['node_move_children'] ) AND $values['node_move_children'] )
		{
			/* If we are moving children, we don't need to act on children of children as their parent reference should not change */
			/* Since we're moving the child nodes elsewhere, we only need to queue this one - no need to recurse into sub-sub nodes. */
			$nodesToQueue = array( $this );
			foreach ( $this->children( NULL ) as $child )
			{
				$parentColumn = ( isset( static::$subnodeClass ) AND $child instanceof static::$subnodeClass ) ? $child::$parentNodeColumnId : $child::$databaseColumnParent;
				$child->$parentColumn = ( isset( $values['node_destination'] ) ) ? $values['node_destination'] : \IPS\Request::i()->node_move_children;
				$child->setLastComment();
				$child->setLastReview();
				$child->save();
			}
		}
		/* However if we are deleting, we need to delete children of children (and their children, etc.) too */
		else
		{
			$nodesToQueue = static::_nodesToQueue( $this );
		}

		/* Load existing tasks */
		$existing = iterator_to_array( \IPS\Db::i()->select( 'id,data', 'core_queue', array( 'app=? AND `key`=?', 'core', 'DeleteOrMoveContent' ) )->setKeyField('id')->setValueField('data') );

		foreach ( $nodesToQueue as $_node )
		{
			if ( \in_array( 'IPS\Node\Permissions', class_implements( $_node ) ) )
			{
				\IPS\Db::i()->update( 'core_permission_index', array( 'perm_view' => '' ), array( "app=? AND perm_type=? AND perm_type_id=?", $_node::$permApp, $_node::$permType, $_node->_id ) );
			}

			$additional = array();

			if ( isset( $values['node_move_content'] ) and $values['node_move_content'] )
			{
				\IPS\Task::queue( 'core', 'DeleteOrMoveContent', array( 'class' => \get_class( $_node ), 'id' => $_node->_id, 'moveToClass' => \get_class( $values['node_move_content'] ), 'moveTo' => $values['node_move_content']->_id, 'deleteWhenDone' => TRUE, 'additional' => $additional ) );
			}
			else
			{
				\IPS\Task::queue( 'core', 'DeleteOrMoveContent', array( 'class' => \get_class( $_node ), 'id' => $_node->_id, 'deleteWhenDone' => TRUE, 'additional' => $additional ) );
			}

			/* Check existing tasks for in-progress moves to one of these nodes */
			foreach( $existing AS $rowId => $row )
			{
				$data = json_decode( $row, TRUE );
				if ( isset( $data['moveToClass'] ) AND $data['moveToClass'] === \get_class( $_node ) AND isset( $data['moveTo'] ) AND $data['moveTo'] == $_node->_id )
				{
					/* Move the content into a different node */
					if ( isset( $values['node_move_content'] ) and $values['node_move_content'] )
					{
						$data['moveToClass'] = \get_class( $values['node_move_content'] );
						$data['moveTo'] = $values['node_move_content']->_id;
					}
					/* Delete opted to delete all content, change the task so that it deletes instead */
					else
					{
						unset( $data['moveToClass'], $data['moveTo'] );
					}

					\IPS\Db::i()->update( 'core_queue', array( 'data' => json_encode( $data ) ), array( 'id=?', $rowId ) );
				}
			}
		}
	}
	
	/**
	 * @brief	Cache of open DeleteOrMoveContent queue tasks
	 * @see		deleteOrMoveQueued()
	 */
	protected static $deleteOrMoveQueue = NULL;

	/**
	 * Is this node currently queued for deleting or moving content OR is it the target of content queued to be moved from another node?
	 *
	 * @return	bool
	 */
	public function deleteOrMoveQueued()
	{
		/* If we already know, don't bother */
		if ( \is_null( $this->queued ) )
		{
			$this->queued = FALSE;

			if( !isset( static::$contentItemClass ) )
			{
				return $this->queued;
			}
			
			if ( !\is_array( static::$deleteOrMoveQueue ) )
			{
				static::$deleteOrMoveQueue = iterator_to_array( \IPS\Db::i()->select( 'data', 'core_queue', array( 'app=? AND `key`=?', 'core', 'DeleteOrMoveContent' ) ) );
			}

			foreach( static::$deleteOrMoveQueue AS $row )
			{
				$data = json_decode( $row, TRUE );
				if ( $data['class'] === \get_class( $this ) AND $data['id'] == $this->_id )
				{
					$this->queued = TRUE;
				}
				elseif ( isset( $data['moveTo'] ) )
				{
					$moveToClass = $data['moveToClass'] ?? \get_class( $this );

					if ( $moveToClass === \get_class( $this ) AND $data['moveTo'] == $this->_id )
					{
						$this->queued = TRUE;
					}
				}
			}
		}

		return $this->queued;
	}

	/**
	 * @brief	Flag for currently queued
	 */
	protected $queued = NULL;

	/* !Ratings */

	/**
	 * Can Rate?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function canRate( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		switch ( $member->group['g_topic_rate_setting'] )
		{
			case 2:
				return TRUE;
			case 1:
				try
				{
					$idColumn = static::$databaseColumnId;
					\IPS\Db::i()->select( '*', 'core_ratings', array( 'class=? AND item_id=? AND `member`=?', \get_called_class(), $this->$idColumn, $member->member_id ) )->first();
					return FALSE;
				}
				catch ( \UnderflowException $e )
				{
					return TRUE;
				}
				break;
			default:
				return FALSE;
		}
	}

	/**
	 * Get average rating
	 *
	 * @return	int
	 * @throws	\BadMethodCallException
	 */
	public function averageRating()
	{
		if ( !( $this instanceof \IPS\Node\Ratings ) )
		{
			throw new \BadMethodCallException;
		}

		if ( isset( static::$ratingColumnMap['rating_average'] ) )
		{
			$column	= static::$ratingColumnMap['rating_average'];
			return $this->$column;
		}
		elseif ( isset( static::$ratingColumnMap['rating_total'] ) and isset( static::$ratingColumnMap['rating_hits'] ) )
		{
			$hits	= static::$ratingColumnMap['rating_hits'];
			$total	= static::$ratingColumnMap['rating_total'];
			return $this->$hits ? round( $this->$total / $this->$hits, 1 ) : 0;
		}
		else
		{
			$idColumn = static::$databaseColumnId;
			return round( \IPS\Db::i()->select( 'AVG(rating)', 'core_ratings', array( 'class=? AND item_id=?', \get_called_class(), $this->$idColumn ) )->first(), 1 );
		}
	}

	/**
	 * Display rating (will just display stars if member cannot rate)
	 *
	 * @return	string
	 * @throws	\BadMethodCallException
	 */
	public function rating()
	{
		if ( !( $this instanceof \IPS\Node\Ratings ) )
		{
			throw new \BadMethodCallException;
		}

		if ( $this->canRate() )
		{
			$idColumn = static::$databaseColumnId;

			$form = new \IPS\Helpers\Form('rating');
			$form->add( new \IPS\Helpers\Form\Rating( 'rating', $this->averageRating() ) );

			if ( $values = $form->values() )
			{
				\IPS\Db::i()->insert( 'core_ratings', array(
					'class'			=> \get_called_class(),
					'item_id'		=> $this->$idColumn,
					'member'		=> \IPS\Member::loggedIn()->member_id,
					'rating'		=> $values['rating'],
					'ip'			=> \IPS\Request::i()->ipAddress(),
					'rating_date'	=> time()
				), TRUE );

				if ( isset( static::$ratingColumnMap['rating_average'] ) )
				{
					$column = static::$ratingColumnMap['rating_average'];
					$this->$column = round( \IPS\Db::i()->select( 'AVG(rating)', 'core_ratings', array( 'class=? AND item_id=?', \get_called_class(), $this->$idColumn ) )->first(), 1 );
				}
				if ( isset( static::$ratingColumnMap['rating_total'] ) )
				{
					$column = static::$ratingColumnMap['rating_total'];
					$this->$column = \IPS\Db::i()->select( 'SUM(rating)', 'core_ratings', array( 'class=? AND item_id=?', \get_called_class(), $this->$idColumn ) )->first();
				}
				if ( isset( static::$ratingColumnMap['rating_hits'] ) )
				{
					$column = static::$ratingColumnMap['rating_hits'];
					$this->$column = \IPS\Db::i()->select( 'COUNT(*)', 'core_ratings', array( 'class=? AND item_id=?', \get_called_class(), $this->$idColumn ) )->first();
				}

				$this->save();

				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( 'OK' );
				}
			}

			return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'ratingTemplate' ) );
		}
		else
		{
			return \IPS\Theme::i()->getTemplate( 'global', 'core' )->rating( 'veryLarge', $this->averageRating() );
		}
	}

	/* !Tables */

	/**
	 * Get template for node tables
	 *
	 * @return	callable
	 */
	public static function nodeTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'tables', 'core' ), 'nodeRows' );
	}

	/**
	 * Get template for managing this nodes follows
	 *
	 * @return	callable
	 */
	public static function manageFollowNodeRow()
	{
		return array( \IPS\Theme::i()->getTemplate( 'tables', 'core' ), 'manageFollowNodeRow' );
	}

	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int			id				ID number
	 * @apiresponse	string		name			Name
	 * @apiresponse	string		url				URL
	 * @apiresponse	string		class			Node class
	 * @apiresponse	int|null	parentId		Parent Node ID
	 * @clientapiresponse	object|null		permissions		Node permissions
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		$return = array(
			'id'			=> $this->id,
			'name'			=> $this->_title,
			'url'			=> (string) $this->url(),
			'class'			=> \get_class( $this ),
			'parentId'		=> static::$databaseColumnParent ? $this->{static::$databaseColumnParent} : NULL
		);

		if( $authorizedMember === NULL )
		{
			$return['permissions']	= \in_array( 'IPS\Node\Permissions', class_implements( \get_class( $this ) ) ) ? $this->permissions() : NULL;
		}

		if ( \IPS\IPS::classUsesTrait( \get_called_class(), 'IPS\Content\ClubContainer' ) )
		{
			if( $this->club() )
			{
				$return['public'] = ( $this->isPublic() ) ? 1 : 0;
				$return['club'] = $this->club()->apiOutput( $authorizedMember );
			}
			else
			{
				$return['club'] = 0;
			}
		}

		return $return;
	}
	
	/**
	 * Webhook filters
	 *
	 * @return	array
	 */
	public function webhookFilters()
	{
		return array();
	}

	/**
	 * [Node] Get the fully qualified node type (mostly for Pages because Pages is Pages)
	 *
	 * @return	string
	 */
	public static function fullyQualifiedType()
	{
		return static::$nodeTitle . '_sg';
	}

	/**
	 * @brief   Field cache for getDataLayerProperties
	 */
	protected $_dataLayerProperties = array();

	/**
	 * Get the properties that can be added to the datalayer for this key
	 *
	 * @return  array
	 */
	public function getDataLayerProperties()
	{
		if ( empty( $this->_dataLayerProperties ) )
		{
			try
			{
				$url = (string) $this->url();
			}
			catch ( \BadMethodCallException $e )
			{
				$url = null;
			}

			/* Try to figure out the Node's app, its lang key will be the area */
			$area = "System";
			if ( static::$permApp )
			{
				$app = static::$permApp;
			}
			elseif ( !empty( static::$restrictions['app'] ) )
			{
				$app = static::$restrictions['app'];
			}
			else
			{
				$class = \get_called_class();
				$matches = array();
				preg_match( '/IPS\\\\([a-z]+)\\\\/', $class, $matches );
				$app = $matches[1] ?? null;
			}

			/* If we got a valid app (directory) name, try its language key */
			if ( $app )
			{
				$area = \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->addToStack( "__app_$app" );
			}

			/* Get the path */
			$type = static::$nodeTitle ?? 'container';
			$path = [
				array( 'type' => $type, 'id' => $this->_id, 'name' => $this->_title )
			];
			foreach ( $this->parents() as $parent )
			{
				$path[] = array( 'type' => $parent::$nodeTitle ?? 'container', 'id' => $parent->_id, 'name' => $parent->_title );
			}

			$this->_dataLayerProperties = array(
				'content_area'              => $area,
				'content_container_id'      => $this->_id,
				'content_container_name'    => $this->_title,
				'content_container_type'    => $type,
				'content_container_url'     => $url,
				'content_container_path'    => array_reverse( $path ),
			);
		}
		return $this->_dataLayerProperties;
	}

	/**
	 * Content was held for approval by container
	 * Allow node classes that can determine if content should be held for approval in individual nodes
	 *
	 * @param	string				$content	The type of content we are checking (item, comment, review).
	 * @param	\IPS\Member|NULL	$member		Member to check or NULL for currently logged in member.
	 * @return	bool
	 */
	public function contentHeldForApprovalByNode( string $content, ?\IPS\Member $member = NULL ): bool
	{
		return FALSE;
	}
}