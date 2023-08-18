<?php
/**
 * @brief		Gift Vouchers
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		5 May 2014
 */

namespace IPS\nexus\modules\front\store;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Gift Vouchers
 */
class _gifts extends \IPS\Dispatcher\Controller
{

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_store.js', 'nexus', 'front' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'store.css', 'nexus' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'store_responsive.css', 'nexus', 'front' ) );
		}

		parent::execute();
	}
	
	/**
	 * Buy
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Work out what our options are */
		$memberCurrency = ( ( ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency() );
		$options = array();
		foreach ( json_decode( \IPS\Settings::i()->nexus_gift_vouchers, TRUE ) as $voucher )
		{
			if ( isset( $voucher[ $memberCurrency ] ) and $voucher[ $memberCurrency ] )
			{
				$options[ $voucher[ $memberCurrency ] ] = new \IPS\nexus\Money( $voucher[ $memberCurrency ], $memberCurrency );
			}
		}
		if ( \IPS\Settings::i()->nexus_gift_vouchers_free )
		{
			$options['x'] = \IPS\Member::loggedIn()->language()->addToStack('other');
		}
		if ( empty( $options ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '4X213/2', 403, '' );
		}
		
		$form = new \IPS\Helpers\Form( 'form', 'buy_gift_voucher' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Color( 'gift_voucher_color', '3b3b3b', FALSE ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'gift_voucher_amount', NULL, TRUE, array( 'options' => $options, 'parse' => 'normal', 'userSuppliedInput' => 'x' ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'gift_voucher_method', NULL, TRUE, array( 'options' => array( 'email' => 'email', 'print' => 'gift_voucher_print' ), 'toggles' => array( 'email' => array( 'gift_voucher_email' ) ) ) ) );
		$form->add( new \IPS\Helpers\Form\Email( 'gift_voucher_email', NULL, NULL, array(), function( $val )
		{
			if ( !$val and \IPS\Request::i()->gift_voucher_method === 'email' )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'gift_voucher_email' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'gift_voucher_recipient' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'gift_voucher_sender', \IPS\Member::loggedIn()->name ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'gift_voucher_message' ) );

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			if( ! \is_numeric( $values['gift_voucher_amount'] ) )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'gift_voucher_invalid_value' );
			}
			elseif( ! abs( $values['gift_voucher_amount'] ) )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'gift_voucher_invalid_amount' );
			}

			if( !$form->error )
			{
				$item = new \IPS\nexus\extensions\nexus\Item\GiftVoucher( \IPS\Member::loggedIn()->language()->get( 'gift_voucher' ), new \IPS\nexus\Money( abs( $values[ 'gift_voucher_amount' ] ), $memberCurrency ) );
				$item->paymentMethodIds = array_keys( \IPS\nexus\Gateway::roots( NULL, NULL, array( 'm_active=1 AND m_gateway<>?', 'TwoCheckout' ) ) ); // It is against 2CO terms to use them for buying gift vouchers
				$item->extra[ 'method' ] = $values[ 'gift_voucher_method' ];
				$item->extra[ 'recipient_email' ] = $values[ 'gift_voucher_email' ];
				$item->extra[ 'recipient_name' ] = $values[ 'gift_voucher_recipient' ];
				$item->extra[ 'sender' ] = $values[ 'gift_voucher_sender' ];
				$item->extra[ 'message' ] = $values[ 'gift_voucher_message' ];
				$item->extra[ 'amount' ] = abs( $values[ 'gift_voucher_amount' ] );
				$item->extra[ 'color' ] = $values[ 'gift_voucher_color' ];
				$item->extra[ 'currency' ] = $memberCurrency;

				$invoice = new \IPS\nexus\Invoice;
				$invoice->currency = $memberCurrency;
				$invoice->member = \IPS\Member::loggedIn();
				$invoice->addItem( $item );
				$invoice->save();

				\IPS\Output::i()->redirect( $invoice->checkoutUrl() );
			}
		}
		
		/* Display */
		$formTemplate = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'store', 'nexus' ), 'giftCardForm' ) );

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('store')->giftCard( $formTemplate );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('buy_gift_voucher');
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('buy_gift_voucher') );
	}
	
	/**
	 * [AJAX] Format currency amount
	 *
	 * @return	void
	 */
	protected function formatCurrency()
	{
		\IPS\Output::i()->json( (string) new \IPS\nexus\Money( \IPS\Request::i()->amount, ( ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency() ) ) );
	}
	
	/**
	 * Redeem
	 *
	 * @return	void
	 */
	protected function redeem()
	{
		if ( !\IPS\nexus\Customer::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'redeem_must_be_logged_in', '1X213/1', 403, '' );
		}
		
		$form = new \IPS\Helpers\Form( 'form', 'redeem_gift_voucher' );
		$form->add( new \IPS\Helpers\Form\Text( 'redemption_code', isset( \IPS\Request::i()->code ) ? \IPS\Request::i()->code : NULL, TRUE, array(), function( $val )
		{
			try
			{
				\IPS\nexus\extensions\nexus\Item\GiftVoucher::getPurchase( $val );
			}
			catch ( \InvalidArgumentException $e )
			{
				throw new \DomainException('redeem_gift_voucher_error');
			}
		} ) );
		if ( $values = $form->values() )
		{
			$purchase = \IPS\nexus\extensions\nexus\Item\GiftVoucher::getPurchase( $values['redemption_code'] );
			$extra = $purchase->extra;
			$currency = isset( $extra['currency'] ) ? $extra['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency();
			
			$credits = \IPS\nexus\Customer::loggedIn()->cm_credits;
			$credits[ $currency ]->amount = $credits[ $currency ]->amount->add( new \IPS\Math\Number( number_format( $extra['amount'], \IPS\nexus\Money::numberOfDecimalsForCurrency( $currency ), '.', '' ) ) );
			\IPS\nexus\Customer::loggedIn()->cm_credits = $credits;
			\IPS\nexus\Customer::loggedIn()->save();
			
			\IPS\nexus\Customer::loggedIn()->log( 'giftvoucher', array( 'type' => 'redeemed', 'code' => $values['redemption_code'], 'amount' => $extra['amount'], 'currency' => $extra['currency'], 'ps_member' => $purchase->member->member_id, 'newCreditAmount' => $credits[ $currency ]->amount ) );
			$purchase->member->log( 'giftvoucher', array( 'type' => 'used', 'code' => $values['redemption_code'], 'amount' => $extra['amount'], 'currency' => $extra['currency'], 'by' => \IPS\nexus\Customer::loggedIn()->member_id ) );
			
			$purchase->delete();
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=store&controller=store&currency={$currency}", 'front', 'store' ) );
		}

		if( \IPS\Request::i()->isAjax() )
		{
			$form->class = 'ipsForm_vertical';
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core', 'front' ), 'popupTemplate' ) );
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'redemption_code' );
			\IPS\Output::i()->output = $form;
		}		
	}
}