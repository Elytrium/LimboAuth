<?php
/**
 * @brief		4.4.10 Beta 2 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		13 Jan 2020
 */

namespace IPS\core\setup\upg_104058;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.10 Beta 2 Upgrade Code
 */
class _Upgrade
{
	/**
 	 * Reset the Emoji cache (we added new emoji support)
 	 *
 	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
 	 */
 	public function step1()
 	{
 		\IPS\Settings::i()->changeValues( array( 'emoji_cache' => time() ) );
 		
 		return TRUE;
 	}
}