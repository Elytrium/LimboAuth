<?php
/**
 * @brief		File Storage Extension: Badges
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		23 Feb 2021
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Badges
 */
class _Badges
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_badges', 'image IS NOT NULL' )->first();
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
		$badgeData = \IPS\Db::i()->select( '*', 'core_badges', 'image IS NOT NULL', 'id', array( $offset, 1 ) )->first();
		
		try
		{
			$file = \IPS\File::get( $oldConfiguration ?: 'core_Badges', $badgeData['image'] )->move( $storageConfiguration );
			
			if ( (string) $file != $badgeData['image'] )
			{
				\IPS\Db::i()->update( 'core_badges', array( 'image' => (string) $file ), array( 'id=?', $badgeData['id'] ) );
			}
		}
		catch( \Exception $e )
		{
			/* Any issues are logged */
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
		$badgeData = \IPS\Db::i()->select( '*', 'core_badges', 'image IS NOT NULL', 'id', array( $offset, 1 ) )->first();

		try
		{
			if ( $new = \IPS\File::repairUrl( $badgeData['image'] ) )
			{
				\IPS\Db::i()->update( 'core_badges', [ 'image' => $new ], array( 'id=?', $badgeData['id'] ) );
			}
		}
		catch( \Exception $e ) { }
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
			$badgeData	= \IPS\Db::i()->select( '*', 'core_badges', array( 'image=?', (string) $file ) )->first();

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
		foreach( \IPS\Db::i()->select( '*', 'core_badges', 'image IS NOT NULL' ) as $badgeData )
		{
			try
			{
				\IPS\File::get( 'core_Badges', $badgeData['image'] )->delete();
			}
			catch( \Exception $e ){}
		}
	}
}