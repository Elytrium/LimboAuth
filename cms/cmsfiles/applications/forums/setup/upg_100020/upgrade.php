<?php
/**
 * @brief		4.0.0 RC6 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		18 Mar 2015
 */

namespace IPS\forums\setup\upg_100020;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC6 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Initiate queue task to remove old "deleted" topics/posts from 3.x
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Task::queue( 'forums', 'DeleteLegacyTopics', array(), 1 );
		\IPS\Task::queue( 'forums', 'DeleteLegacyPosts', array(), 1 );
		return TRUE;
	}
}