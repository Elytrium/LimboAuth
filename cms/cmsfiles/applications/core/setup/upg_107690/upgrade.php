<?php
/**
 * @brief		4.7.11 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		31 May 2023
 */

namespace IPS\core\setup\upg_107690;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.7.11 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Charts Menu
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Try and make sure My Charts is added to the top of the stats menu */
		foreach( \IPS\Db::i()->select( '*', 'core_acp_tab_order' ) AS $order )
		{
			$data = json_decode( $order['data'], true );
			
			if ( isset( $data['stats'] ) )
			{
				foreach( $data['stats'] AS $k => $v )
				{
					if ( $v === '' OR $v === 'core_overview' )
					{
						unset( $data['stats'][ $k ] );
					}
				}
				
				array_unshift( $data['stats'], '', 'core_overview' ); // This looks weird but each entry in the order has a blank entry at the beginning, so preserve that.
			}
			
			\IPS\Db::i()->update( 'core_acp_tab_order', array( 'data' => json_encode( $data ) ), array( "`id`=?", $order['id'] ) );
		}
		
		/* Set a flag to force ACP order cookies to be reset */
		/* The setting won't have been inserted yet at this point */
		\IPS\Db::i()->replace( 'core_sys_conf_settings', array(
			'conf_key'		=> 'acp_menu_cookie_rebuild',
			'conf_value'	=> '1',
			'conf_default'	=> '0',
			'conf_app'		=> 'core'
		)	);

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}