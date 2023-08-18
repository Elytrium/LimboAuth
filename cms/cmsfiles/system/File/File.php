<?php
/**
 * @brief		File Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Feb 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Class
 */
abstract class _File
{	
	/**
	 * Get available storage handlers
	 *
	 * @param	array|NULL	$current	The file storage configuration being edited
	 * @return	array
	 */
	public static function storageHandlers( $current )
	{
		$return = array();
		
		if ( !\IPS\CIC OR ( $current and $current['method'] === 'FileSystem' ) )
		{
			$return['FileSystem'] = 'IPS\\File\\FileSystem';
		}
		
		$return['Amazon'] = 'IPS\\File\\Amazon';
		$return['Database'] = 'IPS\\File\\Database';
		
		if ( $current and $current['method'] === 'Ftp' )
		{
			$return['Ftp'] = 'IPS\\File\\Ftp';
		}
		
		return $return;
	}
	
	/**
	 * @brief	File extensions considered safe. Not an exhaustive list but these are the files we're most interested in being recognised.
	 */
	public static $safeFileExtensions = array( 'js', 'css', 'txt', 'ico', 'gif', 'jpg', 'jpe', 'jpeg', 'png', 'mp4', '3gp', 'mov', 'ogg', 'ogv', 'mp3', 'mpg', 'mpeg', 'ico', 'flv', 'webm', 'wmv', 'avi', 'm4v', 'webp', 'm4a', 'wav' );
	
	/**
	 * @brief	File extensions for HTML5 compatible videos
	 */
	public static $videoExtensions = array( 'mp4', '3gp', 'mov', 'ogg', 'ogv', 'mpg', 'mpeg', 'flv', 'webm', 'wmv', 'avi', 'm4v' );
		
	/**
	 * @brief	File extensions for HTML5 compatible audio
	 */
	public static $audioExtensions = array( 'mp3', 'ogg', 'wav', 'm4a' );

	/**
	 * @brief	Does this storage method support chunked uploads?
	 */
	public static $supportsChunking = FALSE;
	
	/**
	 * @brief	Storage Configurations
	 */
	protected static $storageConfigurations = NULL;
	
	/**
	 * @brief	Thumbnail dimensions
	 */
	protected static $thumbnailDimensions = array();

	/**
	 * @brief Cached filesize
	 */
	protected $_cachedFilesize	= NULL;

	/**
	 * @brief	Ignore errors from uploaded files?
	 */
	const IGNORE_UPLOAD_ERRORS	= 1;

	/**
	 * @brief	When moving files, do not delete the original immediately but log for later deletion
	 */
	const MOVE_DELETE_NOW = 2;

	/**
	 * Get class
	 *
	 * @param	string|int		$storageExtension	Storage extension or configuration ID
	 * @param   bool			$tryOldFirst		Whether to try the old file storage config first or not
	 * @throws 	\RuntimeException
	 * @return	static
	 */
	public static function getClass( $storageExtension, $tryOldFirst=FALSE )
	{
		static::getStore();
		
		$configurationId = NULL;
		if ( \is_int( $storageExtension ) ) 
		{
			$configurationId = $storageExtension;
		}
		else
		{
			$settings = json_decode( \IPS\Settings::i()->upload_settings, TRUE );

			/* If we are IN_DEV, make sure this is a valid file storage extension */
			if ( \IPS\IN_DEV and !isset( $settings[ "filestorage__{$storageExtension}" ] ) )
			{
				/* Quick sanity check in case this is a new extension that hasn't been set yet. */
				$isValid = FALSE;

				foreach( \IPS\Application::allExtensions( 'core', 'FileStorage', FALSE ) as $extension )
				{
					$extensionNamespace = explode( '\\', \get_class( $extension ) );
					$extensionName		= array_pop( $extensionNamespace );

					if( $storageExtension == $extensionNamespace[1] . '_' . $extensionName )
					{
						$isValid = TRUE;
						break;
					}
				}

				if( !$isValid )
				{
					throw new \RuntimeException( 'NO_STORAGE_EXTENSION' );			
				}
			}
			
			/* If this storage extension hasn't been set yet, grab the first *valid* configuration and use that (and set it for future use) */
			if ( !isset( $settings[ "filestorage__{$storageExtension}" ] ) )
			{
				foreach ( static::$storageConfigurations as $k => $data )
				{
					/* Test the storage config - the first one that works wins! */
					$handlers = static::storageHandlers( $data );

					/* Check the handler is still available */
					if( !isset( $handlers[ $data['method'] ] ) )
					{
						continue;
					}

					$classname = $handlers[ $data['method'] ];
					$class = new $classname( json_decode( $data['configuration'], TRUE ) );
					$class->configurationId = $k;

					try
					{
						/* Test - if no LogicException is thrown we are ok */
						$configSettings = json_decode( $data['configuration'], TRUE );
						$class->testSettings( $configSettings );

						/* Still here? Then let's use this configuration. */
						$configurationId = $k;

						/* Now store this for future reference */
						$settings["filestorage__{$storageExtension}"] = $configurationId;
						\IPS\Settings::i()->changeValues( array( 'upload_settings' => json_encode( $settings ) ) );
						break;
					}
					catch( \LogicException $e )
					{
						/* Move on to the next file storage configuration */
						continue;
					}
				}
			}
			else
			{
				/* We have an array of IDs when a move is in progress, the first ID is the new storage method, the second ID is the old */
				if ( \is_array( $settings[ "filestorage__{$storageExtension}" ] ) )
				{
					/* Do we want to use the old storage config if available? */
					if ( $tryOldFirst === TRUE )
					{
						/* We want the old storage method as we know the file is on the old server, but may not have moved to the new yet */
						$copyOfSettings = $settings["filestorage__{$storageExtension}"];
						$configurationId = array_pop( $copyOfSettings );
						
						if ( ! isset( static::$storageConfigurations[ $configurationId ] ) )
						{
							/* No longer exists, lets use the new storage method */
							$copyOfSettings  = $settings["filestorage__{$storageExtension}"];
							$configurationId = array_shift( $copyOfSettings );
						}
					}

					if ( ! isset( static::$storageConfigurations[ $configurationId ] ) )
					{
						/* Use the first ID as this is the 'new' storage engine */
						$copyOfSettings = $settings["filestorage__{$storageExtension}"];
						$configurationId = array_shift( $copyOfSettings );
					}
				}
				else if ( isset( static::$storageConfigurations[ $settings[ "filestorage__{$storageExtension}" ] ] ) )
				{
					$configurationId = $settings[ "filestorage__{$storageExtension}" ];
				}
				else
				{
					$configurationId = $settings[ "filestorage__{$storageExtension}" ];
					$storageConfigurations = static::$storageConfigurations;
					static::$storageConfigurations[ $settings[ "filestorage__{$storageExtension}" ] ] = array_shift( $storageConfigurations );
				}
			}
		}
		
		if ( ! isset( static::$storageConfigurations[ $configurationId ] ) )
		{
			throw new \RuntimeException;
		}		
		
		$handlers	= static::storageHandlers( static::$storageConfigurations[ $configurationId ] );
		$classname	= $handlers[ static::$storageConfigurations[ $configurationId ]['method'] ];
		$class		= new $classname( json_decode( static::$storageConfigurations[ $configurationId ]['configuration'], TRUE ) );
		$class->configurationId = $configurationId;
		return $class;
	}

	/**
	 * Load storage configurations
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( static::$storageConfigurations === NULL )
		{
			if ( isset( \IPS\Data\Store::i()->storageConfigurations ) )
			{
				static::$storageConfigurations = \IPS\Data\Store::i()->storageConfigurations;
			}
			else
			{
				\IPS\Data\Store::i()->storageConfigurations = iterator_to_array( \IPS\Db::i()->select( '*', 'core_file_storage' )->setKeyField('id') );
				static::$storageConfigurations = \IPS\Data\Store::i()->storageConfigurations;
			}
		}

		return static::$storageConfigurations;
	}

	/**
	 * @brief	Copy files instead of moving them
	 */
	public static $copyFiles = FALSE;

	/**
	 * @brief	Temporarily stored EXIF data for an image
	 */
	public $exifData	= NULL;

	/**
	 * Create File
	 *
	 * @param	string		$storageExtension	Storage extension
	 * @param	string		$filename			Filename
	 * @param	string|null	$data				Data (set to null if you intend to use $filePath)
	 * @param	string|null	$container			Key to identify container for storage
	 * @param	boolean		$isSafe				This file is safe and doesn't require security checking
	 * @param	string|null	$filePath			Path to existing file on disk - Filesystem can move file without loading all of the contents into memory if this method is used
	 * @param	bool		$obscure			Controls if an md5 hash should be added to the filename
	 * @return	\IPS\File
	 * @throws	\DomainException
	 * @throws	\RuntimeException
	 */
	public static function create( $storageExtension, $filename, $data=NULL, $container=NULL, $isSafe=FALSE, $filePath=NULL, $obscure=TRUE )
	{
		/* Check we have a file */
		if( $data === NULL AND $filePath === NULL )
		{
			throw new \DomainException( "NO_FILE_UPLOADED", 1 );
		}

		/* Init */
		$class = static::getClass( $storageExtension, TRUE );
		if ( $container !== NULL )
		{
			$class->container = $container;
		}
		
		$class->storageExtension = $storageExtension;
						
		/* Image-specific stuff */
		$ext = mb_substr( $filename, ( mb_strrpos( $filename, '.' ) + 1 ) );
		if( \in_array( mb_strtolower( $ext ), \IPS\Image::supportedExtensions() ) and ( !$isSafe or \IPS\Image::exifSupported() ) )
		{
			/* Get contents */
			if( $data === NULL AND $filePath !== NULL )
			{
				$data = \file_get_contents( $filePath );
				$filePath = NULL;
			}
			
			/* Make sure images don't have HTML in the comments, which can cause be an XSS in older versions of IE */
			$image = NULL;
			if( !$isSafe and static::checkXssInFile( $data ) )
			{
				/* Try to just strip the EXIF */
				$image = \IPS\Image::create( $data );
				$image->resize( $image->width, $image->height );
				$data = (string) $image;
				
				/* And if it still fails, throw an error */
				if ( static::checkXssInFile( $data ) )
				{
					throw new \DomainException( "SECURITY_EXCEPTION_RAISED", 99 );
				}
			}
			
			/* Correct orientation */
			if ( \IPS\Image::exifSupported() )
			{
				$image = $image ?: \IPS\Image::create( $data );

				$class->exifData = $image->parseExif();
				
				if ( $image->hasBeenRotated )
				{
					$data = (string) \IPS\Image::create( $data );
				}
			}
		}
		
		/* Set the name */
		$class->setFilename( $filename, $obscure );
		
		/* Set the contents */
		if( $data !== NULL )
		{
			$class->contents = $data;
		}
		else
		{
			$class->setFile( $filePath );
		}
		
		/* Save and return */
		$class->save();
		return $class;
	}

