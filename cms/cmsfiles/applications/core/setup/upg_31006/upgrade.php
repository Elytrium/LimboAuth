<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_31006;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrade steps
 */
class _Upgrade
{
	/**
	 * Step 1
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$queries	= array();
		
		if ( ! isset( \IPS\Request::i()->run_anyway ) )
		{
			\IPS\Db::i()->addColumn( 'pfields_data', array(
				'name'			=> 'pf_filtering',
				'type'			=> 'tinyint',
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> 0
			) );
	
			if( \IPS\Db::i()->checkForTable('cal_events') )
			{
				\IPS\Db::i()->changeColumn( 'cal_events', 'event_tz', array(
					'name'			=> 'event_tz',
					'type'			=> 'varchar',
					'length'		=> 4,
					'allow_null'	=> false,
					'default'		=> '0'
				) );
			}
	
			\IPS\Db::i()->insert( 'upgrade_history', array(
				'upgrade_version_id'		=> '31006',
				'upgrade_version_human'		=> '3.1.3',
				'upgrade_date'				=> time(),
				'upgrade_mid'				=> 1,
				'upgrade_app'				=> 'calendar'
			) );
	
			if( \IPS\Db::i()->checkForIndex( 'mobile_notifications', 'id' ) )
			{
				\IPS\Db::i()->dropIndex( 'mobile_notifications', 'id' );
			}
	
			\IPS\Db::i()->dropIndex( 'profile_friends', 'friends_member_id' );
			\IPS\Db::i()->dropIndex( 'member_status_updates', 'status_member_id' );
	
			\IPS\Db::i()->changeColumn( 'moderator_logs', 'topic_title', array(
				'name'			=> 'topic_title',
				'type'			=> 'text',
				'length'		=> null,
				'allow_null'	=> true,
				'default'		=> null
			) );
	
			\IPS\Db::i()->changeColumn( 'moderator_logs', 'query_string', array(
				'name'			=> 'query_string',
				'type'			=> 'text',
				'length'		=> null,
				'allow_null'	=> true,
				'default'		=> null
			) );
	
			\IPS\Db::i()->changeColumn( 'moderator_logs', 'http_referer', array(
				'name'			=> 'http_referer',
				'type'			=> 'text',
				'length'		=> null,
				'allow_null'	=> true,
				'default'		=> null
			) );
	
			\IPS\Db::i()->changeColumn( 'moderator_logs', 'action', array(
				'name'			=> 'action',
				'type'			=> 'text',
				'length'		=> null,
				'allow_null'	=> true,
				'default'		=> null
			) );
	
			\IPS\Db::i()->dropIndex( 'attachments', 'attach_where' );
	
			\IPS\Db::i()->delete( 'core_sys_conf_settings', array( "conf_key=?", 'acp_tutorial_mode' ) );
			\IPS\Settings::i()->clearCache();
	
			\IPS\Db::i()->update( 'skin_templates', array( 'template_data' => '$required_output=\'\',$optional_output=\'\',$day=\'\',$mon=\'\',$year=\'\'' ), array( 'template_name=?', 'membersProfileForm' ) );
	
			if( \IPS\Db::i()->checkForTable('facebook_oauth_temp') )
			{
				\IPS\Db::i()->dropTable( 'facebook_oauth_temp' );
			}
	
			if( \IPS\Db::i()->checkForTable('search_index') )
			{
				\IPS\Db::i()->dropTable( 'search_index' );
			}
	
			if( \IPS\Db::i()->checkForTable('templates_diff_import') )
			{
				\IPS\Db::i()->dropTable( 'templates_diff_import' );
			}
	
			if( \IPS\Db::i()->checkForTable('template_diff_changes') )
			{
				\IPS\Db::i()->dropTable( 'template_diff_changes' );
			}
	
			if( \IPS\Db::i()->checkForTable('template_diff_session') )
			{
				\IPS\Db::i()->dropTable( 'template_diff_session' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'attachments', 'attach_approved' ) )
			{
				\IPS\Db::i()->dropColumn( 'attachments', 'attach_approved' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'attachments', 'attach_temp' ) )
			{
				\IPS\Db::i()->dropColumn( 'attachments', 'attach_temp' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'bulk_mail', 'mail_honor' ) )
			{
				\IPS\Db::i()->dropColumn( 'bulk_mail', 'mail_honor' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_rss_imported', 'rss_foreign_id' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_rss_imported', 'rss_foreign_id' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_share_links', 'share_url' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_share_links', 'share_url' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_share_links', 'share_markup' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_share_links', 'share_markup' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_sys_conf_settings', 'conf_end_group' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_sys_conf_settings', 'conf_end_group' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_sys_lang', 'lang_currency_symbol' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_sys_lang', 'lang_currency_symbol' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_sys_lang', 'lang_decimal' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_sys_lang', 'lang_decimal' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_sys_lang', 'lang_comma' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_sys_lang', 'lang_comma' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_sys_login', 'sys_login_skin' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_sys_login', 'sys_login_skin' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_sys_login', 'sys_login_language' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_sys_login', 'sys_login_language' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_sys_login', 'sys_login_last_visit' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_sys_login', 'sys_login_last_visit' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'core_sys_settings_titles', 'conf_title_module' ) )
			{
				\IPS\Db::i()->dropColumn( 'core_sys_settings_titles', 'conf_title_module' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'custom_bbcode', 'bbcode_parse' ) )
			{
				\IPS\Db::i()->dropColumn( 'custom_bbcode', 'bbcode_parse' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'email_logs', 'topic_id' ) )
			{
				\IPS\Db::i()->dropColumn( 'email_logs', 'topic_id' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'forums', 'redirect_loc' ) )
			{
				\IPS\Db::i()->dropColumn( 'forums', 'redirect_loc' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'forums', 'topic_mm_id' ) )
			{
				\IPS\Db::i()->dropColumn( 'forums', 'topic_mm_id' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'forums', 'permission_array' ) )
			{
				\IPS\Db::i()->dropColumn( 'forums', 'permission_array' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'groups', 'g_invite_friend' ) )
			{
				\IPS\Db::i()->dropColumn( 'groups', 'g_invite_friend' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'groups', 'g_can_remove' ) )
			{
				\IPS\Db::i()->dropColumn( 'groups', 'g_can_remove' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'login_methods', 'login_date' ) )
			{
				\IPS\Db::i()->dropColumn( 'login_methods', 'login_date' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'mail_queue', 'mail_type' ) )
			{
				\IPS\Db::i()->dropColumn( 'mail_queue', 'mail_type' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'moderators', 'edit_user' ) )
			{
				\IPS\Db::i()->dropColumn( 'moderators', 'edit_user' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'profile_ratings', 'rating_added' ) )
			{
				\IPS\Db::i()->dropColumn( 'profile_ratings', 'rating_added' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'rc_modpref', 'max_points' ) )
			{
				\IPS\Db::i()->dropColumn( 'rc_modpref', 'max_points' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'rc_modpref', 'reports_pp' ) )
			{
				\IPS\Db::i()->dropColumn( 'rc_modpref', 'reports_pp' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'rc_modpref', 'by_pm' ) )
			{
				\IPS\Db::i()->dropColumn( 'rc_modpref', 'by_pm' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'rc_modpref', 'by_email' ) )
			{
				\IPS\Db::i()->dropColumn( 'rc_modpref', 'by_email' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'rc_modpref', 'by_alert' ) )
			{
				\IPS\Db::i()->dropColumn( 'rc_modpref', 'by_alert' );
			}
	
			if( \IPS\Db::i()->checkForColumn( 'tags_index', 'misc' ) )
			{
				\IPS\Db::i()->dropColumn( 'tags_index', 'misc' );
			}
		}
		
		if( \IPS\Db::i()->checkForColumn( 'reputation_index', 'misc' ) )
		{
			$queries[]	= array( 'table' => 'reputation_index', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "reputation_index DROP COLUMN misc;" );
		}
		
		if( \IPS\Db::i()->checkForColumn( 'topics', 'total_votes' ) )
		{
			$queries[]	= array( 'table' => 'topics', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics DROP COLUMN total_votes;" );
		}

		if( \IPS\Db::i()->checkForColumn( 'members', 'email_pm' ) )
		{
			$queries[]	= array( 'table' => 'members', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members DROP COLUMN email_pm;" );
		}

		if( \IPS\Db::i()->checkForColumn( 'members', 'view_pop' ) )
		{
			$queries[]	= array( 'table' => 'members', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members DROP COLUMN view_pop;" );
		}

		if( \count( $queries ) )
		{
			$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );
			
			if ( \count( $toRun ) )
			{
				\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 2 ) ) );

				/* Queries to run manually */
				return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
			}
		}

		/* Finish */
		return TRUE;
	}
}