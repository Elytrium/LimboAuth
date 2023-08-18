<?php
/**
 * @brief		License Key Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		30 Apr 2014
 */

namespace IPS\nexus\Purchase;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * License Key Model
 */
abstract class _LicenseKey extends \IPS\Patterns\ActiveRecord
{
	/**
	 * License Key Types
	 *
	 * @return	array
	 */
	public static function licenseKeyTypes()
	{
		return array(
			'Standard'	=> 'IPS\nexus\Purchase\LicenseKey\Standard',
			'Mdfive'	=> 'IPS\nexus\Purchase\LicenseKey\Mdfive',
		);
	}
		
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'nexus_licensekeys';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'lkey_';
	
	/**
	 * @brief	[ActiveRecord] Database ID Column
	 */
	public static $databaseColumnId = 'key';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'lkey_purchase' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$types		= static::licenseKeyTypes();
		$classname	= $types[ mb_ucfirst( $data['lkey_type'] ) ];

		/* Initiate an object */
		$obj = new $classname;
		$obj->_new = FALSE;
		
		/* Import data */
		foreach ( $data as $k => $v )
		{
			if( static::$databasePrefix AND mb_strpos( $k, static::$databasePrefix ) === 0 )
			{
				$k = \substr( $k, \strlen( static::$databasePrefix ) );
			}

			$obj->_data[ $k ] = $v;
		}
		$obj->changed = array();
		
		/* Init */
		if ( method_exists( $obj, 'init' ) )
		{
			$obj->init();
		}
				
		/* Return */
		return $obj;
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$exploded = explode( '\\', \get_class( $this ) );
		$this->type = mb_strtolower( array_pop( $exploded ) );
		$this->active = TRUE;
		$this->uses = 0;
		$this->activate_data = array();
		$this->generated = new \IPS\DateTime;
	}
	
	/**
	 * Set purchase
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public function set_purchase( \IPS\nexus\Purchase $purchase )
	{
		$this->_data['purchase'] = $purchase->id;
		$this->_data['member'] = $purchase->member->member_id;
	}
	
	/**
	 * Set purchase
	 *
	 * @return	\IPS\nexus\Purchase
	 */
	public function get_purchase()
	{
		return \IPS\nexus\Purchase::load( $this->_data['purchase'] );
	}
	
	/**
	 * Set activate data
	 *
	 * @param	array	$data	The data
	 * @return	void
	 */
	public function set_activate_data( array $data )
	{
		$this->_data['activate_data'] = json_encode( $data );
	}
	
	/**
	 * Set activate data
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_activate_data()
	{
		return json_decode( $this->_data['activate_data'], TRUE );
	}
	
	/**
	 * Set generated time
	 *
	 * @param	\IPS\DateTime	$generated	Generated time
	 * @return	void
	 */
	public function set_generated( \IPS\DateTime $generated )
	{
		$this->_data['generated'] = $generated->getTimestamp();
	}
	
	/**
	 * Set generated time
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_generated()
	{
		return \IPS\DateTime::ts( $this->_data['generated'] );
	}
	
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		if ( !$this->key )
		{
			do
			{
				$this->key = $this->generate();
			}
			while ( \count( \IPS\Db::i()->select( '*', 'nexus_licensekeys', array( 'lkey_key=?', $this->key ) ) ) );
		}
		return parent::save();
	}
}