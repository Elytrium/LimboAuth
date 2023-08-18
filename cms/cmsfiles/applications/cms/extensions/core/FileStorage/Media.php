<?php
/**
 * @brief		File Storage Extension: Media
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		07 July 2015
 */

namespace IPS\cms\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: CMS Media
 */
class _Media
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'cms_media' )->first();
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
		$media = \IPS\Db::i()->select( '*', 'cms_media', array(), 'media_id', array( $offset, 1 ) )->first();

		try
		{
			$file = \IPS\File::get( $oldConfiguration ?: 'cms_Media', $media['media_file_object'] )->move( $storageConfiguration );

			if ( (string) $file != $media['media_file_object'] )
			{
				\IPS\Db::i()->update( 'cms_media', array( 'media_file_object' => (string) $file ), array( 'media_id=?', $media['media_id'] ) );
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
		$media = \IPS\Db::i()->select( '*', 'cms_media', array(), 'media_id', array( $offset, 1 ) )->first();

		if ( $new = \IPS\File::repairUrl( $media['media_file_object'] ) )
		{
			\IPS\Db::i()->update( 'cms_media', array( 'media_file_object' => $new ), array( 'media_id=?', $media['media_id'] ) );
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
			$media = \IPS\Db::i()->select( '*', 'cms_media', array( 'media_file_object=?', (string) $file ) )->first();

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
		foreach( \IPS\Db::i()->select( '*', 'cms_media', 'media_file_object IS NOT NULL' ) as $item )
		{
			try
			{
				\IPS\File::get( 'cms_Media', $item['media_file_object'] )->delete();
			}
			catch( \Exception $e ){}
		}
	}
}