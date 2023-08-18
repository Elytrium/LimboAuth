<?php
/**
 * @brief		4.1.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		3 Feb 2015
 */

namespace IPS\gallery\setup\upg_101006;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Finish step
	 * Rebuild last poster data
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\\gallery\\Category', 'count' => 0 ), 5, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\\gallery\\Album', 'count' => 0 ), 5, array( 'class' ) );
	}
}
