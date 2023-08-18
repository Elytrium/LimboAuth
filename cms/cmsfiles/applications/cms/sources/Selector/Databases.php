<?php
/**
 * @brief		Databases Selector Model
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
 * @brief	Databases Model
 */
class _Databases extends \IPS\Node\Model implements \IPS\Node\Permissions
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons = array();
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'database_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Table
	 */
	public static $databaseTable = 'cms_databases';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'database_key', 'database_page_id' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnOrder = 'id';
	
	/**
	 * @brief	[Node] Sortable?
	 */
	public static $nodeSortable = FALSE;
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = '';
	
	/**
	 * @brief	[Node] Subnode class
	 */
	public static $subnodeClass = 'IPS\cms\Selector\Categories';
	
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
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = 'cms';
	
	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = 'databases';
	
	/**
	 * @brief	[Node] Prefix string that is automatically prepended to permission matrix language strings
	 */
	public static $permissionLangPrefix = 'perm_content_';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}" as the key
	 */
	public static $titleLangPrefix = 'content_db_';
	
	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = 'desc';
}