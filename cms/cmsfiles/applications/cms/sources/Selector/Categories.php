<?php
/**
 * @brief		Categories Selector Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		20 July 2015
 */

namespace IPS\cms\Selector;

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
	 * @brief	[Node] Parent Node ID Database Column
	 */
	public static $parentNodeColumnId = 'database_id';
	
	/**
	 * @brief	[Node] Parent Node Class
	 */
	public static $parentNodeClass = 'IPS\cms\Selector\Databases';
	
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
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = 'cms';
	
	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = 'categories';
	
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
	public static $permissionLangPrefix = 'perm_content_';
	
	/**
	 * Get title of category
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		/* If the DB is in a page, and we're not using categories, then return the page title, not the category title for continuity */
		if ( ! \IPS\cms\Databases::load( $this->database_id )->use_categories )
		{
			if ( ! $this->_catTitle )
			{
				try
				{
					$page = \IPS\cms\Pages\Page::loadByDatabaseId( $this->database_id );
					$this->_catTitle = $page->_title;
				}
				catch( \OutOfRangeException $e )
				{
					$this->_catTitle = parent::get__title();
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
	
}