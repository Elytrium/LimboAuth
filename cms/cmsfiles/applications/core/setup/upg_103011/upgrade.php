<?php
/**
 * @brief		4.3.3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 May 2018
 */

namespace IPS\core\setup\upg_103011;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Finish step
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		if( isset( \IPS\Settings::i()->search_method ) AND \IPS\Settings::i()->search_method == 'mysql' )
		{
			\IPS\Content\Search\Index::i()->rebuild();
		}

		return TRUE;
	}
}