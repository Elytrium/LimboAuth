<?php
/**
 * @brief		4.4.10 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		09 Jan 2020
 */

namespace IPS\nexus\setup\upg_104057;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.10 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Chexk Stripe's webhook settings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach ( \IPS\nexus\Gateway::roots() as $method )
		{
			if ( $method instanceof \IPS\nexus\Gateway\Stripe )
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

		return TRUE;
	}
}