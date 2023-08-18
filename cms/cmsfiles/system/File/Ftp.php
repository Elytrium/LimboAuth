<?php
/**
 * @brief		File Handler: FTP
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
 * File Handler: FTP
 */
class _Ftp extends \IPS\File
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
		return array(
			'ftp_details'	=> array( 'type' => 'Ftp', 'options' => array( 'validate' => FALSE ) ),
			'url'			=> array( 'type' => 'Text', 'options' => array( 'placeholder' => 'http://www.example.com/example' ) ),
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
		if ( !\function_exists( 'ftp_connect' ) )
		{
			throw new \BadFunctionCallException( 'ftp_err_no_ext' );
		}
		elseif ( $values['ftp_details']['protocol'] === 'ssl_ftp' and !\function_exists( 'ftp_ssl_connect' ) )
		{
			throw new \BadFunctionCallException( 'ftp_err_no_ssl' );
		}
		elseif ( $values['ftp_details']['protocol'] === 'sftp' and !\function_exists( 'ssh2_connect' ) )
		{
			throw new \BadFunctionCallException( 'ftp_err_no_sftp' );
		}
		
		$values['url'] = rtrim( $values['url'], '/' );
		
		if ( filter_var( $values['url'], FILTER_VALIDATE_URL ) === false )
		{
			throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'url_is_not_real', FALSE, array( 'sprintf' => array( $values['url'] ) ) ) );
		}
		
		try
		{
			if ( $values['ftp_details']['protocol'] == 'sftp' )
			{
				$ftp = new \IPS\Ftp\Sftp( $values['ftp_details']['server'], $values['ftp_details']['un'], $values['ftp_details']['pw'], $values['ftp_details']['port'] );
			}
			else
			{
				$ftp = new \IPS\Ftp( $values['ftp_details']['server'], $values['ftp_details']['un'], $values['ftp_details']['pw'], $values['ftp_details']['port'], ( $values['ftp_details']['protocol'] == 'ssl_ftp' ) );
			}
			$ftp->chdir( $values['ftp_details']['path'] );
			
			$filename = md5( mt_rand() ) . '.ips.txt';
			$tmpFileName = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			\file_put_contents( $tmpFileName, 'OK' );
			$ftp->upload( $filename, $tmpFileName );
			$result = \IPS\Http\Url::external( $values['url'] . '/' . $filename)->request()->get();
			$ftp->delete( $filename );
			unlink( $tmpFileName );

			if ( (string) $result !== 'OK' )
			{
				if ( $result->httpResponseCode == 200 )
				{
					throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'file_storage_test_ftp_unexpected_response', FALSE, array( 'sprintf' => array( $values['ftp_details']['server'] ) ) ) );
				}
				else
				{
					throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'file_storage_test_error_ftp', FALSE, array( 'sprintf' => array( $values['ftp_details']['server'], $result->httpResponseCode ) ) ) );
				}
			}
		}
		catch ( \IPS\Ftp\Exception $e )
		{
			throw new \DomainException( 'ftp_err-' . $e->getMessage() );
		}		
	}
	
	/**
	 * Display name
	 *
	 * @param	array	$settings	Configuration settings
	 * @return	string
	 */
	public static function displayName( $settings )
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'filehandler_display_name', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('filehandler__Ftp'), ( !empty( $settings['ftp_details']['path'] ) ) ? $settings['ftp_details']['server'] . " (" . $settings['ftp_details']['path'] . ")" : $settings['ftp_details']['server'] ) ) );
	}
	
	/* !File Handling */

	/**
	 * Constructor
	 *
	 * @param	array	$configuration	Storage configuration
	 * @return	void
	 */
	public function __construct( $configuration )
	{
		$this->container = 'monthly_' . date( 'm' ) . '_' . date( 'Y' );
		parent::__construct( $configuration );
	}

	/**
	 * Return the base URL
	 *
	 * @return string
	 */
	public function baseUrl()
	{
		return rtrim( $this->configuration['url'], '/' );
	}
	
	/**
	 * Save File
	 *
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function save()
	{
		/* Get contents */
		$contents = $this->contents();
				
		/* Create a temporary file */
		$tmpFileName = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
		\file_put_contents( $tmpFileName, $contents );
				
		/* Move into the correct folder */		
		if( !\in_array( $this->container, $this->ftp()->ls() ) )
		{
			/* Make dir */
			$this->ftp()->mkdir( $this->container );
			
			/* Stick an index.html file in */
			$tmpIndexFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			\file_put_contents( $tmpIndexFile, '' );
			$this->ftp()->upload( 'index.html', $tmpIndexFile );

			unlink( $tmpIndexFile );
		}
		$this->ftp()->chdir( $this->container );
		
		/* Upload */
		$this->ftp()->upload( $this->filename, $tmpFileName );
		
		/* Move back */
		$this->ftp()->cdup();
		
		/* Destroy the temporary file */
		unlink( $tmpFileName );
		
		/* Set the URL */
		$this->url = \IPS\Http\Url::createFromString( $this->fullyQualifiedUrl( "{$this->container}/{$this->filename}" ), FALSE );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function delete()
	{
		/* Only issue delete command if the file (and container) exist...otherwise a delete command would fail but our intention is complete */
		if( $this->checkExists( $this->container . '/' . $this->filename ) )
		{
			$this->ftp()->delete( $this->container . '/' . $this->filename );
		}

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
	 * @throws	\IPS\Ftp\Exception
	 */
	public function deleteContainer( $container )
	{
		/* Actually remove, checking if it exists before attempting */
		if( $this->checkExists( $container ) )
		{
			$this->ftp()->rmdir( $container, TRUE );
		}

		/* Log deletion request */
		$realContainer = $this->container;
		$this->container = $container;
		$this->log( "container_deletion", 'delete', NULL, 'log' );
		$this->container = $realContainer;
	}

	/**
	 * Check if a path exists
	 *
	 * @param	string	$path	Path to check
	 * @return	bool
	 */
	protected function checkExists( $path )
	{
		$pathBits		= explode( '/', $path );
		$reconstructed	= array();

		foreach( $pathBits as $piece )
		{
			$pathToCheck = \count( $reconstructed ) ? implode( '/', $reconstructed ) : '.';
			if( $this->ftp()->ls( $pathToCheck ) === FALSE OR !\in_array( $piece, $this->ftp()->ls( $pathToCheck ) ) )
			{
				return FALSE;
			}

			$reconstructed[]	= $piece;
		}

		return TRUE;
	}
	
	/* !FTP Utility Methods */

	/**
	 * @brief	FTP Connection
	 */
	protected static $ftp = array();
	
	/**
	 * Get FTP Connection
	 *
	 * @return	\IPS\Ftp
	 * @throws	\IPS\Ftp\Exception
	 */
	protected function ftp()
	{
		$key = md5( json_encode( $this->configuration ) );
		
		if ( !isset( static::$ftp[ $key ] ) OR static::$ftp[ $key ] === NULL )
		{
			if ( $this->configuration['ftp_details']['protocol'] == 'sftp' )
			{
				static::$ftp[ $key ] = new \IPS\Ftp\Sftp( $this->configuration['ftp_details']['server'], $this->configuration['ftp_details']['un'], $this->configuration['ftp_details']['pw'], $this->configuration['ftp_details']['port'] );
			}
			else
			{
				static::$ftp[ $key ] = new \IPS\Ftp( $this->configuration['ftp_details']['server'], $this->configuration['ftp_details']['un'], $this->configuration['ftp_details']['pw'], $this->configuration['ftp_details']['port'], ( $this->configuration['ftp_details']['protocol'] == 'ssl_ftp' ) );
			}

			static::$ftp[ $key ]->chdir( $this->configuration['ftp_details']['path'] );
		}
		return static::$ftp[ $key ];
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

		/* We don't really care about the container index for the database method...just look for files based on the file offset */
		$checked	= 0;
		$skipped	= 0;

		$iterator	= new \RecursiveIteratorIterator( new \IPS\Ftp\RecursiveDirectoryFtpIterator( $this->ftp(), $this->configuration['ftp_details']['path'] ) );

		while( $iterator->valid() )
		{
			/* We aren't checking directories */
			if( $iterator->current()->isDir() OR $iterator->current()->getFilename() == 'index.html' )
			{
				$iterator->next();
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
				$iterator->next();
				continue;
			}

			$checked++;

			$currentName = str_replace( $this->configuration['ftp_details']['path'] . '/', '', $iterator->current()->getPathname() );

			/* Next we will have to loop through each storage engine type and call it to see if the file is valid */
			foreach( $engines as $engine )
			{
				/* If this file is valid for the engine, skip to the next file */
				if( $engine->isValidFile( $currentName ) )
				{
					$iterator->next();
					continue 2;
				}
			}

			/* If we are still here, the file was not valid */
			$this->logOrphanedFile( $currentName );
		
			$iterator->next();
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
		if( $this->_cachedFilesize !== null )
		{
			return $this->_cachedFilesize;
		}

		try
		{
			$this->ftp()->chdir( $this->configuration['ftp_details']['path'] );

			if( $this->container )
			{
				$this->ftp()->chdir( $this->container );
			}

			$size = $this->ftp()->size( $this->filename );

			$this->ftp()->chdir( $this->configuration['ftp_details']['path'] );

			$this->_cachedFilesize = $size ?: parent::filesize();
		}
		catch( \IPS\Ftp\Exception $e )
		{
			$this->_cachedFilesize = FALSE;
		}

		return $this->_cachedFilesize;
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
		/* Download the file locally */
		$file	= $this->ftp()->download( $this->configuration['ftp_details']['path'] . ( $this->container ? '/' . $this->container : '' ) . '/' . $this->filename, NULL, TRUE );

		/* Send the file */
		$this->sendFile( $file, $start, $length, $throttle );

		/* Remove the temporary file */
		@unlink( $file );
	}
}