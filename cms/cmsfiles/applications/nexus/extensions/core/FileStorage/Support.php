<?php
/**
 * @brief		File Storage Extension: Support Custom Fields
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		17 Apr 2014
 */

namespace IPS\nexus\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Support Custom Fields
 */
class _Support
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_fields', array( 'sf_type=?', 'upload' ) )->first();
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
		$customFields = \IPS\nexus\Support\CustomField::roots( NULL, NULL, array( 'sf_type=?', 'upload' ) );
		if ( \count( $customFields ) )
		{
			$where = array();
			$departments = array();
			foreach ( $customFields as $field )
			{
				if ( $field->departments and $field->departments !== '*' )
				{
					$departments = array_merge( $departments, explode( ',', $field->departments ) );
				}
				else
				{
					$where = NULL;
					break;
				}
			}
			if ( $where !== NULL )
			{
				$where = \IPS\Db::i()->in( 'r_department', array_unique( $departments ) );
			}
			
			$request = \IPS\Db::i()->select( '*', 'nexus_support_requests', $where, 'r_id', array( $offset, 1 ) )->first();
			
			$fieldValues = json_decode( $request['r_cfields'], TRUE );
			foreach ( $fieldValues as $k => $v )
			{
				if ( array_key_exists( $k, $customFields ) )
				{
					try
					{
						$fieldValues[ $k ] = \IPS\File::get( $oldConfiguration ?: 'nexus_Support', $fieldValues[ $k ] )->move( $storageConfiguration );
					}
					catch( \Exception $e )
					{
						/* Any issues are logged */
					}
				}
			}
			
			\IPS\Db::i()->update( 'nexus_support_requests', array( 'r_cfields' => json_encode( $fieldValues ) ), array( 'r_id=?', $request['r_id'] ) );
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
		$customFields = \IPS\nexus\Support\CustomField::roots( NULL, NULL, array( 'sf_type=?', 'upload' ) );
		if ( \count( $customFields ) )
		{
			$where = array();
			$departments = array();
			foreach ( $customFields as $field )
			{
				if ( $field->departments and $field->departments !== '*' )
				{
					$departments = array_merge( $departments, explode( ',', $field->departments ) );
				}
				else
				{
					$where = NULL;
					break;
				}
			}
			if ( $where !== NULL )
			{
				$where = \IPS\Db::i()->in( 'r_department', array_unique( $departments ) );
			}
			
			$request = \IPS\Db::i()->select( '*', 'nexus_support_requests', $where, 'r_id', array( $offset, 1 ) )->first();
			
			$fieldValues = json_decode( $request['r_cfields'], TRUE );
			foreach ( $fieldValues as $k => $v )
			{
				if ( array_key_exists( $k, $customFields ) )
				{
					if ( $new = \IPS\File::repairUrl( $fieldValues[ $k ] ) )
					{
						$fieldValues[ $k ] = $new;
					}
				}
			}
			
			\IPS\Db::i()->update( 'nexus_support_requests', array( 'r_cfields' => json_encode( $fieldValues ) ), array( 'r_id=?', $request['r_id'] ) );
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
		$customFields = \IPS\nexus\Support\CustomField::roots( NULL, NULL, array( 'sf_type=?', 'upload' ) );
		if ( \count( $customFields ) )
		{
			foreach ( \IPS\Db::i()->select( '*', 'nexus_support_requests', array( "r_cfields LIKE ?", "%" . str_replace( '\\', '\\\\\\', trim( json_encode( (string) $file ), '"' ) . "%" ) ) ) as $request )
			{
				$fieldValues = json_decode( $request['r_cfields'], TRUE );
				foreach ( $customFields as $field )
				{
					if ( $fieldValues[ $field->id ] == (string) $file )
					{
						return TRUE;
					}
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
		$customFields = \IPS\nexus\Support\CustomField::roots( NULL, NULL, array( 'sf_type=?', 'upload' ) );
		if ( \count( $customFields ) )
		{
			$where = array();
			$departments = array();
			foreach ( $customFields as $field )
			{
				if ( $field->departments and $field->departments !== '*' )
				{
					$departments = array_merge( $departments, explode( ',', $departments ) );
				}
				else
				{
					$where = NULL;
					break;
				}
			}
			if ( $where !== NULL )
			{
				$where = \IPS\Db::i()->in( 'r_department', array_unique( $departments ) );
			}

			foreach( \IPS\Db::i()->select( '*', 'nexus_support_requests', $where ) as $request )
			{
				$fieldValues = json_decode( $request['r_cfields'], TRUE );
				foreach ( $fieldValues as $k => $v )
				{
					if ( array_key_exists( $k, $customFields ) )
					{
						try
						{
							\IPS\File::get( 'nexus_Support', $fieldValues[ $k ] )->delete();
						}
						catch( \Exception $e ){}
					}
				}
			}
		}
	}
}