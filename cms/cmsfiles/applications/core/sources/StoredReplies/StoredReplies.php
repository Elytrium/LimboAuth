<?php
/**
 * @brief		Stored Replies Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Core
 * @since		03 September 2021
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Stored Replies Node
 */
class _StoredReplies extends \IPS\Node\Model implements \IPS\Node\Permissions
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_editor_stored_replies';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'editor_stored_replies';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databasePrefix = 'reply_';

	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
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
		'app'		=> 'core',
		'module'	=> 'editor',
		'prefix'	=> 'storedReplies_'
	);

	/**
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = 'core';

	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = 'editorStoredReplies';

	/**
	 * @brief	The map of permission columns
	 */
	public static $permissionMap = array(
		'view' => 'view'
	);

	/**
	 * @brief	[Node] Prefix string that is automatically prepended to permission matrix language strings
	 */
	public static $permissionLangPrefix = 'perm_editor_stored_reply_';

	/**
	 * @brief	[Node] Enabled/Disabled Column
	 */
	public static $databaseColumnEnabledDisabled = 'enabled';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'editor_stored_replies_';
		
	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		return $this->enabled;
	}

	/**
	 * [Node] Set whether or not this node is enabled
	 *
	 * @param	bool|int	$enabled	Whether to set it enabled or disabled
	 * @return	void
	 */
	protected function set__enabled( $enabled )
	{
		$this->enabled	= $enabled;
	}

	/**
	 * [Node] Get Title
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		return $this->title;
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Text( 'editor_stored_replies_title', $this->id ? $this->title : NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'editor_stored_replies_enabled', $this->id ? $this->enabled : TRUE, FALSE, array( 'togglesOn' => array( 'editor_stored_replies_content' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'editor_stored_replies_content', $this->id ? $this->text : FALSE, FALSE, array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => ( $this->id ? "core-editor-replies-{$this->id}" : "core-editor-replies" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'editor_stored_replies' ) : NULL ), NULL, NULL, NULL, 'editor_stored_replies_content' ) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		foreach( [
			'editor_stored_replies_title' => 'title',
			'editor_stored_replies_enabled' => 'enabled',
			'editor_stored_replies_content' => 'text'
			] as $input => $name )
		{
			$values[ $name ] = $values[ $input ];
			unset( $values[ $input ] );
		}

		/* Remove any lazy loading stuffs */
		$values['text'] = \IPS\Text\Parser::removeLazyLoad( $values['text'] );

		$values['added_by'] = \IPS\Member::loggedIn()->loggedIn()->member_id;

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
		/* Looks a bit weird, but as this is postSave, $this->id is filled and $this->_new is false, _permissions is always null if it's a new entry */
		\IPS\File::claimAttachments( ( $this->_permissions === null ) ? "core-editor-replies" : "core-editor-replies-{$this->id}", $this->id, NULL, 'editor_stored_replies' );
	}

	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\File::unclaimAttachments( 'core_Admin', $this->id, NULL, 'editor_stored_replies' );
		parent::delete();
	}

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'editorStoredReplies' );

	/**
	 * Get data store
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->editorStoredReplies ) )
		{
			/* Don't get the reply text as this could make for a large store [but, dear future developer it might be fine to include it if you really have a need I didn't forsee]
			   Oh and we grab permissions here so we don't need to do a full query each time the editor is loaded */
			\IPS\Data\Store::i()->editorStoredReplies = iterator_to_array(
				\IPS\Db::i()->select(
					'reply_id, reply_title, reply_added_by, reply_enabled, core_permission_index.perm_id, core_permission_index.perm_view',
					static::$databaseTable
				)->join(
					'core_permission_index',
					array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnId, static::$permApp, static::$permType )
				)->setKeyField('reply_id')
			);
		}
		
		return \IPS\Data\Store::i()->editorStoredReplies;
	}

	/**
	 * Set the permission index permissions
	 *
	 * @param	array	$insert	Permission data to insert
	 * @return  void
	 */
	public function setPermissions( $insert )
	{
		parent::setPermissions( $insert );

		/* Clear cache */
		unset( \IPS\Data\Store::i()->editorStoredReplies );
	}
}