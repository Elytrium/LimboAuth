<?php
/**
 * @brief		4.6.11 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		14 Feb 2022
 */

namespace IPS\downloads\setup\upg_106156;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.6.11 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Finish Upgrade
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\downloads\Category', 'count' => 0 ), 5, array( 'class' ) );

		return TRUE;
	}
}