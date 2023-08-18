<?php
/**
 * @brief		ZipArchive Zip Class
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

/**
 * @brief	ZipArchive Zip Class
 */
class _ZipArchive extends \IPS\Archive\Zip
{
	/**
	 * @brief	ZipArchive Object
	 */
	protected $zipArchive;
	
	/**
	 * Create object from local file
	 *
	 * @param	string	$path			Path to archive file
	 * @return	\IPS\Archive
	 */
	public static function _fromLocalFile( $path )
	{
		$object = new static;
		$object->zipArchive = new \ZipArchive;
		$open = $object->zipArchive->open( $path );
		if ( $open !== TRUE )
		{
			throw new \IPS\Archive\Exception( $open, \IPS\Archive\Exception::COULD_NOT_OPEN );
		}
		return $object;
	}
	
	/**
	 * Number of files
	 *
	 * @return	int
	 */
	public function numberOfFiles()
	{
		return $this->zipArchive->numFiles;
	}
	
	/**
	 * Get file name
	 *
	 * @param	int	$i	File number
	 * @return	string
	 * @throws	\OutOfRangeException
	 */
	public function getFileName( $i )
	{
		$info = $this->zipArchive->statIndex( $i );
		if ( $info === FALSE )
		{
			throw new \OutOfRangeException;
		}
		
		return mb_substr( $info['name'], 0, mb_strlen( $this->containerName ) ) === $this->containerName ? mb_substr( $info['name'], mb_strlen( $this->containerName ) ) : $info['name'];
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
		return $this->zipArchive->getFromIndex( $i );
	}
}