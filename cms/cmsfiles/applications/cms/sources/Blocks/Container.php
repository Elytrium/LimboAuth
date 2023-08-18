<?php
/**
 * @brief		Block Container Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		19 Feb 2014
 */

namespace IPS\cms\Blocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Block Container Model
 */
class _Container extends \IPS\Node\Model
{

	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'cms_containers';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'container_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';

	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array('container_key');
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();

	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = 'parent_id';
	
	/**
	 * @brief	[Node] Parent ID Root Value
	 * @note	This normally doesn't need changing though some legacy areas use -1 to indicate a root node
	 */
	public static $databaseColumnParentRootValue = 0;
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'order';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = '';
	
	/**
	 * @brief	[Node] Subnode class
	 */
	public static $subnodeClass = 'IPS\cms\Blocks\Block';
	
	/**
	 * @brief	[Node] Show forms modally?
	 */
	public static $modalForms = TRUE;
	
	/**
	 * @brief	[Node] Sortable?
	 */
	public static $nodeSortable = TRUE;

	/**
	 * [Node] Get Title
	 *
	 * @return	string|null
	 */
	protected function get__title()
	{
		return $this->name;
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
		return parent::roots( $permissionCheck, $member, array( array( 'container_type=?', 'block' ) ), $limit );
	}
	
	/**
	 * [Node] Get buttons to display in tree
	 *
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = parent::getButtons( $url, $subnode );
		$return  = array();
		
		if ( isset( $buttons['copy'] ) )
		{
			unset( $buttons['copy'] );
		}
		
		if ( isset( $buttons['add'] ) )
		{
			$buttons['add']['icon']	 = 'folder-open';
			$buttons['add']['title'] = 'content_block_cat_add';
			$buttons['add']['data']  = array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('content_block_cat_add') );
			$buttons['add']['link']	 = $url->setQueryString( array( 'subnode' => 0, 'do' => 'form', 'parent' => $this->_id ) );
				
			$buttons['add_block'] = array(
					'icon'	=> 'plus-circle',
					'title'	=> 'content_block_block_add',
					'link'	=> $url->setQueryString( array( 'subnode' => 1, 'do' => 'addBlockType', 'parent' => $this->_id ) )
			);
		}
		
		/* Re-arrange */
		if ( isset( $buttons['edit'] ) )
		{
			$return['edit'] = $buttons['edit'];
		}
		
		if ( isset( $buttons['add_block'] ) )
		{
			$return['add_block'] = $buttons['add_block'];
		}
		
		if ( isset( $buttons['add'] ) )
		{
			$return['add'] = $buttons['add'];
		}
			
		if ( isset( $buttons['delete'] ) )
		{
			$return['delete'] = $buttons['delete'];
		}
		
		return $return;
	}

	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		if ( $this->key == 'block_custom' OR $this->key == 'block_plugins' )
		{
			return FALSE;
		}

		return parent::canDelete();
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		/* Build form */
		$form->add( new \IPS\Helpers\Form\Text( 'container_name', $this->id ? $this->name : '', TRUE, array( 'maxLength' => 64 ) ) );

		$class = \get_called_class();

		$form->add( new \IPS\Helpers\Form\Node( 'container_parent_id', $this->parent_id ? $this->parent_id : 0, FALSE, array(
				'class'         => '\IPS\cms\Blocks\Container',
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
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( isset( $values['container_parent_id'] ) AND ( ! empty( $values['container_parent_id'] ) OR $values['container_parent_id'] === 0 ) )
		{
			$values['container_parent_id'] = ( $values['container_parent_id'] === 0 ) ? 0 : $values['container_parent_id']->id;
		}
		
		$values['type'] = 'block';
		
		return $values;
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
		if ( $column === '_title' )
		{
			$column = 'container_name';
		}
		
		$where = array( array( 'container_type=?', 'block' ) );
	
		return parent::search( $column, $query, 'container_name ASC', $where );
	}
}