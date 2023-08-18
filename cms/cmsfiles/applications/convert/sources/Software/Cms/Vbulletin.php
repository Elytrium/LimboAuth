<?php

/**
 * @brief		Converter vBulletin 4.x Pages Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Cms;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * vBulletin Pages Converter
 */
class _Vbulletin extends \IPS\convert\Software
{
	/**
	 * @brief	The schematic for vB3 and vB4 is similar enough that we can make specific concessions in a single converter for either version.
	 */
	protected static $isLegacy				= NULL;

	/**
	 * @brief	Cached article content type
	 */
	protected static $_articleContentType	= NULL;

	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "vBulletin CMS (4.x only)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "vbulletin";
	}

	/**
	 * Constructor
	 *
	 * @param	\IPS\convert\App	$app	The application to reference for database and other information.
	 * @param	bool				$needDB	Establish a DB connection
	 * @return	void
	 * @throws	\InvalidArgumentException
	 */
	public function __construct( \IPS\convert\App $app, $needDB=TRUE )
	{
		$return = parent::__construct( $app, $needDB );

		/* Is this vB3 or vB4? */
		try
		{
			if ( static::$isLegacy === NULL AND $needDB )
			{
				$version = $this->db->select('value', 'setting', array("varname=?", 'templateversion'))->first();

				if (mb_substr($version, 0, 1) == '3') {
					static::$isLegacy = TRUE;
				} else {
					static::$isLegacy = FALSE;
				}
			}

			/* If this is vB4, what is the content type ID for the cms? */
			if ( static::$_articleContentType === NULL AND ( static::$isLegacy === FALSE OR \is_null( static::$isLegacy ) ) AND $needDB )
			{
				try
				{
					static::$_articleContentType = $this->db->select( 'contenttypeid', 'contenttype', array( "class=?", 'Article' ) )->first();
				}
				catch( \UnderflowException $e )
				{
					static::$_articleContentType = 24; # default
				}
			}

		}
		catch( \Exception $e ) {}


		return $return;
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		$return = array(
			'convertCmsBlocks'				=> array(
				'table'								=> 'cms_widget',
				'where'								=> NULL,
			),
			'convertCmsPages'				=> array(
				'table'								=> 'page',
				'where'								=> NULL,
			),
			'convertCmsDatabases'			=> array(
				'table'								=> 'database',
				'where'								=> NULL,
			),
			'convertCmsDatabaseCategories'	=> array(
				'table'								=> 'cms_category',
				'where'								=> NULL,
			),
			'convertCmsDatabaseRecords'		=> array(
				'table'								=> 'cms_article',
				'where'								=> NULL,
			),
			'convertAttachments'			=> array(
 				'table'								=> 'attachment',
 				'where'								=> array( "contenttypeid=?", static::$_articleContentType )
		 			)
		);
		
		return $return;
	}

	/**
	 * Count Source Rows for a specific step
	 *
	 * @param	string		$table		The table containing the rows to count.
	 * @param	array|NULL	$where		WHERE clause to only count specific rows, or NULL to count all.
	 * @param	bool		$recache	Skip cache and pull directly (updating cache)
	 * @return	integer
	 * @throws	\IPS\convert\Exception
	 */
	public function countRows( $table, $where=NULL, $recache=FALSE )
	{
		switch( $table )
		{
			case 'cms_widget':
				try
				{
					$blocksWeCanConvert = array();
					foreach( $this->db->select( 'widgettypeid', 'cms_widgettype', array( \IPS\Db::i()->in( 'class', array( 'Rss', 'Static' ) ) ) ) AS $typeid )
					{
						$blocksWeCanConvert[] = $typeid;
					}
					return $this->db->select( 'COUNT(*)', 'cms_widget', array( $this->db->in( 'widgettypeid', $blocksWeCanConvert ) ) )->first();
				}
				catch( \Exception $e )
				{
					throw new \IPS\convert\Exception( sprintf( \IPS\Member::loggedIn()->language()->get( 'could_not_count_rows' ), $table ) );
				}
				break;
			
			case 'page':
			case 'database':
				return 1;
				break;
			default:
				return parent::countRows( $table, $where, $recache );
				break;
		}
	}

	/**
	 * Requires Parent
	 *
	 * @return	boolean
	 */
	public static function requiresParent()
	{
		return TRUE;
	}
	
	/**
	 * Possible Parent Conversions
	 *
	 * @return	array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'vbulletin' ) );
	}

	/**
	 * Fix Post Data
	 *
	 * @param	string	$post	Post
	 * @return	string	Fixed Posts
	 */
	public static function fixPostData( $post )
	{
		return \IPS\convert\Software\Core\Vbulletin::fixPostData( $post );
	}

	/**
	 * Get Setting Value - useful for global settings that need to be translated to group or member settings
	 *
	 * @param	string	$key	The setting key
	 * @return	mixed
	 */
	protected function _setting( $key )
	{
		if ( isset( $this->settingsCache[$key] ) )
		{
			return $this->settingsCache[$key];
		}
		
		try
		{
			$setting = $this->db->select( 'value, defaultvalue', 'setting', array( "varname=?", $key ) )->first();
			
			if ( $setting['value'] )
			{
				$this->settingsCache[$key] = $setting['value'];
			}
			else
			{
				$this->settingsCache[$key] = $setting['defaultvalue'];
			}
		}
		catch( \UnderflowException $e )
		{
			/* If we failed to find it, we probably will fail again on later attempts */
			$this->settingsCache[$key] = NULL;
		}
		
		return $this->settingsCache[$key];
	}

	/**
	 * Convert CMS blocks
	 *
	 * @return	void
	 */
	public function convertCmsBlocks()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'widgetid' );
		
		/* We CAN bring over some blocks, like static widgets */
		$blocksWeCanConvert = array();
		$rssTypeId			= NULL;
		foreach( $this->db->select( 'widgettypeid, class', 'cms_widgettype', array( \IPS\Db::i()->in( 'class', array( 'Rss', 'Static' ) ) ) ) AS $type )
		{
			if ( $type['class'] == 'Rss' )
			{
				$rssTypeId = $type['widgettypeid'];
			}
			$blocksWeCanConvert[] = $type['widgettypeid'];
		}
		
		foreach( $this->fetch( 'cms_widget', 'widgetid', array( \IPS\Db::i()->in( 'widgettypeid', $blocksWeCanConvert ) ) ) AS $block )
		{
			$config = array();

			foreach( $this->db->select( 'name, value', 'cms_widgetconfig', array( "widgetid=?", $block['widgetid'] ) ) as $_c )
			{
				$config[ $_c['name'] ] = $_c['value'];
			}
			
			$info = array(
				'block_id'				=> $block['widgetid'],
				'block_name'			=> $block['title'],
				'block_description'		=> $block['description'],
				'block_plugin'			=> ( $block['widgettypeid'] == $rssTypeId ) ? 'Rss' : NULL,
				'block_plugin_config'	=> ( $block['widgettypeid'] == $rssTypeId ) ? array(
					'block_rss_import_title'	=> $block['title'],
					'block_rss_import_url'		=> $config['url'],
					'block_rss_import_number'	=> $config['max_items'],
					'block_rss_import_cache'	=> 30,
					'block_type'				=> 'plugin',
					'block_editor'				=> 'html',
					'block_plugin'				=> 'Rss',
					'block_plugin_app'			=> 'cms',
					'template_params'			=> '',
					'type'						=> 'plugin',
					'plugin_app'				=> 'cms',
				) : NULL,
				'block_content'			=> ( $block['widgettypeid'] != $rssTypeId AND !empty( $config['statichtml'] ) ) ? $config['statichtml'] : NULL,
			);
			
			$libraryClass->convertCmsBlock( $info );
			
			$libraryClass->setLastKeyValue( $block['widgetid'] );
		}
	}
	
	/**
	 * Create a CMS page
	 *
	 * @return	void
	 */
	public function convertCmsPages()
	{
		$this->getLibrary()->convertCmsPage( array(
			'page_id'		=> 1,
			'page_name'		=> 'vBulletin Articles',
		) );
		
		throw new \IPS\convert\Software\Exception;
	}
	
	/**
	 * Create a database
	 *
	 * @return	void
	 */
	public function convertCmsDatabases()
	{
		$convertedForums = FALSE;
		try
		{
			$this->app->checkForSibling( 'forums' );
			
			$convertedForums = TRUE;
		}
		catch( \OutOfRangeException $e ) {}
		$this->getLibrary()->convertCmsDatabase( array(
			'database_id'				=> 1,
			'database_name'				=> 'vBulletin Articles',
			'database_sln'				=> 'article',
			'database_pln'				=> 'articles',
			'database_scn'				=> 'Article',
			'database_pcn'				=> 'Articles',
			'database_ia'				=> 'an article',
			'database_record_count'		=> $this->db->select( 'COUNT(*)', 'cms_article' )->first(),
			'database_tags_enabled'		=> 1,
			'database_forum_record'		=> ( $convertedForums ) ? 1 : 0,
			'database_forum_comments'	=> ( $convertedForums ) ? 1 : 0,
			'database_forum_delete'		=> ( $convertedForums ) ? 1 : 0,
			'database_forum_prefix'		=> ( $convertedForums ) ? 'Article: ' : '',
			'database_forum_forum'		=> ( $convertedForums ) ? $this->_setting( 'vbcmsforumid' ) : 0,
			'database_page_id'			=> 1,
		), array(
			array(
				'field_id'				=> 1,
				'field_type'			=> 'Text',
				'field_name'			=> 'Title',
				'field_key'				=> 'article_title',
				'field_required'		=> 1,
				'field_user_editable'	=> 1,
				'field_position'		=> 1,
				'field_display_listing'	=> 1,
				'field_display_display'	=> 1,
				'field_is_title'		=> TRUE,
			),
			array(
				'field_id'				=> 2,
				'field_type'			=> 'Editor',
				'field_name'			=> 'Content',
				'field_key'				=> 'article_content',
				'field_required'		=> 1,
				'field_user_editable'	=> 1,
				'field_position'		=> 2,
				'field_display_listing'	=> 0,
				'field_display_display'	=> 1,
				'field_is_content'		=> TRUE
			)
		) );
		
		throw new \IPS\convert\Software\Exception;
	}

	/**
	 * Convert CMS database categories
	 *
	 * @return	void
	 */
	public function convertCmsDatabaseCategories()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'categoryid' );
		
		foreach( $this->fetch( 'cms_category', 'categoryid' ) AS $row )
		{
			$libraryClass->convertCmsDatabaseCategory( array(
				'category_id'			=> $row['categoryid'],
				'category_database_id'	=> 1,
				'category_name'			=> $row['category'],
				'category_desc'			=> $row['description'],
				'category_position'		=> $row['catleft'],
				'category_fields'		=> array( 'article_title', 'article_content' )
			) );
			
			$libraryClass->setLastKeyValue( $row['categoryid'] );
		}
	}
	
	/**
	 * Convert CMS database records
	 *
	 * @return	void
	 */
	public function convertCmsDatabaseRecords()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'contentid' );

		$cmsCategories = iterator_to_array( $this->db->select( 'category, categoryid', 'cms_category' )->setKeyField( 'categoryid' )->setValueField( 'category' ) );

		foreach( $this->fetch( 'cms_article', 'contentid' ) AS $row )
		{
			try
			{
				$node		= $this->db->select( '*', 'cms_node', array( "contenttypeid=? AND contentid=?", static::$_articleContentType, $row['contentid'] ) )->first();
				$nodeinfo	= $this->db->select( '*', 'cms_nodeinfo', array( "nodeid=?", $node['nodeid'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$libraryClass->setLastKeyValue( $row['contentid'] );
				continue;
			}
			

			$categories	= iterator_to_array( $this->db->select( 'categoryid', 'cms_nodecategory', array( "nodeid=?", $node['nodeid'] ) ) );

			if( !\count( $categories ) )
			{
				/* Create one */
				try
				{
					$this->app->getLink( '__orphan__', 'cms_database_categories' );
					$categories = array( '__orphan__' );
				}
				catch ( \OutOfRangeException $e )
				{
					$libraryClass->convertCmsDatabaseCategory( array(
						'category_id' => '__orphan__',
						'category_database_id' => 1,
						'category_name' => "vBulletin Articles",
						'category_fields' => array( 'article_title', 'article_content' )
					) );

					$categories = array( '__orphan__' );
				}
			}
			
			$keywords = array();
			foreach( explode( ',', $nodeinfo['keywords'] ) AS $word )
			{
				$keywords[] = trim( $word );
			}

			// First category
			$category = array_shift( $categories );
			
			$id = $libraryClass->convertCmsDatabaseRecord( array(
				'record_id'					=> $row['contentid'],
				'record_database_id'		=> 1,
				'member_id'					=> $node['userid'],
				'rating_real'				=> $nodeinfo['ratingtotal'],
				'rating_hits'				=> $nodeinfo['ratingnum'],
				'rating_value'				=> $nodeinfo['rating'],
				'record_locked'				=> ( $node['comments_enabled'] ) ? 0 : 1,
				'record_views'				=> $nodeinfo['viewcount'],
				'record_allow_comments'		=> $node['comments_enabled'],
				'record_saved'				=> $node['publishdate'],
				'record_updated'			=> $node['lastupdated'],
				'category_id'				=> $category,
				'record_approved'			=> ( $node['hidden'] ) ? -1 : 1,
				'record_static_furl'		=> $node['url'],
				'record_meta_keywords'		=> $keywords,
				'record_meta_description'	=> $nodeinfo['description'],
				'record_topicid'			=> $nodeinfo['associatedthreadid'],
				'record_publish_date'		=> $node['publishdate'],
			), array(
				1 => $nodeinfo['title'],
				2 => $row['pagetext']
			) );

			/* Need to know database for tag conversion */
			try
			{
				$database = $this->app->getLink( 1, 'cms_databases' );
			}
			catch( \OutOfRangeException $e )
			{
				/* Cannot find it, we can't convert tags */
				$libraryClass->setLastKeyValue( $row['contentid'] );
			}

			/* Convert extra unassigned categories as tags */
			$convertedTags = array();
			if( \count( $categories ) )
			{
				foreach( $categories as $key )
				{
					if( isset( $convertedTags[ $cmsCategories[ $key ] ] ) )
					{
						continue;
					}

					$libraryClass->convertTag( array(
						'tag_meta_app'			=> 'cms',
						'tag_meta_area'			=> "records{$database}",
						'tag_meta_parent_id'	=> $category,
						'tag_meta_id'			=> $row['contentid'],
						'tag_text'				=> $cmsCategories[ $key ],
						'tag_member_id'			=> $node['userid'],
						'tag_added'             => $node['publishdate'],
						'tag_prefix'			=> 0,
						'tag_meta_link'			=> 'cms_custom_database_' . $database,
						'tag_meta_parent_link'	=> 'cms_database_categories',
					) );

					if( $id )
					{
						$convertedTags[ $cmsCategories[ $key ] ] = $cmsCategories[ $key ];
					}
				}
			}

			/* Convert normal article tags */
			$tags = $this->db->select( '*', 'tagcontent', array( "contenttypeid=? AND contentid=?", static::$_articleContentType, $row['contentid'] ) )
				->join( 'tag', 'tagcontent.tagid=tag.tagid');

			foreach( $tags AS $tag )
			{
				if( isset( $convertedTags[ $tag['tagtext'] ] ) OR ( $tag['canonicaltagid'] > 0 AND $tag['canonicaltagid'] != $tag['tagid'] ) )
				{
					continue;
				}

				$id = $libraryClass->convertTag( array(
					'tag_meta_app'			=> 'cms',
					'tag_meta_area'			=> "records{$database}",
					'tag_meta_parent_id'	=> $category,
					'tag_meta_id'			=> $tag['contentid'],
					'tag_text'				=> $tag['tagtext'],
					'tag_member_id'			=> $tag['userid'],
					'tag_added'             => $node['publishdate'],
					'tag_prefix'			=> 0,
					'tag_meta_link'			=> 'cms_custom_database_' . $database,
					'tag_meta_parent_link'	=> 'cms_database_categories',
				) );

				if( $id )
				{
					$convertedTags[ $tag['tagtext'] ] = $tag['tagtext'];
				}
			}
			
			$libraryClass->setLastKeyValue( $row['contentid'] );
		}
	}

	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		foreach( \IPS\Db::i()->select( 'ipb_id', 'convert_link', array( 'type=? AND app=?', 'cms_databases', $this->app->app_id ) ) as $database )
		{
			\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\cms\Categories' . $database, 'count' => 0 ), 5, array( 'class' ) );
			\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\cms\Records'. $database ), 3, array( 'class' ) );
			\IPS\Task::queue( 'convert', 'RebuildTagCache', array( 'app' => $this->app->app_id, 'link' => 'cms_custom_database_' . $database, 'class' => 'IPS\cms\Records' . $database ), 3, array( 'app', 'link', 'class' ) );

			try
			{
				\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'link' => 'cms_custom_database_' . $database, 'class' => 'IPS\cms\Records' . $database ), 2, array( 'app', 'link', 'class' ) );
			}
			catch ( \OutOfRangeException $e ) {}
		}

		return array( "f_recount_cms_categories", "f_rebuild_cms_tags" );
	}

	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'attachmentid' );

		$where			= NULL;
		$column			= NULL;

		if ( static::$isLegacy === FALSE OR \is_null( static::$isLegacy ) )
		{
			$where			= array( "contenttypeid=?", static::$_articleContentType );
			$column			= 'contentid';
			$table			= 'attachment';
		}

		foreach( $this->fetch( $table, 'attachmentid', $where ) as $attachment )
		{
			try
 			{
				$vbRecordId = $this->db->select( 'contentid', 'cms_node', array( "nodeid=? AND contenttypeid=?", $attachment[ $column ], static::$_articleContentType ) )->first();
			}
			catch( \UnderflowException $e )
			{
				/* Log this so that it's easier to diagnose */
				$this->app->log( 'attachment_vbcms_missing_parent', __METHOD__, \IPS\convert\App::LOG_WARNING, $attachment['attachmentid'] );

				/* If the record is missing, there isn't much we can do. */
				$libraryClass->setLastKeyValue( $attachment['attachmentid'] );
				continue;
			}

			if ( static::$isLegacy === FALSE OR \is_null( static::$isLegacy ) )
			{
				$filedata = $this->db->select( '*', 'filedata', array( "filedataid=?", $attachment['filedataid'] ) )->first();
			}
			else
			{
				$filedata				= $attachment;
				$filedata['filedataid']	= $attachment['attachmentid'];
			}

			$info = array(
				'attach_id'			=> $attachment['attachmentid'],
				'attach_file'		=> $attachment['filename'],
				'attach_date'		=> $attachment['dateline'],
				'attach_member_id'	=> $attachment['userid'],
				'attach_hits'		=> $attachment['counter'],
				'attach_ext'		=> $filedata['extension'],
				'attach_filesize'	=> $filedata['filesize'],
			);

			if ( $this->app->_session['more_info']['convertAttachments']['file_location'] == 'database' )
			{
				/* Simples! */
				$data = $filedata['filedata'];
				$path = NULL;
			}
			else
			{
				$data = NULL;
				$path = implode( '/', preg_split( '//', $filedata['userid'], -1, PREG_SPLIT_NO_EMPTY ) );
				$path = rtrim( $this->app->_session['more_info']['convertAttachments']['file_location'], '/' ) . '/' . $path . '/' . $attachment['filedataid'] . '.attach';
			}

			/* Do some re-jiggery on the post itself to make sure attachment displays */
			/* The database is hardcoded to 1 while the conversion, so we have to use 1 here */
			$dbId = $this->app->getLink( 1, 'cms_databases' );
			$dbName = "cms_custom_database_" . $dbId;

			/* Get the database object */
			$ipsDb = \IPS\cms\Databases::load( $dbId );

			$map = array(
				'id1'		=> $vbRecordId,
				'id2'		=> 2,
				'id2_type'	=> 'cms_database_fields',
				'id3'		=> 1
			);

			$attach_id = $libraryClass->convertAttachment( $info, $map, $path, $data );

			try
			{
				$recordId = $this->app->getLink( $vbRecordId, 'cms_custom_database_' . $dbId );

				$post = \IPS\Db::i()->select( 'field_' . $ipsDb->field_content, $dbName, array( "primary_id_field=?", $recordId ) )->first();

				if ( preg_match( "/\[ATTACH([^\]]+?)?\]" . $attachment['attachmentid'] . "\[\/ATTACH\]/i", $post ) )
				{
					$post = preg_replace( "/\[ATTACH([^\]]+?)?\]" . $attachment['attachmentid'] . "\[\/ATTACH\]/i", '[attachment=' . $attach_id . ':name]', $post );
					\IPS\Db::i()->update( $dbName, array( 'field_' . $ipsDb->field_content => $post ), array( "primary_id_field=?", $recordId ) );
				}
			}
			catch( \OutOfRangeException $e ) { }

			$libraryClass->setLastKeyValue( $attachment['attachmentid'] );
		}
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array( 'convertAttachments' );
	}

	/**
	 * Get More Information
	 *
	 * @param	string	$method	Conversion method
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();
		switch( $method )
		{
			case 'convertAttachments':
				$return['convertAttachments'] = array(
					'file_location' => array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'			=> 'database',
						'field_required'		=> TRUE,
						'field_extra'			=> array(
							'options'				=> array(
								'database'				=> \IPS\Member::loggedIn()->language()->addToStack( 'conv_store_database' ),
								'file_system'			=> \IPS\Member::loggedIn()->language()->addToStack( 'conv_store_file_system' ),
							),
							'userSuppliedInput'	=> 'file_system',
						),
						'field_hint'			=> NULL,
						'field_validation'	=> function( $value ) { if ( $value != 'database' AND !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					)
				);
				break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}
}