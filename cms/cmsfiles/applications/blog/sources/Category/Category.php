<?php
/**
 * @brief		Blog Category Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		30 Jul 2019
 */

namespace IPS\blog;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Blog Category
 */
class _Category extends \IPS\Node\Model
{
	use \IPS\Content\ViewUpdates;
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'blog_categories';

	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'category_';

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
	public static $nodeTitle = 'blog_categories';

	/**
	 * @brief	[Node] Subnode class
	 */
	public static $subnodeClass = 'IPS\blog\Blog';

	/**
	 * @brief	Content Item Class
	 */
	public static $contentItemClass = 'IPS\blog\Entry';

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'blog_category_';

	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_desc';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'seo_name';

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
		'app'		=> 'blog',
		'module'	=> 'categories',
		'prefix'	=> 'categories_',
	);

	/**
	 * @brief   The class of the ACP \IPS\Node\Controller that manages this node type
	 */
	protected static $acpController = "IPS\\blog\\modules\\admin\\blogs\\blogs";

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->addHeader('category_basic_settings');
		$form->add( new \IPS\Helpers\Form\Translatable( 'category_name', NULL, TRUE, array( 'app' => 'blog', 'key' => $this->id ? "blog_category_{$this->id}" : NULL ) ) );

		\IPS\Member::loggedIn()->language()->words['category_desc'] = \IPS\Member::loggedIn()->language()->addToStack('blog_category_desc');
		$form->add( new \IPS\Helpers\Form\Translatable( 'category_desc', NULL, FALSE, array(
			'app'		=> 'blog',
			'key'		=> ( $this->id ? "blog_category_{$this->id}_desc" : NULL ),
			'editor'	=> array(
				'app'			=> 'blog',
				'key'			=> 'Categories',
				'autoSaveKey'	=> ( $this->id ? "blog-category-{$this->id}" : "blog-new-category" ),
				'attachIds'		=> $this->id ? array( $this->id, NULL, 'bcategory' ) : NULL, 'minimize' => 'category_desc_placeholder'
			)
		) ) );

		$class = \get_called_class();

		$form->add( new \IPS\Helpers\Form\Node( 'category_parent', $this->id ? $this->parent : 0, TRUE, array( 'class' => 'IPS\blog\Category', 'subnodes' => FALSE, 'zeroVal' => 'no_parent', 'permissionCheck' => function( $node ) use ( $class )
		{
			if( isset( $class::$subnodeClass ) AND $class::$subnodeClass AND $node instanceof $class::$subnodeClass )
			{
				return FALSE;
			}

			return !isset( \IPS\Request::i()->id ) or ( $node->id != \IPS\Request::i()->id and !$node->isChildOf( $node::load( \IPS\Request::i()->id ) ) );
		} ) ) );
	}

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if( isset( $values['category_parent'] ) )
		{
			$values['parent'] = $values['category_parent'] ? $values['category_parent']->id : 0;
		}

		if ( !$this->id )
		{
			$this->save();
			\IPS\File::claimAttachments( 'blog-new-category', $this->id, NULL, 'bcategory', TRUE );
		}
		elseif( isset( $values['category_name'] ) OR isset( $values['category_desc'] ) )
		{
			$this->save();
		}

		if( isset( $values['category_name'] ) )
		{
			\IPS\Lang::saveCustom( 'blog', "blog_category_{$this->id}", $values['category_name'] );
			$values['seo_name'] = \IPS\Http\Url\Friendly::seoTitle( $values['category_name'][ \IPS\Lang::defaultLanguage() ] );
		
			unset( $values['category_name'] );
		}

		if( isset( $values['category_desc'] ) )
		{
			\IPS\Lang::saveCustom( 'blog', "blog_category_{$this->id}_desc", $values['category_desc'] );
			unset( $values['category_desc'] );
		}

	
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
			$seoTitleColumn = static::$seoTitleColumn;
			$this->_url = \IPS\Http\Url::internal( "app=blog&module=blogs&controller=browse&id={$this->id}", 'front', 'blog_category', $this->$seoTitleColumn );
		}

		return $this->_url;
	}
	
	/**
	 * Get HTML link
	 *
	 * @return	string
	 */
	public function link()
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'blog' )->blogCategoryLink( $this );
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
		return \IPS\Http\Url::internal( "app=blog&module=blogs&controller=browse&category={$indexData['index_container_id']}", 'front', 'blog_category', \IPS\Member::loggedIn()->language()->addToStack( 'blog_category_' . $indexData['index_container_id'], FALSE, array( 'seotitle' => TRUE ) ) );
	}

	/**
	 * [Node] Get Node Icon
	 *
	 * @return	string
	 */
	protected function get__icon()
	{
		return ( $this->parent > 0 ) ? 'caret-down' : NULL ;
	}

	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int			id			ID number
	 * @apiresponse	string		name		Name
	 * @apiresponse	string		url			URL
	 * @apiresponse	string		class		Node class
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		$return = array(
			'id'		=> $this->id,
			'name'		=> $this->_title,
			'url'		=> (string) $this->url(),
			'class'		=> \get_class( $this ),
		);

		if ( \IPS\IPS::classUsesTrait( \get_called_class(), 'IPS\Content\ClubContainer' ) AND $this->club() )
		{
			$return['public'] = ( $this->isPublic() ) ? 1 : 0;
		}
		
		return $return;
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

		$where = array( array( 'blog_blogs.blog_category_id=?', $this->$idColumn ) );

		if( $data )
		{
			$where = array_merge_recursive( $where, $this->massMoveorDeleteWhere( $data ) );
		}

		$select = \IPS\Db::i()->select( 'COUNT(*)', $contentItemClass::$databaseTable, $where )->join( 'blog_blogs', "blog_entries.entry_blog_id=blog_blogs.blog_id" );
				
		return (int) $select->first();
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
		$where[]	= array( 'blog_blogs.blog_category_id=?', $this->_id );

		if ( \count( $additionalWhere ) )
		{
			foreach( $additionalWhere AS $clause )
			{
				$where[] = $clause;
			}
		}
		
		if ( $countOnly )
		{
			return \IPS\Db::i()->select( 'COUNT(*)', $contentItemClass::$databaseTable, $where )->join( 'blog_blogs', "blog_entries.entry_blog_id=blog_blogs.blog_id" )->first();
		}
		else
		{
			$contentItemClass = static::$contentItemClass;
			$limit	= ( $offset !== NULL ) ? array( $offset, $limit ) : NULL;
			return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', $contentItemClass::$databaseTable, $where, $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnId, $limit )->join( 'blog_blogs', "blog_entries.entry_blog_id=blog_blogs.blog_id" ), $contentItemClass );
		}
	}
}
