<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_31000;

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
		if ( ! isset( \IPS\Request::i()->run_anyway ) )
		{
			if( \IPS\Db::i()->checkForIndex( 'rss_import', 'rss_import_enabled' ) )
			{
				\IPS\Db::i()->dropIndex( 'rss_import', 'rss_import_enabled' );
			}
	
			if( \IPS\Db::i()->checkForIndex( 'rss_import', 'rss_grab' ) )
			{
				\IPS\Db::i()->dropIndex( 'rss_import', 'rss_grab' );
			}
	
			\IPS\Db::i()->addIndex( 'rss_import', array(
				'type'			=> 'key',
				'name'			=> 'rss_grab',
				'columns'		=> array( 'rss_import_enabled', 'rss_import_last_import' )
			) );
	
			\IPS\Db::i()->delete( 'core_item_markers' );
	
			if( !\IPS\Db::i()->checkForColumn( 'core_item_markers', 'item_is_deleted' ) )
			{
				\IPS\Db::i()->addColumn( 'core_item_markers', array(
					'name'			=> 'item_is_deleted',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				) );
			}
	
			\IPS\Db::i()->dropIndex( 'core_item_markers', 'item_member_id' );
	
			\IPS\Db::i()->addIndex( 'core_item_markers', array(
				'type'			=> 'key',
				'name'			=> 'item_member_id',
				'columns'		=> array( 'item_member_id', 'item_is_deleted' )
			) );
	
			if( !\IPS\Db::i()->checkForColumn( 'pfields_data', 'pf_search_type' ) )
			{
				\IPS\Db::i()->addColumn( 'pfields_data', array(
					'name'			=> 'pf_search_type',
					'type'			=> 'varchar',
					'length'		=> 5,
					'allow_null'	=> false,
					'default'		=> 'loose'
				) );
			}
	
			\IPS\Db::i()->delete( 'core_sys_conf_settings', array( "conf_key=?", 'photo_ext' ) );
			\IPS\Db::i()->delete( 'core_sys_conf_settings', array( "conf_key=?", 'seo_r301' ) );
			\IPS\Db::i()->delete( 'core_sys_settings_titles', array( "conf_title_keyword=?", 'searchenginespiders' ) );
	
			\IPS\Settings::i()->clearCache();
	
			\IPS\Db::i()->changeColumn( 'sessions', 'login_type', array(
				'name'			=> 'login_type',
				'type'			=> 'tinyint',
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> 0
			) );
	
			/* We use 150 length just to prevent "specified key was too long" errors, since these columns will be going away anyways */
			\IPS\Db::i()->changeColumn( 'sessions', 'location_1_type', array(
				'name'			=> 'location_1_type',
				'type'			=> 'varchar',
				'length'		=> 150,
				'allow_null'	=> false
			) );
	
			\IPS\Db::i()->changeColumn( 'sessions', 'location_2_type', array(
				'name'			=> 'location_2_type',
				'type'			=> 'varchar',
				'length'		=> 150,
				'allow_null'	=> false
			) );
	
			\IPS\Db::i()->changeColumn( 'sessions', 'location_3_type', array(
				'name'			=> 'location_3_type',
				'type'			=> 'varchar',
				'length'		=> 150,
				'allow_null'	=> false
			) );

			\IPS\Db::i()->dropIndex( 'core_sys_cp_sessions', 'session_running_time' );
			\IPS\Db::i()->addIndex( 'core_sys_cp_sessions', array(
				'type'			=> 'key',
				'name'			=> 'session_running_time',
				'columns'		=> array( 'session_running_time' )
			) );

			\IPS\Db::i()->dropIndex( 'core_sys_cp_sessions', 'session_member_id' );
			\IPS\Db::i()->addIndex( 'core_sys_cp_sessions', array(
				'type'			=> 'key',
				'name'			=> 'session_member_id',
				'columns'		=> array( 'session_member_id' )
			) );

			\IPS\Db::i()->dropIndex( 'upgrade_history', 'upgrades' );
			\IPS\Db::i()->addIndex( 'upgrade_history', array(
				'type'			=> 'key',
				'name'			=> 'upgrades',
				'columns'		=> array( 'upgrade_app', 'upgrade_version_id' )
			) );

			\IPS\Db::i()->dropIndex( 'validating', 'lost_pass' );
			\IPS\Db::i()->addIndex( 'validating', array(
				'type'			=> 'key',
				'name'			=> 'lost_pass',
				'columns'		=> array( 'lost_pass' )
			) );

			\IPS\Db::i()->dropIndex( 'validating', 'coppa_user' );
			\IPS\Db::i()->addIndex( 'validating', array(
				'type'			=> 'key',
				'name'			=> 'coppa_user',
				'columns'		=> array( 'coppa_user' )
			) );

			\IPS\Db::i()->dropIndex( 'api_log', 'api_log_date' );
			\IPS\Db::i()->addIndex( 'api_log', array(
				'type'			=> 'key',
				'name'			=> 'api_log_date',
				'columns'		=> array( 'api_log_date' )
			) );

			\IPS\Db::i()->dropIndex( 'core_applications', 'app_directory' );
			\IPS\Db::i()->addIndex( 'core_applications', array(
				'type'			=> 'key',
				'name'			=> 'app_directory',
				'columns'		=> array( 'app_directory' )
			) );

			\IPS\Db::i()->dropIndex( 'bulk_mail', 'mail_start' );
			\IPS\Db::i()->addIndex( 'bulk_mail', array(
				'type'			=> 'key',
				'name'			=> 'mail_start',
				'columns'		=> array( 'mail_start' )
			) );

			\IPS\Db::i()->dropIndex( 'skin_collections', 'set_is_default' );
			\IPS\Db::i()->addIndex( 'skin_collections', array(
				'type'			=> 'key',
				'name'			=> 'set_is_default',
				'columns'		=> array( 'set_is_default' )
			) );

			\IPS\Db::i()->dropIndex( 'rc_classes', 'onoff' );
			\IPS\Db::i()->addIndex( 'rc_classes', array(
				'type'			=> 'key',
				'name'			=> 'onoff',
				'columns'		=> array( 'onoff', 'mod_group_perm' )
			) );
	
			$tasks	= array();
	
			foreach( \IPS\Db::i()->select( 'task_id, task_key, task_application', 'task_manager' ) as $task )
			{
				$tasks[ $task['task_key'] . $task['task_application'] ]	= $task['task_id'];
			}
	
			\IPS\Db::i()->delete( 'task_manager', "task_key='' OR task_key IS NULL OR task_application='' OR task_application IS NULL" );
	
			if( \count( $tasks ) )
			{
				\IPS\Db::i()->delete( 'task_manager', "task_id NOT IN(" . implode( ',', $tasks ) . ")" );
			}

			\IPS\Db::i()->dropIndex( 'task_manager', 'task_key' );
			\IPS\Db::i()->addIndex( 'task_manager', array(
				'type'			=> 'unique',
				'name'			=> 'task_key',
				'columns'		=> array( 'task_application', 'task_key' )
			) );

			\IPS\Db::i()->dropIndex( 'announcements', 'announce_end' );
			\IPS\Db::i()->addIndex( 'announcements', array(
				'type'			=> 'key',
				'name'			=> 'announce_end',
				'columns'		=> array( 'announce_end' )
			) );
	
			if( \IPS\Db::i()->checkForTable('cal_events') )
			{
				\IPS\Db::i()->dropIndex( 'cal_events', 'daterange' );
	
				\IPS\Db::i()->addIndex( 'cal_events', array(
					'type'			=> 'key',
					'name'			=> 'daterange',
					'columns'		=> array( 'event_approved', 'event_unix_from', 'event_unix_to' )
				) );

				\IPS\Db::i()->dropIndex( 'cal_calendars', 'cal_rss_export' );
				\IPS\Db::i()->addIndex( 'cal_calendars', array(
					'type'			=> 'key',
					'name'			=> 'cal_rss_export',
					'columns'		=> array( 'cal_rss_export' )
				) );
			}

			\IPS\Db::i()->dropIndex( 'core_sys_conf_settings', 'conf_group' );
			\IPS\Db::i()->addIndex( 'core_sys_conf_settings', array(
				'type'			=> 'key',
				'name'			=> 'conf_group',
				'columns'		=> array( 'conf_group', 'conf_position', 'conf_title' )
			) );

			\IPS\Db::i()->dropIndex( 'core_uagent_groups', 'ugroup_title' );
			\IPS\Db::i()->addIndex( 'core_uagent_groups', array(
				'type'			=> 'key',
				'name'			=> 'ugroup_title',
				'columns'		=> array( 'ugroup_title' )
			) );

			\IPS\Db::i()->dropIndex( 'core_uagents', 'ordering' );
			\IPS\Db::i()->addIndex( 'core_uagents', array(
				'type'			=> 'key',
				'name'			=> 'ordering',
				'columns'		=> array( 'uagent_position', 'uagent_key' )
			) );

			\IPS\Db::i()->dropIndex( 'core_sys_conf_settings', 'conf_add_cache' );
			\IPS\Db::i()->addIndex( 'core_sys_conf_settings', array(
				'type'			=> 'key',
				'name'			=> 'conf_add_cache',
				'columns'		=> array( 'conf_add_cache' )
			) );

			\IPS\Db::i()->dropIndex( 'emoticons', 'emo_set' );
			\IPS\Db::i()->addIndex( 'emoticons', array(
				'type'			=> 'key',
				'name'			=> 'emo_set',
				'columns'		=> array( 'emo_set' )
			) );

			\IPS\Db::i()->dropIndex( 'skin_templates', 'template_set_id' );
			\IPS\Db::i()->addIndex( 'skin_templates', array(
				'type'			=> 'key',
				'name'			=> 'template_set_id',
				'columns'		=> array( 'template_set_id' )
			) );

			\IPS\Db::i()->dropIndex( 'skin_css', 'css_set_id' );
			\IPS\Db::i()->addIndex( 'skin_css', array(
				'type'			=> 'key',
				'name'			=> 'css_set_id',
				'columns'		=> array( 'css_set_id' )
			) );
	
			\IPS\Db::i()->dropIndex( 'message_topic_user_map', 'map_user' );
			\IPS\Db::i()->addIndex( 'message_topic_user_map', array(
				'type'			=> 'key',
				'name'			=> 'map_user',
				'columns'		=> array( 'map_user_id', 'map_folder_id', 'map_last_topic_reply' )
			) );

			if( !\IPS\Db::i()->checkForColumn( 'validating', 'spam_flag' ) )
			{
				\IPS\Db::i()->addColumn( 'validating', array(
					'name'			=> 'spam_flag',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				) );
			}

			\IPS\Db::i()->dropIndex( 'validating', 'spam_flag' );
			\IPS\Db::i()->addIndex( 'validating', array(
				'type'			=> 'key',
				'name'			=> 'spam_flag',
				'columns'		=> array( 'spam_flag' )
			) );

			\IPS\Db::i()->dropIndex( 'validating', 'member_id' );
			\IPS\Db::i()->addIndex( 'validating', array(
				'type'			=> 'key',
				'name'			=> 'member_id',
				'columns'		=> array( 'member_id' )
			) );

			if( !\IPS\Db::i()->checkForColumn( 'core_sys_lang', 'lang_protected' ) )
			{
				\IPS\Db::i()->addColumn('core_sys_lang', array(
					'name' => 'lang_protected',
					'type' => 'tinyint',
					'length' => 1,
					'allow_null' => false,
					'default' => 0
				));
			}
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'member_status_actions',
				'columns'	=> array(
					array(
						'name'			=> 'action_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'action_status_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'action_reply_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'action_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'action_date',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'action_key',
						'type'			=> 'varchar',
						'length'		=> 200,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'action_status_owner',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'action_app',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> 'members'
					),
					array(
						'name'			=> 'action_custom_text',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'action_custom',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'action_custom_url',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'action_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'action_status_id',
						'columns'	=> array( 'action_status_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'action_member_id',
						'columns'	=> array( 'action_member_id', 'action_date' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'action_date',
						'columns'	=> array( 'action_date' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'action_custom',
						'columns'	=> array( 'action_custom', 'action_date' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'member_status_replies',
				'columns'	=> array(
					array(
						'name'			=> 'reply_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'reply_status_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'reply_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'reply_date',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'reply_content',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					)
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'reply_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'reply_status_id',
						'columns'	=> array( 'reply_status_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'reply_member_id',
						'columns'	=> array( 'reply_member_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'reply_status_count',
						'columns'	=> array( 'reply_status_id', 'reply_member_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'reply_date',
						'columns'	=> array( 'reply_date' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'member_status_updates',
				'columns'	=> array(
					array(
						'name'			=> 'status_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'status_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'status_date',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'status_content',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'status_replies',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'status_last_ids',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'status_is_latest',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'status_is_locked',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'status_hash',
						'type'			=> 'varchar',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'status_imported',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'status_creator',
						'type'			=> 'varchar',
						'length'		=> 100,
						'allow_null'	=> false,
						'default'		=> ''
					)
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'status_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'status_member_id',
						'columns'	=> array( 'status_member_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'status_date',
						'columns'	=> array( 'status_date' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'status_is_latest',
						'columns'	=> array( 'status_is_latest', 'status_date' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 's_hash',
						'columns'	=> array( 'status_member_id', 'status_hash', 'status_imported' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'inline_notifications',
				'columns'	=> array(
					array(
						'name'			=> 'notify_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'notify_to_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'notify_sent',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'notify_read',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'notify_title',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'notify_text',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'notify_from_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'notify_type_key',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'notify_url',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					)
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'notify_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'notify_to_id',
						'columns'	=> array( 'notify_to_id', 'notify_sent' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'grabber',
						'columns'	=> array( 'notify_to_id', 'notify_read', 'notify_sent' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_soft_delete_log',
				'columns'	=> array(
					array(
						'name'			=> 'sdl_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'sdl_obj_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'sdl_obj_key',
						'type'			=> 'varchar',
						'length'		=> 20,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'sdl_obj_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'sdl_obj_date',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'sdl_obj_reason',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'sdl_locked',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'sdl_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'look_up',
						'columns'	=> array( 'sdl_obj_id', 'sdl_obj_key' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'twitter_connect',
				'columns'	=> array(
					array(
						'name'			=> 't_key',
						'type'			=> 'varchar',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 't_token',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 't_secret',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 't_time',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_share_links',
				'columns'	=> array(
					array(
						'name'			=> 'share_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'share_title',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'share_url',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'share_key',
						'type'			=> 'varchar',
						'length'		=> 50,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'share_enabled',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'share_position',
						'type'			=> 'int',
						'length'		=> 3,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'share_markup',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'share_canonical',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 1
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'share_id' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_share_links_log',
				'columns'	=> array(
					array(
						'name'			=> 'log_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'log_date',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'log_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'log_url',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'log_title',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'log_share_key',
						'type'			=> 'varchar',
						'length'		=> 50,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'log_data_app',
						'type'			=> 'varchar',
						'length'		=> 50,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'log_data_type',
						'type'			=> 'varchar',
						'length'		=> 50,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'log_data_primary_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'log_data_secondary_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'log_ip_address',
						'type'			=> 'varchar',
						'length'		=> 16,
						'allow_null'	=> false,
						'default'		=> ''
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'log_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'findstuff',
						'columns'	=> array( 'log_data_app', 'log_data_type', 'log_data_primary_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'log_date',
						'columns'	=> array( 'log_date' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'log_member_id',
						'columns'	=> array( 'log_member_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'log_share_key',
						'columns'	=> array( 'log_share_key' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'log_ip_address',
						'columns'	=> array( 'log_ip_address' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_share_links_caches',
				'columns'	=> array(
					array(
						'name'			=> 'cache_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'cache_key',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'cache_data',
						'type'			=> 'mediumtext',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'cache_date',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					)
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'cache_id' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_incoming_emails',
				'columns'	=> array(
					array(
						'name'			=> 'rule_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'rule_criteria_field',
						'type'			=> 'varchar',
						'length'		=> 4,
						'allow_null'	=> false
					),
					array(
						'name'			=> 'rule_criteria_type',
						'type'			=> 'varchar',
						'length'		=> 4,
						'allow_null'	=> false
					),
					array(
						'name'			=> 'rule_criteria_value',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'rule_app',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false
					),
					array(
						'name'			=> 'rule_added_by',
						'type'			=> 'mediumint',
						'length'		=> 8,
						'allow_null'	=> false
					),
					array(
						'name'			=> 'rule_added_date',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'rule_id' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'search_sessions',
				'columns'	=> array(
					array(
						'name'			=> 'session_id',
						'type'			=> 'varchar',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'session_created',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'session_updated',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'session_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'session_data',
						'type'			=> 'mediumtext',
						'allow_null'	=> true,
						'default'		=> null
					)
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'session_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'session_updated',
						'columns'	=> array( 'session_updated' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'skin_merge_session',
				'columns'	=> array(
					array(
						'name'			=> 'merge_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'merge_date',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'merge_set_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'merge_master_key',
						'type'			=> 'varchar',
						'length'		=> 200,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'merge_old_version',
						'type'			=> 'varchar',
						'length'		=> 200,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'merge_new_version',
						'type'			=> 'varchar',
						'length'		=> 200,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'merge_templates_togo',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'merge_css_togo',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'merge_templates_done',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'merge_css_done',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'merge_m_templates_togo',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'merge_m_css_togo',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'merge_m_templates_done',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'merge_m_css_done',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'merge_diff_done',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					)
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'merge_id' )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'skin_merge_changes',
				'columns'	=> array(
					array(
						'name'			=> 'change_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'change_key',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'change_session_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'change_updated',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'change_data_group',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'change_data_title',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'change_data_content',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'change_data_type',
						'type'			=> 'varchar',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 'template'
					),
					array(
						'name'			=> 'change_is_new',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'change_is_diff',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'change_can_merge',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'change_merge_content',
						'type'			=> 'mediumtext',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'change_is_conflict',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'change_final_content',
						'type'			=> 'mediumtext',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'change_changes_applied',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'change_original_content',
						'type'			=> 'mediumtext',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					)
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'change_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'change_key',
						'columns'	=> array( 'change_key', 'change_data_type' ),
						'length'	=> array( 150, NULL )
					)
				)
			)	);
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'facebook_oauth_temp',
				'columns'	=> array(
					array(
						'name'			=> 'f_key',
						'type'			=> 'varchar',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'f_token',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'f_time',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					)
				)
			)	);
	
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "member_status_updates (status_member_id, status_date, status_content, status_replies, status_is_latest ) SELECT pp_member_id, pp_status_update, pp_status, 0, 1 FROM " . \IPS\Db::i()->prefix . "profile_portal WHERE LENGTH(pp_status) > 0" );

			if( !\IPS\Db::i()->checkForColumn( 'groups', 'g_max_notifications' ) )
			{
				\IPS\Db::i()->addColumn('groups', array(
					'name' => 'g_max_notifications',
					'type' => 'mediumint',
					'length' => 0,
					'allow_null' => false,
					'default' => 0
				));
			}

			if( !\IPS\Db::i()->checkForColumn( 'groups', 'g_max_bgimg_upload' ) )
			{
				\IPS\Db::i()->addColumn('groups', array(
					'name' => 'g_max_bgimg_upload',
					'type' => 'int',
					'length' => 10,
					'allow_null' => false,
					'default' => 0
				));
			}

			if( !\IPS\Db::i()->checkForColumn( 'rc_classes', 'app' ) )
			{
				\IPS\Db::i()->addColumn('rc_classes', array(
					'name' => 'app',
					'type' => 'varchar',
					'length' => 32,
					'allow_null' => false
				));
			}

			if( !\IPS\Db::i()->checkForColumn( 'forums', 'disable_sharelinks' ) )
			{
				\IPS\Db::i()->addColumn('forums', array(
					'name' => 'disable_sharelinks',
					'type' => 'int',
					'length' => 1,
					'allow_null' => false,
					'default' => 0
				));
			}

			if( !\IPS\Db::i()->checkForColumn( 'forums', 'deleted_posts' ) )
			{
				\IPS\Db::i()->addColumn('forums', array(
					'name' => 'deleted_posts',
					'type' => 'int',
					'length' => 10,
					'allow_null' => false,
					'default' => 0
				));
			}

			if( !\IPS\Db::i()->checkForColumn( 'forums', 'deleted_topics' ) )
			{
				\IPS\Db::i()->addColumn('forums', array(
					'name' => 'deleted_topics',
					'type' => 'int',
					'length' => 10,
					'allow_null' => false,
					'default' => 0
				));
			}

			if( !\IPS\Db::i()->checkForColumn( 'forums', 'rules_raw_html' ) )
			{
				\IPS\Db::i()->addColumn('forums', array(
					'name' => 'rules_raw_html',
					'type' => 'tinyint',
					'length' => 1,
					'allow_null' => false,
					'default' => 0
				));
			}
	
			\IPS\Db::i()->update( 'rc_classes', array( 'app' => 'core' ), array( 'my_class=?', 'default' ) );
			\IPS\Db::i()->update( 'rc_classes', array( 'app' => 'forums' ), array( 'my_class=?', 'post' ) );
			\IPS\Db::i()->update( 'rc_classes', array( 'app' => 'blog' ), array( 'my_class=?', 'blog' ) );
			\IPS\Db::i()->update( 'rc_classes', array( 'app' => 'gallery' ), array( 'my_class=?', 'gallery' ) );
			\IPS\Db::i()->update( 'rc_classes', array( 'app' => 'downloads' ), array( 'my_class=?', 'downloads' ) );
			\IPS\Db::i()->update( 'rc_classes', array( 'app' => 'members' ), array( 'my_class=?', 'messages' ) );
			\IPS\Db::i()->update( 'rc_classes', array( 'app' => 'members' ), array( 'my_class=?', 'profiles' ) );
			\IPS\Db::i()->update( 'rc_classes', array( 'app' => 'calendar' ), array( 'my_class=?', 'calendar' ) );
	
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "core_share_links VALUES(1, 'Twitter', 'http://twitter.com/home?status={title}%20{url}', 'twitter', 1, 1, '',1)" );
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "core_share_links VALUES(2, 'Facebook', 'http://www.facebook.com/share.php?u={url}', 'facebook', 1, 2, '',1)" );
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "core_share_links VALUES(3, 'Digg', 'http://digg.com/submit?phase=2&amp;url={url}&amp;title={title}', 'digg', 1, 3, '',1)" );
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "core_share_links VALUES(4, 'Del.icio.us', 'http://del.icio.us/post?v=2&amp;url={url}&amp;title={title}', 'delicious', 1, 4, '',1)" );
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "core_share_links VALUES(5, 'Reddit', 'http://reddit.com/submit?url={url}&amp;title={title}', 'reddit', 1, 5, '',1)" );
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "core_share_links VALUES(6, 'StumbleUpon', 'http://www.stumbleupon.com/submit?url={url}&title={title}', 'stumble', 1, 6, '',1)" );
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "core_share_links VALUES(8, 'Email', '', 'email', 1, 7, '',1)" );
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "core_share_links VALUES(9, 'Buzz', '', 'buzz', 1, 3, '', 1)" );
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "core_share_links VALUES(10, 'Print', '', 'print', 1, 10, '', 0)" );
			\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "core_share_links VALUES(11, 'Download', '', 'download', 1, 11, '', 0)" );
	
			/* @note Here we previously inserted a master+lofi skin if it didn't exist and ensured a skin was set as default, but this doesn't matter now */
	
			if( !\IPS\Db::i()->checkForColumn( 'custom_bbcode', 'bbcode_custom_regex' ) )
			{
				\IPS\Db::i()->addColumn( 'custom_bbcode', array(
					'name'			=> 'bbcode_custom_regex',
					'type'			=> 'text',
					'length'		=> null,
					'allow_null'	=> true,
					'default'		=> null
				) );
			}

			if( !\IPS\Db::i()->checkForColumn( 'skin_templates', 'template_master_key' ) )
			{
				\IPS\Db::i()->addColumn('skin_templates', array(
					'name' => 'template_master_key',
					'type' => 'varchar',
					'length' => 100,
					'allow_null' => false,
					'default' => ''
				));
			}

			\IPS\Db::i()->dropIndex( 'skin_templates', 'template_master_key' );
			\IPS\Db::i()->addIndex( 'skin_templates', array(
				'type'			=> 'key',
				'name'			=> 'template_master_key',
				'columns'		=> array( 'template_master_key' )
			) );

			\IPS\Db::i()->dropIndex( 'skin_css', 'css_master_key' );
			\IPS\Db::i()->addColumn( 'skin_css', array(
				'name'			=> 'css_master_key',
				'type'			=> 'varchar',
				'length'		=> 100,
				'allow_null'	=> false,
				'default'		=> ''
			) );

			\IPS\Db::i()->dropIndex( 'skin_collections', 'set_master_key' );
			\IPS\Db::i()->addColumn( 'skin_collections', array(
				'name'			=> 'set_master_key',
				'type'			=> 'varchar',
				'length'		=> 100,
				'allow_null'	=> false,
				'default'		=> ''
			) );

			/* @note Here we previously performed some further updates on skins that are no longer relevant */
			if( !\IPS\Db::i()->checkForColumn( 'skin_collections', 'set_order' ) )
			{
				\IPS\Db::i()->addColumn('skin_collections', array(
					'name' => 'set_order',
					'type' => 'int',
					'length' => 10,
					'allow_null' => false,
					'default' => 0
				));
			}

			\IPS\Db::i()->dropIndex( 'skin_collections', 'set_order' );
			\IPS\Db::i()->addIndex( 'skin_collections', array(
				'type'			=> 'key',
				'name'			=> 'set_order',
				'columns'		=> array( 'set_order' )
			) );

			if( !\IPS\Db::i()->checkForColumn( 'skin_replacements', 'replacement_master_key' ) )
			{
				\IPS\Db::i()->addColumn('skin_replacements', array(
					'name' => 'replacement_master_key',
					'type' => 'varchar',
					'length' => 100,
					'allow_null' => false,
					'default' => ''
				));
			}
	
			\IPS\Db::i()->update( 'skin_replacements', array( 'replacement_master_key' => 'root' ), array( 'replacement_set_id=?', 0 ) );

			\IPS\Db::i()->dropIndex( 'skin_templates', 'template_name' );
			\IPS\Db::i()->addIndex( 'skin_templates', array(
				'type'			=> 'key',
				'name'			=> 'template_name',
				'columns'		=> array( 1 => 'template_name', 2 => 'template_group' ),
				'length'		=> array( 1 => 100, 2 => 100 )
			) );
		}
		
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'members',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members CHANGE COLUMN language language MEDIUMINT(4) NULL DEFAULT NULL,
				ADD INDEX failed_login_count (failed_login_count),
				ADD INDEX joined (joined),
				ADD COLUMN twitter_id VARCHAR(255) NOT NULL DEFAULT '',
				ADD COLUMN twitter_token VARCHAR(255) NOT NULL DEFAULT '',
				ADD COLUMN twitter_secret VARCHAR(255) NOT NULL DEFAULT '',
				ADD COLUMN notification_cnt MEDIUMINT(8) NOT NULL DEFAULT 0,
				ADD COLUMN tc_lastsync INT(10) NOT NULL DEFAULT 0,
				ADD COLUMN fb_session VARCHAR(255) NOT NULL DEFAULT '',
				DROP COLUMN fb_emailallow,
				ADD COLUMN fb_token TEXT NULL DEFAULT NULL,
				ADD INDEX fb_uid (fb_uid),
				ADD INDEX twitter_id (twitter_id(200)),
				ADD INDEX email (email);"
		),
		array(
			'table' => 'reputation_index',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "reputation_index ADD INDEX member_rep (member_id, rep_rating, rep_date);"
		),
		array(
			'table' => 'profile_portal',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "profile_portal ADD COLUMN tc_last_sid_import BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				ADD COLUMN tc_photo TEXT NULL DEFAULT NULL,
				ADD COLUMN tc_bwoptions INT(10) UNSIGNED NOT NULL DEFAULT 0,
				ADD COLUMN pp_customization MEDIUMTEXT NULL DEFAULT NULL;"
		),
		array(
			'table' => 'topics',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics ADD COLUMN topic_deleted_posts INT(10) NOT NULL DEFAULT 0;"
		),
		array(
			'table' => 'forum_tracker',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forum_tracker DROP INDEX member_id,
				ADD INDEX member_id (member_id, last_sent),
				ADD INDEX forum_track_type (forum_track_type);"
		),
		array(
			'table' => 'tracker',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "tracker DROP INDEX tm_id,
				ADD INDEX tm_id (member_id, topic_id, last_sent),
				ADD INDEX topic_track_type (topic_track_type);"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 2 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		/* Finish */
		return TRUE;
	}
}