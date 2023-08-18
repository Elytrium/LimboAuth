<?php
/**
 * @brief		4.7.9 Beta 2 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		23 Mar 2023
 */

namespace IPS\nexus\setup\upg_107661;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.7.9 Beta 2 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1 - Check PayPal payout settings
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$settings = json_decode( \IPS\Settings::i()->nexus_payout, TRUE );
		if( isset( $settings['PayPal'] ) AND isset( $settings['PayPal']['api_signature'] ) AND !isset( $settings['PayPal']['client_id'] ) )
		{
			\IPS\core\AdminNotification::send( 'nexus', 'ConfigurationError', "poPayPal", FALSE );
		}

		return TRUE;
	}
}