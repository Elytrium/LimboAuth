<?php
/**
 * @brief		Payment Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		26 Mar 2014
 */

namespace IPS\nexus\modules\admin\payments;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Payment Settings
 */
class _paymentsettings extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * Call
	 *
	 * @return	void
	 */
	public function __call( $method, $args )
	{
		$tabs = array();
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'currencies_manage' ) )
		{
			$tabs['currencies'] = 'currencies';
		}
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'gateways_manage' ) )
		{
			$tabs['gateways'] = 'payment_methods';
		}
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'tax_manage' ) )
		{
			$tabs['tax'] = 'tax_rates';
		}
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'checkout_settings' ) )
		{
			$tabs['checkoutsettings'] = 'checkout_settings';
		}
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'fraud_manage' ) )
		{
			$tabs['fraud'] = 'anti_fraud_rules';
		}
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transaction_review_settings' ) )
		{
			$tabs['review'] = 'transaction_review';
		}
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'email_copies_settings' ) )
		{
			$tabs['emails'] = 'notification_copies';
		}

		if ( isset( \IPS\Request::i()->tab ) and isset( $tabs[ \IPS\Request::i()->tab ] ) )
		{
			$activeTab = \IPS\Request::i()->tab;
		}
		else
		{
			$_tabs = array_keys( $tabs ) ;
			$activeTab = array_shift( $_tabs );
		}
		
		$classname = 'IPS\nexus\modules\admin\payments\\' . $activeTab;
		$class = new $classname;
		$class->url = \IPS\Http\Url::internal("app=nexus&module=payments&controller=paymentsettings&tab={$activeTab}");
		$class->execute();
		
		if ( $method !== 'manage' or \IPS\Request::i()->isAjax() )
		{
			return;
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('payment_settings');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, \IPS\Output::i()->output, \IPS\Http\Url::internal( "app=nexus&module=payments&controller=paymentsettings" ) );
	}
}