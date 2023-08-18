<?php
/**
 * @brief		Customer Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		11 Feb 2014
 */

namespace IPS\nexus;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Customer Model
 */
class _Customer extends \IPS\Member
{
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	Cached logged in member
	 */
	public static $loggedInMember	= NULL;
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'member_id';

	/**
	 * Get logged in member
	 *
	 * @return	\IPS\Member
	 */
	public static function loggedIn()
	{
		/* If we haven't loaded the member yet, or if the session member has changed since we last loaded the member, reload and cache */
		if( static::$loggedInMember === NULL )
		{
			static::$loggedInMember = static::load( parent::loggedIn()->member_id );
		}

		return static::$loggedInMember;
	}

	/**
	 * Construct Load Query
	 *
	 * @param	int|string	$id					ID
	 * @param	string		$idField			The database column that the $id parameter pertains to
	 * @param	mixed		$extraWhereClause	Additional where clause(s)
	 * @return	\IPS\Db\Select
	 */
	protected static function constructLoadQuery( $id, $idField, $extraWhereClause )
	{
		$where = array( array( 'core_members.' . $idField . '=?', $id ) );
		if( $extraWhereClause !== NULL )
		{
			if ( !\is_array( $extraWhereClause ) or !\is_array( $extraWhereClause[0] ) )
			{
				$extraWhereClause = array( $extraWhereClause );
			}
			$where = array_merge( $where, $extraWhereClause );
		}
		
		return static::db()->select( '*, core_members.member_id AS _member_id', static::$databaseTable, $where )->join( 'nexus_customers', 'nexus_customers.member_id=core_members.member_id' );
	}
	
	/**
	 * Load Record
	 *
	 * @see		\IPS\Db::build
	 * @param	int|string	$id					ID
	 * @param	string		$idField			The database column that the $id parameter pertains to (NULL will use static::$databaseColumnId)
	 * @param	mixed		$extraWhereClause	Additional where clause(s) (see \IPS\Db::build for details)
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function load( $id, $idField=NULL, $extraWhereClause=NULL )
	{
		/* Guests */
		if( $id === NULL OR $id === 0 OR $id === '' )
		{
			$classname = \get_called_class();
			return new $classname;
		}
		
		/* If we didn't specify an ID field, assume the default */
		if( $idField === NULL )
		{
			$idField = static::$databasePrefix . static::$databaseColumnId;
		}
		
		/* If we did, check it's valid */
		elseif( !\in_array( $idField, static::$databaseIdFields ) )
		{
			throw new \InvalidArgumentException;
		}
				
		/* Does that exist in the multiton store? */
		if( $idField === static::$databasePrefix . static::$databaseColumnId and !empty( static::$multitons[ $id ] ) )
		{
			return static::$multitons[ $id ];
		}
		
