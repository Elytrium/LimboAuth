<?php
/**
 * @brief		Templates Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		25 Feb 2014
 */

namespace IPS\cms;

/* To prevent PHP errors (extending class does not exist) revealing path */

use IPS\cms\Pages\Page;

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Template Model
 * @notes	Rules of a template:
 * user_created: this means the user has creating a new set of templates (via import or Add New)
 * user_edited: this means the user has edited a template so it no longer matches the master template
 *
 * CUSTOM FLAG: True when user_created is true, user_edited is false, original_group != any of the master original_groups (if original group is the same as any of the master groups then it is a default template still)
 * MODIFIED FLAG: True when user_edited is true
 *
 * Updating templates: Update where user_edited is FALSE and user_created is FALSE OR user_created is TRUE and original_group is in master groups
 */
class _Templates extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'template_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Table
	 */
	public static $databaseTable = 'cms_templates';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'template_key', 'template_id' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();

	/**
	 * @brief	Master groups
	 */
	protected static $masterGroups	= NULL;

	/**
	 * @brief	Master templates
	 */
	protected static $masterTemplates = NULL;
		
	/**
	 * @brief	Retusn all types
	 */
	const RETURN_ALL = 1;
	
	/**
	 * @brief	Returns block templates
	 */
	const RETURN_BLOCK = 2;
	
	/**
	 * @brief	Return page templates
	 */
	const RETURN_PAGE = 4;
	
	/**
	 * @brief	Return database templates
	 */
	const RETURN_DATABASE = 8;

	/**
	 * @brief	Return just css type
	 */
	const RETURN_ONLY_CSS = 16;

	/**
	 * @brief	Return just js type
	 */
	const RETURN_ONLY_JS = 32;

	/**
	 * @brief	Return just template type
	 */
	const RETURN_ONLY_TEMPLATE = 64;

	/**
	 * @brief	Return just contents of cms_templates ignoring IN_DEV and DESIGNERS' MODE
	 */
	const RETURN_DATABASE_ONLY = 128;

	/**
	 * @brief	Return both IN_DEV and database templates
	 */
	const RETURN_DATABASE_AND_IN_DEV = 256;
	
	/**
	 * @brief	Default database template group names
	 */
	public static $databaseDefaults = array(
		'featured'   => 'category_articles',
		'form'	     => 'form',
		'display'    => 'display',
		'listing'    => 'listing',
		'categories' => 'category_index'
	);
	
	/**
	 * Ensure that the template is calling the correct groups
	 *
	 * @param	string	$group	Group to load templates from
	 * @return  void
	 */
	public static function fixTemplateTags( $group )
	{
		$templates = iterator_to_array( \IPS\Db::i()->select( '*', 'cms_templates', array( array( 'template_group=?', $group ) ) )->setKeyField('template_title') );
		
		foreach( $templates as $template )
		{
			$save = array();
			
			/* Make sure template tags call the correct group */
			if ( mb_stristr( $template['template_content'], '{template' ) )
			{
				preg_match_all( '/\{([a-z]+?=([\'"]).+?\\2 ?+)}/', $template['template_content'], $matches, PREG_SET_ORDER );

				/* Work out the plugin and the values to pass */
				foreach( $matches as $index => $array )
				{
					preg_match_all( '/(.+?)=' . $array[ 2 ] . '(.+?)' . $array[ 2 ] . '\s?/', $array[ 1 ], $submatches );

					$plugin = array_shift( $submatches[ 1 ] );
					if ( $plugin == 'template' )
					{
						$value   = array_shift( $submatches[ 2 ] );
						$options = array();

						foreach ( $submatches[ 1 ] as $k => $v )
						{
							$options[ $v ] = $submatches[ 2 ][ $k ];
						}

						if ( isset( $options['app'] ) and $options['app'] == 'cms' and isset( $options['location'] ) and $options['location'] == 'database' and isset( $options['group'] ) and $options['group'] != $template['template_original_group'] )
						{
							if ( \in_array( $value, array_keys( $templates ) ) )
							{
								$options['group'] = $group;

								$replace = '{template="' . $value . '" app="' . $options['app'] . '" location="' . $options['location'] . '" group="' . $options['group'] . '" params="' . ( isset($options['params']) ? $options['params'] : NULL ) . '"}';
								$save['template_content'] = str_replace( $matches[$index][0], $replace, $template['template_content'] );
							}
						}
						
						if ( \count( $save ) )
						{
							\IPS\Db::i()->update( 'cms_templates', $save, array( 'template_id=?', $template['template_id'] ) );
						}
					}
				}
			}
		}
	}
	
	/**
	 * Load Record
	 * Overloaded so we can force loading by key by default but still retain the template_id field as the primary key so
	 * save still updates the primary ID.
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
		if ( !\is_numeric( $id ) and $idField === NULL )
		{
			$idField = 'template_key';
		}
		
		if ( !\is_numeric( $id ) and ( \IPS\IN_DEV or \IPS\Theme::designersModeEnabled() ) )
		{
			$templates = \IPS\cms\Theme::i()->getRawTemplates( NULL, NULL, NULL, \IPS\cms\Theme::RETURN_AS_OBJECT );

			if ( isset( $templates[ $id ] ) )
			{
				return $templates[ $id ];
			}
		}
			
		try
		{
			return parent::load( $id, $idField, $extraWhereClause );
		}
		catch( \OutOfRangeException $ex )
		{
			throw $ex;
		}
	}
	
	/**
	 * Make a group_name readable (Group Name)
	 *
	 * @param	string	$name		Group name from the database
	 * @return	string
	 */
	public static function readableGroupName( $name )
	{
		if ( $name === 'js' )
		{
			return 'JS';
		}
		else if ( $name === 'css' )
		{
			return 'CSS';
		}

		return ucwords( str_replace( array( '-', '_' ), ' ', $name ) );
	}

	/**
	 * Get master templates
	 *
	 * This returns an array of all template groups that are considered IPS defaults
	 * @return array
	 */
	public static function getMasterTemplates() : array
	{
		if ( static::$masterTemplates === NULL )
		{
			static::$masterTemplates = iterator_to_array( \IPS\Db::i()->select( '*, MD5( CONCAT( template_location, \'.\', template_group, \'.\', template_title ) ) as bit_key', 'cms_templates', array( 'template_master=1' ) )->setKeyField( 'bit_key' ) );
		}

		return static::$masterTemplates;
	}

	/**
	 * Get the master template version of this template
	 *
	 * @return \IPS\cms\Templates|NULL
	 */
	public function getMasterOfThis()
	{
		if ( $this->master )
		{
			return $this;
		}

		$key = md5( $this->location . '.'. $this->original_group . '.' . $this->title );
		if ( \in_array( $key, array_keys( static::getMasterTemplates() ) ) )
		{
			return static::constructFromData( static::$masterTemplates[ $key ] );
		}

		return NULL;
	}

	/**
	 * Get master group names
	 *
	 * This returns an array of all template groups that are considered IPS defaults
	 * @return array
	 */
	public static function getMasterGroups() : array
	{
		if ( static::$masterGroups === NULL )
		{
			static::$masterGroups = array();
			foreach( \IPS\Db::i()->select( 'template_group', static::$databaseTable, array( 'template_master=1 and template_original_group=template_group' ), 'template_group ASC', NULL, 'template_group' ) as $template )
			{
				static::$masterGroups[ $template ] = $template;
			}
		}

		return static::$masterGroups;
	}

	/**
	 * Get all template group
	 *
	 * @param	int|constant	$returnType		Determines the content returned
	 * @return	array
	 */
	public static function getGroups( $returnType=1 )
	{
		$where  = array();
		$return = array();
		$locations = NULL;
		
		if ( \is_string( $returnType ) )
		{
			switch( $returnType )
			{
				case 'all':
					$returnType = self::RETURN_ALL;
				break;
				case 'block':
					$returnType = self::RETURN_BLOCK;
				break;
				case 'page':
					$returnType = self::RETURN_PAGE;
				break;
				case 'database':
					$returnType = self::RETURN_DATABASE;
				break;
			}
		}
		
		if ( $returnType & self::RETURN_ALL )
		{
			$where[] = array( 'template_location !=?', NULL );
		}
		else
		{
			$locations = array();
			
			if ( $returnType & self::RETURN_BLOCK )
			{
				$locations[] = 'block';
			}
			
			if ( $returnType & self::RETURN_PAGE )
			{
				$locations[] = 'page';
			}
			
			if ( $returnType & self::RETURN_DATABASE )
			{
				$locations[] = 'database';
			}
			
			if ( ! \count( $locations ) )
			{
				throw new \UnexpectedValueException();
			}
			
			$where[] = array( "template_location IN ('" . implode( "','", $locations ) . "')" );
		}
		
		foreach( \IPS\Db::i()->select( 'template_group', static::$databaseTable, $where, 'template_group ASC', NULL, 'template_group' ) as $template )
		{
			$return[ $template ] = $template;
		}
		
		return $return;
	}
	
	/**
	 * Get all templates
	 *
	 * @param	int|constant	$returnType		Determines the content returned
	 * @return	array
	 */
	public static function getTemplates( $returnType=1 )
	{
		$where  = array();
		$return = array();
		$locations = NULL;

		if ( ( \IPS\IN_DEV or \IPS\Theme::designersModeEnabled() ) AND ( $returnType & self::RETURN_DATABASE_AND_IN_DEV ) AND ! ( $returnType & self::RETURN_DATABASE_ONLY ) )
		{
			$flags = \IPS\cms\Theme::RETURN_AS_OBJECT;

			if ( $returnType & self::RETURN_ONLY_TEMPLATE )
			{
				$flags += \IPS\cms\Theme::RETURN_ONLY_TEMPLATE;
			}
			else if ( $returnType & self::RETURN_ONLY_CSS )
			{
				$flags += \IPS\cms\Theme::RETURN_ONLY_CSS;
			}
			else if ( $returnType & self::RETURN_ONLY_JS )
			{
				$flags += \IPS\cms\Theme::RETURN_ONLY_JS;
			}
			else
			{
				if ( $returnType & self::RETURN_BLOCK )
				{
					$flags += \IPS\cms\Theme::RETURN_BLOCK;
				}

				if ( $returnType & self::RETURN_PAGE )
				{
					$flags += \IPS\cms\Theme::RETURN_PAGE;
				}

				if ( $returnType & self::RETURN_DATABASE )
				{
					$flags += \IPS\cms\Theme::RETURN_DATABASE;
				}
			}

			$return = \IPS\cms\Theme::i()->getRawTemplates( 'cms', NULL, NULL, $flags );
		}

		if ( ! ( \IPS\IN_DEV or \IPS\Theme::designersModeEnabled() ) OR ( $returnType & self::RETURN_DATABASE_AND_IN_DEV ) OR ( $returnType & self::RETURN_DATABASE_ONLY ) )
		{
			if ( $returnType & self::RETURN_ALL )
			{
				$where[] = array( 'template_location !=?', NULL );
			}
			else if ( $returnType & self::RETURN_ONLY_TEMPLATE )
			{
				$where[] = array( 'template_type = ?', 'template' );
			}
			else if ( $returnType & self::RETURN_ONLY_CSS )
			{
				$where[] = array( 'template_type = ?', 'css' );
			}
			else if ( $returnType & self::RETURN_ONLY_JS )
			{
				$where[] = array( 'template_type = ?', 'js' );
			}
			else
			{
				$locations = array();

				if ( $returnType & self::RETURN_BLOCK )
				{
					$locations[] = 'block';
				}

				if ( $returnType & self::RETURN_PAGE )
				{
					$locations[] = 'page';
				}

				if ( $returnType & self::RETURN_DATABASE )
				{
					$locations[] = 'database';
				}

				if ( !\count( $locations ) )
				{
					throw new \UnexpectedValueException();
				}

				$where[] = array( "template_location IN ('" . implode( "','", $locations ) . "')" );
			}

			foreach ( \IPS\Db::i()->select( '*', static::$databaseTable, $where, 'template_user_edited DESC' ) as $template )
			{
				/* user_edited version is returned first, so only add to the array if the key isn't already in $return */
				if ( !isset( $return[ $template['template_key'] ] ) )
				{
					$return[ $template['template_key'] ] = static::constructFromData( $template );
				}
			}
		}

		return $return;
	}
	
	/**
	 * Construct Load Query
	 * Overloaded so we return the user_edited version where available
	 *
	 * @param	int|string	$id					ID
	 * @param	string		$idField			The database column that the $id parameter pertains to
	 * @param	mixed		$extraWhereClause	Additional where clause(s)
	 * @return	\IPS\Db\Select
	 */
	protected static function constructLoadQuery( $id, $idField, $extraWhereClause )
	{
		$where = array( array( $idField . '=?', $id ) );
		if( $extraWhereClause !== NULL )
		{
			if ( !\is_array( $extraWhereClause ) or !\is_array( $extraWhereClause[0] ) )
			{
				$extraWhereClause = array( $extraWhereClause );
			}
			$where = array_merge( $where, $extraWhereClause );
		}
	
		return static::db()->select( '*', static::$databaseTable, $where, 'template_user_edited DESC' );
	}

	/**
	 * Generate a tree of templates
	 *
	 * @param array $templates	Template data from the database
	 * @return array
	 */
	public static function buildTree( $templates )
	{
		$return = array();

		foreach( $templates as $id => $template )
		{
			if ( ! ( $template->location === 'database' and ! \IPS\Member::loggedIn()->hasAcpRestriction( 'cms', 'databases', 'databases_use' ) ) )
			{
				$return[$template->location][$template->group][$template->key] = $template;
			}
		}

		return $return;
	}
	
	/**
	 * Add a new template
	 * 
	 * @param	array	$template	Template Data
	 * @return	object	\IPS\cms\Templates
	 */
	public static function add( $template )
	{
		$newTemplate = new static;

		foreach( $template as $_k => $_v )
		{
			$newTemplate->$_k	= $_v;
		}

		$newTemplate->_new         = TRUE;
		$newTemplate->user_created = 1;
		$newTemplate->user_edited  = 0;
		$newTemplate->master       = 0;

		$newTemplate->save();

		/* Create a unique key */
		$newTemplate->key = $newTemplate->location . '_' . \IPS\Http\Url\Friendly::seoTitle( $newTemplate->title ) . '_' . $newTemplate->id;

		/* Make sure there's no double __ in there */
		foreach( array( 'group', 'title', 'key' ) as $field )
		{
			if ( mb_strstr( $newTemplate->$field, '__' ) )
			{
				$newTemplate->$field = str_replace( '__', '_', $newTemplate->$field );
			}
		}

		$newTemplate->save();
		
		return $newTemplate;
	}
	
	/**
	 * Removes all stored files so they can be rebuilt on the fly
	 *
	 * @return void
	 */
	public static function deleteCompiledFiles()
	{
		foreach( \IPS\Db::i()->select( '*', 'cms_templates', array( 'template_file_object IS NOT NULL' ) ) as $template )
		{
			try
			{
				\IPS\File::get( 'core_Theme', $template['template_file_object'] )->delete();
			}
			catch( \Exception $ex ) { }
		}
		
		\IPS\Db::i()->update( 'cms_templates', array( 'template_file_object' => NULL ) );
	}

	/**
	 * Export form for CMS Templates
	 *
	 * @param 	bool 				$appPluginBuild			TRUE to customise form for app/plugin builds
	 * @param 	array 				$preSelected			Array of default values for checkboxSet
	 * @return 	\IPS\Helpers\Form
	 */
	public static function exportForm( bool $appPluginBuild=FALSE, array $preSelected=array() ): \IPS\Helpers\Form
	{
		$form = new \IPS\Helpers\Form( 'form', $appPluginBuild ? 'save' : 'next' );
		if( !$appPluginBuild )
		{
			$form->addMessage( 'cms_templates_export_description', 'ipsMessage ipsMessage_information' );
			$form->addButton( 'cms_templates_export_return', 'link', \IPS\Http\Url::internal( "app=cms&module=pages&controller=templates" ) );
		}
		else
		{
			$form->addMessage( 'cms_templates_export_description_app', 'ipsMessage ipsMessage_general' );
		}

		$templates = [];
		
		foreach( \IPS\cms\Templates::getTemplates( \IPS\cms\Templates::RETURN_DATABASE_ONLY + \IPS\cms\Templates::RETURN_ALL ) as $template )
		{
			$title = \IPS\cms\Templates::readableGroupName( $template->group );

			if ( $template->location === 'database' )
			{
				$templates['database'][ $template->group ] = $title;
			}
			else if ( $template->location === 'block' )
			{
				$templates['block'][ $template->key ] = $title . ' &gt; ' . $template->title;
			}
			else if ( $template->location === 'page' )
			{
				$templates['page'][ $template->key ] = $title . ' &gt; ' . $template->title;
			}
		}

		foreach( $templates as $location => $data )
		{
			$defaultValue = $preSelected[ 'templates_' . $location ] ?? FALSE;
			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'templates_' . $location, $defaultValue, FALSE, array( 'options' => $data ) ) );
		}

		return $form;
	}

	/**
	 * Export requested templates as XML
	 *
	 * @param	array				$values		Checkbox set values
	 * @return 	\XMLWriter|null
	 */
	public static function exportAsXml( array $values ):? \XMLWriter
	{
		$templates = array();
		foreach( \IPS\cms\Templates::getTemplates( \IPS\cms\Templates::RETURN_DATABASE_ONLY + \IPS\cms\Templates::RETURN_ALL ) as $template )
		{
			$templates[ $template->location ][ $template->group ] = $template->title;
		}
		$exportTemplates = array();
		foreach ( $templates as $location => $data )
		{
			if ( isset( $values[ 'templates_' . $location ] ) and \count( $values[ 'templates_' . $location ] ) )
			{
				if ( $location === 'database' )
				{
					$tmp = \IPS\cms\Theme::i()->getRawTemplates( 'cms', array( 'database' ), array_values( $values[ 'templates_' . $location ] ), \IPS\cms\Theme::RETURN_DATABASE_ONLY + \IPS\cms\Theme::RETURN_ALL );

					foreach ( $tmp['cms'] as $loc => $data )
					{
						if ( $loc === $location )
						{
							foreach ( $data as $key => $tdata )
							{
								foreach ( $tdata as $tkey => $tkdata )
								{
									$exportTemplates['cms'][ $location ][ $key ][ $tkey ] = $tkdata;
								}
							}
						}
					}
				}
				else
				{
					$tmp = \IPS\cms\Theme::i()->getRawTemplates( 'cms', array( $location ), NULL, \IPS\cms\Theme::RETURN_DATABASE_ONLY + \IPS\cms\Theme::RETURN_ALL );

					foreach ( $tmp['cms'] as $loc => $data )
					{
						if ( $loc === $location )
						{
							foreach ( $data as $key => $tdata )
							{
								foreach ( $tdata as $tkey => $tkdata )
								{
									if ( \in_array( $tkey, $values[ 'templates_' . $location ] ) )
									{
										$exportTemplates['cms'][ $location ][ $key ][ $tkey ] = $tkdata;
									}
								}
							}
						}
					}
				}
			}
		}

		if ( \count( $exportTemplates ) )
		{
			/* Init */
			$xml = new \XMLWriter;
			$xml->openMemory();
			$xml->setIndent( TRUE );
			$xml->startDocument( '1.0', 'UTF-8' );

			/* Root tag */
			$xml->startElement( 'templates' );

			foreach ( $exportTemplates as $app => $location )
			{
				foreach ( $location as $key => $data )
				{
					foreach ( $data as $group => $template )
					{
						foreach ( $template as $key => $templateData )
						{
							/* Initiate the <template> tag */
							$xml->startElement( 'template' );

							foreach ( $templateData as $k => $v )
							{
								if ( !\in_array( \substr( $k, 9 ), array( 'content', 'params' ) ) )
								{
									$xml->startAttribute( $k );
									$xml->text( $v );
									$xml->endAttribute();
								}
							}

							/* Write (potential) HTML fields */
							foreach ( array( 'template_params', 'template_content' ) as $field )
							{
								if ( isset( $templateData[ $field ] ) )
								{
									$xml->startElement( $field );
									if ( preg_match( '/<|>|&/', $templateData[ $field ] ) )
									{
										$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $templateData[ $field ] ) );
									}
									else
									{
										$xml->text( $templateData[ $field ] );
									}
									$xml->endElement();
								}
							}

							/* Close the <template> tag */
							$xml->endElement();
						}
					}
				}
			}

			/* Finish */
			$xml->endDocument();

			return $xml;
		}

		return NULL;
	}
	
	/**
	 * Is suitable to be used for a custom wrapper?
	 *
	 * @return boolean
	 */
	public function isSuitableForCustomWrapper()
	{
		if ( $this->location == 'page' and preg_match( '#<html([^>]+?)?>#', $this->content ) )
		{
			if ( preg_match( '#\$html(\s|=|,)#', $this->params ) and preg_match( '#\$title(\s|=|,|$)#', $this->params ) )
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Is suitable to be used for a builder column wrapper?
	 *
	 * @return boolean
	 */
	public function isSuitableForBuilderWrapper()
	{
		if ( $this->location == 'page' and mb_stristr( $this->content, '{template="widgetContainer"' ) )
		{
			return true;
		}

		return false;
	}

	/**
	 * Sets the user_edited flag if it is different from the master template
	 *
	 * @return boolean
	 */
	public function isDifferentFromMaster() : bool
	{
		if ( $master = $this->getMasterOfThis() )
		{
			if ( md5( preg_replace( '#\s#', '', $this->content ) ) != md5( preg_replace( '#\s#', '', $master->content ) ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Import User Template XML
	 *
	 * @param	string 								$filePath			Path to XML file
	 * @param	string 								$fileContents		XML Contents as string
	 * @throws	\UnexpectedValueException
	 * @throws 	\BadMethodCallException
	 * @return	\IPS\Http\Url\Internal|null
	 */
	public static function importUserTemplateXml( string $filePath=NULL, string $fileContents=NULL ):? \IPS\Http\Url\Internal
	{
		/* Open XML file */
		if( $filePath )
		{
			$xml = \IPS\Xml\XMLReader::safeOpen( $filePath );
		}
		elseif( $fileContents )
		{
			$xml = new \IPS\Xml\XMLReader;

			if( !$xml->xml( $fileContents, NULL, LIBXML_NONET ) )
			{
				throw \UnexpectedValueException('bad_xml_string');
			}
		}
		else
		{
			throw new \BadMethodCallException('path_or_data_missing');
		}

		$conflictKey = md5( microtime() );

		$templates = \IPS\cms\Theme::i()->getRawTemplates( 'cms', NULL, NULL, \IPS\cms\Theme::RETURN_DATABASE_ONLY + \IPS\cms\Theme::RETURN_ALL );

		if ( ! @$xml->read() )
		{
			throw new \UnexpectedValueException('xml_file_bad_format');
		}
		$xml->read();
		while ( $xml->read() )
		{
			if ( $xml->name === 'template' )
			{
				$node      = new \SimpleXMLElement( $xml->readOuterXML() );
				$attrs     = array();

				foreach( $node->attributes() as $k => $v )
				{
					$attrs[ $k ] = (string) $v;
				}

				/* Any kids of your own? */
				foreach( $node->children() as $k => $v )
				{
					$tryJson = json_decode( $v, TRUE );
					$attrs[ $k ] =  ( $tryJson ) ? $tryJson : (string) $v;
				}


				if ( isset( $attrs['template_title'] ) and isset( $attrs['template_location'] ) and isset( $attrs['template_group'] ) )
				{
					/* Got this template? */
					$exists = NULL;
					if ( isset( $templates['cms'][ $attrs['template_location'] ][ $attrs['template_group'] ] ) )
					{
						foreach ( $templates['cms'][ $attrs['template_location'] ][ $attrs['template_group'] ] as $key => $template )
						{
							if ( $template['template_title'] === $attrs['template_title'] )
							{
								$exists = $template;
								break;
							}
						}

						/* Should we update it or log it as a conflict? */
						if ( $exists !== NULL )
						{
							if ( $exists['template_master'] )
							{
								/* This is a master template bit, so it's totes fine to create a new one, as it will 'overload' this one */
								$obj = new \IPS\cms\Templates;
								$obj->location = $attrs['template_location'];
								$obj->group = $attrs['template_group'];
								$obj->title = $attrs['template_title'];
								$obj->params = $attrs['template_params'];
								$obj->content = $attrs['template_content'];
								$obj->original_group = $attrs['template_original_group'];
								$obj->type = $attrs['template_type'];
								$obj->user_created = 1;
								$obj->user_edited = (int) $obj->isDifferentFromMaster();
								$obj->desc = '';
								$obj->key = $exists['template_key']; // We need to re-use the already stored key so database mappings remain intact
								$obj->save();
							}
							elseif ( $exists['template_user_created'] == 1 )
							{
								/* Ok, so this is a non-stIt useandard template. Has it been edited by this user at all? */
								if ( $exists['template_user_edited'] )
								{
									/* User has edited this, so we should just log a conflict... */
									\IPS\Db::i()->insert( 'cms_template_conflicts', array(
										'conflict_key'     		=> $conflictKey,
										'conflict_item_id'		=> $exists['template_id'],
										'conflict_type'		    => $attrs['template_type'],
										'conflict_location'	    => $attrs['template_location'],
										'conflict_group'		=> $attrs['template_group'],
										'conflict_original_group' => $attrs['template_original_group'],
										'conflict_title'		=> $attrs['template_title'],
										'conflict_data'		    => $attrs['template_params'],
										'conflict_content'      => $attrs['template_content'],
										'conflict_date'			=> time()
									) );
								}
								else
								{
									/* Not edited, so just update */
									try
									{
										$template = \IPS\cms\Templates::load( $exists['template_key'], 'template_key' );
										$template->params = $attrs['template_params'];
										$template->content = $attrs['template_content'];
										$template->save();
									}
									catch( \Exception $e ) { }
								}
							}
							else
							{
								/* This is an overloaded master template, so just update */
								$template = \IPS\cms\Templates::load( $exists['template_key'], 'template_key' );
								$template->user_edited = 1;
								$template->params = $attrs['template_params'];
								$template->content = $attrs['template_content'];
								$template->save();
							}
						}
					}

					if ( $exists === NULL )
					{
						$obj = new \IPS\cms\Templates;
						$obj->location = $attrs['template_location'];
						$obj->group = $attrs['template_group'];
						$obj->title = $attrs['template_title'];
						$obj->params = $attrs['template_params'];
						$obj->content = $attrs['template_content'];
						$obj->original_group = $attrs['template_original_group'];
						$obj->type = $attrs['template_type'];
						$obj->user_created = 1; # Created
						$obj->user_edited = (int) $obj->isDifferentFromMaster();
						$obj->desc = '';
						$obj->save();

						/* Lets try and re-use the original key if we can */
						$key = NULL;
						if ( isset( $attrs['template_key'] ) )
						{
							try
							{
								$check = \IPS\Db::i()->select( 'template_id', 'cms_templates', array( 'template_key=?', $attrs['template_key'] ) )->first();

								if ( $check['template_master'] == 1 and \in_array( $attrs['template_group'], \IPS\cms\Templates::getMasterGroups() ) )
								{
									/* it's OK to overload this with a user edited version */
									$obj->user_created = 0;
									$obj->user_edited = 1;

									$key = $attrs['template_key'];
								}
							}
							catch( \UnderflowException $e )
							{
								/* It doesn't exist! */
								$key = $attrs['template_key'];
							}
						}

						$obj->key = ( $key ) ? $key : $obj->location . \IPS\Http\Url\Friendly::seoTitle( $obj->group ) . '_' . \IPS\Http\Url\Friendly::seoTitle( $obj->title ) . '_' . $obj->id;
						$obj->save();
					}
				}
			}

			$xml->next();
		}

		/* Check for conflicts */
		$conflicts = \IPS\Db::i()->select( 'count(*)', 'cms_template_conflicts', array( 'conflict_key=?', $conflictKey ) )->first();
		if( $conflicts )
		{
			return \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates&do=conflicts&key=' . $conflictKey );
		}

		return NULL;
	}

	/**
	 * Import templates from an XML file
	 *
	 * @param   string      $file       File to load from (data/ or tmp/)
	 * @param	int|null	$offset     Offset to begin import from
	 * @param	int|null	$limit	    Number of rows to import
	 * @param   boolean     $update   	If updating, files written as master templates
	 * @return	bool		Rows imported (true) or none imported (false)
	 */
	public static function importXml( $file, $offset=NULL, $limit=NULL, $update=TRUE )
	{
		$i		= 0;
		$worked	= false;
	
		if( file_exists( $file ) )
		{
			/* First, delete any existing skin data for this app. */
			if( $offset === NULL OR $offset === 0 )
			{
				if ( $update === TRUE )
				{
					\IPS\Db::i()->delete( 'cms_templates', array( 'template_master=1' ) );
					\IPS\cms\Theme::deleteCompiledTemplate( 'cms' );
				}
			}

			/* Open XML file */
			$xml = \IPS\Xml\XMLReader::safeOpen( $file );
			$xml->read();

			while( $xml->read() )
			{
				if( $xml->nodeType != \XMLReader::ELEMENT )
				{
					continue;
				}

				$i++;

				if ( $offset !== null )
				{
					if ( $i - 1 < $offset )
					{
						$xml->next();
						continue;
					}
				}

				if( $xml->name == 'template' )
				{
					$save = array(
						'template_content'        => $xml->readString(),
						'template_master'         => 1,
					    'template_original_group' => $xml->getAttribute('template_group'),
					    'template_file_object'    => NULL
					);

					foreach( array('key', 'title', 'desc', 'location', 'group', 'params', 'app', 'type' ) as $field )
					{
						$save[ 'template_' . $field ] = $xml->getAttribute( 'template_' . $field );
					}

					\IPS\Db::i()->insert( 'cms_templates', $save );
					$worked	= true;
				}

				if( $limit !== null AND $i === ( $limit + $offset ) )
				{
					break;
				}
			}

			static::$masterGroups = NULL;
			static::$masterTemplates = NULL;

			static::updateAllInheritedMasterTemplates();
		}

		return $worked;
	}

	/**
	 * Update all inherited master templates with the new master templates
	 *
	 * @return void
	 */
	public static function updateAllInheritedMasterTemplates()
	{
		/* Update existing template bits */
		foreach( \IPS\Db::i()->select( '*', 'cms_templates', array( 'template_master=0 and template_user_created=1 and template_user_edited=0' ) ) as $key => $template )
		{
			$obj = static::constructFromData( $template );

			if ( $master = $obj->getMasterOfThis() )
			{
				$obj->content = $master->content;
				$obj->params = $master->params;
				$obj->save();
			}
		}

		/* Now go through the original groups and make sure we have template bits for all */
		$masterByGroup = array();

		foreach( static::getMasterTemplates() as $template )
		{
			$masterByGroup[ $template['template_group'] ][ $template['template_title'] ] = $template;
		}

		$customGroups = iterator_to_array( \IPS\Db::i()->select( 'template_group, MIN(template_original_group) as template_original_group', static::$databaseTable, array( 'template_master=0 and template_original_group != template_group' ), 'template_group ASC', NULL, 'template_group' )->setKeyField('template_group')->setValueField('template_original_group') );

		foreach( $customGroups as $customGroup => $masterGroup )
		{
			if ( $masterGroup )
			{
				$groupByGroup = array();
				foreach ( \IPS\Db::i()->select( '*', static::$databaseTable, array('template_group=?', $customGroup) ) as $bit )
				{
					$groupByGroup[$bit['template_title']] = $bit;
				}

				/* Now, see if we're missing any */
				foreach( $masterByGroup[ $masterGroup ] as $title => $template )
				{
					if ( ! \in_array( $title, array_keys( $groupByGroup ) ) )
					{
						$copy = $template;
						foreach( array( 'template_id', 'template_file_object', 'bit_key' ) as $field )
						{
							unset( $copy[ $field ] );
						}

						$copy['template_master'] = 0;
						$copy['template_user_created'] = 1;
						$copy['template_group'] = $customGroup;
						$copy['template_key'] = $copy['template_location'] . '_' . $customGroup . '_' . $title;

						\IPS\Db::i()->insert( 'cms_templates', $copy );
					}
				}
			}
		}
	}

	/**
	 * Delete
	 * Overloaded to protect inheritence
	 * 
	 *  @return void
	 */
	public function delete()
	{
		\IPS\cms\Theme::deleteCompiledTemplate( 'cms', $this->location, $this->group );

		if ( $this->user_created )
		{
			\IPS\Db::i()->delete( 'cms_templates', array( 'template_key=? AND template_user_created=?', $this->key, 1 ) );

			if ( isset( static::$multitons[ $this->id ] ) )
			{
				unset( static::$multitons[ $this->id ] );

				if( isset( static::$multitonMap['template_id'][ $this->id ] ) )
				{
					unset( static::$multitonMap['template_id'][ $this->id ] );
				}

				if( isset( static::$multitonMap['template_key'][ $this->key ] ) )
				{
					unset( static::$multitonMap['template_key'][ $this->key ] );
				}
			}
		}
		else
		{
			if ( $this->user_edited )
			{
				\IPS\Db::i()->delete( 'cms_templates', array( 'template_key=? AND template_user_edited=?', $this->key, 1 ) );

				if ( isset( static::$multitons[ $this->id ] ) )
				{
					unset( static::$multitons[ $this->id ] );

					if( isset( static::$multitonMap['template_id'][ $this->id ] ) )
					{
						unset( static::$multitonMap['template_id'][ $this->id ] );
					}

					if( isset( static::$multitonMap['template_key'][ $this->key ] ) )
					{
						unset( static::$multitonMap['template_key'][ $this->key ] );
					}
				}
			}
			else
			{
				throw new \OutOfRangeException('CANNOT_DELETE');
			}
		}
	}

	/**
	 * Get the inherited string
	 *
	 * @return string
	 */
	public function get__inherited()
	{
		if ( $this->user_created and ! \in_array( $this->original_group, static::getMasterGroups() ) )
		{
			return 'custom';
		}
		elseif ( $this->user_created and ! $this->user_edited )
		{
			return 'inherit';
		}
		elseif ( $this->user_edited )
		{
			return 'changed';
		}

		return 'original';
	}

	/**
	 * Get the file object
	 *
	 * @return string
	 */
	public function get__file_object()
	{
		if ( ! $this->file_object )
		{
			$content = $this->content;
			
			/* Build on demand */
			if ( $this->type != 'js' and ( mb_stristr( $this->content, "{block=" ) or mb_stristr( $this->content, "{{if" ) or mb_stristr( $this->content, "{media=" ) ) )
			{
				$functionName = 'css_' . mt_rand();
				\IPS\Theme::makeProcessFunction( str_replace( '\\', '\\\\', $content ), $functionName );
				$functionName = "IPS\Theme\\{$functionName}";
				$content = $functionName();
			}
			
			$this->file_object = (string) \IPS\File::create( 'cms_Pages', $this->title, $content ?: ' ', 'page_objects', TRUE );
			parent::save(); # Go to parent save to prevent $this->save() from wiping file objects
		}

		return $this->file_object;
	}

	/**
	 * Save
	 * 
	 * @return void
	 */
	public function save()
	{
		/* Trash file object if appropriate */
		if ( $this->file_object )
		{
			try
			{
				\IPS\File::get( 'cms_Pages', $this->file_object )->delete();
			}
			catch ( \Exception $e )
			{
				/* Just to be sure nothing is throw, we don't care too much if it's not deleted */
			}

			/* Trash all cached page file objects too */
			\IPS\cms\Pages\Page::deleteCachedIncludes( $this->file_object );

			$this->file_object = NULL;
		}

		parent::save();

		/* Clear store */
		$key = \strtolower( 'template_cms_' . \IPS\cms\Theme::makeBuiltTemplateLookupHash( 'cms', $this->location, $this->group ) . '_' . $this->group );
		unset( \IPS\Data\Store::i()->$key );
	}
}