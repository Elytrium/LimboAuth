<?php
/**
 * @brief		File Storage Extension: Clubs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		01 Mar 2017
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Clubs
 */
class _Clubs
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs', 'profile_photo IS NOT NULL' )->first() + \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs', 'cover_photo IS NOT NULL' )->first();
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
		$club = \IPS\Db::i()->select( '*', 'core_clubs', array( 'id > ?', $offset ), 'id', array( 0, 1 ) )->first();
		
		try
		{
			$update = array();
			
			foreach ( array( 'profile_photo', 'cover_photo' ) as $photoKey )
			{
				$file = \IPS\File::get( $oldConfiguration ?: 'core_Clubs', $club[ $photoKey ] )->move( $storageConfiguration );
		
				if ( (string) $file != $club[ $photoKey ] )
				{
					$update[ $photoKey ] = (string) $file;
				}
			}
			
			if ( \count( $update ) )
			{
				\IPS\Db::i()->update( 'core_clubs', $update, array( 'id=?', $club['id'] ) );
			}
		}
		catch( \Exception $e )
		{
			/* Any issues are logged and the \IPS\Db::i()->update not run as the exception is thrown */
		}
		
		return $club['id'];
	}

	/**
	 * Check if a file is valid
	 *
	 * @param	string	$file		The file path to check
	 * @return	bool
	 */
	public function isValidFile( $file )
	{
		try
		{
			$club	= \IPS\Db::i()->select( '*', 'core_clubs', array( 'profile_photo=? OR cover_photo=?', (string) $file, (string) $file ) )->first();

			return TRUE;
		}
		catch ( \UnderflowException $e )
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
		foreach( \IPS\Db::i()->select( '*', 'core_clubs', 'profile_photo IS NOT NULL or cover_photo IS NOT NULL' ) as $club )
		{
			foreach ( array( 'profile_photo', 'cover_photo' ) as $photoKey )
			{
				if ( $club[ $photoKey ] )
				{
					try
					{
						\IPS\File::get( 'core_Clubs', $club[ $photoKey ] )->delete();
					}
					catch( \Exception $e ){}
				}
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
		$club = \IPS\Db::i()->select( '*', 'core_clubs', array( 'id > ?', $offset ), 'id', array( 0, 1 ) )->first();

		try
		{
			$fixed = array();
			
			foreach( array( 'profile_photo', 'cover_photo' ) as $photoKey )
			{
				if ( $club[ $photoKey ] )
				{
					if ( $new = \IPS\File::repairUrl( $club[ $photoKey ] ) )
					{
						$fixed[ $photoKey ] = $new;
					}
				}
			}
			
			if ( \count( $fixed ) )
			{
				\IPS\Db::i()->update( 'core_clubs', $fixed, array( 'id=?', $club['id'] ) );
			}
		}
		catch( \Exception $e )
		{
			/* Any issues are logged and the \IPS\Db::i()->update not run as the exception is thrown */
		}
		
		return $club['id'];
	}
}