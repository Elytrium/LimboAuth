<?php

/**
 * @brief		Converter Joomla Class
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
 * Joomla Pages Converter
 */
class _Joomla extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "Joomla";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "joomla";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertCmsDatabases'			=> array(
				'table'								=> 'cms_database',
				'where'								=> NULL
			),
			'convertCmsDatabaseCategories'	=> array(
				'table'								=> 'categories',
				'where'								=> NULL
			),
			'convertCmsDatabaseRecords'		=> array(
				'table'								=> 'content',
				'where'								=> NULL
			),
		);
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
			case 'cms_database':
				return 1;
				break;
			
			default:
				return parent::countRows( $table, $where, $recache );
				break;
		}
	}

	/**
	 * Can we convert passwords from this software.
	 *
	 * @return 	boolean
	 */
	public static function loginEnabled()
	{
		return TRUE;
	}

	/**
	 * Requires Parent?
	 *
	 * @return	bool
	 */
	public static function requiresParent()
	{
		return TRUE;
	}

	/**
	 * Available Parents
	 *
	 * @return	array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'joomla' ) );
	}

	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		try
		{
			$database = $this->app->getLink( 1, 'cms_databases' );
			\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\cms\Categories' . $database, 'count' => 0 ), 5, array( 'class' ) );
			\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'link' => 'cms_custom_database_' . $database, 'class' => 'IPS\cms\Records' . $database ), 2, array( 'app', 'link', 'class' ) );

			return array( "f_recount_cms_categories" );
		}
		catch( \OutOfRangeException $e )
		{
			return array();
		}
	}

	/**
	 * Create database to store data in
	 *
	 * @return 	void
	 */
	public function convertCmsDatabases()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass->convertCmsDatabase( array(
			'database_id'			=> 1,
			'database_name'			=> "Joomla Posts",
			'database_sln'			=> 'article',
			'database_pln'			=> 'articles',
			'database_scn'			=> 'Article',
			'database_pcn'			=> 'Articles',
			'database_ia'			=> 'an article',
			'database_tags_enabled'	=> 1,
		), array(
			array(
				'field_id'				=> 1,
				'field_name'			=> 'Title',
				'field_type'			=> 'Text',
				'field_key'				=> 'post_title',
				'field_required'		=> 1,
				'field_position'		=> 1,
				'field_display_listing'	=> 1,
				'field_is_title'		=> 1,
			),
			array(
				'field_id'				=> 2,
				'field_name'			=> 'Content',
				'field_type'			=> 'Editor',
				'field_key'				=> 'post_content',
				'field_required'		=> 1,
				'field_position'		=> 3,
				'field_is_content'		=> 1,
			)
		) );
		
		/* Throw an exception here to tell the library that we're done with this step */
		throw new \IPS\convert\Software\Exception;
	}

	/**
	 * Convert categories
	 *
	 * @return 	void
	 */
	public function convertCmsDatabaseCategories()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id' );
		
		foreach( $this->fetch( 'categories', 'id' ) as $row )
		{
			$info = array(
				'category_id'			=> $row['id'],
				'category_database_id'	=> 1,
				'category_name'			=> $row['title'],
				'category_furl_name'	=> $row['alias'],
				'category_desc'			=> $row['description'],
				'category_fields'		=> array( 'post_title', 'post_content' ),
			);
			
			$libraryClass->convertCmsDatabaseCategory( $info );
			$libraryClass->setLastKeyValue( $row['id'] );
		}
	}

	/**
	 * Convert articles
	 *
	 * @return 	void
	 */
	public function convertCmsDatabaseRecords()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id' );
		
		foreach( $this->fetch( 'content', 'id' ) as $row )
		{
			/* Set the basic details */
			$info = array(
				'record_id'				=> $row['id'],
				'record_database_id'	=> 1,
				'member_id'				=> $row['created_by'],
				'record_allow_comments'	=> 1,
				'record_saved'			=> strtotime( $row['created'] ),
				'record_publish_date'	=> strtotime( $row['created'] ),
				'category_id'			=> $row['catid'],
				'record_approved'		=> ( $row['state'] != 0 ? 1 : 0 ),
				'record_updated'		=> ( strtotime( $row['modified'] ) > 0 ) ? strtotime( $row['modified'] ) : strtotime( $row['created'] ),
				'record_edit_member_id'	=> $row['modified_by'],
			);

			/* Joomla saves half of a post in introtext and half in fulltext */
			if( isset( $row['introtext'] ) AND $row['introtext'] )
			{
				$row['fulltext'] = $row['fulltext'] ? $row['introtext'] . '<br><br>' . $row['fulltext'] : $row['introtext'];
			}
			
			/* And then the custom fields */
			$fields = array(
				1 => $row['title'],
				2 => $row['fulltext']
			);
			
			$libraryClass->convertCmsDatabaseRecord( $info, $fields );

			$libraryClass->setLastKeyValue( $row['id'] );
		}
	}
}