<?php
/**
 * @brief		File Storage Extension: Blogs (cover photos)
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		16 Jun 2014
 */

namespace IPS\blog\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Blogs (cover photos)
 */
class _Blogs
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs', 'blog_cover_photo IS NOT NULL' )->first();
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
		$record	= \IPS\Db::i()->select( '*', 'blog_blogs', 'blog_cover_photo IS NOT NULL', 'blog_id', array( $offset, 1 ) )->first();
		
		try
		{
			$file	= \IPS\File::get( $oldConfiguration ?: 'blog_Blogs', $record['blog_cover_photo'] )->move( $storageConfiguration );
			
			if ( (string) $file != $record['blog_cover_photo'] )
			{
				\IPS\Db::i()->update( 'blog_blogs', array( 'blog_cover_photo' => (string) $file ), array( 'blog_id=?', $record['blog_id'] ) );
			}
		}
		catch( \Exception $e )
		{
			/* Any issues are logged and the \IPS\Db::i()->update not run as the exception is thrown */
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
		$record	= \IPS\Db::i()->select( '*', 'blog_blogs', 'blog_cover_photo IS NOT NULL', 'blog_id', array( $offset, 1 ) )->first();
		
		if ( $new = \IPS\File::repairUrl( $record['blog_cover_photo'] ) )
		{
			\IPS\Db::i()->update( 'blog_blogs', array( 'blog_cover_photo' => $new ), array( 'blog_id=?', $record['blog_id'] ) );
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
			$record	= \IPS\Db::i()->select( '*', 'blog_blogs', array( 'blog_cover_photo=?', (string) $file ) )->first();

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
		foreach( \IPS\Db::i()->select( '*', 'blog_blogs', 'blog_cover_photo IS NOT NULL' ) as $blog )
		{
			try
			{
				\IPS\File::get( 'blog_Blogs', $blog['blog_cover_photo'] )->delete();
			}
			catch( \Exception $e ){}
		}
	}
}