<?php
/**
 * @brief		Report Types
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Dec 2017
 */

namespace IPS\core\Reports;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Report types node model
 */
class _Types extends \IPS\Node\Model
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_automatic_moderation_types';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'type_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'automaticmod_types';
	
	/**
	 * @brief	[Node] Sortable
	 */
	public static $nodeSortable = TRUE;
	
	/**
	 * @brief	[Node] Positon Column
	 */
	public static $databaseColumnOrder = 'position';

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'automaticmod_types_';
	
	/**
	 * Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Translatable( 'automaticmod_types_title', NULL, TRUE, array( 'app' => 'core', 'key' => ( $this->id ? 'automaticmod_types_' . $this->id : NULL ) ) ) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( !$this->id )
		{
			$this->save();
		}
		
		/* Save the title */
		\IPS\Lang::saveCustom( 'core', 'automaticmod_types_' . $this->id, $values['automaticmod_types_title'] );
		
		unset( $values['automaticmod_types_title'] );
		
		return parent::formatFormValues( $values );
	}

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
		if ( !\count( $where ) )
		{
			$return = array();
			foreach( static::getStore() AS $node )
			{
				$return[ $node['type_id'] ] = static::constructFromData( $node );
			}
			
			return $return;
		}
		else
		{
			return parent::roots( $permissionCheck, $member, $where, $limit );
		}
	}
	
	/**
	 * Get data store
	 *
	 * @return	array
	 * @note	Note that all records are returned, even disabled report type rules. Enable status needs to be checked in userland code when appropriate.
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->automatic_moderation_types ) )
		{
			\IPS\Data\Store::i()->automatic_moderation_types = iterator_to_array( \IPS\Db::i()->select( '*', static::$databaseTable, NULL, "type_position ASC" )->setKeyField( 'type_id' ) );
		}
		
		return \IPS\Data\Store::i()->automatic_moderation_types;
	}

	/**
	 * @brief	[ActiveRecord] Attempt to load from cache
	 * @note	If this is set to TRUE you MUST define a getStore() method to return the objects from cache
	 */
	protected static $loadFromCache = TRUE;
	
	/**
	 * Get item count
	 * I'm sure you could have figured that out from the method name
	 * but I'll spoon feed you, it's ok.
	 *
	 * @return INT
	 */
	public function getItemCount()
	{
		return \intval( \IPS\Db::i()->select( 'COUNT(*)', 'core_rc_reports', array( 'report_type=?', $this->id ) )->first() );
	}
	
	/**
	 * Form to delete or move content
	 *
	 * @param	bool	$showMoveToChildren	If TRUE, will show "move to children" even if there are no children
	 * @return	\IPS\Helpers\Form
	 */
	public function deleteOrMoveForm( $showMoveToChildren=FALSE )
	{
		$form = new \IPS\Helpers\Form( 'delete_node_form', 'delete' );
		$form->addMessage( 'node_delete_blurb' );
	
		if ( $this->getItemCount() )
		{
			$form->add( new \IPS\Helpers\Form\Node( 'node_move_content', 0, TRUE, array( 'class' => \get_class( $this ), 'disabled' => array( $this->_id ), 'disabledLang' => 'node_move_delete', 'zeroVal' => 'node_delete_content', 'subnodes' => FALSE, 'permissionCheck' => function( $node )
			{
				return true;
			} ) ) );
		}

		return $form;
	}
	
		/**
	 * Handle submissions of form to delete or move content
	 *
	 * @param	array	$values			Values from form
	 * @return	void
	 */
	public function deleteOrMoveFormSubmit( $values )
	{
		if ( isset( $values['node_move_content'] ) and $values['node_move_content'] )
		{
			/* We're moving first */
			\IPS\Db::i()->update( 'core_rc_reports', array( 'report_type' => $values['node_move_content']->_id ), array( 'report_type=?', $this->id ) );
		}
		
		$this->delete();
	}

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'automatic_moderation_types' );
}