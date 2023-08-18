<?php
/**
 * @brief		File Storage Extension: Screenshots
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		08 Oct 2013
 */

namespace IPS\downloads\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Screenshots
 */
class _Screenshots
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'downloads_files_records', array( 'record_type=?', 'ssupload' ) )->first();
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
		$record		= \IPS\Db::i()->select( '*', 'downloads_files_records', array( 'record_type=?', 'ssupload' ), 'record_id', array( $offset, 1 ) )->first();
		$updates	= array();

		try
		{
			$file = \IPS\File::get( $oldConfiguration ?: 'downloads_Screenshots', $record['record_location'] )->move( $storageConfiguration );

			if ( (string) $file != $record['record_location'] )
			{
				$updates['record_location'] = (string) $file;
			}
			
			$file = \IPS\File::get( $oldConfiguration ?: 'downloads_Screenshots', $record['record_thumb'] )->move( $storageConfiguration );
			
			if ( (string) $file != $record['record_thumb'] )
			{
				$updates['record_thumb'] = (string) $file;
			}

			if( $record['record_no_watermark'] )
			{
				$file = \IPS\File::get( $oldConfiguration ?: 'downloads_Screenshots', $record['record_no_watermark'] )->move( $storageConfiguration );
				
				if ( (string) $file != $record['record_no_watermark'] )
				{
					$updates['record_no_watermark'] = (string) $file;
				}
			}
		}
		catch( \Exception $e )
		{
			/* Any issues are logged */
		}

		if( \count( $updates ) )
		{
			\IPS\Db::i()->update( 'downloads_files_records', $updates, array( 'record_id=?', $record['record_id'] ) );
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
		$record = \IPS\Db::i()->select( '*', 'downloads_files_records', array( 'record_type=?', 'ssupload' ), 'record_id', array( $offset, 1 ) )->first();
		
		if ( $new = \IPS\File::repairUrl( $record['record_location'] ) )
		{
			\IPS\Db::i()->update( 'downloads_files_records', array( 'record_location' => $new ), array( 'record_id=?', $record['record_id'] ) );
		}
		
		if ( $record['record_thumb'] and $new = \IPS\File::repairUrl( $record['record_thumb'] ) )
		{
			\IPS\Db::i()->update( 'downloads_files_records', array( 'record_thumb' => $new ), array( 'record_id=?', $record['record_id'] ) );
		}

		if ( $record['record_no_watermark'] and $new = \IPS\File::repairUrl( $record['record_no_watermark'] ) )
		{
			\IPS\Db::i()->update( 'downloads_files_records', array( 'record_no_watermark' => $new ), array( 'record_id=?', $record['record_id'] ) );
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
			$fileName = (string) $file;
			$record	= \IPS\Db::i()->select( '*', 'downloads_files_records', array( '( record_location=? OR record_thumb=? OR record_no_watermark=? ) AND record_type=?', $fileName, $fileName, $fileName, 'ssupload' ) )->first();

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
		foreach( \IPS\Db::i()->select( '*', 'downloads_files_records', "record_location IS NOT NULL and record_type='ssupload'" ) as $screenshot )
		{
			try
			{
				\IPS\File::get( 'downloads_Screenshots', $screenshot['record_location'] )->delete();
			}
			catch( \Exception $e ){}

			if( $screenshot['record_thumb'] )
			{
				try
				{
					\IPS\File::get( 'downloads_Screenshots', $screenshot['record_thumb'] )->delete();
				}
				catch( \Exception $e ){}
			}

			if( $screenshot['record_no_watermark'] )
			{
				try
				{
					\IPS\File::get( 'downloads_Screenshots', $screenshot['record_no_watermark'] )->delete();
				}
				catch( \Exception $e ){}
			}
		}
	}
}