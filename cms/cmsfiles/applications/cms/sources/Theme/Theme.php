<?php
/**
 * @brief		IN_DEV Skin Set
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		16 Apr 2013
 */

namespace IPS\cms;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * IN_DEV Skin set
 */
class _Theme extends \IPS\Theme
{
	/**
	 * @brief	Template Classes
	 */
	protected $templates;

	/**
	 * @brief	[SkinSets] Templates already loaded and evald via getTemplate()
	 */
	public static $calledTemplates = array();

	/**
	 * @brief	Return type for getRawTemplates/getRawCss: Uses DB if not IN_DEV, otherwise uses disk .phtml look up
	 */
	const RETURN_AS_OBJECT = 32;

	/**
	 * @brief	Return just template type
	 */
	const RETURN_ONLY_TEMPLATE = 64;

	/**
	 * @brief	Return just css type
	 */
	const RETURN_ONLY_CSS = 128;

	/**
	 * @brief	Return just js type
	 */
	const RETURN_ONLY_JS = 256;

	/**
	 * @brief	Return just page type
	 */
	const RETURN_PAGE = 512;

	/**
	 * @brief	Return just database type
	 */
	const RETURN_DATABASE = 1024;

	/**
	 * @brief	Return just block type
	 */
	const RETURN_BLOCK = 2048;

	/**
	 * @brief	Return just contents of cms_templates ignoring IN_DEV and DESIGNERS' MODE
	 */
	const RETURN_DATABASE_ONLY = 4096;

	/**
	 * Get currently logged in member's theme
	 *
	 * @return	\IPS\Theme
	 */
	public static function i()
	{
		if ( \IPS\Theme::designersModeEnabled() )
		{
			return new \IPS\cms\Theme\Advanced\Theme;
		}
		else
		{
			return new self;
		}
	}

	/**
	 * Imports templates from the /dev directories.
	 *
	 * @param	string	$location	Location (database, block)
	 * @return	void
	 */
	public static function importInDev( $location )
	{
		/* Clear out existing template bits */
		\IPS\Db::i()->delete( 'cms_templates', array( 'template_master=1 and template_user_created=0 and template_user_edited=0 AND template_location=?', $location ) );

		static::importLocation( $location );
	}

	/**
	 * Write IN_DEV files
	 *
	 * @param	boolean		$force		TRUE to rewrite templates, FALSE to check if exists first
	 * @return void
	 */
	public static function writeInDev( $force=FALSE )
	{
		foreach( \IPS\Db::i()->select( '*', 'cms_templates', array( 'template_master=?', 1 ) ) as $template )
		{
			try
			{
				static::writeTemplate( $template, $force );
			}
			catch( \RuntimeException $ex )
			{
				throw new \RuntimeException( $ex->getMessage() );
			}
		}
	}

	/**
	 * Imports templates from the /dev directories.
	 *
	 * @param	string	$location	Location (database, block)
	 * @return	array
	 */
	public static function importLocation( $location )
	{
		$master = iterator_to_array( \IPS\Db::i()->select(
				"*, MD5( CONCAT(template_location, ',', template_group, ',', template_title) ) as bit_key",
				'cms_templates',
				array( 'template_master=1 and template_user_created=0 and template_user_edited=0 AND template_location=?', $location )
			)->setKeyField('bit_key') );

		$path = static::_getHtmlPath( 'cms', $location );
		$seen = array();

		if ( is_dir( $path ) )
		{
			foreach( new \DirectoryIterator( $path ) as $group )
			{
				if ( $group->isDot() || mb_substr( $group->getFilename(), 0, 1 ) === '.' || $group->getFilename() == 'index.html' )
				{
					continue;
				}

				if ( $group->isDir() )
				{
					foreach( new \DirectoryIterator( $path . '/' . $group->getFilename() ) as $file )
					{
						if ( $file->isDot() || mb_substr( $file->getFilename(), -6 ) !== '.phtml')
						{
							continue;
						}

						/* Get the content */
						$html = file_get_contents( $path . '/' . $group->getFilename() . '/' . $file->getFilename() );

						/* Parse the header tag */
						preg_match( '/^<ips:template parameters="(.+?)?"([^>]+?)>(\r\n?|\n)/', $html, $params );

						/* Strip it */
						$html = ( isset($params[0]) ) ? str_replace( $params[0], '', $html ) : $html;
						$title = str_replace( '.phtml', '', $file->getFilename() );

						/* If we're syncing designer mode, check for actual changes */
						$key = md5( $location . ',' . $group->getFilename() . ',' . $title );

						if ( isset( $master[ $key ] ) )
						{
							if( \IPS\Login::compareHashes( md5( trim( $master[ $key ]['template_content'] ) ), md5( trim( $html ) ) ) )
							{
								continue;
							}
						}

						$seen[ $group->getFilename() ] = $title;

						/* remove compiled version */
						$key = \strtolower( 'template_cms_' .static::makeBuiltTemplateLookupHash( 'cms', $location, $group->getFilename() ) . '_' . static::cleanGroupName( $group->getFilename() ) );

						if ( isset( \IPS\Data\Store::i()->$key ) )
						{
							unset(\IPS\Data\Store::i()->$key);
						}

						\IPS\Db::i()->insert( 'cms_templates', array(
							'template_key'            => $location . '_' . $group->getFilename() . '_' . $title,
							'template_title'	      => $title,
							'template_desc'		      => '',
							'template_content'        => $html,
							'template_location'       => $location,
							'template_group'          => $group->getFilename(),
							'template_original_group' => $group->getFilename(),
							'template_container'      => 0,
							'template_params'	      => ( isset($params[1]) ) ? $params[1] : '',
							'template_master'         => 1
						) );
					}
				}
			}
		}

		return $seen;
	}

