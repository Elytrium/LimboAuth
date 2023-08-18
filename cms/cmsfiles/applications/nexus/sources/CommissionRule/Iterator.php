<?php
/**
 * @brief		Commission Rule Filter Iterator
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		18 Aug 2014
 */

namespace IPS\nexus\CommissionRule;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Commission Rule Filter Iterator
 * @note	When we require PHP 5.4+ this can just be replaced with a CallbackFilterIterator
 */
class _Iterator extends \FilterIterator implements \Countable
{
	/**
	 * @brief	Member
	 */
	protected $member;
	
	/**
	 * @brief	Number of purchases
	 */
	protected $numberOfPurchases = NULL;
	
	/**
	 * @brief	Value of purchases
	 */
	protected $valueOfPurchases = NULL;
	
	/**
	 * @brief	Number of referral rules
	 */
	protected $numberOfRules= NULL;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Patterns\ActiveRecordIterator	$iterator	Iterator
	 * @param	\IPS\Member							$member		Member
	 * @return	void
	 */
	public function __construct( \IPS\Patterns\ActiveRecordIterator $iterator, \IPS\Member $member )
	{
		$this->member = $member;
		return parent::__construct( $iterator );
	}
	
	/**
	 * Does this rule apply?
	 *
	 * @return	void
	 */
	public function accept()
	{	
		$rule = $this->getInnerIterator()->current();
		
		if ( $rule->by_group != '*' )
		{
			if ( !$this->member->inGroup( explode( ',', $rule->by_group ) ) )
			{
				return FALSE;
			}
		}
		
		if ( $rule->by_purchases_op )
		{
			if ( $rule->by_purchases_type === 'n' )
			{
				$value = $this->numberOfPurchases();
				
				$unit = $rule->by_purchases_unit;
			}
			else
			{
				$value = $this->valueOfPurchases();
				$value = array_map( 'floatval', $value );				
				asort( $value );
				
				$keys = array_keys( $value );
				$currency = array_pop( $keys );
				$value = $value[ $currency ];
				
				$unit = json_decode( $rule->by_purchases_unit, TRUE );
				$unit = $unit[ $currency ];
			}
			
			switch ( $rule->by_purchases_op )
			{
				case 'g':
					if ( $value < $unit )
					{
						return FALSE;
					}
					break;
					
				case 'l':
					if ( $value > $unit )
					{
						return FALSE;
					}
					break;
				
				case 'e':
					if ( $value != $unit )
					{
						return FALSE;
					}
					break;
			}
		}
		
		return TRUE;
	}
	
	/**
	 * Get the number of purchases
	 *
	 * @return	int
	 */
	protected function numberOfPurchases()
	{
		if ( $this->numberOfPurchases === NULL )
		{
			$this->numberOfPurchases = \IPS\Db::i()->select( 'COUNT( i_id )', 'nexus_invoices', array( "i_member=? AND i_status=?", $this->member->member_id, \IPS\nexus\Invoice::STATUS_PAID ) )->first();
		}
		return $this->numberOfPurchases;
	}
	
	/**
	 * Get the amounts spent
	 *
	 * @return	int
	 */
	protected function valueOfPurchases()
	{
		if ( $this->valueOfPurchases === NULL )
		{
			$this->valueOfPurchases = iterator_to_array( \IPS\Db::i()->select( 'i_currency, SUM( i_total ) as value', 'nexus_invoices', array( "i_member=? AND i_status=?", $this->member->member_id, \IPS\nexus\Invoice::STATUS_PAID ), NULL, NULL, 'i_currency' )->setKeyField( 'i_currency' )->setValueField( 'value' ) );
		}
		return $this->valueOfPurchases;
	}
	
	/**
	 * Countable
	 *
	 * @return	int
	 */
	public function count(): int
	{
		if ( $this->numberOfRules === NULL )
		{
			$this->numberOfRules = (int) \IPS\Db::i()->select( 'COUNT(*)', 'nexus_referral_rules' )->first();
		}
		return $this->numberOfRules;
	}
}