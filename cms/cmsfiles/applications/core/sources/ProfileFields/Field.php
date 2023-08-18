<?php
/**
 * @brief		Custom Profile Field Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Apr 2013
 */

namespace IPS\core\ProfileFields;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Custom Profile Field Node
 */
class _Field extends \IPS\CustomField
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Access level constants
	 */
	const PROFILE 	= 0;
	const REG		= 1;
	const STAFF		= 2;
	const CONTENT	= 3;
	const SEARCH	= 4;
	const EDIT		= 5;
	const PROFILE_COMPLETION = 6;

	const PII_DATA_EXPORT = 7;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_pfields_data';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'pf_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[Node] Parent Node ID Database Column
	 */
	public static $parentNodeColumnId = 'group_id';
	
	/**
	 * @brief	[Node] Parent Node Class
	 */
	public static $parentNodeClass = 'IPS\core\ProfileFields\Group';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[CustomField] Title/Description lang prefix
	 */
	protected static $langKey = 'core_pfield';
	
	/**
	 * @brief	[Node] ACP Restrictions
	 */
	protected static $restrictions = array(
		'app'		=> 'core',
		'module'	=> 'membersettings',
		'prefix'	=> 'profilefields_',
	);

	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'profile_field';
	
	/**
	 * @brief	[CustomField] Editor Options
	 */
	public static $editorOptions = array( 'app' => 'core', 'key' => 'CustomField' );

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'core_pfield_';
	
	/**
	 * @brief	[CustomField] FileStorage Extension for Upload fields
	 */
	public static $uploadStorageExtension = 'core_ProfileField';
	
	/**
	 * Display Value
	 *
	 * @param	mixed	$value						The value
	 * @param	bool	$showSensitiveInformation	If TRUE, potentially sensitive data (like passwords) will be displayed - otherwise will be blanked out
	 * @param	int		$location					\IPS\core\ProfileFields\Field::PROFILE for profile, \IPS\core\ProfileFields\Field::REG for registration screen, \IPS\core\ProfileFields\Field::STAFF for ModCP/ACP or \IPS\core\ProfileFields\Field::CONTENT for content areas (post bit)
	 * @param	\IPS\Member|NULL	$member			Member who completed the profile field		
	 * @return	string
	 */
	public function displayValue( $value=NULL, $showSensitiveInformation=FALSE, $location=0, \IPS\Member $member = null, $bypassCustomFormatting=FALSE, $separator=NULL )
	{
		$formattedValue = parent::displayValue( $value, $showSensitiveInformation, $separator );
		$member			= $member ?: new \IPS\Member;

		switch( $location )
		{
			case ( static::CONTENT ):
				/* If there's no value, return nothing */
				if( empty( $formattedValue ) )
				{
					return '';
				}

				/* If we are using the "default" formatting, set that up now */
				$format = $this->format ?: "<strong>{\$title}:</strong> {\$processedContent}";

				return $this->parseHtmlLogic( $format, $value, $formattedValue, $member );
			break;

			case ( static::PROFILE ):
			case ( static::PROFILE_COMPLETION ):
				if( $this->profile_format and !$bypassCustomFormatting )
				{
					return $this->parseHtmlLogic( $this->profile_format, $value, $formattedValue, $member );
				}
				else
				{
					return $formattedValue;
				}
			break;

			case ( static::STAFF ) :
			default:
					return ( $this->type == 'Editor' ) ? \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->richText( $formattedValue ) : $formattedValue;
			break;
		}
	}

	/**
	 * Parse the HTML logic to format the field
	 *
	 * @param	string	$template	Format (which may include HTML logic)
	 * @param	string	$rawValue	Raw value
	 * @param	string	$value		Formatted value
	 * @param	\IPS\Member	$member	Member who completed the profile field
	 * @return	string
	 */
	protected function parseHtmlLogic( $template, $rawValue, $value, \IPS\Member $member )
	{
		try
		{
			$functionName = 'profilefields_t_' .  md5( $template );
			
			if ( ! isset( \IPS\Data\Store::i()->$functionName ) )
			{
				/* We need the "raw" content because HTML is typically used to format the value */
				$template = str_replace( '{$processedContent}', '{$processedContent|raw}', $template );

				/* If this an editor, we can safely use the |raw value as it is clean, otherwise the escaped value will display which is not what the user expects */
				if ( $this->type == 'Editor' )
				{
					$template = str_replace( '{$content}', '{$content|raw}', $template );
					$template = \IPS\Theme::i()->getTemplate('global', 'core', 'global')->richText( $template );
				}

				/* $content is the raw content from the database, $processedContent is the parsed content ready for display */
				\IPS\Data\Store::i()->$functionName = \IPS\Theme::compileTemplate( $template, $functionName, '$title, $content, $processedContent, $member, $member_id', true );
			}

			\IPS\Theme::runProcessFunction( \IPS\Data\Store::i()->$functionName, $functionName );

			$themeFunction = 'IPS\\Theme\\'. $functionName;
			$html = $themeFunction( \IPS\Member::loggedIn()->language()->addToStack( static::$langKey . '_' . $this->id ), $rawValue, $value, $member, $member->member_id );

			return $html;
		}
		catch ( \ParseError $e )
		{
			@ob_end_clean();
			\IPS\Log::log( $e, 'pfield_error' );
			return $value;
		}
	}
	
	/**
	 * Get field data
	 *
	 * @return	array
	 */
	public static function fieldData()
	{
		if ( !isset( \IPS\Data\Store::i()->profileFields ) )
		{		
			$fields = array();
			$display = FALSE;
			
			foreach ( \IPS\Db::i()->select( '*', 'core_pfields_groups', NULL, 'pf_group_order' ) as $row )
			{
				$fields[ $row['pf_group_id'] ] = array();
			}
	
			foreach ( \IPS\Db::i()->select( '*', 'core_pfields_data', NULL, 'pf_position' ) as $row )
			{
				$fields[ $row['pf_group_id'] ][ $row['pf_id'] ] = $row;

				if ( $row['pf_topic_hide'] != 'hide' )
				{
					$display = TRUE;
				}
			}
			
			\IPS\Data\Store::i()->profileFields = array( 'fields' => $fields, 'display' => $display );
		}
		
		return \IPS\Data\Store::i()->profileFields['fields'];
	}
	
	/**
	 * Are there fields to display in content view?
	 *
	 * @return	bool
	 */
	public static function fieldsForContentView()
	{
		if ( !isset( \IPS\Data\Store::i()->profileFields ) )
		{
			static::fieldData();
		}
		return \IPS\Data\Store::i()->profileFields['display'];
	}
	
	/**
	 * Get Fields
	 *
	 * @param	array				$values		Current values
	 * @param	int					$location	\IPS\core\ProfileFields\Field::PROFILE for profile, \IPS\core\ProfileFields\Field::REG for registration screen, \IPS\core\ProfileFields\Field::STAFF for ModCP/ACP, \IPS\core\ProfileFields\Field::EDIT for member editing
	 * @param	\IPS\Member|NULL	$member		IPS Member Object
	 * @return	array
	 */
	public static function fields( $values=array(), $location=0, ?\IPS\Member $member = null ): array
	{
		if( !$values )
		{
			$values = array();
		}

		$return = array();

		foreach ( static::fieldData() as $groupId => $fields )
		{
			foreach ( $fields as $row )
			{
				if ( 
						( $location === static::PROFILE and $row['pf_member_hide'] == 'hide' ) or
						( $location === static::SEARCH and ( !$row['pf_search_type'] or $row['pf_member_hide'] == 'hide' ) ) or
						( $location === static::REG and !$row['pf_show_on_reg'] ) or
						( ( $location === static::EDIT OR $location === static::PROFILE_COMPLETION ) and !$row['pf_member_edit'] ) or
						( $location === static::PII_DATA_EXPORT and !$row['pf_contains_pii'] )
				)
				{
					continue;
				}
	
				if ( !array_key_exists( 'field_' . $row['pf_id'], $values ) )
				{
					$values['field_' . $row['pf_id'] ] = NULL;
				}

				static::$editorOptions['autoSaveKey'] = md5( \get_called_class() . '-' . $row['pf_id'] ) . ( $member ? '-' . $member->member_id : '' );

				if( $row['pf_type'] == 'Editor' AND $member )
				{
					static::$editorOptions['attachIds'] = array( $member->member_id, $row['pf_id'] );
				}
				
				if ( $location === static::STAFF )
				{
					$row['pf_not_null'] = 0;
				}
				
				$return[ $groupId ][ $row['pf_id'] ] = static::constructFromData( $row )->buildHelper( $values[ 'field_' . $row['pf_id'] ] );
			}
		}
				
		return $return;
	}

	/**
	 * Load Record with Member
	 *
	 * @see		\IPS\Db::build
	 * @param	int|string			$id					ID
	 * @param	string				$idField			The database column that the $id parameter pertains to (NULL will use static::$databaseColumnId)
	 * @param	mixed				$extraWhereClause	Additional where clause(s) (see \IPS\Db::build for details)
	 * @param	\IPS\Member|NULL	$member				IPS Member Object
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function loadWithMember( $id, $idField=NULL, $extraWhereClause=NULL, \IPS\Member $member = NULL ): self
	{
		$result = parent::load( $id, $idField, $extraWhereClause );

		static::$editorOptions['autoSaveKey'] = md5( \get_called_class() . '-' . $result->id ) . ( $member ? '-' . $member->member_id : '' );

		if( $result->type == 'Editor' AND $member )
		{
			static::$editorOptions['attachIds'] = array( $member->member_id, $result->id );
		}

		return $result;
	}

	/**
	 * Get Values
	 *
	 * @param	array		$values		Current values
	 * @param	int			$location	\IPS\core\ProfileFields\Field::PROFILE for profile, \IPS\core\ProfileFields\Field::REG for registration screen or \IPS\core\ProfileFields\Field::STAFF for ModCP/ACP
	 * @param	bool		$raw		Returns the raw value if true or the display value if false. Useful for comparisons for field types like Yes/NO to see if a value is set.
	 * @return	array
	 */
	public static function values( $values, $location=0, $raw=FALSE )
	{
		$return = array();
		foreach ( static::fieldData() as $groupId => $fields )
		{
			foreach ( $fields as $row )
			{
				/* Make sure we have permission to see the field */
				switch( $location )
				{
					case ( static::CONTENT ):
						if( $row['pf_topic_hide'] == 'hide' OR ( $row['pf_topic_hide'] == 'staff' AND !\IPS\Member::loggedIn()->isAdmin() AND !\IPS\Member::loggedIn()->modPermissions() ) )
						{
							continue 2;
						}
					break;

					case ( static::PROFILE ):
						if( $row['pf_member_hide'] == 'hide' OR ( $row['pf_member_hide'] == 'staff' AND !\IPS\Member::loggedIn()->isAdmin() AND !\IPS\Member::loggedIn()->modPermissions() ) OR ( $row['pf_member_hide'] == 'owner' AND !\IPS\Member::loggedIn()->isAdmin() AND !\IPS\Member::loggedIn()->modPermissions() AND \IPS\Member::loggedIn()->member_id != $values['member_id'] ) )
						{
							continue 2;
						}
					break;
					case ( static::PII_DATA_EXPORT ):
						if( !$row['pf_contains_pii'] )
						{
							continue 2;
						}
				}

				/* Next to user content we perform a couple of extra checks */
				if ( $location == static::CONTENT and ( !isset( $values[ 'field_' . $row['pf_id'] ] ) OR $row['pf_type'] == 'Poll' ) )
				{
					continue;
				}

				$return[ $groupId ][ static::$langKey . '_' . $row['pf_id'] ] = !( $raw ) ? static::constructFromData( $row )->displayValue( $values[ 'field_' . $row['pf_id'] ], FALSE, $location, \IPS\Member::load( $values['member_id'] ) ) : $values[ 'field_' . $row['pf_id'] ];
			}
		}

		return $return;
	}

	/**
	 * @brief	Field ID controlling formatting that we should show/hide depending upon field type selection
	 */
	protected $fieldFormattingId = 'pf_topic_hide';
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		parent::form( $form );

		/* Remove the last two form elements, plus the header that was already added */
		unset( $form->elements['']['pf_search_type'], $form->elements['']['pf_search_type_on_off'] );
		array_pop( $form->elements[''] );

		$form->addHeader( 'pfield_permissions' );

		if ( \IPS\Login::registrationType() != 'full' )
		{
			/* Quick register is enabled, so do not allow required to be set */
			if ( isset( $form->elements['']['pf_not_null'] ) )
			{
				unset( $form->elements['']['pf_not_null'] );
			}
		}
		else
		{
			/* Quick register is off, so show normal 'reg' field */
			$form->add( new \IPS\Helpers\Form\YesNo( 'pf_show_on_reg', $this->id ? $this->show_on_reg : TRUE, FALSE, array(), NULL, NULL, NULL, 'pf_show_on_reg' ) );
		}

		$form->add( new \IPS\Helpers\Form\YesNo( 'pf_member_edit', $this->id ? $this->member_edit : TRUE, FALSE, array(), NULL, NULL, NULL, 'pf_member_edit' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'pf_member_hide', $this->id ? $this->member_hide : 'all', TRUE, array( 'options' => array( 'hide' => 'custom_fields_hide', 'staff' => 'custom_fields_staff', 'owner' => 'custom_fields_staff_owner', 'all' => 'custom_fields_all' ), 'toggles' => array( 'staff' => array( 'pf_profile_format' ), 'all' => array( 'pf_profile_format' ), 'owner' => array( 'pf_profile_format' ) ) ), NULL, NULL, NULL, 'pf_member_hide' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'pf_topic_hide', $this->id ? $this->topic_hide : 'hide', TRUE, array( 'options' => array( 'hide' => 'custom_fields_hide', 'staff' => 'custom_fields_staff', 'all' => 'custom_fields_all' ), 'toggles' => array( 'staff' => array( 'pf_format' ), 'all' => array( 'pf_format' ) ) ), NULL, NULL, NULL, 'pf_topic_hide' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'pf_contains_pii', $this->id ? $this->contains_pii : FALSE, FALSE ) );

		$form->addHeader( 'pfield_displayoptions' );
		$form->add( new \IPS\Helpers\Form\Select( 'pf_search_type', $this->id ? $this->search_type : 'loose', FALSE, array( 'options' => array( 'exact' => 'pf_search_type_exact', 'loose' => 'pf_search_type_loose', '' => 'pf_search_type_none' ) ), NULL, NULL, NULL, 'pf_search_type' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'pf_search_type_on_off', $this->id ? $this->search_type : 'exact', FALSE, array( 'options' => array( 'exact' => 'pf_search_type_exact', '' => 'pf_search_type_none' ) ), NULL, NULL, NULL, 'pf_search_type_on_off' ) );

		$formatOptions = array( 'default' => 'custom_field_format_default', 'custom' => 'custom_field_format_custom' );
		$form->add( new \IPS\Helpers\Form\Radio( 'pf_profile_format', $this->profile_format ? 'custom' : 'default', TRUE, array( 'options' => $formatOptions, 'toggles' => array( 'custom' => array( 'pf_profile_format_custom' ) ) ), NULL, NULL, NULL, 'pf_profile_format' ) );

		$form->add( new \IPS\Helpers\Form\TextArea( 'pf_profile_format_custom', $this->profile_format, FALSE, array( 'placeholder' => "<strong>{\$title}:</strong> {\$content}" ), NULL, NULL, NULL, 'pf_profile_format_custom' ) );

		$form->add( new \IPS\Helpers\Form\Radio( 'pf_format', $this->format ? 'custom' : 'default', TRUE, array( 'options' => $formatOptions, 'toggles' => array( 'custom' => array( 'pf_format_custom' ) ) ), NULL, NULL, NULL, 'pf_format' ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'pf_format_custom', $this->format, FALSE, array( 'placeholder' => "<strong>{\$title}:</strong> {\$content}" ), NULL, NULL, NULL, 'pf_format_custom' ) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if( array_key_exists( 'pf_search_type', $values ) )
		{
			$values['pf_search_type'] = (string) $values['pf_search_type'];
		}
		
		if ( $values['pf_type'] == 'Poll' )
		{
			$values['pf_format'] = '';
		}

		if( isset( $values['pf_profile_format'] ) )
		{
			if( $values['pf_profile_format'] == 'default' )
			{
				$values['pf_profile_format']	= NULL;
			}
			else
			{
				$values['pf_profile_format']	= $values['pf_profile_format_custom'];
			}

			unset( $values['pf_profile_format_custom'] );
		}

		if( isset( $values['pf_format'] ) )
		{
			if( $values['pf_format'] == 'default' )
			{
				$values['pf_format']	= NULL;
			}
			else
			{
				$values['pf_format']	= $values['pf_format_custom'];
			}

			unset( $values['pf_format_custom'] );
		}

		return parent::formatFormValues( $values );
	}

	/**
	 * [ActiveRecord] Save Record
	 *
	 * @return	void
	 */
	public function save()
	{
		if( $this->_new )
		{
			$return = parent::save();
			\IPS\Db::i()->addColumn( 'core_pfields_content', array( 'name' => "field_{$this->id}", 'type' => 'MEDIUMTEXT' ) );
		}
		else
		{
			$return = parent::save();
		}

		return $return;
	}

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'profileFields' );

	/**
	 * @brief	[CustomField] Database table
	 */
	protected static $contentDatabaseTable;

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		static::$contentDatabaseTable = 'core_pfields_content';

		parent::delete();
		
		/* Do we need to do stuff with profile steps? */
		\IPS\Member\ProfileStep::resync();
	}
}

/* This is only here for backwards compatibility */
const PROFILE 	= _Field::PROFILE;
const REG		= _Field::REG;
const STAFF		= _Field::STAFF;
const CONTENT	= _Field::CONTENT;
const SEARCH	= _Field::SEARCH;
const EDIT		= _Field::EDIT;

const PROFILE_COMPLETION = _Field::PROFILE_COMPLETION;