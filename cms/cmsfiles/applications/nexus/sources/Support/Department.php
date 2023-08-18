<?php
/**
 * @brief		Support Department Model
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
 * Support Department Model
 */
class _Department extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_support_departments';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'dpt_';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
			
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'departments';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'dpt_email' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'nexus_department_';

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
		'all' 		=> 'departments_manage'
	);
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	protected function setDefaultValues()
	{
		$this->ppi = '*';
	}
	
	/**
	 * Get the permission array used to get the departments that a staff member has access to
	 *
	 * @param	\IPS\Member|NULL	$member	The staff member
	 * @return	array|NULL			NULL indicates all departments
	 */
	public static function staffDepartmentPerms( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$return = array( "m{$member->member_id}" );
		foreach ( $member->groups as $groupId )
		{
			$return[] = "g{$groupId}";
		}
		return $return;
	}
	
	/**
	 * Cache for departmentsWithPermission()
	 */
	protected static $departmentsWithPermission = array();
	
	/**
	 * Departments that a staff member has access to
	 *
	 * @param	\IPS\Member|NULL	$member	The staff member
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public static function departmentsWithPermission( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !isset( static::$departmentsWithPermission[ $member->member_id ] ) )
		{
			static::$departmentsWithPermission[ $member->member_id ] = new \IPS\Patterns\ActiveRecordIterator(
				\IPS\Db::i()->select( '*', 'nexus_support_departments', array( "( dpt_staff='*' OR " . \IPS\Db::i()->findInSet( 'dpt_staff', \IPS\nexus\Support\Department::staffDepartmentPerms( $member ) ) . ')' ) )->setKeyField( 'dpt_id' ),
				'IPS\nexus\Support\Department'
			);
		}
		return static::$departmentsWithPermission[ $member->member_id ];
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->addHeader('department_settings');
		$form->add( new \IPS\Helpers\Form\Translatable( 'dpt_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => ( $this->id ? "nexus_department_{$this->id}" : NULL ) ) ) );
		
		/* PHP 5.3 does not allow the use of $this in anonymous functions */
		$obj = &$this;
		$form->add( new \IPS\Helpers\Form\Email( 'dpt_email', $this->email, FALSE, array( 'placeholder' => 'support@example.com' ), function ( $val ) use ( $obj )
		{
			if ( $val )
			{
				try
				{
					$other = \IPS\Db::i()->select( '*', 'nexus_support_departments', array( 'dpt_email=? AND dpt_id<>?', $val, $obj->id ?: 0 ) )->first();
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'dpt_email_error', FALSE, array( 'sprintf' => array( \IPS\nexus\Support\Department::constructFromData( $other)->_title ) ) ) );
				}
				catch ( \UnderflowException $e ) { }
			}
		} ) );
		$form->addHeader( 'department_submissions' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'dpt_open', $this->id ? $this->open : TRUE, FALSE, array( 'togglesOn' => array( 'dpt_desc_editor', 'dpt_ppi' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'dpt_desc', NULL, FALSE, array(
			'app'		=> 'nexus',
			'key'		=> ( $this->id ? "nexus_department_{$this->id}_desc" : NULL ),
			'editor'	=> array(
				'app'			=> 'nexus',
				'key'			=> 'Admin',
				'autoSaveKey'	=> ( $this->id ? "nexus-dpt-{$this->id}" : "nexus-new-dpt" ),
				'attachIds'		=> $this->id ? array( $this->id, NULL, 'dpt' ) : NULL, 'minimize' => 'dpt_desc_placeholder'
			)
		), NULL, NULL, NULL, 'dpt_desc_editor' ) );
		$form->add( new \IPS\nexus\Form\Money( 'dpt_ppi', ( !$this->ppi or $this->ppi === '*' ) ? '*' : json_decode( $this->ppi, TRUE ), FALSE, array( 'unlimitedLang' => 'no_charge', 'unlimitedTogglesOff' => array( 'dpt_ppi_tax' ) ), NULL, NULL, NULL, 'dpt_ppi' ) );
		$form->add( new \IPS\Helpers\Form\Node( 'dpt_ppi_tax', $this->ppi_tax ?: 0, FALSE, array( 'class' => 'IPS\nexus\Tax', 'zeroVal' => 'do_not_tax' ), NULL, NULL, NULL, 'dpt_ppi_tax' ) );
		$form->add( new \IPS\Helpers\Form\Node( 'dpt_packages', $this->packages ? array_filter( array_map( function( $val )
		{
			try
			{
				return \IPS\nexus\Package::load( $val );
			}
			catch ( \OutOfRangeException $e )
			{
				return NULL;
			}
		}, explode( ',', $this->packages ) ) ) : 0, FALSE, array( 'zeroVal' => 'do_not_associate_requests', 'multiple' => TRUE, 'class' => 'IPS\nexus\Package\Group', 'zeroValTogglesOff' => array( 'dpt_require_package' ), 'permissionCheck' => function( $node )
		{
			return !( $node instanceof \IPS\nexus\Package\Group );
		} ) ) );

		if ( \IPS\Settings::i()->nexus_subs_enabled )
        {
            $form->add( new \IPS\Helpers\Form\Node( 'dpt_subscriptions', $this->subscriptions ? array_filter( array_map( function ( $val ) {
                try
                {
                    return \IPS\nexus\Subscription\Package::load( $val );
                } catch ( \OutOfRangeException $e )
                {
                    return NULL;
                }
            }, explode( ',', $this->subscriptions ) ) ) : 0, FALSE, array('zeroVal' => 'do_not_associate_sub_requests', 'multiple' => TRUE, 'class' => 'IPS\nexus\Subscription\Package', 'zeroValTogglesOff' => array('dpt_require_package') ) ) );
        }

		$form->add( new \IPS\Helpers\Form\YesNo( 'dpt_require_package', $this->require_package, FALSE, array(), NULL, NULL, NULL, 'dpt_require_package' ) );		
		
		$form->addHeader( 'department_staff' );
		$admins = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_admin_permission_rows' ) as $row )
		{
			if ( $row['row_id_type'] === 'group' )
			{
				$admins[ 'dpt_staff_group' ][ 'g' . $row['row_id'] ] = \IPS\Member\Group::load( $row['row_id'] )->name;
			}
			else
			{
				$admins[ 'dpt_staff_member' ][ 'm' . $row['row_id'] ] = \IPS\Member::load( $row['row_id'] )->name;
			}
		}
		$form->add( new \IPS\Helpers\Form\Select( 'dpt_staff', ( $this->id and $this->staff !== '*' ) ? explode( ',', $this->staff ) : '*', TRUE, array( 'options' => $admins, 'multiple' => TRUE, 'unlimited' => '*', 'parse' => 'normal' ) ) );
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
			\IPS\File::claimAttachments( 'nexus-new-dpt', $this->id, NULL, 'dpt', TRUE );
		}
		
		if ( array_key_exists( 'dpt_name', $values ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_department_{$this->id}", $values['dpt_name'] );
			unset( $values['dpt_name'] );
		}
		
		if ( array_key_exists( 'dpt_desc', $values ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_department_{$this->id}_desc", $values['dpt_desc'] );
		}
		unset( $values['dpt_desc'] );

		if ( array_key_exists( 'dpt_ppi', $values ) )
		{
			$values['dpt_ppi'] = $values['dpt_ppi'] === '*' ? '*' : json_encode( $values['dpt_ppi'] );
		}

		if ( array_key_exists( 'dpt_staff', $values ) )
		{
			$values['dpt_staff'] = $values['dpt_staff'] === '*' ? '*' : implode( ',', $values['dpt_staff'] );
		}
		
		if ( array_key_exists( 'dpt_ppi_tax', $values ) )
		{
			$values['dpt_ppi_tax'] = $values['dpt_ppi_tax'] ? $values['dpt_ppi_tax']->id : NULL;
		}

		if ( array_key_exists( 'dpt_packages', $values ) )
		{
			$values['dpt_packages'] = \is_array( $values['dpt_packages'] ) ? implode( ',', array_map( function( $val )
			{
				return ltrim( $val, 's' );
			}, array_keys( $values['dpt_packages'] ) ) ) : 0;
		}

        if ( array_key_exists( 'dpt_subscriptions', $values ) )
        {
            $values['dpt_subscriptions'] = \is_array( $values['dpt_subscriptions'] ) ? implode( ',', array_keys( $values['dpt_subscriptions'] ) ) : 0;
        }
        else
        {
		    $values['dpt_subscriptions'] = 0;
        }

		if ( ( array_key_exists( 'dpt_require_package', $values ) AND \is_null( $values['dpt_require_package'] ) ) or ( $values['dpt_packages'] == 0 and $values['dpt_subscriptions'] == 0 ) )
		{
			$values['dpt_require_package'] = 0;
		}
						
		return $values;
	}
	
	/**
	 * Get serialised package IDs
	 *
	 * @return	string
	 */
	public function serializedPackageIds()
	{
		if ( $this->packages and $this->packages !== '*' )
		{
			$allowedPackages = explode( ',', $this->packages );
			sort( $allowedPackages );
			$allowedPackages = array_filter( $allowedPackages );
			$allowedPackages = array_unique( $allowedPackages );
			return md5( implode( ',', $allowedPackages ) );
		}
		else
		{
			return '*';
		}
	}
	
	/**
	 * @brief	Available severities
	 */
	protected $_availableSeverities = NULL;
	
	/**
	 * Get available severities
	 *
	 * @return	array
	 */
	public function availableSeverities()
	{
		if ( $this->_availableSeverities === NULL )
		{
			$this->_availableSeverities = array();
			
			foreach ( Severity::roots( NULL, NULL, array( 'sev_public=1' ) ) as $severity )
			{
				if ( $severity->public )
				{
					if ( !$severity->departments or $severity->departments === '*' or \in_array( $this->id, explode( ',', $severity->departments )  ) )
					{
						$this->_availableSeverities[] = $severity;
					}
				}
			}
		}
		
		return $this->_availableSeverities;
	}
	
	/**
	 * Get serialised severity IDs
	 *
	 * @return	string
	 */
	public function serializedSeverityIds()
	{
		$allowedSeverityIds = array_keys( $this->availableSeverities() );
		sort( $allowedSeverityIds );
		$allowedSeverityIds = array_filter( $allowedSeverityIds );
		$allowedSeverityIds = array_unique( $allowedSeverityIds );
		return md5( implode( ',', $allowedSeverityIds ) );
	}
	
	/**
	 * Get custom fields
	 *
	 * @return	array
	 */
	public function customFields()
	{
		return CustomField::roots( NULL, NULL, "sf_departments='*' OR " . \IPS\Db::i()->findInSet( 'sf_departments', array( $this->id ) ) );
	}
	
	/**
	 * Pay-Per-Incident Cost
	 *
	 * @param	string|NULL	$currency	Desired currency (NULL for the current user's preference)
	 * @return	\IPS\nexus\Money|NULL
	 */
	public function ppiCost( $currency = NULL )
	{
		if ( $this->ppi and $this->ppi !== '*' )
		{
			$costs = json_decode( $this->ppi, TRUE );

			if( isset( $costs[ $currency ?: \IPS\nexus\Customer::loggedIn()->defaultCurrency() ] ) AND $costs[ $currency ?: \IPS\nexus\Customer::loggedIn()->defaultCurrency() ]['amount'] )
			{
				$cost = $costs[ $currency ?: \IPS\nexus\Customer::loggedIn()->defaultCurrency() ];
				return new \IPS\nexus\Money( $cost['amount'], $cost['currency'] );
			}
		}
		return NULL;
	}
	
	/**
	 * [ActiveRecord] Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		foreach( \IPS\Db::i()->select( '*', 'nexus_support_fields', array( \IPS\Db::i()->findInSet( 'sf_departments', array( $this->id ) ) ) ) AS $field )
		{
			$newValue = array();
			foreach( explode( ',', $field['sf_departments'] ) AS $dpt )
			{
				if ( $dpt == $this->id )
				{
					continue;
				}
				
				$newValue[] = $dpt;
			}
			
			\IPS\Db::i()->update( 'nexus_support_fields', array( 'sf_departments' => implode( ',', $newValue ) ), array( "sf_id=?", $field['sf_id'] ) );
		}
		
		parent::delete();
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
		if ( isset( $buttons['delete'] ) and \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_requests', array( 'r_department=?', $this->id ) ) )
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