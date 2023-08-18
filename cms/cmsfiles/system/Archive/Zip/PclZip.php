<?php
/**
 * @brief		PclZip Zip Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 Jul 2015
 */

namespace IPS\Archive\Zip;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

@require_once( \IPS\ROOT_PATH . '/system/3rd_party/pclzip/pclzip.lib.php' ); // @ is because of a deprecated warning

/**
 * @brief	PclZip Zip Class
 */
class _PclZip extends \IPS\Archive\Zip
{
	/**
	 * @brief	PclZip Object
	 */
	protected $zipArchive;
	
	/**
	 * @brief	Files
	 */
	protected $files;
	
	/**
	 * Create object from local file
	 *
	 * @param	string	$path			Path to archive file
	 * @return	\IPS\Archive
	 */
	public static function _fromLocalFile( $path )
	{
		$object = new static;
		$object->zipArchive = new \PclZip( $path );
		return $object;
	}
	
	/**
	 * Number of files
	 *
	 * @return	int
	 */
	public function numberOfFiles()
	{
		$properties = $this->zipArchive->properties();
		return $properties['nb'];
	}
	
	/**
	 * Get file name
	 *
	 * @param	int	$i	File number
	 * @return	string
	 * @throws	\RuntimeException
	 * @throws	\OutOfRangeException
	 */
	public function getFileName( $i )
	{
		if ( $this->files === NULL )
		{
			$this->files = $this->zipArchive->listContent();
		}
		
		/* pclZip returns 0 on an unrecoverable failure */
		if ( $this->files === 0 )
		{
			throw new \RuntimeException;
		}
		
		if ( isset( $this->files[ $i ] ) )
		{
			return mb_substr( $this->files[ $i ]['filename'], 0, mb_strlen( $this->containerName ) ) === $this->containerName ? mb_substr( $this->files[ $i ]['filename'], mb_strlen( $this->containerName ) ) : $this->files[ $i ]['filename'];
		}
		else
		{
			throw new \OutOfRangeException;
		}
	}
	
	/**
	 * Get file contents
	 *
	 * @param	int	$i	File number
	 * @return	string
	 * @throws	\OutOfRangeException
	 */
	public function getFileContents( $i )
	{
		$content = $this->zipArchive->extractByIndex( $i, \PCLZIP_OPT_EXTRACT_AS_STRING );
		return $content[0]['content'];
	}
}