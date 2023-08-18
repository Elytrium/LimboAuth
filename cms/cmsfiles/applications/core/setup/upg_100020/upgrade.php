<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Mar 2015
 */

namespace IPS\core\setup\upg_100020;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC 6 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Db Cleanup
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->dropTable( 'search_sessions', TRUE );
		\IPS\Db::i()->dropTable( 'inline_notifications', TRUE );
		\IPS\Db::i()->dropTable( 'api_log', TRUE );
		\IPS\Db::i()->dropTable( 'api_users', TRUE );

		/* We try to drop core_spider_logs in 100003 but otherwise missed this table */
		\IPS\Db::i()->dropTable( 'spider_logs', TRUE );

		/* This looks odd, but we handled members_warn_logs in 4.0 already - this is an old old table */
		\IPS\Db::i()->dropTable( 'warn_logs', TRUE );

		/* This wasn't dropped in the 4.0 routine */
		\IPS\Db::i()->dropTable( 'moderators', TRUE );

		\IPS\Db::i()->dropTable( 'skin_cache', TRUE );
		\IPS\Db::i()->dropTable( 'skin_generator_sessions', TRUE );
		\IPS\Db::i()->dropTable( 'skin_merge_changes', TRUE );
		\IPS\Db::i()->dropTable( 'skin_merge_session', TRUE );
		\IPS\Db::i()->dropTable( 'skin_replacements', TRUE );
		\IPS\Db::i()->dropTable( 'skin_templates_cache', TRUE );
		\IPS\Db::i()->dropTable( 'skin_templates_previous', TRUE );
		\IPS\Db::i()->dropTable( 'skin_url_mapping', TRUE );
		\IPS\Db::i()->dropTable( 'skin_css_previous', TRUE );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Cleaning up database";
	}
}