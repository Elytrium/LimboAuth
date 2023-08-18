<?php
/**
 * @brief		4.1.3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Oct 2015
 */

namespace IPS\core\setup\upg_101013;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Changes to how child menu items are stored
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 50;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'core_menu', array( 'app=? AND extension=?', 'core', 'Menu' ), 'id ASC', array( $limit, $perCycle ) ) as $menuItem )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}
			$did++;

			\IPS\Db::i()->update( 'core_menu', array( 'is_menu_child' => 1 ), array( 'parent=?', $menuItem['id'] ) );
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( \IPS\Data\Store::i()->frontNavigation );
			return TRUE;
		}
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Updating menu";
	}
}