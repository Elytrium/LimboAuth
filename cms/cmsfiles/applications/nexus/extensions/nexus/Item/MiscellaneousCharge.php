<?php
/**
 * @brief		Miscellaneous Charge
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
 * Miscellaneous Charge
 */
class _MiscellaneousCharge extends \IPS\nexus\Invoice\Item\Charge
{
	/**
	 * @brief	Application
	 */
	public static $application = 'nexus';
	
	/**
	 * @brief	Application
	 */
	public static $type = 'charge';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'dollar';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'miscellaneous_charge';
	
	/**
	 * Generate Invoice Form
	 *
	 * @param	\IPS\Helpers\Form	$form		The form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public static function form( \IPS\Helpers\Form $form, \IPS\nexus\Invoice $invoice )
	{
		$form->add( new \IPS\Helpers\Form\Text( 'item_name', NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Number( 'item_net_price', 0, TRUE, array( 'decimals' => TRUE, 'min' => NULL ), NULL, NULL, $invoice->currency ) );
		$form->add( new \IPS\Helpers\Form\Node( 'item_tax_rate', 0, FALSE, array( 'class' => 'IPS\nexus\Tax', 'zeroVal' => 'item_tax_rate_none' ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'item_paymethods', 0, FALSE, array( 'class' => 'IPS\nexus\Gateway', 'multiple' => TRUE, 'zeroVal' => 'all' ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'item_physical', FALSE, FALSE, array( 'togglesOn' => array( 'item_weight', 'item_shipmethods' ) ) ) );
		$form->add( new \IPS\nexus\Form\Weight( 'item_weight', NULL, FALSE, array(), NULL, NULL, NULL, 'item_weight' ) );
		
		$availableShippingMethods = array();
		foreach ( \IPS\nexus\Shipping\FlatRate::roots() as $rate )
		{
			$availableShippingMethods[ $rate->_id ] = $rate->_title;
		}
		if ( \IPS\Settings::i()->easypost_api_key and \IPS\Settings::i()->easypost_show_rates )
		{
			$availableShippingMethods['easypost'] = 'enhancements__nexus_EasyPost';
		}
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'item_shipmethods', '*', FALSE, array( 'options' => $availableShippingMethods, 'multiple' => TRUE, 'unlimited' => '*', 'impliedUnlimited' => TRUE ), NULL, NULL, NULL, 'item_shipmethods' ) );		
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'item_pay_other', FALSE, FALSE, array( 'togglesOn' => array( 'item_pay_to', 'item_commission', 'item_fee' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Member( 'item_pay_to', FALSE, FALSE, array(), NULL, NULL, NULL, 'item_pay_to' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'item_commission', FALSE, FALSE, array( 'min' => 0, 'max' => 100 ), NULL, NULL, '%', 'item_commission' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'item_fee', FALSE, FALSE, array(), NULL, NULL, $invoice->currency, 'item_fee' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'invoice_quantity', 1, FALSE, array( 'min' => 1 ) ) );
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
		$obj = new static( $values['item_name'], new \IPS\nexus\Money( $values['item_net_price'], $invoice->currency ) );
		$obj->quantity = $values['invoice_quantity'];
		if ( $values['item_tax_rate'] )
		{
			$obj->tax = $values['item_tax_rate'];
		}
		if ( $values['item_paymethods'] )
		{
			$obj->paymentMethodIds = array_keys( $values['item_paymethods'] );
		}
		if ( $values['item_physical'] )
		{
			$obj->physical = TRUE;
			$obj->weight = $values['item_weight'];
			if ( $values['item_shipmethods'] !== '*' )
			{
				$obj->shippingMethodIds = array_keys( $values['item_shipmethods'] );
			}
		}
		if ( $values['item_pay_other'] )
		{
			$obj->payTo = $values['item_pay_to'];
			$obj->commission = $values['item_commission'];
			$obj->fee = new \IPS\nexus\Money( $values['item_fee'], $invoice->currency );
		}
		return $obj;
	}
}