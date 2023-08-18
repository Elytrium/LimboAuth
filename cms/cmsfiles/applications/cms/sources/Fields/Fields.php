<?php
/**
 * @brief		Database Field Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		2 Apr 2014
 */

/**
 * 
 * @todo Shared media field type
 *
 */
namespace IPS\cms;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Database Field Node
 */
class _Fields extends \IPS\CustomField implements \IPS\Node\Permissions
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons = array();
		
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'cms_database_fields';
	
	/**
	 * @brief	[Fields] Custom Database Id
	 */
	public static $customDatabaseId = NULL;
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'field_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';

	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array('field_id', 'field_key');
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();

	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[CustomField] Title/Description lang prefix
	 */
	protected static $langKey = 'content_field';
	
	/**
	 * @brief	Have fetched all?
	 */
	protected static $gotAll = FALSE;
	
	/**
	 * @brief	The map of permission columns
	 */
	public static $permissionMap = array(
			'view' 				=> 'view',
			'edit'				=> 2,
			'add'               => 3
	);
	
	/**
	 * @brief	[Node] App for permission index
	*/
	public static $permApp = 'cms';
	
	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = 'fields';
	
	/**
	 * @brief	[Node] Prefix string that is automatically prepended to permission matrix language strings
	 */
	public static $permissionLangPrefix = 'perm_content_field_';
	
	/**
	 * @brief	[Node] ACP Restrictions
	 */
	protected static $restrictions = array(
		'app'		=> 'cms',
		'module'	=> 'databases',
		'prefix'	=> 'cms_fields_',
	);

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'content_field_';
	
	/**
	 * @brief	[Node] Sortable?
	 */
	public static $nodeSortable = TRUE;
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = '';
	
	/**
	 * @brief	[CustomField] Database table
	 */
	protected static $contentDatabaseTable;
	
	/**
	 * @brief	[CustomField] Upload storage extension
	 */
	protected static $uploadStorageExtension = 'cms_Records';
	
	/**
	 * @brief	[CustomField] Set to TRUE if uploads fields are capable of holding the submitted content for moderation
	 */
	public static $uploadsCanBeModerated = TRUE;

	/**
	 * @brief	[CustomField] Cache retrieved fields
	 */
	protected static $cache = array();

	/**
	 * @brief   Custom Media fields
	 */
	protected static $mediaFields = array( 'Youtube', 'Spotify', 'Soundcloud' );

	/**
	 * @brief	Skip the title and content fields
	 */
	const FIELD_SKIP_TITLE_CONTENT = 1;
	
	/**
	 * @brief	Show only fields allowed on the comment form
	 */
	const FIELD_DISPLAY_COMMENTFORM = 2;
	
	/**
	 * @brief	Show only fields allowed on the listing view
	 */
	const FIELD_DISPLAY_LISTING = 4;
	
	/**
	 * @brief	Show only fields allowed on the record view
	 */
	const FIELD_DISPLAY_RECORD  = 8;
	
	/**
	 * @brief	Show only fields allowed to be filterable
	 */
	const FIELD_DISPLAY_FILTERS = 16;
	
	/**
	 * @brief	Fields that cannot be title fields
	 */
	public static $cannotBeTitleFields = array( 'Member', 'Editor', 'CheckboxSet', 'YesNo', 'Radio', 'Item' );
	
	/**
	 * @brief	Fields that cannot be content fields
	 */
	public static $cannotBeContentFields = array();
	
	/**
	 * @brief	Fields that can be filtered on the front end. These appear in \Table advanced search and also in the DatabaseFilters widget.
	 */
	protected static $filterableFields = array( 'CheckboxSet', 'Radio', 'Select', 'YesNo', 'Date', 'Member' );
	
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
		if ( $idField === 'field_key' )
		{
			$extraWhereClause = array( 'field_database_id=?', static::$customDatabaseId );
		}
		
		return parent::load( $id, $idField, $extraWhereClause );
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
		$permissionCheck = ( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation === 'admin' ) ? NULL : $permissionCheck;

		if ( ! isset( static::$cache[ static::$customDatabaseId ][ $permissionCheck ] ) )
		{
			$langToLoad = array();
			$where[]    = array( 'field_database_id=?', static::$customDatabaseId );

			static::$cache[ static::$customDatabaseId ][ $permissionCheck ] = parent::roots( $permissionCheck, $member, $where, $limit );

			foreach( static::$cache[ static::$customDatabaseId ][ $permissionCheck ] as $id => $obj )
			{
				if ( ! array_key_exists( $obj->type, static::$additionalFieldTypes ) AND ( ! class_exists( '\IPS\Helpers\Form\\' . mb_ucfirst( $obj->type ) ) AND ! class_exists( '\IPS\cms\Fields\\' . mb_ucfirst( $obj->type ) ) ) )
				{
					unset( static::$cache[ static::$customDatabaseId ][ $permissionCheck ][ $id ] );
					continue;
				}

				$langToLoad[] = static::$langKey . '_' . $obj->id;
				$langToLoad[] = static::$langKey . '_' . $obj->id . '_desc';
				$langToLoad[] = static::$langKey . '_' . $obj->id . '_warning';
			}

			if ( \count( $langToLoad ) AND \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation !== 'setup' )
			{
				\IPS\Member::loggedIn()->language()->get( $langToLoad );
			}
		}

		return static::$cache[ static::$customDatabaseId ][ $permissionCheck ];
	}
	
	/**
	 * Just return all field IDs in a database without permission checking
	 *
	 * @return array
	 */
	public static function databaseFieldIds()
	{
		$key = 'cms_fieldids_' . static::$customDatabaseId;
		
		if ( ! isset( \IPS\Data\Store::i()->$key ) )
		{
			\IPS\Data\Store::i()->$key = iterator_to_array( \IPS\Db::i()->select( 'field_id', 'cms_database_fields', array( array( 'field_database_id=?', static::$customDatabaseId ) ) )->setKeyField('field_id') );
		}
		
		return \IPS\Data\Store::i()->$key;
	}
	
	/**
	 * Get Field Data
	 *
	 * @param	string|NULL		            $permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Node\Model|NULL		$container			Parent container
	 * @param   INT                         $flags              Bit flags
	 *
	 * @return	array
	 */
	public static function data( $permissionCheck=NULL, \IPS\Node\Model $container=NULL, $flags=0 )
	{
		$fields   = array();
		$database = \IPS\cms\Databases::load( static::$customDatabaseId );
		
		foreach( static::roots( $permissionCheck ) as $row )
		{
			if ( $container !== NULL AND $database->use_categories )
			{
				if ( $container->fields !== '*' AND $container->fields !== NULL )
				{
					if ( ! \in_array( $row->id, $container->fields ) AND $row->id != $database->field_title AND $row->id != $database->field_content )
					{
						continue;
					}
				}
			}

			if ( $flags & self::FIELD_SKIP_TITLE_CONTENT AND ( $row->id == $database->field_title OR $row->id == $database->field_content ) )
			{
				continue;
			}
			
			if ( $flags & self::FIELD_DISPLAY_FILTERS )
			{
				if ( ! $row->filter )
				{
					continue;
				}
				
				if ( $row->type === 'Date' )
				{
					$row->type = 'DateRange';
				}
			}
			
			$fields[ $row->id ] = $row;
		}
		
		return $fields;
	}
	
	/**
	 * Get Fields
	 *
	 * @param	array			            $values				Current values
	 * @param	string|NULL		            $permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Node\Model|NULL		$container			Parent container
	 * @param	int				            $flags				Bit flags
	 * @param   \IPS\cms\Records|NULL       $record             The record itself
	 * @return	array
	 */
	public static function fields( $values, $permissionCheck='view', \IPS\Node\Model $container=NULL, $flags=0, \IPS\cms\Records $record = NULL )
	{
		$fields        = array();
		$database      = \IPS\cms\Databases::load( static::$customDatabaseId );

		foreach( static::roots( $permissionCheck ) as $row )
		{
			$row->required = (bool) $row->required;
			
			if ( $container !== NULL AND $database->use_categories )
			{
				if ( $container->fields !== '*' AND $container->fields !== NULL )
				{
					if ( ! \in_array( $row->id, $container->fields ) AND $row->id != $database->field_title AND $row->id != $database->field_content )
					{
						continue;
					}
				}
			}

			if ( $flags & self::FIELD_SKIP_TITLE_CONTENT AND ( $row->id == $database->field_title OR $row->id == $database->field_content ) )
			{
				continue;
			}
			
			if ( $flags & self::FIELD_DISPLAY_COMMENTFORM )
			{
				if ( ! $row->display_commentform )
				{
					continue;
				}
			}

			if ( $flags & self::FIELD_DISPLAY_LISTING AND ( ! $row->display_listing ) )
			{
				continue;
			}
			
			if ( $flags & self::FIELD_DISPLAY_RECORD AND ( ! $row->display_display ) )
			{
				continue;
			}
			
			if ( $flags & self::FIELD_DISPLAY_FILTERS  )
			{
				if ( ! $row->filter )
				{
					continue;
				}
				else
				{
					if ( $row->type === 'Radio' )
					{
						$row->type = 'Select';
					}
					
					if ( $row->type === 'Date' )
					{
						$row->type = 'DateRange';
					}

					$row->required = FALSE;
					
					if ( $row->type === 'Select' )
					{
						$row->is_multiple = true;
					}
				}
			}
			
			$customValidationCode = NULL;
			
			if ( $row->unique )
			{
				$customValidationCode = function( $val ) use ( $database, $row, $record )
				{
					$class = 'IPS\cms\Fields' . static::$customDatabaseId;
					$class::validateUnique( $val, $row, $record );
				};
			}

			if( $row->id == $database->field_title AND $row->type === 'Select' )
			{
				$customValidationCode = function( $val )
				{
					if( $val === NULL )
					{
						throw new \DomainException( 'form_required' );
					}
				};
			}

			if ( isset( $values['field_' . $row->id ] ) )
			{
				$fields[ $row->id ] = $row->buildHelper( $values['field_' . $row->id ], $customValidationCode, $record, $flags );
			}
			else
			{
				$fields[ $row->id ] = $row->buildHelper( $row->default_value, $customValidationCode, $record, $flags );
			}
		}

		return $fields;
	}
	
	/**
	 * Get Values
	 *
	 * @param	array			            $values				Current values
	 * @param	string|NULL		            $permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Node\Model|NULL		$container			Parent container
	 * @return	array
	 */
	public static function values( $values, $permissionCheck='view', \IPS\Node\Model $container=NULL )
	{
		$fields   = array();
		$database = \IPS\cms\Databases::load( static::$customDatabaseId );
		
		foreach( static::roots( $permissionCheck ) as $row )
		{
			if ( $container !== NULL AND $database->use_categories )
			{
				if ( $container->fields !== '*' AND $container->fields !== NULL )
				{
					if ( ! \in_array( $row->id, $container->fields ) AND $row->id != $database->field_title AND $row->id != $database->field_content )
					{
						continue;
					}
				}
			}
			
			if ( isset( $values[ 'field_' . $row->id ] ) )
			{
				$fields[ 'field_' . $row->id ] = $values[ 'field_' . $row->id ];
			}
		}
		
		return $fields;
	}
	
	/**
	 * Display Values
	 *
	 * @param	array			            $values				Current values
	 * @param	string|NULL		            $display			Type of display (listing/display/raw/processed).
	 * @param	\IPS\Node\Model|NULL		$container			Parent container
	 * @param   string                      $index              Field to index return array on
	 * @param	NULL|\IPS\cms\Record		$record				Record showing this field
	 * @note    Raw means the value saved from the input field, processed has the form display value method called. Listing and display take the options set by the field (badges, custom, etc)
	 * @return	array
	 */
	public static function display( $values, $display='listing', \IPS\Node\Model $container=NULL, $index='key', $record=NULL )
	{
		$fields   = array();
		$database = \IPS\cms\Databases::load( static::$customDatabaseId );

		foreach( static::roots('view') as $row )
		{
			if ( $display !== 'record' AND ( $row->id == $database->field_title OR $row->id == $database->field_content ) )
			{
				continue;
			}

			if ( $container !== NULL AND $database->use_categories )
			{
				if ( $container->fields !== '*' AND $container->fields !== NULL )
				{
					if ( ! \in_array( $row->id, $container->fields ) AND $row->id != $database->field_title AND $row->id != $database->field_content )
					{
						continue;
					}
				}
			}

			/* If we don't need these fields we don't need to do any further formatting */
			if ( ( $display === 'listing' and !$row->display_listing ) or ( ( $display === 'display' or $display === 'display_top' or $display === 'display_bottom' ) and !$row->display_display ) )
			{
				continue;
			}

			$formValue = ( isset( $values[ 'field_' . $row->id ] ) AND $values[ 'field_' . $row->id ] !== '' AND $values[ 'field_' . $row->id ] !== NULL ) ? $values[ 'field_' . $row->id ] : $row->default_value;
			 			
			$value     = $row->displayValue( $formValue );

			if ( $display === 'listing' )
			{
				$value = $row->truncate( $value, TRUE );

				if ( $value !== '' AND $value !== NULL )
				{
					$value = $row->formatForDisplay( $value, $formValue, 'listing', $record );
				}
			}
			else if ( $display === 'display' or $display === 'display_top' or $display === 'display_bottom' )
			{
				$displayData = $row->display_json;

				if ( $display === 'display_bottom' )
				{
					if ( isset( $displayData['display']['where'] ) )
					{
						if ( $displayData['display']['where'] !== 'bottom' )
						{
							continue;
						}
					}
					else
					{
						continue;
					}
				}
				else if ( $display === 'display_top' )
				{
					if ( isset( $displayData['display']['where'] ) )
					{
						if ( $displayData['display']['where'] !== 'top' )
						{
							continue;
						}
					}
				}

				if ( $value !== '' AND $value !== NULL )
				{
					$value = $row->formatForDisplay( $value, $formValue, 'display', $record );
				}
			}
			else if ( $display === 'raw' )
			{
				$value = $formValue;
			}

			$fields[ ( $index === 'id' ? $row->id : $row->key ) ] = $value;
		}

		return $fields;
	}

	/**
	 * Display the field
	 *
	 * @param   mixed        $value         Processed value
	 * @param   mixed        $formValue     Raw form value
	 * @param   string       $type          Type of display (listing/display/raw/processed).
	 * @param	NULL|\IPS\cms\Records	$record	Record showing this field
	 * @note    Raw means the value saved from the input field, processed has the form display value method called. Listing and display take the options set by the field (badges, custom, etc)
	 *
	 * @return mixed|string
	 * @throws \ErrorException
	 */
	public function formatForDisplay( $value, $formValue, $type='listing', $record=NULL )
	{
		if ( $type === 'raw' )
		{
			if ( $this->type === 'Upload' )
			{
				if ( $this->is_multiple )
				{
					$images = array();
					foreach( explode( ',', $value ) as $val )
					{
						$images[] = \IPS\File::get( static::$uploadStorageExtension, $val )->url;
					}
					
					return $images;
				}
				
				return (string) \IPS\File::get( static::$uploadStorageExtension, $value )->url;
			}

			if ( $this->type === 'Item' )
			{
				if ( ! \is_array( $formValue ) and mb_strstr( $formValue, ',' ) )
				{
					$value = explode( ',', $formValue );
				}
				else
				{
					$value = array( $formValue );
				}

				if ( \count( $value ) and isset( $this->extra['database'] ) and $this->extra['database'] )
				{
					$results = array();
					$class   = '\IPS\cms\Records' . $this->extra['database'];
					$field   = $class::$databasePrefix . $class::$databaseColumnMap['title'];
					$where   = array( \IPS\Db::i()->in( $class::$databaseColumnId, $value ) );

					foreach ( $class::getItemsWithPermission( array( $where ), $field, NULL ) as $item )
					{
						$results[ $item->_id ] = $item;
					}

					return $results;
				}
			}

			return $formValue;
		}
		else if ( $type === 'processed' )
		{
			return $value;
		}
		else if ( $type === 'thumbs' and $this->type === 'Upload' )
		{
			if ( isset( $this->extra['thumbsize'] ) )
			{
				if ( $this->is_multiple )
				{
					$thumbs = iterator_to_array( \IPS\Db::i()->select( '*', 'cms_database_fields_thumbnails', array( array( 'thumb_field_id=? AND thumb_record_id=?', $this->id, $record->_id ) ) )->setKeyField('thumb_original_location')->setValueField('thumb_location') );
					$images = array();
				
					foreach( $thumbs as $orig => $thumb )
					{
						try
						{
							$images[] = \IPS\File::get( static::$uploadStorageExtension, $thumb )->url;
						}
						catch( \Exception $e ) { }
					}
					
					return $images;
				}
				else
				{
					try
					{
						return (string) \IPS\File::get( static::$uploadStorageExtension, \IPS\Db::i()->select( 'thumb_location', 'cms_database_fields_thumbnails', array( array( 'thumb_field_id=? AND thumb_record_id=?', $this->id, $record->_id ) ) )->first() )->url;
					}
					catch( \Exception $e ) { }
				}
			}
			
			return $this->formatForDisplay( $value, $formValue, 'raw', $record );
		}

		$options = $this->display_json;

		if ( isset( $options[ $type ]['method'] ) AND $options[ $type ]['method'] !== 'simple' )
		{
			if ( \in_array( $this->type, static::$mediaFields ) )
			{
				$template = mb_strtolower( $this->type );

				if ( $options[ $type ]['method'] === 'player' )
				{
					$class = '\IPS\cms\Fields\\' . $this->type;

					if ( method_exists( $class, 'displayValue' ) )
					{
						$value = $class::displayValue( $formValue, $this );
					}
					else
					{
						try
						{
							$value = \IPS\Theme::i()->getTemplate( 'records', 'cms', 'global' )->$template( $formValue, $this->extra );
						}
						catch( \Exception $ex )
						{
							$value = $formValue;
						}
					}
				}
				else
				{
					$value = $formValue;
				}
			}
			else
			{
				if ( $options[ $type ]['method'] == 'custom' )
				{
					if ( $this->type === 'Upload' )
					{
						if ( mb_strstr( $value, ',' ) )
						{
							$files = explode( ',', $value );
						}
						else
						{
							$files = array( $value );
						}

						$objects = array();
						foreach ( $files as $file )
						{
							$object = \IPS\File::get( static::$uploadStorageExtension, (string) $file );
							
							if ( $object->isImage() and $type === 'display' )
							{
								\IPS\Output::i()->metaTags['og:image:url'][] = (string) $object->url;
							}
							
							$objects[] = $object;
						}

						if ( ! $this->is_multiple )
						{
							$value = array_shift( $objects );
						}
						else
						{
							$value = $objects;
						}
					}

					$value = trim( $this->parseCustomHtml( $type, $options[ $type ]['html'], $formValue, $value, $record ) );
				}
				else if ( $options[ $type ]['method'] !== 'none' )
				{
					$class = 'ipsBadge_style' . $options[ $type ]['method'];

					if ( isset( $options[ $type ]['right'] ) AND $options[ $type ]['right'] )
					{
						$class .= ' ' . 'ipsPos_right';
					}

					if ( $this->type === 'Address' and $formValue and isset( $options[ $type ]['map'] ) AND $options[ $type ]['map'] AND \IPS\GeoLocation::enabled() )
					{
						$value .= \IPS\GeoLocation::buildFromJson( $formValue )->map()->render( $options[ $type ]['mapDims'][0], $options[ $type ]['mapDims'][1] );
					}

					if ( $this->type === 'Upload' )
					{
						if ( mb_strstr( $value, ',' ) )
						{
							$files = explode( ',', $value );
						}
						else
						{
							$files = array( $value );
						}

						$parsed = array();
						foreach( $files as $idx => $file )
						{
							$file = \IPS\File::get( static::$uploadStorageExtension, (string) $file );

							if ( $file->isImage() and $type === 'display' )
							{
								\IPS\Output::i()->metaTags['og:image:url'][] = (string) $file->url;
							}

							$fileKey		= \IPS\Text\Encrypt::fromPlaintext( (string) $file )->tag();
							$downloadUrl	= \IPS\Http\Url::internal( 'applications/core/interface/file/cfield.php', 'none' )->setqueryString( array(
								'storage'	=> $file->storageExtension,
								'path'		=> (string) $file->originalFilename,
								'fileKey'   => $fileKey
							) );
							
							$parsed[] = \IPS\Theme::i()->getTemplate( 'global', 'cms', 'front' )->uploadDisplay( \IPS\File::get( static::$uploadStorageExtension, $file ), $record, $downloadUrl, $fileKey );
						}

						$value = implode( " ", $parsed );
					}
					else if ( $this->type === 'Member' )
					{
						if ( mb_strstr( $value, "\n" ) )
						{
							$members = explode( "\n", $formValue );
						}
						else
						{
							$members = array( $formValue );
						}

						$parsed = array();

						foreach( $members as $id )
						{
							try
							{
								$parsed[] = \IPS\Member::load( $id )->link();
							}
							catch( \Exception $e ) { }
						}
						
						$value = implode( ", ", $parsed );
						
					}

					$value = \IPS\Theme::i()->getTemplate( 'records', 'cms', 'global' )->fieldBadge( $this->_title, $value, $class );
				}
			}
		}
		else
		{
			if ( $this->type === 'Address' and $formValue and isset( $options[ $type ]['map'] ) AND $options[ $type ]['map'] AND \IPS\GeoLocation::enabled() )
			{
				$value .= \IPS\GeoLocation::buildFromJson( $formValue )->map()->render( $options[ $type ]['mapDims'][0], $options[ $type ]['mapDims'][1] );
			}
			else if ( $this->type === 'Upload' )
			{
				if ( mb_stristr( $value, ',' ) )
				{
					$files = explode( ',', $value );
				}
				else
				{
					$files = array( $value );
				}
				
				if ( \count( $files ) )
				{
					$parsed = array();
					foreach( $files AS $file )
					{
						$file = \IPS\File::get( static::$uploadStorageExtension, (string) $file );
						$parsed[] = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $file->fullyQualifiedUrl( $file->url ) );
					}
					$value = implode( '', $parsed );
				}
			}
			
			$value = \IPS\Theme::i()->getTemplate( 'records', 'cms', 'global' )->fieldDefault( $this->_title, $value );
		}

		return $value;
	}

	/**
	 * Parse custom HTML
	 *
	 * @param   string  $type           Type of display
	 * @param   string  $template       The HTML to parse
	 * @param   string  $formValue      The form value (key of select box, for example)
	 * @param   string  $value          The display value
	 * @param	NULL|\IPS\cms\Record	$record	Record showing this field
	 *
	 * @return \IPS\Theme
	 */
	public function parseCustomHtml( $type, $template, $formValue, $value, $record=NULL )
	{
		$functionName = $this->fieldTemplateName( $type );
		$options      = $this->display_json;

		if ( $formValue and $this->type === 'Address' )
		{
			$functionName .= '_' . mt_rand();
			
			$template = str_replace( '{map}'    , \IPS\GeoLocation::buildFromJson( $formValue )->map()->render( $options[ $type ]['mapDims'][0], $options[ $type ]['mapDims'][1] ), $template );
			$template = str_replace( '{address}', \IPS\GeoLocation::parseForOutput( $formValue ), $template );
			$template = \IPS\Theme::compileTemplate( $template, $functionName, '$value, $formValue, $label, $record', true );
		}
		else
		{
			if ( $this->type === 'Upload' )
			{
				if ( \is_array( $value ) )
				{
					foreach( $value as $idx => $val )
					{
						if ( $val instanceof \IPS\File\FileSystem )
						{
							$value[ $idx ] = (string) $val->url;
						}
					}
				}
				else if ( $value instanceof \IPS\File\FileSystem )
				{
					$value = (string) $value->url;
				}
			}
			if ( ! isset( \IPS\Data\Store::i()->$functionName ) )
			{
				\IPS\Data\Store::i()->$functionName = \IPS\Theme::compileTemplate( $template, $functionName, '$value, $formValue, $label, $record', true );
			}

			$template = \IPS\Data\Store::i()->$functionName;
		}

		\IPS\Theme::runProcessFunction( $template, $functionName );

		$themeFunction = 'IPS\\Theme\\'. $functionName;
		return $themeFunction( $value, $formValue, $this->_title, $record );
	}
	
	/**
	 * Show this form field?
	 * 
	 * @param	 string	 	$field		Field key
	 * @param	 string		$where		Where to show, form or record
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member			The member or group to check (NULL for currently logged in member)
	 * @return	 boolean
	 */
	public static function fixedFieldFormShow( $field, $where='form', $member=NULL )
	{
		$fixedFields = \IPS\cms\Databases::load( static::$customDatabaseId )->fixed_field_perms;
		$perm        = ( $where === 'form' ) ? 'perm_2' : 'perm_view';
		
		if ( ! \in_array( $field, array_keys( $fixedFields ) ) )
		{
			return FALSE;
		}
		
		$permissions = $fixedFields[ $field ];
		
		if ( empty( $permissions['visible'] ) OR empty( $permissions[ $perm ] ) )
		{
			return FALSE;
		}
		
		/* Load member */
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}
		
		/* Finally check permissions */
		if( $member instanceof \IPS\Member\Group )
		{
			return ( $permissions[ $perm ] === '*' or ( $permissions[ $perm ] and \in_array( $member->g_id, explode( ',', $permissions[ $perm ] ) ) ) );
		}
		else
		{
			return ( $permissions[ $perm ] === '*' or ( $permissions[ $perm ] and $member->inGroup( explode( ',', $permissions[ $perm ] ) ) ) );
		}
	}
	
	/**
	 * Get fixed field permissions as an array or a *
	 * 
	 * @param	string|null		$field		Field Key
	 * @return array|string|null
	 */
	public static function fixedFieldPermissions( $field=NULL )
	{
		$fixedFields = \IPS\cms\Databases::load( static::$customDatabaseId )->fixed_field_perms;
		
		if ( $field !== NULL AND \in_array( $field, array_keys( $fixedFields ) ) )
		{
			return $fixedFields[ $field ]; 
		}
		
		return ( $field !== NULL ) ? NULL : $fixedFields;
	}
	
	/**
	 * Set fixed field permissions
	 *
	 * @param	string	$field		Field Key
	 * @param	array	$values		Perm values
	 * @return  void
	 */
	public static function setFixedFieldPermissions( $field, $values )
	{
		$fixedFields = \IPS\cms\Databases::load( static::$customDatabaseId )->fixed_field_perms;

		foreach( $values as $k => $v )
		{
			$fixedFields[ $field ][ $k ] = $v;
		}

		\IPS\cms\Databases::load( static::$customDatabaseId )->fixed_field_perms = $fixedFields;
		\IPS\cms\Databases::load( static::$customDatabaseId )->save();
	}
	
	/**
	 * Set the visiblity
	 *
	 * @param	string	$field		Field Key
	 * @param	bool	$value		True/False
	 * @return  void
	 */
	public static function setFixedFieldVisibility( $field, $value )
	{
		$fixedFields = \IPS\cms\Databases::load( static::$customDatabaseId )->fixed_field_perms;
	
		$fixedFields[ $field ]['visible'] = $value;

		\IPS\cms\Databases::load( static::$customDatabaseId )->fixed_field_perms = $fixedFields;
		\IPS\cms\Databases::load( static::$customDatabaseId )->save();
	}
	
	/**
	 * Magic method to capture validateInput_{id} callbacks
	 * @param	string 		$name		Name of method called
	 * @param	mixed 		$arguments	Args passed
	 * @throws \InvalidArgumentException
	 * @return	mixed
	 */
	public static function __callStatic($name, $arguments)
	{
		if ( mb_substr( $name, 0, 14 ) === 'validateInput_' )
		{
			$id = mb_substr( $name, 14 );
			
			if ( \is_numeric( $id ) )
			{
				$field = static::load( $id );
			}
			
			if ( ! empty($arguments[0]) AND $field->validator AND $field->validator_custom )
			{
				if ( ! preg_match( $field->validator_custom, $arguments[0] ) )
				{
					throw new \InvalidArgumentException( ( \IPS\Member::loggedIn()->language()->addToStack('content_field_' . $field->id . '_validation_error') === 'content_field_' . $field->id . '_validation_error' ) ? 'content_exception_invalid_custom_validation' : \IPS\Member::loggedIn()->language()->addToStack('content_field_' . $field->id . '_validation_error') );
				}
			}
		}
	}
	
	/**
	 * Checks to see if this value is unique
	 * Used in custom validation for fomr helpers
	 *
	 * @param	string		$val	The value to check
	 * @param	\IPS\cms\Fields	$field	The field
	 * @param	\IPS\cms\Records	$record	The record (if any)
	 * @return	void
	 * @throws \LogicException
	 */
	public static function validateUnique( $val, $field, $record )
	{
		if ( $val === '' )
		{
			return;
		}
		
		$database = \IPS\cms\Databases::load( static::$customDatabaseId );

		if( $field->type == 'Member' AND $val instanceof \IPS\Member )
		{
			$val = $val->member_id;
		}
		
		$where = array( array( 'field_' . $field->id . '=?', $val ) );
							
		if ( $record !== NULL )
		{
			$where[] = array( 'primary_id_field != ?', $record->_id );
		}
		
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'cms_custom_database_' . $database->id, $where )->first() )
		{
			throw new \LogicException( \IPS\Member::loggedIn()->language()->addToStack( "field_unique_entry_not_unique", FALSE, array( 'sprintf' => array( $database->recordWord( 1 ) ) ) ) );
		}
	}
	
	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		if( $this->skipCloneDuplication === TRUE )
		{
			return;
		}
		
		parent::__clone();
		
		$this->key .= '_' . $this->id;
		$this->save();
	}
	
	/**
	 * Set some default values
	 * 
	 * @return void
	 */
	public function setDefaultValues()
	{
		$this->_data['extra'] = '';
		$this->_data['default_value'] = '';
		$this->_data['format_opts'] = '';
		$this->_data['validator'] = '';
		$this->_data['topic_format'] = '';
		$this->_data['allowed_extensions'] = '';
		$this->_data['validator_custom'] = '';
		$this->_data['display_json'] = array();;
	}

	/**
	 * Field custom template name
	 *
	 * @param   string  $type   Type of name to fetch
	 * @return	string
	 */
	public function fieldTemplateName( $type )
	{
		return 'pages_field_custom_html_' . $type . '_' . $this->id;
	}

	/**
	 * Set the "display json" field
	 *
	 * @param string|array $value	Value
	 * @return void
	 */
	public function set_display_json( $value )
	{
		$this->_data['display_json'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}

	/**
	 * Get the "display json" field
	 *
	 * @return array
	 */
	public function get_display_json()
	{
		return ( \is_array( $this->_data['display_json'] ) ) ? $this->_data['display_json'] : json_decode( $this->_data['display_json'], TRUE );
	}

	/**
	 * Set the "Format Options" field
	 *
	 * @param string|array $value	Value
	 * @return void
	 */
	public function set_format_opts( $value )
	{
		$this->_data['format_opts'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "Format Options" field
	 *
	 * @return array
	 */
	public function get_format_opts()
	{
		return json_decode( $this->_data['format_opts'], TRUE );
	}
	
	/**
	 * Set the "extra" field
	 * 
	 * @param string|array $value	Value
	 * @return void
	 */
	public function set_extra( $value )
	{
		$this->_data['extra'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Set the "allowed_extensions" field
	 *
	 * @param string|array $value	Value
	 * @return void
	 */
	public function set_allowed_extensions( $value )
	{
		$this->_data['allowed_extensions'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "extra" field
	 *
	 * @return array
	 */
	public function get_extra()
	{
		return json_decode( $this->_data['extra'], TRUE );
	}
	
	/**
	 * Get the "allowed_extensions" field
	 *
	 * @return array
	 */
	public function get_allowed_extensions()
	{
		return json_decode( $this->_data['allowed_extensions'], TRUE );
	}
	
	/**
	 * [Node] Get Node Title
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		if ( !$this->id )
		{
			return '';
		}
		
		try
		{
			return \IPS\Member::loggedIn()->language()->get( static::$langKey . '_' . $this->id );
		}
		catch( \UnderflowException $e )
		{
			return FALSE;
		}
	}
	
	/**
	 * [Node] Get Description
	 *
	 * @return	string|null
	 */
	protected function get__description()
	{
		try
		{
			return \IPS\Member::loggedIn()->language()->get( static::$langKey . '_' . $this->id . '_desc' );
		}
		catch( \UnderflowException $e )
		{
			return FALSE;
		}
	}
	
	/**
	 * [Node] Return the custom badge for each row
	 *
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	protected function get__badge()
	{
		$badge = null;

		if ( \IPS\cms\Databases::load( $this->database_id )->field_title == $this->id )
		{
			$badge = array( 0 => 'positive ipsPos_right', 1 => 'content_fields_is_title' );
		}
		else if ( \IPS\cms\Databases::load( $this->database_id )->field_content == $this->id )
		{
			$badge = array( 0 => 'positive ipsPos_right', 1 => 'content_fields_is_content' );
		}
		
		return $badge;
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		if ( class_exists( '\IPS\Helpers\Form\\' . mb_ucfirst( $this->type ) ) )
		{
			return NULL;
		}
		else if ( class_exists( '\IPS\cms\Fields\\' . mb_ucfirst( $this->type ) ) )
		{
			return NULL;
		}

		return 'warning';
	}


	/**
	 * Truncate the field value
	 * 
	 * @param	string      $text	Value to truncate
	 * @param   boolean     $oneLine    Truncate to a single line?
	 * @return	string
	 */
	public function truncate( $text, $oneLine=FALSE )
	{
		if ( ! $this->truncate )
		{
			return $text;
		}
		
		switch( mb_ucfirst( $this->type ) )
		{
			default:
				// No truncate
			break;
			case 'Radio':
			case 'Select':
			case 'Text':
				$text = mb_substr( $text, 0, $this->truncate );
			break;
			case 'TextArea':
			case 'Editor':
				$text = preg_replace( '#</p>(\s+?)?<p>#', $oneLine ? ' $1' : '<br>$1', $text );
				$text = str_replace( array( '<p>', '</p>', '<div>', '</div>' ), '', $text );
				$text = '<div data-ipsTruncate data-ipsTruncate-size="' . $this->truncate . ' lines">' . $text . '</div>';
			break;
		}
		
		return $text;
	}

	/**
	 * Display Value
	 *
	 * @param	mixed	$value						The value
	 * @param	bool	$showSensitiveInformation	If TRUE, potentially sensitive data (like passwords) will be displayed - otherwise will be blanked out
	 * @param	string	$separator					Used to separate items when displaying a field with multiple values.
	 * @return	string
	 */
	public function displayValue( $value=NULL, $showSensitiveInformation=FALSE, $separator=NULL )
	{
		$database = \IPS\cms\Databases::load( static::$customDatabaseId );
		
		if ( class_exists( '\IPS\cms\Fields\\' . mb_ucfirst( $this->type ) ) )
		{
			/* Is special! */
			$class = '\IPS\cms\Fields\\' . mb_ucfirst( $this->type );
			
			if ( method_exists( $class, 'displayValue' ) )
			{
				return $class::displayValue( $value, $this );
			}
		}
		
		switch( mb_ucfirst( $this->type ) )
		{
			case 'Upload':
				/* We need to return NULL if there's no value, File::get will return an URL object even if $value is empty */
				if ( empty( $value ) )
				{
					return NULL;
				}

				return \IPS\File::get( 'cms_Records', $value )->url;
			break;
			case 'Text':
			case 'TextArea':
				$value = $this->applyFormatter( $value );

				/* We don't want the parent adding wordbreak to the title */
				if ( $this->id == $database->field_title )
				{
					return $value;
				}
				else if ( $this->id == $database->field_content )
				{
					return nl2br( $value );
				}
				
				/* If we allow HTML, then do not pass to parent::displayValue as htmlspecialchars is run */
				if ( $this->html )
				{
					return $value;
				}
			break;
			case 'Select':
			case 'Radio':
			case 'CheckboxSet':
				/* This comes from a keyValue stack, so reformat */
				if ( $this->extra and isset( $this->extra[0]['key'] ) )
				{
					$extra = array();
					foreach( $this->extra as $id => $row )
					{
						$extra[ $row['key'] ] = $row['value']; 
					}
			
					$this->extra = $extra;
				}

				if ( ! \is_array( $value ) )
				{
					$value = explode( ',', $value );
				}

				if ( \is_array( $value ) )
				{
					$return = array();
					foreach( $value as $key )
					{
						$return[] = isset( $this->extra[ $key ] ) ? htmlspecialchars( $this->extra[ $key ], ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ) : htmlspecialchars( $key, ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE );
					}

					return implode( ', ', $return );
				}
				else
				{
					return ( isset( $this->extra[ $value ] ) ? htmlspecialchars( $this->extra[ $value ], ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ) : htmlspecialchars( $value, ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ) );
				}
			break;
			case 'Member':
				if ( ! $value )
				{
					return NULL;
				}
				else
				{
					$links = array();

					$value = \is_array( $value ) ? $value : ( ( $value instanceof \IPS\Member ) ? array( $value ) : explode( "\n", $value ) );
					
					foreach( $value as $id )
					{
						$links[] = ( $id instanceof \IPS\Member ) ? $id->link() : \IPS\Member::load( $id )->link();
					}
					
					return implode( ', ', $links );
				}
			break;
			case 'Url':
				if ( \IPS\Dispatcher::hasInstance() AND class_exists( '\IPS\Dispatcher', FALSE ) and \IPS\Dispatcher::i()->controllerLocation === 'front' )
				{
					return ( $value ) ? \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $value, TRUE, NULL, FALSE ) : NULL;
				}
			break;
			case 'Date':
			case 'DateRange':
				if ( \is_numeric( $value ) )
				{
					$time = \IPS\DateTime::ts( $value );
					
					if ( isset( $this->extra['timezone'] ) and $this->extra['timezone'] )
					{
						/* The timezone is already set to user by virtue of DateTime::ts() */
						if ( $this->extra['timezone'] != 'user' )
						{							
							if ( $time instanceof \IPS\DateTime )
							{
								$time->setTimezone( new \DateTimeZone( $this->extra['timezone'] ) );
							}
						}
					}
					else
					{
						if ( $time instanceof \IPS\DateTime )
						{
							$time->setTimezone( new \DateTimeZone( 'UTC' ) );
						}
					}
	
					return $this->extra['time'] ? (string) $time : $time->localeDate();
				}
				
				if ( ! \is_array( $value ) )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('field_no_value_entered');
				}
				
				$start = NULL;
				$end   = NULL;
				foreach( array( 'start', 'end' ) as $t )
				{
					if ( isset( $value[ $t ] ) )
					{
						$time = ( \is_integer( $value[ $t ] ) ) ? \IPS\DateTime::ts( $value[ $t ], TRUE ) : $value[ $t ];
						if ( isset( $this->extra['timezone'] ) and $this->extra['timezone'] )
						{
							try
							{
								$time->setTimezone( new \DateTimeZone( $this->extra['timezone'] ) );
							}
							catch( \Exception $e ){}
						}
						
						$$t = $time->localeDate();
					}
				}
				
				if ( $start and $end )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'field_daterange_start_end', FALSE, array( 'sprintf' => array( $start, $end ) ) );
				}
				else if ( $start )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'field_daterange_start', FALSE, array( 'sprintf' => array( $start ) ) );
				}
				else if ( $end )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'field_daterange_end', FALSE, array( 'sprintf' => array( $end ) ) );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('field_no_value_entered');
				}
			break;
			case 'Item':
				if ( ! \is_array( $value ) and mb_strstr( $value, ',' ) )
				{
					$value = explode( ',', $value );
				}
				else
				{
					$value = array( $value );
				}

				if ( \count( $value ) and isset( $this->extra['database'] ) and $this->extra['database'] )
				{
					$results = array();
					$class   = '\IPS\cms\Records' . $this->extra['database'];
					$field   = $class::$databasePrefix . $class::$databaseColumnMap['title'];
					$where   = array( \IPS\Db::i()->in( $class::$databaseColumnId, $value ) );

					foreach( $class::getItemsWithPermission( array( $where ), $field, NULL ) as $item )
					{
						$results[] = $item;
					}

                    if( \count( $results ) )
                    {
                        return \IPS\Theme::i()->getTemplate( 'global', 'cms', 'front' )->basicRelationship( $results );
                    }
				}

				return NULL;
				break;
		}

		/* Formatters */
		try
		{
			return parent::displayValue( $value, $showSensitiveInformation );
		}
		catch( \InvalidArgumentException $ex )
		{
			return NULL;
		}
	}
	
	/**
	 * Apply formatter
	 *
	 * @param	mixed	$value	The value
	 * @return	string
	 */
	public function applyFormatter( $value )
	{
		if ( \is_array( $this->format_opts ) and \count( $this->format_opts ) )
		{
			foreach( $this->format_opts as $id => $type )
			{
				switch( $type )
				{
					case 'strtolower':
						$value	= mb_convert_case( $value, MB_CASE_LOWER );
					break;
					
					case 'strtoupper':
						$value	= mb_convert_case( $value, MB_CASE_UPPER );
					break;
					
					case 'ucfirst':
						$value	= ( mb_strtoupper( mb_substr( $value, 0, 1 ) ) . mb_substr( $value, 1, mb_strlen( $value ) ) );
					break;
					
					case 'ucwords':
						$value	= mb_convert_case( $value, MB_CASE_TITLE );
					break;
					
					case 'punct':
						$value	= preg_replace( "/\?{1,}/"		, "?"		, $value );
						$value	= preg_replace( "/(&#33;){1,}/"	, "&#33;"	, $value );
					break;
					
					case 'numerical':
						$value	= \IPS\Member::loggedIn()->language()->formatNumber( $value );
					break;
				}
			}
		}
		
		return $value;
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
		$buttons  = parent::getButtons( $url, $subnode );
		$database = \IPS\cms\Databases::load( $this->database_id );

		if ( $this->canEdit() )
		{
			if ( $this->id != $database->field_title and $this->id != $database->field_content )
			{
				if ( $this->canBeTitleField() )
				{
					$buttons['set_as_title'] = array(
						'icon'	=> 'list-ul',
						'title'	=> 'cms_set_field_as_title',
						'link'	=> $url->setQueryString( array( 'do' => 'setAsTitle', 'id' => $this->_id ) )->csrf(),
						'data'	=> array()
					);
				}

				if ( $this->canBeContentField() )
				{
					$buttons['set_as_content'] = array(
						'icon'	=> 'file-text-o',
						'title'	=> 'cms_set_field_as_content',
						'link'	=> $url->setQueryString( array( 'do' => 'setAsContent', 'id' => $this->_id ) )->csrf(),
						'data'	=> array()
					);
				}
			}
		}

		return $buttons;
	}

	/**
	 * Can this field be a title field?
	 *
	 * @return boolean
	 */
	public function canBeTitleField()
	{
		if ( $this->is_multiple or \in_array( mb_ucfirst( $this->type ), static::$cannotBeTitleFields ) )
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Can this field be a content field?
	 *
	 * @return boolean
	 */
	public function canBeContentField()
	{
		$no = array();

		if ( $this->is_multiple or \in_array( mb_ucfirst( $this->type ), static::$cannotBeContentFields ) )
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		$database = \IPS\cms\Databases::load( $this->database_id );

		if ( $this->id == $database->field_title or $this->id == $database->field_content )
		{
			return FALSE;
		}

		return parent::canDelete();
	}

	/**
	 *
	 * [Node] Does the currently logged in user have permission to edit permissions for this node?
	 *
	 * @return	bool
	 */
	public function canManagePermissions()
	{
		return true;
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->hiddenValues['database_id'] = static::$customDatabaseId;

		if ( $this->type )
		{
			$ok = FALSE;
			if ( class_exists( '\IPS\Helpers\Form\\' . mb_ucfirst( $this->type ) ) )
			{
				$ok = TRUE;
			}
			else if ( class_exists( '\IPS\cms\Fields\\' . mb_ucfirst( $this->type ) ) )
			{
				$ok = TRUE;
			}

			if ( !$ok )
			{
				\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( \IPS\Member::loggedIn()->language()->addToStack( 'cms_field_no_type_warning', FALSE, array( 'sprintf' => array( $this->type ) ) ), 'warning', NULL, FALSE );
			}
		}

		$form->addTab( 'field_generaloptions' );
		$form->addHeader( 'pfield_settings' );

		$form->add( new \IPS\Helpers\Form\Translatable( 'field_title', NULL, TRUE, array( 'app' => 'core', 'key' => ( $this->id ? static::$langKey . '_' . $this->id : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'field_description', NULL, FALSE, array( 'app' => 'core', 'key' => ( $this->id ? static::$langKey . '_' . $this->id . '_desc' : NULL ) ) ) );

		$displayDefaults = array( 'field_display_listing_json_badge', 'field_display_listing_json_badge_right', 'field_display_listing_json_custom', 'field_display_display_json_badge', 'field_display_display_json_custom', 'field_display_display_json_where' );

		$options = array_merge( array(
            'Address'    => 'pf_type_Address',
            'Checkbox'   => 'pf_type_Checkbox',
            'CheckboxSet' => 'pf_type_CheckboxSet',
            'Codemirror' => 'pf_type_Codemirror',
            'Item'       => 'pf_type_Relational',
            'Date'		 => 'pf_type_Date',
            'Editor'	 => 'pf_type_Editor',
            'Email'		 => 'pf_type_Email',
            'Member'     => 'pf_type_Member',
            'Number'	 => 'pf_type_Number',
            'Password'	 => 'pf_type_Password',
            'Radio'		 => 'pf_type_Radio',
            'Select'	 => 'pf_type_Select',
            'Tel'		 => 'pf_type_Tel',
            'Text'		 => 'pf_type_Text',
            'TextArea'	 => 'pf_type_TextArea',
            'Upload'	 => 'pf_type_Upload',
            'Url'		 => 'pf_type_Url',
            'YesNo'		 => 'pf_type_YesNo',
            'Youtube'    => 'pf_type_Youtube',
            'Spotify'    => 'pf_type_Spotify',
            'Soundcloud' => 'pf_type_Soundcloud',
        ), static::$additionalFieldTypes );

		$toggles = array(
			'Address'	=> array_merge( array( 'field_show_map_listing', 'field_show_map_display', 'field_show_map_listing_dims', 'field_show_map_display_dims' ), $displayDefaults ),
			'Codemirror'=> array_merge( array( 'field_default_value', 'field_truncate' ), $displayDefaults ),
			'Checkbox'  => array_merge( array( 'field_default_value', 'field_truncate' ), $displayDefaults ),
			'CheckboxSet' => array_merge( array( 'field_extra', 'field_default_value', 'field_truncate' ), $displayDefaults ),
			'Date'		=> array_merge( array( 'field_default_value', 'field_date_time_override', 'field_date_time_time' ), $displayDefaults ),
			'Editor'    => array_merge( array( 'field_max_length', 'field_default_value', 'field_truncate', 'field_allow_attachments' ), $displayDefaults ),
			'Email'		=> array_merge( array( 'field_max_length', 'field_default_value', 'field_unique' ), $displayDefaults ),
			'Item'      => array_merge( array( 'field_is_multiple', 'field_relational_db', 'field_crosslink' ), $displayDefaults ),
			'Member'    => array_merge( array( 'field_is_multiple', 'field_unique' ), $displayDefaults ),
			'Number'    => array_merge( array( 'field_default_value', 'field_number_decimals_on', 'field_number_decimals', 'field_unique', 'field_number_min', 'field_number_max' ), $displayDefaults ),
			'Password'  => array_merge( array( 'field_default_value' ), $displayDefaults ),
			'Radio'     => array_merge( array( 'field_extra', 'field_default_value', 'field_truncate', 'field_unique' ), $displayDefaults ),
			'Select'    => array_merge( array( 'field_extra', 'field_default_value', 'field_is_multiple', 'field_truncate', 'field_unique' ), $displayDefaults ),
			'Tel'		=> array_merge( array( 'field_default_value', 'field_unique' ), $displayDefaults ),
			'Text'		=> array_merge( array( 'field_validator', 'field_format_opts_on', 'field_max_length', 'field_default_value', 'field_html', 'field_truncate', 'field_unique' ), $displayDefaults ),
			'TextArea'	=> array_merge( array( 'field_validator', 'field_format_opts_on', 'field_max_length', 'field_default_value', 'field_html', 'field_truncate', 'field_unique' ), $displayDefaults ),
			'Upload'    => array_merge( array( 'field_upload_is_image', 'field_upload_is_multiple', 'field_upload_thumb' ), $displayDefaults ),
			'Url'		=> array_merge( array( 'field_default_value', 'field_unique' ), $displayDefaults ),
			'YesNo'		=> array_merge( array( 'field_default_value' ), $displayDefaults ),
			'Youtube'   => array( 'media_params', 'media_display_listing_method', 'media_display_display_method', 'field_unique' ),
			'Spotify'   => array( 'media_params', 'media_display_listing_method', 'media_display_display_method', 'field_unique' ),
			'Soundcloud'=> array( 'media_params', 'media_display_listing_method', 'media_display_display_method', 'field_unique' )
		);
		
		foreach( static::$filterableFields as $field )
		{
			$toggles[ $field ][] = 'field_filter';
		}

		foreach ( static::$additionalFieldTypes as $k => $v )
		{
			$toggles[ $k ] = isset( static::$additionalFieldToggles[ $k ] ) ? static::$additionalFieldToggles[ $k ] : array( 'pf_not_null' );
		}
		
		/* Title or content? */
		$isTitleField	= FALSE;

		if ( $this->id )
		{
			$database = \IPS\cms\Databases::load( static::$customDatabaseId );
		
			if ( $this->id == $database->field_title )
			{
				$isTitleField	= TRUE;

				foreach( static::$cannotBeTitleFields as $type )
				{
					unset( $options[ $type ] );
					unset( $toggles[ $type ] );
				}
			}
			else if ( $this->id == $database->field_content )
			{
				foreach( static::$cannotBeContentFields as $type )
				{
					unset( $options[ $type ] );
					unset( $toggles[ $type ] );
				}
			}
		}
		
		ksort( $options );

		if ( !$this->_new )
		{
			\IPS\Member::loggedIn()->language()->words['field_type_warning'] = \IPS\Member::loggedIn()->language()->addToStack('custom_field_change');

			foreach ( $toggles as $k => $_toggles )
			{
				if ( !$this->canKeepValueOnChange( $k ) )
				{
					$toggles[ $k ][] = 'form_' . $this->id . '_field_type_warning';
				}
			}
		}

		$form->add( new \IPS\Helpers\Form\Select( 'field_type', $this->id ? mb_ucfirst( $this->type ) : 'Text', TRUE, array(
				'options' => $options,
				'toggles' => $toggles
		) ) );

		/* Relational specific */
		if( !$isTitleField )
		{
			$databases = array();
			$disabled  = array();
			foreach( \IPS\cms\Databases::databases() as $db )
			{
				if ( $db->page_id )
				{
					$databases[ $db->id ] = $db->_title;
				}
				else
				{
					$disabled[] = $db->id;
					$databases[ $db->id ] = \IPS\Member::loggedIn()->language()->addToStack( 'cms_db_relational_with_name_disabled', FALSE, array( 'sprintf' => array( $db->_title ) ) );
				}
			}
			if ( ! \count( $databases ) )
			{
				$databases[0] = \IPS\Member::loggedIn()->language()->addToStack('cms_relational_field_no_dbs');
				$disabled[] = 0;
			}

			$form->add( new \IPS\Helpers\Form\Select( 'field_relational_db', ( isset( $this->extra['database'] ) ? $this->extra['database'] : NULL ), FALSE, array( 'options' => $databases, 'disabled' => $disabled ), NULL, NULL, NULL, 'field_relational_db' ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'field_crosslink', $this->id ? ( ( isset( $this->extra['crosslink'] ) and $this->extra['crosslink'] ) ? TRUE : FALSE ) : FALSE, FALSE, array(), NULL, NULL, NULL, 'field_crosslink' ) );
		}

		/* Number specific */
		$form->add( new \IPS\Helpers\Form\Number( 'field_number_min', $this->id and isset( $this->extra['min'] ) ? $this->extra['min'] : NULL, FALSE, array( 'unlimited' => '', 'unlimitedLang' => 'any' ), NULL, NULL, NULL, 'field_number_min' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'field_number_max', $this->id and isset( $this->extra['max'] ) ? $this->extra['max'] : NULL, FALSE, array( 'unlimited' => '', 'unlimitedLang' => 'any' ), NULL, NULL, NULL, 'field_number_max' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'field_number_decimals_on', $this->id ? ( ( isset( $this->extra['on'] ) and $this->extra['on'] ) ? TRUE : FALSE ) : FALSE, FALSE, array( 'togglesOn' => array( 'field_number_decimals' ) ), NULL, NULL, NULL, 'field_number_decimals_on' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'field_number_decimals', $this->id ? ( ( isset( $this->extra['places'] ) and $this->extra['places'] ) ? $this->extra['places'] : 0 ) : 0, FALSE, array( 'max' => 6 ), NULL, NULL, NULL, 'field_number_decimals' ) );

		/* Upload specific */
		$form->add( new \IPS\Helpers\Form\YesNo( 'field_upload_is_multiple', $this->id ? $this->is_multiple : 0, FALSE, array( ), NULL, NULL, NULL, 'field_upload_is_multiple' ) );

		$form->add( new \IPS\Helpers\Form\Radio( 'field_upload_is_image', $this->id ? ( ( isset( $this->extra['type'] ) and $this->extra['type'] == 'image' ) ? 'yes' : 'no' ) : 'yes', TRUE, array(
			'options'	=> array(
				'yes' => 'cms_upload_field_is_image',
				'no'  => 'cms_upload_field_is_not_image',

			),
			'toggles' => array(
				'yes' => array( 'field_image_size', 'field_upload_thumb' ),
				'no'  => array( 'field_allowed_extensions' )
			)
		), NULL, NULL, NULL, 'field_upload_is_image' ) );

		$widthHeight = NULL;
		$thumbWidthHeight = array( 0, 0 );
		if ( isset( $this->extra['type'] ) and $this->extra['type'] === 'image' )
		{
			$widthHeight = $this->extra['maxsize'];
			
			if ( isset( $this->extra['thumbsize'] ) )
			{
				$thumbWidthHeight = $this->extra['thumbsize'];
			}
		}

		$form->add( new \IPS\Helpers\Form\WidthHeight( 'field_image_size', $this->id ? $widthHeight : array( 0, 0 ), FALSE, array( 'resizableDiv' => FALSE, 'unlimited' => array( 0, 0 ) ), NULL, NULL, NULL, 'field_image_size' ) );
		
		$form->add( new \IPS\Helpers\Form\WidthHeight( 'field_upload_thumb', $this->id ? $thumbWidthHeight : array( 0, 0 ), FALSE, array( 'resizableDiv' => FALSE, 'unlimited' => array( 0, 0 ), 'unlimitedLang' => 'field_upload_thumb_none' ), NULL, NULL, NULL, 'field_upload_thumb' ) );
		
		$form->add( new \IPS\Helpers\Form\Text( 'field_allowed_extensions', $this->id ? ( $this->allowed_extensions ?: NULL ) : NULL, FALSE, array(
			'autocomplete' => array( 'unique' => 'true' ),
			'nullLang'     => 'content_any_extensions'
		), NULL, NULL, NULL, 'field_allowed_extensions' ) );

		/* Editor Specific */
		if( !$isTitleField )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'field_allow_attachments', $this->id ? $this->allow_attachments : 1, FALSE, array( ), NULL, NULL, NULL, 'field_allow_attachments' ) );
		}

		/* Date specific */
		$tzValue = 'UTC';
		if ( isset( $this->extra['timezone'] ) and $this->extra['timezone'] )
		{
			if ( ! \in_array( $this->extra['timezone'], array( 'UTC', 'user' ) ) )
			{
				 $tzValue = 'set';
			}
			else
			{
				$tzValue = $this->extra['timezone'];
			}
		}
		
		$form->add( new \IPS\Helpers\Form\Radio( 'field_date_time_override', $tzValue, FALSE, array(
			'options' => array(
				'UTC'  => 'field_date_tz_utc',
				'set'  => 'field_date_tz_set',
				'user' => 'field_date_tz_user'
			),
			'toggles' => array(
				'set' => array( 'field_date_timezone' )
			)
		), NULL, NULL, NULL, 'field_date_time_override' ) );
			
		$timezones = array();
		foreach ( \IPS\DateTime::getTimezoneIdentifiers() as $tz )
		{
			if ( $pos = mb_strpos( $tz, '/' ) )
			{
				$timezones[ 'timezone__' . mb_substr( $tz, 0, $pos ) ][ $tz ] = 'timezone__' . $tz;
			}
			else
			{
				$timezones[ $tz ] = 'timezone__' . $tz;
			}
		}
		$form->add( new \IPS\Helpers\Form\Select( 'field_date_timezone', ( isset( $this->extra['timezone'] ) ? $this->extra['timezone'] : \IPS\Member::loggedIn()->timezone ), FALSE, array( 'options' => $timezones ), NULL, NULL, NULL, 'field_date_timezone' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'field_date_time_time', ( isset( $this->extra['time'] ) ? $this->extra['time'] : 0 ), FALSE, array(), NULL, NULL, NULL, 'field_date_time_time' ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'field_is_multiple', $this->id ? $this->is_multiple : 0, FALSE, array(), NULL, NULL, NULL, 'field_is_multiple' ) );
		
		$form->add( new \IPS\Helpers\Form\TextArea( 'field_default_value', $this->id ? $this->default_value : '', FALSE, array(), NULL, NULL, NULL, 'field_default_value' ) );

		if ( ! $this->_new )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'field_default_update_existing', FALSE, FALSE, array(), NULL, NULL, NULL, 'field_default_update_existing' ) );
		}

		$form->add( new \IPS\Helpers\Form\Number( 'field_max_length', $this->id ? $this->max_length : NULL, FALSE, array( 'unlimited' => 0 ), NULL, NULL, NULL, 'field_max_length' ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'field_validator', $this->id ? \intval( $this->validator ) : 0, FALSE, array(
			'togglesOn' =>array( 'field_validator_custom', 'field_validator_error' )
		), NULL, NULL, NULL, 'field_validator' ) );
		
		$form->add( new \IPS\Helpers\Form\Text( 'field_validator_custom', $this->id ? $this->validator_custom : NULL, FALSE, array( 'placeholder' => '/[A-Z0-9]+/i' ), NULL, NULL, NULL, 'field_validator_custom' ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'field_validator_error', NULL, FALSE, array( 'app' => 'core', 'key' => ( $this->id ? static::$langKey . '_' . $this->id . '_validation_error' : NULL ) ), NULL, NULL, NULL, 'field_validator_error' ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'field_format_opts_on', $this->id ? $this->format_opts : 0, FALSE, array( 'togglesOn' => array('field_format_opts') ), NULL, NULL, NULL, 'field_format_opts_on' ) );
		
		$form->add( new \IPS\Helpers\Form\Select( 'field_format_opts', $this->id ? $this->format_opts : 'none', FALSE, array(
				'options' => array(
						'strtolower' => 'content_format_strtolower',
						'strtoupper' => 'content_format_strtoupper',
						'ucfirst'    => 'content_format_ucfirst',
						'ucwords'    => 'content_format_ucwords',
						'punct'	     => 'content_format_punct',
						'numerical'	 => 'content_format_numerical'
				),
				'multiple' => true
		), NULL, NULL, NULL, 'field_format_opts' ) );
		
		$extra = array();
		if ( $this->id AND $this->extra )
		{
			foreach( $this->extra as $k => $v )
			{
				$extra[] = array( 'key' => $k, 'value' => $v );
			}
		}
		
		$form->add( new \IPS\Helpers\Form\Stack( 'field_extra', $extra, FALSE, array( 'stackFieldType' => 'KeyValue'), NULL, NULL, NULL, 'field_extra' ) );

		/* Media specific stack */
		$form->add( new \IPS\Helpers\Form\Stack( 'media_params', $extra, FALSE, array( 'stackFieldType' => 'KeyValue'), NULL, NULL, NULL, 'media_params' ) );

		$form->addheader( 'pfield_options' );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'field_unique', $this->id ? $this->unique : 0, FALSE, array(), NULL, NULL, NULL, 'field_unique' ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'field_filter', $this->id ? $this->filter : 0, FALSE, array(), NULL, NULL, NULL, 'field_filter' ) );

		/* Until we have a mechanism for other field searching, remove this $form->add( new \IPS\Helpers\Form\YesNo( 'field_is_searchable', $this->id ? $this->is_searchable : 0, FALSE, array(), NULL, NULL, NULL, 'field_is_searchable' ) );*/

		if ( isset( \IPS\Request::i()->database_id ) )
		{
			$usingForum = \IPS\cms\Databases::load( \IPS\Request::i()->database_id )->forum_record;
			if ( ! $usingForum and $this->id )
			{
				$usingForum = \IPS\Db::i()->select( 'COUNT(*)', 'cms_database_categories', array( 'category_database_id=? and category_forum_override=1 and category_forum_record=1', $this->id ) )->first();
			}
			
			if ( $usingForum )
			{
				$form->add( new \IPS\Helpers\Form\TextArea( 'field_topic_format', $this->id ? $this->topic_format : '', FALSE, array( 'placeholder' => "<strong>{title}:</strong> {value}" ) ) );
			}
		}

		if ( !$isTitleField )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'field_required', $this->id ? $this->required : TRUE, FALSE ) );
		}

		$form->add( new \IPS\Helpers\Form\YesNo( 'field_html', $this->id ? $this->html : FALSE, FALSE, array( 'togglesOn' => array( 'field_html_warning' ) ), NULL, NULL, NULL, 'field_html' ) );

		$form->addTab( 'field_displayoptions' );

		$isTitleOrContent = FALSE;
		if ( $this->id AND ( $this->id == \IPS\cms\Databases::load( static::$customDatabaseId )->field_title OR $this->id == \IPS\cms\Databases::load( static::$customDatabaseId )->field_content ) )
		{
			$isTitleOrContent = TRUE;

			if ( $this->id == \IPS\cms\Databases::load( static::$customDatabaseId )->field_title )
			{
				$form->addMessage( 'field_display_opts_title', 'ipsMessage ipsMessage_info' );
			}

			if ( $this->id == \IPS\cms\Databases::load( static::$customDatabaseId )->field_content )
			{
				$form->addMessage( 'field_display_opts_content', 'ipsMessage ipsMessage_info' );
			}
		}

		$form->add( new \IPS\Helpers\Form\Text( 'field_key', $this->id ? $this->key : FALSE, FALSE, array(), function( $val )
		{
			try
			{
				if ( ! $val )
				{
					return true;
				}

				$class = '\IPS\cms\Fields' . \IPS\Request::i()->database_id;

				try
				{
					$testField = $class::load( $val, 'field_key');
				}
				catch( \OutOfRangeException $ex )
				{
					/* Doesn't exist? Good! */
					return true;
				}

				/* It's taken... */
				if ( \IPS\Request::i()->id == $testField->id )
				{
					/* But it's this one so that's ok */
					return true;
				}

				/* and if we're here, it's not... */
				throw new \InvalidArgumentException('cms_field_key_not_unique');
			}
			catch ( \OutOfRangeException $e )
			{
				/* Slug is OK as load failed */
				return true;
			}

			return true;
		} ) );

		$displayToggles = array( 'custom' => array( 'field_display_display_json_custom' ) );
		$listingToggles = array( 'custom' => array( 'field_display_listing_json_custom' ) );
		$displayJson    = $this->display_json;
		$displayDefault = isset( $displayJson['display']['method'] ) ? $displayJson['display']['method'] : '1';
		$listingDefault = isset( $displayJson['listing']['method'] ) ? $displayJson['listing']['method'] : '1';
		$mediaDisplayDefault = isset( $displayJson['display']['method'] ) ? $displayJson['display']['method'] : 'player';
		$mediaListingDefault = isset( $displayJson['listing']['method'] ) ? $displayJson['listing']['method'] : 'url';
		$mapDisplay = isset( $displayJson['display']['map'] ) ? $displayJson['display']['map'] : FALSE;
		$mapListing = isset( $displayJson['listing']['map'] ) ? $displayJson['listing']['map'] : FALSE;
		$mapDisplayDims = isset( $displayJson['display']['mapDims'] ) ? $displayJson['display']['mapDims'] : array( 200, 200 );
		$mapListingDims = isset( $displayJson['listing']['mapDims'] ) ? $displayJson['listing']['mapDims'] : array( 100, 100 );
		$listingOptions = $displayOptions = array();

		foreach( range( 1, 7 ) as $id )
		{
			$displayOptions[ $id ] = $listingOptions[ $id ] = \IPS\Theme::i()->getTemplate( 'records', 'cms', 'global' )->fieldBadge( \IPS\Member::loggedIn()->language()->addToStack('cms_badge_label'), \IPS\Member::loggedIn()->language()->addToStack('cms_badge_value'), 'ipsBadge_front ipsBadge_style' . $id );
			$listingToggles[ $id ] = array( 'field_display_listing_json_badge_right' );
		}

		$displayOptions['simple'] = $listingOptions['simple'] = \IPS\Theme::i()->getTemplate( 'records', 'cms', 'global' )->fieldDefault( \IPS\Member::loggedIn()->language()->addToStack('cms_badge_label'), \IPS\Member::loggedIn()->language()->addToStack('cms_badge_value' ) );
		$displayOptions['custom'] = $listingOptions['custom'] = \IPS\Member::loggedIn()->language()->addToStack('field_display_custom');
		$displayOptions['none'] = $listingOptions['none']     = \IPS\Member::loggedIn()->language()->addToStack('field_display_none');

		if ( ! $isTitleOrContent )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'field_display_listing', $this->id ? $this->display_listing : 1, FALSE, array(
				'togglesOn' => array('field_truncate', 'field_display_listing_json_badge', 'field_show_map_listing', 'field_show_map_listing_dims' )
			), NULL, NULL, NULL, 'field_display_listing' ) );
		}

		$form->add( new \IPS\Helpers\Form\Radio( 'field_display_listing_json_badge', $listingDefault, FALSE, array( 'options' => $listingOptions, 'toggles' => $listingToggles, 'parse' => 'raw' ), NULL, NULL, NULL, 'field_display_listing_json_badge' ) );

		if ( ! $isTitleOrContent )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'field_display_listing_json_badge_right', ( isset( $displayJson['listing']['right'] ) ? $displayJson['listing']['right'] : 0 ), FALSE, array(), NULL, NULL, NULL, 'field_display_listing_json_badge_right' ) );
		}

		$form->add( new \IPS\Helpers\Form\YesNo( 'field_show_map_listing', $mapListing, FALSE, array(), NULL, NULL, NULL, 'field_show_map_listing' ) );
		$form->add( new \IPS\Helpers\Form\WidthHeight( 'field_show_map_listing_dims', $mapListingDims, FALSE, array( 'resizableDiv' => FALSE ), NULL, NULL, NULL, 'field_show_map_listing_dims' ) );

		$form->add( new \IPS\Helpers\Form\Codemirror( 'field_display_listing_json_custom', ( isset( $displayJson['listing']['html'] ) ? $displayJson['listing']['html'] : NULL ), FALSE, array( 'placeholder' => '{label}: {value}' ), function( $val )
        {
            /* Test */
            try
            {
	            \IPS\Theme::checkTemplateSyntax( $val );
            }
            catch( \LogicException $e )
            {
	            throw new \LogicException('cms_field_error_bad_syntax');
            }

        }, NULL, NULL, 'field_display_listing_json_custom' ) );

		/* Media listing */
		$mediaListingOptions = array( 'player' => 'media_display_as_player', 'url' => 'media_display_as_url' );
		$form->add( new \IPS\Helpers\Form\Radio( 'media_display_listing_method', $mediaListingDefault, FALSE, array( 'options' => $mediaListingOptions ), NULL, NULL, NULL, 'media_display_listing_method' ) );

		if ( ! $isTitleOrContent )
		{
			$form->add( new \IPS\Helpers\Form\Number( 'field_truncate', $this->id ? $this->truncate : 0, FALSE, array( 'unlimited' => 0 ), NULL, NULL, NULL, 'field_truncate' ) );
		}

		$form->addSeparator();

		if ( ! $isTitleOrContent )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'field_display_display', $this->id ? $this->display_display : 1, FALSE, array(
				'togglesOn' => array( 'field_display_display_json_badge', 'field_show_map_display', 'field_show_map_display_dims', 'field_display_display_json_where' )
			), NULL, NULL, NULL, 'field_display_display' ) );
		}

		$form->add( new \IPS\Helpers\Form\Radio( 'field_display_display_json_badge', $displayDefault, FALSE, array( 'options' => $displayOptions, 'toggles' => $displayToggles, 'parse' => 'raw' ), NULL, NULL, NULL, 'field_display_display_json_badge' ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'field_show_map_display', $mapDisplay, FALSE, array(), NULL, NULL, NULL, 'field_show_map_display' ) );
		$form->add( new \IPS\Helpers\Form\WidthHeight( 'field_show_map_display_dims', $mapDisplayDims, FALSE, array( 'resizableDiv' => FALSE ), NULL, NULL, NULL, 'field_show_map_display_dims' ) );

		$form->add( new \IPS\Helpers\Form\Codemirror( 'field_display_display_json_custom', ( isset( $displayJson['display']['html'] ) ? $displayJson['display']['html'] : NULL ), FALSE, array( 'placeholder' => '{label}: {value}' ), function( $val )
        {
            /* Test */
            try
            {
	            \IPS\Theme::checkTemplateSyntax( $val );
            }
            catch( \LogicException $e )
            {
	            throw new \LogicException('cms_field_error_bad_syntax');
            }

        }, NULL, NULL, 'field_display_display_json_custom' ) );

		/* Display where? */
		$form->add( new \IPS\Helpers\Form\Radio( 'field_display_display_json_where', ( isset( $displayJson['display']['where'] ) ? $displayJson['display']['where'] : 'top' ), FALSE, array( 'options' => array( 'top' => 'cms_field_display_top', 'bottom' => 'cms_field_display_bottom' ) ), NULL, NULL, NULL, 'field_display_display_json_where' ) );

		/* Media display */
		$form->add( new \IPS\Helpers\Form\Radio( 'media_display_display_method', $mediaDisplayDefault, FALSE, array( 'options' => $mediaListingOptions ), NULL, NULL, NULL, 'media_display_display_method' ) );

		$form->addSeparator();

		$form->add( new \IPS\Helpers\Form\YesNo( 'field_display_commentform', $this->id ? $this->display_commentform : 0, FALSE, array(), NULL, NULL, NULL, 'field_display_commentform' ) );
		\IPS\Output::i()->globalControllers[]  = 'cms.admin.fields.form';
		\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_fields.js', 'cms' ) );

		\IPS\Output::i()->title  = ( $this->id ) ? \IPS\Member::loggedIn()->language()->addToStack('cms_edit_field', FALSE, array( 'sprintf' => array( $this->_title ) ) ) : \IPS\Member::loggedIn()->language()->addToStack('cms_add_field');
	}

	/**
	 * @brief	Disable the copy button - useful when the forms are very distinctly different
	 */
	public $noCopyButton	= TRUE;

	/**
	 * @brief	Update the default value in records
	 */
	protected $_updateDefaultValue = FALSE;

	/**
	 * @brief	Stores the old default value after a change
	 */
	protected $_oldDefaultValue = NULL;

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @todo	Separate out the need for `$this->save()` to be called by moving the database table field creation to postSaveForm()
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		static::$contentDatabaseTable = 'cms_custom_database_' . static::$customDatabaseId;

		$values['field_max_length'] = ( isset( $values['field_max_length'] ) ) ? \intval( $values['field_max_length'] ) : 0;
		
		/* Work out the column definition */
		if( isset( $values['field_type'] ) )
		{
			$columnDefinition = array( 'name' => "field_{$this->id}" );
			switch ( $values['field_type'] )
			{
				case 'CheckboxSet':
				case 'Member':
				case 'Radio':
				case 'Select':
					/* Reformat keyValue pairs */
					if ( isset( $values['field_extra'] ) AND \is_array( $values['field_extra'] ) )
					{
						$extra = array();
						foreach( $values['field_extra'] as $row )
						{
							if ( isset( $row['key'] ) )
							{
								$extra[ $row['key'] ] = $row['value'];
							}
						}

						if ( \count( $extra ) )
						{
							$values['field_extra'] = $extra;
						}
					}
					if ( $values['field_type'] === 'Select' )
					{
						$columnDefinition['type'] = 'TEXT';
					}
					else
					{
						$columnDefinition['type']	= 'VARCHAR';
						$columnDefinition['length']	= 255;
					}
					break;
				case 'Youtube':
				case 'Spotify':
				case 'Soundcloud':
					/* Reformat keyValue pairs */
					if ( isset( $values['media_params'] ) AND \is_array( $values['media_params'] ) )
					{
						$extra = array();
						foreach( $values['media_params'] as $row )
						{
							if ( isset( $row['key'] ) )
							{
								$extra[ $row['key'] ] = $row['value'];
							}
						}

						if ( \count( $extra ) )
						{
							$values['field_extra'] = $extra;
						}
					}
					$columnDefinition['type'] = 'TEXT';
					break;
				case 'Date':
					$columnDefinition['type'] = 'INT';
					$columnDefinition['length'] = 10;
					$values['field_extra'] = '';
					$values['default_value'] = ( isset( $values['default_value'] ) ) ? (int) $values['default_value'] : NULL;
					break;
				case 'Number':
					if ( isset( $values['field_number_decimals_on'] ) and $values['field_number_decimals_on'] and $values['field_number_decimals'] )
					{
						$columnDefinition['type'] = 'DECIMAL(20,' . $values['field_number_decimals'] . ')';
						$values['field_extra'] = '';
					}
					else
					{
						$columnDefinition['type'] = 'VARCHAR';
						$columnDefinition['length'] = 255;
						$values['field_extra'] = '';
						break;
					}
					break;
				case 'YesNo':
					$columnDefinition['type'] = 'INT';
					$columnDefinition['length'] = 10;
					$values['field_extra'] = '';
					break;
				
				case 'Address':
				case 'Codemirror':
				case 'Editor':
				case 'TextArea':
				case 'Upload':
					$columnDefinition['type'] = 'MEDIUMTEXT';
					$values['field_extra'] = '';
					break;
				
				case 'Email':
				case 'Password':
				case 'Tel':
				case 'Text':
				case 'Url':
				case 'Checkbox':
					if ( !isset( $values['field_max_length'] ) OR !$values['field_max_length'] )
					{
						$columnDefinition['type'] = 'MEDIUMTEXT';
						unset( $columnDefinition['length'] );
					}
					else
					{
						$columnDefinition['type'] = 'VARCHAR';
						$columnDefinition['length'] = 255;
					}

					$values['field_extra'] = '';
					break;
				default:
					$columnDefinition['type'] = 'TEXT';
					break;
			}
			
			if ( ! empty( $values['field_max_length'] ) )
			{
				if( $values['field_max_length'] > 255 )
				{
					$columnDefinition['type'] = 'MEDIUMTEXT';

					if( isset( $columnDefinition['length'] ) )
					{
						unset( $columnDefinition['length'] );
					}
				}
				else
				{
					$columnDefinition['length'] = $values['field_max_length'];
				}
			}
			else if ( empty( $columnDefinition['length'] ) )
			{
				$columnDefinition['length'] = NULL;
			}
		}

		if ( isset( $values['media_params'] ) )
		{
			unset( $values['media_params'] );
		}

		/* Add/Update the content table */
		if ( !$this->id )
		{
			/* field key cannot be null, so we assign a temporary key here which is overwritten below */
			$this->key = md5( mt_rand() );
			$this->database_id = static::$customDatabaseId;
			$values['database_id']	= $this->database_id;
			
			$this->save();
			
			$columnDefinition['name'] = "field_{$this->id}";
			
			if ( isset( static::$contentDatabaseTable ) )
			{
				try
				{
					\IPS\Db::i()->addColumn( static::$contentDatabaseTable, $columnDefinition );
				}
				catch( \IPS\Db\Exception $e )
				{
					if ( $e->getCode() === 1118 )
					{
						# 1118 is thrown when there are too many varchar columns in a single table. BLOBs and TEXT fields do not add to this limit
						$columnDefinition['length'] = NULL;
						$columnDefinition['type'] = 'TEXT';
						
						\IPS\Db::i()->addColumn( static::$contentDatabaseTable, $columnDefinition );
					}
				}
				if ( ! empty( $values['field_filter'] ) and $values['field_type'] != 'Upload' )
				{
					try
					{
						if ( \in_array( $columnDefinition['type'], array( 'TEXT', 'MEDIUMTEXT' ) ) )
						{
							\IPS\Db::i()->addIndex( static::$contentDatabaseTable, array( 'type' => 'fulltext', 'name' => "field_{$this->id}", 'columns' => array( "field_{$this->id}" ) ) );
						}
						else
						{
							\IPS\Db::i()->addIndex( static::$contentDatabaseTable, array( 'type' => 'key', 'name' => "field_{$this->id}", 'columns' => array( "field_{$this->id}" ) ) );
						}
					}
					catch( \IPS\Db\Exception $e )
					{
						if ( $e->getCode() !== 1069 )
						{
							# 1069: MyISAM can only have 64 indexes per table. This should be really rare though so we silently ignore it.
							throw $e;
						}
					}
				}
			}
		}
		elseif( !$this->canKeepValueOnChange( $values['field_type'] ) )
		{
			try
			{
				/* Drop the index if it exists */
				if( \IPS\Db::i()->checkForIndex( static::$contentDatabaseTable, "field_{$this->id}" ) )
				{
					\IPS\Db::i()->dropIndex( static::$contentDatabaseTable, "field_{$this->id}" );
				}
				\IPS\Db::i()->dropColumn( static::$contentDatabaseTable, "field_{$this->id}" );
			}
			catch ( \IPS\Db\Exception $e ) { }

			\IPS\Db::i()->addColumn( static::$contentDatabaseTable, $columnDefinition );

			if ( $values['field_type'] != 'Upload' )
			{
				try
				{
					if ( \in_array( $columnDefinition['type'], array( 'TEXT', 'MEDIUMTEXT' ) ) )
					{
						\IPS\Db::i()->addIndex( static::$contentDatabaseTable, array( 'type' => 'fulltext', 'name' => "field_{$this->id}", 'columns' => array( "field_{$this->id}" ) ) );
					}
					else
					{
						\IPS\Db::i()->addIndex( static::$contentDatabaseTable, array( 'type' => 'key', 'name' => "field_{$this->id}", 'columns' => array( "field_{$this->id}" ) ) );
					}
				}
				catch( \IPS\Db\Exception $e )
				{
					if ( $e->getCode() !== 1069 )
					{
						# 1069: MyISAM can only have 64 indexes per table. This should be really rare though so we silently ignore it.
						throw $e;
					}
				}
			}
		}
		elseif ( isset( static::$contentDatabaseTable ) AND isset( $columnDefinition ) )
		{
			try
			{
				/* Drop the index if it exists */
				if( \IPS\Db::i()->checkForIndex( static::$contentDatabaseTable, "field_{$this->id}" ) )
				{
					\IPS\Db::i()->dropIndex( static::$contentDatabaseTable, "field_{$this->id}" );
				}
				\IPS\Db::i()->changeColumn( static::$contentDatabaseTable, "field_{$this->id}", $columnDefinition );
			}
			catch ( \IPS\Db\Exception $e ) { }

			if ( $values['field_filter'] and $values['field_type'] != 'Upload' )
			{
				try
				{
					if ( \in_array( $columnDefinition['type'], array( 'TEXT', 'MEDIUMTEXT' ) ) )
					{
						\IPS\Db::i()->addIndex( static::$contentDatabaseTable, array( 'type' => 'fulltext', 'name' => "field_{$this->id}", 'columns' => array( "field_{$this->id}" ) ) );
					}
					else
					{
						\IPS\Db::i()->addIndex( static::$contentDatabaseTable, array( 'type' => 'key', 'name' => "field_{$this->id}", 'columns' => array( "field_{$this->id}" ) ) );
					}
				}
				catch( \IPS\Db\Exception $e )
				{
					if ( $e->getCode() !== 1069 )
					{
						# 1069: MyISAM can only have 64 indexes per table. This should be really rare though so we silently ignore it.
						throw $e;
					}
				}
			}
		}
		
		/* Save the name and desctipn */
		if( isset( $values['field_title'] ) )
		{
			\IPS\Lang::saveCustom( 'cms', static::$langKey . '_' . $this->id, $values['field_title'] );
		}
		
		if ( isset( $values['field_description'] ) )
		{
			\IPS\Lang::saveCustom( 'cms', static::$langKey . '_' . $this->id . '_desc', $values['field_description'] );
			unset( $values['field_description'] );
		}
		
		if ( array_key_exists( 'field_validator_error', $values ) )
		{
			\IPS\Lang::saveCustom( 'cms', static::$langKey . '_' . $this->id . '_validation_error', $values['field_validator_error'] );
			unset( $values['field_validator_error'] );
		}

		if ( isset( $values['field_format_opts_on'] ) AND ! $values['field_format_opts_on'] )
		{
			$values['field_format_opts'] = NULL;
		}

		if ( isset( $values['field_key'] ) AND ! $values['field_key'] )
		{
			if ( \is_array( $values['field_title'] ) )
			{
				/* We need to make sure the internal pointer on the array is on the first element */
				reset( $values['field_title'] );
				$values['field_key'] = \IPS\Http\Url\Friendly::seoTitle( $values['field_title'][ key( $values['field_title'] ) ] );
			}
			else
			{
				$values['field_key'] = \IPS\Http\Url\Friendly::seoTitle( $values['field_title'] );
			}

			/* Now test it */
			$class = '\IPS\cms\Fields' . \IPS\Request::i()->database_id;

			try
			{
				$testField = $class::load( $this->key, 'field_key');

				/* It's taken... */
				if ( $this->id != $testField->id )
				{
					$this->key .= '_' . mt_rand();
				}
			}
			catch( \OutOfRangeException $ex )
			{
				/* Doesn't exist? Good! */
			}
		}

		if( isset( $values['field_type'] ) AND ( !isset( $values['_skip_formatting'] ) OR $values['_skip_formatting'] !== TRUE ) )
		{
			$displayJson = array( 'display' => array( 'method' => NULL ), 'listing' => array( 'method' => NULL ) );

			/* Listing */
			if ( \in_array( $values['field_type'], static::$mediaFields ) )
			{
				$displayJson['listing']['method'] = $values['media_display_listing_method'];
				$displayJson['display']['method'] = $values['media_display_display_method'];
			}
			else
			{
				if ( $values['field_type'] === 'Address' )
				{
					if ( isset( $values['field_show_map_listing'] ) )
					{
						$displayJson['listing']['map'] = (boolean) $values['field_show_map_listing'];
					}

					if ( isset( $values['field_show_map_listing_dims'] ) )
					{
						$displayJson['listing']['mapDims'] = $values['field_show_map_listing_dims'];
					}

					if ( isset( $values['field_show_map_display'] ) )
					{
						$displayJson['display']['map'] = (boolean) $values['field_show_map_display'];
					}

					if ( isset( $values['field_show_map_display_dims'] ) )
					{
						$displayJson['display']['mapDims'] = $values['field_show_map_display_dims'];
					}
				}

				if ( isset( $values['field_display_listing_json_badge'] ) )
				{
					if( isset( $values['field_display_listing_json_custom'] ) )
					{
						$displayJson['listing']['html'] = $values['field_display_listing_json_custom'];
						unset( $values['field_display_listing_json_custom'] );
					}
					else
					{
						$displayJson['listing']['html'] = NULL;
					}

					if ( $values['field_display_listing_json_badge'] === 'custom' )
					{
						$displayJson['listing']['method'] = 'custom';
					}
					else
					{
						$displayJson['listing']['method'] = $values['field_display_listing_json_badge'];
						$displayJson['listing']['right']  = isset( $values['field_display_listing_json_badge_right'] ) ? $values['field_display_listing_json_badge_right'] : FALSE;
					}
				}

				/* Display */
				if ( isset( $values['field_display_display_json_badge'] ) )
				{
					if( isset( $values['field_display_display_json_custom'] ) )
					{
						$displayJson['display']['html'] = $values['field_display_display_json_custom'];
						unset( $values['field_display_display_json_custom'] );
					}
					else
					{
						$displayJson['display']['html'] = NULL;
					}

					if ( $values['field_display_display_json_badge'] === 'custom' )
					{
						$displayJson['display']['method'] = 'custom';
					}
					else
					{
						$displayJson['display']['method'] = $values['field_display_display_json_badge'];
					}

					if ( isset( $values['field_display_display_json_where'] ) )
					{
						$displayJson['display']['where'] = $values['field_display_display_json_where'];
					}
				}
			}

			$values['display_json'] = json_encode( $displayJson );
		}

		/* If we are importing a database we skip the json formatting as it gets set after */
		if( array_key_exists( '_skip_formatting', $values ) )
		{
			unset( $values['_skip_formatting'] );
		}

		/* Special upload stuffs */
		if ( isset( $values['field_type'] ) AND $values['field_type'] === 'Upload' )
		{
			if ( isset( $values['field_upload_is_image'] ) and $values['field_upload_is_image'] === 'yes')
			{
				$values['extra'] = array( 'type' => 'image', 'maxsize' => $values['field_image_size'] );
				
				if ( $values['field_upload_thumb'][0] > 0 )
				{
					$values['extra']['thumbsize'] = $values['field_upload_thumb'];
				}
			}
			else
			{
				$values['extra'] = array( 'type' => 'any' );
			}

			if ( isset( $values['field_upload_is_multiple'] ) and $values['field_upload_is_multiple'] )
			{
				$values['field_is_multiple'] = 1;
			}
			else
			{
				$values['field_is_multiple'] = 0;
			}

			$values['field_default_value'] = NULL;
		}

		/* Special date stuff */
		if ( isset( $values['field_type'] ) AND $values['field_type'] === 'Date' )
		{
			$values['extra'] = array();
			if ( isset( $values['field_date_time_override'] ) )
			{
				if ( $values['field_date_time_override'] === 'set' )
				{
					$values['extra']['timezone'] = $values['field_date_timezone'];
				}
				else
				{
					$values['extra']['timezone'] = $values['field_date_time_override'];
				}
			}
			
			if ( isset( $values['field_date_time_time'] ) )
			{
				$values['extra']['time'] = $values['field_date_time_time'];
			}
		}

		/* Special relational stuff */
		if ( isset( $values['field_type'] ) AND $values['field_type'] === 'Item' )
		{
			if ( array_key_exists( 'field_relational_db', $values ) and empty( $values['field_relational_db'] ) )
			{
				throw new \LogicException( \IPS\Member::loggedIn()->language()->addToStack('cms_relational_field_no_db_selected') );
			}
			
			if ( isset( $values['field_relational_db'] ) )
			{
				$values['extra'] = array( 'database' => $values['field_relational_db'] );
			}
			
			if ( array_key_exists( 'field_crosslink', $values ) and ! empty( $values['field_crosslink'] ) )
			{
				$values['extra']['crosslink'] = (boolean) $values['field_crosslink'];
			}
			
			/* Best remove the stored data incase the crosslink setting changed */
			unset( \IPS\Data\Store::i()->database_reciprocal_links );
		}
		
		/* Special number stuff */
		if ( isset( $values['field_type'] ) AND $values['field_type'] === 'Number' AND ( isset( $values['field_number_decimals_on'] ) or isset( $values['field_number_min'] ) or isset( $values['field_number_max'] ) ) )
		{
			$values['extra'] = array( 'on' => (boolean) $values['field_number_decimals_on'], 'places' => $values['field_number_decimals'], 'min' => $values['field_number_min'], 'max' => $values['field_number_max']  );
		}
		
		/* Remove the filter flag if this field cannot be filtered */
		if ( isset( $values['field_type'] ) AND isset( $values['field_filter'] ) AND $values['field_filter'] and ! \in_array( $values['field_type'], static::$filterableFields ) )
		{
			$values['field_filter'] = false;
		}
		
		if ( ! $this->new AND isset( $values['field_default_update_existing'] ) AND $values['field_default_update_existing'] AND $values['field_default_value'] !== $this->default_value )
		{
			$this->_updateDefaultValue = TRUE;
			$this->_oldDefaultValue    = $this->default_value;
		}

		foreach( array( 'field_crosslink', 'field_number_decimals_on', 'field_number_decimals', 'field_number_min', 'field_number_max', 'field_format_opts_on', 'field_relational_db', 'field_upload_is_multiple', 'field_default_update_existing', 'field_date_time_override', 'field_date_timezone', 'field_date_time_time', 'field_upload_is_image', 'field_image_size', 'field_upload_thumb', 'field_title', 'field_display_display_json_badge', 'field_display_display_json_custom', 'field_display_listing_json_badge', 'field_display_listing_json_custom', 'field_display_listing_json_badge_right', 'media_display_listing_method', 'media_display_display_method', 'field_show_map_listing', 'field_show_map_listing_dims', 'field_show_map_display', 'field_show_map_display_dims', 'field_display_display_json_where' ) as $field )
		{
			if ( array_key_exists( $field, $values ) )
			{
				unset( $values[ $field ] );
			}
		}

		return parent::formatFormValues( $values );
	}

	/**
	 * [Node] Perform actions after saving the form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function postSaveForm( $values )
	{
		/* Ensure it has some permissions */
		$this->permissions();

		if ( $this->_updateDefaultValue )
		{
			static::$contentDatabaseTable = 'cms_custom_database_' . static::$customDatabaseId;

			$field = 'field_' . $this->id;
			\IPS\Db::i()->update( static::$contentDatabaseTable, array( $field => $this->default_value ), array( $field . '=?  OR ' . $field . ' IS NULL', $this->_oldDefaultValue ) );
		}
	}

	/**
	 * Does the change mean wiping the value?
	 *
	 * @param	string	$newType	The new type
	 * @return	array
	 */
	protected function canKeepValueOnChange( $newType )
	{
		$custom = array( 'Youtube', 'Spotify', 'Soundcloud', 'Relational' );

		if ( ! \in_array( $this->type, $custom ) )
		{
			return parent::canKeepValueOnChange( $newType );
		}

		switch ( $this->type )
		{
			case 'Youtube':
				return \in_array( $newType, array( 'Youtube', 'Text', 'TextArea' ) );

			case 'Spotify':
				return \in_array( $newType, array( 'Spotify', 'Text', 'TextArea' ) );

			case 'Soundcloud':
				return \in_array( $newType, array( 'Soundcloud', 'Text', 'TextArea' ) );
		}

		return FALSE;
	}

	/**
	 * [ActiveRecord] Save Record
	 *
	 * @return	void
	 */
	public function save()
	{
		static::$contentDatabaseTable = 'cms_custom_database_' . static::$customDatabaseId;
		static::$cache = array();

		$functionName = $this->fieldTemplateName('listing');

		if ( isset( \IPS\Data\Store::i()->$functionName ) )
		{
			unset( \IPS\Data\Store::i()->$functionName );
		}

		$functionName = $this->fieldTemplateName('display');

		if ( isset( \IPS\Data\Store::i()->$functionName ) )
		{
			unset( \IPS\Data\Store::i()->$functionName );
		}

		parent::save();
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @param	bool	$skipDrop	Skip dropping the column/index, useful when we are deleting the entire table
	 * @return	void
	 */
	public function delete( $skipDrop=FALSE )
	{
		static::$contentDatabaseTable = NULL; // This ensures the parent class doesn't try to drop the column regardless
		static::$cache = array();

		/* Remove reciprocal map data */
		\IPS\Db::i()->delete( 'cms_database_fields_reciprocal_map', array( 'map_origin_database_id=? AND map_field_id=? ', static::$customDatabaseId, $this->id ) );

		parent::delete();

		if( $skipDrop === TRUE )
		{
			return;
		}
		
		if( $this->type == 'Upload' )
		{
			/* Delete thumbnails */
			\IPS\Task::queue( 'core', 'FileCleanup', array( 
				'table'				=> 'cms_database_fields_thumbnails',
				'column'			=> 'thumb_location',
				'storageExtension'	=> 'cms_Records',
				'where'				=> array( array( 'thumb_field_id=?', $this->id ) ),
				'deleteRows'		=> TRUE,
			), 4 );

			/* Delete records */
			\IPS\Task::queue( 'core', 'FileCleanup', array( 
				'table'				=> 'cms_custom_database_' . static::$customDatabaseId,
				'column'			=> 'field_' . $this->id,
				'storageExtension'	=> 'cms_Records',
				'primaryId'			=> 'primary_id_field',
				'dropColumn'		=> 'field_' . $this->id,
				'dropColumnTable'	=> 'cms_custom_database_' . static::$customDatabaseId,
				'multipleFiles'		=> $this->is_multiple
			), 4 );
		}
		else
		{
			try
			{
				\IPS\Db::i()->dropColumn( 'cms_custom_database_' . static::$customDatabaseId, "field_{$this->id}" );
			}
			catch( \IPS\Db\Exception $e ) { }
		}
		
		\IPS\Lang::deleteCustom( 'cms', "content_field_{$this->id}_desc" );
		\IPS\Lang::deleteCustom( 'cms', "content_field_{$this->id}_validation_error" );
	}

	/**
	 * Build Form Helper
	 *
	 * @param	mixed	$value	                    The value
	 * @param	callback	$customValidationCode	Custom validation code
	 * @param   \IPS\cms\Records|NULL   $content     The record
	 * @param	int				        $flags		Bit flags
	 * @return \IPS\Helpers\Form\FormAbstract
	 */
	public function buildHelper( $value=NULL, $customValidationCode=NULL, \IPS\Content $content = NULL, $flags=0 )
	{
		if ( class_exists( '\IPS\cms\Fields\\' . mb_ucfirst( $this->type ) ) )
		{
			/* Is special! */
			$class = '\IPS\cms\Fields\\' . mb_ucfirst( $this->type );
		}
		else if ( class_exists( '\IPS\Helpers\Form\\' . mb_ucfirst( $this->type ) ) )
		{
			$class = '\IPS\Helpers\Form\\' . mb_ucfirst( $this->type );

			if ( !\is_array( $this->extra ) )
			{
				if ( method_exists( $class, 'formatOptions' ) )
				{
					$options = $class->formatOptions( json_decode( $this->extra ) );
				}
				else
				{
					$options = json_decode( $this->extra );
				}
			}
		}
		else
		{
			/* Fail safe */
			$this->type = 'Text';
			$class = '\IPS\Helpers\Form\Text';
		}

		$options    = array();
		switch ( mb_ucfirst( $this->type ) )
		{
			case 'Editor':
				$options['app']         = 'cms';
				$options['key']         = 'Records' . static::$customDatabaseId;
				$options['allowAttachments'] = $this->allow_attachments;
				$options['autoSaveKey'] = 'RecordField_' . ( ( $content === NULL OR !$content->_id ) ? 'new' : $content->_id ) . '_' . $this->id;
				$options['attachIds']   = ( $content === NULL OR !$content->_id ) ? NULL : array( $content->_id, $this->id,  static::$customDatabaseId );
				break;
			case 'Email':
			case 'Password':
			case 'Tel':
			case 'Text':
			case 'TextArea':
			case 'Url':
				$options['maxLength']	= $this->max_length ?: NULL;
				$options['regex']		= $this->input_format ?: NULL;
				break;
			case 'Upload':
				$options['storageExtension'] = static::$uploadStorageExtension;
				$options['canBeModerated'] = static::$uploadsCanBeModerated;

				if ( isset( $this->extra['type'] ) )
				{
					if ( $this->extra['type'] === 'image' )
					{
						$options['allowStockPhotos'] = TRUE;
						$options['image'] = array( 'maxWidth' => $this->extra['maxsize'][0] ?: NULL, 'maxHeight' => $this->extra['maxsize'][1] ?: NULL );
					}
					else
					{
						$options['allowedFileTypes'] = $this->allowed_extensions ?: NULL;
					}
				}
				else
				{
					$options['allowedFileTypes'] = $this->allowed_extensions ?: NULL;
				}

				if ( $this->is_multiple )
				{
					$options['multiple'] = TRUE;
				}

				if( $value and ! \is_array( $value ) )
				{
					if ( mb_strstr( $value, ',' ) )
					{
						$files = explode( ',', $value );

						$return = array();
						foreach( $files as $file )
						{
							try
							{
								$return[] = \IPS\File::get( static::$uploadStorageExtension, $file );
							}
							catch ( \OutOfRangeException $e ) { }
						}

						$value = $return;
					}
					else
					{
						try
						{
							$value = array( \IPS\File::get( static::$uploadStorageExtension, $value ) );
						}
						catch ( \OutOfRangeException $e )
						{
							$value = NULL;
						}
					}
				}
				break;
			case 'Select':
			case 'CheckboxSet':
				$options['multiple'] = ( mb_ucfirst( $this->type ) == 'CheckboxSet' ) ? TRUE : $this->is_multiple;
				
				if ( $flags & self::FIELD_DISPLAY_FILTERS or ( ! $this->default_value and ! $this->required ) )
				{
					$options['noDefault'] = true;
				}

				if ( $flags & self::FIELD_DISPLAY_COMMENTFORM )
				{
					$options['noDefault'] = true;
					$this->required       = false;
				}

				if ( $options['multiple'] and ! \is_array( $value ) )
				{
					$exp   = explode( ',', $value );
					$value = array();
					foreach( $exp as $val )
					{
						if ( \is_numeric( $val ) and \intval( $val ) == $val )
						{
							$value[] = \intval( $val );
						}
						else
						{
							$value[] = $val;
						}
					}
				}
				else
				{
					if ( \is_numeric( $value ) and \intval( $value ) == $value )
					{
						$value = \intval( $value );
					}
				}

				$json = $this->extra;
				$options['options'] = ( $json ) ? $json : array();
				break;
			case 'Radio':
				$json = $this->extra;
				$options['options'] = ( $json ) ? $json : array();
				$options['multiple'] = FALSE;
				break;
			case 'Address':
				$value = \IPS\GeoLocation::buildFromJson( $value );
				break;
			
			case 'Member':
				if ( ! $value )
				{
					$value = NULL;
				}
				
				$options['multiple'] = $this->is_multiple ? NULL : 1;
				
				if ( \is_string( $value ) )
				{
					$value = array_map( function( $id )
					{
						return \IPS\Member::load( \intval( $id ) );
					}, explode( "\n", $value ) );
				}
				
				break;
			case 'Date':
				if ( \is_numeric( $value ) )
				{
					/* We want to normalize based on user time zone here */
					$value = \IPS\DateTime::ts( $value );
				}
				
				if ( isset( $this->extra['timezone'] ) and $this->extra['timezone'] )
				{
					/* The timezone is already set to user by virtue of DateTime::ts() */
					if ( $this->extra['timezone'] != 'user' )
					{
						$options['timezone'] = new \DateTimeZone( $this->extra['timezone'] );
						
						if ( $value instanceof \IPS\DateTime )
						{
							$value->setTimezone( $options['timezone'] );
						}
					}
				}
				/* If we haven't specified a timezone, default back to UTC to normalize the date, so a date of 5/6/2016 doesn't become 5/5/2016 depending
					on who submits and who views */
				else
				{
					$options['timezone'] = new \DateTimeZone( 'UTC' );

					if ( $value instanceof \IPS\DateTime )
					{
						$value->setTimezone( $options['timezone'] );
					}
				}
				
				if ( $this->extra['time'] )
				{
					$options['time'] = true;
				}
				break;
			case 'Item':
				$options['maxItems'] = ( $this->is_multiple ) ? NULL : 1;
				$options['class']    = '\IPS\cms\Records' . $this->extra['database'];
				break;
			case 'Number':
				if ( $this->extra['on'] and $this->extra['places'] )
				{
					$options['decimals'] = $this->extra['places'];
				}

				if ( isset( $this->extra['min'] ) and $this->extra['min'] )
				{
					$options['min'] = $this->extra['min'];
				}

				if ( isset( $this->extra['max'] ) and $this->extra['max'])
				{
					$options['max'] = $this->extra['max'];
				}

				if( !$this->required )
				{
					$options['unlimited'] 		= '';
					$options['unlimitedLang']	= 'cms_number_none';
				}
				break;
		}
		
		if ( $this->validator AND $this->validator_custom )
		{
			switch( mb_ucfirst( $this->type ) )
			{
				case 'Text':
				case 'TextArea':
					if ( $this->unique )
					{
						$field = $this;
						$customValidationCode = function( $val ) use ( $field, $content )
						{
							\call_user_func_array( 'IPS\cms\Fields' . static::$customDatabaseId . '::validateUnique', array( $val, $field, $content ) );
							
							return \call_user_func( 'IPS\cms\Fields' . static::$customDatabaseId . '::validateInput_' . $field->id, $val );
						};
					}
					else
					{
						$customValidationCode = 'IPS\cms\Fields' . static::$customDatabaseId . '::validateInput_' . $this->id;
					}
				break;
			}
		}

		return new $class( 'content_field_' . $this->id, $value, $this->required, $options, $customValidationCode );
	}
	
	
	
	/**
	 * Get output for API
	 *
	 * @param			\IPS\Member|NULL		$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return			array
	 * @apiresponse		int					id					ID number
	 * @apiresponse		string				title				Title
	 * @apiresponse		string|null			description			Description
	 * @apiresponse		string				type				The field type - e.g. "Text", "Editor", "Radio"
	 * @apiresponse		string|null			default				The default value
	 * @apiresponse		bool					required			If the field is required
	 * @apiresponse		object|null			options				If the field has certain options (for example, it is a select field), the possible values
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'id'			=> $this->_id,
			'title'			=> $this->_title,
			'description'	=> $this->_description ?: NULL,
			'type'			=> $this->type,
			'default'		=> $this->default_value ?: NULL,
			'required'		=> (bool) $this->required,
			'options'		=> $this->extra
		);
	}
}