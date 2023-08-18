<?php
/**
 * @brief		4.6.8 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		07 Oct 2021
 */

namespace IPS\core\setup\upg_106138;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.6.8 Beta 1 Upgrade Code
 */
class _Upgrade
{
/**
	 * Reorganise tracking code
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Move code to the appropriate setting */
		
		switch( \IPS\Settings::i()->ipbseo_ga_provider )
		{
			case 'ga':
				\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_key' => 'ga_enabled', 'conf_value' => 1, 'conf_default' => 0, 'conf_app' => 'core' ), TRUE );
				\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_key' => 'ga_code', 'conf_value' => \IPS\Settings::i()->ipseo_ga, 'conf_default' => "", 'conf_app' => 'core' ), TRUE );
		
				break;
			
			case 'piwik':
				\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_key' => 'matomo_enabled', 'conf_value' => 1, 'conf_default' => 0, 'conf_app' => 'core' ), TRUE );
				\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_key' => 'matomo_code', 'conf_value' => \IPS\Settings::i()->ipseo_ga, 'conf_default' => "", 'conf_app' => 'core' ), TRUE );

				break;
			
			case 'custom':	
				\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_key' => 'custom_body_code', 'conf_value' => \IPS\Settings::i()->ipbseo_ga, 'conf_default' => "", 'conf_app' => 'core' ), TRUE );
				\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_key' => 'custom_page_view_js', 'conf_value' => \IPS\Settings::i()->ipbseo_ga_paginatecode, 'conf_default' => "", 'conf_app' => 'core' ), TRUE );
	
				break;
		}

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}