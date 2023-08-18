<?php
/**
 * @brief		4.4.3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		16 Apr 2019
 */

namespace IPS\core\setup\upg_104023;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix ACP Notifications
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach ( array( 'NewRegComplete', 'Spammer' ) as $k )
		{
			if ( $count = \IPS\Db::i()->select( 'COUNT(*)', 'core_acp_notifications', array( 'app=? AND ext=?', 'core', $k ) )->first() )
			{				
				$sent = \IPS\Db::i()->select( 'MAX(sent)', 'core_acp_notifications', array( 'app=? AND ext=?', 'core', $k ) )->first();
				\IPS\Db::i()->delete( 'core_acp_notifcations_dismissals', array('notification IN(?)', \IPS\Db::i()->select( 'id', 'core_acp_notifications', array( 'app=? AND ext=?', 'core', $k ) ) ) );
				\IPS\Db::i()->delete( 'core_acp_notifications', array( 'app=? AND ext=?', 'core', $k ) );
				
				\IPS\Db::i()->insert( 'core_acp_notifications', array(
					'app'	=> 'core',
					'ext'	=> $k,
					'sent'	=> $sent,
					'extra'	=> NULL
				) );
			}
		}
		
		unset( \IPS\Data\Store::i()->acpNotifications );
		unset( \IPS\Data\Store::i()->acpNotificationIds );

		return TRUE;
	}
}