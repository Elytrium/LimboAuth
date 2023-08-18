<?php
/**
 * @brief		Support Stock Action Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		15 Apr 2014
 */

namespace IPS\nexus\Support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Stock Action Model
 */
class _StockAction extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_support_stock_actions';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'action_';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
			
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'stock_actions';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'nexus_stockaction_';
		
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
		'module'	=> 'support',
		'all' 		=> 'stockactions_manage'
	);
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->status = NULL;
		$this->department = NULL;
		$this->staff = NULL;
	}
	
	/**
	 * Get department
	 *
	 * @return	\IPS\nexus\Support\Department|NULL
	 */
	public function get_department()
	{
		try
		{
			return $this->_data['department'] ? Department::load( $this->_data['department'] ) : NULL;
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set department
	 *
	 * @param	\IPS\nexus\Support\Department|NULL	$department	The department
	 * @return	void
	 */
	public function set_department( Department $department = NULL )
	{
		$this->_data['department'] = $department ? $department->id : 0;
	}
			
	/**
	 * Get status
	 *
	 * @return	\IPS\nexus\Support\Status|NULL
	 */
	public function get_status()
	{
		try
		{
			return $this->_data['status'] ? Status::load( $this->_data['status'] ) : NULL;
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set purchase
	 *
	 * @param	\IPS\nexus\Support\Status|NULL	$status	The status
	 * @return	void
	 */
	public function set_status( Status $status = NULL )
	{
		$this->_data['status'] = $status ? $status->id : 0;
	}
		
	/**
	 * Get staff
	 *
	 * @return	\IPS\Member|NULL
	 */
	public function get_staff()
	{
		try
		{
			return $this->_data['staff'] ? \IPS\Member::load( $this->_data['staff'] ) : NULL;
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}

	/**
	 * Set staff
	 *
	 * @param	\IPS\Member|NULL	$member	The staff member to assign to
	 * @return	void
	 */
	public function set_staff( \IPS\Member $member = NULL )
	{
		$this->_data['staff'] = $member ? $member->member_id : 0;
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Translatable( 'action_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => ( $this->id ? "nexus_stockaction_{$this->id}" : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'action_status', $this->status ?: 0, FALSE, array( 'class' => 'IPS\nexus\Support\Status', 'zeroVal' => 'do_not_change' ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'action_department', $this->department ?: 0, FALSE, array( 'class' => 'IPS\nexus\Support\Department', 'zeroVal' => 'do_not_move' ) ) );
		$form->add( new \IPS\Helpers\Form\Select( 'action_staff', $this->staff ? $this->staff->member_id : 0, FALSE, array( 'parse' => 'normal', 'options' => array( 0 => \IPS\Member::loggedIn()->language()->addToStack( 'do_not_change' ) ) + \IPS\nexus\Support\Request::staff() ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'action_message_on', $this->message, FALSE, array( 'togglesOn' => array( 'action_message_editor' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'action_message', $this->message, FALSE, array( 'app' => 'nexus', 'key' => 'Support', 'autoSaveKey' => md5( 'stockaction-' . ( $this->id ?: 'new' ) ) ), NULL, NULL, NULL, 'action_message_editor' ) );
		$form->add( new \IPS\Helpers\Form\Node( 'action_show_in', ( !$this->show_in or $this->show_in === '*' ) ? 0 : explode( ',', $this->show_in ), FALSE, array( 'class' => 'IPS\nexus\Support\Department', 'zeroVal' => 'all', 'multiple' => TRUE ) ) );
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
		
		if( isset( $values['action_name'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_stockaction_{$this->id}", $values['action_name'] );
			unset( $values['action_name'] );
		}
		
		if ( isset( $values['action_message_on'] ) AND !$values['action_message_on'] )
		{
			$values['action_message'] = NULL;
		}
		unset( $values['action_message_on'] );
		
		foreach ( array( 'status', 'department' ) as $k )
		{
			if( isset( $values["action_{$k}"] ) )
			{
				$values["action_{$k}"] = $values["action_{$k}"] ?: NULL;
			}
		}
		
		if( isset( $values['action_staff'] ) )
		{
			$values['action_staff'] = $values['action_staff'] ? \IPS\Member::load( $values['action_staff'] ) : NULL;
		}
		
		if( isset( $values['action_show_in'] ) )
		{
			$values['action_show_in'] = $values['action_show_in'] ? implode( ',', array_keys( $values['action_show_in'] ) ) : '*';
		}
		
		return $values;
	}
}