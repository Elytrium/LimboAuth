<?php
/**
 * @brief		File Storage Extension: Image proxy cache
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		7 Nov 2016
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Image proxy cache
 */
class _Imageproxycache
{
	/**
	 * @brief Does this exist?
	 */
	protected static $tableExists = NULL;
	
	/**
	 * Does the table exist? 
	 *
	 * @return boolean
	 */
	protected static function tableExists()
	{
		if ( static::$tableExists === NULL )
		{
			static::$tableExists = \IPS\Db::i()->checkForTable( 'core_image_proxy' );
		}
		
		return static::$tableExists;
	}
	
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		if ( static::tableExists() )
		{
			return \IPS\Db::i()->select( 'COUNT(*)', 'core_image_proxy' )->first();
		}
		else
		{
			return 0;
		}
	}
	
	/**
	 * Move stored files
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param	int|NULL	$oldConfiguration		Old storage configuration ID
	 * @throws	\UnderflowException					When file record doesn't exist. Indicating there are no more files to move
	 * @return	void|int							An offset integer to use on the next cycle, or nothing
	 */
	public function move( $offset, $storageConfiguration, $oldConfiguration=NULL )
	{
		if ( static::tableExists() )
		{
			$cache = \IPS\Db::i()->select( '*', 'core_image_proxy', array(), 'md5_url', array( $offset, 1 ) )->first();
		}
		else
		{
			throw new \UnderflowException;
		}
		
		/* Don't move a file if it doesn't have a filename */
		if( $cache['location'] === NULL )
		{
			return;
		}

		try
		{
			$file = \IPS\File::get( $oldConfiguration ?: 'core_Imageproxycache', $cache['location'] )->move( $storageConfiguration );

			if ( (string) $file != $cache['location'] )
			{
				\IPS\Db::i()->update( 'core_image_proxy', array( 'location' => (string) $file ), array( 'md5_url=?', $cache['md5_url'] ) );
			}
		}
		catch( \Exception $e )
		{
			/* Any issues are logged and the \IPS\Db::i()->update not run as the exception is thrown */
		}
	}

	/**
	 * Check if a file is valid
	 *
	 * @param	string	$file		The file path to check
	 * @return	bool
	 */
	public function isValidFile( $file )
	{
		if ( static::tableExists() )
		{
			\IPS\Db::i()->select( '*', 'core_image_proxy', array( 'location=?', (string) $file ) )->first();

			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Delete all stored files
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( static::tableExists() )
		{
			foreach( \IPS\Db::i()->select( '*', 'core_image_proxy', 'location IS NOT NULL' ) as $cache )
			{
				try
				{
					\IPS\File::get( 'core_Imageproxycache', $cache['location'] )->delete();
				}
				catch( \Exception $e ){}
			}
		}
	}
	
	/**
	 * Fix all URLs
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @return void
	 */
	public function fixUrls( $offset )
	{
		if ( static::tableExists() ) 
		{
			$cache = \IPS\Db::i()->select( '*', 'core_image_proxy', array(), 'md5_url', array( $offset, 1 ) )->first();
			
			try
			{
				if ( $new = \IPS\File::repairUrl( $cache['location'] ) )
				{
					\IPS\Db::i()->update( 'core_image_proxy', array( 'location' => (string) $new ), array( 'md5_url=?', $cache['location'] ) );
				}
			}
			catch( \Exception $e )
			{
				/* Any issues are logged and the \IPS\Db::i()->update not run as the exception is thrown */
			}
		}
	}
}