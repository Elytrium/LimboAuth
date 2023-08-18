<?php
/**
 * @brief		4.4.9 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		18 Nov 2019
 */

namespace IPS\nexus\setup\upg_104048;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.9 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix nexus_support_departments, if needed and add a webhook to PayPal
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{

		foreach ( \IPS\nexus\Gateway::roots() as $method )
		{
			if ( $method instanceof \IPS\nexus\Gateway\PayPal )
			{
				try
				{
					$newSettings = $method->testSettings( json_decode( $method->settings, TRUE ) );
					$method->settings = json_encode( $newSettings );
					$method->save();
				}
				catch ( \Exception $e )
				{
					\IPS\core\AdminNotification::send( 'nexus', 'ConfigurationError', "pm{$method->id}", FALSE );
				}
			}
		}
		
		if( ( $currencies = json_decode( \IPS\Settings::i()->nexus_currency, TRUE ) ) === NULL )
		{
			if( \IPS\Settings::i()->nexus_currency and \IPS\Db::i()->checkForColumn( 'nexus_support_departments', \IPS\Settings::i()->nexus_currency ) )
			{
				\IPS\Db::i()->dropColumn( 'nexus_support_departments', \IPS\Settings::i()->nexus_currency );
			}
		}

		return TRUE;
	}
}