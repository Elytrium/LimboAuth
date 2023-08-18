<?php
/**
 * @brief		Donation
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		18 Jun 2014
 */

namespace IPS\nexus\extensions\nexus\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Donation
 */
class _Donation extends \IPS\nexus\Invoice\Item\Charge
{
	/**
	 * @brief	Application
	 */
	public static $application = 'nexus';
	
	/**
	 * @brief	Application
	 */
	public static $type = 'donation';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'money';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'donation';
	
	/**
	 * @brief	Can use coupons?
	 */
	public static $canUseCoupons = FALSE;
	
	/**
	 * @brief	Can use account credit?
	 */
	public static $canUseAccountCredit = FALSE;
	
	/**
	 * On Paid
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public function onPaid( \IPS\nexus\Invoice $invoice )
	{
		try
		{
			$goal = \IPS\nexus\Donation\Goal::load( $this->id );
			$goalAmount = new \IPS\Math\Number( str_replace( \IPS\Member::loggedIn()->Language()->locale['decimal_point'], '.', (string) $goal->current ) );
			$goalAmount = $goalAmount->add( $this->price->amount );
			$goal->current = (string) $goalAmount;
			$goal->save();
		}
		catch ( \Exception $e ) {}
		
		\IPS\Db::i()->insert( 'nexus_donate_logs', array(
			'dl_goal'	=> $this->id,
			'dl_member'	=> $invoice->member->member_id,
			'dl_amount'	=> $this->price->amount,
			'dl_invoice'=> $invoice->id,
			'dl_date'	=> time()
		) );
	}
	
	/**
	 * On Unpaid
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @param	string				$status		Status
	 * @return	void
	 */
	public function onUnpaid( \IPS\nexus\Invoice $invoice, $status )
	{
		
	}
	
	/**
	 * Requires Billing Address
	 *
	 * @return	bool
	 * @throws	\DomainException
	 */
	public function requiresBillingAddress()
	{
		return \in_array( 'donation', explode( ',', \IPS\Settings::i()->nexus_require_billing ) );
	}
}