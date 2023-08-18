<?php
/**
 * @brief		File Storage Extension: FileField
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		29 Aug 2014
 */

namespace IPS\downloads\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: FileField
 */
class _FileField
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		$count = 0;
		
		foreach( \IPS\Db::i()->select( '*', 'downloads_cfields', array( 'cf_type=?', 'Upload' ) ) AS $field )
		{
			$count += \IPS\Db::i()->select( 'COUNT(*)', 'downloads_ccontent', array( "field_{$field['cf_id']}<>? OR field_{$field['cf_id']} IS NOT NULL", '' ) )->first();
		}
		
		return $count;
	}
	
	/**
	 * Move stored files
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param	int|NULL	$oldConfiguration		Old storage configuration ID
	 * @throws	\Underflowexception				When file record doesn't exist. Indicating there are no more files to move
	 * @return	void								FALSE when there are no more files to move
	 */
	public function move( $offset, $storageConfiguration, $oldConfiguration=NULL )
	{
        if( !\IPS\Db::i()->select( 'COUNT(*)', 'downloads_cfields', array( 'cf_type=?', 'Upload' ) )->first() )
        {
            throw new \Underflowexception;
        }

		foreach( \IPS\Db::i()->select( '*', 'downloads_cfields', array( 'cf_type=?', 'Upload' ) ) AS $field )
		{
			$cfield	= \IPS\Db::i()->select( '*', 'downloads_ccontent', array( "field_{$field['cf_id']}<>? OR field_{$field['cf_id']} IS NOT NULL", '' ), 'file_id', array( $offset, 1 ) )->first();
			
			try
			{
				$file = \IPS\File::get( $oldConfiguration ?: 'downloads_FileField', $cfield[ 'field_' . $field['cf_id'] ] )->move( $storageConfiguration );
				
				if ( (string) $file != $cfield[ 'field_' . $field['cf_id'] ] )
				{
					\IPS\Db::i()->update( 'downloads_ccontent', array( "field_{$field['cf_id']}=?", (string) $file ), array( 'file_id=?', $cfield['file_id'] ) );
				}
			}
			catch( \Exception $e )
			{
				/* Any issues are logged */
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
		if( !\IPS\Db::i()->select( 'COUNT(*)', 'downloads_cfields', array( 'cf_type=?', 'Upload' ) )->first() )
        {
            throw new \Underflowexception;
        }

		foreach( \IPS\Db::i()->select( '*', 'downloads_cfields', array( 'cf_type=?', 'Upload' ) ) AS $field )
		{
			$cfield	= \IPS\Db::i()->select( '*', 'downloads_ccontent', array( "field_{$field['cf_id']}<>? OR field_{$field['cf_id']} IS NOT NULL", '' ), 'file_id', array( $offset, 1 ) )->first();
			
			if ( $new = \IPS\File::repairUrl( $cfield[ 'field_' . $field['cf_id'] ] ) )
			{
				\IPS\Db::i()->update( 'downloads_ccontent', array( "field_{$field['cf_id']}" => $new ), array( 'file_id=?', $cfield['file_id'] ) );
			}
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
		$valid = FALSE;
		foreach( \IPS\Db::i()->select( '*', 'downloads_cfields', array( 'cf_type=?', 'Upload' ) ) AS $field )
		{
			try
			{
				\IPS\Db::i()->select( '*', 'downloads_ccontent', array( "field_{$field['cf_id']}=?", (string) $file ) )->first();
				
				$valid = TRUE;
				break;
			}
			catch( \UnderflowException $e ) {}
		}
		
		return $valid;
	}

	/**
	 * Delete all stored files
	 *
	 * @return	void
	 */
	public function delete()
	{
		foreach( \IPS\Db::i()->select( '*', 'downloads_cfields', array( 'cf_type=?', 'Upload' ) ) AS $field )
		{
			try
			{
				foreach( \IPS\Db::i()->select( '*', 'downloads_ccontent', array( "field_{$field['cf_id']}<>? OR field_{$field['cf_id']} IS NOT NULL", '' ) ) as $cfield )
				{
					\IPS\File::get( 'downloads_FileField', $cfield[ 'field_' . $field['cf_id'] ] )->delete();
				}
			}
			catch( \Exception $e ){}
		}
	}
}