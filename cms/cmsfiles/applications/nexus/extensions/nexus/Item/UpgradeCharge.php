<?php
/**
 * @brief		Invoice Item Class for Package Upgrade Charges
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		8 May 2014
 */

namespace IPS\nexus\extensions\nexus\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invoice Item Class for Shipping Charges
 */
class _UpgradeCharge extends \IPS\nexus\Invoice\Item\Charge
{
	/**
	 * @brief	Application
	 */
	public static $application = 'nexus';
	
	/**
	 * @brief	Application
	 */
	public static $type = 'upgrade';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'level-up';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'upgrade_charge';
	
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
			$purchase = \IPS\nexus\Purchase::load( $this->id );
			$oldPackage = \IPS\nexus\Package::load( $this->extra['oldPackage'] );
			$newPackage = \IPS\nexus\Package::load( $this->extra['newPackage'] );
			$oldPackage->upgradeDowngrade( $purchase, $newPackage, $this->extra['renewalOption'], TRUE );
			$purchase->member->log( 'purchase', array( 'type' => 'change', 'id' => $purchase->id, 'old' => $oldPackage->titleForLog(), 'name' => $newPackage->titleForLog(), 'system' => TRUE ) );
		}
		catch ( \OutOfRangeException $e ){}
	}
	
	/**
	 * On Unpaid description
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	array
	 */
	public function onUnpaidDescription( \IPS\nexus\Invoice $invoice )
	{
		$return = parent::onUnpaidDescription( $invoice );
		
		try
		{
			$oldPackage = \IPS\nexus\Package::load( $this->extra['oldPackage'] );
			$newPackage = \IPS\nexus\Package::load( $this->extra['newPackage'] );
			
			$return[] = \IPS\Member::loggedIn()->language()->addToStack( 'invoice_unpaid_change', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'purchase_number', FALSE, array( 'sprintf' => array( $this->id ) ) ), $newPackage->_title, $oldPackage->_title ) ) );
		}
		catch ( \OutOfRangeException $e ){}
		
		return $return;
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
		try
		{
			/* Figure out tax */
			$tax = NULL;

			try
			{
				if( isset( $this->extra['previousRenewalTerms']['tax'] ) AND $this->extra['previousRenewalTerms']['tax'] )
				{
					$tax = \IPS\nexus\Tax::load( $this->extra['previousRenewalTerms']['tax'] );
				}
			}
			catch( \OutOfRangeException $e ){}

			$previousRenewalTerm = $this->extra['previousRenewalTerms'] ? new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $this->extra['previousRenewalTerms']['cost'], $this->extra['previousRenewalTerms']['currency'] ), new \DateInterval( 'P' . $this->extra['previousRenewalTerms']['term']['term'] . mb_strtoupper( $this->extra['previousRenewalTerms']['term']['unit'] ) ), $tax ) : NULL;
			
			$purchase = \IPS\nexus\Purchase::load( $this->id );
			$oldPackage = \IPS\nexus\Package::load( $this->extra['oldPackage'] );
			$newPackage = \IPS\nexus\Package::load( $this->extra['newPackage'] );
			$newPackage->upgradeDowngrade( $purchase, $oldPackage, $previousRenewalTerm, TRUE );
			$purchase->member->log( 'purchase', array( 'type' => 'change', 'id' => $purchase->id, 'old' => $newPackage->titleForLog(), 'name' => $oldPackage->titleForLog(), 'system' => TRUE ) );
		}
		catch ( \OutOfRangeException $e ){}
	}
	
	/**
	 * Client Area URL
	 *
	 * @return |IPS\Http\Url|NULL
	 */
	public function url()
	{
		try
		{
			return \IPS\nexus\Purchase::load( $this->id )->url();
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * ACP URL
	 *
	 * @return |IPS\Http\Url|NULL
	 */
	public function acpUrl()
	{
		try
		{
			return \IPS\nexus\Purchase::load( $this->id )->acpUrl();
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
}