<?php
/**
 * @brief		1.5.0 Alpha 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Dec 2014
 */

namespace IPS\nexus\setup\upg_15000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 1.5.0 Alpha 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Convert MaxMind settings into fraud rules
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if ( \IPS\Settings::i()->maxmind_hold )
		{
			\IPS\Db::i()->insert( 'nexus_fraud_rules', array(
				'f_name'			=> "MaxMind Hold",
				'f_groups'			=> '*',
				'f_methods'			=> '*',
				'f_maxmind'			=> 'g',
				'f_maxmind_unit'	=> \IPS\Settings::i()->maxmind_hold,
				'f_action'			=> 'hold',
				'f_order'			=> 1,
			) );
		}
		
		if ( \IPS\Settings::i()->maxmind_decline )
		{
			\IPS\Db::i()->insert( 'nexus_fraud_rules', array(
				'f_name'			=> "MaxMind Decline",
				'f_groups'			=> '*',
				'f_methods'			=> '*',
				'f_maxmind'			=> 'g',
				'f_maxmind_unit'	=> \IPS\Settings::i()->maxmind_decline,
				'f_action'			=> 'fail',
				'f_order'			=> 2,
			) );
		}
				
		\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key='maxmind_hold'" );
		\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key='maxmind_decline'" );

		return TRUE;
	}
}