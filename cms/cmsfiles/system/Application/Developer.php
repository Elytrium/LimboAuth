<?php
/**
 * @brief		Application Developer Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 October 2013
 */

namespace IPS\Application;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Developer class used for IN_DEV management
 */
class _Developer extends \IPS\Application
{
	/**
	 * @brief Array of directories that should always be present inside /dev
	 */
	protected static $devDirs = array( 'css', 'email', 'html', 'img', 'js' );

	/**
	 * Returns NULL or a list of missing IN_DEV required directories
	 * 
	 * @return NULL|array	Null if all directories are present, or an array of directory names ('dev', 'dev/css', 'dev/email',... )
	 */
	public function getMissingDirectories()
	{
		$path    = \IPS\ROOT_PATH . '/' . $this->directory . '/dev';
		$missing = array();
		
		if ( ! is_dir( $path ) )
		{
			return array( 'dev' );
		}
		
		foreach( static::$devDirs as $dir )
		{
			if ( ! is_dir( $path . '/' . $dir ) )
			{
				$missing[] = $dir;
			}
		}
		
		return ( \count( $missing ) ) ? $missing : null;
	}
	
	/**
	 * Returns NULL or a list of unwritable directories
	 * 
	 * @return NULL|Array	Null if all directories are writeable, or an array of directory names ('dev', 'dev/css', 'dev/email',... )
	 */
	public function getUnwritableDirectories()
	{
		$path        = \IPS\ROOT_PATH . '/' . $this->directory . '/dev';
		$unwriteable = array();
		
		if ( ! is_writeable( $path ) )
		{
			return  array( 'dev' );
		}
		
		foreach( static::$devDirs as $dir )
		{
			if ( ! is_writeable( $path . '/' . $dir ) )
			{
				$unwriteable[] = $dir;
			}
		}
		
		return ( \count( $unwriteable ) ) ? $unwriteable : null;
	}
}