<?php
/**
 * @brief		4.1.15 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Aug 2016
 */

namespace IPS\core\setup\upg_101056;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.15 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Remove spaces in typed emoticon codes
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'core_emoticons' ) as $emo )
		{
			if ( preg_match( '#\s#', $emo['typed'] ) )
			{
				\IPS\Db::i()->update( 'core_emoticons', array( 'typed' => preg_replace( '#\s#', '', $emo['typed'] ) ), array( 'id=?', $emo['id'] ) );
			}
		}

		return true;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Updating emoticon typed codes";
	}
}