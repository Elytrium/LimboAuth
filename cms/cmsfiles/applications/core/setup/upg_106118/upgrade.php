<?php
/**
 * @brief		4.6.4 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		01 Jul 2021
 */

namespace IPS\core\setup\upg_106118;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.6.4 Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		


		return TRUE;
	}

	/**
	 * Finish step
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		/* Enable the onboarding system for those that still have the admincp notification active. */
		if( \IPS\core\AdminNotification::find('core', 'ConfigurationError', 'marketplaceSetup') )
		{
			/* If the upgrade is from an old version, the settings may not exist yet. */
			try
			{
				\IPS\Db::i()->select( '*', 'core_sys_conf_settings', [ 'conf_key=?', 'mp_onboard_complete' ] )->setKeyField('conf_key')->setValueField('conf_default' )->first();
			}
			catch( \UnderflowException $e )
			{
				\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_key' => 'mp_onboard_complete', 'conf_value' => 0, 'conf_default' => 1, 'conf_app' => 'core' ), TRUE );
			}

			/* Set onboard complete flag */
			\IPS\Settings::i()->changeValues( array( 'mp_onboard_complete' => 0 ) );
		}

		return TRUE;
	}
}