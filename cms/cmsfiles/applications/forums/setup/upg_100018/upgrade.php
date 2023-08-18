<?php
/**
 * @brief		4.0.0 RC 5 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		5 May 2015
 */

namespace IPS\forums\setup\upg_100018;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC 5 Upgrade Code
 */
class _Upgrade
{
	
	/**
	 * Make sure all theme settings are applied to every theme.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
    {
	    \IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => 'IPS\forums\Topic' ), 4 );
	    \IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => 'IPS\forums\Topic\Post' ), 4 );
	    \IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => 'IPS\forums\Topic\ArchivedPost' ), 4 );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\forums\Forum', 'count' => 0 ), 5, array( 'class' ) );

        return TRUE;
    }

}