<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		03 Mar 2014
 */

namespace IPS\blog\setup\upg_23000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Conversion from 3.0
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$queries	= array();

		if ( \IPS\Db::i()->checkForTable('blog_tracker') )
		{
			$PRE = \IPS\Db::i()->prefix;

			if( \IPS\Db::i()->checkForTable('core_like') )
			{
				$queries[] = array( 'table' => 'blog_tracker', 'query' => "INSERT IGNORE INTO `{$PRE}core_like` (like_id, like_lookup_id, like_app, like_area, like_rel_id, like_member_id, like_is_anon, like_added, like_notify_do, like_notify_freq) SELECT MD5(CONCAT('blog;blog;', blog_id, ';', member_id)), MD5(CONCAT('blog;blog;', blog_id)), 'blog', 'blog', blog_id, member_id, 0, UNIX_TIMESTAMP(), 1, 'immediate' FROM `{$PRE}blog_tracker`" );

				$queries[] = array( 'table' => 'blog_tracker', 'query' => "INSERT IGNORE INTO `{$PRE}core_like` (like_id, like_lookup_id, like_app, like_area, like_rel_id, like_member_id, like_is_anon, like_added, like_notify_do, like_notify_freq) SELECT MD5(CONCAT('blog;entries;', entry_id, ';', member_id)), MD5(CONCAT('blog;entries;', entry_id)), 'blog', 'entries', entry_id, member_id, 0, UNIX_TIMESTAMP(), 1,'immediate' FROM `{$PRE}blog_tracker` WHERE entry_id <> 0 AND entry_id IS NOT NULL" );
			}
			else
			{
				$queries[] = array( 'table' => 'blog_tracker', 'query' => "INSERT IGNORE INTO `{$PRE}core_follow` (follow_id, follow_app, follow_area, follow_rel_id, follow_member_id, follow_is_anon, follow_added, follow_notify_do, follow_notify_freq) SELECT MD5(CONCAT('blog;blog;', blog_id, ';', member_id)), 'blog', 'blog', blog_id, member_id, 0, UNIX_TIMESTAMP(), 1, 'immediate' FROM `{$PRE}blog_tracker`" );

				$queries[] = array( 'table' => 'blog_tracker', 'query' => "INSERT IGNORE INTO `{$PRE}core_follow` (follow_id, follow_app, follow_area, follow_rel_id, follow_member_id, follow_is_anon, follow_added, follow_notify_do, follow_notify_freq) SELECT MD5(CONCAT('blog;entries;', entry_id, ';', member_id)), 'blog', 'entries', entry_id, member_id, 0, UNIX_TIMESTAMP(), 1,'immediate' FROM `{$PRE}blog_tracker` WHERE entry_id <> 0 AND entry_id IS NOT NULL" );
			}

			$queries[] = array( 'table' => 'blog_tracker', 'query' => "DROP TABLE {$PRE}blog_tracker" );

			if( \IPS\Db::i()->checkForTable( 'blog_tracker_queue' ) )
			{
				\IPS\Db::i()->dropTable( 'blog_tracker_queue' );
			}
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'blog', 'extra' => array( '_upgradeStep' => 2 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}
		
		return TRUE;
	}
}