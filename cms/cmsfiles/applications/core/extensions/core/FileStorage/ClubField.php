<?php
/**
 * @brief		File Storage Extension: ClubField
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		11 Aug 2017
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: ClubField
 */
class _ClubField
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		$count = 0;
		foreach( \IPS\Db::i()->select( '*', 'core_clubs_fields', array( "f_type=?", 'Upload' ) ) AS $row )
		{
			$count += \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs_fieldvalues', array( "field_{$row['f_id']}<>? AND field_{$row['f_id']} IS NOT NULL", '' ) )->first();
		}
		return $count;
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
		foreach( \IPS\Db::i()->select( '*', 'core_clubs_fields', array( "f_type=?", 'Upload' ) ) AS $row )
		{
			foreach( \IPS\Db::i()->select( '*', 'core_clubs_fieldvalues', array( "field_{$row['f_id']}<>? AND field_{$row['f_id']} IS NOT NULL", '' ) ) AS $field )
			{
				try
				{
					$moved = \IPS\File::get( $oldConfiguration ?: 'core_ClubField', $field[ "field_{$row['f_id']}" ] )->move( $storageConfiguration );
					\IPS\Db::i()->update( 'core_clubs_fieldvalues', array( "field_{$row['f_id']}" => (string) $moved ), array( "club_id=?", $field['club_id'] ) );
				}
				catch( \Exception $e )
				{
					// Any issues are logged
				}
				catch( \Throwable $e )
				{
					// Any issues are logged
				}
			}
		}
		
		throw new \UnderflowException;
	}
	
	/**
	 * Fix all URLs
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @return void
	 */
	public function fixUrls( $offset )
	{
		foreach( \IPS\Db::i()->select( '*', 'core_clubs_fields', array( "f_type=?", 'Upload' ) ) AS $row )
		{
			foreach( \IPS\Db::i()->select( '*', 'core_clubs_fieldvalues', array( "field_{$row['f_id']}<>? AND field_{$row['f_id']} IS NOT NULL" ) ) AS $field )
			{
				$new = \IPS\File::repairUrl( $field[ "field_{$row['f_id']}" ] );
				
				\IPS\Db::i()->update( 'core_clubs_fieldvalues', array( "field_{$row['f_id']}" => $new ), array( "club_id=?", $field['club_id'] ) );
			}
		}
		
		throw new \UnderflowException;
	}

	/**
	 * Check if a file is valid
	 *
	 * @param	string	$file		The file path to check
	 * @return	bool
	 */
	public function isValidFile( $file )
	{
		foreach( \IPS\Db::i()->select( '*', 'core_clubs_fields', array( "f_type=?", 'Upload' ) ) AS $row )
		{
			if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs_fieldvalues', array( "field_{$row['f_id']}=?", (string) $file ) )->first() )
			{
				return TRUE;
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
		foreach( \IPS\Db::i()->select( '*', 'core_clubs_fields', array( "f_type=?", 'Upload' ) ) AS $row )
		{
			foreach( \IPS\Db::i()->select( '*', 'core_clubs_fieldvalues', array( "field_{$row['f_id']}<>? AND field_{$row['f_id']} IS NOT NULL" ) ) AS $field )
			{
				try
				{
					\IPS\File::get( 'core_ClubField', $field[ "field_{$row['f_id']}" ] )->delete();
				}
				catch( \OutOfRangeException $e ) {}
				
				\IPS\Db::i()->update( 'core_clubs_fieldvalues', array( "field_{$row['f_id']}" => NULL ), array( "club_id=?", $field['club_id'] ) );
			}
		}
	}
}