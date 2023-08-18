<?php
/**
 * @brief		Alternative Contact Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		09 May 2014
 */

namespace IPS\nexus\Customer;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Alternative Contact Model
 */
class _AlternativeContact extends \IPS\Patterns\ActiveRecord
{	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'nexus_alternate_contacts';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = NULL;
		
	/**
	 * Get main account
	 *
	 * @return	\IPS\nexus\Customer
	 */
	public function get_main_id()
	{
		return \IPS\nexus\Customer::load( $this->_data['main_id'] );
	}
	
	/**
	 * Set main account
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	public function set_main_id( \IPS\Member $member )
	{
		$this->_data['main_id'] = $member->member_id;
	}
	
	/**
	 * Get alternate account
	 *
	 * @return	\IPS\nexus\Customer
	 */
	public function get_alt_id()
	{
		return \IPS\nexus\Customer::load( $this->_data['alt_id'] );
	}
	
	/**
	 * Set alternate account
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	public function set_alt_id( \IPS\Member $member )
	{
		$this->_data['alt_id'] = $member->member_id;
	}
	
	/**
	 * Get purchases
	 *
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public function get_purchases()
	{
		return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_purchases', array( array( 'ps_member=?', $this->main_id->member_id ), \IPS\Db::i()->in( 'ps_id', $this->purchaseIds() ) ) ), 'IPS\nexus\Purchase' );
	}
	
	/**
	 * Get purchase IDs
	 *
	 * @return	array
	 */
	public function purchaseIds()
	{
		return explode( ',', $this->_data['purchases'] );
	}
	
	/**
	 * Set purchases
	 *
	 * @param	array	$purchases	The purchases
	 * @return	void
	 */
	public function set_purchases( array $purchases )
	{
		$this->_data['purchases'] = implode( ',', array_keys( $purchases ) );
	}
	
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		if ( $this->_new )
		{
			$data = $this->_data;
		}
		else
		{
			$data = $this->changed;
		}
		
		foreach ( array_keys( static::$bitOptions ) as $k )
		{
			if ( $this->$k instanceof Bitwise )
			{
				foreach( $this->$k->values as $field => $value )
				{
					$data[ $field ] = \intval( $value );
				}
			}
		}
	
		if ( $this->_new )
		{
			$insert = array();
			if( static::$databasePrefix === NULL )
			{
				$insert = $data;
			}
			else
			{
				$insert = array();
				foreach ( $data as $k => $v )
				{
					$insert[ static::$databasePrefix . $k ] = $v;
				}
			}

			$insertId = static::db()->insert( static::$databaseTable, $insert );
			
			$this->_new = FALSE;
		}
		elseif( !empty( $data ) )
		{
			/* Set the column names with a prefix */
			if( static::$databasePrefix === NULL )
			{
				$update = $data;
			}
			else
			{
				$update = array();
				foreach ( $data as $k => $v )
				{
					$update[ static::$databasePrefix . $k ] = $v;
				}
			}
						
			/* Work out the ID */
			$idColumn = static::$databaseColumnId;

			/* Save */
			static::db()->update( static::$databaseTable, $update, array( 'main_id=? AND alt_id=?', $this->main_id->member_id, $this->alt_id->member_id ) );
			
			/* Reset our log of what's changed */
			$this->changed = array();
		}
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		static::db()->delete( 'nexus_alternate_contacts', array( 'main_id=? AND alt_id=?', $this->_data['main_id'], $this->_data['alt_id'] ) );
	}
}