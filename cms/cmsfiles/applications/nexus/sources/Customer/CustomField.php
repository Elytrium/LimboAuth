<?php
/**
 * @brief		Custom Customer Field Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		16 Apr 2013
 */

namespace IPS\nexus\Customer;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Custom Profile Field Node
 */
class _CustomField extends \IPS\CustomField
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_customer_fields';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'f_';
		
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[CustomField] Title/Description lang prefix
	 */
	protected static $langKey = 'nexus_ccfield';
	
	/**
	 * @brief	[CustomField] Content database table
	 */
	protected static $contentDatabaseTable = 'nexus_customers';
	
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
		'module'	=> 'customers',
		'prefix'	=> 'customer_fields_'
	);
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'nexus_customer_fields';

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'nexus_ccfield_';
	
	/**
	 * @brief	[CustomField] Column Map
	 */
	public static $databaseColumnMap = array(
		'content'	=> 'extra',
		'not_null'	=> 'reg_require'
	);
	
	/**
	 * @brief	[CustomField] Editor Options
	 */
	public static $editorOptions = array( 'app' => 'nexus', 'key' => 'Customer' );
	
	/**
	 * @brief	[CustomField] Upload Storage Extension
	 */
	public static $uploadStorageExtension = 'nexus_Customer';
			
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		parent::form( $form );
		
		if ( \IPS\Login::registrationType() == 'full' )
		{
			/* Quick register is disabled */
			$form->addHeader( 'customer_field_registration' );
			$form->add( new \IPS\Helpers\Form\YesNo( 'f_reg_show', $this->reg_show ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'f_reg_require', $this->reg_require ) );
		}
		
		$form->addHeader( 'customer_field_purchase' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'f_purchase_show', $this->purchase_show ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'f_purchase_require', $this->purchase_require ) );

		unset( $form->elements[''][1] );
		unset( $form->elements['']['pf_not_null'] );
		unset( $form->elements['']['pf_max_input'] );
		unset( $form->elements['']['pf_input_format'] );
		unset( $form->elements[''][2] );
		unset( $form->elements['']['pf_search_type'] );
		unset( $form->elements['']['pf_search_type_on_off'] );
		unset( $form->elements['']['pf_format'] );
	}

	/**
	 * [Node] Perform actions after saving the form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function postSaveForm( $values )
	{
		if ( !$this->column )
		{
			$this->column = "field_{$this->id}";
			$this->save();
		}
	}
	
	/**
	 * [ActiveRecord] Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
		\IPS\Widget::deleteCaches( 'donations', 'nexus' );
		static::recountCustomerFields();
	}
	
	/**
	 * [ActiveRecord] Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();
		static::recountCustomerFields();

		/* Do we need to do stuff with profile steps? */
		\IPS\Member\ProfileStep::resync();
	}
	
	/**
	 * Recount card storage gateays
	 *
	 * @return	void
	 */
	protected static function recountCustomerFields()
	{
		$count = \count( static::roots() );
		\IPS\Settings::i()->changeValues( array( 'customer_fields' => $count ) );
	}

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		$values['allow_attachments']	= $values['pf_allow_attachments'];
		unset( $values['pf_allow_attachments'] );

		return parent::formatFormValues( $values );
	}

	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		parent::__clone();

		$this->column = "field_{$this->id}";
		$this->save();
	}
}