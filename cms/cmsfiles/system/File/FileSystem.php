<?php
/**
 * @brief		File Handler: File System
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
 * File Handler: File System
 */
class _FileSystem extends \IPS\File
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
		$default = ( isset( $configuration['custom_url'] ) and ! empty( $configuration['custom_url'] ) ) ? TRUE : FALSE;
		
		return array(
			'dir'		 => array( 'type' => 'Text', 'default' => '{root}/uploads' ),
			'toggle'	 => array( 'type' => 'YesNo', 'default' => $default, 'options' => array(
				'togglesOn' => array( 'FileSystem_custom_url' )
			) ),
			'custom_url' => array( 'type' => 'Text', 'default' => '' ),
		);
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
		$values['dir'] = rtrim( $values['dir'], '\\/' );
		$testDir = str_replace( '{root}', \IPS\ROOT_PATH, $values['dir'] );
		$values['url'] = trim( str_replace( \IPS\ROOT_PATH, '', $testDir ), '\\/' );
		
		if ( empty( $values['toggle'] ) )
		{
			$values['custom_url'] = NULL;
		}

		if ( !$testDir )
		{
			throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'dir_not_provided', FALSE ) );
		}
		if ( !is_dir( $testDir ) )
		{
			throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'dir_does_not_exist', FALSE, array( 'sprintf' => array( $testDir ) ) ) );
		}
		if ( !is_writable( $testDir ) )
		{
			throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'dir_is_not_writable', FALSE, array( 'sprintf' => array( $testDir ) ) ) );
		}
		
		if ( ! empty( $values['custom_url'] ) )
		{
			if ( mb_substr( $values['custom_url'], 0, 2 ) !== '//' AND mb_substr( $values['custom_url'], 0, 4 ) !== 'http' )
			{
				$values['custom_url'] = '//' . $values['custom_url'];
			}
			
			$test = $values['custom_url'];
			
			if ( mb_substr( $test, 0, 2 ) === '//' )
			{
				$test = 'http:' . $test;
			}
			
			if ( filter_var( $test, FILTER_VALIDATE_URL ) === false )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'url_is_not_real', FALSE, array( 'sprintf' => array( $values['custom_url'] ) ) ) );
			}
		}
	}

	/**
	 * Determine if the change in configuration warrants a move process
	 *
	 * @param	array		$configuration	    New Storage configuration
	 * @param	array		$oldConfiguration   Existing Storage Configuration
	 * @return	boolean
	 */
	public static function moveCheck( $configuration, $oldConfiguration )
	{
		if ( str_replace( '{root}', \IPS\ROOT_PATH, preg_replace( '#/{1,}/#', '/', rtrim( $configuration['dir'], '/' ) ) ) !== str_replace( '{root}', \IPS\ROOT_PATH, preg_replace( '#/{1,}/#', '/', rtrim( $oldConfiguration['dir'], '/' ) ) ) )
		{
			return TRUE;
		}
		
		return FALSE;
	}

	/**
	 * Display name
	 *
	 * @param	array	$settings	Configuration settings
	 * @return	string
	 */
	public static function displayName( $settings )
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'filehandler_display_name', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('filehandler__FileSystem'), str_replace( '{root}', \IPS\ROOT_PATH, $settings['dir'] ) ) ) );
	}

	/* !File Handling */

	/**
	 * @brief	Does this storage method support chunked uploads?
	 */
	public static $supportsChunking = TRUE;
	
	/**
	 * Constructor
	 *
	 * @param	array	$configuration	Storage configuration
	 * @return	void
	 */
	public function __construct( $configuration )
	{
		$this->container = 'monthly_' . date( 'Y' ) . '_' . date( 'm' );
		$configuration['dir'] = str_replace( '{root}', \IPS\ROOT_PATH, $configuration['dir'] );

		parent::__construct( $configuration );
	}

	/**
	 * @brief	Store the path to the file so we can just move it later
	 */
	protected $temporaryFilePath	= NULL;
	
	/**
	 * Set the file
	 *
	 * @param	string	$filepath	The path to the file on disk
	 * @return  void
	 */
	public function setFile( $filepath )
	{
		$this->temporaryFilePath	= $filepath;
	}
	
	/**
	 * Return the base URL
	 *
	 * @return string
	 */
	public function baseUrl()
	{
		$url = ( empty( $this->configuration['custom_url'] ) ) ? rtrim( \IPS\Settings::i()->base_url, '/' ) . '/' . ltrim( $this->configuration['url'], '/' ) : $this->configuration['custom_url'];
		if ( \IPS\Request::i()->isSecure() )
		{
			$url = str_replace( 'http://', 'https://', $url );
		}
		return rtrim( $url, '/' );
	}
	
	/**
	 * Move file to a different storage location
	 *
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param   int         $flags                  Bitwise Flags
	 * @return	\IPS\File
	 */
	public function move( $storageConfiguration, $flags=0 )
	{
		if ( $this->configurationId === $storageConfiguration and isset( $this->configuration['old_url'] ) )
		{
			/* We're just updating the URL, not actually moving the file */
			if ( mb_substr( $this->url, 0, mb_strlen( $this->configuration['old_url'] ) ) == $this->configuration['old_url'] )
			{
				$this->url = str_replace( $this->configuration['old_url'], $this->configuration['url'], $this->url );

				return $this;
			}

			/* Is this the new url, then? */
			if (  mb_substr( $this->url, 0, mb_strlen( $this->configuration['url'] ) ) != $this->configuration['url'] )
			{
				/* No? Something has gone wrong */
				throw new \RuntimeException('url_update_incorrect_url');
			}
			else
			{
				return $this;
			}
		}
		else if ( static::$storageConfigurations[ $storageConfiguration ]['method'] === 'FileSystem' )
		{
			$newConfig = json_decode( static::$storageConfigurations[ $storageConfiguration ]['configuration'], TRUE );

			/* Do both configs have the same path? */
			if ( str_replace( '{root}', \IPS\ROOT_PATH, $newConfig['dir'] ) == $this->configuration['dir'] )
			{
				/* Don't move */
				return $this;
			}
		}

		return parent::move( $storageConfiguration );
	}

	/**
	 * Print the contents of the file
	 *
	 * @param	int|null	$start		Start point to print from (for ranges)
	 * @param	int|null	$length		Length to print to (for ranges)
	 * @param	int|null	$throttle	Throttle speed
	 * @return	void
	 */
	public function printFile( $start=NULL, $length=NULL, $throttle=NULL )
	{
		$file	= $this->configuration['dir'] . '/' . $this->container . '/' . $this->filename;

		$this->sendFile( $file, $start, $length, $throttle );
	}

	/**
	 * Get Contents
	 *
	 * @param	bool	$refresh	If TRUE, will fetch again
	 * @return	string
	 * @throws  \RuntimeException
	 */
	public function contents( $refresh=FALSE )
	{
		if ( $this->contents === NULL or $refresh === TRUE )
		{
			if( file_exists( $this->configuration['dir'] . '/' . $this->container . '/' . $this->filename ) )
			{
				$this->contents = @file_get_contents( $this->configuration['dir'] . '/' . $this->container . '/' . $this->filename );
				
				if ( $this->contents === FALSE )
				{
					throw new \IPS\File\Exception( $this->container . '/' . $this->filename, \IPS\File\Exception::CANNOT_OPEN, $this->originalFilename );
				}
			}
			else
			{
				throw new \IPS\File\Exception( $this->container . '/' . $this->filename, \IPS\File\Exception::DOES_NOT_EXIST, $this->originalFilename );
			}
		}

		return $this->contents;
	}

	/**
	 * If the file is an image, get the dimensions
	 *
	 * @return	array
	 * @throws	\DomainException
	 * @throws  \RuntimeException
	 * @throws	\InvalidArgumentException
	 */
	public function getImageDimensions()
	{
		if( !$this->isImage() )
		{
			throw new \DomainException;
		}

		$file	= $this->configuration['dir'] . '/' . $this->container . '/' . $this->filename;
		
		if ( ! file_exists( $file ) )
		{
			throw new \RuntimeException;
		}
		
		if( ( $image = getimagesize( $file ) ) === FALSE )
		{
			return parent::getImageDimensions();
		}

		return array( $image[0], $image[1] );
	}
		
	/**
	 * Save File
	 *
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function save()
	{
		/* Make the folder */
		$folder = $this->configuration['dir'] . '/' . $this->getFolder();
				
		/* Save the file */
		if( $this->temporaryFilePath )
		{
			if( static::$copyFiles === TRUE )
			{
				if( !@\copy( $this->temporaryFilePath, "{$folder}/{$this->filename}" ) )
				{
					\IPS\Log::log( "Could not copy file {$folder}/{$this->filename}" , 'FileSystem' );
					throw new \IPS\File\Exception( "{$folder}/{$this->filename}", \IPS\File\Exception::CANNOT_COPY, $this->originalFilename );
				}
			}
			else
			{
				if( !@\rename( $this->temporaryFilePath, "{$folder}/{$this->filename}" ) )
				{
					\IPS\Log::log( "Could not move file {$folder}/{$this->filename}" , 'FileSystem' );
					throw new \IPS\File\Exception( "{$folder}/{$this->filename}", \IPS\File\Exception::CANNOT_MOVE, $this->originalFilename );
				}
			}

			@chmod( "{$folder}/{$this->filename}", \IPS\IPS_FILE_PERMISSION );
		}
		else
		{
			if ( $contents = $this->contents() )
			{				
				if ( !@\file_put_contents( "{$folder}/{$this->filename}", $contents ) )
				{					
					\IPS\Log::log( "Could not write file {$folder}/{$this->filename}" , 'FileSystem' );
					throw new \IPS\File\Exception( "{$folder}/{$this->filename}", \IPS\File\Exception::CANNOT_WRITE, $this->originalFilename );
				}

				@chmod( "{$folder}/{$this->filename}", \IPS\IPS_FILE_PERMISSION );
			}
			else
			{
				$return = touch( "{$folder}/{$this->filename}" );
				@chmod( "{$folder}/{$this->filename}", \IPS\IPS_FILE_PERMISSION );
			}
		}
		
		/* Clear zend opcache if enabled */
		if ( \function_exists( 'opcache_invalidate' ) )
		{
			@opcache_invalidate( "{$folder}/{$this->filename}" );
		}
		
		/* Set the URL */
		$this->url = \IPS\Http\Url::createFromString( $this->fullyQualifiedUrl( "{$this->container}/{$this->filename}" ), FALSE );
	}
		
	/**
	 * Delete
	 *
	 * @return	bool
	 */
	public function delete()
	{		
		/* Log deletion request */
		$immediateCaller = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
		$debug = array_map( function( $row ) {
			return array_filter( $row, function( $key ) {
				return \in_array( $key, array( 'class', 'function', 'line' ) );
			}, ARRAY_FILTER_USE_KEY );
		}, debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) );
		$this->log( "file_deletion", 'delete', $debug, 'log' );

		if( file_exists( "{$this->configuration['dir']}/{$this->container}/{$this->filename}" ) )
		{
			$result = @unlink( "{$this->configuration['dir']}/{$this->container}/{$this->filename}" );

			/* Clear zend opcache if enabled */
			if ( \function_exists( 'opcache_invalidate' ) )
			{
				@opcache_invalidate( "{$this->configuration['dir']}/{$this->container}/{$this->filename}" );
			}

			return $result;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Delete Container
	 *
	 * @param	string	$container	Key
	 * @param	bool	$skipClear	Skip clearing the opcache, used for recursive calls since the parent call will do it
	 * @return	void
	 */
	public function deleteContainer( $container, $skipClear = FALSE )
	{
		$dir = $this->configuration['dir'] . '/' . $container;

		if ( is_dir( $dir ) )
		{
			$files	= array();
			$dirs	= array();

			foreach ( new \DirectoryIterator( $dir ) as $f )
			{
				if ( !$f->isDot() )
				{
					if( $f->isDir() )
					{
						$dirs[]	= $container . '/' . $f->getFilename();
					}
					else
					{
						$files[]	= $f->getPathname();
					}
				}
			}

			/* If we have any directories to delete - delete them */
			foreach( $dirs as $directory )
			{
				$this->deleteContainer( $directory, true );
			}

			/* And now delete the files */
			foreach( $files as $file )
			{
				unlink( $file );

				/* Clear zend opcache if enabled */
				if( $skipClear === FALSE and \function_exists( 'opcache_invalidate' ) )
				{
					@opcache_invalidate( $file );
				}
			}

			/* And finally, remove the directory we're currently working with */
			rmdir( $dir );
		}

		/* Log deletion request */
		$realContainer = $this->container;
		$this->container = $container;
		$this->log( "container_deletion", 'delete', $_REQUEST, 'log' );
		$this->container = $realContainer;
	}
	
	/**
	 * Initiate a chunked upload
	 *
	 * @param	string		$filename	The desired filename
	 * @param	string|null	$container	Key to identify container for storage
	 * @param	bool		$obscure		Controls if an md5 hash should be added to the filename
	 * @return	mixed					A reference to be passed to chunkProcess() and chunkFinish()
	 * @throws	\RuntimeException
	 */
	public function chunkInit( $filename, $container = '', $obscure = TRUE )
	{
		/* If we don't have a chunk folder, create one */
		$tempFolder = $this->getFolder( 'chunks' );

		/* Get the final folder name */
		$folder = $this->getFolder( $container );

		$this->setFilename( $filename, $obscure );

		$return = array( 'temp' => "{$this->configuration['dir']}/{$tempFolder}/chunk-" . md5( uniqid() ) . '.txt', 'final' => "{$folder}/" . $this->filename, 'original' => $this->originalFilename );
		touch( $return['temp'] );
		return $return;
	}
	
	/**
	 * Append more contents in a chunked upload
	 *
	 * @param	mixed	$ref							The reference for this upload as returned by chunkInit() 
	 * @param	string	$temporaryFileOrContents		The contents to write, or the path to the temporary file on disk with the contents
	 * @param	int		$chunkNumber					Which chunk this is (0 for the first chunk, 1 for the second, etc)
	 * @param	bool	$isContents					If TRUE, $temporaryFileOrContents is treated as raw contents. If FALSE, is path to file
	 * @return	mixed								Updated reference fore future chunkProcess() calls and chunkFinish()
	 * @throws	\RuntimeException
	 */
	public function chunkProcess( $ref, $temporaryFileOrContents, $chunkNumber, $isContents = FALSE )
	{
		$file = fopen( $ref['temp'], 'ab' );
		if ( $isContents )
		{
			\fwrite( $file, $temporaryFileOrContents );
		}
		else
		{
			$_chunk = fopen( $temporaryFileOrContents, 'rb' );
			while ( $buffer = fread( $_chunk, 4096 ) )
			{
				\fwrite( $file, $buffer );
			}
			@unlink( $temporaryFileOrContents );
		}
		fclose( $file );
		
		if ( \function_exists( 'opcache_invalidate' ) )
		{
			@opcache_invalidate( $ref['temp'] );
		}
		
		return $ref;
	}

	/**
	 * Finalize a chunked upload
	 *
	 * @param   array       $ref                    The reference for this upload as returned by chunkInit()
	 * @param   string      $storageConfiguration   Storage configuration name
	 * @return  \IPS\File                           The file object just created
	 * @throws  \RuntimeException
	 */
	public function chunkFinish( array $ref, string $storageConfiguration ): \IPS\File
	{
		rename( $ref['temp'], "{$this->configuration['dir']}/{$ref['final']}" );

		$fileObj = \IPS\File::get( $storageConfiguration,$ref['final'] );

		/* This isn't preserved for chunk uploads, so we need to set it so apps can get the real value */
		if( !empty( $ref['original'] ) )
		{
			$fileObj->originalFilename = $ref['original'];
		}

		return $fileObj;
	}
	
	/* !File System Utility Methods */
	
	/**
	 * Get the path to the folder
	 *
	 * @param	string|null	$folderName	Folder name - if NULL, a monthly name will be used
	 * @return	string
	 */
	protected function getFolder( $folderName=NULL )
	{
		$folderName = $folderName ?: $this->container;
		$folder = $this->configuration['dir'] . '/' . $folderName;
		if( !is_dir( $folder ) )
		{
			if( @mkdir( $folder, \IPS\IPS_FOLDER_PERMISSION, TRUE ) === FALSE or @chmod( $folder, \IPS\IPS_FOLDER_PERMISSION ) === FALSE )
			{
				throw new \IPS\File\Exception( $folder, \IPS\File\Exception::CANNOT_MAKE_DIR );
			}
			@\file_put_contents( $folder . '/index.html', '' );
		}
		
		return $folderName;
	}

	/**
	 * Remove orphaned files
	 *
	 * @param	int		$fileIndex	The file offset to start at in a listing
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

		/* Some basic init */
		$checked	= 0;
		$skipped	= 0;

		/* We need to open our storage directory and start looping over it */
		$dir = $this->configuration['dir'];

		if ( is_dir( $dir ) )
		{
			$iterator	= new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS ) );

			foreach ( $iterator as $f )
			{
				/* We aren't checking directories */
				if( $f->isDir() OR $f->getFilename() == 'index.html' OR mb_substr( $f->getFilename(), 0, 1 ) === '.' OR mb_substr( $iterator->getSubPathname(), 0, 5 ) === 'logs/' )
				{
					continue;
				}

				/* Have we hit our limit?  If so we need to stop. */
				if( $checked >= 100 )
				{
					break;
				}

				/* Is there an offset?  If so we need to skip */
				if( $fileIndex > 0 AND $fileIndex > $skipped )
				{
					$skipped++;
					continue;
				}

				$checked++;

				/* Next we will have to loop through each storage engine type and call it to see if the file is valid */
				foreach( $engines as $engine )
				{
					/* If this file is valid for the engine, skip to the next file */
					try
					{
						if( $engine->isValidFile( $iterator->getSubPathname() ) )
						{
							continue 2;
						}
					}
					catch( \InvalidArgumentException $e )
					{
						continue 2;
					}
				}
								
				/* If we are still here, the file was not valid */
				$this->logOrphanedFile( $iterator->getSubPathname() );
			}
		}

		$results['fileIndex'] += $checked;

		/* Are we done? */
		if( !$checked OR $checked < 100 )
		{
			$results['_done']	= TRUE;
		}

		return $results;
	}

	/**
	 * Get filesize (in bytes)
	 *
	 * @return	string|bool
	 */
	public function filesize()
	{
		if( $this->_cachedFilesize !== NULL )
		{
			return $this->_cachedFilesize;
		}

		$this->_cachedFilesize = file_exists( $this->configuration['dir'] . '/' . $this->container . '/' . $this->filename ) ? @filesize( $this->configuration['dir'] . '/' . $this->container . '/' . $this->filename ) : FALSE;
		return $this->_cachedFilesize;
	}
}