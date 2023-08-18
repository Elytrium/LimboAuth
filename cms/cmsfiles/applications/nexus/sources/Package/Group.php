<?php
/**
 * @brief		Package Group Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		29 Apr 2014
 */

namespace IPS\nexus\Package;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Package Group
 */
class _Group extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_package_groups';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'pg_';
	
	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = 'parent';
		
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
		
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'product_groups';
	
	/**
	 * @brief	[Node] Subnode class
	 */
	public static $subnodeClass = 'IPS\nexus\Package';

	/**
	 * @brief	Content Item Class
	 */
	public static $contentItemClass = 'IPS\nexus\Package\Item';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'nexus_pgroup_';
	
	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_desc';
								
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
		'app'		=> 'nexus',
		'module'	=> 'store',
		'prefix'	=> 'packages_',
	);

	/**
	 * Return only the root groups that have packages OR subcategories/groups
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @return	array
	 */
	public static function rootsWithViewablePackages( $permissionCheck='view', $member=NULL, $where=array() )
	{
		$roots = static::roots( $permissionCheck, $member, $where );

		foreach( $roots as $index => $group )
		{
			if( !$group->hasSubgroups() AND !$group->hasPackages( NULL, array( array( "p_store=1 AND ( p_member_groups='*' OR " . \IPS\Db::i()->findInSet( 'p_member_groups', \IPS\Member::loggedIn()->groups ) . ' )' ) ) ) )
			{
				unset( $roots[ $index ] );
			}
		}

		return $roots;
	}
	
	/**
	 * Load record based on a URL
	 *
	 * @param	\IPS\Http\Url	$url	URL to load from
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function loadFromUrl( \IPS\Http\Url $url )
	{
		$qs = array_merge( $url->queryString, $url->hiddenQueryString );
		
		if ( isset( $qs['cat'] ) )
		{
			return static::load( $qs['cat'] );
		}
		
		throw new \InvalidArgumentException;
	}
		
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->addHeader('pg_basic_settings');
		$form->add( new \IPS\Helpers\Form\Translatable( 'pg_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => $this->id ? "nexus_pgroup_{$this->id}" : NULL ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'pg_desc', NULL, FALSE, array(
			'app'		=> 'nexus',
			'key'		=> ( $this->id ? "nexus_pgroup_{$this->id}_desc" : NULL ),
			'editor'	=> array(
				'app'			=> 'nexus',
				'key'			=> 'Admin',
				'autoSaveKey'	=> ( $this->id ? "nexus-group-{$this->id}" : "nexus-new-group" ),
				'attachIds'		=> $this->id ? array( $this->id, NULL, 'pgroup' ) : NULL, 'minimize' => 'pg_desc_placeholder'
			)
		) ) );

		$class = \get_called_class();

		$form->add( new \IPS\Helpers\Form\Node( 'pg_parent', $this->id ? $this->parent : 0, TRUE, array( 'class' => 'IPS\nexus\Package\Group', 'subnodes' => FALSE, 'zeroVal' => 'no_parent', 'permissionCheck' => function( $node ) use ( $class )
		{
			if( isset( $class::$subnodeClass ) AND $class::$subnodeClass AND $node instanceof $class::$subnodeClass )
			{
				return FALSE;
			}

			return !isset( \IPS\Request::i()->id ) or ( $node->id != \IPS\Request::i()->id and !$node->isChildOf( $node::load( \IPS\Request::i()->id ) ) );
		} ) ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'pg_image', $this->image ? \IPS\File::get( 'nexus_PackageGroups', $this->image ) : NULL, FALSE, array( 'storageExtension' => 'nexus_PackageGroups', 'image' => TRUE, 'allowStockPhotos' => TRUE ) ) );
		
		$priceFilters = array();
		if ( $this->price_filters )
		{
			foreach ( json_decode( $this->price_filters, TRUE ) as $currency => $prices )
			{
				foreach ( $prices as $i => $price )
				{
					$priceFilters[ $i ][ $currency ] = $price;
				}
			}
		}
		
		$form->addHeader('pg_filters_header');
		$form->add( new \IPS\Helpers\Form\Node( 'pg_filters', explode( ',', $this->filters ), FALSE, array( 'class' => 'IPS\nexus\Package\Filter', 'multiple' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'pg_price_filters', $priceFilters, FALSE, array( 'stackFieldType' => 'IPS\nexus\Form\Money' ) ) );
		
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{		
		if( isset( $values['pg_parent'] ) )
		{
			$values['parent'] = $values['pg_parent'] ? $values['pg_parent']->id : 0;
		}

		if( isset( $values['pg_image'] ) )
		{
			$values['image'] = (string) $values['pg_image'];
		}
		
		if ( !$this->id )
		{
			$this->save();
			\IPS\File::claimAttachments( 'nexus-new-group', $this->id, NULL, 'pgroup', TRUE );
		}
		elseif( isset( $values['pg_name'] ) OR isset( $values['pg_desc'] ) )
		{
			$this->save();
		}
		
		if( isset( $values['pg_name'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_pgroup_{$this->id}", $values['pg_name'] );
			unset( $values['pg_name'] );
		}

		if( isset( $values['pg_desc'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_pgroup_{$this->id}_desc", $values['pg_desc'] );
			unset( $values['pg_desc'] );
		}
		
		$values['pg_filters'] = $values['pg_filters'] ? implode( ',', array_keys( $values['pg_filters'] ) ) : NULL;
		
		$priceFilters = array();
		foreach ( $values['pg_price_filters'] as $filter )
		{
			foreach ( $filter as $currency => $amount )
			{
				$priceFilters[ $currency ][] = $amount->amount->jsonSerialize();
			}
		}
		$values['pg_price_filters'] = \count( $priceFilters ) ? json_encode( $priceFilters ) : NULL;

		return $values;
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * Get URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		if( $this->_url === NULL )
		{
			$this->_url = \IPS\Http\Url::internal( "app=nexus&module=store&controller=store&cat={$this->id}", 'front', 'store_group', \IPS\Http\Url\Friendly::seoTitle( \IPS\Member::loggedIn()->language()->get( 'nexus_pgroup_' . $this->id ) ) );
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
		return \IPS\Http\Url::internal( "app=nexus&module=store&controller=store&cat={$indexData['index_container_id']}", 'front', 'store_group', \IPS\Member::loggedIn()->language()->addToStack( 'nexus_pgroup_' . $indexData['index_container_id'], FALSE, array( 'seotitle' => TRUE ) ) );
	}
	
	/**
	 * Get full image URL
	 *
	 * @return string
	 */
	public function get_image()
	{
		return ( isset( $this->_data['image'] ) ) ? (string) \IPS\File::get( 'nexus_PackageGroups', $this->_data['image'] )->url : NULL;
	}
	
	/**
	 * Does this group have subgroups?
	 *
	 * @param	mixed	$_where	Additional WHERE clause
	 * @return	bool
	 */
	public function hasSubgroups( $_where=array() )
	{
		return ( $this->childrenCount( NULL, NULL, FALSE, $_where ) > 0 );
	}
	
	/**
	 * Does this group have packages?
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member to perform the permission check for, or NULL for currently logged in member, or FALSE for no permission check
	 * @param	mixed					$_where			Additional WHERE clause
	 * @param	bool					$viewableOnly	Only check packages the member can view
	 * @return	bool
	 */
	public function hasPackages( $member=NULL, $_where=array(), $viewableOnly=FALSE )
	{
		if( $viewableOnly === TRUE )
		{
			$member = $member ?: \IPS\Member::loggedIn();

			$_where[]	= array( "p_store=1 AND ( p_member_groups='*' OR " . \IPS\Db::i()->findInSet( 'p_member_groups', $member->groups ) . ' )' );
		}

		return ( $this->childrenCount( $member === FALSE ? FALSE : 'view', $member, NULL, $_where ) > 0 );
	}
	
	/**
	 * Get Filter Options
	 *
	 * @param	\IPS\Lang	$language	The language to return options in
	 * @return	array
	 */
	public function filters( \IPS\Lang $language )
	{
		$return = array();
		
		if ( $this->filters )
		{
			foreach ( \IPS\Db::i()->select( 'pfilter_id', 'nexus_package_filters', array( \IPS\Db::i()->in( 'pfilter_id', explode( ',', $this->filters ) ) ), 'pfilter_order' ) as $filterId )
			{
				$return[ $filterId ] = array();
			}
						
			foreach ( \IPS\Db::i()->select( '*', 'nexus_package_filters_values', array( array( \IPS\Db::i()->in( 'pfv_filter', array_keys( $return ) ) ), array( 'pfv_lang=?', $language->id ) ), 'pfv_order' ) as $value )
			{
				$return[ $value['pfv_filter'] ][ $value['pfv_value'] ] = $value['pfv_text'];
			}
		}

		return $return;
	}

	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		if ( $this->skipCloneDuplication === TRUE )
		{
			return;
		}

		$oldImage = $this->image;

		parent::__clone();

		if ( $oldImage )
		{
			try
			{
				$icon = \IPS\File::get( 'nexus_PackageGroups', $oldImage );
				$newIcon = \IPS\File::create( 'nexus_PackageGroups', $icon->originalFilename, $icon->contents() );
				$this->image = (string) $newIcon;
			}
			catch ( \Exception $e )
			{
				$this->pg_image = NULL;
			}

			$this->save();
		}
	}
	
	/**
	 * Is this node currently queued for deleting or moving content?
	 *
	 * @return	bool
	 */
	public function deleteOrMoveQueued()
	{
		return FALSE;
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
		$buttons = parent::getButtons( $url, $subnode );

		if( isset( $buttons['content'] ) )
		{
			$buttons['content']['title'] = 'mass_manage_productgroups';
		}

		return $buttons;
	}
}
