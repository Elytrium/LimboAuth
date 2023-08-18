<?php
/**
 * @brief		FTP Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 May 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * FTP Class
 */
class _Ftp
{
	/**
	 * @brief	Connection resource
	 */
	protected $ftp;

	/**
	 * Constructor
	 *
	 * @param	string	$host		Hostname
	 * @param	string	$username	Username
	 * @param	string	$password	Password
	 * @param	int		$port		Port
	 * @param	bool	$secure		Use secure SSL-FTP connection?
	 * @param	int		$timeout	Timeout (in seconds)
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function __construct( $host, $username, $password, $port=21, $secure=FALSE, $timeout=10 )
	{
		if ( $secure )
		{
			if( !\function_exists('ftp_ssl_connect') )
			{
				throw new \IPS\Ftp\Exception( 'SSL_NOT_AVAILABLE' );
			}

			$this->ftp = @ftp_ssl_connect( $host, $port, $timeout );
		}
		else
		{
			$this->ftp = @ftp_connect( $host, $port, $timeout );
		}
		
		if ( $this->ftp === FALSE )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_CONNECT' );
		}
		if ( !@ftp_login( $this->ftp, $username, $password ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_LOGIN' );
		}

		/* Typically if passive mode is required, ftp_nlist will return FALSE instead of an array */
		if( $this->ls() === FALSE )
		{
			@ftp_pasv( $this->ftp, true );
		}
	}
	
	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		if( $this->ftp !== NULL )
		{
			@ftp_close( $this->ftp );
		}
	}
	
	/**
	 * chdir
	 *
	 * @param	string	$dir	Directory
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function chdir( $dir )
	{
		if ( !@ftp_chdir( $this->ftp, $dir ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_CHDIR' );
		}
	}
	
	/**
	 * cdup
	 *
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function cdup()
	{
		if ( !@ftp_cdup( $this->ftp ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_CDUP' );
		}
	}
	
	/**
	 * mkdir
	 *
	 * @param	string	$dir	Directory
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function mkdir( $dir )
	{
		if ( !@ftp_mkdir( $this->ftp, $dir ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_MKDIR' );
		}
	}
	
	/**
	 * ls
	 *
	 * @param	string	$path	Argument to pass to ftp_nlist
	 * @return	array|bool
	 */
	public function ls( $path = '.' )
	{
		return ftp_nlist( $this->ftp, $path );
	}

	/**
	 * Raw list
	 *
	 * @param	string	$path		Argument to pass to ftp_nlist
	 * @param	bool	$recursive	Whether or not to list recursively
	 * @return	array
	 */
	public function rawList( $path = '.', $recursive = FALSE )
	{
		return ftp_rawlist( $this->ftp, $path, $recursive );
	}
	
	/**
	 * Upload File
	 *
	 * @param	string	$filename	Filename to use
	 * @param	string	$file		Path to local file
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function upload( $filename, $file )
	{
		if ( !@ftp_put( $this->ftp, $filename, $file, FTP_BINARY ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_UPLOAD' );
		}
	}
	
	/**
	 * Download File
	 *
	 * @param	string		$filename	The file to download
	 * @param	string|null	$target		Location to save downloaded file or NULL to return contents
	 * @param	bool		$returnPath	Return the path to the downloaded file instead of the contents
	 * @return	string		File contents
	 * @throws	\IPS\Ftp\Exception
	 */
	public function download( $filename, $target=NULL, $returnPath=FALSE )
	{
		$temp = FALSE;
		if ( $target === NULL )
		{
			$temp = TRUE;
			$target = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
		}
		
		if ( !@ftp_get( $this->ftp, $target, $filename, FTP_BINARY ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_DOWNLOAD' );
		}

		/* We use this to avoid out of memory errors - just return path */
		if( $returnPath === TRUE )
		{
			return $target;
		}

		$result = file_get_contents( $target );
		
		if ( $temp )
		{
			@unlink( $target );
		}
		
		return $result;		
	}
	
	/**
	 * CHMOD
	 * 
	 * @param	string		$filename	The file to CHMOD
	 * @param	int			$mode		Mode (in octal form)
	 * @return	@e void
	 * @throws	Exception	CHMOD_ERROR
	 */
	public function chmod( $filename, $mode )
	{
		if( !@ftp_chmod( $this->ftp, $mode, $filename ) )
		{
			throw new \IPS\Ftp\Exception( 'CHMOD_ERROR' );
		}
	}
	
	/**
	 * Delete file
	 *
	 * @param	string	$file		Path to file
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function delete( $file )
	{
		if ( !@ftp_delete( $this->ftp, $file ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_DELETE' );
		}
	}

	/**
	 * Get file size (if possible)
	 *
	 * @param	string	$file		Path to file
	 * @return	float
	 */
	public function size( $file )
	{
		$size = @ftp_size( $this->ftp, $file );

		if ( !$size OR $size == -1 )
		{
			$size = 0;

			$rawValue = @ftp_raw( $this->ftp, "SIZE " . $file );

			if( mb_substr( $rawValue[0], 0, 3 ) == 213 )
			{
				$size = str_replace( '213 ', '', $rawValue[0] );
			}
		}

		return sprintf( "%u", $size );
	}
	
	/**
	 * Delete directory
	 *
	 * @param	string	$dir		Path to directory
	 * @param	bool	$recursive	Recursive? (If FALSE and directory is not empty, operation will fail)
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function rmdir( $dir, $recursive=FALSE )
	{	
		if ( $recursive )
		{
			$this->chdir( $dir );
			foreach ( ftp_rawlist( $this->ftp, '.' ) as $data )
			{
				preg_match( '/^(.).*\s(.*)$/', $data, $matches );
				if ( $matches[2] !== '.' and $matches[2] !== '..' )
				{
					if ( $matches[1] === 'd' )
					{
						$this->rmdir( $matches[2], TRUE );
					}
					else
					{
						$this->delete( $matches[2] );
					}
				}
			}
			$this->cdup();
		}
				
		if ( !@ftp_rmdir( $this->ftp, $dir ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_DELETE' );
		}
	}
}