	/**
	 * Create \IPS\File objects from uploaded $_FILES array
	 *
	 * @param	string		$storageLocation	The storage location to create the files under (e.g. core_Attachment)
	 * @param	string|NULL	$fieldName			Restrict collection of uploads to this upload field name, or pass NULL to collect any and all uploads
	 * @param	array|NULL	$allowedFileTypes	Array of allowed file extensions, or NULL to allow any extensions
	 * @param	int|NULL	$maxFileSize		The maximum file size in MB, or NULL to allow any size
	 * @param	int|NULL	$totalMaxSize		The maximum total size of all files in MB, or NULL for no limit
	 * @param	int			$flags				`\IPS\File::IGNORE_UPLOAD_ERRORS` to skip over invalid files rather than throw exception
	 * @param	array|NULL	$callback			Callback function to run against the file contents before creating the file (useful for resizing images, for instance)
	 * @param	string|null	$container			Key to identify container for storage
	 * @param	bool		$obscure			Controls if an md5 hash should be added to the filename
	 * @return	array		Array of \IPS\File objects
	 * @throws	\DomainException
	 * @throws	\RuntimeException
	 */
	public static function createFromUploads( $storageLocation, $fieldName=NULL, $allowedFileTypes=NULL, $maxFileSize=NULL, $totalMaxSize=NULL, $flags=0, $callback=NULL, $container=NULL, $obscure=TRUE )
	{				
		/* Do we have any uploads? */
		if( empty( $_FILES ) )
		{
			return array();
		}

		if( $fieldName !== NULL )
		{
			if( empty( $_FILES[ $fieldName ]['name'] ) )
			{
				return array();
			}
		}

		/* Normalize the files array */
		$files			= static::normalizeFilesArray( $fieldName );
		$fileObjects	= array();
		
		/* Now loop over each file */
		$currentTotal = 0;
		foreach( $files as $i => $file )
		{
			/* First, validate the upload */
			try
			{
				static::validateUpload( $file, $allowedFileTypes, $maxFileSize );
				
				if ( $totalMaxSize !== NULL )
				{
					$currentTotal += $file['size'];
					if ( $currentTotal > ( $totalMaxSize * 1048576 ) )
					{
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('uploaderr_total_size', FALSE, array( 'sprintf' => array( \IPS\Output\Plugin\Filesize::humanReadableFilesize( $totalMaxSize * 1048576 ) ) ) ) );
					}
				}
				
				if ( $file['name'] === 'blob' )
				{
					switch ( $file['type'] )
					{
						case 'image/png':
							$file['name'] .= '.png';
							break;
						case 'image/jpeg':
							$file['name'] .= '.jpg';
							break;
						case 'image/gif':
							$file['name'] .= '.gif';
							break;
					}
				}

				/* Check that the filename doesn't have an invalid bytecode sequence */
				if( !preg_match( '//u', $file['name'] ) )
				{
					throw new \DomainException( 'upload_error', 4 );
				}

				if( $callback !== NULL )
				{
					$contents	= file_get_contents( $file['tmp_name'] );
					$contents	= $callback( $contents, $file['name'], $i );

					$fileObjects[ $i ] = static::create( $storageLocation, $file['name'], $contents, $container, FALSE, NULL, $obscure );
				}
				else
				{
					$fileObjects[ $i ] = static::create( $storageLocation, $file['name'], NULL, $container, FALSE, $file['tmp_name'], $obscure );
				}

				if( is_file( $file['tmp_name'] ) and file_exists( $file['tmp_name'] ) )
				{
					@unlink( $file['tmp_name'] );
				}
			}
			catch( \DomainException $e )
			{				
				if( is_file( $file['tmp_name'] ) and file_exists( $file['tmp_name'] ) )
				{
					@unlink( $file['tmp_name'] );
				}

				/* Are we ignoring upload errors? */
				if( $flags === \IPS\File::IGNORE_UPLOAD_ERRORS )
				{
					continue;
				}
				else
				{
					throw $e;
				}
			}
		}

