<?php
/**
 * @brief		File Storage Extension: Advertisement Images
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		14 Aug 2014
 */

namespace IPS\nexus\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Advertisement Images
 */
class _Ads
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', array( 'ad_member<>0 or ad_member IS NULL' ) )->first();
	}
	
	/**
	 * Move stored files
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param	int|NULL	$oldConfiguration		Old storage configuration ID
	 * @throws	\Underflowexception				When file record doesn't exist. Indicating there are no more files to move
	 * @return	void
	 */
	public function move( $offset, $storageConfiguration, $oldConfiguration=NULL )
	{
		$record = \IPS\Db::i()->select( '*', 'core_advertisements', array( 'ad_member<>0 or ad_member IS NULL' ), 'ad_id', array( $offset, 1 ) )->first();
		
		$images = json_decode( $record['ad_images'], TRUE );
		foreach ( $images as $key => $location )
		{
			try
			{
				$images[ $key ] = (string) \IPS\File::get( $oldConfiguration ?: 'nexus_Ads', $location )->move( $storageConfiguration );
			}
			catch( \Exception $e )
			{
				/* Any issues are logged */
			}
		}
		\IPS\Db::i()->update( 'core_advertisements', array( 'ad_images' => json_encode( $images ) ), array( 'ad_id=?', $record['ad_id'] ) );
	}
	
	/**
	 * Fix all URLs
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @return void
	 */
	public function fixUrls( $offset )
	{
		$record = \IPS\Db::i()->select( '*', 'core_advertisements', array( 'ad_member<>0 or ad_member IS NULL' ), 'ad_id', array( $offset, 1 ) )->first();
		
		$images = json_decode( $record['ad_images'], TRUE );
		
		foreach ( $images as $key => $location )
		{
			if ( $new = \IPS\File::repairUrl( $location ) )
			{
				$images[ $key ] = $new;
			}
		}
		
		\IPS\Db::i()->update( 'core_advertisements', array( 'ad_images' => json_encode( $images ) ), array( 'ad_id=?', $record['ad_id'] ) );
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
			foreach ( \IPS\Db::i()->select( '*', 'core_advertisements', array( 'ad_member<>0 or ad_member IS NULL' ) ) as $ad )
			{
				if ( \in_array( (string) $file, json_decode( $ad['ad_images'], TRUE ) ) )
				{
					return TRUE;
				}
			}
		}
		catch ( \UnderflowException $e ) { }
		
		return FALSE;
	}

	/**
	 * Delete all stored files
	 *
	 * @return	void
	 */
	public function delete()
	{
		foreach( \IPS\Db::i()->select( '*', 'core_advertisements', array( 'ad_member<>0 or ad_member IS NULL' ) ) as $ad )
		{
			foreach ( json_decode( $ad['ad_images'], TRUE ) as $key => $location )
			{
				try
				{
					\IPS\File::get( 'nexus_Products', $location )->delete();
				}
				catch( \Exception $e ){}
			}
		}
	}
}