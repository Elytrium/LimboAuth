<?php
/**
 * @brief		Folder Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		07 July 2015
 */

namespace IPS\cms\Media;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Folder Model
 */
class _Folder extends \IPS\Node\Model
{
	/**
	 * Munge different record types
	 *
	 * @return  array
	 */
	public static function munge()
	{
		$rows = array();
		$args = \func_get_args();
	
		foreach( $args as $arg )
		{
			foreach( $arg as $id => $obj )
			{
				$rows[ $obj->getSortableName() . '_' . $obj::$databaseTable . '_' . $obj->id  ] = $obj;
			}
		}
	
		ksort( $rows );
	
		return $rows;
	}
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'cms_media_folders';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'media_folder_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array('media_folder_path');
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = 'parent';
	
	/**
	 * @brief	[Node] Parent ID Root Value
	 * @note	This normally doesn't need changing though some legacy areas use -1 to indicate a root node
	 */
	public static $databaseColumnParentRootValue = 0;
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'path';

	/**
	 * @brief	[Node] Automatically set position for new nodes
	 */
	public static $automaticPositionDetermination = FALSE;
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'folder';
	
	/**
	 * @brief	[Node] Subnode class
	 */
	public static $subnodeClass = 'IPS\cms\Media';
	
	/**
	 * @brief	[Node] Show forms modally?
	 */
	public static $modalForms = TRUE;

	/**
	 * @brief	[Node] Restrictions
	 */
	protected static $restrictions = array(
 		'app'		=> 'cms',
 		'module'	=> 'pages',
 		'all'		=> 'media_manage',
 		'prefix'	=> 'media_'
	);
	
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
	 * Get sortable name
	 *
	 * @return	string
	 */
	public function getSortableName()
	{
		return $this->name;
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
			$buttons['add']['title'] = 'cms_add_media_folder';
			$buttons['add']['data']  = array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('cms_add_media_folder') );
			$buttons['add']['link']	 = $url->setQueryString( array( 'subnode' => 0, 'do' => 'form', 'parent' => $this->_id ) );
			
			$buttons['add_page'] = array(
					'icon'	=> 'plus-circle',
					'title'	=> 'cms_add_media',
					'link'	=> $url->setQueryString( array( 'subnode' => 1, 'do' => 'add', 'parent' => $this->_id ) ),
					'data'  => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('cms_add_media') )
			);
		}
		
		/* Re-arrange */
		if ( isset( $buttons['edit'] ) )
		{
			$return['edit'] = $buttons['edit'];
		}
		
		if ( isset( $buttons['add_page'] ) )
		{
			$return['add_page'] = $buttons['add_page'];
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
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		/* Build form */
		$form->add( new \IPS\Helpers\Form\Text( 'media_folder_name', $this->id ? $this->name : '', TRUE, array( 'maxLength' => 64 ), function( $val )
		{
			try
			{
				$test = \IPS\cms\Media\Folder::load( \IPS\Http\Url\Friendly::seoTitle( $val ), 'media_folder_name' );

				if ( ! empty( \IPS\Request::i()->id ) and $test->id != \IPS\Request::i()->id )
				{
					throw new \InvalidArgumentException('content_folder_name_in_use');
				}
			}
			catch ( \OutOfRangeException $e )
			{
			}
		} ) );
		
		$form->add( new \IPS\Helpers\Form\Node( 'media_folder_parent', $this->parent ? $this->parent : 0, FALSE, array(
				'class'         => '\IPS\cms\Media\Folder',
				'zeroVal'         => 'node_no_parent',
				'permissionCheck' => function( $node )
				{
					if ( ! isset( \IPS\Request::i()->id ) )
					{
						return true;
					}

					if ( ! isset( \IPS\Request::i()->parent ) )
					{
						return $node->id != \IPS\Request::i()->id;
					}
				}
		) ) );
	}

	/**
	 * @brief	Original parent ID
	 */
	protected $origParentId;

	/**
	 * @brief	Original parent Name
	 */
	protected $origName;

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( ! $this->id )
		{
			$this->save();
		}
		
		$this->origParentId = $this->parent;
		$this->origName     = $this->name;
		
		if ( isset( $values['media_folder_parent'] ) AND ( ! empty( $values['media_folder_parent'] ) OR $values['media_folder_parent'] === 0 ) )
		{
			$values['parent'] = ( $values['media_folder_parent'] === 0 ) ? 0 : $values['media_folder_parent']->id;
			unset( $values['media_folder_parent'] );
		}
		
		if( isset( $values['media_folder_name'] ) )
		{
			$values['name'] = \IPS\Http\Url\Friendly::seoTitle( $values['media_folder_name'] );
			unset( $values['media_folder_name'] );
		}

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
		if ( $this->origParentId !== $values['parent'] OR $this->origName !== $values['name'] )
		{
			$this->resetPath( true );
		}
	}
	
	/**
	 * Resets the stored path
	 * 
	 * @param	boolean	$recursivelyCheck	Recursively reset up and down the tree
	 * @return	void
	 */
	public function resetPath( $recursivelyCheck=true )
	{
		$path = array();
		
		foreach( $this->parents() as $obj )
		{
			$path[] = $obj->name;
		}
		
		$this->path = ( \count( $path ) ) ? implode( '/', $path ) . '/' . $this->name : $this->name;
		
		/* Save path update */
		parent::save();
		
		/* Update media */
		\IPS\cms\Media::resetPath( $this->id );
		
		if ( $recursivelyCheck === true )
		{
			/* Fix children */
			foreach( $this->children( NULL, NULL, FALSE ) as $child )
			{
				$child->resetPath( false );
				$child->_recursivelyResetChildPaths();
			}
			
			/* Fix parents */
			foreach( $this->parents() as $parent )
			{
				$parent->resetPath( false );
			}
		}
	}
	
	/**
	 * Recurse through the node tree to reset kids
	 * 
	 * @return void
	 */
	protected function _recursivelyResetChildPaths()
	{
		foreach( $this->children( NULL, NULL, FALSE ) as $child )
		{
			$child->resetPath( false );
			$child->_recursivelyResetChildPaths();
		}
	}
}