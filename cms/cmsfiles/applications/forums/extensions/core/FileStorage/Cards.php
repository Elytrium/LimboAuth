<?php
/**
 * @brief		File Storage Extension: Cards
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		24 January 2020
 */

namespace IPS\forums\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Cards
 */
class _Cards
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'forums_forums', 'card_image IS NOT NULL' )->first();
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
		$file = \IPS\Db::i()->select( '*', 'forums_forums', 'card_image IS NOT NULL', 'id', array( $offset, 1 ) )->first();
		
		try
		{
			$forum = \IPS\forums\Forum::constructFromData( $file );
			$forum->icon = \IPS\File::get( $oldConfiguration ?: 'forums_Cards', $forum->icon )->move( $storageConfiguration );
			$forum->save();
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
		$forum = \IPS\forums\Forum::constructFromData( \IPS\Db::i()->select( '*', 'forums_forums', 'card_image IS NOT NULL', 'id', array( $offset, 1 ) )->first() );
		
		if ( $new = \IPS\File::repairUrl( $forum->icon ) )
		{
			$forum->icon = $new;
			$forum->save();
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
			\IPS\Db::i()->select( 'id', 'forums_forums', array( 'card_image=?', $file ) )->first();
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
		foreach( \IPS\Db::i()->select( '*', 'forums_forums', "card_image IS NOT NULL" ) as $forum )
		{
			try
			{
				\IPS\File::get( 'forums_Cards', $forum['icon'] )->delete();
			}
			catch( \Exception $e ){}
		}
	}
}