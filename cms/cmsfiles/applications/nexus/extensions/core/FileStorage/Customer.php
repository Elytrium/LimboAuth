<?php
/**
 * @brief		File Storage Extension: Customer Fields
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		29 Aug 2014
 */

namespace IPS\nexus\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Customer Fields
 */
class _Customer
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		$where = array();
		foreach ( \IPS\nexus\Customer\CustomField::roots( NULL, NULL, array( 'f_type=?', 'Upload' ) ) as $field )
		{
			$where[] = "field_{$field->id}<>''";
		}
		if ( !\count( $where ) )
		{
			return 0;
		}
		
		return \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customers', implode( ' OR ', $where ) )->first();
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
		$ids = array();
		$where = array();
		foreach ( \IPS\nexus\Customer\CustomField::roots( NULL, NULL, array( 'f_type=?', 'Upload' ) ) as $field )
		{
			$ids[] = $field->id;
			$where[] = "field_{$field->id}<>''";
		}
		if ( !\count( $where ) )
		{
			throw new \Underflowexception;
		}
		
		$customer = \IPS\Db::i()->select( '*', 'nexus_customers', implode( ' OR ', $where ) )->first();
		$update = array();
		foreach ( $ids as $id )
		{
			try
			{
				$update[ 'field_' . $id ] = (string) \IPS\File::get( $oldConfiguration ?: 'nexus_Customer', $update[ 'field_' . $id ] )->move( $storageConfiguration );
			}
			catch( \Exception $e )
			{
				/* Any issues are logged */
			}
		}
		
		if ( \count( $update ) )
		{
			foreach( $update as $k => $v )
			{
				if ( $update[ $k ] == $customer[ $k ] )
				{
					unset( $update[ $k ] );
				}
			}
			
			if ( \count( $update ) )
			{
				\IPS\Db::i()->update( 'nexus_customers', $update, array( 'member_id=?', $customer['member_id'] ) );
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
		$ids = array();
		$where = array();
		foreach ( \IPS\nexus\Customer\CustomField::roots( NULL, NULL, array( 'f_type=?', 'Upload' ) ) as $field )
		{
			$ids[] = $field->id;
			$where[] = "field_{$field->id}<>''";
		}
		if ( !\count( $where ) )
		{
			throw new \Underflowexception;
		}
		
		$customer = \IPS\Db::i()->select( '*', 'nexus_customers', implode( ' OR ', $where ) )->first();
		$update = array();
		foreach ( $ids as $id )
		{
			if ( $new = \IPS\File::repairUrl( $customer[ 'field_' . $id ] ) )
			{
				$update[ 'field_' . $id ] = $new;
			}
		}
		
		if ( \count( $update ) )
		{
			if ( \count( $update ) )
			{
				\IPS\Db::i()->update( 'nexus_customers', $update, array( 'member_id=?', $customer['member_id'] ) );
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
		$_where = array();
		foreach ( \IPS\nexus\Customer\CustomField::roots( NULL, NULL, array( 'f_type=?', 'Upload' ) ) as $field )
		{
			$_where[] = "field_{$field->id}=?";
		}
		if ( !\count( $_where ) )
		{
			return FALSE;
		}
		
		$where = array_fill( 0, \count( $_where ), (string) $file );
		array_unshift( $where, implode( ' OR ', $_where ) );
		
		return (bool) \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customers', $where )->first();
	}

	/**
	 * Delete all stored files
	 *
	 * @return	void
	 */
	public function delete()
	{
		$ids = array();
		$where = array();
		foreach ( \IPS\nexus\Customer\CustomField::roots( NULL, NULL, array( 'f_type=?', 'Upload' ) ) as $field )
		{
			$ids[] = $field->id;
			$where[] = "field_{$field->id}<>''";
		}
		if ( !\count( $where ) )
		{
			return;
		}
		
		foreach ( \IPS\Db::i()->select( '*', 'nexus_customers', implode( ' OR ', $where ) ) as $customer )
		{
			foreach ( $ids as $id )
			{
				try
				{
					\IPS\File::get( 'nexus_Customer', $customer[ 'field_' . $id ] )->delete();
				}
				catch( \Exception $e ){}
			}
		}
	}
}