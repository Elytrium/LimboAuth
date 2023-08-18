<?php
/**
 * @brief		File Handler: Database
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 May 2013
 */

namespace IPS\File;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Handler: Database
 */
class _Database extends \IPS\File
{
	/* !ACP Configuration */
	
	/**
	 * Settings
	 *
	 * @param	array	$configuration		Configuration if editing a setting, or array() if creating a setting.
	 * @return	array
	 */
	public static function settings( $configuration=array() )
	{
		return array();
	}

	/**
	 * Test Settings
	 *
	 * @param	array	$values	The submitted values
	 * @return	void
	 * @throws	\LogicException
	 */
	public static function testSettings( &$values )
	{

	}
	
	/**
	 * Display name
	 *
	 * @param	array	$settings	Configuration settings
	 * @return	string
	 */
	public static function displayName( $settings )
	{
		return \IPS\Member::loggedIn()->language()->addToStack('filehandler__Database');
	}
	
	/* !File Handling */

	/**
	 * Is this URL valid for this engine?
	 *
	 * @param   \IPS\Http\Url   $url            URL
	 * @param   array           $configuration  Specific configuration for this method
	 * @return  bool
	 */
	public static function isValidUrl( $url, $configuration )
	{
		$check = \IPS\Http\Url::internal( "applications/core/interface/file/index.php", 'none', NULL, array(), \IPS\Http\Url::PROTOCOL_RELATIVE );
		if ( mb_substr( $url, 0, mb_strlen( $check ) ) === $check )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Constructor
	 *
	 * @param	array	$configuration	Storage configuration
	 * @return	void
	 */
	public function __construct( $configuration )
	{
		$this->container = 'monthly_' . date( 'Y' ) . '_' . date( 'm' );
		parent::__construct( $configuration );
	}

	/**
	 * Return the base URL
	 *
	 * @return string
	 */
	public function baseUrl()
	{
		return (string) \IPS\Http\Url::internal( "applications/core/interface/file/index.php?file=", 'none', NULL, array(), \IPS\Http\Url::PROTOCOL_RELATIVE );
	}
	
	/**
	 * Encode the file name for the fully qualified URL
	 *
	 * @param	string	$filename	The filename
	 * @return	string
	 */
	public function encodeFileUrl( $filename )
	{
		return \IPS\Http\Url::encodeComponent( \IPS\Http\Url::COMPONENT_QUERY_VALUE, $filename );
	}
	
	/**
	 * Load File Data
	 *
	 * @return	void
	 */
	public function load()
	{
		parent::load();
		
		$this->filename = urldecode( $this->filename );
		$this->container = urldecode( $this->container );
		$this->originalFilename = urldecode( $this->originalFilename );

		/* The URL may have been stored as //site.com/applications/core/interface/file/index.php?id=1&salt=1111 in which case the previous load()
			call wouldn't have loaded the data correctly, so we need to do it here now */
		if( isset( $this->url->queryString['id'] ) AND isset( $this->url->queryString['salt'] ) )
		{
			try
			{
				$record = \IPS\Db::i()->select( '*', 'core_files', array( 'id=? AND salt=?', $this->url->queryString['id'], $this->url->queryString['salt'] ) )->first();
				$this->contents = $record['contents'];
				$this->filename = $record['filename'];
				$this->originalFilename = $this->unObscureFilename( $this->filename );
				$this->container = $record['container'];
			}
			catch( \UnderflowException $ex )
			{
				throw new \IPS\File\Exception( $this->container . '/' . $this->filename, \IPS\File\Exception::DOES_NOT_EXIST, $this->originalFilename );
			}
		}
	}

	/**
	 * Get Contents
	 *
	 * @param	bool	$refresh	If TRUE, will fetch again
	 * @return	string
	 */
	public function contents( $refresh=FALSE )
	{		
		if ( $this->contents === NULL or $refresh === TRUE )
		{
			try
			{
				$record = \IPS\Db::i()->select( '*', 'core_files', array( 'filename=? AND container=?', $this->filename, $this->container ) )->first();
				$this->contents = $record['contents'];
			}
			catch( \UnderflowException $ex )
			{
				throw new \IPS\File\Exception( $this->container . '/' . $this->filename, \IPS\File\Exception::DOES_NOT_EXIST, $this->originalFilename );
			}
		}
		return $this->contents;
	}

	/**
	 * Replace file contents
	 *
	 * @param	string	$contents	New contents
	 * @return	void
	 */
	public function replace( $contents )
	{
		/* Ensure any existing files with this name are removed otherwise the wrong file may be selected from the database as it will store multiple copies when replace() is used */
		\IPS\Db::i()->delete( 'core_files', array( 'filename=? and container=?', $this->filename, $this->container ) );
		
		parent::replace( $contents );
	}
	
	/**
	 * Save File
	 *
	 * @return	void
	 */
	public function save()
	{
		$salt = md5( mt_rand() );
		
		$id = \IPS\Db::i()->insert( 'core_files', array(
			'filename'	=> $this->filename,
			'salt'		=> $salt,
			'contents'	=> $this->contents(),
			'container'	=> $this->container
		) );

		$this->url = \IPS\Http\Url::internal( "applications/core/interface/file/index.php?file={$this->container}/{$this->filename}", 'none', NULL, array(), \IPS\Http\Url::PROTOCOL_RELATIVE );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Db::i()->delete( 'core_files', array( 'filename=? AND container=?', $this->filename, $this->container ) );

		/* Log deletion request */
		$immediateCaller = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
		$debug = array_map( function( $row ) {
			return array_filter( $row, function( $key ) {
				return \in_array( $key, array( 'class', 'function', 'line' ) );
			}, ARRAY_FILTER_USE_KEY );
		}, debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) );
		$this->log( "file_deletion", 'delete', $debug, 'log' );
	}
	
	/**
	 * Delete Container
	 *
	 * @param	string	$container	Key
	 * @return	void
	 */
	public function deleteContainer( $container )
	{
		\IPS\Db::i()->delete( 'core_files', array( 'container=?', $container ) );

		/* Log deletion request */
		$realContainer = $this->container;
		$this->container = $container;
		$this->log( "container_deletion", 'delete', NULL, 'log' );
		$this->container = $realContainer;
	}

	/**
	 * Remove orphaned files
	 *
	 * @param	int			$fileIndex		The file offset to start at in a listing
	 * @param	array	$engines	All file storage engine extension objects
	 * @return	array
	 */
	public function removeOrphanedFiles( $fileIndex, $engines )
	{
		/* Start off our results array */
		$results	= array(
			'_done'				=> FALSE,
			'fileIndex'			=> $fileIndex,
		);

		/* Init */
		$checked	= 0;

		/* Loop over files */
		foreach( \IPS\Db::i()->select( '*', 'core_files', array(), 'id ASC', array( $fileIndex, 100 ) ) as $file )
		{
			$checked++;

			/* Next we will have to loop through each storage engine type and call it to see if the file is valid */
			foreach( $engines as $engine )
			{
				/* If this file is valid for the engine, skip to the next file */
				if( $engine->isValidFile( $file['container'] . '/' . $file['filename'] ) )
				{
					continue 2;
				}
			}

			/* If we are still here, the file was not valid.  Delete and increment count. */
			$this->logOrphanedFile( $file['container'] . '/' . $file['filename'] );
		}

		$results['fileIndex'] += $checked;

		/* Are we done? */
		if( !$checked OR $checked < 100 )
		{
			$results['_done']	= TRUE;
		}

		return $results;
	}
}