		/* If not, find it */
		else
		{
			/* Load it */
			try
			{
				$row = static::constructLoadQuery( $id, $idField, $extraWhereClause )->first();
			}
			catch ( \UnderflowException $e )
			{
				throw new \OutOfRangeException;
			}
			
			/* If it doesn't exist in the multiton store, set it */
			if( !isset( static::$multitons[ $row[ static::$databasePrefix . static::$databaseColumnId ] ] ) )
			{
				static::$multitons[ $row['_member_id'] ] = static::constructFromData( $row );
			}
			
			/* And return it */
			return static::$multitons[ $row['_member_id'] ];
		}
	}
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		if ( isset( $data['_member_id'] ) )
		{
			$data['member_id'] = $data['_member_id'];
			unset( $data['_member_id'] );
		}

		/* If this was guest data there may be no member_id set, which will cause an undefined index */
		if( !isset( $data['member_id'] ) )
		{
			$data['member_id']	= 0;
		}
		
		return parent::constructFromData( $data, $updateMultitonStoreIfExists );
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->_data['cm_first_name'] = '';
		$this->_data['cm_last_name'] = '';
		parent::setDefaultValues();
	}
	
	/**
	 * Get customer name
	 *
	 * @return	string
	 */
	public function get_cm_name()
	{
		return ( $this->cm_first_name or $this->cm_last_name ) ? "{$this->cm_first_name} {$this->cm_last_name}" : $this->name;
	}
	
	/**
	 * Get account credit
	 *
	 * @return	array
	 */
	public function get_cm_credits()
	{
		$amounts = $this->member_id ? json_decode( $this->_data['cm_credits'], TRUE ) : array();
		$return = array();
		foreach ( \IPS\nexus\Money::currencies() as $currency )
		{
			if ( isset( $amounts[ $currency ] ) )
			{
				$return[ $currency ] = new \IPS\nexus\Money( $amounts[ $currency ], $currency );
			}
			else
			{
				$return[ $currency ] = new \IPS\nexus\Money( 0, $currency );
			}
		}
		return $return;
	}
	
	/**
	 * Set account credit
	 *
	 * @param	array	$amounts	Amounts
	 * @return	void
	 */
	public function set_cm_credits( $amounts )
	{
		$save = array();
		foreach ( $amounts as $amount )
		{
			$save[ $amount->currency ] = $amount->amountAsString();
		}
		$this->_data['cm_credits'] = json_encode( $save );
	}
	
	/**
	 * Get profiles
	 *
	 * @return	array
	 */
	public function get_cm_profiles()
	{
		return ( isset( $this->_data['cm_profiles'] ) and $this->_data['cm_profiles'] ) ? json_decode( $this->_data['cm_profiles'], TRUE ) : array();
	}
	
	/**
	 * Set profiles
	 *
	 * @param	array	$profiles	Profiles
	 * @return	array
	 */
	public function set_cm_profiles( $profiles )
	{
		$this->_data['cm_profiles'] = json_encode( $profiles );
	}
	
	/**
	 * Get default currency
	 *
	 * @return	void
	 */
	public function defaultCurrency()
	{
		if ( $currencies = json_decode( \IPS\Settings::i()->nexus_currency, TRUE ) )
		{
			foreach ( $currencies as $k => $v )
			{
				if ( \in_array( $this->language()->id, $v ) )
				{
					return $k;
				}
			}
			
			$keys = array_keys( $currencies );
			return array_shift( $keys );
		}
		else
		{
			return \IPS\Settings::i()->nexus_currency;
		}
	}

	/**
	 * @brief	Cache primary billing address in case the method is called multiple times
	 */
	protected $primaryBillingAddress = FALSE;
	
	/**
	 * Get primary billing address, if one exists
	 *
	 * @return	\IPS\GeoLocation|NULL
	 */
	public function primaryBillingAddress()
	{
		if( $this->primaryBillingAddress === FALSE )
		{
			try
			{
				$this->primaryBillingAddress = \IPS\GeoLocation::buildFromJson( \IPS\Db::i()->select( 'address', 'nexus_customer_addresses', array( '`member`=? AND primary_billing=1', $this->member_id ) )->first() );
			}
			catch ( \UnderflowException $e )
			{
				$this->primaryBillingAddress = NULL;
			}
		}

		return $this->primaryBillingAddress;
	}
	
	/**
	 * Estimated location
	 *
	 * @return	\IPS\GeoLocation|NULL
	 */
	public function estimatedLocation()
	{
		if ( $this->member_id === \IPS\nexus\Customer::loggedIn()->member_id )
		{
			if ( isset( \IPS\Request::i()->cookie['location'] ) AND ( !\IPS\Request::i()->cookie['location'] OR ( \IPS\Request::i()->cookie['location'] AND $data = json_decode( \IPS\Request::i()->cookie['location'], true ) AND array_key_exists( 'member_id', $data ) AND $data['member_id'] == $this->member_id ) ) )
			{
				$location = NULL;

				if( \IPS\Request::i()->cookie['location'] AND $data = json_decode( \IPS\Request::i()->cookie['location'], true ) AND $data['member_id'] == $this->member_id )
				{
					$location = \IPS\GeoLocation::buildFromJson( \IPS\Request::i()->cookie['location'] );
				}
			}
			else
			{
				$location = $this->primaryBillingAddress();
				if ( !$location )
				{
					try
					{
						$location = \IPS\GeoLocation::getByIp( \IPS\Request::i()->ipAddress() );
						\IPS\Request::i()->setCookie( 'location', $this->_addMemberToAddress( $location ) );
					}
					catch ( \Exception $e )
					{
						if( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
						{
							$languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
							$exploded = explode( '-', array_shift( $languages ) );

							if ( \in_array( mb_strtoupper( $exploded[1] ), \IPS\GeoLocation::$countries ) )
							{
								$location = new \IPS\GeoLocation;
								$location->country = mb_strtoupper( $exploded[1] );
								\IPS\Request::i()->setCookie( 'location', $this->_addMemberToAddress( $location ) );
							}
							else
							{
								$location = NULL;
								\IPS\Request::i()->setCookie( 'location', '' );
							}
						}
						else
						{
							$location = NULL;
							\IPS\Request::i()->setCookie( 'location', '' );
						}
					}
				}
			}
		}
		else
		{
			$location = $this->primaryBillingAddress();
		}
		
		return $location;
	}

	/**
	 * Add the member ID to the address object so we can validate it on subsequent requests
	 *
	 * @param	\IPS\GeoLocation	$location	Location object
	 * @return	string
	 */
	protected function _addMemberToAddress( $location )
	{
		return json_encode( array_merge( json_decode( json_encode( $location ), true ), array( 'member_id' => $this->member_id ) ) );
	}
	
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		$data = $this->_data;
		
		$customerTable = array();
		foreach ( ( $this->_new ? $this->_data : $this->changed ) as $k => $v )
		{
			if ( ( mb_substr( $k, 0, 3 ) === 'cm_' and !\in_array( $k, array( 'cm_credits', 'cm_no_sev', 'cm_return_group', 'cm_reg' ) ) ) or mb_substr( $k, 0, 6 ) === 'field_'  )
			{
				$customerTable[ $k ] = $v;
				unset( $this->_data[ $k ] );
				unset( $this->changed[ $k ] );
			}
		}
				
		parent::save();
		$data['member_id'] = $this->_data['member_id'];
		$this->_data = $data;
		
		if ( \count( $customerTable ) )
		{
			$customerTable['member_id'] = $this->member_id;
			\IPS\Db::i()->insert( 'nexus_customers', $customerTable, TRUE );
		}
	}
	
	/**
	 * Log Action
	 *
	 * @param	string	$type	Log type
	 * @param	mixed	$extra	Any extra data for the type
	 * @param	mixed	$by		The member performing the action. NULL for currently logged in member or FALSE for no member
	 * @return	void
	 */
	public function log( $type, $extra=NULL, $by=NULL )
	{
		$this->logHistory( 'nexus', $type, $extra, $by, TRUE );
	}
	
	/**
	 * Get total amount spent
	 *
	 * @return	string
	 */
	public function totalSpent()
	{
		$return = array();
		foreach ( \IPS\Db::i()->select( 't_currency, ( SUM(t_amount)-SUM(t_partial_refund) ) AS amount', 'nexus_transactions', array( 't_member=? AND ( t_status=? OR t_status=? )', $this->member_id, \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_PART_REFUNDED ), NULL, NULL, 't_currency' ) as $amount )
		{
			$return[] = (string) new \IPS\nexus\Money( $amount['amount'], $amount['t_currency'] );
		}
		return \count( $return ) ? implode( ' + ', $return ) : new \IPS\nexus\Money( 0, $this->defaultCurrency() );
	}
	
	/**
	 * @brief	Number of previous purchases by package ID
	 */
	protected $previousPurchasesCount;
	
	/**
	 * Get number of previous purchases of a package ID (used to calculate loyalty discounts)
	 *
	 * @param	int		$packageID	Package ID
	 * @param	bool	$activeOnly	Active only?
	 * @return	array
	 */
	public function previousPurchasesCount( $packageID, $activeOnly )
	{
		if ( $this->previousPurchasesCount === NULL )
		{			
			$this->previousPurchasesCount['all'] = iterator_to_array( \IPS\Db::i()->select( 'ps_item_id, COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_member=?', 'nexus', 'package', $this->member_id ), NULL, NULL, 'ps_item_id' )->setKeyField('ps_item_id')->setValueField('COUNT(*)') );
			$this->previousPurchasesCount['active'] = iterator_to_array( \IPS\Db::i()->select( 'ps_item_id, COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_member=? AND ps_active=1', 'nexus', 'package', $this->member_id ), NULL, NULL, 'ps_item_id' )->setKeyField('ps_item_id')->setValueField('COUNT(*)') );
		}
				
		return isset( $this->previousPurchasesCount[ $activeOnly ? 'active' : 'all' ][ $packageID ] ) ? $this->previousPurchasesCount[ $activeOnly ? 'active' : 'all' ][ $packageID ] : 0;
	}
	
	/**
	 * Client Area Links
	 *
	 * @return	array
	 */
	public function clientAreaLinks()
	{
		$return = array( 'invoices' );
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_member=? AND ps_show=1', $this->member_id ) ) )
		{
			$return[] = 'purchases';
		}
		
		$return[] = 'addresses';
		if ( \count( \IPS\nexus\Gateway::cardStorageGateways() ) or \count( \IPS\nexus\Gateway::otherStoredPaymentMethodGateways() ) )
		{
			$return[] = 'cards';
		}
		if ( \count( \IPS\nexus\Customer\CustomField::roots() ) )
		{
			$return[] = 'info';
		}
		
		if ( \IPS\Settings::i()->nexus_min_topup or \count( json_decode( \IPS\Settings::i()->nexus_payout, TRUE ) ) )
		{
			$return[] = 'credit';
		}
		
		$return[] = 'alternatives';
		
		if ( \count( \IPS\nexus\Donation\Goal::roots() ) )
		{
			$return[] = 'donations';
		}
		
		if ( \IPS\Settings::i()->ref_on )
		{
			$return[] = 'referrals';
		}
		
		return $return;
	}
	
	/**
	 * Alternative Contacts
	 *
	 * @param	array	$where	WHERE clause
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public function alternativeContacts( $where = array() )
	{
		$where = \count( $where ) ? array( $where ) : $where;
		$where[] = array( 'main_id=?', $this->member_id );
		return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_alternate_contacts', $where )->setKeyField( 'alt_id' ), 'IPS\nexus\Customer\AlternativeContact' );
	}
	
	/**
	 * Parent Contacts
	 *
	 * @param	array	$where	WHERE clause
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public function parentContacts( $where = array() )
	{
		$where = \count( $where ) ? array( $where ) : $where;
		$where[] = array( 'alt_id=?', $this->member_id );
		return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_alternate_contacts', $where )->setKeyField( 'main_id' ), 'IPS\nexus\Customer\AlternativeContact' );
	}
	
	/**
	 * ACP Customer Page URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function acpUrl()
	{
		return parent::acpUrl()->setQueryString( 'tab', 'nexus_Main' );
	}

	/**
	 * ACP Customer Page URL
	 *
	 * @param	\IPS\Math\Number	$amount	 	Adjustment amount
	 * @param	string				$currency	Currency code
	 * @param	bool				$refund		Should the spend be reduced?
	 *
	 * @return	void
	 */
	public function updateSpend( \IPS\Math\Number $amount, $currency, $refund=false )
	{
		try
		{
			$currentSpend = \IPS\Db::i()->select( 'spend_amount', 'nexus_customer_spend', array( "spend_member_id=? AND spend_currency=?", $this->member_id, $currency ) )->first();
			$newSpend = new \IPS\Math\Number( number_format( $currentSpend, \IPS\nexus\Money::numberOfDecimalsForCurrency( $currency ), '.', '' ) );
			$newSpend = ( $refund ) ? $newSpend->subtract( $amount ) : $newSpend->add( $amount );
			\IPS\Db::i()->replace( 'nexus_customer_spend', array( 'spend_member_id' => $this->member_id, 'spend_amount' => $newSpend, 'spend_currency' => $currency ) );
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Db::i()->insert( 'nexus_customer_spend', array( 'spend_member_id' => $this->member_id, 'spend_amount' => ( $refund ) ? $amount->multiply( new \IPS\Math\Number( "-1" ) ) : $amount, 'spend_currency' => $currency ) );
		}
	}

	/**
	 * Recalculate total amount spent in all currencies
	 *
	 * @return	void
	 */
	public function recountTotalSpend()
	{
		\IPS\Db::i()->delete( 'nexus_customer_spend', array( 'spend_member_id=?', $this->member_id ) );

		$spend = array();
		foreach ( \IPS\Db::i()->select( 't_currency, ( SUM(t_amount)-SUM(t_partial_refund) ) AS amount', 'nexus_transactions', array( 't_member=? AND ( t_status=? OR t_status=? )', $this->member_id, \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_PART_REFUNDED ), NULL, NULL, 't_currency' ) as $amount )
		{
			$spend[] = array( 'spend_member_id' => $this->member_id, 'spend_amount' => $amount['amount'], 'spend_currency' => $amount['t_currency'] );
		}

		if( \count( $spend ) )
		{
			\IPS\Db::i()->insert( 'nexus_customer_spend', $spend );
		}
	}
}