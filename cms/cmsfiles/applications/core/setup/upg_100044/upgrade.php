<?php
/**
 * @brief		4.0.13 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 Jul 2015
 */

namespace IPS\core\setup\upg_100044;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.13 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Some users have bad warn log data due to a past bug
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->update( 'core_members_warn_logs', "wl_mq=REPLACE(wl_mq,'P-','P')" );
		\IPS\Db::i()->update( 'core_members_warn_logs', "wl_rpa=REPLACE(wl_rpa,'P-','P')" );
		\IPS\Db::i()->update( 'core_members_warn_logs', "wl_suspend=REPLACE(wl_suspend,'P-','P')" );

		\IPS\Db::i()->update( 'core_members_warn_logs', "wl_mq=REPLACE(wl_mq,'P','PT')", "wl_mq regexp 'P[0-9]+H' and wl_mq not regexp 'PT[0-9]+H'" );
		\IPS\Db::i()->update( 'core_members_warn_logs', "wl_rpa=REPLACE(wl_rpa,'P','PT')", "wl_rpa regexp 'P[0-9]+H' and wl_rpa not regexp 'PT[0-9]+H'" );
		\IPS\Db::i()->update( 'core_members_warn_logs', "wl_suspend=REPLACE(wl_suspend,'P','PT')", "wl_suspend regexp 'P[0-9]+H' and wl_suspend not regexp 'PT[0-9]+H'" );

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing incorrectly formatted warn logs";
	}

	/**
	 * Notifications for status updates and replies were not previously properly removed
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if( \IPS\Db::i()->select( 'COUNT(*)', 'core_notifications' )->first() )
		{
			$toRunQueries	= array(
				array(
					'table'	=> 'core_notifications',
					'query'	=> "DELETE FROM " . \IPS\Db::i()->prefix . "core_notifications WHERE item_class='IPS\\core\\Statuses\\Status' AND item_id NOT IN (SELECT status_id FROM " . \IPS\Db::i()->prefix . "core_member_status_updates)",
				),
				array(
					'table'	=> 'core_notifications',
					'query'	=> "DELETE FROM " . \IPS\Db::i()->prefix . "core_notifications WHERE item_class='IPS\\core\\Statuses\\Reply' AND item_id NOT IN (SELECT reply_id FROM " . \IPS\Db::i()->prefix . "core_member_status_replies)",
				)
			);

			$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $toRunQueries );
			
			if ( \count( $toRun ) )
			{
				\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 'extra' => array( '_upgradeStep' => 3 ) ) );

				/* Queries to run manually */
				return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
			}
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Removing invalid notifications";
	}

	/**
	 * Fix mixed charset/collations for union query
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		\IPS\Db::i()->query( "ALTER TABLE " . \IPS\Db::i()->prefix . "core_applications CHARACTER SET " . \IPS\Db::i()->charset . " COLLATE " . \IPS\Db::i()->collation );
		\IPS\Db::i()->query( "ALTER TABLE " . \IPS\Db::i()->prefix . "core_applications CHANGE app_directory app_directory VARCHAR(250) CHARACTER SET " . \IPS\Db::i()->charset . " COLLATE " . \IPS\Db::i()->collation . " NOT NULL,
			CHANGE app_update_check app_update_check VARCHAR(255) CHARACTER SET " . \IPS\Db::i()->charset . " COLLATE " . \IPS\Db::i()->collation . " NULL DEFAULT NULL" );

		\IPS\Db::i()->query( "ALTER TABLE " . \IPS\Db::i()->prefix . "core_plugins CHARACTER SET " . \IPS\Db::i()->charset . " COLLATE " . \IPS\Db::i()->collation );
		\IPS\Db::i()->query( "ALTER TABLE " . \IPS\Db::i()->prefix . "core_plugins CHANGE plugin_update_check plugin_update_check TEXT CHARACTER SET " . \IPS\Db::i()->charset . " COLLATE " . \IPS\Db::i()->collation . " NULL DEFAULT NULL COMMENT 'URL to check for updates'" );

		\IPS\Db::i()->query( "ALTER TABLE " . \IPS\Db::i()->prefix . "core_themes CHARACTER SET " . \IPS\Db::i()->charset . " COLLATE " . \IPS\Db::i()->collation );
		\IPS\Db::i()->query( "ALTER TABLE " . \IPS\Db::i()->prefix . "core_themes CHANGE set_update_check set_update_check VARCHAR(255) CHARACTER SET " . \IPS\Db::i()->charset . " COLLATE " . \IPS\Db::i()->collation . " NULL DEFAULT NULL COMMENT 'Remote URL to retrieve update data'" );

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Fixing mixed character sets";
	}

	/**
	 * Fix url dofollow if needed
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		if( \IPS\Settings::i()->posts_add_nofollow_exclude )
		{
			$nofollow	= json_decode( \IPS\Settings::i()->posts_add_nofollow_exclude, TRUE );
			$domains	= array();

			foreach( $nofollow as $domain )
			{
				/* We only want to compare against the domain, not the full URL */
				$domain = str_replace( array( 'http://', 'https://' ), '', $domain );

				/* And we are comparing against the non-www. version during parsing */
				$domain = preg_replace( '/^www\./', '', $domain );

				/* Use the domain as the key as well to prevent duplicates for efficiency purposes */
				$domains[ $domain ] = $domain;
			}

			\IPS\Settings::i()->changeValues( array( 'posts_add_nofollow_exclude' => json_encode( array_values( $domains ) ) ) );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Fixing URL nofollow filters";
	}

	/**
	 * Enable the cache cleanup task
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), "`key`='clearcache'" );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		return "Re-enable cache cleanup task";
	}

}