<?php
/**
 * @brief		Gift Voucher
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		29 Apr 2014
 */

namespace IPS\nexus\extensions\nexus\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Gift Voucher
 */
class _GiftVoucher extends \IPS\nexus\Invoice\Item\Purchase
{
	/**
	 * @brief	Application
	 */
	public static $application = 'nexus';
	
	/**
	 * @brief	Application
	 */
	public static $type = 'giftvoucher';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'gift';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'gift_voucher';
	
	/**
	 * @brief	Can use coupons?
	 */
	public static $canUseCoupons = FALSE;
	
	/**
	 * Get purchase from redeem code
	 *
	 * @param	string	$redemptionCode	The redemption code
	 * @return	\IPS\nexus\Purchase
	 * @throws	\InvalidArgumentException
	 */
	public static function getPurchase( $redemptionCode )
	{
		$exploded = explode( 'X', $redemptionCode );

		if ( !isset( $exploded[0] ) or !\is_numeric( $exploded[0] ) )
		{
			throw new \InvalidArgumentException('BAD_FORMAT');
		}
		try
		{
			$purchase = \IPS\nexus\Purchase::load( $exploded[0] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \InvalidArgumentException('NO_PURCHASE');
		}
		if ( $purchase->app != 'nexus' or $purchase->type != 'giftvoucher' )
		{
			throw new \InvalidArgumentException('BAD_PURCHASE');
		}
		if ( !$purchase->active or $purchase->cancelled )
		{
			throw new \InvalidArgumentException('CANCELED');
		}
		$extra = $purchase->extra;
		if ( !isset( $extra['code'] ) or $redemptionCode !== "{$extra['code']}" )
		{
			throw new \InvalidArgumentException('BAD_CODE');
		}
		
		return $purchase;
	}
	
	/**
	 * Generate Invoice Form
	 *
	 * @param	\IPS\Helpers\Form	$form		The form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public static function form( \IPS\Helpers\Form $form, \IPS\nexus\Invoice $invoice )
	{
		$form->add( new \IPS\Helpers\Form\Number( 'gift_voucher_amount', 0, TRUE, array(), NULL, NULL, $invoice->currency ) );
		$form->add( new \IPS\Helpers\Form\Color( 'gift_voucher_color', '3b3b3b', FALSE ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'gift_voucher_method', 'print', TRUE, array( 'options' => array( 'email' => 'gift_voucher_email', 'print' => 'gift_voucher_print' ), 'toggles' => array( 'email' => array( 'gift_voucher_email' ) ) ) ) );
		$form->add( new \IPS\Helpers\Form\Email( 'gift_voucher_email', NULL, NULL, array(), function( $val )
		{
			if ( !$val and \IPS\Request::i()->gift_voucher_method === 'email' )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'gift_voucher_email' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'gift_voucher_recipient' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'gift_voucher_sender', $invoice->member->name ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'gift_voucher_message' ) );
	}
	
	/**
	 * Create From Form
	 *
	 * @param	array				$values		Values from form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	\IPS\nexus\extensions\nexus\Item\MiscellaneousCharge
	 */
	public static function createFromForm( array $values, \IPS\nexus\Invoice $invoice )
	{
		$item = new \IPS\nexus\extensions\nexus\Item\GiftVoucher( \IPS\Member::loggedIn()->language()->get('gift_voucher'), new \IPS\nexus\Money( $values['gift_voucher_amount'], $invoice->currency ) );
		$item->paymentMethodIds = array_keys( \IPS\nexus\Gateway::roots( NULL, NULL, array( 'm_active=1 AND m_gateway<>?', 'TwoCheckout' ) ) ); // It is against 2CO terms to use them for buying gift vouchers
		$item->extra['method'] = $values['gift_voucher_method'];
		$item->extra['recipient_email'] = $values['gift_voucher_email'];
		$item->extra['recipient_name'] = $values['gift_voucher_recipient'];
		$item->extra['sender'] = $values['gift_voucher_sender'];
		$item->extra['message'] = $values['gift_voucher_message'];
		$item->extra['amount'] = $values['gift_voucher_amount'];
		$item->extra['color'] = $values['gift_voucher_color'];
		$item->extra['currency'] = $invoice->currency;
		
		return $item;
	}
	
	/**
	 * On Purchase Generated
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public static function onPurchaseGenerated( \IPS\nexus\Purchase $purchase, \IPS\nexus\Invoice $invoice )
	{
		/* Generate a redemption code */
		$code = "{$purchase->id}X{$purchase->member->member_id}X";
		foreach ( range( 1, 10 ) as $j )
		{
			do
			{
				$chr = rand( 48, 90 );
			}
			while ( \in_array( $chr, array( 58, 59, 60, 61, 62, 63, 64, 88 ) ) );
			$code .= \chr( $chr );
		}
		$extra = $purchase->extra;
		$extra['code'] = $code;
		$purchase->extra = $extra;
		$purchase->save();
		
		/* Send the email */
		if ( $purchase->extra['method'] === 'email' )
		{
			\IPS\Email::buildFromTemplate( 'nexus', 'giftVoucher', array( $purchase->extra['recipient_name'], new \IPS\nexus\Money( $purchase->extra['amount'], $purchase->extra['currency'] ), $code, $purchase->extra['message'], $purchase->extra['sender'], $purchase->extra['color'] ), \IPS\Email::TYPE_TRANSACTIONAL )->send( $purchase->extra['recipient_email'] );
		}
	}
	
	/**
	 * Get ACP Page HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public static function acpPage( \IPS\nexus\Purchase $purchase )
	{
		$extra = $purchase->extra;
		return \IPS\Theme::i()->getTemplate('purchases')->giftvoucher( $purchase, $extra );
	}
	
	/**
	 * Get Client Area Page HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public static function clientAreaPage( \IPS\nexus\Purchase $purchase )
	{
		$extra = $purchase->extra;
		return array(
			'purchaseInfo'	=> \IPS\Theme::i()->getTemplate('purchases')->giftvoucher( $purchase, $extra )
		);
	}
	
	/**
	 * Get Client Area Page HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public static function clientAreaAction( \IPS\nexus\Purchase $purchase )
	{
		$extra = $purchase->extra;
		if ( $extra['method'] === 'print' )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'purchases' )->giftvoucherPrint( $extra ), 200, 'text/html' );
			return;
		}
	}
	
	/**
	 * Requires Billing Address
	 *
	 * @return	bool
	 * @throws	\DomainException
	 */
	public function requiresBillingAddress()
	{
		return \in_array( 'giftvoucher', explode( ',', \IPS\Settings::i()->nexus_require_billing ) );
	}

}