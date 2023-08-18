<?php
/**
 * @brief		File Storage Extension: Promote
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Feb 2017
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Promote
 */
class _Promote
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'MAX(promote_id)', 'core_social_promote', array( "promote_media != '[]'" ) )->first();
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
		$data = \IPS\Db::i()->select( '*', 'core_social_promote', array( "promote_media != '[]' and promote_id >?", $offset ), 'promote_id', array( 0, 1 ) )->first();
		$update = array();

		foreach( json_decode( $data['promote_media'], TRUE ) as $location )
		{
			if ( $location )
			{
				try
				{
					$update[] = (string) \IPS\File::get( $oldConfiguration ?: 'core_Promote', $location )->move( $storageConfiguration );
				}
				catch( \Exception $e )
				{
					/* Any issues are logged */
				}
			}
		}
		if ( $update )
		{
			\IPS\Db::i()->update( 'core_social_promote', array( 'promote_media' => json_encode( $update ) ), array( 'promote_id=?', $data['promote_id'] ) );
		}

		return $data['promote_id'];
	}

	/**
	 * Fix all URLs
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @return void
	 */
	public function fixUrls( $offset )
	{
		$data = \IPS\Db::i()->select( '*', 'core_social_promote', array( "promote_media!=?", '[]' ), 'promote_id', array( $offset, 1 ) )->first();
		$fixed = array();

		foreach( json_decode( $data['promote_media'], TRUE ) as $location )
		{
			if ( $new = \IPS\File::repairUrl( $location ) )
			{
				$fixed[] = $new;
			}
		}
		
		if ( \count( $fixed ) )
		{
			\IPS\Db::i()->update( 'core_social_promote', array( 'promote_media' => json_encode( $fixed ), array( 'promote_id=?', $data['promote_id'] ) ) );
		}
				
		return $data['promote_id'];
	}
	
	/**
	 * Check if a file is valid
	 *
	 * @param	string	$file		The file path to check
	 * @return	bool
	 */
	public function isValidFile( $file )
	{
		foreach( \IPS\Db::i()->select( '*', 'core_social_promote', array( "promote_media!=?", '[]' ), 'promote_id' ) as $data )
		{
			foreach( json_decode( $data['promote_media'], TRUE ) as $location )
			{
				if ( (string) $file === $location )
				{
					return TRUE;
				}
			}
		}
		
		return FALSE;
	}

	/**
	 * Delete all stored files
	 *
	 * @return	void
	 */
	public function delete()
	{
		foreach( \IPS\Db::i()->select( '*', 'core_social_promote', array( "promote_media!=?", '[]' ), 'promote_id' ) as $data )
		{
			foreach( json_decode( $data['promote_media'], TRUE ) as $location )
			{
				try
				{
					\IPS\File::get( 'core_Promote', $location )->delete();
				}
				catch( \Exception $e ){}
			}
		}
	}
}