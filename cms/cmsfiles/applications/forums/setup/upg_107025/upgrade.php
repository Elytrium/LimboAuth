<?php
/**
 * @brief		4.7.2 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		22 Jul 2022
 */

namespace IPS\forums\setup\upg_107025;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.7.2 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Changes to solved mode
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Prevent all topics from being emailed after upgrade, just leave those that have been started within the last 14 days as able to be emailed */
		\IPS\Db::i()->update( 'forums_topics', [ 'solved_reminder_sent' => time() ], [ 'start_date < ?', ( time() - ( 14 * 86400 ) ) ] );
		
		/* Kick off a rebuild so stats are populated */
		foreach( \IPS\Db::i()->select( '*', 'forums_forums', array( 'topics>? and ( forums_bitoptions & ? or forums_bitoptions & ? or forums_bitoptions & ? )', 0, 4, 8, 16 ) ) as $forum )
		{
			\IPS\Task::queue( 'forums', 'RebuildSolvedStats', array( 'forum_id' => $forum['id'] ) );
		}

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}