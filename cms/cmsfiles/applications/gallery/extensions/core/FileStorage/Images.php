<?php
/**
 * @brief		File Storage Extension: Images
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Images
 */
class _Images
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images' )->first();
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
		$image	= \IPS\Db::i()->select( '*', 'gallery_images', array(), 'image_id', array( $offset, 1 ) )->first();
		$update	= array();

		if( $image['image_small_file_name'] )
		{
			try
			{
				$file = \IPS\File::get( $oldConfiguration ?: 'gallery_Images', $image['image_small_file_name'] )->move( $storageConfiguration );
				$update['image_small_file_name']	= (string) $file;
			}
			catch( \Exception $e )
			{
				/* Any issues are logged */
			}
		}

		if( $image['image_masked_file_name'] )
		{
			if( $image['image_masked_file_name'] == $image['image_small_file_name'] AND isset( $update['image_small_file_name'] ) )
			{
				$update['image_masked_file_name']	= $update['image_small_file_name'];
			}
			else
			{
				try
				{
					$file = \IPS\File::get( $oldConfiguration ?: 'gallery_Images', $image['image_masked_file_name'] )->move( $storageConfiguration );
					$update['image_masked_file_name']	= (string) $file;
				}
				catch( \Exception $e )
				{
					/* Any issues are logged */
				}
			}
		}

		if( $image['image_original_file_name'] )
		{
			if( $image['image_original_file_name'] == $image['image_masked_file_name'] AND isset( $update['image_masked_file_name'] ) )
			{
				$update['image_original_file_name']	= $update['image_masked_file_name'];
			}
			else
			{
				try
				{
					$file = \IPS\File::get( $oldConfiguration ?: 'gallery_Images', $image['image_original_file_name'] )->move( $storageConfiguration );
					$update['image_original_file_name']	= (string) $file;
				}
				catch( \Exception $e )
				{
					/* Any issues are logged */
				}
			}
		}
		
		if ( \count( $update ) )
		{
			foreach( $update as $k => $v )
			{
				if ( $update[ $k ] == $image[ $k ] )
				{
					unset( $update[ $k ] );
				}
			}
			
			if ( \count( $update ) )
			{
				\IPS\Db::i()->update( 'gallery_images', $update, array( 'image_id=?', $image['image_id'] ) );
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
		$image	= \IPS\Db::i()->select( '*', 'gallery_images', array(), 'image_id', array( $offset, 1 ) )->first();
		$update	= array();

		foreach( array( 'image_small_file_name', 'image_masked_file_name', 'image_original_file_name' ) as $key )
		{
			if( $image[ $key ] )
			{
				if ( $new = \IPS\File::repairUrl( $image[ $key ] ) )
				{
					$update[ $key ] = $new;
				}
			}
		}
				
		if ( \count( $update ) )
		{
			\IPS\Db::i()->update( 'gallery_images', $update, array( 'image_id=?', $image['image_id'] ) );
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
		try
		{
			$record	= \IPS\Db::i()->select( '*', 'gallery_images', array( 'image_masked_file_name=? OR image_original_file_name=? OR image_small_file_name=?', (string) $file, (string) $file, (string) $file ) )->first();

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
		foreach( \IPS\Db::i()->select( '*', 'gallery_images' ) as $image )
		{
			foreach( array( 'image_masked_file_name', 'image_original_file_name', 'image_small_file_name' ) as $size )
			{
				if( $image[ $size ] )
				{
					try
					{
						\IPS\File::get( 'gallery_Images', $image[ $size ] )->delete();
					}
					catch( \Exception $e ){}
				}
			}
		}
	}
}