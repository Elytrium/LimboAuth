<?php
/**
 * @brief		File Storage Extension: Reaction
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Feb 2017
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Reaction
 */
class _Reaction
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_reactions' )->first();
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
		$reaction = \IPS\Db::i()->select( '*', 'core_reactions', array(), 'reaction_id', array( $offset, 1 ) )->first();

		try
		{
			$url = (string) \IPS\File::get( $oldConfiguration ?: 'core_Reaction', $reaction['reaction_icon'] )->move( $storageConfiguration );
		}
		catch( \Exception $e )
		{
			/* Any issues are logged */
		}

		if ( !empty( $url ) AND $url != $reaction['reaction_icon'] )
		{
			\IPS\Db::i()->update( 'core_reactions', array( 'reaction_icon' => $url ), array( 'reaction_id=?', $reaction['reaction_id'] ) );
			unset( \IPS\Data\Store::i()->reactions );
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
			\IPS\Db::i()->select( '*', 'core_reactions', array( "reaction_icon=?", $file ) )->first();
			
			return TRUE;
		}
		catch( \UnderflowException $e )
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
		foreach( \IPS\Db::i()->select( '*', 'core_reactions' ) AS $row )
		{
			\IPS\File::get( 'core_Reaction', $row['reaction_icon'] )->delete();
		}
	}
}