	/**
	 *  Write a template to disk
	 *
	 * @param   array       $template       Template to write
	 * @param   boolean     $force          Force overwrite
	 * @return  void
	 * @throws  \RuntimeException
	 */
	public static function writeTemplate( $template, $force=FALSE )
	{
		$path = static::_getHtmlPath('cms');

		if ( ! is_dir( $path ) )
		{
			if ( ! mkdir( $path, \IPS\IPS_FOLDER_PERMISSION, TRUE ) )
			{
				throw new \DomainException();
			}
		}

		if ( ! is_dir( $path . '/' . $template['template_location'] ) )
		{
			mkdir( $path . '/' . $template['template_location'] );
			@chmod( $path . '/' . $template['template_location'], \IPS\IPS_FOLDER_PERMISSION );
		}

		if ( ! is_dir( $path . '/' . $template['template_location'] . '/' . $template['template_group'] ) )
		{
			mkdir( $path . '/' . $template['template_location'] . '/' . $template['template_group'] );
			@chmod( $path . '/' . $template['template_location'] . '/' . $template['template_group'], \IPS\IPS_FOLDER_PERMISSION );
		}

		$fileName = ( $template['template_type'] === 'template' ) ? $template['template_title'] . '.phtml' : $template['template_title'];

		if ( ! file_exists( $path . '/' . $template['template_location'] . '/' . $template['template_group'] . '/' . $fileName ) OR $force === TRUE )
		{
			$write = '';
			
			if ( $template['template_type'] === 'template' )
			{
				$write  = '<ips:template parameters="' . $template['template_params'] . '" original_group="' . $template['template_original_group'] . '" key="' . $template['template_key'] . '" />' . "\n";
			}
			
			$write .= $template['template_content'];

			if ( @\file_put_contents( $path . '/' . $template['template_location'] . '/' . $template['template_group'] . '/' . $fileName, $write ) === FALSE )
			{
				throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack( 'content_theme_dev_cannot_write_template', FALSE, array( 'sprintf' => array( $path . '/' . $template['template_location'] . '/' . $template['template_group'] . '/' . $fileName ) ) ) );
			}
			else
			{
				@chmod( $path . '/' . $template['template_location'] . '/' . $template['template_group'] . '/' . $fileName, \IPS\IPS_FILE_PERMISSION );
			}
		}
	}

	/**
	 * Get raw templates. Raw means HTML logic and variables are still in {{format}}
	 *
	 * @param string|array	$app				Template app (e.g. core, forum)
	 * @param string|array	$location			Template location (e.g. admin,global,front)
	 * @param string|array	$group				Template group (e.g. login, share)
	 * @param int|constant	$returnType			Determines the content returned
	 * @param boolean		$returnThisSetOnly  Returns rows unique to this set only
	 * @return array
	 */
	public function getRawTemplates( $app=array(), $location=array(), $group=array(), $returnType=null, $returnThisSetOnly=false )
	{
		$returnType = ( $returnType === null )  ? self::RETURN_ALL   : $returnType;
		$app        = ( \is_string( $app )      AND ! empty( $app ) ) ? array( $app )      : $app;
		$location   = ( \is_string( $location ) AND ! empty( $location ) ) ? array( $location ) : $location;
		$group      = ( \is_string( $group )    AND ! empty( $group ) ) ? array( $group )    : $group;
		$where      = array();
		$templates  = array();

		if ( ( \IPS\IN_DEV or \IPS\Theme::designersModeEnabled() ) AND ! ( $returnType & static::RETURN_DATABASE_ONLY ) )
		{
			$fixedLocations = array( 'admin', 'front', 'global' );
			$results	    = array();
			$seenKeys       = array();

			foreach( new \DirectoryIterator( static::_getHtmlPath('cms') ) as $location )
			{
				if ( ! \in_array( $location->getFilename(), $fixedLocations ) AND $location->isDir() AND mb_substr( $location->getFilename(), 0, 1 ) !== '.' )
				{
					$allowedLocations = array();
					if ( $returnType & static::RETURN_ONLY_TEMPLATE )
					{
						$allowedLocations = array('page', 'block', 'database');
					}
					else
					{
						if ( $returnType & static::RETURN_ONLY_CSS )
						{
							$allowedLocations[] = 'css';
						}

						if ( $returnType & static::RETURN_ONLY_JS )
						{
							$allowedLocations[] = 'js';
						}

						if ( $returnType & static::RETURN_PAGE )
						{
							$allowedLocations[] = 'page';
						}

						if ( $returnType & static::RETURN_BLOCK )
						{
							$allowedLocations[] = 'block';
						}

						if ( $returnType & static::RETURN_DATABASE )
						{
							$allowedLocations[] = 'database';
						}
					}

					if ( \count( $allowedLocations ) and ! \in_array( $location->getFilename(), $allowedLocations ) )
					{
						continue;
					}

					foreach( new \DirectoryIterator( static::_getHtmlPath( 'cms', $location->getFilename() ) ) as $file )
					{
						if ( $file->isDir() AND mb_substr( $file->getFilename(), 0, 1 ) !== '.' )
						{
							if ( $group === NULL or ! \count( $group ) or ( \in_array( $file->getFilename(), $group ) ) )
							{
								foreach( new \DirectoryIterator( static::_getHtmlPath( 'cms', $location->getFilename(), $file->getFilename() ) ) as $template )
								{
									if ( ! $template->isDir() AND ( mb_substr( $template->getFilename(), -6 ) === '.phtml' or mb_substr( $template->getFilename(), -4 ) === '.css' or mb_substr( $template->getFilename(), -3 ) === '.js' ) )
									{
										$title     = str_replace( ".phtml", "", $template->getFilename() );

										$contents  = file_get_contents( static::_getHtmlPath( 'cms', $location->getFilename(), $file->getFilename() ) . '/' . $template->getFilename() );
										$key       = \IPS\cms\Theme\Template::extractDataFromTag( $contents, 'key' );
										$key       = $key ? $key : \IPS\Http\Url\Friendly::seoTitle( $file->getFilename() . '_' . $title );
										$ogroup    = \IPS\cms\Theme\Template::extractDataFromTag( $contents, 'original_group' );
										$params    = \IPS\cms\Theme\Template::extractParamsFromTag( $contents );
										$container = NULL;

										if ( \in_array( $key, $seenKeys ) )
										{
											$key .= filemtime( static::_getHtmlPath( 'cms', $location->getFilename(), $file->getFilename() ) . '/' . $template->getFilename() ) . mt_rand();
										}

										$seenKeys[] = $key;

										$contents = preg_replace( "#^<ips:template([^>]+?)>(\r\n|\n)#", "", $contents );

										if ( $returnType & static::RETURN_AS_OBJECT )
										{
											$object = new \IPS\cms\Templates;
											$object->key          = $key;
											$object->title        = $title;
											$object->desc         = NULL;
											$object->rel_id       = NULL;
											$object->content      = $contents;
											$object->location     = $location->getFilename();
											$object->group        = $file->getFilename();
											$object->original_group = isset( $ogroup ) ? $ogroup : $object->group;
											$object->user_created = 0;
											$object->user_edited  = 0;
											$object->params       = $params;

											$results[ $key ] = $object;
										}
										else if ( $returnType & static::RETURN_ALL OR $returnType & static::RETURN_ALL_NO_CONTENT )
										{
											$results['cms'][ $location->getFilename() ][ $file->getFilename() ][ $key ] = array(
												'template_key'            => $key,
												'template_title'          => $title,
												'template_desc'           => NULL,
												'template_rel_id'         => NULL,
												'template_content'        => $contents,
												'template_location'       => $location->getFilename(),
												'template_group'          => $file->getFilename(),
												'template_original_group' => isset( $ogroup ) ? $ogroup : $file->getFilename(),
												'template_user_created'   => 0,
												'template_user_edited'    => 0,
												'template_params'         => $params,
											);
											
											if ( $returnType & static::RETURN_ALL_NO_CONTENT )
											{
												unset( $results['cms'][ $location->getFilename() ][ $file->getFilename() ][ $key ]['template_content'] );
											}
										}
										else
										{
											$results[ $key ] = $key;
										}
									}
								}
							}
						}
					}
				}
			}

			return $results;
		}
		else
		{
			if ( \is_array( $location ) AND \count( $location ) )
			{
				$where[] = "template_location IN ('" . implode( "','", $location ) . "')";
			}

			if ( \is_array( $group ) AND \count( $group ) )
			{
				$where[] = "template_group IN ('" . implode( "','", $group ) . "')";
			}

			if ( $returnType & static::RETURN_ONLY_CSS )
			{
				$where[] = "template_type='css'";
			}

			if ( $returnType & static::RETURN_ONLY_JS )
			{
				$where[] = "template_type='js'";
			}

			$templateNames = array();
			$rawTemplates = array();
			$originalGroups = array();
			$originalTemplates = array();
			$originalTemplateNames = array();
			$groupMap = array();
			
			foreach( \IPS\Db::i()->select( '*', 'cms_templates', implode( " AND ", $where ), 'template_location, template_group, template_key, template_user_edited ASC' ) as $row )
			{
				$rawTemplates[] = $row;
				$templateNames[ $row['template_original_group'] ][] = $row['template_title'];
				$groupMap[ $row['template_original_group'] ] = $row['template_group'];
				
				if ( $row['template_original_group'] )
				{
					$originalGroups[ $row['template_original_group'] ] = $row['template_original_group'];
				}
			}
			
			if ( \count( $originalGroups ) )
			{
				foreach( \IPS\Db::i()->select( '*', 'cms_templates', array( array( 'template_original_group = template_group '), array( \IPS\Db::i()->in( 'template_original_group', $originalGroups ) ) ) ) as $row )
				{
					$originalTemplates[ $row['template_group'] . '_' . $row['template_title'] ] = $row;
					$originalTemplateNames[ $row['template_group'] ][] = $row['template_title'];
				}
			}
			
			/* Now try and see if we can merge in missing templates */			
			foreach( $originalTemplateNames as $group => $names )
			{
				if ( isset( $templateNames[ $group ] ) )
				{
					foreach( $names as $name )
					{
						if ( ! \in_array( $name, $templateNames[ $group ] ) )
						{
							$rawTemplates[] = array_merge( $originalTemplates[ $group . '_' . $name ], array( 'template_group' => $groupMap[ $group ]) );
						}
					}
				}
			}
		
			foreach( $rawTemplates as $row )
			{
				$row['TemplateKey']     = $row['template_app'] . '_' . $row['template_location'] . '_' . $row['template_group'] . '_' . $row['template_key'];
				$row['jsDataKey']       = str_replace( '.', '--', $row['TemplateKey'] );
				$row['template_app']    = 'cms';

				if ( $returnType & static::RETURN_ALL_NO_CONTENT )
				{
					unset( $row['template_content'] );
					$templates[ $row['template_app'] ][ $row['template_location'] ][ $row['template_group'] ][ $row['template_key'] ] = $row;
				}
				else if ( $returnType & static::RETURN_ALL )
				{
					$templates[ $row['template_app'] ][ $row['template_location'] ][ $row['template_group'] ][ $row['template_key'] ] = $row;
				}
				else if ( $returnType & static::RETURN_BIT_NAMES )
				{
					$templates[ $row['template_app'] ][ $row['template_location'] ][ $row['template_group'] ][] = $row['template_key'];
				}
				else if ( $returnType & static::RETURN_ARRAY_BIT_NAMES )
				{
					$templates[] = $row['template_key'];
				}
			}

			if ( $returnType & static::RETURN_ARRAY_BIT_NAMES )
			{
				sort( $templates );
				return $templates;
			}

			ksort( $templates );

			/* Pretty sure Mark can turn this into a closure */
			foreach( $templates as $k => $v )
			{
				ksort( $templates[ $k ] );

				foreach( $templates[ $k ] as $ak => $av )
				{
					ksort( $templates[ $k ][ $ak ] );

					if ( $returnType & static::RETURN_ALL )
					{
						foreach( $templates[ $k ][ $ak ] as $bk => $bv )
						{
							ksort( $templates[ $k ][ $ak ][ $bk ] );
						}
					}
				}
			}

			return $templates;
		}
	}

	/**
	 * Get a template
	 *
	 * @param	string	$group				Template Group
	 * @param	string	$app				Application key (NULL for current application)
	 * @param	string	$location		    Template Location (NULL for current template location)
	 * @return	\IPS\Theme\Template
	 */
	public function getTemplate( $group, $app=NULL, $location=NULL )
	{
		/* Do we have an application? */
		if( $app === NULL )
		{
			$app = \IPS\Dispatcher::i()->application->directory;
		}

		/* How about a template location? */
		if( $location === NULL )
		{
			$location = \IPS\Dispatcher::i()->controllerLocation;
		}

		/* Get template */
		if ( \IPS\IN_DEV or \IPS\Theme::designersModeEnabled() )
		{
			if ( ! isset( $this->templates[ $app ][ $location ][ $group ] ) )
			{
				if ( $app === 'cms' AND ! \in_array( $location, array( 'admin', 'front', 'global' ) ) )
				{
					$this->templates[ $app ][ $location ][ $group ] = new \IPS\cms\Theme\Template( $app, $location, $group );
				}
				else
				{
					$this->templates[ $app ][ $location ][ $group ] = \IPS\Theme::i()->getTemplate( $group, $app, $location );
				}
			}

			return $this->templates[ $app ][ $location ][ $group ];
		}
		else
		{
			/* Group is saved clean */
			$group = static::cleanGroupName( $group );

			if ( ( $app !== 'cms' ) OR ( $app === 'cms' AND \in_array( $location, array( 'admin', 'front', 'global' ) ) ) )
			{
				return \IPS\Theme::i()->getTemplate( $group, $app, $location );
			}

			$key = \strtolower( 'template_cms_' .static::makeBuiltTemplateLookupHash( $app, $location, $group ) . '_' . static::cleanGroupName( $group ) );

			/* Still here */
			if ( !\in_array( $key, array_keys( static::$calledTemplates ) ) )
			{
				/* If we don't have a compiled template, do that now */
				if ( !isset( \IPS\Data\Store::i()->$key ) )
				{
					$this->compileTemplates( $app, $location, $group );
				}

				/* Still no key? */
				if ( ! isset( \IPS\Data\Store::i()->$key ) )
				{
					\IPS\Log::log( "Template store key: {$key} missing ({$app}, {$location}, {$group})", "template_store_missing" );

					throw new \ErrorException('template_store_missing ' . $key);
				}

				/* Load compiled template */
				$compiledGroup = \IPS\Data\Store::i()->$key;
				try
				{
					if ( @eval( $compiledGroup ) === FALSE )
					{
						throw new \UnexpectedValueException;
					}
				}
				catch ( \ParseError $e )
				{
					throw new \UnexpectedValueException;
				}

				/* Hooks */
				$class = 'class_' . $app . '_' . $location . '_' . $group;
				$class = "\IPS\Theme\\{$class}";

				/* Init */
				static::$calledTemplates[ $key ] = new $class();
			}

			return static::$calledTemplates[ $key ];
		}
	}

	/**
	 * Build Templates ready for non IN_DEV use
	 * This fetches all templates in a group, converts HTML logic into ready to eval PHP and stores as a single PHP class per template group
	 *
	 * @param	string|array	$app		Templates app (e.g. core, forum)
	 * @param	string|array	$location	Templates location (e.g. admin,global,front)
	 * @param	string|array	$group		Templates group (e.g. forms, members)
	 * @return	void
	 */
	public function compileTemplates( $app=null, $location=null, $group=null )
	{
		$templates = $this->getRawTemplates( $app, $location, static::cleanGroupName( $group ) );

		foreach( $templates as $templateApp => $v )
		{
			foreach( $templates[ $templateApp ] as $location => $groups )
			{
				foreach( $templates[ $templateApp ][ $location ] as $group => $bits )
				{

					/* Any template hooks? */
					$templateHooks = array();
					if( isset( \IPS\IPS::$hooks[ "\IPS\Theme\class_{$app}_{$location}_{$group}" ] ) AND \IPS\RECOVERY_MODE === FALSE )
					{
						foreach ( \IPS\IPS::$hooks[ "\IPS\Theme\class_{$app}_{$location}_{$group}" ] as $k => $data )
						{
							if ( !class_exists( "IPS\Theme\hook{$k}", FALSE ) )
							{
								try
								{
									if ( @eval( "namespace IPS\Theme;\n\n" . str_replace( ' extends _HOOK_CLASS_', '', file_get_contents( \IPS\ROOT_PATH . '/' . $data['file'] ) ) ) !== FALSE )
									{
										$class = "IPS\Theme\hook{$data['class']}";
										$templateHooks = array_merge( $templateHooks, $class::hookData() );
									}
								}
								catch ( \ParseError $e ) {}
							}
						}
					}

					/* Build all the functions */
					$functions = array();
					foreach( $templates[ $templateApp ][ $location ][ $group ] as $name => $data )
					{
						if ( isset( $templateHooks[ $name ] ) )
						{
							$data['template_content'] = static::themeHooks( $data['template_content'], $templateHooks[ $name ] );
						}

						$functions[ $name ] = static::compileTemplate( $data['template_content'], $data['template_title'], $data['template_params'], true, false, $app, $location, $group );
					}

					/* Put them in a class */
					$template = <<<EOF
namespace IPS\Theme;
class class_{$app}_{$location}_{$group}
{

EOF;
					$template .= implode( "\n\n", $functions );

					$template .= <<<EOF
}
EOF;

					/* Store it */
					$key = \strtolower( 'template_cms_' . static::makeBuiltTemplateLookupHash( $app, $location, $group ) . '_' . static::cleanGroupName( $group ) );

					\IPS\Data\Store::i()->$key = $template;
				}
			}
		}
	}

	/**
	 * Delete compiled templates
	 * Removes compiled templates bits for all themes that match the arguments
	 *
	 * @param	string		$app		Application Directory (core, forums, etc)
	 * @param	string|null	$location	Template location (front, admin, global, etc)
	 * @param	string|null	$group		Template group (forms, messaging, etc)
	 * @param	int|null	$themeId	Limit to a specific theme (and children)
	 * @return 	void
	 */
	public static function deleteCompiledTemplate( $app=null, $location=null, $group=null, $themeId=null )
	{
		$templates = \IPS\cms\Theme::i()->getRawTemplates( $app, $location, $group );

		foreach( $templates as $templateApp => $v )
		{
			foreach( $templates[ $templateApp ] as $location => $groups )
			{
				foreach( $templates[ $templateApp ][ $location ] as $group => $bits )
				{
					$key = \strtolower( 'template_cms_' . static::makeBuiltTemplateLookupHash( $app, $location, $group ) . '_' . static::cleanGroupName( $group ) );

					if ( isset( \IPS\Data\Store::i()->$key ) )
					{
						unset( \IPS\Data\Store::i()->$key );
					}
				}
			}
		}

		parent::deleteCompiledTemplate( $app, $location, $group, $themeId );
	}

	/**
	 * Returns the path for the IN_DEV .phtml files
	 * @param string 	 	  $app			Application Key
	 * @param string|null	  $location		Location
	 * @param string|null 	  $path			Path or Filename
	 * @return string
	 */
	protected static function _getHtmlPath( $app, $location=null, $path=null )
	{
		return rtrim( \IPS\ROOT_PATH . "/applications/{$app}/dev/html/{$location}/{$path}", '/' ) . '/';
	}

}