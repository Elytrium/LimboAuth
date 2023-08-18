<?php
/**
 * @brief		Account Credit Increase
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		25 Mar 2014
 */

namespace IPS\nexus\extensions\nexus\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Account Credit Topup
 */
class _AccountCreditIncrease extends \IPS\nexus\Invoice\Item\Charge
{
	/**
	 * @brief	Application
	 */
	public static $application = 'nexus';
	
	/**
	 * @brief	Application
	 */
	public static $type = 'topup';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'folder-open';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'account_credit_increase';
	
	/**
	 * @brief	Can use coupons?
	 */
	public static $canUseCoupons = FALSE;
	
	/**
	 * @brief	Can use account credit?
	 */
	public static $canUseAccountCredit = FALSE;
	
	/**
	 * Generate Invoice Form
	 *
	 * @param	\IPS\Helpers\Form	$form		The form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public static function form( \IPS\Helpers\Form $form, \IPS\nexus\Invoice $invoice )
	{
		$form->add( new \IPS\Helpers\Form\Number( 'credit_amount', 0, TRUE, array( 'decimals' => TRUE ), NULL, NULL, $invoice->currency ) );
	}
	
	/**
	 * Create From Form
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	\IPS\nexus\extensions\nexus\Item\MiscellaneousCharge
	 */
	public static function createFromForm( array $values, \IPS\nexus\Invoice $invoice )
	{		
		$obj = new static( $invoice->member->language()->get('account_credit'), new \IPS\nexus\Money( $values['credit_amount'], $invoice->currency ) );
		return $obj;
	}
	
	/**
	 * On Paid
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public function onPaid( \IPS\nexus\Invoice $invoice )
	{
		$credits = $invoice->member->cm_credits;
		
		$oldAmount = $credits[ $this->price->currency ]->amount;
		$credits[ $this->price->currency ]->amount = $credits[ $this->price->currency ]->amount->add( $this->price->amount );
		$invoice->member->cm_credits = $credits;
		$invoice->member->save();
		
		$invoice->member->log( 'comission', array(
			'type'			=> 'bought',
			'currency'		=> $this->price->currency,
			'amount'		=> $oldAmount,
			'new_amount'	=> $credits[ $this->price->currency ]->amount,
			'invoice_id'	=> $invoice->id,
			'invoice_title'	=> $invoice->title
		) );
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
		
		$message = \IPS\Member::loggedIn()->language()->addToStack('account_credit_remove', FALSE, array( 'sprintf' => array( $this->price, $invoice->member->cm_name ) ) );
		
		$credits = $invoice->member->cm_credits;
		if ( !$credits[ $this->price->currency ]->amount->subtract( $this->price->amount )->isPositive() )
		{
			$return[] = array( 'message' => $message, 'warning' => \IPS\Member::loggedIn()->language()->addToStack('account_credit_remove_neg') );
		}
		else
		{
			$return[] = $message;
		}
		
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
		$credits = $invoice->member->cm_credits;
		
		$oldAmount = $credits[ $this->price->currency ]->amount;
		$credits[ $this->price->currency ]->amount = $credits[ $this->price->currency ]->amount->subtract( $this->price->amount );
		$invoice->member->cm_credits = $credits;
		$invoice->member->save();
		
		$invoice->member->log( 'comission', array(
			'type'			=> 'bought',
			'currency'		=> $this->price->currency,
			'amount'		=> $oldAmount,
			'new_amount'	=> $credits[ $this->price->currency ]->amount,
			'invoice_id'	=> $invoice->id,
			'invoice_title'	=> $invoice->title
		) );
	}
}