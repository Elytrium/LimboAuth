<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Jun 2014
 */

namespace IPS\core\setup\upg_32000;

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
		/* @note In the original upgrade steps we disable hooks and delete some skin templates here, but that's unnecessary since we'll do it again for the 4.0 step */

		\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key IN( 'threaded_per_page', 'spider_anon', 'enable_sockets', 'spam_service_api_key', 'news_forum_id', 'index_news_link', 
	'csite_title', 'csite_article_date', 'csite_nav_show', 'csite_nav_contents', 'csite_fav_show', 'csite_fav_contents', 'blogs_portal_lastx', 'poll_poll_url', 'recent_topics_article_forum', 
	'recent_topics_article_max', 'portal_exclude_pinned', 'recent_topics_discuss_number', 'forum_trash_can_enable', 'forum_trash_can_id', 'forum_trash_can_use_admin', 'forum_trash_can_use_smod', 
	'forum_trash_can_use_mod', 'guests_ava', 'avatars_on', 'avatar_url', 'avatar_ext', 'avatar_def', 'avup_size_max', 'avatar_dims', 'disable_ipbsize', 'registration_qanda',
	'disable_reportpost', 'captcha_allow_fonts', 'gd_version', 'login_page_info', 'register_page_info', 'cache_calendar', 'disable_online_ip', 'topicmode_default', 'allow_skins',
	'ipb_disable_group_psformat', 'max_sig_length', 'aboutme_emoticons', 'login_change_key', 'disable_flash', 'disable_admin_anon', 'msg_allow_code', 'warn_show_rating', 'short_forum_jump',
	'ips_default_editor', 'poll_tags', 'ipb_reg_show', 'cpu_watch_update', 'report_nemail_enabled', 'report_pm_enabled', 'enable_show_as_titles', 'pre_pinned', 'pre_moved', 'pre_polls', 
	'max_bbcodes_per_post', 'post_wordwrap', 'aboutme_bbcode', 'sig_allow_ibc', 'aboutme_html', 'sig_allow_html', 'msg_allow_html', 'postpage_contents', 'topicpage_contents', 'use_mail_form',
	'resize_linked_img', 'cc_monitor' )" );

		\IPS\Db::i()->delete( 'core_sys_settings_titles', "conf_title_keyword in ( 'ipbportal', 'portal_poll', 'portal_recent_topics', 'portal_blogs', 'newssetup', 'trashcansetup',
	'cookies', 'ipbreg', 'twitterconnect' )" );

		\IPS\Settings::i()->clearCache();

		\IPS\Db::i()->delete( 'core_applications', "app_directory='portal'" );
		\IPS\Db::i()->delete( 'core_sys_module', "sys_module_application='portal'" );
		\IPS\Db::i()->delete( 'upgrade_history', "upgrade_app='portal'" );
		\IPS\Db::i()->delete( 'cache_store', "cs_key IN( 'ccMonitor', 'portal' )" );
		\IPS\Db::i()->delete( 'core_sys_lang_words', "word_app='portal'" );

		return TRUE;
	}

	/**
	 * Step 2
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		/* We are using a raw query here to ensure the driver doesn't attempt to modify things or help us, like adjusting the table engine */
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'reputation_cache',
			'query' => "CREATE TABLE " . \IPS\Db::i()->prefix . "reputation_cache2 (SELECT * FROM " . \IPS\Db::i()->prefix . "reputation_cache WHERE 1=0);"
		),
		array(
			'table' => 'reputation_cache',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "reputation_cache2 ADD UNIQUE INDEX app (app, type, type_id);"
		),
		array(
			'table' => 'reputation_cache',
			'query' => "REPLACE INTO " . \IPS\Db::i()->prefix . "reputation_cache2 (SELECT * FROM " . \IPS\Db::i()->prefix . "reputation_cache WHERE id > 0);"
		),
		array(
			'table' => 'reputation_cache',
			'query' => "DROP TABLE " . \IPS\Db::i()->prefix . "reputation_cache;"
		),
		array(
			'table' => 'reputation_cache',
			'query' => "RENAME TABLE " . \IPS\Db::i()->prefix . "reputation_cache2 TO " . \IPS\Db::i()->prefix . "reputation_cache;"
		),
		array(
			'table' => 'reputation_cache',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "reputation_cache ADD PRIMARY KEY(id), 
				CHANGE COLUMN id id BIGINT(20) NOT NULL,
				ADD COLUMN rep_like_cache MEDIUMTEXT NULL DEFAULT NULL,
				CHANGE COLUMN rep_points rep_points INT(10) NOT NULL DEFAULT 0;"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 3 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		/* Finish */
		return TRUE;
	}

	/**
	 * Step 3
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		if( !\IPS\Db::i()->checkForColumn( 'login_methods', 'login_custom_config' ) )
		{
			\IPS\Db::i()->addColumn( 'login_methods', array(
				'name'			=> 'login_custom_config',
				'type'			=> 'text',
				'allow_null'	=> true,
				'default'		=> null
			) );
		}

		if( !\IPS\Db::i()->checkForColumn( 'core_applications', 'app_tab_groups' ) )
		{
			\IPS\Db::i()->addColumn( 'core_applications', array(
				'name'			=> 'app_website',
				'type'			=> 'varchar',
				'length'		=> 255,
				'allow_null'	=> true,
				'default'		=> null
			) );

			\IPS\Db::i()->addColumn( 'core_applications', array(
				'name'			=> 'app_update_check',
				'type'			=> 'varchar',
				'length'		=> 255,
				'allow_null'	=> true,
				'default'		=> null
			) );

			\IPS\Db::i()->addColumn( 'core_applications', array(
				'name'			=> 'app_global_caches',
				'type'			=> 'varchar',
				'length'		=> 255,
				'allow_null'	=> true,
				'default'		=> null
			) );

			\IPS\Db::i()->addColumn( 'core_applications', array(
				'name'			=> 'app_tab_groups',
				'type'			=> 'text',
				'allow_null'	=> true,
				'default'		=> null
			) );
		}

		if( \IPS\Db::i()->checkForColumn( 'cache_store', 'cs_extra' ) )
		{
			\IPS\Db::i()->dropColumn( 'cache_store', 'cs_extra' );
		}

		if( !\IPS\Db::i()->checkForColumn( 'cache_store', 'cs_rebuild' ) )
		{
			\IPS\Db::i()->addColumn( 'cache_store', array(
				'name'			=> 'cs_rebuild',
				'type'			=> 'tinyint',
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> 0
			) );
		}

		if( \IPS\Db::i()->checkForColumn( 'banfilters', 'ban_nocache' ) )
		{
			\IPS\Db::i()->dropColumn( 'banfilters', 'ban_nocache' );
		}

		if( !\IPS\Db::i()->checkForColumn( 'banfilters', 'ban_reason' ) )
		{
			\IPS\Db::i()->addColumn( 'banfilters', array(
				'name'			=> 'ban_reason',
				'type'			=> 'varchar',
				'length'		=> 255,
				'allow_null'	=> true,
				'default'		=> null
			) );
		}

		if( !\IPS\Db::i()->checkForIndex( 'rc_reports_index', 'status' ) )
		{
			\IPS\Db::i()->addIndex( 'rc_reports_index', array(
				'type'			=> 'key',
				'name'			=> 'status',
				'columns'		=> array( 'status' )
			) );
		}

		/* Report center comments */
		if( !\IPS\Db::i()->checkForColumn( 'rc_comments', 'approved' ) )
		{
			\IPS\Db::i()->addColumn( 'rc_comments', array(
				'name'			=> 'approved',
				'type'			=> 'tinyint',
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> 1
			) );
			\IPS\Db::i()->addColumn( 'rc_comments', array(
				'name'			=> 'edit_date',
				'type'			=> 'int',
				'length'		=> 10,
				'allow_null'	=> false,
				'default'		=> 0
			) );
			\IPS\Db::i()->addColumn( 'rc_comments', array(
				'name'			=> 'author_name',
				'type'			=> 'varchar',
				'length'		=> 255,
				'allow_null'	=> true,
				'default'		=> null
			) );
			\IPS\Db::i()->addColumn( 'rc_comments', array(
				'name'			=> 'ip_address',
				'type'			=> 'varchar',
				'length'		=> 46,
				'allow_null'	=> true,
				'default'		=> null
			) );
		}

		/* Ignored users */
		if( !\IPS\Db::i()->checkForColumn( 'ignored_users', 'ignore_signatures' ) )
		{
			\IPS\Db::i()->addColumn( 'ignored_users', array(
				'name'			=> 'ignore_signatures',
				'type'			=> 'int',
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> 0
			) );
			\IPS\Db::i()->addColumn( 'ignored_users', array(
				'name'			=> 'ignore_chats',
				'type'			=> 'int',
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> 0
			) );
		}

		if( !\IPS\Db::i()->checkForColumn( 'mail_queue', 'mail_cc' ) )
		{
			\IPS\Db::i()->addColumn( 'mail_queue', array(
				'name'			=> 'mail_cc',
				'type'			=> 'text',
				'allow_null'	=> true,
				'default'		=> null
			) );
		}

		/* Permissions */
		\IPS\Db::i()->changeColumn( 'permission_index', 'perm_2', array(
			'name'			=> 'perm_2',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );
		\IPS\Db::i()->changeColumn( 'permission_index', 'perm_3', array(
			'name'			=> 'perm_3',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );
		\IPS\Db::i()->changeColumn( 'permission_index', 'perm_4', array(
			'name'			=> 'perm_4',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );
		\IPS\Db::i()->changeColumn( 'permission_index', 'perm_5', array(
			'name'			=> 'perm_5',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );
		\IPS\Db::i()->changeColumn( 'permission_index', 'perm_6', array(
			'name'			=> 'perm_6',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );
		\IPS\Db::i()->changeColumn( 'permission_index', 'perm_7', array(
			'name'			=> 'perm_7',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );

		/* Forums */
		if( \IPS\Db::i()->checkForColumn( 'forums', 'quick_reply' ) )
		{
			\IPS\Db::i()->dropColumn( 'forums', 'quick_reply' );
		}

		if( !\IPS\Db::i()->checkForColumn( 'forums', 'tag_predefined' ) )
		{
			\IPS\Db::i()->addColumn( 'forums', array(
				'name'			=> 'tag_predefined',
				'type'			=> 'text',
				'allow_null'	=> true,
				'default'		=> null
			) );
		}

		/* IP addresses */
		\IPS\Db::i()->changeColumn( 'captcha', 'captcha_ipaddress', array(
			'name'			=> 'captcha_ipaddress',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );
		\IPS\Db::i()->changeColumn( 'converge_local', 'converge_ip_address', array(
			'name'			=> 'converge_ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );
		\IPS\Db::i()->changeColumn( 'api_log', 'api_log_ip', array(
			'name'			=> 'api_log_ip',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );
		\IPS\Db::i()->changeColumn( 'api_users', 'api_user_ip', array(
			'name'			=> 'api_user_ip',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );
		\IPS\Db::i()->changeColumn( 'core_sys_cp_sessions', 'session_ip_address', array(
			'name'			=> 'session_ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );

		/* Sessions */
		\IPS\Db::i()->delete( 'sessions' );
		if( \IPS\Db::i()->checkForColumn( 'sessions', 'location' ) )
		{
			\IPS\Db::i()->dropColumn( 'sessions', 'location' );
		}
		\IPS\Db::i()->changeColumn( 'sessions', 'ip_address', array(
			'name'			=> 'ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );
		\IPS\Db::i()->changeColumn( 'sessions', 'member_name', array(
			'name'			=> 'member_name',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> true,
			'default'		=> null
		) );
		\IPS\Db::i()->changeColumn( 'sessions', 'search_thread_time', array(
			'name'			=> 'search_thread_time',
			'type'			=> 'int',
			'length'		=> 10,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->changeColumn( 'upgrade_sessions', 'session_ip_address', array(
			'name'			=> 'session_ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );

		\IPS\Db::i()->changeColumn( 'validating', 'ip_address', array(
			'name'			=> 'ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false,
			'default'		=> '0'
		) );

		\IPS\Db::i()->changeColumn( 'core_sys_lang', 'lang_short', array(
			'name'			=> 'lang_short',
			'type'			=> 'varchar',
			'length'		=> 32,
			'allow_null'	=> false
		) );

		if( !\IPS\Db::i()->checkForTable( 'core_editor_autosave' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_editor_autosave',
				'columns'	=> array(
					array(
						'name'			=> 'eas_key',
						'type'			=> 'char',
						'length'		=> 32,
						'allow_null'	=> false
					),
					array(
						'name'			=> 'eas_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'eas_app',
						'type'			=> 'varchar',
						'length'		=> 50,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'eas_section',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'eas_updated',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'eas_content',
						'type'			=> 'text',
						'allow_null'	=> true,
						'default'		=> null
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'unique',
						'name'		=> 'eas_key',
						'columns'	=> array( 'eas_key' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'eas_member_lookup',
						'columns'	=> array( 1 => 'eas_member_id', 2 => 'eas_app', 3 => 'eas_section' ),
						'length'	=> array( 1 => null, 2 => null, 3 => 100 )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'eas_updated',
						'columns'	=> array( 'eas_updated' )
					)
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable( 'core_tags' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_tags',
				'columns'	=> array(
					array(
						'name'			=> 'tag_id',
						'type'			=> 'bigint',
						'length'		=> 20,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'tag_aai_lookup',
						'type'			=> 'char',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'tag_aap_lookup',
						'type'			=> 'char',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'tag_meta_app',
						'type'			=> 'varchar',
						'length'		=> 100,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'tag_meta_area',
						'type'			=> 'varchar',
						'length'		=> 100,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'tag_meta_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'tag_meta_parent_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'tag_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'tag_added',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'tag_prefix',
						'type'			=> 'int',
						'length'		=> 1,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'tag_text',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> true,
						'default'		=> null
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'tag_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'tag_aai_lookup',
						'columns'	=> array( 'tag_aai_lookup' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'tag_app',
						'columns'	=> array( 1 => 'tag_meta_app', 2 => 'tag_meta_area' ),
						'length'	=> array( 1 => 100, 2 => 100 )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'tag_member_id',
						'columns'	=> array( 'tag_member_id' ),
					),
					array(
						'type'		=> 'key',
						'name'		=> 'tag_aap_lookup',
						'columns'	=> array( 1 => 'tag_aap_lookup', 2 => 'tag_text' ),
						'length'	=> array( 1 => null, 2 => 200 )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'tag_added',
						'columns'	=> array( 'tag_added' ),
					),
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable( 'core_tags_perms' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_tags_perms',
				'columns'	=> array(
					array(
						'name'			=> 'tag_perm_aai_lookup',
						'type'			=> 'char',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'tag_perm_aap_lookup',
						'type'			=> 'char',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'tag_perm_text',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'tag_perm_visible',
						'type'			=> 'int',
						'length'		=> 1,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 1
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'unique',
						'name'		=> 'tag_perm_aai_lookup',
						'columns'	=> array( 'tag_perm_aai_lookup' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'tag_perm_aap_lookup',
						'columns'	=> array( 'tag_perm_aap_lookup' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'tag_lookup',
						'columns'	=> array( 1 => 'tag_perm_text', 2 => 'tag_perm_visible' ),
						'length'	=> array( 1 => 200, 2 => null )
					),
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable( 'core_tags_cache' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_tags_cache',
				'columns'	=> array(
					array(
						'name'			=> 'tag_cache_key',
						'type'			=> 'char',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'tag_cache_text',
						'type'			=> 'text',
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'tag_cache_date',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'unique',
						'name'		=> 'tag_cache_key',
						'columns'	=> array( 'tag_cache_key' )
					),
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable( 'cache_simple' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'cache_simple',
				'columns'	=> array(
					array(
						'name'			=> 'cache_id',
						'type'			=> 'varchar',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'cache_perm_key',
						'type'			=> 'varchar',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'cache_time',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'cache_data',
						'type'			=> 'mediumtext',
						'allow_null'	=> false
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'unique',
						'name'		=> 'lookup',
						'columns'	=> array( 'cache_id', 'cache_perm_key' )
					),
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable( 'core_incoming_email_log' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_incoming_email_log',
				'columns'	=> array(
					array(
						'name'			=> 'log_id',
						'type'			=> 'int',
						'length'		=> 11,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'log_email',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'log_time',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> true,
						'default'		=> null
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'log_id' )
					),
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable( 'core_geolocation_cache' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_geolocation_cache',
				'columns'	=> array(
					array(
						'name'			=> 'geocache_key',
						'type'			=> 'varchar',
						'length'		=> 32,
						'allow_null'	=> false,
					),
					array(
						'name'			=> 'geocache_lat',
						'type'			=> 'varchar',
						'length'		=> 100,
						'allow_null'	=> false,
					),
					array(
						'name'			=> 'geocache_lon',
						'type'			=> 'varchar',
						'length'		=> 100,
						'allow_null'	=> false,
					),
					array(
						'name'			=> 'geocache_raw',
						'type'			=> 'text',
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'geocache_country',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'geocache_district',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'geocache_district2',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'geocache_locality',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'geocache_type',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'geocache_engine',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'geocache_added',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'geocache_short',
						'type'			=> 'text',
						'allow_null'	=> true,
						'default'		=> null
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'geocache_key' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'geo_lat_lon',
						'columns'	=> array( 'geocache_lat', 'geocache_lon' )
					),
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable( 'skin_generator_sessions' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'skin_generator_sessions',
				'columns'	=> array(
					array(
						'name'			=> 'sg_session_id',
						'type'			=> 'varchar',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'sg_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'sg_skin_set_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'sg_date_start',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'sg_data',
						'type'			=> 'mediumtext',
						'allow_null'	=> true,
						'default'		=> null
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'sg_session_id' )
					),
				)
			)	);
		}

		if( \IPS\Db::i()->checkForTable( 'search_results' ) )
		{
			\IPS\Db::i()->dropTable( 'search_results' );
		}

		if( \IPS\Db::i()->checkForColumn( 'attachments_type', 'atype_photo' ) )
		{
			\IPS\Db::i()->dropColumn( 'attachments_type', 'atype_photo' );
		}

		if( \IPS\Db::i()->checkForColumn( 'groups', 'g_avatar_upload' ) )
		{
			\IPS\Db::i()->dropColumn( 'groups', 'g_avatar_upload' );
		}

		if( \IPS\Db::i()->checkForColumn( 'custom_bbcode', 'bbcode_strip_search' ) )
		{
			\IPS\Db::i()->dropColumn( 'custom_bbcode', 'bbcode_strip_search' );
		}

		if( !\IPS\Db::i()->checkForColumn( 'emoticons', 'emo_position' ) )
		{
			\IPS\Db::i()->addColumn( 'emoticons', array(
				'name'			=> 'emo_position',
				'type'			=> 'int',
				'length'		=> 5,
				'allow_null'	=> false,
				'default'		=> 0
			) );
		}

		\IPS\Db::i()->query( "UPDATE " . \IPS\Db::i()->prefix . "emoticons SET image=REPLACE(image, '.gif', '.png') WHERE emo_set='default' AND image IN ( 'angry.gif', 'biggrin.gif', 'blink.gif', 'blush.gif', 'cool.gif', 'dry.gif', 'excl.gif', 
		'happy.gif', 'huh.gif', 'laugh.gif', 'mellow.gif', 'ohmy.gif', 'ph34r.gif', 'sad.gif', 'sleep.gif', 'smile.gif', 'tongue.gif', 'unsure.gif', 'wacko.gif', 'wink.gif', 'wub.gif' );" );

		return TRUE;
	}

	/**
	 * Step 4
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{	
		/* Fix likes */
		$addLikeNotifySent	= '';
		if( !\IPS\Db::i()->checkForColumn( 'core_like', 'like_notify_sent' ) )
		{
			$addLikeNotifySent = "ADD COLUMN like_notify_sent INT(10) NOT NULL DEFAULT 0,";
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'core_like',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_like ADD COLUMN like_lookup_area CHAR(32) NOT NULL DEFAULT '', 
				ADD COLUMN like_visible TINYINT(1) NOT NULL DEFAULT 1,
				{$addLikeNotifySent}
				DROP INDEX find_rel_favs,
				DROP INDEX like_member_id,
				CHANGE like_id like_id VARCHAR(32) NOT NULL DEFAULT '',
				CHANGE like_lookup_id like_lookup_id VARCHAR(32) NOT NULL DEFAULT '',
				ADD INDEX find_rel_likes ( like_lookup_id(32), like_visible, like_is_anon, like_added ),
				ADD INDEX like_member_id ( like_member_id, like_visible, like_added ),
				ADD INDEX like_lookup_area ( like_lookup_area(32), like_visible ),
				ADD INDEX notification_task ( like_notify_do, like_app(50), like_area(50), like_visible, like_notify_sent, like_notify_freq(100) );"
		),
		array(
			'table' => 'core_like',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "core_like SET like_id = MD5( CONCAT( like_app, ';', like_area, ';', like_rel_id, ';', like_member_id  ) ), like_lookup_area = MD5( CONCAT( like_app, ';', like_area, ';', like_member_id ) );"
		),
		array(
			'table' => 'inline_notifications',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "inline_notifications ADD COLUMN notify_meta_app VARCHAR(50) NULL DEFAULT NULL, 
				ADD COLUMN notify_meta_area VARCHAR(100) NULL DEFAULT NULL, 
				ADD COLUMN notify_meta_id INT(10) NOT NULL DEFAULT 0,
				ADD COLUMN notify_meta_key VARCHAR(32) NULL DEFAULT NULL,
				ADD INDEX notify_meta_key ( notify_meta_key );"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 5 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Step 5
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		\IPS\Db::i()->dropTable( 'email_logs' );

		\IPS\Db::i()->changeColumn( 'profile_ratings', 'rating_ip_address', array(
			'name'			=> 'rating_ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );

		\IPS\Db::i()->changeColumn( 'topic_ratings', 'rating_ip_address', array(
			'name'			=> 'rating_ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );

		\IPS\Db::i()->changeColumn( 'voters', 'ip_address', array(
			'name'			=> 'ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );

		\IPS\Db::i()->changeColumn( 'core_hooks', 'hook_installed', array(
			'name'			=> 'hook_installed',
			'type'			=> 'int',
			'length'		=> 10,
			'allow_null'	=> false,
			'default'		=> 0
		) );
		\IPS\Db::i()->changeColumn( 'core_hooks', 'hook_updated', array(
			'name'			=> 'hook_updated',
			'type'			=> 'int',
			'length'		=> 10,
			'allow_null'	=> false,
			'default'		=> 0
		) );
		\IPS\Db::i()->addColumn( 'core_hooks', array(
			'name'			=> 'hook_global_caches',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->addColumn( 'attachments', array(
			'name'			=> 'attach_parent_id',
			'type'			=> 'int',
			'length'		=> 10,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addIndex( 'attachments', array(
			'type'			=> 'key',
			'name'			=> 'attach_parent_id',
			'columns'		=> array( 'attach_parent_id', 'attach_rel_module' )
		) );

		\IPS\Db::i()->query( "UPDATE " . \IPS\Db::i()->prefix . "attachments a SET a.attach_parent_id=CASE WHEN (select p.topic_id from " . \IPS\Db::i()->prefix . "posts p WHERE p.pid=a.attach_rel_id) IS NULL THEN 0 ELSE (select p.topic_id from " . \IPS\Db::i()->prefix . "posts p WHERE p.pid=a.attach_rel_id) END WHERE a.attach_rel_module='post'" );

		return TRUE;
	}

	/**
	 * Step 6
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
	{
		\IPS\Db::i()->addColumn( 'member_status_updates', array(
			'name'			=> 'status_author_id',
			'type'			=> 'int',
			'length'		=> 10,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'member_status_updates', array(
			'name'			=> 'status_author_ip',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false,
			'default'		=> ''
		) );
		\IPS\Db::i()->addColumn( 'member_status_updates', array(
			'name'			=> 'status_approved',
			'type'			=> 'int',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 1
		) );
		\IPS\Db::i()->addIndex( 'member_status_updates', array(
			'type'			=> 'key',
			'name'			=> 'status_author_lookup',
			'columns'		=> array( 'status_author_id', 'status_member_id', 'status_date' )
		) );
		\IPS\Db::i()->addIndex( 'member_status_updates', array(
			'type'			=> 'key',
			'name'			=> 'status_member_id',
			'columns'		=> array( 'status_member_id', 'status_approved', 'status_date' )
		) );

		\IPS\Db::i()->update( 'member_status_updates', "status_author_id=status_member_id" );

		\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "member_status_updates (status_member_id, status_author_id, status_date, status_content, status_replies, status_last_ids, status_is_latest, status_is_locked, status_hash, status_imported, status_creator, status_author_ip, status_approved)
		SELECT comment_for_member_id, comment_by_member_id, comment_date, comment_content, 0, '', 0, 0, MD5(comment_content), 0, '', comment_ip_address, comment_approved FROM " . \IPS\Db::i()->prefix . "profile_comments" );

		\IPS\Db::i()->dropTable( 'profile_comments' );

		require \IPS\ROOT_PATH . '/conf_global.php';
		\IPS\Db::i()->update( 'groups', array( 'g_view_board' => 1 ), array( 'g_id=?', $INFO['guest_group'] ) );

		return TRUE;
	}

	/**
	 * Step 7
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'message_posts',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "message_posts CHANGE COLUMN msg_ip_address msg_ip_address VARCHAR(46) NOT NULL DEFAULT '';"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 8 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Step 8
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step8()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'members',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members CHANGE COLUMN ip_address ip_address VARCHAR(46) NOT NULL DEFAULT '', 
				DROP COLUMN hide_email,
				DROP COLUMN email_full,
				DROP COLUMN view_prefs,
				DROP COLUMN view_avs,
				DROP INDEX mgroup,
				ADD INDEX mgroup (member_group_id, member_id);"
		),
		array(
			'table' => 'profile_portal',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "profile_portal SET pp_setting_count_visitors=1 WHERE pp_setting_count_visitors>1;"
		),
		array(
			'table' => 'profile_portal',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "profile_portal CHANGE COLUMN tc_last_sid_import tc_last_sid_import VARCHAR(50) NULL DEFAULT '0', 
				ADD COLUMN pp_gravatar VARCHAR(255) NOT NULL DEFAULT '',
				ADD COLUMN pp_photo_type VARCHAR(20) NOT NULL DEFAULT '',
				CHANGE COLUMN pp_setting_count_visitors pp_setting_count_visitors TINYINT(1) NOT NULL DEFAULT 0;"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 9 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Step 9
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step9()
	{
		\IPS\Db::i()->query( "INSERT IGNORE INTO " . \IPS\Db::i()->prefix . "core_like
				(like_id, like_lookup_id, like_lookup_area, like_app, like_area, like_rel_id, like_member_id, like_is_anon, like_added, like_notify_do, like_notify_freq, like_notify_sent, like_visible)
				SELECT MD5(CONCAT('forums;forums;', forum_id, ';', member_id)), MD5(CONCAT('forums;forums;', forum_id)), MD5(CONCAT('forums;forums;', member_id)), 'forums', 'forums', forum_id, member_id, 0, start_date, CASE WHEN forum_track_type='none' THEN 0 ELSE 1 END, forum_track_type, last_sent, 1
				FROM " . \IPS\Db::i()->prefix . "forum_tracker" );

		\IPS\Db::i()->query( "INSERT IGNORE INTO " . \IPS\Db::i()->prefix . "core_like
				(like_id, like_lookup_id, like_lookup_area, like_app, like_area, like_rel_id, like_member_id, like_is_anon, like_added, like_notify_do, like_notify_freq, like_notify_sent, like_visible)
				SELECT MD5(CONCAT('forums;topics;', topic_id, ';', member_id)), MD5(CONCAT('forums;topics;', topic_id)), MD5(CONCAT('forums;topics;', member_id)), 'forums', 'topics', topic_id, member_id, 0, start_date, CASE WHEN topic_track_type='none' THEN 0 ELSE 1 END, topic_track_type, last_sent, 1
				FROM " . \IPS\Db::i()->prefix . "tracker" );

		\IPS\Db::i()->dropTable( "forum_tracker" );
		\IPS\Db::i()->dropTable( "tracker" );

		return TRUE;
	}

	/**
	 * Step 10
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step10()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'topics',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics ADD COLUMN tdelete_time INT(10) NOT NULL DEFAULT 0,
				ADD COLUMN moved_on INT(10) NOT NULL DEFAULT 0,
				ADD INDEX approved (approved, tdelete_time),
				ADD INDEX moved_redirects (moved_on, moved_to, pinned),
				DROP INDEX starter_id,
				ADD INDEX starter_id (starter_id, forum_id, approved, start_date);"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 11 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Step 11
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step11()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'posts',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "posts CHANGE author_name author_name VARCHAR( 255 ) NULL DEFAULT NULL,
	CHANGE ip_address ip_address VARCHAR( 46 ) NOT NULL,
	ADD post_bwoptions INT(10) UNSIGNED NOT NULL DEFAULT 0,
	ADD pdelete_time INT NOT NULL DEFAULT 0,
	ADD post_field_int INT(10) NOT NULL DEFAULT 0,
	ADD post_field_t1 TEXT NULL DEFAULT NULL,
	ADD post_field_t2 TEXT NULL DEFAULT NULL,
	DROP post_parent,
	DROP INDEX author_id,
	ADD INDEX author_id ( author_id , post_date , queued ),
	ADD INDEX queued (queued,pdelete_time)"
		) ) );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 12 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Step 12
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step12()
	{
		\IPS\Db::i()->addIndex( 'warn_logs', array(
			'type'			=> 'key',
			'name'			=> 'wlog_mid',
			'columns'		=> array( 'wlog_mid', 'wlog_date' )
		) );

		return TRUE;
	}

	/**
	 * Step 13
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step13()
	{
		\IPS\Db::i()->changeColumn( 'admin_login_logs', 'admin_ip_address', array(
			'name'			=> 'admin_ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false,
			'default'		=> '0.0.0.0'
		) );

		return TRUE;
	}

	/**
	 * Step 14
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step14()
	{
		\IPS\Db::i()->changeColumn( 'admin_logs', 'ip_address', array(
			'name'			=> 'ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> true,
			'default'		=> null
		) );

		return TRUE;
	}

	/**
	 * Step 15
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step15()
	{
		\IPS\Db::i()->changeColumn( 'dnames_change', 'dname_ip_address', array(
			'name'			=> 'dname_ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );

		return TRUE;
	}

	/**
	 * Step 16
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step16()
	{
		\IPS\Db::i()->changeColumn( 'error_logs', 'log_ip_address', array(
			'name'			=> 'log_ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> true,
			'default'		=> null
		) );
		\IPS\Db::i()->changeColumn( 'error_logs', 'log_date', array(
			'name'			=> 'log_date',
			'type'			=> 'int',
			'length'		=> 10,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		return TRUE;
	}

	/**
	 * Step 17
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step17()
	{
		\IPS\Db::i()->changeColumn( 'moderator_logs', 'ip_address', array(
			'name'			=> 'ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false,
			'default'		=> '0'
		) );
		\IPS\Db::i()->changeColumn( 'moderator_logs', 'member_name', array(
			'name'			=> 'member_name',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> false
		) );

		return TRUE;
	}

	/**
	 * Step 18
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step18()
	{
		\IPS\Db::i()->changeColumn( 'spam_service_log', 'ip_address', array(
			'name'			=> 'ip_address',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false
		) );

		return TRUE;
	}

	/**
	 * Step 19
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step19()
	{
		\IPS\Db::i()->changeColumn( 'task_logs', 'log_ip', array(
			'name'			=> 'log_ip',
			'type'			=> 'varchar',
			'length'		=> 46,
			'allow_null'	=> false,
			'default'		=> '0'
		) );

		return TRUE;
	}

	/**
	 * Step 20
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step20()
	{
		/* Update array for archive forums */
		$_update	= array(
							'perm_3'	=> '',
							'perm_4'	=> '',
							'perm_5'	=> '',
							);

		/* Get 'archive' forums */
		foreach( \IPS\Db::i()->select( '*', 'forums', array( 'status=?', 0 ) ) as $r )
		{
			\IPS\Db::i()->update( 'permission_index', $_update, "app='forums' AND perm_type='forum' AND perm_type_id={$r['id']}" );
		}
		
		/* Now drop the status field in forums table */
		\IPS\Db::i()->dropColumn( 'forums', 'status' );

		return TRUE;
	}

	/**
	 * Step 21
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step21()
	{
		/* Check for existing login method config files (in the old location) and import config files if needed */
		try
		{
			if( is_dir( \IPS\ROOT_PATH . '/admin/sources/loginauth' ) )
			{
				foreach( new \DirectoryIterator( \IPS\ROOT_PATH . '/admin/sources/loginauth' ) as $file )
				{
					if ( $file->isDir() )
					{
						if( is_file( $file->getPathname() . '/conf.php' ) )
						{
							$LOGIN_CONF = array();
							
							include( $file->getPathname() . '/conf.php' );/*noLibHook*/

							if( \is_array($LOGIN_CONF) AND \count($LOGIN_CONF) )
							{
								\IPS\Db::i()->update( 'login_methods', array( 'login_custom_config' => @serialize( $LOGIN_CONF ) ), array( "login_folder_name=?", $file->getFilename() ) );
							}
						}
					}
				}
			}
		}
		catch( \Exception $e )
		{
		}

		return TRUE;
	}

	/**
	 * Step 22
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step22()
	{
		/* @note Steps we skipped that previously used to run:
			-- Uninstalling old hooks (this is done in the 4.0 step already)
			-- Resetting the default skin (this is done in the 4.0 step already)
			-- Converting hook data (4.0 uninstalls all existing hooks anyways)
		*/

		/* Our options */
		$convertFrom	= $_SESSION['upgrade_options']['core']['32000']['avatar_or_photo'];

		/* Init */
		$st		= \intval( \IPS\Request::i()->extra );
		$did	= 0;
		$each	= 200;

		/* Loop over members */
		foreach( \IPS\Db::i()->select( '*', 'profile_portal', null, 'pp_member_id ASC', array( $st, $each ) ) as $r )
		{
			$did++;

			$update	= array();
			
			if( $r['fb_photo'] )
			{
				$update['pp_photo_type']	= 'facebook';
			}
			else if( $r['tc_photo'] )
			{
				$update['pp_photo_type']	= 'twitter';
			}
			else
			{
				if( $convertFrom == 'avatars' )
				{
					if( $r['avatar_type'] == 'upload' AND $r['avatar_location'] )
					{
						$update['pp_photo_type']	= 'custom';
						$update['pp_main_photo']	= $r['avatar_location'];
						$_dims						= @getimagesize( \IPS\Settings::i()->upload_dir . '/' . $r['avatar_location'] );
						$update['pp_main_width']	= $_dims[0] ? $_dims[0] : 1;
						$update['pp_main_height']	= $_dims[1] ? $_dims[1] : 1;
					}
					else if( $r['avatar_type'] == 'gravatar' )
					{
						$update['pp_photo_type']	= 'gravatar';
						$update['pp_gravatar']		= $r['avatar_location'];
						
						$md5Gravatar = md5( $update['pp_gravatar'] );
						
						$_url	= "http://www.gravatar.com";
						
						if( \IPS\Request::i()->isSecure() )
						{
							$_url	= "https://secure.gravatar.com";
						}
						
						$update['pp_main_photo']	= $_url . "/avatar/" .$md5Gravatar . "?s=100";
						$update['pp_main_width']	= 100;
						$update['pp_main_height']	= 100;
						$update['pp_thumb_photo']	= $_url . "/avatar/" .$md5Gravatar . "?s=100";
						$update['pp_thumb_width']	= 100;
						$update['pp_thumb_height']	= 100;
					}
				}
				else
				{
					if( $r['pp_main_photo'] )
					{
						$update['pp_photo_type']	= 'custom';
						$update['pp_main_photo']	= $r['pp_main_photo'];
					}
				}
			}
			
			if( $update['pp_photo_type'] == 'custom' )
			{
				if( $image = @file_get_contents( \IPS\Settings::i()->upload_dir . '/' . str_replace( 'upload:', '', $update['pp_main_photo'] ) ) )
				{
					/* Create an \IPS\Image object */
					try
					{
						$image = \IPS\Image::create( $image );
						
						/* Resize it */
						$image->resizeToMax( 200, 200 );
	
						/* What are we calling this? */		
						$thumbnailName = mb_substr( $image->originalFilename, 0, mb_strrpos( $image->originalFilename, '.' ) ) . '.thumb' . mb_substr( $image->originalFilename, mb_strrpos( $image->originalFilename, '.' ) );
						
						/* Create and return */
						$thumbnail = file_put_contents( \IPS\Settings::i()->upload_dir . '/' . $thumbnailName, (string) $image );
	
						$update['pp_thumb_photo']	= $thumbnailName;
						$update['pp_thumb_width']	= \intval( $image->width );
						$update['pp_thumb_height']	= \intval( $image->height );
					}
					catch( \InvalidArgumentException $e )
					{
						/* It is not a valid image */
						$update['pp_photo_type']	= '';
						$update['pp_main_photo']	= NULL;
						$update['pp_main_width']	= NULL;
						$update['pp_main_height']	= NULL;
						$update['pp_thumb_photo']	= NULL;
						$update['pp_thumb_width']	= NULL;
						$update['pp_thumb_height']	= NULL;
					}
				}
			}
			
			if( \count($update) )
			{
				\IPS\Db::i()->update( 'profile_portal', $update, 'pp_member_id=' . $r['pp_member_id'] );
			}
		}

		/* Show message and redirect */
		if( $did > 0 )
		{
			return ( $st + $did );
		}
		else
		{
			return TRUE;
		}
	}
}