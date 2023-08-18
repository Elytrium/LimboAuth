<?php

/**
 * @brief		Converter Library CMS Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Library;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invision Pages Support
 * @note	We must extend the Core Library here so we can access methods like convertAttachment, convertFollow, etc
 */
class _Cms extends Core
{
	/**
	 * @brief	Application
	 */
	public $app = 'cms';
	
	/**
	 * Returns a block of text, or a language string, that explains what the admin must do to complete this conversion
	 *
	 * @return	string
	 */
	public function getPostConversionInformation()
	{
		return parent::getPostConversionInformation() . \IPS\Member::loggedIn()->language()->addToStack( 'convert_cms_info_message' );
	}

	/**
	 * Returns an array of items that we can convert, including the amount of rows stored in the Community Suite as well as the recommend value of rows to convert per cycle
	 *
	 * @param	bool	$rowCounts		enable row counts
	 * @return	array
	 */
	public function menuRows( $rowCounts=FALSE )
	{
		$return		= array();
		$extraRows 	= $this->software->extraMenuRows();

		foreach( $this->getConvertableItems() as $k => $v )
		{
			switch( $k )
			{
				case 'convertCmsBlocks':
					$dependencies = array();
					if ( array_key_exists( 'convertCmsContainers', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertCmsContainers';
					}
					$return[ $k ] = array(
						'step_title'	=> 'convert_cms_blocks',
						'step_method'	=> 'convertCmsBlocks',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'cms_blocks' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 100,
						'dependencies'	=> array(),
						'link_type'		=> 'cms_blocks',
					);
					break;
				
				case 'convertCmsContainers':
					$return[ $k ] = array(
						'step_title'	=> 'convert_cms_containers',
						'step_method'	=> 'convertCmsContainers',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'cms_containers' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array(),
						'link_type'		=> 'cms_containers',
					);
					break;
				
				case 'convertCmsDatabases':
					$dependencies = array();
					if ( array_key_exists( 'convertCmsPages', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertCmsPages';
					}
					$return[ $k ] = array(
						'step_title'	=> 'convert_cms_databases',
						'step_method'	=> 'convertCmsDatabases',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'cms_databases' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 100,
						'dependencies'	=> $dependencies,
						'link_type'		=> 'cms_databases',
					);
					break;
				
				case 'convertCmsDatabaseCategories':
					$return[ $k ] = array(
						'step_title'	=> 'convert_cms_database_categories',
						'step_method'	=> 'convertCmsDatabaseCategories',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'cms_database_categories' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array( 'convertCmsDatabases' ),
						'link_type'		=> 'cms_database_categories',
					);
					break;
				
				case 'convertCmsDatabaseRecords':
					$count = 0;
					$links = array();
					foreach( \IPS\Db::i()->select( 'database_id', 'cms_databases' ) AS $database )
					{
						if( $rowCounts )
						{
							$count += \IPS\Db::i()->select( 'COUNT(*)', "cms_custom_database_{$database}" )->first();
						}
						$links[] = "cms_custom_database_{$database}";
					}

					$dependencies = array( 'convertCmsDatabases' );
					if ( array_key_exists( 'convertCmsDatabaseCategories', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertCmsDatabaseCategories';
					}
					
					$return[ $k ] = array(
						'step_title'		=> 'convert_cms_database_records',
						'step_method'		=> 'convertCmsDatabaseRecords',
						'ips_rows'			=> $count,
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> $dependencies,
						'link_type'			=> $links,
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertCmsDatabaseComments':
					$return[ $k ] = array(
						'step_title'		=> 'convert_cms_database_comments',
						'step_method'		=> 'convertCmsDatabaseComments',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'cms_database_comments' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertCmsDatabaseRecords' ),
						'link_type'			=> 'cms_database_comments',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertCmsDatabaseReviews':
					$return[ $k ] = array(
						'step_title'		=> 'convert_cms_database_reviews',
						'step_method'		=> 'convertCmsDatabaseReviews',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'cms_database_reviews' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertCmsDatabaseRecords' ),
						'link_type'			=> 'cms_database_reviews',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertCmsFolders':
					$return[ $k ] = array(
						'step_title'	=> 'convert_cms_folders',
						'step_method'	=> 'convertCmsFolders',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'cms_folders' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array(),
						'link_type'		=> 'cms_folders',
					);
					break;
				
				case 'convertCmsMedia':
					$dependencies = array();
					if ( array_key_exists( 'convertCmsMediaFolders', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertCmsMediaFolders';
					}
					
					$return[ $k ] = array(
						'step_title'	=> 'convert_cms_media',
						'step_method'	=> 'convertCmsMedia',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'cms_media' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 10,
						'dependencies'	=> $dependencies,
						'link_type'		=> 'cms_media',
					);
					break;
				
				case 'convertCmsMediaFolders':
					$return[ $k ] = array(
						'step_title'	=> 'convert_cms_media_folders',
						'step_method'	=> 'convertCmsMediaFolders',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'cms_media_folders' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array(),
						'link_type'		=> 'cms_media_folders',
					);
					break;
				
				case 'convertCmsPages':
					$dependencies = array();
					if ( array_key_exists( 'convertCmsFolders', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertCmsFolders';
					}
					
					$return[ $k ] = array(
						'step_title'	=> 'convert_cms_pages',
						'step_method'	=> 'convertCmsPages',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'cms_pages' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 100,
						'dependencies'	=> $dependencies,
						'link_type'		=> 'cms_pages',
					);
					break;
				
				case 'convertAttachments':
					$dependencies = array( 'convertCmsDatabaseRecords' );
					
					if ( array_key_exists( 'convertCmsDatabaseComments', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertCmsDatabaseComments';
					}
					
					if ( array_key_exists( 'convertCmsDatabaseReviews', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertCmsDatabaseReviews';
					}

					$in = array();
					foreach( \IPS\Db::i()->select( 'database_id', 'cms_databases' ) AS $database )
					{
						$in[] = $database;
					}

					$return[ $k ] = array(
						'step_title'	=> 'convert_cms_attachments',
						'step_method'	=> 'convertAttachments',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments_map', array( \IPS\Db::i()->in( 'id3', $in ) . " AND location_key=?", 'cms_Records' ) ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 10,
						'dependencies'	=> $dependencies,
						'link_type'		=> 'core_attachments',
					);
					break;
			}

			/* Append any extra steps immediately to retain ordering */
			if( isset( $v['extra_steps'] ) )
			{
				foreach( $v['extra_steps'] as $extra )
				{
					$return[ $extra ] = $extraRows[ $extra ];
				}
			}
		}

		/* Run the queries if we want row counts */
		if( $rowCounts )
		{
			$return = $this->getDatabaseRowCounts( $return );
		}

		return $return;
	}
	
	/**
	 * Returns an array of tables that need to be truncated when Empty Local Data is used
	 *
	 * @param	string	$method	Method to truncate
	 * @return	array
	 */
	protected function truncate( $method )
	{
		$return		= array();
		$classname	= \get_class( $this->software );
		foreach( $classname::canConvert() as $k => $v )
		{
			switch( $k )
			{
				case 'convertCmsFolders':
					$return['convertCmsFolders'] = array( 'cms_folders' => NULL );
					break;
				
				case 'convertCmsPages':
					$return['convertCmsPages'] = array( 'cms_pages' => NULL );
					break;
				
				case 'convertCmsContainers':
					$return['convertCmsContainers'] = array( 'cms_containers' => NULL );
					break;
				
				case 'convertCmsBlocks':
					$return['convertCmsBlocks'] = array(
															'cms_blocks' => NULL,
															'core_permission_index' => array( 'app=? AND perm_type=?', 'cms', 'blocks' )
										);
					break;
				
				case 'convertCmsDatabases':
					if ( $method == $k )
					{
						foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'cms_databases' ), 'IPS\cms\Databases' ) AS $database )
						{
							$database->delete();
						}
					}

					/* Only return if we're truncating this step */
					if( $method == 'convertCmsDatabases' )
					{
						return array();
					}
					break;
				
				case 'convertCmsDatabaseCategories':
					$return['convertCmsDatabaseCategories'] = array( 'cms_database_categories' => NULL );
					break;
				
				case 'convertCmsDatabaseRecords':
					if ( $method == $k )
					{
						$toReturn = array();
						foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'cms_databases' ), 'IPS\cms\Databases' ) AS $database )
						{
							$toReturn["cms_custom_database_{$database->_id}"] = NULL;
						}
						$return['convertCmsDatabaseRecords'] = $toReturn;
					}

					$return['convertCmsDatabaseRecords']['core_tags'] = array( 'tag_meta_app=?', 'cms' );
					break;
				
				case 'convertCmsDatabaseComments':
					$return['convertCmsDatabaseComments'] = array( 'cms_database_comments' => NULL );
					break;
				
				case 'convertCmsDatabaseReviews':
					$return['convertCmsDatabaseReviews'] = array( 'cms_database_reviews' => NULL );
					break;
				
				case 'convertCmsMediaFolders':
					$return['convertCmsMediaFolders'] = array( 'cms_media_folders' => NULL );
					break;
				
				case 'convertCmsMedia':
					$return['convertCmsMedia'] = array( 'cms_media' => NULL );
					break;

				case 'convertAttachments':
					$subQuery = (string) \IPS\Db::i()->select( 'attachment_id', 'core_attachments_map', array( "location_key='cms_Records'" ) );
					$return['convertAttachments'] = array( 'core_attachments' => "attach_id IN ( {$subQuery} )", 'core_attachments_map' => array( "location_key=?", 'cms_Records' ) );
					break;

			}
		}

		return isset( $return[ $method ] ) ? $return[ $method ] : array();
	}
	
	/**
	 * This is how the insert methods will work - basically like 3.x, but we should be using the actual classes to insert the data unless there is a real world reason not too.
	 * Using the actual routines to insert data will help to avoid having to resynchronize and rebuild things later on, thus resulting in less conversion time being needed overall.
	 * Anything that parses content, for example, may need to simply insert directly then rebuild via a task over time, as HTML Purifier is slow when mass inserting content.
	 */
	
	/**
	 * A note on logging -
	 * If the data is missing and it is unlikely that any source software would be able to provide this, we do not need to log anything and can use default data (for example, group_layout in convertLeaderGroups).
	 * If the data is missing and it is likely that a majority of the source software can provide this, we should log a NOTICE and use default data (for example, a_casesensitive in convertAcronyms).
	 * If the data is missing and it is required to convert the item, we should log a WARNING and return FALSE.
	 * If the conversion absolutely cannot proceed at all (filestorage locations not writable, for example), then we should log an ERROR and throw an \IPS\convert\Exception to completely halt the process and redirect to an error screen showing the last logged error.
	 */
	
	/**
	 * Convert a Folder
	 *
	 * @param	array			$info	Data to insert.
	 * @return	integer|boolean	The ID of the newly inserted folder, or FALSE on failure.
	 */
	public function convertCmsFolder( $info=array() )
	{
		if ( !isset( $info['folder_id'] ) )
		{
			$this->software->app->log( 'cms_folder_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( !isset( $info['folder_name'] ) )
		{
			$info['folder_name'] = "untitledfolder{$info['folder_id']}";
		}
		
		if ( isset( $info['folder_parent_id'] ) )
		{
			try
			{
				$info['folder_parent_id'] = $this->software->app->getLink( $info['folder_parent_id'], 'cms_folders' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['folder_conv_parent'] = $info['folder_parent_id'];
			}
		}
		else
		{
			$info['folder_parent_id'] = 0;
		}
		
		if ( isset( $info['folder_last_modified'] ) )
		{
			if ( $info['folder_last_modified'] instanceof \IPS\DateTime )
			{
				$info['folder_last_modified'] = $info['folder_last_modified']->getTimestamp();
			}
		}
		else
		{
			$info['folder_last_modified'] = time();
		}
		
		if ( !isset( $info['folder_path'] ) )
		{
			$info['folder_path'] = $info['folder_name'];
		}
		
		$id = $info['folder_id'];
		unset( $info['folder_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'cms_folders', $info );
		$this->software->app->addLink( $inserted_id, $id, 'cms_folders' );
		
		\IPS\Db::i()->update( 'cms_folders', array( 'folder_parent_id' => $inserted_id ), array( "folder_conv_parent=?", $id ) );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a Page
	 *
	 * @param	array			$info	Data to insert.
	 * @return	integer|boolean	The ID of the newly inserted page, or FALSE on failure.
	 */
	public function convertCmsPage( $info=array() )
	{
		if ( !isset( $info['page_id'] ) )
		{
			$this->software->app->log( 'cms_page_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		/* Get these out of the way - we won't know what they are, can't convert them, or are unused */
		$info['page_theme']				= 0;
		$info['page_type']				= 'html';
		$info['page_template']			= '';
		$info['page_quicknav']			= 1;
		$info['page_wrapper_template']	= '_none_';
		$info['page_has_error']			= 0;
		$info['page_js_css_ids']		= '';
		$info['page_js_css_objects']	= json_encode( array( 'css' => NULL, 'js' => NULL ) );
		
		if ( !isset( $info['page_name'] ) OR empty( $info['page_name'] ) )
		{
			$info['page_name'] = "Untitled Page {$info['page_id']}";
		}
		
		if ( !isset( $info['page_seo_name'] ) OR empty( $info['page_seo_name'] ) )
		{
			$info['page_seo_name'] = \IPS\Http\Url::seoTitle( $info['page_name'] );
		}
		
		if ( !isset( $info['page_content'] ) )
		{
			$info['page_content'] = '';
		}
		
		if ( isset( $info['page_meta_keywords'] ) )
		{
			if ( \is_array( $info['page_meta_keywords'] ) )
			{
				$info['page_meta_keywords'] = implode( ',', $info['page_meta_keywords'] );
			}
		}
		else
		{
			$info['page_meta_keywords'] = '';
		}
		
		if ( !isset( $info['page_meta_description'] ) )
		{
			$info['page_meta_description'] = '';
		}
		
		if ( !isset( $info['page_ipb_wrapper'] ) )
		{
			$info['page_ipb_wrapper'] = 1;
		}
		
		if ( !isset( $info['page_title'] ) )
		{
			$info['page_title'] = $info['page_name'];
		}
		
		if ( isset( $info['page_folder_id'] ) )
		{
			try
			{
				$info['page_folder_id'] = $this->software->app->getLink( $info['page_folder_id'], 'cms_folders' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['page_folder_id'] = 0;
			}
		}
		else
		{
			$info['page_folder_id'] = 0;
		}

		/* We cannot have a page name the same as a folder name in this folder */
		try
		{
			/* Check folder */
			if ( \intval( $info['page_folder_id'] ) == \IPS\cms\Pages\Folder::load( $info['page_seo_name'], 'folder_name' )->parent_id )
			{
				$info['page_seo_name'] = \IPS\Http\Url::seoTitle( $info['page_name'] . '-' . $info['page_id'] );
			}
		}
		catch ( \OutOfRangeException $e ) {}

		/* Check that we don't have the same FURL as an app */
		if ( \IPS\cms\Pages\Page::isFurlCollision( $info['page_seo_name'] ) )
		{
			$info['page_seo_name'] = \IPS\Http\Url::seoTitle( $info['page_name'] . '-page' );
		}
		
		if ( !isset( $info['page_full_path'] ) )
		{
			try
			{
				$info['page_full_path'] = \IPS\Db::i()->select( 'folder_path', 'cms_folders', array( "folder_id=?", $info['page_folder_id'] ) )->first() . '/' . $info['page_seo_name'];
			}
			catch( \UnderflowException $e )
			{
				$info['page_full_path'] = $info['page_seo_name'];
			}
		}
		else
		{
			$info['page_full_path'] = $info['page_seo_name'];
		}
		
		if ( !isset( $info['page_show_sidebar'] ) )
		{
			$info['page_show_sidebar'] = 0;
		}
		
		if ( !isset( $info['page_default'] ) )
		{
			$info['page_default'] = 0;
		}
		
		$id = $info['page_id'];
		unset( $info['page_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'cms_pages', $info );
		$this->software->app->addLink( $inserted_id, $id, 'cms_pages' );
		\IPS\Lang::saveCustom( 'cms', "cms_page_{$inserted_id}", $info['page_name'] );

		\IPS\Db::i()->insert( 'core_permission_index', array( 'app' => 'cms', 'perm_type' => 'pages', 'perm_type_id' => $inserted_id, 'perm_view' => '' ) );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a block container
	 *
	 * @param	array			$info	Data to insert.
	 * @return	integer|boolean	The ID of the newly inserted container, or FALSE on failure.
	 */
	public function convertCmsContainer( $info=array() )
	{
		if ( !isset( $info['container_id'] ) )
		{
			$this->software->app->log( 'cms_container_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( !isset( $info['container_name'] ) )
		{
			$info['container_name'] = "Converted Blocks";
			$this->software->app->log( 'cms_container_no_name', __METHOD__, \IPS\convert\ApP::LOG_NOTICE, $info['container_id'] );
		}
		
		if ( isset( $info['container_parent_id'] ) )
		{
			try
			{
				$info['container_parent_id'] = $this->software->app->getLink( $info['container_parent_id'], 'cms_containers' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['container_conv_parent'] = $info['container_parent_id'];
			}
		}
		else
		{
			$info['container_parent_id'] = 0;
		}
		
		if ( !isset( $info['container_type'] ) OR !\in_array( $info['container_type'], array( 'block' ) ) )
		{
			$info['container_type'] = 'block';
		}
		
		if ( !isset( $info['container_order'] ) )
		{
			$position = \IPS\Db::i()->select( 'MAX(container_order)', 'cms_containers' )->first();
			
			$info['container_order'] = $position + 1;
		}
		
		if ( !isset( $info['container_key'] ) )
		{
			$info['container_key'] = \IPS\Http\Url::seoTitle( $info['container_name'] );
		}
		
		$id = $info['container_id'];
		unset( $info['container_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'cms_containers', $info );
		$this->software->app->addLink( $inserted_id, $id, 'cms_containers' );
		
		\IPS\Db::i()->update( 'cms_containers', array( "container_parent_id" => $inserted_id ), array( "container_conv_parent=?", $id ) );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a block
	 * @note These probably won't really work unless going from Invision Community to Invision Community, but we still support them anyway so they can be refactored.
	 *
	 * @param	array			$info	Data to insert.
	 * @return	integer|boolean	The ID of the newly inserted block, or FALSE on failure.
	 */
	public function convertCmsBlock( $info )
	{
		if ( !isset( $info['block_id'] ) )
		{
			$this->software->app->log( 'cms_block_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['block_name'] ) )
		{
			$name = $info['block_name'];
			unset( $info['block_name'] );
		}
		else
		{
			$name = "Untitled Block {$info['block_id']}";
		}
		
		if ( isset( $info['block_description'] ) )
		{
			$desc = $info['block_description'];
			unset( $info['block_description'] );
		}
		else
		{
			$desc = '';
		}
		
		if ( !isset( $info['block_key'] ) )
		{
			$info['block_key'] = \IPS\Http\Url::seoTitle( $name );
		}
		
		/* We cannot do these - force defaults */
		$info['block_template']			= 0;
		$info['block_template_params']	= '';
		$info['block_type']				= 'custom';
		$info['block_plugin']			= NULL;
		$info['block_plugin_config']	= NULL;
		$info['block_plugin_app']		= NULL;
		$info['block_plugin_plugin']	= NULL;
		
		if ( isset( $info['block_config'] ) )
		{
			if ( !\is_array( $info['block_config'] ) )
			{
				$info['block_config'] = json_decode( $info['block_config'], TRUE );
			}
			
			if ( !$info['block_config'] )
			{
				$info['block_config'] = array( 'editor' => 'html' );
			}
			else
			{
				if ( !isset( $info['block_config']['editor'] ) OR !\in_array( $info['block_config']['editor'], array( 'editor', 'html', 'php' ) ) )
				{
					$info['block_config']['editor'] = 'html';
				}
			}
		}
		else
		{
			$info['block_config'] = array( 'editor' => 'html' );
		}
		
		$info['block_config'] = json_encode( $info['block_config'] );
		
		if ( !isset( $info['block_content'] ) )
		{
			$info['block_content'] = NULL;
		}
		
		if ( !isset( $info['block_position'] ) )
		{
			$position = \IPS\Db::i()->select( 'MAX(block_position)', 'cms_blocks' )->first();
			
			$info['block_position'] = $position + 1;
		}
		
		if ( isset( $info['block_category'] ) )
		{
			try
			{
				$info['block_category'] = $this->software->app->getLink( $info['block_category'], 'cms_containers' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['block_category'] = $this->_orphanedBlocksCategory();
			}
		}
		else
		{
			$info['block_category'] = $this->_orphanedBlocksCategory();
		}
		
		if ( !isset( $info['block_cache'] ) )
		{
			$info['block_cache'] = 0;
		}
		
		$id = $info['block_id'];
		unset( $info['block_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'cms_blocks', $info );
		$this->software->app->addLink( $inserted_id, $id, 'cms_blocks' );
		
		\IPS\Lang::saveCustom( 'cms', "content_block_name_{$inserted_id}", $name );
		\IPS\Lang::saveCustom( 'cms', "content_block_name_{$inserted_id}_desc", $desc );

		\IPS\Db::i()->insert( 'core_permission_index', array( 'app' => 'cms', 'perm_type' => 'blocks', 'perm_type_id' => $inserted_id, 'perm_view' => '' ) );
		
		return $inserted_id;
	}
	
	/**
	 * @brief	Orphaned Blocks Container
	 */
	protected $orphanedBlockContainer = NULL;
	
	/**
	 * Returns a category to store blocks that do not have one, creating one if needed.
	 *
	 * @return	integer	The ID.
	 */
	protected function _orphanedBlocksCategory()
	{
		if ( $this->orphanedBlockContainer === NULL )
		{
			try
			{
				$this->orphanedBlockContainer = $this->software->app->getLink( '__orphaned__', 'cms_containers' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->orphanedBlockContainer = $this->convertCmsContainer( array(
					'container_id'		=> '__orphaned__',
					'container_name'	=> "Converted Blocks",
				) );
			}
		}
		
		return $this->orphanedBlockContainer;
	}
	
	/**
	 * Convert a database
	 *
	 * @param	array			$info	Data to insert
	 * @param	array			$fields	Custom Field Data
	 * @return	integer|boolean	The ID of the newly inserted database, or FALSE on failure.
	 */
	public function convertCmsDatabase( $info=array(), $fields=array() )
	{
		/* This is about to get caaaraaaaaazy */
		if ( !isset( $info['database_id'] ) )
		{
			$this->software->app->log( 'cms_database_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		/* No fields - we need at least one */
		if ( !\count( $fields ) )
		{
			$this->software->app->log( 'cms_database_no_fields', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['database_id'] );
			return FALSE;
		}
		
		/* Language Stuff */
		if ( isset( $info['database_name'] ) )
		{
			$name = $info['database_name'];
			unset( $info['database_name'] );
		}
		else
		{
			$name = "Database {$info['database_id']}";
		}
		
		if ( isset( $info['database_description'] ) )
		{
			$desc = $info['database_description'];
			unset( $info['database_description'] );
		}
		else
		{
			$desc = '';
		}
		
		if ( isset( $info['database_sln'] ) )
		{
			$sln = $info['database_sln'];
			unset( $info['database_sln'] );
		}
		else
		{
			$sln = 'record';
		}
		
		if ( isset( $info['database_pln'] ) )
		{
			$pln = $info['database_pln'];
			unset( $info['database_pln'] );
		}
		else
		{
			$pln = 'records';
		}
		
		if ( isset( $info['database_scn'] ) )
		{
			$scn = $info['database_scn'];
			unset( $info['database_scn'] );
		}
		else
		{
			$scn = "Record";
		}
		
		if ( isset( $info['database_pcn'] ) )
		{
			$pcn = $info['database_pcn'];
			unset( $info['database_pcn'] );
		}
		else
		{
			$pcn = "Records";
		}
		
		if ( isset( $info['database_ia'] ) )
		{
			$ia = $info['database_ia'];
			unset( $info['database_ia'] );
		}
		else
		{
			$ia = "a record";
		}
		
		if ( !isset( $info['database_key'] ) )
		{
			$info['database_key'] = \IPS\Http\Url::seoTitle( $name ) . '_' . time();
		}
		
		/* Zero Defaults */
		foreach( array( 'database_record_count', 'database_all_editable', 'database_comment_approve', 'database_record_approve', 'database_tags_enabled', 'database_tags_noprefixes', 'database_cat_index_type' ) AS $zeroDefault )
		{
			if ( !isset( $info[ $zeroDefault ] ) )
			{
				$info[ $zeroDefault ] = 0;
			}
		}
		
		/* One Defaults */
		foreach( array( 'database_revisions', 'database_rss', 'database_comment_bump', 'database_search', 'database_use_categories' ) as $oneDefault )
		{
			if ( !isset( $info[ $oneDefault ] ) )
			{
				$info[ $oneDefault ] = 1;
			}
		}
		
		if ( isset( $info['database_options'] ) )
		{
			if ( \is_array( $info['database_options'] ) )
			{
				$bitoptions = 0;
				
				foreach( \IPS\cms\Databases::$bitOptions as $key => $value )
				{
					if ( isset( $info['database_options'][$key] ) AND $info['database_options'][$key] )
					{
						$bitoptions += $value;
					}
				}
				
				$info['database_options'] = $bitoptions;
			}
		}
		else
		{
			$info['database_options'] = 1;
		}
		
		if ( isset( $info['database_fixed_field_perms'] ) )
		{
			if ( \is_array( $info['database_fixed_field_perms'] ) )
			{
				$info['database_fixed_field_perms'] = json_encode( $info['database_fixed_field_perms'] );
			}
		}
		else
		{
			$info['database_fixed_field_perms'] = json_encode( array(
				'record'		=> array(
					'visible'		=> TRUE,
					'perm_view'		=> '*',
					'perm_2'		=> '*',
					'perm_3'		=> '*'
				),
				'record_image'	=> array(
					'visible'		=> TRUE,
					'perm_view'		=> '*',
					'perm_2'		=> '*',
					'perm_3'		=> '*'
				)
			) );
		}
		
		if ( isset( $info['database_featured_settings'] ) )
		{
			if ( \is_array( $info['database_featured_settings'] ) )
			{
				$info['database_featured_settings'] = json_encode( $info['database_featured_settings'] );
			}
		}
		else
		{
			$info['database_featured_settings'] = json_encode( array(
				'featured'		=> FALSE,
				'perpage'		=> 10,
				'pagination'	=> FALSE,
				'sort'			=> 'record_publish_date',
				'direction'		=> 'desc',
				'categories'	=> 0,
			) );
		}
		
		if ( isset( $info['database_fixed_field_settings'] ) )
		{
			if ( \is_array( $info['database_fixed_field_settings'] ) )
			{
				$info['database_fixed_field_settings'] = json_encode( $info['database_fixed_field_settings'] );
			}
		}
		else
		{
			$info['database_fixed_field_settings'] = json_encode( array(
				'record_image'	=> array(
					'image_dims'	=> array( 0, 0 ),
					'thumb_dims'	=> array( 200, 200 ),
				)
			) );
		}
		
		/* These are things we will not know, or cannot convert (like templates) */
		$info['database_template_listing']		= 'listing';
		$info['database_template_display']		= 'display';
		$info['database_template_categories']	= 'category_index';
		$info['database_field_title']			= 0; # we will set this later
		$info['database_field_content']			= 0; # we will set this later
		$info['database_template_form']			= 'form';
		$info['database_template_featured']		= 'category_articles';
		
		/* Forum Integration */
		try
		{
			$this->software->app->checkForSibling( 'forums' );
			if ( \IPS\Application::appIsEnabled( 'forums' ) === FALSE )
			{
				throw new \LogicException;
			}
			
			foreach( array( 'database_forum_record', 'database_forum_comments', 'database_forum_delete' ) as $forumZero )
			{
				if ( !isset( $info[ $forumZero ] ) )
				{
					$info[ $forumZero ] = 0;
				}
			}
			
			foreach( array( 'database_forum_prefix', 'database_forum_suffix' ) as $forumEmpty )
			{
				if ( !isset( $info[ $forumEmpty ] ) )
				{
					$info[ $forumEmpty ] = '';
				}
			}
			
			if ( isset( $info['database_forum_forum'] ) )
			{
				$info['database_forum_forum'] = $this->software->app->getSiblingLink( $info['database_forum_forum'], 'forums_forums', 'forums' );
			}
			else
			{
				throw new \InvalidArgumentException;
			}
		}
		catch( \OutOfRangeException $e ) # no sibling or forum
		{
			$info['database_forum_record']		= 0;
			$info['database_forum_comments']	= 0;
			$info['database_forum_delete']		= 0;
			$info['database_forum_forum']		= 0;
			$info['database_forum_prefix']		= '';
			$info['database_forum_suffix']		= '';
		}
		catch( \InvalidArgumentException $e ) # no forum provided
		{
			$info['database_forum_record']		= 0;
			$info['database_forum_comments']	= 0;
			$info['database_forum_delete']		= 0;
			$info['database_forum_forum']		= 0;
			$info['database_forum_prefix']		= '';
			$info['database_forum_suffix']		= '';
		}
		catch( \LogicException $e ) # a bug
		{
			throw $e;
		}
		
		if ( isset( $info['database_tags_predefined'] ) )
		{
			if ( \is_array( $info['database_tags_predefined'] ) )
			{
				$info['database_tags_predefined'] = implode( ',', $info['database_tags_predefined'] );
			}
		}
		
		if ( isset( $info['database_page_id'] ) )
		{
			try
			{
				$info['database_page_id'] = $this->software->app->getLink( $info['database_page_id'], 'cms_pages' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['database_page_id'] = 0;
			}
		}
		else
		{
			$info['database_page_id'] = 0;
		}
		
		/* And we're finally done with this table */
		$id = $info['database_id'];
		unset( $info['database_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'cms_databases', $info );
		$this->software->app->addLink( $inserted_id, $id, 'cms_databases' );

		\IPS\Db::i()->insert( 'core_permission_index', array( 'app' => 'cms', 'perm_type' => 'databases', 'perm_type_id' => $inserted_id, 'perm_view' => '' ) );
		
		/* And now fields */
		$validFields		= array_merge( static::$fieldTypes, \IPS\cms\Fields::$additionalFieldTypes, array( 'Youtube', 'Spotify', 'Soundcloud' ) );
		$fieldsInserted		= array();
		$titleField			= 0;
		$contentField		= 0;
		$haveTitleField 	= FALSE;
		$haveContentField	= FALSE;
		foreach( $fields AS $field )
		{
			if ( !isset( $field['field_id'] ) )
			{
				continue;
			}
			
			/* We already know this */
			$field['field_database_id'] = $inserted_id;
			
			if ( !\in_array( $field['field_type'], $validFields ) )
			{
				continue;
			}
			
			if ( isset( $field['field_name'] ) )
			{
				$fieldName = $field['field_name'];
				unset( $field['field_name'] );
			}
			else
			{
				$fieldName = mb_ucfirst( $field['field_type'] );
			}
			
			if ( isset( $field['field_desc'] ) )
			{
				$fieldDesc = $field['field_desc'];
				unset( $field['field_desc'] );
			}
			else
			{
				$fieldDesc = '';
			}
			
			if ( !isset( $field['field_key'] ) )
			{
				$field['field_key'] = \strtolower( $field['field_type'] . '_' . $fieldsInserted );
			}
			
			if ( !isset( $field['field_required'] ) )
			{
				$field['field_required'] = 0;
			}
			
			if ( !isset( $field['field_user_editable'] ) )
			{
				$field['field_user_editable'] = 1;
			}
			
			if ( !isset( $field['field_position'] ) )
			{
				$position = \IPS\Db::i()->select( 'MAX(field_position)', 'cms_database_fields', array( 'field_database_id=?', $inserted_id ) )->first();
				
				$field['field_position'] = $position + 1;
			}
			
			if ( !isset( $field['field_max_length'] ) )
			{
				$field['field_max_length'] = 0;
			}
			
			if ( !isset( $field['field_extra'] ) )
			{
				$field['field_extra'] = '';
			}

			/* An Item Field needs extra data set */
			if( $field['field_type'] == 'Item' )
			{
				$fieldExtra = !empty( $field['field_extra'] ) ? $field['field_extra'] : array();

				if( isset( $fieldExtra['database'] ) )
				{
					try
					{
						$fieldExtra['database'] = $this->software->app->getLink( $fieldExtra['database'], 'cms_databases' );
					}
					catch( \OutOfRangeException $e )
					{
						/* Can't convert this if we don't know what it's referencing */
						$this->software->app->log( 'cms_database_field_item_no_reference', __METHOD__, \IPS\convert\APP::LOG_NOTICE, $field['field_id'] );
						continue;
					}
				}
				else
				{
					$fieldExtra['database'] = $inserted_id;
				}

				$field['field_extra'] = json_encode( $fieldExtra );
			}
			
			if ( !isset( $field['field_html'] ) )
			{
				$field['field_html'] = 0;
			}
			
			if ( !isset( $field['field_truncate'] ) )
			{
				$field['field_truncate'] = 100;
			}
			
			if ( !isset( $field['field_default_value'] ) )
			{
				$field['field_default_value'] = '';
			}
			
			if ( !isset( $field['field_display_listing'] ) )
			{
				$field['field_display_listing'] = 0;
			}
			
			if ( !isset( $field['field_display_display'] ) )
			{
				$field['field_display_display'] = 1;
			}
			
			if ( isset( $field['field_format_opts'] ) )
			{
				$opts = array();
				if ( !\is_array( $field['field_format_opts'] ) )
				{
					$field['field_format_opts'] = json_decode( $field['field_format_opts'], TRUE );
				}
				
				foreach( $field['field_format_opts'] AS $key => $value )
				{
					if ( \in_array( $value, array( 'strtolower', 'strtoupper', 'ucfirst', 'ucwords', 'punct', 'numerical', 'bold', 'italic' ) ) )
					{
						$opts[$key] = $value;
					}
				}
				
				if ( \count( $opts ) )
				{
					$field['field_format_opts'] = json_encode( $opts );
				}
				else
				{
					$field['field_format_opts'] = NULL;
				}
			}
			else
			{
				$field['field_format_opts'] = NULL;
			}
			
			if ( !isset( $field['field_validator'] ) )
			{
				$field['field_validator'] = 0;
			}
			
			if ( !isset( $field['field_topic_format'] ) )
			{
				$field['field_topic_format'] = '';
			}
			
			if ( !isset( $field['field_filter'] ) )
			{
				$field['field_filter'] = 0;
			}
			
			if ( isset( $field['field_allowed_extensions'] ) )
			{
				if ( \is_array( $field['field_allowed_extensions'] ) )
				{
					$field['field_allowed_extensions'] = json_encode( $field['field_allow_extensions'] );
				}
			}
			else
			{
				$field['field_allowed_extensions'] = json_encode( array() );
			}
			
			if ( !isset( $field['field_is_multiple'] ) )
			{
				$field['field_is_multiple'] = 0;
			}
			
			if ( !isset( $field['field_is_searchable'] ) )
			{
				$field['field_is_searchable'] = 1;
			}
			
			if ( !isset( $field['field_validator_custom'] ) )
			{
				$field['field_validator_custom'] = '';
			}
			
			if ( !isset( $field['field_display_commentform'] ) )
			{
				$field['field_display_commentform'] = 0;
			}
			
			if ( isset( $field['field_display_json'] ) )
			{
				if ( \is_array( $field['field_display_json'] ) )
				{
					$field['field_display_json'] = json_encode( $field['field_display_json'] );
				}
			}
			else
			{
				$field['field_display_json'] = json_encode( array(
					'display'	=> array( 'method' => NULL ),
					'listing'	=> array( 'method' => NULL )
				) );
			}
			
			if ( isset( $field['field_is_title'] ) AND $field['field_is_title'] )
			{
				$haveTitleField = TRUE;
			}
			unset( $field['field_is_title'] );
			
			if ( isset( $field['field_is_content'] ) AND $field['field_is_content'] )
			{
				$haveContentField = TRUE;
			}
			unset( $field['field_is_content'] );
			
			$fieldId = $field['field_id'];
			unset( $field['field_id'] );
			
			$fieldInsertedId = \IPS\Db::i()->insert( 'cms_database_fields', $field );
			\IPS\Lang::saveCustom( 'cms', "content_field_{$fieldInsertedId}", $fieldName );
			\IPS\Lang::saveCustom( 'cms', "content_field_{$fieldInsertedId}_desc", $fieldDesc );
			$this->software->app->addLink( $fieldInsertedId, $fieldId, 'cms_database_fields' );

			\IPS\Db::i()->insert( 'core_permission_index', array( 'app' => 'cms', 'perm_type' => 'fields', 'perm_type_id' => $fieldInsertedId, 'perm_view' => '' ) );
			
			if ( $haveTitleField AND $titleField == 0 )
			{
				$titleField = $fieldInsertedId;
			}
			
			if ( $haveContentField AND $contentField == 0 )
			{
				$contentField = $fieldInsertedId;
			}
			
			$fieldsInserted[$fieldInsertedId] = array( 'type' => $field['field_type'], 'multiple' => $field['field_is_multiple'], 'max_length' => $field['field_max_length'] );
		}
		
		if ( \count( $fieldsInserted ) > 0 )
		{
			\IPS\Db::i()->update( 'cms_databases', array( 'database_field_title' => $titleField, 'database_field_content' => $contentField ), array( "database_id=?", $inserted_id ) );
			\IPS\Lang::saveCustom( 'cms', "content_db_{$inserted_id}", $name );
			\IPS\Lang::saveCustom( 'cms', "content_db_{$inserted_id}_desc", $desc );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_sl_{$inserted_id}", $sln );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_pl_{$inserted_id}", $pln );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_su_{$inserted_id}", $scn );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_pu_{$inserted_id}", $pcn );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_ia_{$inserted_id}", $ia );
		}
		else
		{
			/* If we did not insert any fields, delete the database row and log a warning */
			\IPS\Db::i()->delete( 'cms_databases', array( "database_id=?", $inserted_id ) );
			$this->software->app->log( 'cms_database_no_fields', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['database_id'] );
			return FALSE;
		}
		
		/* If we're all done, we need to create the table... */
		$json  = json_decode( @file_get_contents( \IPS\ROOT_PATH . "/applications/cms/data/databaseschema.json" ), true );
		$table = $json['cms_custom_database_1'];
	
		$table['name'] = 'cms_custom_database_' . $inserted_id;
		
		foreach( $table['columns'] as $name => $data )
		{
			if ( mb_substr( $name, 0, 6 ) === 'field_' )
			{
				unset( $table['columns'][ $name ] );
			}
		}
		
		foreach( $table['indexes'] as $name => $data )
		{
			if ( mb_substr( $name, 0, 6 ) === 'field_' )
			{
				unset( $table['indexes'][ $name ] );
			}
		}
		
		try
		{
			if ( ! \IPS\Db::i()->checkForTable( $table['name'] ) )
			{
				\IPS\Db::i()->createTable( $table );
			}
		}
		catch( \IPS\Db\Exception $ex )
		{
			throw new \LogicException( $ex );
		}
		
		foreach( $fieldsInserted as $name => $data )
		{
			$columnDefinition = array( 'name' => "field_{$name}" );
			
			switch( $data['type'] )
			{
				case 'Member':
					if ( $data['multiple'] )
					{
						$columnDefinition['type']	= 'TEXT';
					}
					else
					{
						$columnDefinition['type']	= 'INT';
						$columnDefinition['length']	= 10;
					}
					break;
					
				case 'Date':
				case 'Poll':
					$columnDefinition['type'] = 'INT';
					$columnDefinition['length'] = 10;
					break;
				
				case 'Editor':
					$columnDefinition['type'] = 'MEDIUMTEXT';
					break;
				
				case 'TextArea':
				case 'Upload':
				case 'Address':
				case 'Codemirror':
				case 'Select':
				case 'CheckboxSet':
				case 'Youtube':
				case 'Spotify':
				case 'Soundcloud':
				case 'Item':
					$columnDefinition['type'] = 'TEXT';
					break;
				
				case 'Email':
				case 'Password':
				case 'Tel':
				case 'Text':
				case 'Url':
				case 'Color':
				case 'Radio':
				case 'Number':
					$columnDefinition['type'] = 'VARCHAR';
					$columnDefinition['length'] = 255;
					break;
				
				case 'YesNo':
				case 'Checkbox':
				case 'Rating':
					$columnDefinition['type'] = 'TINYINT';
					$columnDefinition['length'] = 1;
					break;
			}
			
			if ( isset( $data['max_length'] ) AND $data['max_length'] )
			{
				if( $data['max_length'] > 255 )
				{
					$columnDefinition['type'] = 'MEDIUMTEXT';

					if( isset( $columnDefinition['length'] ) )
					{
						unset( $columnDefinition['length'] );
					}
				}
				else
				{
					$columnDefinition['length'] = $data['max_length'];
				}
			}
			
			\IPS\Db::i()->addColumn( "cms_custom_database_{$inserted_id}", $columnDefinition );
			
			if ( $data['type'] != 'Upload' )
			{
				if ( \in_array( $columnDefinition['type'], [ 'TEXT', 'MEDIUMTEXT' ] ) )
				{
					\IPS\Db::i()->addIndex( "cms_custom_database_{$inserted_id}", array( 'type' => 'fulltext', 'name' => $columnDefinition['name'], 'columns' => array( $columnDefinition['name'] ) ) );
				}
				else
				{
					\IPS\Db::i()->addIndex( "cms_custom_database_{$inserted_id}", array( 'type' => 'key', 'name' => $columnDefinition['name'], 'columns' => array( $columnDefinition['name'] ) ) );
				}
			}
		}
		
		/* Make sure the page has the appropriate tag */
		try
		{
			$page = \IPS\Db::i()->select( 'page_content', 'cms_pages', array( "page_id=?", $info['database_page_id'] ) )->first();
			
			if ( preg_match( '/\{database=\"' . $id . '\"\}/', $page ) )
			{
				$page = str_replace( "{database=\"{$id}\"}", "{database=\"{$inserted_id}\"}", $page );
			}
			else
			{
				$page .= "\n{database=\"{$inserted_id}\"}";
			}
			
			\IPS\Db::i()->update( 'cms_pages', array( 'page_content' => $page ), array( "page_id=?", $info['database_page_id'] ) );
		}
		catch( \UnderflowException $e ) {}
		catch( \IPS\Db\Exception $e ) {}

		unset( \IPS\Data\Store::i()->cms_databases );
		
		/* And return */
		return $inserted_id;
	}
	
	/**
	 * Convert a database category
	 *
	 * @param	array			$info	Data to insert.
	 * @return	integer|boolean	The ID of the newly inserted category, or FALSE on failure.
	 */
	public function convertCmsDatabaseCategory( $info=array() )
	{
		if ( !isset( $info['category_id'] ) )
		{
			$this->software->app->log( 'cms_database_category_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['category_database_id'] ) )
		{
			try
			{
				$info['category_database_id'] = $this->software->app->getLink( $info['category_database_id'], 'cms_databases' );
			}
			catch( \OutRangeException $e )
			{
				$this->software->app->log( 'cms_database_category_missing_database', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['category_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'cms_database_category_missing_database', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['category_id'] );
			return FALSE;
		}
		
		if ( !isset( $info['category_name'] ) )
		{
			$info['category_name'] = \IPS\Member::loggedIn()->language()->get( "content_db_{$info['category_database_id']}" ) . " Category {$info['category_id']}";
		}
		
		if ( array_key_exists( 'category_desc', $info ) )
		{
			$desc = !empty( $info['category_desc'] ) ? $info['category_desc'] : '';
			unset( $info['category_desc'] );
		}
		else
		{
			$desc = '';
		}
		
		/* Do not know */
		$info['category_last_record_id'] = 0;
		
		if ( isset( $info['category_last_record_date'] ) )
		{
			if ( $info['category_last_record_date'] instanceof \IPS\DateTime )
			{
				$info['category_last_record_date'] = $info['category_last_record_date']->getTimestamp();
			}
		}
		else
		{
			$info['category_last_record_date'] = 0;
		}
		
		if ( isset( $info['category_last_record_member'] ) )
		{
			try
			{
				$info['category_last_record_member'] = $this->software->app->getLink( $info['category_last_record_member'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['category_last_record_member'] = 0;
			}
		}
		else
		{
			$info['category_last_record_member'] = 0;
		}
		
		if ( !isset( $info['category_last_record_name'] ) )
		{
			$author = \IPS\Member::load( $info['category_last_record_member'] );
			
			if ( $author->member_id )
			{
				$info['category_last_record_name'] = $author->name;
			}
			else
			{
				$info['category_last_record_name'] = NULL;
			}
		}
		else
		{
			$info['category_last_record_name'] = NULL;
		}
		
		if ( !isset( $info['category_last_record_seo_name'] ) )
		{
			if ( !\is_null( $info['category_last_record_name'] ) )
			{
				$info['category_last_record_seo_name'] = \IPS\Http\Url::seoTitle( $info['category_last_record_name'] );
			}
			else
			{
				$info['category_last_record_seo_name'] = NULL;
			}
		}
		else
		{
			$info['category_last_record_seo_name'] = NULL;
		}
		
		if ( !isset( $info['category_position'] ) )
		{
			$position = \IPS\Db::i()->select( 'MAX(category_position)', 'cms_database_categories', array( "category_database_id=?", $info['category_database_id'] ) )->first();
			
			$info['category_position'] = $position + 1;
		}
		
		/* Counts and other zero defaults to make my life easier */
		foreach( array( 'category_records', 'category_records_queued', 'category_record_comments', 'category_record_comments_queued', 'category_has_perms', 'category_allow_rating', 'category_records_future', 'category_record_reviews', 'category_record_reviews_queued' ) AS $zero )
		{
			if ( !isset( $info[ $zero ] ) )
			{
				$info[ $zero ] = 0;
			}
		}
		
		if ( !isset( $info['category_show_records'] ) )
		{
			$info['category_show_records'] = 1;
		}
		
		if ( !isset( $info['category_furl_name'] ) )
		{
			$info['category_furl_name'] = \IPS\Http\Url::seoTitle( $info['category_name'] );
		}
		
		if ( isset( $info['category_meta_keywords'] ) )
		{
			if ( \is_array( $info['category_meta_keywords'] ) )
			{
				$info['category_meta_keywords'] = implode( ',', $info['category_meta_keywords'] );
			}
		}
		else
		{
			$info['category_meta_keywords'] = NULL;
		}
		
		if ( !isset( $info['category_meta_description'] ) )
		{
			$info['category_meta_description'] = NULL;
		}

		if ( isset( $info['category_parent_id'] ) )
		{
			if ( $info['category_parent_id'] !== 0 )
			{
				try
				{
					$info['category_parent_id'] = $this->software->app->getLink( $info['category_parent_id'], 'cms_database_categories' );
				}
				catch( \OutOfRangeException $e )
				{
					/* Does the ID exist in the source? */
					$info['category_conv_parent'] = $info['category_parent_id'];

					/* Default to a category unless a later converted forum updates the parent_id */
					$info['category_parent_id'] = 0;
				}
			}
		}
		
		/* Forum Integration */
		try
		{
			$this->software->app->checkForSibling( 'forums' );
			if ( \IPS\Application::appIsEnabled( 'forums' ) === FALSE )
			{
				throw new \LogicException;
			}
			
			foreach( array( 'category_forum_override', 'category_forum_record', 'category_forum_comments', 'category_forum_delete' ) AS $forumZero )
			{
				if ( !isset( $info[ $forumZero ] ) )
				{
					$info[ $forumZero ] = 0;
				}
			}
			
			if ( isset( $info['category_forum_forum'] ) )
			{
				$info['category_forum_forum'] = $this->software->app->getSiblingLink( $info['category_forum_forum'], 'forums_forums', 'forums' );
			}
			else
			{
				throw new \InvalidArgumentException;
			}
			
			foreach( array( 'category_forum_prefix', 'category_forum_suffix' ) as $forumNull )
			{
				if ( !isset( $info[ $forumNull ] ) )
				{
					$info[ $forumNull ] = NULL;
				}
			}
		}
		catch( \OutOfRangeException $e )
		{
			$info['category_forum_override']	= 0;
			$info['category_forum_record']		= 0;
			$info['category_forum_comments']	= 0;
			$info['category_forum_delete']		= 0;
			$info['category_forum_forum']		= 0;
			$info['category_forum_prefix']		= NULL;
			$info['category_forum_suffix']		= NULL;
		}
		catch( \InvalidArgumentException $e )
		{
			$info['category_forum_override']	= 0;
			$info['category_forum_record']		= 0;
			$info['category_forum_comments']	= 0;
			$info['category_forum_delete']		= 0;
			$info['category_forum_forum']		= 0;
			$info['category_forum_prefix']		= NULL;
			$info['category_forum_suffix']		= NULL;
		}
		catch( \LogicException $e )
		{
			throw $e;
		}
		
		if ( !isset( $info['category_full_path'] ) )
		{
			$info['category_full_path'] = \IPS\Http\Url::seoTitle( $info['category_name'] );
		}
		
		if ( !isset( $info['category_last_title'] ) )
		{
			$info['category_last_title'] = NULL;
		}
		
		if ( !isset( $info['category_last_seo_title'] ) )
		{
			if ( !\is_null( $info['category_last_title'] ) )
			{
				$info['category_last_seo_title'] = \IPS\Http\Url::seoTitle( $info['category_last_title'] );
			}
			else
			{
				$info['category_last_seo_title'] = NULL;
			}
		}
		else
		{
			$info['category_last_seo_title'] = NULL;
		}
		
		if ( isset( $info['category_fields'] ) )
		{
			if ( !\is_array( $info['category_fields'] ) )
			{
				$info['category_fields'] = json_decode( $info['category_fields'], TRUE );
			}
			
			$fields = array();
			foreach( $info['category_fields'] AS $field )
			{
				try
				{
					$fields[] = $this->software->app->getLink( $field, 'cms_database_fields' );
				}
				catch( \OutOfRangeException $e )
				{
					continue;
				}
			}
			
			if ( \count( $fields ) )
			{
				$info['category_fields'] = json_encode( $fields );
			}
			else
			{
				$info['category_fields'] = NULL;
			}
		}
		else
		{
			$info['category_fields'] = NULL;
		}
		
		/* Not Used? I'm confused. */
		$info['category_options'] = 0;
		
		/* Do not know */
		$info['category_template_listing'] = 0;
		$info['category_template_display'] = 0;
		
		$id = $info['category_id'];
		unset( $info['category_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'cms_database_categories', $info );
		$this->software->app->addLink( $inserted_id, $id, 'cms_database_categories' );

		\IPS\Db::i()->update( 'cms_database_categories', array( "category_parent_id" => $inserted_id ), array( "category_conv_parent=?", $id ) );
		\IPS\Db::i()->insert( 'core_permission_index', array( 'app' => 'cms', 'perm_type' => 'categories_' . $info['category_database_id'], 'perm_type_id' => $inserted_id, 'perm_view' => '' ) );
		
		\IPS\Lang::saveCustom( 'cms', "content_cat_name_{$inserted_id}", $info['category_name'] );
		\IPS\Lang::saveCustom( 'cms', "content_cat_name_{$inserted_id}_desc", $desc );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a Record
	 *
	 * @param	array			$info			The data to insert.
	 * @param	array			$fields			Custom Field Data.
	 * @param	string|NULL		$imagepath		Path to record image
	 * @param	string|NULL		$imagedata		Image data
	 * @return	integer|boolean	The ID of the newly inserted record, or FALSE on failure.
	 */
	public function convertCmsDatabaseRecord( $info=array(), $fields=array(), $imagepath=NULL, $imagedata=NULL )
	{
		if ( !isset( $info['record_id'] ) )
		{
			$this->software->app->log( 'cms_database_record_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['record_database_id'] ) )
		{
			try
			{
				$database = $this->software->app->getLink( $info['record_database_id'], 'cms_databases' );
				unset( $info['record_database_id'] );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'cms_database_record_missing_datebase', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['record_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'cms_database_record_missing_database', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['record_id'] );
			return FALSE;
		}
		
		if ( isset( $info['member_id'] ) ) # inconsistency ftl
		{
			try
			{
				$info['member_id'] = $this->software->app->getLink( $info['member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['member_id'] = 0;
			}
		}
		else
		{
			$info['member_id'] = 0;
		}
		
		/* We can knock out a lot of this right now */
		foreach( array( 'post_key', 'rating_real', 'rating_hits', 'rating_value', 'record_locked', 'record_comments', 'record_comments_queued', 'record_comments_hidden', 'record_views', 'record_pinned', 'record_template', 'record_on_homepage', 'record_allow_comments', 'record_allow_reviews', 'record_featured', 'record_reviews', 'record_reviews_queued', 'record_rating', 'record_edit_show' ) AS $zero )
		{
			if ( !isset( $info[ $zero ] ) )
			{
				$info[ $zero ] = 0;
			}
		}
		
		/* And these too */
		foreach( array( 'record_saved', 'record_updated', 'record_future_date', 'record_expiry_date', 'record_comment_cutoff', 'record_edit_time' ) AS $date )
		{
			if ( isset( $info[ $date ] ) )
			{
				if ( $info[ $date ] instanceof \IPS\DateTime )
				{
					$info[ $date ] = $info[ $date ]->getTimestamp();
				}
			}
			else
			{
				$info[ $date ] = 0;
			}
		}
		
		if ( isset( $info['category_id'] ) ) # sigh
		{
			try
			{
				$info['category_id'] = $this->software->app->getLink( $info['category_id'], 'cms_database_categories' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'cms_database_record_missing_category', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['record_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'cms_database_record_missing_category', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['record_id'] );
			return FALSE;
		}
		
		if ( !isset( $info['record_approved'] ) )
		{
			$info['record_approved'] = 1;
		}
		
		$fields = $this->_formatFieldsForSaving( $fields );
		
		if ( !isset( $info['record_dynamic_furl'] ) )
		{
			$titleField = \IPS\Db::i()->select( 'database_field_title', 'cms_databases', array( "database_id=?", $database ) )->first();
			
			$info['record_dynamic_furl'] = \IPS\Http\Url::seoTitle( $fields['field_' . $titleField ] );
		}
		
		if ( !isset( $info['record_static_furl'] ) )
		{
			$info['record_static_furl'] = NULL;
		}
		else
		{
			$info['record_static_furl'] = \IPS\Http\Url::seoTitle( $info['record_static_furl'] );
		}
		
		if ( isset( $info['record_meta_keywords'] ) )
		{
			if ( \is_array( $info['record_meta_keywords'] ) )
			{
				$info['record_meta_keywords'] = implode( ',', $info['record_meta_keywords'] );
			}
		}
		else
		{
			$info['record_meta_keywords'] = NULL;
		}
		
		if ( !isset( $info['record_meta_description'] ) )
		{
			$info['record_meta_description'] = NULL;
		}
		
		if ( isset( $info['record_topicid'] ) )
		{
			try
			{
				$this->software->app->checkForSibling( 'forums' );
				if ( \IPS\Application::appIsEnabled( 'forums' ) === FALSE )
				{
					throw new \LogicException;
				}
				
				$info['record_topicid'] = $this->software->app->getSiblingLink( $info['record_topicid'], 'forums_topics', 'forums' );
			}
			catch( \OutOfrangeException $e )
			{
				$info['record_topicid'] = 0;
			}
			catch( \LogicException $e )
			{
				throw $e;
			}
		}
		else
		{
			$info['record_topicid'] = 0;
		}
		
		foreach( array( 'record_publish_date', 'record_last_comment', 'record_last_review' ) AS $currentDate )
		{
			if ( isset( $info[ $currentDate ] ) )
			{
				if ( $info[ $currentDate ] instanceof \IPS\DateTime )
				{
					$info[ $currentDate ] = $info[ $currentDate ]->getTimestamp();
				}

				/* Last comment and last review dates cannot be in the future */
				if( \in_array( $currentDate, array( 'record_last_comment', 'record_last_review' ) ) AND $info[ $currentDate ] > time() )
				{
					$info[ $currentDate ] = time();
				}
			}
			else
			{
				$info[ $currentDate ] = time();
			}
		}
		
		if ( isset( $info['record_last_comment_by'] ) )
		{
			try
			{
				$info['record_last_comment_by'] = $this->software->app->getLink( $info['record_last_comment_by'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['record_last_comment_by'] = 0;
			}
		}
		else
		{
			$info['record_last_comment_by'] = 0;
		}
		
		if ( !isset( $info['record_last_comment_name'] ) )
		{
			$author = \IPS\Member::load( $info['record_last_comment_by'] );
			
			if ( $author->member_id )
			{
				$info['record_last_comment_name'] = $author->name;
			}
			else
			{
				$info['record_last_comment_name'] = NULL;
			}
		}
		else
		{
			$info['record_last_comment_name'] = NULL;
		}

		if ( isset( $info['record_image'] ) AND ( !\is_null( $imagepath ) OR !\is_null( $imagedata ) ) )
		{
			$container = NULL;
			if( $info['record_saved'] )
			{
				$container = 'monthly_' . date( 'Y', $info['record_saved'] ) . '_' . date( 'm', $info['record_saved'] );
			}

			try
			{
				if ( \is_null( $imagedata ) AND !\is_null( $imagepath ) )
				{
					$imagedata = @file_get_contents( $imagepath );
					$imagepath = NULL;
				}

				$file = \IPS\File::create( 'cms_Records', $info['record_image'], $imagedata, $container, TRUE );
				$info['record_image']		= (string) $file;
				$info['record_image_thumb']	= (string) $file->thumbnail( 'cms_Records' );
			}
			catch( \Exception $e )
			{
				$info['record_image']		= NULL;
				$info['record_image_thumb']	= NULL;
			}
		}
		else
		{
			$info['record_image']			= NULL;
			$info['record_image_thumb']		= NULL;
		}
		
		if ( isset( $info['record_last_review_by'] ) )
		{
			try
			{
				$info['record_last_review_by'] = $this->software->app->getLink( $info['record_last_review_by'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['record_last_review_by'] = 0;
			}
		}
		else
		{
			$info['record_last_review_by'] = 0;
		}
		
		if ( !isset( $info['record_last_review_name'] ) )
		{
			$author = \IPS\Member::load( $info['record_last_review_by'] );
			
			if ( $author->member_id )
			{
				$info['record_last_review_name'] = $author->name;
			}
			else
			{
				$info['record_last_review_name'] = NULL;
			}
		}
		else
		{
			$info['record_last_review_name'] = NULL;
		}

		if ( !isset( $info['record_edit_reason'] ) )
		{
			$info['record_edit_reason'] = NULL;
		}
		
		if ( isset( $info['record_edit_member_id'] ) )
		{
			try
			{
				$info['record_edit_member_id'] = $this->software->app->getLink( $info['record_edit_member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['record_edit_member_id'] = 0;
			}
		}
		else
		{
			$info['record_edit_member_id'] = 0;
		}
		
		if ( !isset( $info['record_edit_member_name'] ) )
		{
			$member = \IPS\Member::load( $info['record_edit_member_id'] );
			
			if ( $member->member_id )
			{
				$info['record_edit_member_name'] = $member->name;
			}
			else
			{
				$info['record_edit_member_name'] = NULL;
			}
		}
		
		/* Merge in custom field data */
		$info = array_merge( $info, $fields );
		
		$id = $info['record_id'];
		unset( $info['record_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( "cms_custom_database_{$database}", $info );
		$this->software->app->addLink( $inserted_id, $id, "cms_custom_database_{$database}" );
		
		return $inserted_id;
	}
	
	/**
	 * Format Field Content
	 *
	 * @param	array	$fieldInfo		The Field Information to format. This SHOULD be in $foreign_id => $content format, however field_$foreign_id => $content is also accepted.
	 * @return	array					An array of data formatted for cms_custom_database_*
	 */
	protected function _formatFieldsForSaving( $fieldInfo )
	{
		$return = array();
		
		if ( \count( $fieldInfo ) )
		{
			foreach( $fieldInfo as $key => $value )
			{
				if ( preg_match( '/^field_(\d+)/i', $key, $matches ) )
				{
					$id = str_replace( 'field_', '', $matches[1] );
				}
				else if ( \is_numeric( $key ) )
				{
					$id = $key;
				}
				else
				{
					continue;
				}
				
				try
				{
					$link = $this->software->app->getLink( $id, 'cms_database_fields' );
				}
				catch( \OutOfRangeException $e )
				{
					/* Does not exist - skip */
					continue;
				}
				
				$return[ 'field_' . $link ] = $value;
			}
		}
		
		return $return;
	}
	
	/**
	 * Convert a Comment
	 *
	 * @param	array			$info	Data to insert.
	 * @return	integer|boolean	The ID of the newly inserted comment, or FALSE on failure.
	 */
	public function convertCmsDatabaseComment( $info=array() )
	{
		if ( !isset( $info['comment_id'] ) )
		{
			$this->software->app->log( 'cms_database_comment_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['comment_user'] ) )
		{
			try
			{
				$info['comment_user'] = $this->software->app->getLink( $info['comment_user'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['comment_user'] = 0;
			}
		}
		else
		{
			$info['comment_user'] = 0;
		}
		
		if ( isset( $info['comment_database_id'] ) )
		{
			try
			{
				$info['comment_database_id'] = $this->software->app->getLink( $info['comment_database_id'], 'cms_databases' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'cms_database_comment_missing_database', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'cms_database_comment_missing_database', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}
		
		if ( isset( $info['comment_record_id'] ) )
		{
			try
			{
				$info['comment_record_id'] = $this->software->app->getLink( $info['comment_record_id'], "cms_custom_database_{$info['comment_database_id']}" );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'cms_database_comment_missing_record', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'cms_database_comment_missing_record', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}
		
		if ( isset( $info['comment_date'] ) )
		{
			if ( $info['comment_date'] instanceof \IPS\DateTime )
			{
				$info['comment_date'] = $info['comment_date']->getTimestamp();
			}
		}
		else
		{
			$info['comment_date'] = time();
		}
		
		if ( !isset( $info['comment_ip_address'] ) OR filter_var( $info['comment_ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['comment_ip_address'] = '127.0.0.1';
		}
		
		if ( empty( $info['comment_post'] ) )
		{
			$this->software->app->log( 'cms_database_comment_missing_content', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}
		
		if ( !isset( $info['comment_approved'] ) )
		{
			$info['comment_approved'] = 1;
		}
		
		if ( !isset( $info['comment_author'] ) )
		{
			$author = \IPS\Member::load( $info['comment_user'] );
			
			if ( $author->member_id )
			{
				$info['comment_author'] = $author->name;
			}
			else
			{
				$info['comment_author'] = NULL;
			}
		}
		
		if ( isset( $info['comment_edit_date'] ) )
		{
			if ( $info['comment_edit_date'] instanceof \IPS\DateTime )
			{
				$info['comment_edit_date'] = $info['comment_edit_date']->getTimestamp();
			}
		}
		else
		{
			$info['comment_edit_date'] = 0;
		}
		
		if ( !isset( $info['comment_edit_reason'] ) )
		{
			$info['comment_edit_reason'] = NULL;
		}
		
		if ( isset( $info['comment_edit_member_id'] ) )
		{
			try
			{
				$info['comment_edit_member_id'] = $this->software->app->getLink( $info['comment_edit_member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['comment_edit_member_id'] = 0;
			}
		}
		else
		{
			$info['comment_edit_member_id'] = 0;
		}
		
		if ( !isset( $info['comment_edit_member_name'] ) )
		{
			$member = \IPS\Member::load( $info['comment_edit_member_id'] );
			
			if ( $member->member_id )
			{
				$info['comment_edit_member_name'] = $member->name;
			}
			else
			{
				$info['comment_edit_member_name'] = NULL;
			}
		}
		else
		{
			$info['comment_edit_member_name'] = NULL;
		}
		
		if ( !isset( $info['comment_edit_show'] ) )
		{
			$info['comment_edit_show'] = 0;
		}
		
		$id = $info['comment_id'];
		unset( $info['comment_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'cms_database_comments', $info );
		$this->software->app->addLink( $inserted_id, $id, 'cms_database_comments' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a review
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted review, or FALSE on failure.
	 */
	public function convertCmsDatabaseReview( $info=array() )
	{
		if ( !isset( $info['review_id'] ) )
		{
			$this->software->app->log( 'cms_database_review_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['review_database_id'] ) )
		{
			try
			{
				$info['review_database_id'] = $this->software->app->getLink( $info['review_database_id'], 'cms_databases' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'cms_database_review_missing_database', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'cms_database_review_missing_database', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		if ( isset( $info['review_item'] ) )
		{
			try
			{
				$info['review_item'] = $this->software->app->getLink( $info['review_item'], "cms_custom_database_{$info['review_database_id']}" );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'cms_database_review_missing_item', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'cms_database_review_missing_item', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		/* Unlike comments, guests cannot review */
		if ( isset( $info['review_author'] ) )
		{
			try
			{
				$info['review_author'] = $this->software->app->getLink( $info['review_author'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'cms_database_review_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'cms_database_review_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		if ( empty( $info['review_content'] ) )
		{
			$this->software->app->log( 'cms_database_review_empty', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		/* This seems silly, but we really do need a rating  */
		if ( !isset( $info['review_rating'] ) OR $info['review_rating'] < 1 )
		{
			$this->software->app->log( 'cms_database_review_invalid_rating', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		if ( !isset( $info['review_edit_show'] ) )
		{
			$info['review_edit_show'] = 0;
		}
		
		if ( isset( $info['review_edit_time'] ) )
		{
			if ( $info['review_edit_time'] instanceof \IPS\DateTime )
			{
				$info['review_edit_time'] = $info['review_edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['review_edit_time'] = time();
		}
		
		if ( !isset( $info['review_edit_name'] ) )
		{
			$info['review_edit_name'] = NULL;
		}
		
		if ( isset( $info['review_date'] ) )
		{
			if ( $info['review_date'] instanceof \IPS\DateTime )
			{
				$info['review_date'] = $info['review_date']->getTimestamp();
			}
		}
		else
		{
			$info['review_date'] = time();
		}
		
		if ( !isset( $info['review_ip_address'] ) OR filter_var( $info['review_ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['review_ip_address'] = '127.0.0.1';
		}
		
		if ( !isset( $info['review_author_name'] ) )
		{
			$author = \IPS\Member::load( $info['review_mid'] );
			
			if ( $author->member_id )
			{
				$info['review_author_name'] = $author->name;
			}
			else
			{
				$info['review_author_name'] = "Guest";
			}
		}
		
		if ( isset( $info['review_votes_data'] ) )
		{
			if ( !\is_array( $info['review_votes_data'] ) )
			{
				$info['review_votes_data'] = json_decode( $info['review_votes_data'], TRUE );
			}
			
			$newVoters = array();
			if ( !\is_null( $info['review_votes_data'] ) AND \count( $info['review_votes_data'] ) )
			{
				foreach( $info['review_votes_data'] AS $member => $vote )
				{
					try
					{
						$memberId = $this->software->app->getLink( $member, 'core_members', TRUE );
					}
					catch( \OutOfRangeException $e )
					{
						continue;
					}
					
					$newVoters[ $memberId ] = $vote;
				}
			}
			
			if ( \count( $newVoters ) )
			{
				$info['review_votes_data'] = json_encode( $newVoters );
			}
			else
			{
				$info['review_votes_data'] = NULL;
			}
		}
		else
		{
			$info['review_votes_data'] = NULL;
		}
		
		if ( !isset( $info['review_votes_total'] ) )
		{
			if ( \is_null( $info['review_votes_data'] ) )
			{
				$info['review_votes_total'] = 0;
			}
			else
			{
				$info['review_votes_total'] = \count( json_decode( $info['review_votes_data'], TRUE ) );
			}
		}
		
		if ( !isset( $info['review_votes_helpful'] ) )
		{
			if ( \is_null( $info['review_votes_data'] ) )
			{
				$info['review_votes_helpful'] = 0;
			}
			else
			{
				$helpful = 0;
				foreach( json_decode( $info['review_votes_data'], TRUE ) AS $member => $vote )
				{
					if ( $vote == 1 )
					{
						$helpful += 1;
					}
				}
				
				$info['review_votes_helpful'] = $helpful;
			}
		}
		
		if ( !isset( $info['review_approved'] ) )
		{
			$info['review_approved'] = 1;
		}
		
		$id = $info['review_id'];
		unset( $info['review_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'cms_database_reviews', $info );
		$this->software->app->addLink( $inserted_id, $id, 'cms_database_reviews' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a Media Folder
	 *
	 * @param	array			$info	Data to insert
	 * @return	integer|boolean	The ID of the newly inserted folder, or FALSE on failure.
	 */
	public function convertCmsMediaFolder( $info=array() )
	{
		if ( !isset( $info['media_folder_id'] ) )
		{
			$this->software->app->log( 'cms_media_folder_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( !isset( $info['media_folder_name'] ) )
		{
			$info['media_folder_name'] = "untitledfolder{$info['media_folder_id']}";
		}
		
		if ( isset( $info['media_folder_parent'] ) )
		{
			try
			{
				$info['media_folder_parent'] = $this->software->app->getLink( $info['media_folder_parent'], 'cms_media_folders' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['media_conv_parent'] = $info['media_folder_parent'];
			}
		}
		else
		{
			$info['media_folder_parent'] = 0;
		}
		
		if ( !isset( $info['media_folder_path'] ) )
		{
			$info['member_folder_path'] = $info['media_folder_name'];
		}
		
		$id = $info['media_folder_id'];
		unset( $info['media_folder_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'cms_media_folders', $info );
		$this->software->app->addLink( $inserted_id, $id, 'cms_media_folders' );
		
		\IPS\Db::i()->update( 'cms_media_folders', array( "media_folder_parent" => $inserted_id ), array( "media_conv_parent=?", $id ) );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a media file
	 *
	 * @param	array			$info		Data to insert
	 * @param	string|NULL		$filepath	The path to the file, or NULL.
	 * @param	string|NULL		$filedata	The data for the file, or NULL.
	 * @return	integer|boolean	The ID of the newly inserted media file, or FALSE on failure.
	 */
	public function convertCmsMedia( $info=array(), $filepath=NULL, $filedata=NULL )
	{
		if ( !isset( $info['media_id'] ) )
		{
			$this->software->app->log( 'cms_media_file_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( \is_null( $filepath ) AND \is_null( $filedata ) )
		{
			$this->software->app->log( 'cms_media_file_no_file', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['media_id'] );
			return FALSE;
		}
		
		if ( isset( $info['media_parent'] ) )
		{
			try
			{
				$info['media_parent'] = $this->software->app->getLink( $info['media_parent'], 'cms_media_folders' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['media_parent'] = 0;
			}
		}
		else
		{
			$info['media_parent'] = 0;
		}
		
		if ( !isset( $info['media_filename'] ) )
		{
			if ( !\is_null( $filepath ) )
			{
				$name = explode( '/', $filepath );
				$name = array_pop( $name );
				$info['media_filename'] = $name;
			}
			else
			{
				$this->software->app->log( 'cms_media_file_missing_name', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['media_id'] );
				return FALSE;
			}
		}
		
		if ( isset( $info['media_added'] ) )
		{
			if ( $info['media_added'] instanceof \IPS\DateTime )
			{
				$info['media_added'] = $info['media_added']->getTimestamp();
			}
		}
		else
		{
			$info['media_added'] = time();
		}
		
		if ( !isset( $info['media_full_path'] ) )
		{
			try
			{
				$folderPath = \IPS\Db::i()->select( 'media_folder_path', 'cms_media_folders', array( "media_folder_id=?", $info['media_parent'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$folderPath = '';
			}
			
			$info['media_full_path'] = ltrim( $folderPath . '/' . $info['media_filename'] );
		}
		
		try
		{
			if ( \is_null( $filedata ) AND !\is_null( $filepath ) )
			{
				$filedata = @file_get_contents( $filepath );
				$filepath = NULL;
			}
			
			$file = \IPS\File::create( 'cms_Media', $info['media_parent'] . '_' . $info['media_filename'], $filedata, 'pages_media' );
			$info['media_file_object'] = (string) $file;
			$info['media_filename_stored'] = $info['media_parent'] . '_' . $info['media_filename'];
			
			if ( !isset( $info['media_is_image'] ) )
			{
				if ( $file->isImage() )
				{
					$info['media_is_image'] = 1;
				}
				else
				{
					$info['media_is_image'] = 0;
				}
			}
		}
		catch( \Exception $e )
		{
			$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_WARNING, $info['media_id'] );
			return FALSE;
		}
		catch( \ErrorException $e )
		{
			$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_WARNING, $info['media_id'] );
			return FALSE;
		}
		
		$id = $info['media_id'];
		unset( $info['media_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'cms_media', $info );
		$this->software->app->addLink( $inserted_id, $id, 'cms_media' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert an attachment
	 *
	 * @param	array			$info		Data to insert
	 * @param	array			$map		Attachment Map Data
	 * @param	string|NULL		$filepath	Path to the file, or NULL.
	 * @param	string|NULL		$filedata	Binary data for the file, or NULL.
	 * @param	string|NULL		$thumbnailpath	Path to the thumbnail, or NULL.
	 * @return	integer|boolean	The ID of the newly inserted attachment, or FALSE on failure.
	 */
	public function convertAttachment( $info=array(), $map=array(), $filepath=NULL, $filedata=NULL, $thumbnailpath = NULL )
	{
		$database = $this->software->app->getLink( str_replace( '-review', '', $map['id3'] ), 'cms_databases' );
		
		$map['id1_type']		= "cms_custom_database_{$database}";
		$map['id1_from_parent']	= FALSE;
		$map['id2_from_parent']	= FALSE;
		$map['id3_skip_link']	= TRUE;
		$map['location_key']	= "cms_Records" . $database;

		if( empty( $map['id2_type'] ) )
		{
			if ( \substr( $map['id3'], -1, 7 ) == '-review' )
			{
				$map['id2_type'] = 'cms_database_reviews';
			}
			else
			{
				$map['id2_type'] = 'cms_database_comments';
			}
		}
		
		$map['id3'] = $database;
		
		return parent::convertAttachment( $info, $map, $filepath, $filedata, $thumbnailpath );
	}


	/**
	 * Convert a Tag - CMS, we need to verify the database in a different way that doesn't require it to be on a page
	 *
	 * @param	array		$info	Data to insert
	 * @return	boolean|integer		The ID of the newly inserted tag, or FALSE on failure.
	 * @note Like Follows, this should be done when the actual content it's attached too is being converted.
	 * @note core_tags_cache and core_tags_perms need to be populated by the converter.
	 */
	public function convertTag( $info=array() )
	{
		$classname = 'IPS\cms\\' . ucfirst( $info['tag_meta_area'] );

		if( !class_exists( $classname ) )
		{
			$this->software->app->log( 'tag_area_invalid_cms', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['tag_id'] ?? NULL );
			return FALSE;
		}

		/* Set this as a validated content class */
		$this->_ipsTagContentClass = $classname;

		return parent::convertTag( $info );
	}
}