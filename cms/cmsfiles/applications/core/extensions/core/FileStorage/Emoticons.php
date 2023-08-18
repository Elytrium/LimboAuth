<?php
/**
 * @brief		File Storage Extension: Emoticons
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Sep 2013
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Emoticons
 */
class _Emoticons
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_emoticons' )->first();
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
		$emoticon = \IPS\Db::i()->select( '*', 'core_emoticons', array(), 'id', array( $offset, 1 ) )->first();
		
		try
		{
			$file = \IPS\File::get( $oldConfiguration ?: 'core_Emoticons', $emoticon['image'] )->move( $storageConfiguration );

			$image_2x = NULL;
			if ( $emoticon['image_2x'] )
			{
				$image_2x = \IPS\File::get( $oldConfiguration ?: 'core_Emoticons', $emoticon['image_2x'] )->move( $storageConfiguration );
			}

			if ( (string) $file != $emoticon['image'] or (string) $image_2x != $emoticon['image_2x'] )
			{
				\IPS\Db::i()->update( 'core_emoticons', array( 'image' => (string) $file, 'image_2x' => (string) $image_2x ), array( 'id=?', $emoticon['id'] ) );
			}
			
			\IPS\Settings::i()->changeValues( array( 'emoji_cache' => time() ) );
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
		$emoticon = \IPS\Db::i()->select( '*', 'core_emoticons', array(), 'id', array( $offset, 1 ) )->first();

		try
		{
			$fixed = array();
			foreach( array( 'image', 'image_2x' ) as $location )
			{
				if ( $new = \IPS\File::repairUrl( $emoticon[ $location ] ) )
				{
					$fixed[ $location ] = $new;
				}
			}

			if ( \count( $fixed ) )
			{
				\IPS\Db::i()->update( 'core_emoticons', $fixed, array( 'id=?', $emoticon['id'] ) );

				\IPS\Settings::i()->changeValues( array( 'emoji_cache' => time() ) );
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
			$emoticon	= \IPS\Db::i()->select( '*', 'core_emoticons', array( 'image=? or image_2x=?', (string) $file, (string) $file ) )->first();

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
		foreach( \IPS\Db::i()->select( '*', 'core_emoticons', 'image IS NOT NULL' ) as $emoticon )
		{
			try
			{
				\IPS\File::get( 'core_Emoticons', $emoticon['image'] )->delete();
				\IPS\File::get( 'core_Emoticons', $emoticon['image_2x'] )->delete();
			}
			catch( \Exception $e ){}
		}
	}
}