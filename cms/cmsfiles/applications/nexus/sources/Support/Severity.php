<?php
/**
 * @brief		Support Severity Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		9 Apr 2014
 */

namespace IPS\nexus\Support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Severity Model
 */
class _Severity extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_support_severities';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'sev_';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
			
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'severities';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'sev_default' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'nexus_severity_';

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
		'module'	=> 'support',
		'all' 		=> 'severities_manage'
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
			/* Severity is required. If none are set as the fault (for example, bad data from upgrade), find or create one */
			if ( $id === TRUE and $idField === 'sev_default' and $extraWhereClause === NULL )
			{
				try
				{
					$return = parent::constructFromData( \IPS\Db::i()->select( '*', 'nexus_support_severities' )->first() );
				}
				catch ( \UnderflowException $e )
				{
					$return = new static;
					$return->id = 1;
					$return->color = '000';
					$return->public = TRUE;
					$return->position = 1;
					$return->departments = '*';
				}
				
				$return->default = TRUE;
				$return->save();
				return $return;
			}
			
			throw $e;
		}
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->addHeader( 'severity_settings' );
		$form->add( new \IPS\Helpers\Form\Translatable( 'sev_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => ( $this->id ? "nexus_severity_{$this->id}" : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'sev_default', $this->default, FALSE, array( 'disabled' => $this->default ) ) );
		$form->addHeader( 'severity_submissions' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'sev_public', $this->public, FALSE, array( 'togglesOn' => array( 'sev_departments', 'sev_desc' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'sev_departments', ( $this->departments and $this->departments !== '*' ) ? explode( ',', $this->departments ) : 0, FALSE, array( 'class' => 'IPS\nexus\Support\Department', 'multiple' => TRUE, 'zeroVal' => 'all' ), NULL, NULL, NULL, 'sev_departments' ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'sev_desc', NULL, FALSE, array(
			'app'		=> 'nexus',
			'key'		=> ( $this->id ? "nexus_severity_{$this->id}_desc" : NULL ),
			'textArea'	=> TRUE
		), NULL, NULL, NULL, 'sev_desc' ) );
		$form->addHeader( 'severity_acp_list' );
		$form->add( new \IPS\Helpers\Form\Color( 'sev_color', $this->color ?: '000' ) );
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
		
		if( isset( $values['sev_name'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_severity_{$this->id}", $values['sev_name'] );
			unset( $values['sev_name'] );
		}

		if( array_key_exists( 'sev_desc', $values ) )
		{
			if ( $values['sev_desc'] )
			{
				\IPS\Lang::saveCustom( 'nexus', "nexus_severity_{$this->id}_desc", $values['sev_desc'] );
			}
			else
			{
				\IPS\Lang::deleteCustom( 'nexus', "nexus_severity_{$this->id}_desc" );
			}
			
			unset( $values['sev_desc'] );
		}
		
		if ( isset( $values['sev_default'] ) AND $values['sev_default'] )
		{
			\IPS\Db::i()->update( 'nexus_support_severities', array( 'sev_default' => 0 ) );
		}
		
		if( isset( $values['sev_color'] ) )
		{
			$values['sev_color'] = ltrim( $values['sev_color'], '#' );
		}

		if( isset( $values['sev_departments'] ) )
		{
			$values['sev_departments'] = $values['sev_departments'] == 0 ? '*' : implode( ',', array_keys( $values['sev_departments'] ) );
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
		if ( isset( $buttons['delete'] ) and \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_requests', array( 'r_severity=?', $this->id ) ) )
		{
			$buttons['delete']['data'] = array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('delete') );
		}
		
		return $buttons;
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
			'id'	=> $this->_id,
			'name'	=> $this->_title
		);
	}
}