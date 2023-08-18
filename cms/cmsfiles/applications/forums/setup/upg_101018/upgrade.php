<?php
/**
 * @brief		4.1.5 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		23 Nov 2015
 */

namespace IPS\forums\setup\upg_101018;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.5 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Find forums where the parent/child mapping creates an infinite loop
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$count = \IPS\Db::i()->select( 'COUNT(*)', array( 'forums_forums', 'f1' ), 'f1.parent_id=f2.id' )->join( array( 'forums_forums', 'f2' ), 'f1.id=f2.parent_id' )->first();

		if( $count )
		{
			/* Create a new category */
			$category = new \IPS\forums\Forum;
			$category->parent_id				= -1;
			$category->forum_parent_id			= 0;
			$category->forum_min_posts_view		= 0;
			$category->forum_can_view_others	= 1;
			$category->forum_sort_key			= 'last_post';
			$category->forum_permission_showtopic	= 0;
			$category->save();

			\IPS\Lang::saveCustom( 'forums', 'forums_forums_' . $category->id, "Temporary Category" );

			foreach( \IPS\Db::i()->select( 'f1.*', array( 'forums_forums', 'f1' ), 'f1.parent_id=f2.id' )->join( array( 'forums_forums', 'f2' ), 'f1.id=f2.parent_id' ) as $forum )
			{
				\IPS\Db::i()->update( 'forums_forums', array( 'parent_id' => $category->id ), array( 'id=?', $forum['id'] ) );
			}

			$category->setLastComment();
			$category->save();
		}


		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing incorrectly mapped forums";
	}

	/**
	* Fix container counts that may be off due to bug with unapproved topics
	*
	* @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	*/
	public function step2()
	{
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\forums\Forum', 'count' => 0 ), 5, array( 'class' ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Rebuilding forum post counts";
	}
}