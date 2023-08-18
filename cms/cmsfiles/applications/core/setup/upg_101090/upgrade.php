<?php
/**
 * @brief		4.1.19 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 Feb 2017
 */

namespace IPS\core\setup\upg_101090;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.19 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Remove bulk mail task
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->delete('core_tasks', array('`key`=? and app=?', 'bulkmail', 'core' ) );

		return TRUE;
	}

	/**
	 * Step 2
	 * Fix core_menu items
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->update( 'core_menu', array( 'is_menu_child' => 0 ), array( 'parent IS NULL' ) );
		unset( \IPS\Data\Store::i()->frontNavigation );

		return TRUE;
	}

	/**
	 * Step 3
	 * Fix admin permissions - remove not existing groups
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		\IPS\Db::i()->delete( 'core_admin_permission_rows', array( 'row_id_type=? AND ' . \IPS\Db::i()->in( 'row_id', iterator_to_array( \IPS\Db::i()->select('g_id', 'core_groups' ) ), TRUE ), 'group' ) );
		unset ( \IPS\Data\Store::i()->administrators );

		return TRUE;
	}
}