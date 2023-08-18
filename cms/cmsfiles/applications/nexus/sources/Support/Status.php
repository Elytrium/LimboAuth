<?php
/**
 * @brief		Support Status Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		8 Apr 2014
 */

namespace IPS\nexus\Support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Status Model
 */
class _Status extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_support_statuses';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'status_';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
			
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'statuses';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'status_default_member', 'status_default_staff' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
		
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
		'all' 		=> 'statuses_manage'
	);

	/**
	 * Load Record
	 *
	 * @see		\IPS\Db::build
	 * @param	int|string	$id					ID
	 * @param	string		$idField			The database column that the $id parameter pertains to (NULL will use static::$databaseColumnId)
	 * @param	mixed		$extraWhereClause	Additional where clause(s) (see \IPS\Db::build for details)
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function load( $id, $idField=NULL, $extraWhereClause=NULL )
	{
		try
		{
			return parent::load( $id, $idField, $extraWhereClause );
		}
		catch ( \OutOfRangeException $e )
		{
			/* A default status (both user and staff) is required. If none are set as the fault (for example, bad data from upgrade), find or create one */
			if ( $id === TRUE and ( $idField === 'status_default_member' OR $idField === 'status_default_staff' ) and $extraWhereClause === NULL )
			{
				try
				{
					$return = parent::constructFromData( \IPS\Db::i()->select( '*', 'nexus_support_statuses' )->first() );
				}
				catch ( \UnderflowException $e )
				{
					$return = new static;
					$return->open		= 1;
					$return->log		= 1;
					$return->position	= 0;
				}
				
				$col = str_replace( 'status_', '', $idField );
				$return->$col = TRUE;
				$return->save();
				return $return;
			}
			
			throw $e;
		}
	}

	/**
	 * Statuses that the member can set
	 *
	 * @param	 \IPS\nexus\Support\Request|NULL	$request	If provided, the status of that request will be excluded
	 * @return	array
	 */
	public static function publicSetStatuses( \IPS\nexus\Support\Request $request = NULL )
	{
		$return = array();
		foreach ( parent::roots() as $status )
		{
			if ( \IPS\Member::loggedIn()->language()->checkKeyExists("nexus_status_{$status->id}_set") and ( $request === NULL or $request->status->id != $status->id ) )
			{
				$return[ $status->id ] = \IPS\Member::loggedIn()->language()->addToStack("nexus_status_{$status->id}_set");
			}
		}
		return $return;
	}
	
	/**
	 * Get name
	 *
	 * @return	string
	 */
	public function get__title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'nexus_status_' . $this->id . '_' . \IPS\Dispatcher::i()->controllerLocation );
	}
		
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->addHeader( 'internal_settings' );
				
		$form->add( new \IPS\Helpers\Form\Translatable( 'status_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => ( $this->id ? "nexus_status_{$this->id}_admin" : NULL ) ) ) );
		$defaults = array();
		if ( $this->id )
		{
			if ( $this->default_member )
			{
				$defaults[] = 'member';
			}
			if ( $this->default_staff )
			{
				$defaults[] = 'staff';
			}
		}
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'status_default', $defaults, FALSE, array( 'options' => array( 'member' => 'status_default_member', 'staff' => 'status_default_staff' ) ), function( $val ) use ( $defaults )
		{
			$diff = array_diff( $defaults, $val );
			if ( !empty( $diff ) )
			{
				throw new \DomainException( 'status_default_change' );
			}
		} ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'status_open', $this->open ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'status_assign', $this->assign ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'status_log', $this->log ) );
		$form->add( new \IPS\Helpers\Form\Color( 'status_color', $this->color ?: 'C04848' ) );
		$form->addHeader( 'public_settings' );
		$form->add( new \IPS\Helpers\Form\Translatable( 'status_public_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => ( $this->id ? "nexus_status_{$this->id}_front" : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'status_public_set', $this->id ? \IPS\Member::loggedIn()->language()->checkKeyExists("nexus_status_{$this->id}_set") : FALSE, FALSE, array( 'togglesOn' => array( 'status_public_set_text' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'status_public_set_text', NULL, FALSE, array( 'app' => 'nexus', 'key' => ( $this->id ? "nexus_status_{$this->id}_set" : NULL ) ), NULL, NULL, NULL, 'status_public_set_text' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'status_is_locked', $this->id ? !$this->is_locked : FALSE ) );
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
		
		if( isset( $values['status_name'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_status_{$this->id}_admin", $values['status_name'] );
			unset( $values['status_name'] );
		}

		if( isset( $values['status_public_name'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_status_{$this->id}_front", $values['status_public_name'] );
			unset( $values['status_public_name'] );
		}

		if( isset( $values['status_public_set'] ) )
		{
			if ( $values['status_public_set'] )
			{
				\IPS\Lang::saveCustom( 'nexus', "nexus_status_{$this->id}_set", $values['status_public_set_text'] );
			}
			else
			{
				\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_app=? AND word_key=?', 'nexus', "nexus_status_{$this->id}_set" ) );
			}
			unset( $values['status_public_set_text'] );
		}
		
		if( isset( $values['status_is_locked'] ) )
		{
			$values['status_is_locked'] = !$values['status_is_locked'];
		}
		
		if( isset( $values[ "status_member" ] ) OR isset( $values['status_staff'] ) )
		{
			foreach ( array( 'member', 'staff' ) as $k )
			{
				if ( isset( $values[ "status_{$k}" ] ) and \in_array( $k, $values[ "status_{$k}" ] ) )
				{
					$values["default_{$k}"] = TRUE;
					\IPS\Db::i()->update( 'nexus_support_statuses', array( 'default_member' => 0 ) );
				}
				else
				{
					$values["default_{$k}"] = FALSE;
				}
			}
		}
		
		if( isset( $values['status_default'] ) )
		{
			if ( \in_array( 'member', $values['status_default'] ) )
			{
				$this->default_member = TRUE;
				\IPS\Db::i()->update( 'nexus_support_statuses', array( 'status_default_member' => 0 ) );
			}
			if ( \in_array( 'staff', $values['status_default'] ) )
			{
				$this->default_staff = TRUE;
				\IPS\Db::i()->update( 'nexus_support_statuses', array( 'status_default_staff' => 0 ) );
			}
			unset( $values['status_default'] );
		}
		
		if( isset( $values['status_color'] ) )
		{
			$values['status_color'] = ltrim( $values['status_color'], '#' );
		}
						
		return $values;
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
		if ( isset( $buttons['delete'] ) and \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_requests', array( 'r_status=?', $this->id ) )->first() )
		{
			$buttons['delete']['data'] = array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('delete') );
		}
		
		return $buttons;
	}
	
	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		$oldId = $this->_id;
		
		parent::__clone();
		
		\IPS\Lang::saveCustom( 'nexus', "nexus_status_{$this->id}_admin", iterator_to_array( \IPS\Db::i()->select( 'CONCAT(word_custom, \' ' . \IPS\Member::loggedIn()->language()->get('copy_noun') . '\') as word_custom, lang_id', 'core_sys_lang_words', array( 'word_key=?', "nexus_status_{$oldId}_admin" ) )->setKeyField( 'lang_id' )->setValueField('word_custom') ) );
		\IPS\Lang::saveCustom( 'nexus', "nexus_status_{$this->id}_front", iterator_to_array( \IPS\Db::i()->select( 'word_custom, lang_id', 'core_sys_lang_words', array( 'word_key=?', "nexus_status_{$oldId}_front" ) )->setKeyField( 'lang_id' )->setValueField('word_custom') ) );
		\IPS\Lang::saveCustom( 'nexus', "nexus_status_{$this->id}_set", iterator_to_array( \IPS\Db::i()->select( 'word_custom, lang_id', 'core_sys_lang_words', array( 'word_key=?', "nexus_status_{$oldId}_set" ) )->setKeyField( 'lang_id' )->setValueField('word_custom') ) );		
	}

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();

		\IPS\Lang::deleteCustom( 'nexus', "nexus_status_{$this->id}_admin" );
		\IPS\Lang::deleteCustom( 'nexus', "nexus_status_{$this->id}_front" );
		\IPS\Lang::deleteCustom( 'nexus', "nexus_status_{$this->id}_set" );
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse		int		id		ID number
	 * @apiresponse		string	name	Name
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'id'			=> $this->_id,
			'publicName'	=> \IPS\Member::loggedIn()->language()->addToStack( 'nexus_status_' . $this->id . '_front' ),
			'internalName'	=> \IPS\Member::loggedIn()->language()->addToStack( 'nexus_status_' . $this->id . '_admin' )
		);
	}
}