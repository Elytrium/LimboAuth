<?php
/**
 * @brief		4.6.9 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		01 Nov 2021
 */

namespace IPS\core\setup\upg_106144;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.6.9 Beta 1 Upgrade Code
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
		if( \IPS\CIC )
		{
			/* Set prune_member_history to empty string */
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => "" ), array( \IPS\Db::i()->in( 'conf_key', array( 'prune_member_history', 'prune_notifications' ) ) ) );
		}
		
		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}