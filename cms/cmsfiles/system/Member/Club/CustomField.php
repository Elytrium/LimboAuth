<?php
/**
 * @brief		Clubs Customer Field Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		3 Mar 2017
 */

namespace IPS\Member\Club;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Clubs Customer Field Node
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
	public static $databaseTable = 'core_clubs_fields';
	
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
	protected static $langKey = 'core_clubfield';
	
	/**
	 * @brief	[CustomField] Content database table
	 */
	protected static $contentDatabaseTable = 'core_clubs_fieldvalues';
	
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
		'module'	=> 'clubs',
		'all'		=> 'fields_manage'
	);
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'clubs_custom_fields';

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'core_clubfield_';
	
	/**
	 * @brief	[CustomField] Column Map
	 */
	public static $databaseColumnMap = array(
		'content'	=> 'extra',
		'not_null'	=> 'required',
	);
	
	/**
	 * @brief	[CustomField] Additional Field Toggles
	 */
	public static $additionalFieldToggles = array(
		'Checkbox'		=> array( 'f_filterable' ),
		'CheckboxSet'	=> array( 'f_filterable' ),
		'Radio'			=> array( 'f_filterable' ),
		'Select'		=> array( 'f_filterable' ),
		'YesNo'			=> array( 'f_filterable' ),
	);
	
	/**
	 * @brief	[CustomField] Editor Options
	 */
	public static $editorOptions = array( 'app' => 'core', 'key' => 'Clubs' );
	
	/**
	 * @brief	[CustomField] Upload Storage Extension
	 */
	public static $uploadStorageExtension = 'core_ClubField';
	
	/**
	 * Get fields
	 *
	 * @return	array
	 */
	public static function fields()
	{
		if ( !isset( \IPS\Data\Store::i()->clubFields ) )
		{		
			$fields = array();
			$filterable = FALSE;
			
			foreach ( \IPS\Db::i()->select( '*', 'core_clubs_fields', NULL, 'f_position' ) as $row )
			{
				$fields[ $row['f_id'] ] = $row;
				if ( $row['f_filterable'] and \in_array( $row['f_type'], array( 'Checkbox', 'CheckboxSet', 'Radio', 'Select', 'YesNo' ) ) )
				{
					$filterable = TRUE;
				}
			}
				
			\IPS\Data\Store::i()->clubFields = array( 'fields' => $fields, 'filterable' => $filterable );
		}
		
		return new \IPS\Patterns\ActiveRecordIterator( new \ArrayIterator( \IPS\Data\Store::i()->clubFields['fields'] ), 'IPS\Member\Club\CustomField' );
	}
	
	/**
	 * Get if there are any filterable fields
	 *
	 * @return	bool
	 */
	public static function areFilterableFields()
	{
		if ( !isset( \IPS\Data\Store::i()->clubFields ) )
		{
			static::fields();
		}
		return \IPS\Data\Store::i()->clubFields['filterable'];
	}
			
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		parent::form( $form );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'f_filterable', (bool) $this->filterable, FALSE, array(), NULL, NULL, NULL, 'f_filterable' ) );

		unset( $form->elements[''][1] );
		unset( $form->elements['']['pf_max_input'] );
		unset( $form->elements['']['pf_input_format'] );
		unset( $form->elements[''][2] );
		unset( $form->elements['']['pf_search_type'] );
		unset( $form->elements['']['pf_search_type_on_off'] );
		unset( $form->elements['']['pf_format'] );
	}
	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'clubFields' );

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if( array_key_exists( 'pf_allow_attachments', $values ) )
		{
			$values[ 'allow_attachments' ] = $values[ 'pf_allow_attachments' ];
			unset( $values[ 'pf_allow_attachments' ] );
		}

		return parent::formatFormValues( $values );
	}
}