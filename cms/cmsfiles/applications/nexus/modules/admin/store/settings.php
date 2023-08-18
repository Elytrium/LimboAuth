<?php
/**
 * @brief		Store Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		05 May 2014
 */

namespace IPS\nexus\modules\admin\store;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Store Settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage' );
		parent::execute();
	}

	/**
	 * Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$giftVouchers = array();
		foreach ( \IPS\Settings::i()->nexus_gift_vouchers ? ( json_decode( \IPS\Settings::i()->nexus_gift_vouchers, TRUE ) ?: array() ) : array() as $voucher )
		{
			$amounts = array();
			foreach ( $voucher as $currency => $amount )
			{
				$amounts[ $currency ] = new \IPS\nexus\Money( $amount, $currency );
			}
			$giftVouchers[] = $amounts;
		}
		
		$groups = array();
		foreach ( \IPS\Member\Group::groups( FALSE, FALSE ) as $group )
		{
			$groups[ $group->g_id ] = $group->name;
		}
						
		$form = new \IPS\Helpers\Form;
		$form->addTab('nexus_store_display');
		$form->addHeader('nexus_store_prices');
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_show_tax', \IPS\Settings::i()->nexus_show_tax ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'nexus_tax_explain', NULL, FALSE, array( 'app' => 'nexus', 'key' => 'nexus_tax_explain_val', 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('nexus_tax_explain_placeholder') ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'nexus_show_renew_option_savings', \IPS\Settings::i()->nexus_show_renew_option_savings, FALSE, array( 'options' => array(
			'none'		=> 'nexus_show_renew_option_savings_none',
			'amount'	=> 'nexus_show_renew_option_savings_amount',
			'percent'	=> 'nexus_show_renew_option_savings_percent',
		) ) ) );
		$form->addHeader( 'nexus_store_index' );
		$form->add( new \IPS\Helpers\Form\Custom( 'nexus_store_new', explode( ',', \IPS\Settings::i()->nexus_store_new ), FALSE, array(
			'getHtml'	=> function( $field )
			{
				return \IPS\Theme::i()->getTemplate( 'store' )->storeIndexProductsSetting( 'nexus_store_new_field', $field->name, $field->value );
			}
		) ) );
		$form->add( new \IPS\Helpers\Form\Custom( 'nexus_store_popular', explode( ',', \IPS\Settings::i()->nexus_store_popular ), FALSE, array(
			'getHtml'	=> function( $field )
			{
				return \IPS\Theme::i()->getTemplate( 'store' )->storeIndexProductsSetting( 'nexus_store_popular_field', $field->name, $field->value );
			}
		) ) );
		$form->addHeader( 'nexus_stock' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_show_stock', \IPS\Settings::i()->nexus_show_stock ) );
		$form->addTab( 'nexus_purchase_settings' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_reg_force', \IPS\Settings::i()->nexus_reg_force, FALSE ) );
		$form->add( new \IPS\nexus\Form\Money( 'nexus_minimum_order', json_decode( \IPS\Settings::i()->nexus_minimum_order, TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'cm_protected', explode( ',', \IPS\Settings::i()->cm_protected ), FALSE, array( 'options' => $groups, 'multiple' => TRUE ) ) );
		$form->addTab('nexus_gift_vouchers');
		$form->add( new \IPS\Helpers\Form\Stack( 'nexus_gift_vouchers', $giftVouchers, FALSE, array( 'stackFieldType' => 'IPS\nexus\Form\Money' ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_gift_vouchers_free', \IPS\Settings::i()->nexus_gift_vouchers_free ) );
		
		if ( $values = $form->values() )
		{
			$giftVouchers = array();
			foreach ( $values['nexus_gift_vouchers'] as $voucher )
			{
				$gvValues = array();
				foreach ( $voucher as $currency => $amount )
				{
					$gvValues[ $currency ] = $amount->amount;
				}
				$giftVouchers[] = $gvValues;
			}
			$values['nexus_gift_vouchers'] = json_encode( $giftVouchers );
			
			\IPS\Lang::saveCustom( 'nexus', "nexus_tax_explain_val", $values['nexus_tax_explain'] );
			unset( $values['nexus_tax_explain'] );
			
			$values['cm_protected'] = implode( ',', $values['cm_protected'] );
			$values['nexus_store_popular'] = implode( ',', $values['nexus_store_popular'] );
			$values['nexus_store_new'] = implode( ',', $values['nexus_store_new'] );
			
			$values['nexus_minimum_order'] = $values['nexus_minimum_order'] ? json_encode( $values['nexus_minimum_order'] ) : '';

			$form->saveAsSettings( $values );
			
			\IPS\Session::i()->log( 'acplogs__nexus_store_settings' );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
		}
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('store_settings');
		\IPS\Output::i()->output = $form;
	}
}