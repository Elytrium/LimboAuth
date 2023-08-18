<?php
/**
 * @brief		4.0.2 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		14 Apr 2015
 */

namespace IPS\gallery\setup\upg_100025;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.2 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix album counts
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		try
		{
			\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\gallery\Album', 'count' => 0 ), 5, array( 'class' ) );
		}
		catch( \OutOfRangeException $ex ) { }
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Rebuilding gallery album counts";
	}
}