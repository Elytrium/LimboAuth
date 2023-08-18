<?php
/**
 * @brief		4.7.2.1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		16 Sep 2022
 */

namespace IPS\core\setup\upg_107036;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.7.2.1 Upgrade Code
 */
class _Upgrade
{
	public function step1()
	{
		if( \IPS\Settings::i()->allow_reg == 1 )
		{
			\IPS\Settings::i()->changeValues( array( 'allow_reg' => 'normal' ) );
		}

		return TRUE;
	}
}