		return $fileObjects;
	}

	/**
	 * Normalize the files array
	 *
	 * @param	string|NULL	$fieldName			Restrict collection of uploads to this upload field name, or pass NULL to collect any and all uploads
	 * @return	array
	 */
	public static function normalizeFilesArray( $fieldName=NULL )
	{
		$files			= array();

		foreach( $_FILES as $index => $file )
		{
			if( $fieldName !== NULL AND $fieldName != $index )
			{
				continue;
			}

			/* Do we have $_FILES['field'] = array( 'name' => ..., 'size' => ... ) */
			if( isset( $file['name'] ) AND !\is_array( $file['name'] ) )
			{
				$files[]	= $file;
			}
			/* Or do we have $_FILES['field'] = array( 'name' => array( 0 => ..., 1 => ... ), 'size' => array( 0 => ..., 1 => ... ) ) */
			else
			{
				if( \is_array( $file['name'] ) )
				{
					foreach( $file as $fieldName => $fields )
					{
						foreach( $fields as $fileIndex => $fileFieldValue )
						{
							$files[ $fileIndex ][ $fieldName ]	= $fileFieldValue;
						}
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Validate the uploaded file is valid
	 *
	 * @param	array 		$file	The uploaded file data
	 * @param	array|NULL	$allowedFileTypes	Array of allowed file extensions, or NULL to allow any extensions
	 * @param	int|NULL	$maxFileSize		The maximum file size in MB, or NULL to allow any size
	 * @return	void
	 * @throws	\DomainException
	 * @note	plupload inherently supports certain errors, so when appropriate we return the error code plupload expects
	 */
	public static function validateUpload( $file, $allowedFileTypes, $maxFileSize )
	{
		/* Was an error registered by PHP already? */
		if( $file['error'] )
		{
			$extraInfo	= NULL;

			switch( $file['error'] )
			{
				case 1:	//UPLOAD_ERR_INI_SIZE
				case 2:	//UPLOAD_ERR_FORM_SIZE
					$errorCode	= "-600";
					$extraInfo	= 2;
				break;

				case 3:	//UPLOAD_ERR_PARTIAL
				case 4: //UPLOAD_ERR_NO_FILE
					$errorCode	= "NO_FILE_UPLOADED";
					$extraInfo	= 1;
				break;

				case 6:	//UPLOAD_ERR_NO_TMP_DIR
				case 7:	//UPLOAD_ERR_CANT_WRITE
				case 8:	//UPLOAD_ERR_EXTENSION
					$errorCode	= "SERVER_CONFIGURATION";
					$extraInfo	= $file['error'];
				break;
			}

			throw new \DomainException( $errorCode, $extraInfo );
		}
		
		/* Do we have a path? */
		if ( empty( $file['tmp_name'] ) AND !isset( $file['_skipUploadCheck'] ) )
		{
			throw new \DomainException( 'upload_error', 1 );
		}

		/* Is this actually an uploaded file? */
		if( !is_uploaded_file( $file['tmp_name'] ) AND !isset( $file['_skipUploadCheck'] ) )
		{
			throw new \DomainException( 'upload_error', 1 );
		}
		
		/* Check size */
		if( $maxFileSize !== NULL )
		{
			$maxFileSize	= $maxFileSize * 1048576;
			if( $file['size'] > $maxFileSize OR ( !isset( $file['_skipUploadCheck'] ) AND filesize( $file['tmp_name'] ) > $maxFileSize ) )
			{
				throw new \DomainException( '-600', 2 );
			}
		}

		/* Check allowed types */
		$ext = mb_substr( $file['name'], mb_strrpos( $file['name'], '.' ) + 1 );
		if( $allowedFileTypes !== NULL and \is_array( $allowedFileTypes ) and !empty( $allowedFileTypes ) )
		{
			if( !\in_array( mb_strtolower( $ext ), array_map( 'mb_strtolower', $allowedFileTypes ) ) )
			{
				throw new \DomainException( '-601', 3 );
			}
		}

		/* If it's got an image extension, check it's actually a valid image */
		if ( \in_array( $ext, \IPS\Image::supportedExtensions() ) AND !isset( $file['_skipUploadCheck'] ) )
		{
			$imageAttributes = getimagesize( $file['tmp_name'] );
			if( !\in_array( $imageAttributes[2], array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ) ) )
			{
				throw new \DomainException( 'upload_error', 4 );
			}
		}

		return;
	}

	/**
	 * Load File
	 *
	 * @param	string					$storageExtension	Storage extension
	 * @param	string|\IPS\Http|Url	$url				URL to file
	 * @param 	int						$cachedFilesize		Pre-cache filesize (bytes)
	 * @return	\IPS\File
	 * @throws	\OutOfRangeException
	 */
	public static function get( $storageExtension, $url, int $cachedFilesize=NULL )
	{
		if( mb_strpos( rawurldecode( $url ), '../' ) !== FALSE OR mb_strpos( rawurldecode( $url ), '..\\' ) !== FALSE )
		{
			throw new \OutOfRangeException( 'INVALID_PATH' );
		}

		$class = static::getClass( $storageExtension, TRUE );
		$class->storageExtension = $storageExtension;
		$class->url = $url;
		$class->_cachedFilesize = $cachedFilesize;
		$class->load();
		return $class;
	}

	/**
	 * Remove orphaned files based on a given storage configuration
	 *
	 * @param	array		$configurationId	Storage configuration ID
	 * @param	int			$fileIndex			The file offset to start at in a listing
	 * @return	array
	 */
	public static function orphanedFiles( $configurationId, $fileIndex )
	{
		return static::getClass( $configurationId )->removeOrphanedFiles( $fileIndex, \IPS\Application::allExtensions( 'core', 'FileStorage', FALSE ) );
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
		$needsMove = FALSE;
		if ( \count( array_merge( array_diff( $configuration, $oldConfiguration ), array_diff( $oldConfiguration, $configuration ) ) ) )
		{
			$needsMove = TRUE;
		}

		if ( ! $needsMove )
		{
			foreach( $configuration as $k => $v )
			{
				$pass = TRUE;
				if ( ! isset( $oldConfiguration[ $k ] ) or $v != $oldConfiguration[ $k ] )
				{
					$pass = FALSE;
				}
			}

			$needsMove = ( $pass ) ? FALSE : TRUE;
		}

		return $needsMove;
	}
		
	/**
	 * Is this a fully qualified URL?
	 *
	 * @param	string	$url		URL to examine
	 * @return	boolean
	 */
	public static function isFullyQualifiedUrl( $url )
	{
		return ( mb_substr( $url, 0, 4 ) === 'http' OR mb_substr( $url, 0, 2 ) === '//' );
	}
	
	/**
	 * This is primarily a utility function to convert old style full URLs (http://site.com/uploads/monthly_04_2015/file.txt) into the new style (monthly_04_2015/file.txt)
	 *
	 * @deprecated 4.1.0
	 * @param	string	$url	The URL to repair
	 * @return string|boolean	URL (string) if URL needed repairing, or FALSE if it did not.
	 */
	public static function repairUrl( $url )
	{
		if ( static::isFullyQualifiedUrl( $url ) )
		{
			/* Loop through all configurations and remove the baseUrl() if it matches */
			static::getStore();
			
			foreach( static::$storageConfigurations as $id => $data  )
			{
				$class      = static::getClass( $data['id'] );
				
				$urlRelative  = preg_replace( '#^http(s)?://#', '//', $url );
				$baseRelative = preg_replace( '#^http(s)?://#', '//', $class->baseUrl() );
				
				if ( mb_strpos( $urlRelative, $baseRelative ) === 0 )
				{
					return ltrim( str_replace( $baseRelative, '', $urlRelative ), '/' );
				}
			}
		}
		
		return FALSE;
	}
	
	/**
	 * @brief	Storage Configuration
	 */
	public $configuration = array();
	
	/**
	 * @brief	Storage Configuration ID
	 */
	public $configurationId;
	
	/**
	 * @brief	Storage Extension (core_Theme, etc)
	 */
	public $storageExtension;
	
	/**
	 * @brief	Original Filename
	 */
	public $originalFilename;
	
	/**
	 * @brief	Filename
	 */
	public $filename;
	
	/**
	 * @brief	Container
	 */
	public $container;
	
	/**
	 * @brief	Cached contents
	 */
	protected $contents;

	/**
	 * @brief	URL
	 */
	public $url;
	
	/**
	 * @brief	Temp ID
	 */
	public $tempId;
	
	/**
	 * @brief	A flag used immediately after the file is uploaded to indicate if it has been flagged by the image scanner
	 */
	public $requiresModeration = NULL;
	
	/**
	 * Constructor
	 *
	 * @param	array	$configuration		Storage configuration
	 * @param 	int		$cachedFilesize		Pre-cached filesize
	 * @return	void
	 */
	public function __construct( $configuration, int $cachedFilesize=null )
	{
		$this->configuration = $configuration;
		$this->_cachedFilesize = $cachedFilesize;
	}
	
	/**
	 * Return the base URL
	 *
	 * @return string
	 */
	public function baseUrl()
	{
		return NULL;
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
		if( $start AND $length )
		{
			$contents	= substr( $this->contents(), $start, $length );
		}
		else
		{
			$contents	= $this->contents();
		}

		if( $throttle === NULL )
		{
			print $contents;
		}
		else
		{
			$pointer	= 0;
			$contentsLength = \strlen( $contents );
			while( $pointer < $contentsLength )
			{
				print \substr( $contents, $pointer, $throttle );
				$pointer	+= $throttle;

				sleep( 1 );
			}
		}
	}

	/**
	 * Send file with byte-offset supported.  Requires a path to a locally stored file. This method can be more efficient than
	 * getting the file contents and printing as the file contents do not need to be stored in memory, however files will need to
	 * be written to disk and removed, and the method is only useful if there is a way to retrieve a file without storing the
	 * contents in memory already.
	 *
	 * @param	string	$file	Path to file
	 * @param	int|null	$start		Start point to print from (for ranges)
	 * @param	int|null	$length		Length to print to (for ranges)
	 * @param	int|null	$throttle	Throttle speed
	 * @return	void
	 */
	protected function sendFile( $file, $start=NULL, $length=NULL, $throttle=NULL )
	{
		/* Turn off output buffering if it is on */
		while( ob_get_level() > 0 )
		{
			ob_end_clean();
		}

		if( $throttle === NULL AND $start === NULL AND \function_exists('readfile') )
		{
			readfile( $file );
		}
		else
		{
			if( $fh = fopen( $file, 'rb' ) )
			{
				$read	= ( $throttle !== NULL ) ? $throttle : 4096;

				if( $start !== NULL AND $length )
				{
					fseek( $fh, $start );

					while( $length AND !feof( $fh ) )
					{
						if( $read > $length )
						{
							$read	= $length;
						}

						echo fread( $fh, $read );
						flush();

						$length -= $read;

						if( $throttle )
						{
							sleep( 1 );
						}
					}
				}
				else
				{
					while( ! feof( $fh ) )
					{
						echo fread( $fh, $read );
						flush();

						if( $throttle )
						{
							sleep( 1 );
						}
					}
				}

				fclose( $fh );
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
			$this->contents = (string) \IPS\Http\Url::external( $this->url )->request()->get();
		}
		return $this->contents;
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

		try
		{
			$this->_cachedFilesize = \strlen( $this->contents() );
		}
		catch( \RuntimeException $ex )
		{
			$this->_cachedFilesize = FALSE;
		}

		return $this->_cachedFilesize;
	}

	/**
	 * Set the file
	 *
	 * @param	string	$filepath	The path to the file on disk
	 * @return	void
	 */
	public function setFile( $filepath )
	{
		$this->contents	= file_get_contents( $filepath );
	}

	/**
	 * Set filename
	 *
	 * @param	string	$filename	The filename
	 * @param	bool	$obscure	Controls if an md5 hash should be added to the filename
	 * @return	void
	 */
	public function setFilename( $filename, $obscure=TRUE )
	{
		$this->originalFilename = $filename;
		
		/* Make sure name doesn't have anything that may break URLs */
		if ( preg_match( '#[^a-zA-Z0-9!\-_\.\*\(\)\@]#', $filename ) )
		{
			$filename = preg_replace( '#[^a-zA-Z0-9!\-_\.\*\(\)\@]#', '', $filename );
			if( !$obscure OR \strlen( pathinfo( $filename, PATHINFO_FILENAME ) ) === 0 )
			{
				$filename = mt_rand() . '_' . $filename;
			}
		}
		
		if ( $obscure )
		{
			$filename = static::obscureFilename( $filename );
		}
		else
		{
			$filename = $filename;
		}

		/* Most operating systems allow a max filename length of 255 bytes, so we should make sure we don't go over that */
		if( \strlen( $filename ) > 200 )
		{
			/* If the filename is over 200 chars, grab the first 100 and the last 100 and concatenate with a dash - this should help ensure we retain the most useful info */
			$filename = mb_substr( $filename, 0, 100 ) . '-' . mb_substr( $filename, -100 );
		}

		$this->filename = $filename;
	}
	
	/**
	 * Obscure Filename
	 *
	 * @param	string	$filename	The filename
	 * @return	string
	 */
	protected function obscureFilename( $filename )
	{
		$ext  = mb_substr( $filename, ( mb_strrpos( $filename, '.' ) + 1 ) );
		$safe = \in_array( mb_strtolower( $ext ), static::$safeFileExtensions );

		if ( ! $safe )
		{
			$filename = mb_substr( $filename, 0, ( mb_strrpos( $filename, '.' ) ) ) . '_' . $ext;
		}
		
		while( preg_match( '#\.(?!(' . implode( '|', static::$safeFileExtensions ) . '))([a-z0-9]{2,4})(\.|$)#i', $filename, $matches ) )
		{
			$filename = str_replace( '.' . $matches[2], '_' . $matches[2], $filename );
		}

		return str_replace( array( ' ', '#' ), '_', $filename ) . '.' . \IPS\Login::generateRandomString( 32 ) . ( ( $safe ) ? '.' . $ext : '' );
	}

	/**
	 * "Un"-obscure the filename
	 *
	 * @param	string	$filename	The filename
	 * @return	string
	 */
	protected function unObscureFilename( $filename )
	{
		$ext = mb_substr( $filename, ( mb_strrpos( $filename, '.' ) + 1 ) );
		if ( mb_strlen( $ext ) == 32 )
		{
			preg_match( '#(.*)_([A-Z0-9\-]{2,14})\.([A-Z0-9]{32})#i', $filename, $matches );

			if ( isset( $matches[1] ) and isset( $matches[2] ) )
			{
				return $matches[1] . '.' . $matches[2];
			}
			else
			{
				/* Fix foo.pdf.hash */
				preg_match( '#(.*)\.([A-Z0-9\-]{2,14})\.([A-Z0-9]{32})#i', $filename, $matches );
		
				if ( isset( $matches[1] ) and isset( $matches[2] ) )
				{
					return $matches[1] . '.' . $matches[2];
				}
			}
		}
		
		if( preg_match( "/\.([a-zA-Z0-9]{32})\." . preg_quote( $ext ) . "$/", $filename ) )
		{
			return mb_substr( $filename, 0, mb_strlen( $filename ) - ( 34 + mb_strlen( $ext ) ) );
		}
		else
		{
			return $filename;
		}
	}

	/**
	 * Load File Data
	 *
	 * @return	void
	 */
	public function load()
	{
		$url = (string) $this->url;
		
		if ( static::isFullyQualifiedUrl( $url ) )
		{
			$url = mb_substr( $url, mb_strlen( $this->baseUrl() ) + 1 );
		}
	
		$exploded = explode( '/', $url );
		$this->filename = array_pop( $exploded );		
		$this->originalFilename = $this->unObscureFilename( $this->filename );
		$this->url = \IPS\Http\Url::createFromString( $this->fullyQualifiedUrl( $this->url ), FALSE, TRUE );

		/* Upon upgrade we don't rename every file, so we need to account for this */
		if( mb_strpos( $this->originalFilename, '.' ) === FALSE )
		{
			$this->originalFilename	= $this->filename;
		}

		$this->container = implode( '/', $exploded );
	}
	
	/**
	 * Replace file contents
	 *
	 * @param	string	$contents	New contents
	 * @return	void
	 */
	public function replace( $contents )
	{
		$this->contents = $contents;
		$this->save();
	}
	
	/**
	 * Save File
	 *
	 * @return	void
	 */
	abstract public function save();
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	abstract public function delete();
	
	/**
	 * Delete Container
	 *
	 * @param	string	$container	Key
	 * @return	void
	 */
	abstract public function deleteContainer( $container );
	
	/**
	 * Get the relative URL
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return $this->container ? ( $this->container . '/' . $this->filename ) : $this->filename;
	}
	
	/**
	 * The configuration settings have been updated
	 *
	 * @return void
	 */
	public function settingsUpdated()
	{
		$settings = json_decode( \IPS\Settings::i()->upload_settings, TRUE );
		
		foreach( $settings as $method => $id )
		{
			if ( $id == $this->configurationId )
			{
				$exploded  = explode( '_', str_replace( 'filestorage__', '', $method ) );		
				$classname = "IPS\\{$exploded[0]}\\extensions\\core\\FileStorage\\{$exploded[1]}";
				
				if ( method_exists( $classname, 'settingsUpdated' ) )
				{
					$classname::settingsUpdated( $this->configuration );
				}
			}
		}
		
		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

		/* Clear datastore */
		\IPS\Data\Store::i()->clearAll();
	}
	
	/**
	 * Return the fully qualified URL
	 *
	 * @param	string	$url	URL
	 * @return string
	 */
	public function fullyQualifiedUrl( $url )
	{
		if ( mb_substr( $url, 0, 4 ) !== 'http' AND mb_substr( $url, 0, 2 ) !== '//' )
		{
			$url = $this->baseUrl() . '/' . $this->encodeFileUrl( $url );
		}
		
		return $url;
	}
	
	/**
	 * Encode the file name for the fully qualified URL
	 *
	 * @param	string	$filename	Filename in the URL
	 * @return string
	 */
	public function encodeFileUrl( $filename )
	{
		return \IPS\Http\Url::encodeComponent( \IPS\Http\Url::COMPONENT_PATH, $filename );
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
		/* Copy file */
		try
		{
			$class = $this->copy( $storageConfiguration );
		}
		catch( \Exception $e )
		{
			throw new \RuntimeException( $e->getMessage(), $e->getCode() );
		}

		try
		{
			if( $flags === \IPS\File::MOVE_DELETE_NOW )
			{
				/* Delete this one */
				$this->delete();	
			}
			else
			{
				/* Will be deleted later */
				$this->log( "file_moved", 'move', array(
					'method'            => static::$storageConfigurations[ $storageConfiguration ]['method'],
					'configuration_id'  => $storageConfiguration,
					'container'         => $class->container,
					'filename'          => $class->filename
				), 'move' );
			}
		}
		catch( \Exception $e )
		{
			$this->log( $e->getMessage(), 'delete' );

			throw new \RuntimeException( $e->getMessage(), $e->getCode() );
		}

		/* Return */
		return $class;
	}
	
	/**
	 * Create a duplicate of this file in the same storage location but with a new filename
	 *
	 * @return	\IPS\File
	 * @throws  \RuntimeException
	 */
	public function duplicate()
	{
		$copyFiles = static::$copyFiles;
		static::$copyFiles = TRUE;
		$return = static::create( $this->storageExtension, $this->originalFilename, $this->contents(), $this->container, $this instanceof \IPS\File\FileSystem ? ( $this->configuration['dir'] . '/' . ( $this->container ? "{$this->container}/" : '' ) . $this->filename ) : NULL );
		static::$copyFiles = $copyFiles;
		return $return;
	}

	/**
	 * Copy a file to a different storage location
	 *
	 * @param	int			       $storageConfiguration	New storage configuration ID
	 * @return	\IPS\File
	 * @throws  \RuntimeException
	 */
	public function copy( $storageConfiguration )
	{
		/* Load class */
		static::getStore();
		$handlers	= static::storageHandlers( static::$storageConfigurations[ $storageConfiguration ] );
		$classname	= $handlers[ static::$storageConfigurations[ $storageConfiguration ]['method'] ];
		$class		= new $classname( json_decode( static::$storageConfigurations[ $storageConfiguration ]['configuration'], TRUE ) );

		/* Store it there */
		if ( $this->container !== NULL )
		{
			$class->container = trim( $this->container, '/' );
		}
		
		/* We want to keep the same filename so we don't have to update the database */
		$class->originalFilename = $this->unObscureFilename( $this->filename );
		$class->filename = $this->filename;
		
		try
		{
			$class->contents = $this->contents();
		}
		catch( \Exception $e )
		{
			$this->log( $e->getMessage(), 'copy', array(
				'method'            => static::$storageConfigurations[ $storageConfiguration ]['method'],
				'configuration_id'  => $storageConfiguration
			) );

			throw new \RuntimeException( $e->getMessage(), $e->getCode() );
		}

		try
		{
			$class->save();
		}
		catch( \Exception $e )
		{
			$this->log( $e->getMessage(), 'copy', array(
				'method'            => static::$storageConfigurations[ $storageConfiguration ]['method'],
				'configuration_id'  => $storageConfiguration
			) );

			throw new \RuntimeException( $e->getMessage(), $e->getCode() );
		}

		/* Return */
		return $class;
	}
	
	/**
	 * @brief Attachment thumbnail URL
	 */
	public $attachmentThumbnailUrl	= NULL;
	
	/**
	 * @brief	Security Key
	 */
	public $securityKey = NULL;

	/**
	 * Make into an attachment
	 *
	 * @param	string				$postKey				Post key
	 * @param	\IPS\Member|NULL		$member					Member who uploaded the attachment
	 * @param	bool				$requiresModeration		If this attachment requires moderation
	 * @param	array|NULL				$labels		Image scanner labels
	 * @return	array
	 * @throws	\DomainException
	 */
	public function makeAttachment( $postKey, \IPS\Member $member = NULL, $requiresModeration = FALSE, $labels=NULL )
	{
		$ext = mb_substr( $this->originalFilename, mb_strrpos($this->originalFilename, '.') + 1 );

		$memberId = ( $member and $member->member_id ) ? $member->member_id : 0;
		
		$this->securityKey = \IPS\Login::generateRandomString();

		$data = array(
			'attach_ext'				=> $ext,
			'attach_file'				=> $this->originalFilename,
			'attach_location'			=> (string) $this,
			'attach_thumb_location'		=> '',
			'attach_thumb_width'		=> 0,
			'attach_thumb_height'		=> 0,
			'attach_is_image'			=> 0,
			'attach_hits'				=> 0,
			'attach_date'				=> time(),
			'attach_post_key'			=> $postKey,
			'attach_member_id'			=> $memberId,
			'attach_filesize'			=> $this->filesize(),
			'attach_img_width'			=> 0,
			'attach_img_height'			=> 0,
			'attach_is_archived'		=> FALSE,
			'attach_moderation_status'	=> $requiresModeration ? 'pending' : 'skipped',
			'attach_security_key'	=> $this->securityKey
		);
		
		/* If this is an image, grab the appropriate data */
		if ( $this->isImage() )
		{
			try
			{
				$thumbDims	= \IPS\Settings::i()->attachment_image_size ? explode( 'x', \IPS\Settings::i()->attachment_image_size ) : [ 0, 0 ];
				$dimensions	= $this->getImageDimensions();
				
				$data['attach_is_image'] = TRUE;
				$data['attach_img_width'] = $dimensions[0];
				$data['attach_img_height'] = $dimensions[1];
				
				if ( ( isset( $thumbDims[0] ) and $thumbDims[0] and $dimensions[0] > $thumbDims[0] ) or ( isset( $thumbDims[1] ) and $thumbDims[1] and $dimensions[1] > $thumbDims[1] ) )
				{
					$data['attach_thumb_location']	= (string) $this->thumbnail( 'core_Attachment', $thumbDims[0], $thumbDims[1] );
					$data['attach_thumb_width']		= static::$thumbnailDimensions[0];
					$data['attach_thumb_height']	= static::$thumbnailDimensions[1];

					$this->attachmentThumbnailUrl	= $data['attach_thumb_location'];
				}

				$data['attach_labels'] = $labels;
			}
			
			catch ( \InvalidArgumentException $e ) { }
		}
		
		return array_merge( array( 'attach_id' => \IPS\Db::i()->insert( 'core_attachments', $data ) ), $data );
	}

	/**
	 * Determine if the file is an image
	 *
	 * @return	bool
	 */
	public function isImage()
	{
		$ext = mb_substr( $this->originalFilename, mb_strrpos( $this->originalFilename, '.' ) + 1 );

		if ( \in_array( mb_strtolower( $ext ), \IPS\Image::supportedExtensions() ) )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Determine if the file is a video
	 *
	 * @return	bool
	 */
	public function isVideo()
	{
		$ext = mb_substr( $this->originalFilename, mb_strrpos( $this->originalFilename, '.' ) + 1 );

		if ( \in_array( mb_strtolower( $ext ), \IPS\File::$videoExtensions ) )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Determine if the file is a video
	 *
	 * @return	bool
	 */
	public function isAudio()
	{
		$ext = mb_substr( $this->originalFilename, mb_strrpos( $this->originalFilename, '.' ) + 1 );

		if ( \in_array( mb_strtolower( $ext ), \IPS\File::$audioExtensions ) )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Get media type ("file", "image" or "video")
	 *
	 * @return	bool
	 */
	public function mediaType()
	{
		if ( $this->isVideo() )
		{
			return 'video';
		}
		elseif ( $this->isImage() )
		{
			return 'image';
		}
		elseif ( $this->isAudio() )
		{
			return 'audio';
		}
		else
		{
			return 'file';
		}
	}

	/**
	 * Generate a temporary download URL the user can be redirected to
	 *
	 * @param	$validForSeconds	int	The number of seconds the link should be valid for
	 * @return	\IPS\Http\Url
	 */
	public function generateTemporaryDownloadUrl( $validForSeconds = 1200 )
	{
		return NULL;
	}

	/**
	 * If the file is an image, get the dimensions
	 *
	 * @return	array
	 * @throws	\DomainException
	 * @throws	\InvalidArgumentException
	 * @throws	\RuntimeException
	 */
	public function getImageDimensions()
	{
		if( !$this->isImage() )
		{
			throw new \DomainException;
		}

		$image     = \IPS\Image::create( $this->contents() );

		return array( $image->width, $image->height );
	}
	
	/**
	 * Claim Attachments and clear autosave content
	 *
	 * @param	string		$autoSaveKey	Auto-save key
	 * @param	int|NULL	$id1			ID 1	
	 * @param	int|NULL	$id2			ID 2		
	 * @param	int|NULL	$id3			ID 3		
	 * @param	bool		$translatable	Are we claiming from a Translatable field?
	 * @return	void
	 * @note	If you call this, it is your responsibility to call unclaimAttachments if/when the thing is deleted
	 */
	public static function claimAttachments( $autoSaveKey, $id1=NULL, $id2=NULL, $id3=NULL, $translatable=FALSE )
	{
		if ( $translatable )
		{
			foreach ( \IPS\Lang::languages() as $lang )
			{
				\IPS\Db::i()->update( 'core_attachments_map', array(
					'id1'	=> $id1,
					'id2'	=> $id2,
					'id3'	=> $id3,
					'temp'	=> NULL
				), array( 'temp=?', md5( $autoSaveKey . $lang->id ) ) );
				
				\IPS\Db::i()->update( 'core_attachments', array( 'attach_post_key' => '' ), array( 'attach_post_key=?', md5( $autoSaveKey . $lang->id . ':' . session_id() ) ) );
				
				
				\IPS\Request::i()->setClearAutosaveCookie( $autoSaveKey . $lang->id );
			}
		}
		else
		{
			\IPS\Db::i()->update( 'core_attachments_map', array(
				'id1'	=> $id1,
				'id2'	=> $id2,
				'id3'	=> $id3,
				'temp'	=> NULL
			), array( 'temp=?', md5( $autoSaveKey ) ) );
			
			\IPS\Db::i()->update( 'core_attachments', array( 'attach_post_key' => '' ), array( 'attach_post_key=?', md5( $autoSaveKey . ':' . session_id() ) ) );
			
			\IPS\Request::i()->setClearAutosaveCookie( $autoSaveKey );
		}
	}
	
	/**
	 * Unclaim Attachments
	 *
	 * @param	string		$locationKey	Location key (e.g. "forums_Forums")
	 * @param	int|NULL	$id1			ID 1	
	 * @param	int|NULL	$id2			ID 2		
	 * @param	int|NULL	$id3			ID 3		
	 * @return	void
	 * @note	If any of the IDs are NULL, this will unclaim any attachments with any value. This can be useful to unclaim all attachments for all posts in a topic, but caution must be used.
	 */
	public static function unclaimAttachments( $locationKey, $id1=NULL, $id2=NULL, $id3=NULL )
	{
		/* Delete from core_attachments_map */
		$where = array( array( 'location_key=?', $locationKey ) );
		foreach ( range( 1, 3 ) as $i )
		{
			$v = "id{$i}";
			if ( $$v !== NULL )
			{
				$where[] = array( "{$v}=?", $$v );
			}
		}
		\IPS\Db::i()->delete( 'core_attachments_map', $where );
	}

	/**
	 * @brief This can be used to force a thumbnail name
	 */
	public $thumbnailName = NULL;

	/**
	 * @brief This can be used to force a thumbnail container
	 */
	public $thumbnailContainer = NULL;

	/**
	 * Make a thumbnail of the file - copies file, resizes and returns new file object
	 *
	 * @param	string	$storageExtension	Storage extension to use for generated thumbnail
	 * @param	int		$maxWidth			Max width (in pixels) - NULL to use \IPS\THUMBNAIL_SIZE
	 * @param	int		$maxHeight			Max height (in pixels) - NULL to use \IPS\THUMBNAIL_SIZE
	 * @param	bool	$cropToSquare		If TRUE, will first crop to a square
	 * @return	\IPS\File
	 */
	public function thumbnail( $storageExtension, $maxWidth=NULL, $maxHeight=NULL, $cropToSquare=FALSE )
	{	
		/* Work out size */	
		$defaultSize = explode( 'x', \IPS\THUMBNAIL_SIZE );
		$maxWidth    = $maxWidth ?: $defaultSize[0];
		$maxHeight   = $maxHeight ?: $defaultSize[1];

		/* Create an \IPS\Image object */
		$image = \IPS\Image::create( $this->contents() );
		
		/* Crop it */
		if ( $cropToSquare and $image->width != $image->height )
		{
			$cropProperty = ( $image->width > $image->height ) ? 'height' : 'width';
			$image->crop( $image->$cropProperty, $image->$cropProperty );
		}
		
		/* Resize it */
		$image->resizeToMax( $maxWidth, $maxHeight );
		
		static::$thumbnailDimensions = array( $image->width, $image->height );
		
		/* What are we calling this? */		
		$thumbnailName = $this->thumbnailName ?: mb_substr( $this->originalFilename, 0, mb_strrpos( $this->originalFilename, '.' ) ) . '.thumb' . mb_substr( $this->originalFilename, mb_strrpos( $this->originalFilename, '.' ) );

		/* Create and return */
		return \IPS\File::create( $storageExtension, $thumbnailName, (string) $image, $this->thumbnailContainer, FALSE, NULL, $this->thumbnailName ? FALSE : TRUE );
	}

	/**
	 * Log an error
	 *
	 * @param      string   $message    Message to log
	 * @param      string   $action     Action that triggered the error (copy/move/delete/save)
	 * @param      mixed    $data       Extra data to save
	 * @param      string   $type       Type of log (error/log/copy/move)
	 * @return     void
	 */
	protected function log( $message, $action, $data=NULL, $type='error' )
	{
		if ( $this->configurationId === NULL )
		{
			return;
		}
		
		\IPS\Db::i()->insert( 'core_file_logs', array (
          'log_action'           => $action,
          'log_type'             => $type,
          'log_configuration_id' => $this->configurationId,
          'log_method'           => static::$storageConfigurations[ $this->configurationId ]['method'],
          'log_filename'         => $this->filename,
          'log_url'              => $this->url,
          'log_container'        => $this->container,
          'log_msg'              => $message,
          'log_date'             => time(),
          'log_data'             => \is_array( $data ) ? json_encode( $data ) : NULL
        ) );
	}
	
	/**
	 * Log a found orphaned file
	 *
	 * @param      string   $url	URL or container/filename.ext
	 */
	protected function logOrphanedFile( $url )
	{
		if ( static::isFullyQualifiedUrl( $url ) )
		{
			$url = mb_substr( $url, mb_strlen( $this->baseUrl() ) + 1 );
		}
	
		$exploded  = explode( '/', $url );
		$filename  = array_pop( $exploded );
		$container = implode( '/', $exploded );
		$method    = static::$storageConfigurations[ $this->configurationId ]['method'];
		
		\IPS\Db::i()->delete( 'core_file_logs', array(
			'log_action=? AND log_type=? AND log_configuration_id=? AND log_method=? AND log_url=?',
			'orphaned',
			'orphaned',
			$this->configurationId,
			$method,
			$url
		) );
		
		\IPS\Db::i()->insert( 'core_file_logs', array (
          'log_action'           => 'orphaned',
          'log_type'             => 'orphaned',
          'log_configuration_id' => $this->configurationId,
          'log_method'           => $method,
          'log_filename'         => $filename,
          'log_url'              => $url,
          'log_container'        => $container,
          'log_msg'              => 'orphan_found',
          'log_date'             => time(),
          'log_data'             => NULL
        ) );
	}

	/**
	 * Check a file for XSS content inside it
	 *
	 * @param	string	$data	File data
	 * @return	bool
	 * @note	Thanks to Nicolas Grekas from comments at www.splitbrain.org for helping to identify all vulnerable HTML tags
	 */
	public static function checkXssInFile( $data )
	{
		/* We only need to check the first 1kb of the file...some programs will use more, but this is the most common */
		$firstBytes	= \substr( $data, 0, 1024 );

		/* @see https://mimesniff.spec.whatwg.org/#identifying-a-resource-with-an-unknown-mime-type */
		if( preg_match( '#(<\!DOCTYPE\s+HTML|<script|<html|<head|<iframe|<h1|<div|<font|<table|<title|<style|<body|<pre|<table|<br|<a\s+href|<img|<plaintext|<cross\-domain\-policy|<\!\-\-|<\?xml)(\s|>)#si', $firstBytes, $matches ) )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Get mime type based on file extension
	 *
	 * @param	string	$filename	Filename (with extension)
	 * @return	string
	 */
	public static function getMimeType( $filename )
	{
		$extension	= mb_strtolower( mb_substr( $filename, mb_strrpos( $filename, '.' ) + 1 ) );
		
		if ( array_key_exists( $extension, static::$mimeTypes ) )
		{
			return static::$mimeTypes[ $extension ];
		}
		
		return 'application/x-unknown'; // This, slightly unusual, type is needed to stop some browsers adding random extensions
	}

	/**
	 * Return a value pulled from php.ini in bytes
	 *
	 * @note	This function is intended to normalize values for things like post_max_size which could be -1, 0, 8383900, 8M, 1G, etc.
	 * @param	string|int	$size	The size we want to convert to bytes (see note)
	 * @return	float
	 */
	public static function returnBytes( $size )
	{
		$size	= trim( $size );

		if( !$size OR $size == -1 )
		{
			return 0;
		}

		/* Get the last character, which may be 'm' or may be a number */
		$last	= mb_strtolower( $size[ \strlen( $size ) - 1 ] );
		
		/* Convert $size to a number */
		$size = \intval( preg_replace( '/[^0-9]/', '', $size ) );

		/* Adjust value as necessary - note that we do not break intentionally */
		switch( $last )
		{
			case 'g':
				$size *= 1024;
			case 'm':
				$size *= 1024;
			case 'k':
				$size *= 1024;
		}

		return (float) $size;
	}

	/**
	 * Returns an icon to represent the file, based on its extension
	 * 
	 * @param 	string 	filename 	The full filename
	 * @return 	string
	 */
	public static function getIconFromName( $filename )
	{
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );

		if( isset( static::$fileIconMap[ $ext ] ) )
		{
			return static::$fileIconMap[ $ext ];
		}

		return 'file-o';
	}

	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	string	name	The filename
	 * @apiresponse	string	url		URL to where file is stored
	 * @apiresponse	int		size	Filesize in bytes
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'name'	=> $this->originalFilename,
			'url'	=> (string) $this->url,
			'size'	=> $this->filesize()
		);
	}
	
	/**
	 * @brief 	Maps file extensions to fontawesome icons
	 */
	public static $fileIconMap = array(
		'txt' => 'file-text-o',
		'rtf' => 'file-text-o',
		'csv' => 'file-text-o',
		'pdf' => 'file-pdf-o',
		'doc' => 'file-word-o',
		'docx' => 'file-word-o',
		'xls' => 'file-excel-o',
		'xlsx' => 'file-excel-o',
		'xlsm' => 'file-excel-o',
		'zip' => 'file-archive-o',
		'tar' => 'file-archive-o',
		'gz' => 'file-archive-o',
		'ppt' => 'file-powerpoint-o',
		'pptx' => 'file-powerpoint-o',
		'ico' => 'file-image-o',
		'gif' => 'file-image-o',
		'jpeg' => 'file-image-o',
		'jpg' => 'file-image-o',
		'jpe' => 'file-image-o',
		'png' => 'file-image-o',
		'psd' => 'file-image-o',
		'aac' => 'file-audio-o',
		'mp3' => 'file-audio-o',
		'ogg' => 'file-audio-o',
		'ogv' => 'file-audio-o',
		'wav' => 'file-audio-o',
		'm4a' => 'file-audio-o',
		'avi' => 'file-video-o',
		'flv' => 'file-video-o',
		'mkv' => 'file-video-o',
		'mp4' => 'file-video-o',
		'mpg' => 'file-video-o',
		'mpeg' => 'file-video-o',
		'3gp' => 'file-video-o',
		'webm' => 'file-video-o',
		'wmv' => 'file-video-o',
		'avi' => 'file-video-o',
		'm4v' => 'file-video-o',
		'mov' => 'file-video-o',
		'css' => 'file-code-o',
		'html' => 'file-code-o',
		'js' => 'file-code-o',
		'xml' => 'file-code-o',
	);

	/* !Mime-Type Map */
	
	/**
	 * @brief	Mime-Type Map
	 */
	public static $mimeTypes = array(
        '3dml' => 'text/vnd.in3d.3dml',
        '3g2' => 'video/3gpp2',
        '3gp' => 'video/3gpp',
        '7z' => 'application/x-7z-compressed',
        'aab' => 'application/x-authorware-bin',
        'aac' => 'audio/x-aac',
        'aam' => 'application/x-authorware-map',
        'aas' => 'application/x-authorware-seg',
        'abw' => 'application/x-abiword',
        'ac' => 'application/pkix-attr-cert',
        'acc' => 'application/vnd.americandynamics.acc',
        'ace' => 'application/x-ace-compressed',
        'acu' => 'application/vnd.acucobol',
        'acutc' => 'application/vnd.acucorp',
        'adp' => 'audio/adpcm',
        'aep' => 'application/vnd.audiograph',
        'afm' => 'application/x-font-type1',
        'afp' => 'application/vnd.ibm.modcap',
        'ahead' => 'application/vnd.ahead.space',
        'ai' => 'application/postscript',
        'aif' => 'audio/x-aiff',
        'aifc' => 'audio/x-aiff',
        'aiff' => 'audio/x-aiff',
        'air' => 'application/vnd.adobe.air-application-installer-package+zip',
        'ait' => 'application/vnd.dvb.ait',
        'ami' => 'application/vnd.amiga.ami',
        'apk' => 'application/vnd.android.package-archive',
        'application' => 'application/x-ms-application',
        'apr' => 'application/vnd.lotus-approach',
        'asa' => 'text/plain',
        'asax' => 'application/octet-stream',
        'asc' => 'application/pgp-signature',
        'ascx' => 'text/plain',
        'asf' => 'video/x-ms-asf',
        'ashx' => 'text/plain',
        'asm' => 'text/x-asm',
        'asmx' => 'text/plain',
        'aso' => 'application/vnd.accpac.simply.aso',
        'asp' => 'text/plain',
        'aspx' => 'text/plain',
        'asx' => 'video/x-ms-asf',
        'atc' => 'application/vnd.acucorp',
        'atom' => 'application/atom+xml',
        'atomcat' => 'application/atomcat+xml',
        'atomsvc' => 'application/atomsvc+xml',
        'atx' => 'application/vnd.antix.game-component',
        'au' => 'audio/basic',
        'avi' => 'video/x-msvideo',
        'aw' => 'application/applixware',
        'axd' => 'text/plain',
        'azf' => 'application/vnd.airzip.filesecure.azf',
        'azs' => 'application/vnd.airzip.filesecure.azs',
        'azw' => 'application/vnd.amazon.ebook',
        'bat' => 'application/x-msdownload',
        'bcpio' => 'application/x-bcpio',
        'bdf' => 'application/x-font-bdf',
        'bdm' => 'application/vnd.syncml.dm+wbxml',
        'bed' => 'application/vnd.realvnc.bed',
        'bh2' => 'application/vnd.fujitsu.oasysprs',
        'bin' => 'application/octet-stream',
        'bmi' => 'application/vnd.bmi',
        'bmp' => 'image/bmp',
        'book' => 'application/vnd.framemaker',
        'box' => 'application/vnd.previewsystems.box',
        'boz' => 'application/x-bzip2',
        'bpk' => 'application/octet-stream',
        'btif' => 'image/prs.btif',
        'bz' => 'application/x-bzip',
        'bz2' => 'application/x-bzip2',
        'c' => 'text/x-c',
        'c11amc' => 'application/vnd.cluetrust.cartomobile-config',
        'c11amz' => 'application/vnd.cluetrust.cartomobile-config-pkg',
        'c4d' => 'application/vnd.clonk.c4group',
        'c4f' => 'application/vnd.clonk.c4group',
        'c4g' => 'application/vnd.clonk.c4group',
        'c4p' => 'application/vnd.clonk.c4group',
        'c4u' => 'application/vnd.clonk.c4group',
        'cab' => 'application/vnd.ms-cab-compressed',
        'car' => 'application/vnd.curl.car',
        'cat' => 'application/vnd.ms-pki.seccat',
        'cc' => 'text/x-c',
        'cct' => 'application/x-director',
        'ccxml' => 'application/ccxml+xml',
        'cdbcmsg' => 'application/vnd.contact.cmsg',
        'cdf' => 'application/x-netcdf',
        'cdkey' => 'application/vnd.mediastation.cdkey',
        'cdmia' => 'application/cdmi-capability',
        'cdmic' => 'application/cdmi-container',
        'cdmid' => 'application/cdmi-domain',
        'cdmio' => 'application/cdmi-object',
        'cdmiq' => 'application/cdmi-queue',
        'cdx' => 'chemical/x-cdx',
        'cdxml' => 'application/vnd.chemdraw+xml',
        'cdy' => 'application/vnd.cinderella',
        'cer' => 'application/pkix-cert',
        'cfc' => 'application/x-coldfusion',
        'cfm' => 'application/x-coldfusion',
        'cgm' => 'image/cgm',
        'chat' => 'application/x-chat',
        'chm' => 'application/vnd.ms-htmlhelp',
        'chrt' => 'application/vnd.kde.kchart',
        'cif' => 'chemical/x-cif',
        'cii' => 'application/vnd.anser-web-certificate-issue-initiation',
        'cil' => 'application/vnd.ms-artgalry',
        'cla' => 'application/vnd.claymore',
        'class' => 'application/java-vm',
        'clkk' => 'application/vnd.crick.clicker.keyboard',
        'clkp' => 'application/vnd.crick.clicker.palette',
        'clkt' => 'application/vnd.crick.clicker.template',
        'clkw' => 'application/vnd.crick.clicker.wordbank',
        'clkx' => 'application/vnd.crick.clicker',
        'clp' => 'application/x-msclip',
        'cmc' => 'application/vnd.cosmocaller',
        'cmdf' => 'chemical/x-cmdf',
        'cml' => 'chemical/x-cml',
        'cmp' => 'application/vnd.yellowriver-custom-menu',
        'cmx' => 'image/x-cmx',
        'cod' => 'application/vnd.rim.cod',
        'com' => 'application/x-msdownload',
        'conf' => 'text/plain',
        'cpio' => 'application/x-cpio',
        'cpp' => 'text/x-c',
        'cpt' => 'application/mac-compactpro',
        'crd' => 'application/x-mscardfile',
        'crl' => 'application/pkix-crl',
        'crt' => 'application/x-x509-ca-cert',
        'cryptonote' => 'application/vnd.rig.cryptonote',
        'cs' => 'text/plain',
        'csh' => 'application/x-csh',
        'csml' => 'chemical/x-csml',
        'csp' => 'application/vnd.commonspace',
        'css' => 'text/css',
        'cst' => 'application/x-director',
        'csv' => 'text/csv',
        'cu' => 'application/cu-seeme',
        'curl' => 'text/vnd.curl',
        'cww' => 'application/prs.cww',
        'cxt' => 'application/x-director',
        'cxx' => 'text/x-c',
        'dae' => 'model/vnd.collada+xml',
        'daf' => 'application/vnd.mobius.daf',
        'dataless' => 'application/vnd.fdsn.seed',
        'davmount' => 'application/davmount+xml',
        'dcr' => 'application/x-director',
        'dcurl' => 'text/vnd.curl.dcurl',
        'dd2' => 'application/vnd.oma.dd2+xml',
        'ddd' => 'application/vnd.fujixerox.ddd',
        'deb' => 'application/x-debian-package',
        'def' => 'text/plain',
        'deploy' => 'application/octet-stream',
        'der' => 'application/x-x509-ca-cert',
        'dfac' => 'application/vnd.dreamfactory',
        'dic' => 'text/x-c',
        'dir' => 'application/x-director',
        'dis' => 'application/vnd.mobius.dis',
        'dist' => 'application/octet-stream',
        'distz' => 'application/octet-stream',
        'djv' => 'image/vnd.djvu',
        'djvu' => 'image/vnd.djvu',
        'dll' => 'application/x-msdownload',
        'dmg' => 'application/octet-stream',
        'dms' => 'application/octet-stream',
        'dna' => 'application/vnd.dna',
        'doc' => 'application/msword',
        'docm' => 'application/vnd.ms-word.document.macroenabled.12',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'dot' => 'application/msword',
        'dotm' => 'application/vnd.ms-word.template.macroenabled.12',
        'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        'dp' => 'application/vnd.osgi.dp',
        'dpg' => 'application/vnd.dpgraph',
        'dra' => 'audio/vnd.dra',
        'dsc' => 'text/prs.lines.tag',
        'dssc' => 'application/dssc+der',
        'dtb' => 'application/x-dtbook+xml',
        'dtd' => 'application/xml-dtd',
        'dts' => 'audio/vnd.dts',
        'dtshd' => 'audio/vnd.dts.hd',
        'dump' => 'application/octet-stream',
        'dvi' => 'application/x-dvi',
        'dwf' => 'model/vnd.dwf',
        'dwg' => 'image/vnd.dwg',
        'dxf' => 'image/vnd.dxf',
        'dxp' => 'application/vnd.spotfire.dxp',
        'dxr' => 'application/x-director',
        'ecelp4800' => 'audio/vnd.nuera.ecelp4800',
        'ecelp7470' => 'audio/vnd.nuera.ecelp7470',
        'ecelp9600' => 'audio/vnd.nuera.ecelp9600',
        'ecma' => 'application/ecmascript',
        'edm' => 'application/vnd.novadigm.edm',
        'edx' => 'application/vnd.novadigm.edx',
        'efif' => 'application/vnd.picsel',
        'ei6' => 'application/vnd.pg.osasli',
        'elc' => 'application/octet-stream',
        'eml' => 'message/rfc822',
        'emma' => 'application/emma+xml',
        'eol' => 'audio/vnd.digital-winds',
        'eot' => 'application/vnd.ms-fontobject',
        'eps' => 'application/postscript',
        'epub' => 'application/epub+zip',
        'es3' => 'application/vnd.eszigno3+xml',
        'esf' => 'application/vnd.epson.esf',
        'et3' => 'application/vnd.eszigno3+xml',
        'etx' => 'text/x-setext',
        'exe' => 'application/x-msdownload',
        'exi' => 'application/exi',
        'ext' => 'application/vnd.novadigm.ext',
        'ez' => 'application/andrew-inset',
        'ez2' => 'application/vnd.ezpix-album',
        'ez3' => 'application/vnd.ezpix-package',
        'f' => 'text/x-fortran',
        'f4v' => 'video/x-f4v',
        'f77' => 'text/x-fortran',
        'f90' => 'text/x-fortran',
        'fbs' => 'image/vnd.fastbidsheet',
        'fcs' => 'application/vnd.isac.fcs',
        'fdf' => 'application/vnd.fdf',
        'fe_launch' => 'application/vnd.denovo.fcselayout-link',
        'fg5' => 'application/vnd.fujitsu.oasysgp',
        'fgd' => 'application/x-director',
        'fh' => 'image/x-freehand',
        'fh4' => 'image/x-freehand',
        'fh5' => 'image/x-freehand',
        'fh7' => 'image/x-freehand',
        'fhc' => 'image/x-freehand',
        'fig' => 'application/x-xfig',
        'fli' => 'video/x-fli',
        'flo' => 'application/vnd.micrografx.flo',
        'flv' => 'video/x-flv',
        'flw' => 'application/vnd.kde.kivio',
        'flx' => 'text/vnd.fmi.flexstor',
        'fly' => 'text/vnd.fly',
        'fm' => 'application/vnd.framemaker',
        'fnc' => 'application/vnd.frogans.fnc',
        'for' => 'text/x-fortran',
        'fpx' => 'image/vnd.fpx',
        'frame' => 'application/vnd.framemaker',
        'fsc' => 'application/vnd.fsc.weblaunch',
        'fst' => 'image/vnd.fst',
        'ftc' => 'application/vnd.fluxtime.clip',
        'fti' => 'application/vnd.anser-web-funds-transfer-initiation',
        'fvt' => 'video/vnd.fvt',
        'fxp' => 'application/vnd.adobe.fxp',
        'fxpl' => 'application/vnd.adobe.fxp',
        'fzs' => 'application/vnd.fuzzysheet',
        'g2w' => 'application/vnd.geoplan',
        'g3' => 'image/g3fax',
        'g3w' => 'application/vnd.geospace',
        'gac' => 'application/vnd.groove-account',
        'gdl' => 'model/vnd.gdl',
        'geo' => 'application/vnd.dynageo',
        'gex' => 'application/vnd.geometry-explorer',
        'ggb' => 'application/vnd.geogebra.file',
        'ggt' => 'application/vnd.geogebra.tool',
        'ghf' => 'application/vnd.groove-help',
        'gif' => 'image/gif',
        'gim' => 'application/vnd.groove-identity-message',
        'gmx' => 'application/vnd.gmx',
        'gnumeric' => 'application/x-gnumeric',
        'gph' => 'application/vnd.flographit',
        'gqf' => 'application/vnd.grafeq',
        'gqs' => 'application/vnd.grafeq',
        'gram' => 'application/srgs',
        'gre' => 'application/vnd.geometry-explorer',
        'grv' => 'application/vnd.groove-injector',
        'grxml' => 'application/srgs+xml',
        'gsf' => 'application/x-font-ghostscript',
        'gtar' => 'application/x-gtar',
        'gtm' => 'application/vnd.groove-tool-message',
        'gtw' => 'model/vnd.gtw',
        'gv' => 'text/vnd.graphviz',
        'gxt' => 'application/vnd.geonext',
        'h' => 'text/x-c',
        'h261' => 'video/h261',
        'h263' => 'video/h263',
        'h264' => 'video/h264',
        'hal' => 'application/vnd.hal+xml',
        'hbci' => 'application/vnd.hbci',
        'hdf' => 'application/x-hdf',
        'hh' => 'text/x-c',
        'hlp' => 'application/winhlp',
        'hpgl' => 'application/vnd.hp-hpgl',
        'hpid' => 'application/vnd.hp-hpid',
        'hps' => 'application/vnd.hp-hps',
        'hqx' => 'application/mac-binhex40',
        'hta' => 'application/octet-stream',
        'htc' => 'text/html',
        'htke' => 'application/vnd.kenameaapp',
        'htm' => 'text/html',
        'html' => 'text/html',
        'hvd' => 'application/vnd.yamaha.hv-dic',
        'hvp' => 'application/vnd.yamaha.hv-voice',
        'hvs' => 'application/vnd.yamaha.hv-script',
        'i2g' => 'application/vnd.intergeo',
        'icc' => 'application/vnd.iccprofile',
        'ice' => 'x-conference/x-cooltalk',
        'icm' => 'application/vnd.iccprofile',
        'ico' => 'image/x-icon',
        'ics' => 'text/calendar',
        'ief' => 'image/ief',
        'ifb' => 'text/calendar',
        'ifm' => 'application/vnd.shana.informed.formdata',
        'iges' => 'model/iges',
        'igl' => 'application/vnd.igloader',
        'igm' => 'application/vnd.insors.igm',
        'igs' => 'model/iges',
        'igx' => 'application/vnd.micrografx.igx',
        'iif' => 'application/vnd.shana.informed.interchange',
        'imp' => 'application/vnd.accpac.simply.imp',
        'ims' => 'application/vnd.ms-ims',
        'in' => 'text/plain',
        'ini' => 'text/plain',
        'ipfix' => 'application/ipfix',
        'ipk' => 'application/vnd.shana.informed.package',
        'irm' => 'application/vnd.ibm.rights-management',
        'irp' => 'application/vnd.irepository.package+xml',
        'iso' => 'application/octet-stream',
        'itp' => 'application/vnd.shana.informed.formtemplate',
        'ivp' => 'application/vnd.immervision-ivp',
        'ivu' => 'application/vnd.immervision-ivu',
        'jad' => 'text/vnd.sun.j2me.app-descriptor',
        'jam' => 'application/vnd.jam',
        'jar' => 'application/java-archive',
        'java' => 'text/x-java-source',
        'jisp' => 'application/vnd.jisp',
        'jlt' => 'application/vnd.hp-jlyt',
        'jnlp' => 'application/x-java-jnlp-file',
        'joda' => 'application/vnd.joost.joda-archive',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'jpgm' => 'video/jpm',
        'jpgv' => 'video/jpeg',
        'jpm' => 'video/jpm',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'kar' => 'audio/midi',
        'karbon' => 'application/vnd.kde.karbon',
        'kfo' => 'application/vnd.kde.kformula',
        'kia' => 'application/vnd.kidspiration',
        'kml' => 'application/vnd.google-earth.kml+xml',
        'kmz' => 'application/vnd.google-earth.kmz',
        'kne' => 'application/vnd.kinar',
        'knp' => 'application/vnd.kinar',
        'kon' => 'application/vnd.kde.kontour',
        'kpr' => 'application/vnd.kde.kpresenter',
        'kpt' => 'application/vnd.kde.kpresenter',
        'ksp' => 'application/vnd.kde.kspread',
        'ktr' => 'application/vnd.kahootz',
        'ktx' => 'image/ktx',
        'ktz' => 'application/vnd.kahootz',
        'kwd' => 'application/vnd.kde.kword',
        'kwt' => 'application/vnd.kde.kword',
        'lasxml' => 'application/vnd.las.las+xml',
        'latex' => 'application/x-latex',
        'lbd' => 'application/vnd.llamagraphics.life-balance.desktop',
        'lbe' => 'application/vnd.llamagraphics.life-balance.exchange+xml',
        'les' => 'application/vnd.hhe.lesson-player',
        'lha' => 'application/octet-stream',
        'link66' => 'application/vnd.route66.link66+xml',
        'list' => 'text/plain',
        'list3820' => 'application/vnd.ibm.modcap',
        'listafp' => 'application/vnd.ibm.modcap',
        'log' => 'text/plain',
        'lostxml' => 'application/lost+xml',
        'lrf' => 'application/octet-stream',
        'lrm' => 'application/vnd.ms-lrm',
        'ltf' => 'application/vnd.frogans.ltf',
        'lvp' => 'audio/vnd.lucent.voice',
        'lwp' => 'application/vnd.lotus-wordpro',
        'lzh' => 'application/octet-stream',
        'm13' => 'application/x-msmediaview',
        'm14' => 'application/x-msmediaview',
        'm1v' => 'video/mpeg',
        'm21' => 'application/mp21',
        'm2a' => 'audio/mpeg',
        'm2v' => 'video/mpeg',
        'm3a' => 'audio/mpeg',
        'm3u' => 'audio/x-mpegurl',
        'm3u8' => 'application/vnd.apple.mpegurl',
        'm4a' => 'audio/mp4',
        'm4u' => 'video/vnd.mpegurl',
        'm4v' => 'video/mp4',
        'ma' => 'application/mathematica',
        'mads' => 'application/mads+xml',
        'mag' => 'application/vnd.ecowin.chart',
        'maker' => 'application/vnd.framemaker',
        'man' => 'text/troff',
        'mathml' => 'application/mathml+xml',
        'mb' => 'application/mathematica',
        'mbk' => 'application/vnd.mobius.mbk',
        'mbox' => 'application/mbox',
        'mc1' => 'application/vnd.medcalcdata',
        'mcd' => 'application/vnd.mcd',
        'mcurl' => 'text/vnd.curl.mcurl',
        'mdb' => 'application/x-msaccess',
        'mdi' => 'image/vnd.ms-modi',
        'me' => 'text/troff',
        'mesh' => 'model/mesh',
        'meta4' => 'application/metalink4+xml',
        'mets' => 'application/mets+xml',
        'mfm' => 'application/vnd.mfmp',
        'mgp' => 'application/vnd.osgeo.mapguide.package',
        'mgz' => 'application/vnd.proteus.magazine',
        'mid' => 'audio/midi',
        'midi' => 'audio/midi',
        'mif' => 'application/vnd.mif',
        'mime' => 'message/rfc822',
        'mj2' => 'video/mj2',
        'mjp2' => 'video/mj2',
        'mlp' => 'application/vnd.dolby.mlp',
        'mmd' => 'application/vnd.chipnuts.karaoke-mmd',
        'mmf' => 'application/vnd.smaf',
        'mmr' => 'image/vnd.fujixerox.edmics-mmr',
        'mny' => 'application/x-msmoney',
        'mobi' => 'application/x-mobipocket-ebook',
        'mods' => 'application/mods+xml',
        'mov' => 'video/quicktime',
        'movie' => 'video/x-sgi-movie',
        'mp2' => 'audio/mpeg',
        'mp21' => 'application/mp21',
        'mp2a' => 'audio/mpeg',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'mp4a' => 'audio/mp4',
        'mp4s' => 'application/mp4',
        'mp4v' => 'video/mp4',
        'mpc' => 'application/vnd.mophun.certificate',
        'mpe' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpg4' => 'video/mp4',
        'mpga' => 'audio/mpeg',
        'mpkg' => 'application/vnd.apple.installer+xml',
        'mpm' => 'application/vnd.blueice.multipass',
        'mpn' => 'application/vnd.mophun.application',
        'mpp' => 'application/vnd.ms-project',
        'mpt' => 'application/vnd.ms-project',
        'mpy' => 'application/vnd.ibm.minipay',
        'mqy' => 'application/vnd.mobius.mqy',
        'mrc' => 'application/marc',
        'mrcx' => 'application/marcxml+xml',
        'ms' => 'text/troff',
        'mscml' => 'application/mediaservercontrol+xml',
        'mseed' => 'application/vnd.fdsn.mseed',
        'mseq' => 'application/vnd.mseq',
        'msf' => 'application/vnd.epson.msf',
        'msh' => 'model/mesh',
        'msi' => 'application/x-msdownload',
        'msl' => 'application/vnd.mobius.msl',
        'msty' => 'application/vnd.muvee.style',
        'mts' => 'model/vnd.mts',
        'mus' => 'application/vnd.musician',
        'musicxml' => 'application/vnd.recordare.musicxml+xml',
        'mvb' => 'application/x-msmediaview',
        'mwf' => 'application/vnd.mfer',
        'mxf' => 'application/mxf',
        'mxl' => 'application/vnd.recordare.musicxml',
        'mxml' => 'application/xv+xml',
        'mxs' => 'application/vnd.triscape.mxs',
        'mxu' => 'video/vnd.mpegurl',
        'n-gage' => 'application/vnd.nokia.n-gage.symbian.install',
        'n3' => 'text/n3',
        'nb' => 'application/mathematica',
        'nbp' => 'application/vnd.wolfram.player',
        'nc' => 'application/x-netcdf',
        'ncx' => 'application/x-dtbncx+xml',
        'ngdat' => 'application/vnd.nokia.n-gage.data',
        'nlu' => 'application/vnd.neurolanguage.nlu',
        'nml' => 'application/vnd.enliven',
        'nnd' => 'application/vnd.noblenet-directory',
        'nns' => 'application/vnd.noblenet-sealer',
        'nnw' => 'application/vnd.noblenet-web',
        'npx' => 'image/vnd.net-fpx',
        'nsf' => 'application/vnd.lotus-notes',
        'oa2' => 'application/vnd.fujitsu.oasys2',
        'oa3' => 'application/vnd.fujitsu.oasys3',
        'oas' => 'application/vnd.fujitsu.oasys',
        'obd' => 'application/x-msbinder',
        'oda' => 'application/oda',
        'odb' => 'application/vnd.oasis.opendocument.database',
        'odc' => 'application/vnd.oasis.opendocument.chart',
        'odf' => 'application/vnd.oasis.opendocument.formula',
        'odft' => 'application/vnd.oasis.opendocument.formula-template',
        'odg' => 'application/vnd.oasis.opendocument.graphics',
        'odi' => 'application/vnd.oasis.opendocument.image',
        'odm' => 'application/vnd.oasis.opendocument.text-master',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'oga' => 'audio/ogg',
        'ogg' => 'audio/ogg',
        'ogv' => 'video/ogg',
        'ogx' => 'application/ogg',
        'onepkg' => 'application/onenote',
        'onetmp' => 'application/onenote',
        'onetoc' => 'application/onenote',
        'onetoc2' => 'application/onenote',
        'opf' => 'application/oebps-package+xml',
        'oprc' => 'application/vnd.palm',
        'org' => 'application/vnd.lotus-organizer',
        'osf' => 'application/vnd.yamaha.openscoreformat',
        'osfpvg' => 'application/vnd.yamaha.openscoreformat.osfpvg+xml',
        'otc' => 'application/vnd.oasis.opendocument.chart-template',
        'otf' => 'application/x-font-otf',
        'otg' => 'application/vnd.oasis.opendocument.graphics-template',
        'oth' => 'application/vnd.oasis.opendocument.text-web',
        'oti' => 'application/vnd.oasis.opendocument.image-template',
        'otp' => 'application/vnd.oasis.opendocument.presentation-template',
        'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
        'ott' => 'application/vnd.oasis.opendocument.text-template',
        'oxt' => 'application/vnd.openofficeorg.extension',
        'p' => 'text/x-pascal',
        'p10' => 'application/pkcs10',
        'p12' => 'application/x-pkcs12',
        'p7b' => 'application/x-pkcs7-certificates',
        'p7c' => 'application/pkcs7-mime',
        'p7m' => 'application/pkcs7-mime',
        'p7r' => 'application/x-pkcs7-certreqresp',
        'p7s' => 'application/pkcs7-signature',
        'p8' => 'application/pkcs8',
        'pas' => 'text/x-pascal',
        'paw' => 'application/vnd.pawaafile',
        'pbd' => 'application/vnd.powerbuilder6',
        'pbm' => 'image/x-portable-bitmap',
        'pcf' => 'application/x-font-pcf',
        'pcl' => 'application/vnd.hp-pcl',
        'pclxl' => 'application/vnd.hp-pclxl',
        'pct' => 'image/x-pict',
        'pcurl' => 'application/vnd.curl.pcurl',
        'pcx' => 'image/x-pcx',
        'pdb' => 'application/vnd.palm',
        'pdf' => 'application/pdf',
        'pfa' => 'application/x-font-type1',
        'pfb' => 'application/x-font-type1',
        'pfm' => 'application/x-font-type1',
        'pfr' => 'application/font-tdpfr',
        'pfx' => 'application/x-pkcs12',
        'pgm' => 'image/x-portable-graymap',
        'pgn' => 'application/x-chess-pgn',
        'pgp' => 'application/pgp-encrypted',
        'php' => 'text/x-php',
        'phps' => 'application/x-httpd-phps',
        'pic' => 'image/x-pict',
        'pkg' => 'application/octet-stream',
        'pki' => 'application/pkixcmp',
        'pkipath' => 'application/pkix-pkipath',
        'plb' => 'application/vnd.3gpp.pic-bw-large',
        'plc' => 'application/vnd.mobius.plc',
        'plf' => 'application/vnd.pocketlearn',
        'pls' => 'application/pls+xml',
        'pml' => 'application/vnd.ctc-posml',
        'png' => 'image/png',
        'pnm' => 'image/x-portable-anymap',
        'portpkg' => 'application/vnd.macports.portpkg',
        'pot' => 'application/vnd.ms-powerpoint',
        'potm' => 'application/vnd.ms-powerpoint.template.macroenabled.12',
        'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
        'ppam' => 'application/vnd.ms-powerpoint.addin.macroenabled.12',
        'ppd' => 'application/vnd.cups-ppd',
        'ppm' => 'image/x-portable-pixmap',
        'pps' => 'application/vnd.ms-powerpoint',
        'ppsm' => 'application/vnd.ms-powerpoint.slideshow.macroenabled.12',
        'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptm' => 'application/vnd.ms-powerpoint.presentation.macroenabled.12',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'pqa' => 'application/vnd.palm',
        'prc' => 'application/x-mobipocket-ebook',
        'pre' => 'application/vnd.lotus-freelance',
        'prf' => 'application/pics-rules',
        'ps' => 'application/postscript',
        'psb' => 'application/vnd.3gpp.pic-bw-small',
        'psd' => 'image/vnd.adobe.photoshop',
        'psf' => 'application/x-font-linux-psf',
        'pskcxml' => 'application/pskc+xml',
        'ptid' => 'application/vnd.pvi.ptid1',
        'pub' => 'application/x-mspublisher',
        'pvb' => 'application/vnd.3gpp.pic-bw-var',
        'pwn' => 'application/vnd.3m.post-it-notes',
        'pya' => 'audio/vnd.ms-playready.media.pya',
        'pyv' => 'video/vnd.ms-playready.media.pyv',
        'qam' => 'application/vnd.epson.quickanime',
        'qbo' => 'application/vnd.intu.qbo',
        'qfx' => 'application/vnd.intu.qfx',
        'qps' => 'application/vnd.publishare-delta-tree',
        'qt' => 'video/quicktime',
        'qwd' => 'application/vnd.quark.quarkxpress',
        'qwt' => 'application/vnd.quark.quarkxpress',
        'qxb' => 'application/vnd.quark.quarkxpress',
        'qxd' => 'application/vnd.quark.quarkxpress',
        'qxl' => 'application/vnd.quark.quarkxpress',
        'qxt' => 'application/vnd.quark.quarkxpress',
        'ra' => 'audio/x-pn-realaudio',
        'ram' => 'audio/x-pn-realaudio',
        'rar' => 'application/x-rar-compressed',
        'ras' => 'image/x-cmu-raster',
        'rb' => 'text/plain',
        'rcprofile' => 'application/vnd.ipunplugged.rcprofile',
        'rdf' => 'application/rdf+xml',
        'rdz' => 'application/vnd.data-vision.rdz',
        'rep' => 'application/vnd.businessobjects',
        'res' => 'application/x-dtbresource+xml',
        'resx' => 'text/xml',
        'rgb' => 'image/x-rgb',
        'rif' => 'application/reginfo+xml',
        'rip' => 'audio/vnd.rip',
        'rl' => 'application/resource-lists+xml',
        'rlc' => 'image/vnd.fujixerox.edmics-rlc',
        'rld' => 'application/resource-lists-diff+xml',
        'rm' => 'application/vnd.rn-realmedia',
        'rmi' => 'audio/midi',
        'rmp' => 'audio/x-pn-realaudio-plugin',
        'rms' => 'application/vnd.jcp.javame.midlet-rms',
        'rnc' => 'application/relax-ng-compact-syntax',
        'roff' => 'text/troff',
        'rp9' => 'application/vnd.cloanto.rp9',
        'rpss' => 'application/vnd.nokia.radio-presets',
        'rpst' => 'application/vnd.nokia.radio-preset',
        'rq' => 'application/sparql-query',
        'rs' => 'application/rls-services+xml',
        'rsd' => 'application/rsd+xml',
        'rss' => 'text/xml',		/* application/rss+xml is not actually a registered IANA mime-type */
        'rtf' => 'application/rtf',
        'rtx' => 'text/richtext',
        's' => 'text/x-asm',
        'saf' => 'application/vnd.yamaha.smaf-audio',
        'sbml' => 'application/sbml+xml',
        'sc' => 'application/vnd.ibm.secure-container',
        'scd' => 'application/x-msschedule',
        'scm' => 'application/vnd.lotus-screencam',
        'scq' => 'application/scvp-cv-request',
        'scs' => 'application/scvp-cv-response',
        'scurl' => 'text/vnd.curl.scurl',
        'sda' => 'application/vnd.stardivision.draw',
        'sdc' => 'application/vnd.stardivision.calc',
        'sdd' => 'application/vnd.stardivision.impress',
        'sdkd' => 'application/vnd.solent.sdkm+xml',
        'sdkm' => 'application/vnd.solent.sdkm+xml',
        'sdp' => 'application/sdp',
        'sdw' => 'application/vnd.stardivision.writer',
        'see' => 'application/vnd.seemail',
        'seed' => 'application/vnd.fdsn.seed',
        'sema' => 'application/vnd.sema',
        'semd' => 'application/vnd.semd',
        'semf' => 'application/vnd.semf',
        'ser' => 'application/java-serialized-object',
        'setpay' => 'application/set-payment-initiation',
        'setreg' => 'application/set-registration-initiation',
        'sfd-hdstx' => 'application/vnd.hydrostatix.sof-data',
        'sfs' => 'application/vnd.spotfire.sfs',
        'sgl' => 'application/vnd.stardivision.writer-global',
        'sgm' => 'text/sgml',
        'sgml' => 'text/sgml',
        'sh' => 'application/x-sh',
        'shar' => 'application/x-shar',
        'shf' => 'application/shf+xml',
        'sig' => 'application/pgp-signature',
        'silo' => 'model/mesh',
        'sis' => 'application/vnd.symbian.install',
        'sisx' => 'application/vnd.symbian.install',
        'sit' => 'application/x-stuffit',
        'sitx' => 'application/x-stuffitx',
        'skd' => 'application/vnd.koan',
        'skm' => 'application/vnd.koan',
        'skp' => 'application/vnd.koan',
        'skt' => 'application/vnd.koan',
        'sldm' => 'application/vnd.ms-powerpoint.slide.macroenabled.12',
        'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
        'slt' => 'application/vnd.epson.salt',
        'sm' => 'application/vnd.stepmania.stepchart',
        'smf' => 'application/vnd.stardivision.math',
        'smi' => 'application/smil+xml',
        'smil' => 'application/smil+xml',
        'snd' => 'audio/basic',
        'snf' => 'application/x-font-snf',
        'so' => 'application/octet-stream',
        'spc' => 'application/x-pkcs7-certificates',
        'spf' => 'application/vnd.yamaha.smaf-phrase',
        'spl' => 'application/x-futuresplash',
        'spot' => 'text/vnd.in3d.spot',
        'spp' => 'application/scvp-vp-response',
        'spq' => 'application/scvp-vp-request',
        'spx' => 'audio/ogg',
        'src' => 'application/x-wais-source',
        'sru' => 'application/sru+xml',
        'srx' => 'application/sparql-results+xml',
        'sse' => 'application/vnd.kodak-descriptor',
        'ssf' => 'application/vnd.epson.ssf',
        'ssml' => 'application/ssml+xml',
        'st' => 'application/vnd.sailingtracker.track',
        'stc' => 'application/vnd.sun.xml.calc.template',
        'std' => 'application/vnd.sun.xml.draw.template',
        'stf' => 'application/vnd.wt.stf',
        'sti' => 'application/vnd.sun.xml.impress.template',
        'stk' => 'application/hyperstudio',
        'stl' => 'application/vnd.ms-pki.stl',
        'str' => 'application/vnd.pg.format',
        'stw' => 'application/vnd.sun.xml.writer.template',
        'sub' => 'image/vnd.dvb.subtitle',
        'sus' => 'application/vnd.sus-calendar',
        'susp' => 'application/vnd.sus-calendar',
        'sv4cpio' => 'application/x-sv4cpio',
        'sv4crc' => 'application/x-sv4crc',
        'svc' => 'application/vnd.dvb.service',
        'svd' => 'application/vnd.svd',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        'swa' => 'application/x-director',
        'swf' => 'application/x-shockwave-flash',
        'swi' => 'application/vnd.aristanetworks.swi',
        'sxc' => 'application/vnd.sun.xml.calc',
        'sxd' => 'application/vnd.sun.xml.draw',
        'sxg' => 'application/vnd.sun.xml.writer.global',
        'sxi' => 'application/vnd.sun.xml.impress',
        'sxm' => 'application/vnd.sun.xml.math',
        'sxw' => 'application/vnd.sun.xml.writer',
        't' => 'text/troff',
        'tao' => 'application/vnd.tao.intent-module-archive',
        'tar' => 'application/x-tar',
        'tcap' => 'application/vnd.3gpp2.tcap',
        'tcl' => 'application/x-tcl',
        'teacher' => 'application/vnd.smart.teacher',
        'tei' => 'application/tei+xml',
        'teicorpus' => 'application/tei+xml',
        'tex' => 'application/x-tex',
        'texi' => 'application/x-texinfo',
        'texinfo' => 'application/x-texinfo',
        'text' => 'text/plain',
        'tfi' => 'application/thraud+xml',
        'tfm' => 'application/x-tex-tfm',
        'thmx' => 'application/vnd.ms-officetheme',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'tmo' => 'application/vnd.tmobile-livetv',
        'torrent' => 'application/x-bittorrent',
        'tpl' => 'application/vnd.groove-tool-template',
        'tpt' => 'application/vnd.trid.tpt',
        'tr' => 'text/troff',
        'tra' => 'application/vnd.trueapp',
        'trm' => 'application/x-msterminal',
        'tsd' => 'application/timestamped-data',
        'tsv' => 'text/tab-separated-values',
        'ttc' => 'application/x-font-ttf',
        'ttf' => 'application/x-font-ttf',
        'ttl' => 'text/turtle',
        'twd' => 'application/vnd.simtech-mindmapper',
        'twds' => 'application/vnd.simtech-mindmapper',
        'txd' => 'application/vnd.genomatix.tuxedo',
        'txf' => 'application/vnd.mobius.txf',
        'txt' => 'text/plain',
        'u32' => 'application/x-authorware-bin',
        'udeb' => 'application/x-debian-package',
        'ufd' => 'application/vnd.ufdl',
        'ufdl' => 'application/vnd.ufdl',
        'umj' => 'application/vnd.umajin',
        'unityweb' => 'application/vnd.unity',
        'uoml' => 'application/vnd.uoml+xml',
        'uri' => 'text/uri-list',
        'uris' => 'text/uri-list',
        'urls' => 'text/uri-list',
        'ustar' => 'application/x-ustar',
        'utz' => 'application/vnd.uiq.theme',
        'uu' => 'text/x-uuencode',
        'uva' => 'audio/vnd.dece.audio',
        'uvd' => 'application/vnd.dece.data',
        'uvf' => 'application/vnd.dece.data',
        'uvg' => 'image/vnd.dece.graphic',
        'uvh' => 'video/vnd.dece.hd',
        'uvi' => 'image/vnd.dece.graphic',
        'uvm' => 'video/vnd.dece.mobile',
        'uvp' => 'video/vnd.dece.pd',
        'uvs' => 'video/vnd.dece.sd',
        'uvt' => 'application/vnd.dece.ttml+xml',
        'uvu' => 'video/vnd.uvvu.mp4',
        'uvv' => 'video/vnd.dece.video',
        'uvva' => 'audio/vnd.dece.audio',
        'uvvd' => 'application/vnd.dece.data',
        'uvvf' => 'application/vnd.dece.data',
        'uvvg' => 'image/vnd.dece.graphic',
        'uvvh' => 'video/vnd.dece.hd',
        'uvvi' => 'image/vnd.dece.graphic',
        'uvvm' => 'video/vnd.dece.mobile',
        'uvvp' => 'video/vnd.dece.pd',
        'uvvs' => 'video/vnd.dece.sd',
        'uvvt' => 'application/vnd.dece.ttml+xml',
        'uvvu' => 'video/vnd.uvvu.mp4',
        'uvvv' => 'video/vnd.dece.video',
        'uvvx' => 'application/vnd.dece.unspecified',
        'uvx' => 'application/vnd.dece.unspecified',
        'vcd' => 'application/x-cdlink',
        'vcf' => 'text/x-vcard',
        'vcg' => 'application/vnd.groove-vcard',
        'vcs' => 'text/x-vcalendar',
        'vcx' => 'application/vnd.vcx',
        'vis' => 'application/vnd.visionary',
        'viv' => 'video/vnd.vivo',
        'vor' => 'application/vnd.stardivision.writer',
        'vox' => 'application/x-authorware-bin',
        'vrml' => 'model/vrml',
        'vsd' => 'application/vnd.visio',
        'vsf' => 'application/vnd.vsf',
        'vss' => 'application/vnd.visio',
        'vst' => 'application/vnd.visio',
        'vsw' => 'application/vnd.visio',
        'vtu' => 'model/vnd.vtu',
        'vxml' => 'application/voicexml+xml',
        'w3d' => 'application/x-director',
        'wad' => 'application/x-doom',
        'wav' => 'audio/x-wav',
        'wax' => 'audio/x-ms-wax',
        'wbmp' => 'image/vnd.wap.wbmp',
        'wbs' => 'application/vnd.criticaltools.wbs+xml',
        'wbxml' => 'application/vnd.wap.wbxml',
        'wcm' => 'application/vnd.ms-works',
        'wdb' => 'application/vnd.ms-works',
        'weba' => 'audio/webm',
        'webm' => 'video/webm',
        'webp' => 'image/webp',
        'wg' => 'application/vnd.pmi.widget',
        'wgt' => 'application/widget',
        'wks' => 'application/vnd.ms-works',
        'wm' => 'video/x-ms-wm',
        'wma' => 'audio/x-ms-wma',
        'wmd' => 'application/x-ms-wmd',
        'wmf' => 'application/x-msmetafile',
        'wml' => 'text/vnd.wap.wml',
        'wmlc' => 'application/vnd.wap.wmlc',
        'wmls' => 'text/vnd.wap.wmlscript',
        'wmlsc' => 'application/vnd.wap.wmlscriptc',
        'wmv' => 'video/x-ms-wmv',
        'wmx' => 'video/x-ms-wmx',
        'wmz' => 'application/x-ms-wmz',
        'woff' => 'application/x-font-woff',
        'wpd' => 'application/vnd.wordperfect',
        'wpl' => 'application/vnd.ms-wpl',
        'wps' => 'application/vnd.ms-works',
        'wqd' => 'application/vnd.wqd',
        'wri' => 'application/x-mswrite',
        'wrl' => 'model/vrml',
        'wsdl' => 'application/wsdl+xml',
        'wspolicy' => 'application/wspolicy+xml',
        'wtb' => 'application/vnd.webturbo',
        'wvx' => 'video/x-ms-wvx',
        'x32' => 'application/x-authorware-bin',
        'x3d' => 'application/vnd.hzn-3d-crossword',
        'xap' => 'application/x-silverlight-app',
        'xar' => 'application/vnd.xara',
        'xbap' => 'application/x-ms-xbap',
        'xbd' => 'application/vnd.fujixerox.docuworks.binder',
        'xbm' => 'image/x-xbitmap',
        'xdf' => 'application/xcap-diff+xml',
        'xdm' => 'application/vnd.syncml.dm+xml',
        'xdp' => 'application/vnd.adobe.xdp+xml',
        'xdssc' => 'application/dssc+xml',
        'xdw' => 'application/vnd.fujixerox.docuworks',
        'xenc' => 'application/xenc+xml',
        'xer' => 'application/patch-ops-error+xml',
        'xfdf' => 'application/vnd.adobe.xfdf',
        'xfdl' => 'application/vnd.xfdl',
        'xht' => 'application/xhtml+xml',
        'xhtml' => 'application/xhtml+xml',
        'xhvml' => 'application/xv+xml',
        'xif' => 'image/vnd.xiff',
        'xla' => 'application/vnd.ms-excel',
        'xlam' => 'application/vnd.ms-excel.addin.macroenabled.12',
        'xlc' => 'application/vnd.ms-excel',
        'xlm' => 'application/vnd.ms-excel',
        'xls' => 'application/vnd.ms-excel',
        'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroenabled.12',
        'xlsm' => 'application/vnd.ms-excel.sheet.macroenabled.12',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xlt' => 'application/vnd.ms-excel',
        'xltm' => 'application/vnd.ms-excel.template.macroenabled.12',
        'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        'xlw' => 'application/vnd.ms-excel',
        'xml' => 'application/xml',
        'xo' => 'application/vnd.olpc-sugar',
        'xop' => 'application/xop+xml',
        'xpi' => 'application/x-xpinstall',
        'xpm' => 'image/x-xpixmap',
        'xpr' => 'application/vnd.is-xpr',
        'xps' => 'application/vnd.ms-xpsdocument',
        'xpw' => 'application/vnd.intercon.formnet',
        'xpx' => 'application/vnd.intercon.formnet',
        'xsl' => 'application/xml',
        'xslt' => 'application/xslt+xml',
        'xsm' => 'application/vnd.syncml+xml',
        'xspf' => 'application/xspf+xml',
        'xul' => 'application/vnd.mozilla.xul+xml',
        'xvm' => 'application/xv+xml',
        'xvml' => 'application/xv+xml',
        'xwd' => 'image/x-xwindowdump',
        'xyz' => 'chemical/x-xyz',
        'yaml' => 'text/yaml',
        'yang' => 'application/yang',
        'yin' => 'application/yin+xml',
        'yml' => 'text/yaml',
        'zaz' => 'application/vnd.zzazz.deck+xml',
        'zip' => 'application/zip',
        'zir' => 'application/vnd.zul',
        'zirz' => 'application/vnd.zul',
        'zmm' => 'application/vnd.handheld-entertainment+xml'
	);

	/**
	 * Is this an animated image ( does it contain multiple frames )?
	 *
	 * @return bool
	 */
	public function isAnimatedImage(): bool
	{
		$offset = 0;
		$frame = 0;
		$contents = $this->contents();

		while ( $frame < 2 )
		{
			$pos = \strpos( $contents, "\x00\x21\xF9\x04", $offset );
			if ( $pos === false )
			{
				break;
			}
			else
			{
				$offset = $pos + 1;
				$newPos = \strpos( $contents, "\x00\x2C", $offset );
				if ( $newPos === false )
				{
					break;
				}
				else
				{
					if ( $pos + 8 == $newPos )
					{
						$frame++;
					}
					$offset = $newPos + 1;
				}
			}
		}

		return $frame > 1;
	}
}
