<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_30001;

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
		\IPS\Db::i()->dropTable( 'sessions' );

		\IPS\Db::i()->createTable( array(
			'name'		=> 'sessions',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'varchar',
					'length'		=> 60,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'member_name',
					'type'			=> 'varchar',
					'length'		=> 64,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'seo_name',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'member_id',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'ip_address',
					'type'			=> 'varchar',
					'length'		=> 16,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'browser',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'running_time',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'login_type',
					'type'			=> 'char',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'location',
					'type'			=> 'varchar',
					'length'		=> 40,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'member_group',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'in_error',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'location_1_type',
					'type'			=> 'varchar',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'location_1_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'location_2_type',
					'type'			=> 'varchar',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'location_2_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'location_3_type',
					'type'			=> 'varchar',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'location_3_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'current_appcomponent',
					'type'			=> 'varchar',
					'length'		=> 100,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'current_module',
					'type'			=> 'varchar',
					'length'		=> 100,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'current_section',
					'type'			=> 'varchar',
					'length'		=> 100,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'uagent_key',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'uagent_version',
					'type'			=> 'varchar',
					'length'		=> 100,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'uagent_type',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'uagent_bypass',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'search_thread_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'search_thread_time',
					'type'			=> 'varchar',
					'length'		=> 13,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'location1',
					'columns'	=> array( 'location_1_type', 'location_1_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'location2',
					'columns'	=> array( 'location_2_type', 'location_2_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'location3',
					'columns'	=> array( 'location_3_type', 'location_3_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'running_time',
					'columns'	=> array( 'running_time' )
				)
			)
		)	);

		\IPS\Db::i()->dropTable( 'custom_bbcode' );

		\IPS\Db::i()->createTable( array(
			'name'		=> 'custom_bbcode',
			'columns'	=> array(
				array(
					'name'			=> 'bbcode_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'bbcode_title',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'bbcode_desc',
					'type'			=> 'text',
					'length'		=> null,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'bbcode_tag',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'bbcode_replace',
					'type'			=> 'text',
					'length'		=> null,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'bbcode_useoption',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'bbcode_example',
					'type'			=> 'text',
					'length'		=> null,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'bbcode_switch_option',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'bbcode_menu_option_text',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'bbcode_menu_content_text',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'bbcode_single_tag',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'bbcode_groups',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'bbcode_sections',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'bbcode_php_plugin',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'bbcode_parse',
					'type'			=> 'smallint',
					'length'		=> 2,
					'allow_null'	=> false,
					'default'		=> 1
				),
				array(
					'name'			=> 'bbcode_no_parsing',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'bbcode_protected',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'bbcode_aliases',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'bbcode_optional_option',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'bbcode_image',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'bbcode_strip_search',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'bbcode_app',
					'type'			=> 'varchar',
					'length'		=> 50,
					'allow_null'	=> false,
					'default'		=> ''
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'bbcode_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'profile_friends_flood',
			'columns'	=> array(
				array(
					'name'			=> 'friends_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'friends_member_id',
					'type'			=> 'int',
					'unsigned'		=> true,
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'friends_friend_id',
					'type'			=> 'int',
					'unsigned'		=> true,
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'friends_removed',
					'type'			=> 'int',
					'unsigned'		=> true,
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'friends_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'my_friends',
					'columns'	=> array( 'friends_member_id', 'friends_friend_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'friends_member_id',
					'columns'	=> array( 'friends_member_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_sys_cp_sessions',
			'columns'	=> array(
				array(
					'name'			=> 'session_id',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'session_ip_address',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'session_member_name',
					'type'			=> 'varchar',
					'length'		=> 250,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'session_member_id',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'session_member_login_key',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'session_location',
					'type'			=> 'varchar',
					'length'		=> 64,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'session_log_in_time',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'session_running_time',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'session_url',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'session_app_data',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'session_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_sys_settings_titles',
			'columns'	=> array(
				array(
					'name'			=> 'conf_title_id',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'conf_title_title',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'conf_title_desc',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'conf_title_count',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'conf_title_noshow',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'conf_title_keyword',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'conf_title_module',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'conf_title_app',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'conf_title_tab',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'conf_title_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_sys_conf_settings',
			'columns'	=> array(
				array(
					'name'			=> 'conf_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'conf_title',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'conf_description',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'conf_group',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'conf_type',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'conf_key',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'conf_value',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'conf_default',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'conf_extra',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'conf_evalphp',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'conf_protected',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'conf_position',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'conf_start_group',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'conf_end_group',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'conf_add_cache',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 1
				),
				array(
					'name'			=> 'conf_keywords',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'conf_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_item_markers',
			'columns'	=> array(
				array(
					'name'			=> 'item_key',
					'type'			=> 'char',
					'length'		=> 32,
					'allow_null'	=> false,
				),
				array(
					'name'			=> 'item_member_id',
					'type'			=> 'int',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'item_app',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> 'core'
				),
				array(
					'name'			=> 'item_last_update',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'item_last_saved',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'item_unread_count',
					'type'			=> 'int',
					'length'		=> 5,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'item_read_array',
					'type'			=> 'mediumtext',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'item_global_reset',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'item_app_key_1',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'item_app_key_2',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'item_app_key_3',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'unique',
					'name'		=> 'combo_key',
					'columns'	=> array( 'item_key', 'item_member_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'marker_index',
					'columns'	=> array( 'item_member_id', 'item_app' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'item_member_id',
					'columns'	=> array( 'item_member_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_item_markers_storage',
			'columns'	=> array(
				array(
					'name'			=> 'item_member_id',
					'type'			=> 'int',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'item_markers',
					'type'			=> 'mediumtext',
					'length'		=> null,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'item_last_updated',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'item_last_saved',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'item_member_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'template_sandr',
			'columns'	=> array(
				array(
					'name'			=> 'sandr_session_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'sandr_set_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'sandr_search_only',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'sandr_search_all',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'sandr_search_for',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'sandr_replace_with',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'sandr_is_regex',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'sandr_template_count',
					'type'			=> 'int',
					'length'		=> 5,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'sandr_template_processed',
					'type'			=> 'int',
					'length'		=> 5,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'sandr_results',
					'type'			=> 'mediumtext',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'sandr_updated',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'sandr_session_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'question_and_answer',
			'columns'	=> array(
				array(
					'name'			=> 'qa_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'qa_question',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'qa_answers',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'qa_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'mod_queued_items',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'type',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> 'post'
				),
				array(
					'name'			=> 'type_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'type_id',
					'columns'	=> array( 'type_id' )
				),
			)
		)	);

		\IPS\Db::i()->addIndex( 'message_topics', array(
			'type'			=> 'key',
			'name'			=> 'mt_msg_id',
			'columns'		=> array( 'mt_msg_id' )
		) );

		\IPS\Db::i()->renameTable( 'message_topics', 'message_topics_old' );

		\IPS\Db::i()->createTable( array(
			'name'		=> 'message_topics',
			'columns'	=> array(
				array(
					'name'			=> 'mt_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'mt_date',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_title',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'mt_hasattach',
					'type'			=> 'smallint',
					'length'		=> 5,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_starter_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_start_time',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_last_post_time',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_invited_members',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'mt_to_count',
					'type'			=> 'int',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_to_member_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_replies',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_last_msg_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_first_msg_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_is_draft',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_is_deleted',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'mt_is_system',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'mt_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'mt_starter_id',
					'columns'	=> array( 'mt_starter_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'message_topic_user_map',
			'columns'	=> array(
				array(
					'name'			=> 'map_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'map_user_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'map_topic_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'map_folder_id',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'map_read_time',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'map_user_active',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'map_user_banned',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'map_has_unread',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'map_is_system',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'map_is_starter',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'map_left_time',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'map_ignore_notification',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'map_last_topic_reply',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'map_id' )
				),
				array(
					'type'		=> 'unique',
					'name'		=> 'map_main',
					'columns'	=> array( 'map_user_id', 'map_topic_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'map_user',
					'columns'	=> array( 'map_user_id', 'map_folder_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'map_topic_id',
					'columns'	=> array( 'map_topic_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'message_posts',
			'columns'	=> array(
				array(
					'name'			=> 'msg_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'msg_topic_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'msg_date',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'msg_post',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'msg_post_key',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'msg_author_id',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'msg_ip_address',
					'type'			=> 'varchar',
					'length'		=> 16,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'msg_is_first_post',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'msg_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'msg_topic_id',
					'columns'	=> array( 'msg_topic_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'msg_date',
					'columns'	=> array( 'msg_date' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'error_logs',
			'columns'	=> array(
				array(
					'name'			=> 'log_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'log_member',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'log_date',
					'type'			=> 'varchar',
					'length'		=> 13,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'log_error',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'log_error_code',
					'type'			=> 'varchar',
					'length'		=> 24,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'log_ip_address',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'log_request_uri',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'log_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'log_date',
					'columns'	=> array( 'log_date' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'pfields_groups',
			'columns'	=> array(
				array(
					'name'			=> 'pf_group_id',
					'type'			=> 'mediumint',
					'length'		=> 4,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'pf_group_name',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'pf_group_key',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'pf_group_id' )
				)
			)
		)	);

		\IPS\Db::i()->dropTable( 'rc_classes', TRUE );
		\IPS\Db::i()->dropTable( 'rc_comments', TRUE );
		\IPS\Db::i()->dropTable( 'rc_modpref', TRUE );
		\IPS\Db::i()->dropTable( 'rc_reports', TRUE );
		\IPS\Db::i()->dropTable( 'rc_reports_index', TRUE );
		\IPS\Db::i()->dropTable( 'rc_status', TRUE );
		\IPS\Db::i()->dropTable( 'rc_status_sev', TRUE );

		\IPS\Db::i()->createTable( array(
			'name'		=> 'rc_classes',
			'columns'	=> array(
				array(
					'name'			=> 'com_id',
					'type'			=> 'smallint',
					'length'		=> 4,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'onoff',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'class_title',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'class_desc',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'author',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'author_url',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'pversion',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'my_class',
					'type'			=> 'varchar',
					'length'		=> 100,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'group_can_report',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'mod_group_perm',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'extra_data',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'lockd',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'com_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'rc_comments',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'rid',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'comment',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'comment_by',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'comment_date',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'rc_modpref',
			'columns'	=> array(
				array(
					'name'			=> 'mem_id',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'by_pm',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'by_email',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'by_alert',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'rss_key',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'max_points',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'reports_pp',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'rss_cache',
					'type'			=> 'mediumtext',
					'allow_null'	=> false
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'mem_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'rc_reports',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'rid',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'report',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'report_by',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'date_reported',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'rc_reports_index',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'uid',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'title',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'status',
					'type'			=> 'smallint',
					'length'		=> 2,
					'allow_null'	=> false,
					'default'		=> 1
				),
				array(
					'name'			=> 'url',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'img_preview',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'rc_class',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'updated_by',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'date_updated',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'date_created',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'exdat1',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'exdat2',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'exdat3',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'num_reports',
					'type'			=> 'smallint',
					'length'		=> 4,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'num_comments',
					'type'			=> 'smallint',
					'length'		=> 4,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'seoname',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'seotemplate',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'uid',
					'columns'	=> array( 'uid' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'rc_status',
			'columns'	=> array(
				array(
					'name'			=> 'status',
					'type'			=> 'tinyint',
					'length'		=> 2,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'title',
					'type'			=> 'varchar',
					'length'		=> 100,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'points_per_report',
					'type'			=> 'smallint',
					'length'		=> 4,
					'allow_null'	=> false,
					'default'		=> 1
				),
				array(
					'name'			=> 'minutes_to_apoint',
					'type'			=> 'double',
					'allow_null'	=> false,
					'default'		=> 5
				),
				array(
					'name'			=> 'is_new',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'is_complete',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'is_active',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'rorder',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'status' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'rc_status_sev',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'smallint',
					'length'		=> 4,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'status',
					'type'			=> 'tinyint',
					'length'		=> 2,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'points',
					'type'			=> 'smallint',
					'length'		=> 4,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'img',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'is_png',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'width',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> 16
				),
				array(
					'name'			=> 'height',
					'type'			=> 'smallint',
					'length'		=> 3,
					'allow_null'	=> false,
					'default'		=> 16
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'status',
					'columns'	=> array( 'status', 'points' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'reputation_levels',
			'columns'	=> array(
				array(
					'name'			=> 'level_id',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'level_points',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'level_title',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'level_image',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'level_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'reputation_cache',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'bigint',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'app',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'type',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'type_id',
					'type'			=> 'int',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'rep_points',
					'type'			=> 'int',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'level_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'app',
					'columns'	=> array( 'app', 'type', 'type_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'reputation_index',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'bigint',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'member_id',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'unsigned'		=> true,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'app',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'type',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'type_id',
					'type'			=> 'int',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'misc',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'rep_date',
					'type'			=> 'int',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'rep_msg',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'rep_rating',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'app',
					'columns'	=> array( 'app', 'type', 'type_id', 'member_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_hooks',
			'columns'	=> array(
				array(
					'name'			=> 'hook_id',
					'type'			=> 'mediumint',
					'length'		=> 4,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'hook_enabled',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'hook_name',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null,
				),
				array(
					'name'			=> 'hook_desc',
					'type'			=> 'text',
					'allow_null'	=> true
				),
				array(
					'name'			=> 'hook_author',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null,
				),
				array(
					'name'			=> 'hook_email',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null,
				),
				array(
					'name'			=> 'hook_website',
					'type'			=> 'text',
					'allow_null'	=> true
				),
				array(
					'name'			=> 'hook_update_check',
					'type'			=> 'text',
					'allow_null'	=> true
				),
				array(
					'name'			=> 'hook_requirements',
					'type'			=> 'text',
					'allow_null'	=> true
				),
				array(
					'name'			=> 'hook_version_human',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'hook_version_long',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'hook_installed',
					'type'			=> 'varchar',
					'length'		=> 13,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'hook_updated',
					'type'			=> 'varchar',
					'length'		=> 13,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'hook_position',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'hook_extra_data',
					'type'			=> 'text',
					'allow_null'	=> true
				),
				array(
					'name'			=> 'hook_key',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> true,
					'default'		=> null
				)
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'hook_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_hooks_files',
			'columns'	=> array(
				array(
					'name'			=> 'hook_file_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'hook_hook_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'hook_file_stored',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null,
				),
				array(
					'name'			=> 'hook_file_real',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null,
				),
				array(
					'name'			=> 'hook_type',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> true,
					'default'		=> null,
				),
				array(
					'name'			=> 'hook_classname',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null,
				),
				array(
					'name'			=> 'hook_data',
					'type'			=> 'text',
					'allow_null'	=> true
				),
				array(
					'name'			=> 'hooks_source',
					'type'			=> 'longtext',
					'allow_null'	=> true
				)
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'hook_file_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'hook_hook_id',
					'columns'	=> array( 'hook_hook_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'tags_index',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'bigint',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'app',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'tag',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'type',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'type_id',
					'type'			=> 'bigint',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'type_2',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'type_id_2',
					'type'			=> 'bigint',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'updated',
					'type'			=> 'int',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'misc',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'member_id',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'unsigned'		=> true,
					'allow_null'	=> false
				)
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'app',
					'columns'	=> array( 'app' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_uagents',
			'columns'	=> array(
				array(
					'name'			=> 'uagent_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'uagent_key',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'uagent_name',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'uagent_regex',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'uagent_regex_capture',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0,
				),
				array(
					'name'			=> 'uagent_type',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'uagent_position',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				)
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'uagent_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'uagent_key',
					'columns'	=> array( 'uagent_key' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_uagent_groups',
			'columns'	=> array(
				array(
					'name'			=> 'ugroup_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'ugroup_title',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'ugroup_array',
					'type'			=> 'mediumtext',
					'allow_null'	=> false
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'ugroup_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'skin_replacements',
			'columns'	=> array(
				array(
					'name'			=> 'replacement_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'replacement_key',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'replacement_content',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'replacement_set_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'replacement_added_to',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'replacement_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'replacement_set_id',
					'columns'	=> array( 'replacement_set_id' )
				),
			)
		)	);

		\IPS\Db::i()->dropTable( 'skin_templates' );

		\IPS\Db::i()->createTable( array(
			'name'		=> 'skin_templates',
			'columns'	=> array(
				array(
					'name'			=> 'template_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'template_set_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'template_group',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'template_content',
					'type'			=> 'mediumtext',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'template_name',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'template_data',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'template_updated',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'template_removable',
					'type'			=> 'int',
					'length'		=> 4,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'template_added_to',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'template_user_added',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'template_user_edited',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),				
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'template_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'skin_collections',
			'columns'	=> array(
				array(
					'name'			=> 'set_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'set_name',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'set_key',
					'type'			=> 'varchar',
					'length'		=> 100,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'set_parent_id',
					'type'			=> 'int',
					'length'		=> 5,
					'allow_null'	=> false,
					'default'		=> -1
				),
				array(
					'name'			=> 'set_parent_array',
					'type'			=> 'mediumtext',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'set_child_array',
					'type'			=> 'mediumtext',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'set_permissions',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'set_is_default',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'set_author_name',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'set_author_url',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'set_image_dir',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'set_emo_dir',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),				
				array(
					'name'			=> 'set_css_inline',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'set_css_groups',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'set_added',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'set_updated',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'set_output_format',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> 'html'
				),
				array(
					'name'			=> 'set_locked_uagent',
					'type'			=> 'mediumtext',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'set_hide_from_list',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),				
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'set_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'skin_css',
			'columns'	=> array(
				array(
					'name'			=> 'css_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'css_set_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'css_updated',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'css_group',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'css_content',
					'type'			=> 'mediumtext',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'css_position',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'css_added_to',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'css_app',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'css_app_hide',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'css_attributes',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'css_removed',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),		
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'css_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'skin_cache',
			'columns'	=> array(
				array(
					'name'			=> 'cache_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'cache_updated',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'cache_type',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_set_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'cache_key_1',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_value_1',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_key_2',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_value_2',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_key_3',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_value_3',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_key_4',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_value_4',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_key_5',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_value_5',
					'type'			=> 'varchar',
					'length'		=> 200,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'cache_content',
					'type'			=> 'mediumtext',
					'allow_null'	=> false
				),	
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'cache_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'cache_type',
					'columns'	=> array( 'cache_type' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'cache_set_id',
					'columns'	=> array( 'cache_set_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'bbcode_mediatag',
			'columns'	=> array(
				array(
					'name'			=> 'mediatag_id',
					'type'			=> 'smallint',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'mediatag_name',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'mediatag_match',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'mediatag_replace',
					'type'			=> 'text',
					'allow_null'	=> false
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'mediatag_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'ignored_users',
			'columns'	=> array(
				array(
					'name'			=> 'ignore_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'ignore_owner_id',
					'type'			=> 'int',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'ignore_ignore_id',
					'type'			=> 'int',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'ignore_messages',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'ignore_topics',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'ignore_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'ignore_owner_id',
					'columns'	=> array( 'ignore_owner_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'ignore_ignore_id',
					'columns'	=> array( 'ignore_ignore_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'captcha',
			'columns'	=> array(
				array(
					'name'			=> 'captcha_unique_id',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'captcha_string',
					'type'			=> 'varchar',
					'length'		=> 100,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'captcha_ipaddress',
					'type'			=> 'varchar',
					'length'		=> 16,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'captcha_date',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'captcha_unique_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'permission_index',
			'columns'	=> array(
				array(
					'name'			=> 'perm_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'unsigned'		=> true,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'app',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'perm_type',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'perm_type_id',
					'type'			=> 'int',
					'unsigned'		=> true,
					'length'		=> 10,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'perm_view',
					'type'			=> 'text',
					'allow_null'	=> false,
				),
				array(
					'name'			=> 'perm_2',
					'type'			=> 'text',
					'allow_null'	=> false,
				),
				array(
					'name'			=> 'perm_3',
					'type'			=> 'text',
					'allow_null'	=> false,
				),
				array(
					'name'			=> 'perm_4',
					'type'			=> 'text',
					'allow_null'	=> false,
				),
				array(
					'name'			=> 'perm_5',
					'type'			=> 'text',
					'allow_null'	=> false,
				),
				array(
					'name'			=> 'perm_6',
					'type'			=> 'text',
					'allow_null'	=> false,
				),
				array(
					'name'			=> 'perm_7',
					'type'			=> 'text',
					'allow_null'	=> false,
				),
				array(
					'name'			=> 'owner_only',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'friend_only',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'authorized_users',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'perm_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'perm_index',
					'columns'	=> array( 'perm_type', 'perm_type_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'perm_type',
					'columns'	=> array( 'app', 'perm_type', 'perm_type_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'openid_temp',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'referrer',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'privacy',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'cookiedate',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'fullurl',
					'type'			=> 'text',
					'allow_null'	=> false
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_applications',
			'columns'	=> array(
				array(
					'name'			=> 'app_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'app_title',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'app_public_title',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'app_description',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'app_author',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'app_version',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'app_long_version',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 10000
				),
				array(
					'name'			=> 'app_directory',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'app_added',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'app_position',
					'type'			=> 'int',
					'length'		=> 2,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'app_protected',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'app_enabled',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'app_location',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'app_hide_tab',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'app_tab_groups',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'app_website',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'app_update_check',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'app_global_caches',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'app_id' )
				)
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_sys_module',
			'columns'	=> array(
				array(
					'name'			=> 'sys_module_id',
					'type'			=> 'mediumint',
					'length'		=> 4,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'sys_module_title',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'sys_module_application',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'sys_module_key',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'sys_module_description',
					'type'			=> 'varchar',
					'length'		=> 100,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'sys_module_version',
					'type'			=> 'varchar',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'sys_module_protected',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'sys_module_visible',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'default'		=> 1
				),
				array(
					'name'			=> 'sys_module_position',
					'type'			=> 'int',
					'length'		=> 5,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'sys_module_admin',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'sys_module_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'sys_module_application',
					'columns'	=> array( 'sys_module_application' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'sys_module_visible',
					'columns'	=> array( 'sys_module_visible' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'sys_module_key',
					'columns'	=> array( 'sys_module_key' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_sys_lang',
			'columns'	=> array(
				array(
					'name'			=> 'lang_id',
					'type'			=> 'mediumint',
					'length'		=> 4,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'lang_short',
					'type'			=> 'varchar',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'lang_title',
					'type'			=> 'varchar',
					'length'		=> 60,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'lang_currency_name',
					'type'			=> 'varchar',
					'length'		=> 4,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'lang_currency_symbol',
					'type'			=> 'char',
					'length'		=> 2,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'lang_decimal',
					'type'			=> 'char',
					'length'		=> 2,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'lang_comma',
					'type'			=> 'char',
					'length'		=> 2,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'lang_default',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'lang_isrtl',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'lang_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'lang_short',
					'columns'	=> array( 'lang_short' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_sys_lang_words',
			'columns'	=> array(
				array(
					'name'			=> 'word_id',
					'type'			=> 'int',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'lang_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'unsigned'		=> true,
				),
				array(
					'name'			=> 'word_app',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'word_pack',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'word_key',
					'type'			=> 'varchar',
					'length'		=> 64,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'word_default',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'word_custom',
					'type'			=> 'text',
					'allow_null'	=> false
				),
				array(
					'name'			=> 'word_default_version',
					'type'			=> 'varchar',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> '1'
				),
				array(
					'name'			=> 'word_custom_version',
					'type'			=> 'varchar',
					'length'		=> 10,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'word_js',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'unsigned'		=> true,
					'allow_null'	=> false
				)
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'word_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'word_js',
					'columns'	=> array( 'word_js' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'word_find',
					'columns'	=> array( 1=> 'lang_id', 2 => 'word_app', 3 => 'word_pack' ),
					'length'	=> array( 1 => null, 2 => 32, 3 => 100 )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_sys_login',
			'columns'	=> array(
				array(
					'name'			=> 'sys_login_id',
					'type'			=> 'int',
					'length'		=> 8,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'sys_login_skin',
					'type'			=> 'int',
					'length'		=> 5,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'sys_login_language',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'sys_login_last_visit',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'sys_cookie',
					'type'			=> 'mediumtext',
					'allow_null'	=> false,
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'sys_login_id' )
				),
			)
		)	);

		/* @note Previously the upgrader would add fulltext indexes here if appropriate, however we'll just tackle that in the 4.0 upgrade routine now */

		/* Finish */
		return TRUE;
	}

	/**
	 * Step 2
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->delete( "members", "id=0" );

		/* Groups */
		\IPS\Db::i()->update( 'groups', "g_icon=REPLACE(g_icon,'style_images/<#IMG_DIR#>/folder_team_icons/admin.gif','public/style_extra/team_icons/admin.png')" );
		\IPS\Db::i()->update( 'groups', "g_icon=REPLACE(g_icon,'style_images/<#IMG_DIR#>/folder_team_icons/','public/style_extra/team_icons/')" );

		\IPS\Db::i()->addColumn( 'bbcode_mediatag', array(
			'name'			=> 'mediatag_position',
			'type'			=> 'smallint',
			'length'		=> 3,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'custom_bbcode', array(
			'name'			=> 'bbcode_custom_regex',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );

		return TRUE;
	}

	/**
	 * Step 3
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'members',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members CHANGE COLUMN id member_id MEDIUMINT(8) NOT NULL AUTO_INCREMENT,
				CHANGE COLUMN mgroup member_group_id SMALLINT(3) NOT NULL DEFAULT 0,
				ADD COLUMN members_pass_hash VARCHAR(32) NOT NULL DEFAULT '',
				ADD COLUMN members_pass_salt VARCHAR(5) NOT NULL DEFAULT '',
				ADD COLUMN member_banned TINYINT(1) NOT NULL DEFAULT 0,
				ADD INDEX member_banned (member_banned),
				ADD COLUMN identity_url TEXT NULL DEFAULT NULL;"
		),
		array(
			'table' => 'members',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "members SET new_msg=0 WHERE new_msg IS NULL;"
		), 
		array(
			'table' => 'members',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "members SET msg_total=0 WHERE msg_total IS NULL;"
		),
		array(
			'table' => 'members',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "members SET show_popup=0 WHERE show_popup IS NULL;"
		),
		array(
			'table' => 'members',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members CHANGE COLUMN new_msg msg_count_new INT(2) NOT NULL DEFAULT 0,
				CHANGE COLUMN msg_total msg_count_total INT(3) NOT NULL DEFAULT 0,
				ADD COLUMN msg_count_reset INT(1) NOT NULL DEFAULT 0,
				CHANGE COLUMN show_popup msg_show_notification INT(1) NOT NULL DEFAULT 0,
				ADD COLUMN member_uploader VARCHAR(32) NOT NULL DEFAULT 'default',
				DROP COLUMN members_markers,
				ADD COLUMN members_seo_name VARCHAR(255) NOT NULL DEFAULT '',
				ADD COLUMN members_bitoptions INT(10) UNSIGNED NOT NULL DEFAULT 0,
				ADD COLUMN fb_uid INT(10) UNSIGNED NOT NULL DEFAULT 0,
				ADD COLUMN fb_emailhash VARCHAR(60) NOT NULL DEFAULT '',
				ADD COLUMN fb_emailallow INT(1) NOT NULL DEFAULT 0,
				ADD COLUMN fb_lastsync INT(10) NOT NULL DEFAULT 0;"
		),
		array(
			'table' => 'profile_portal',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "profile_portal ADD COLUMN notes TEXT NULL DEFAULT NULL,
				ADD COLUMN links TEXT NULL DEFAULT NULL,
				ADD COLUMN bio TEXT NULL DEFAULT NULL,
				ADD COLUMN ta_size VARCHAR(3) NULL DEFAULT NULL,
				ADD COLUMN signature TEXT NULL DEFAULT NULL,
				ADD COLUMN avatar_location VARCHAR(255) NULL DEFAULT NULL,
				ADD COLUMN avatar_size VARCHAR(9) NOT NULL DEFAULT '0',
				ADD COLUMN avatar_type VARCHAR(15) NULL DEFAULT NULL,
				ADD COLUMN pconversation_filters TEXT NULL DEFAULT NULL,
				ADD COLUMN fb_photo TEXT NULL DEFAULT NULL,
				ADD COLUMN fb_photo_thumb TEXT NULL DEFAULT NULL,
				ADD COLUMN fb_bwoptions INT(1) UNSIGNED NOT NULL DEFAULT 0,
				ADD COLUMN pp_reputation_points INT(10) NOT NULL DEFAULT 0,
				ADD COLUMN pp_status TEXT NULL DEFAULT NULL,
				ADD COLUMN pp_status_update VARCHAR(13) NOT NULL DEFAULT '0';"
		)
		) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 4 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Step 4
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		\IPS\Db::i()->update( 'forums', array( 'skin_id' => 0 ) );

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'topics',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics CHANGE COLUMN id member_id MEDIUMINT(8) NOT NULL AUTO_INCREMENT,
				ADD COLUMN seo_last_name VARCHAR(255) NOT NULL DEFAULT '',
				ADD COLUMN seo_first_name VARCHAR(255) NOT NULL DEFAULT '';"
		),
		array(
			'table' => 'members',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members CHANGE COLUMN description description VARCHAR(250) NULL DEFAULT NULL;"
		),
		array(
			'table' => 'voters',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "voters CHANGE COLUMN member_id member_id INT(10) NOT NULL DEFAULT 0,
				ADD COLUMN member_choices TEXT NULL DEFAULT NULL;"
		),
		array(
			'table' => 'polls',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "polls ADD COLUMN poll_view_voters INT(1) NOT NULL DEFAULT 0;"
		),
		array(
			'table' => 'tracker',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "tracker ADD INDEX tm_id (topic_id, member_id);"
		),
		array(
			'table' => 'forum_tracker',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forum_tracker ADD INDEX fm_id (member_id, forum_id);"
		)
		) );
		
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
		\IPS\Db::i()->dropColumn( 'admin_logs', array( 'act', 'code' ) );

		\IPS\Db::i()->addColumn( 'admin_logs', array(
			'name'			=> 'appcomponent',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> false,
			'default'		=> ''
		) );

		\IPS\Db::i()->addColumn( 'admin_logs', array(
			'name'			=> 'module',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> false,
			'default'		=> ''
		) );

		\IPS\Db::i()->addColumn( 'admin_logs', array(
			'name'			=> 'section',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> false,
			'default'		=> ''
		) );

		\IPS\Db::i()->addColumn( 'admin_logs', array(
			'name'			=> 'do',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> false,
			'default'		=> ''
		) );

		\IPS\Db::i()->addIndex( 'task_logs', array(
			'type'			=> 'key',
			'name'			=> 'log_date',
			'columns'		=> array( 'log_date' )
		) );

		\IPS\Db::i()->addIndex( 'admin_logs', array(
			'type'			=> 'key',
			'name'			=> 'ctime',
			'columns'		=> array( 'ctime' )
		) );

		\IPS\Db::i()->addIndex( 'moderator_logs', array(
			'type'			=> 'key',
			'name'			=> 'ctime',
			'columns'		=> array( 'ctime' )
		) );

		return TRUE;
	}

	/**
	 * Step 6
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
	{
		\IPS\Db::i()->addColumn( 'pfields_data', array(
			'name'			=> 'pf_group_id',
			'type'			=> 'mediumint',
			'length'		=> 4,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'pfields_data', array(
			'name'			=> 'pf_icon',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->addColumn( 'pfields_data', array(
			'name'			=> 'pf_key',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->changeColumn( 'pfields_data', 'pf_input_format', array(
			'name'			=> 'pf_input_format',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->dropIndex( 'moderators', 'forum_id' );

		\IPS\Db::i()->changeColumn( 'moderators', 'forum_id', array(
			'name'			=> 'forum_id',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->addColumn( 'moderators', array(
			'name'			=> 'mod_bitoptions',
			'type'			=> 'int',
			'length'		=> 10,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_rep_max_positive',
			'type'			=> 'mediumint',
			'length'		=> 8,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_rep_max_negative',
			'type'			=> 'mediumint',
			'length'		=> 8,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_mod_preview',
			'type'			=> 'tinyint',
			'length'		=> 1,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_signature_limits',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_can_add_friends',
			'type'			=> 'tinyint',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 1
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_hide_online_list',
			'type'			=> 'tinyint',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_bitoptions',
			'type'			=> 'int',
			'length'		=> 10,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_pm_perday',
			'type'			=> 'smallint',
			'length'		=> 3,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->changeColumn( 'forums', 'id', array(
			'name'			=> 'id',
			'type'			=> 'smallint',
			'length'		=> 5,
			'allow_null'	=> false,
			'auto_increment'	=> true
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'can_view_others',
			'type'			=> 'tinyint',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 1
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'min_posts_post',
			'type'			=> 'int',
			'length'		=> 10,
			'unsigned'		=> true,
			'allow_null'	=> false
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'min_posts_view',
			'type'			=> 'int',
			'length'		=> 10,
			'unsigned'		=> true,
			'allow_null'	=> false
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'hide_last_info',
			'type'			=> 'tinyint',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'name_seo',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'seo_last_title',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> false,
			'default'		=> ''
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'seo_last_name',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> false,
			'default'		=> ''
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'last_x_topic_ids',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'forums_bitoptions',
			'type'			=> 'int',
			'length'		=> 10,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'cache_store', array(
			'name'			=> 'cs_updated',
			'type'			=> 'int',
			'length'		=> 10,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'task_manager', array(
			'name'			=> 'task_application',
			'type'			=> 'varchar',
			'length'		=> 100,
			'allow_null'	=> false
		) );

		\IPS\Db::i()->changeColumn( 'task_manager', 'task_week_day', array(
			'name'			=> 'task_week_day',
			'type'			=> 'smallint',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> -1
		) );

		\IPS\Db::i()->addColumn( 'admin_permission_rows', array(
			'name'			=> 'row_id_type',
			'type'			=> 'varchar',
			'length'		=> 13,
			'allow_null'	=> false,
			'default'		=> 'member'
		) );

		\IPS\Db::i()->changeColumn( 'admin_permission_rows', 'row_member_id', array(
			'name'			=> 'row_id',
			'type'			=> 'int',
			'length'		=> 8,
			'allow_null'	=> false
		) );

		\IPS\Db::i()->dropIndex( 'admin_permission_rows', 'PRIMARY KEY' );

		\IPS\Db::i()->addIndex( 'admin_permission_rows', array(
			'type'			=> 'primary',
			'columns'		=> array( 'row_id', 'row_id_type' )
		) );

		\IPS\Db::i()->addColumn( 'login_methods', array(
			'name'			=> 'login_alt_acp_html',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->addColumn( 'login_methods', array(
			'name'			=> 'login_order',
			'type'			=> 'smallint',
			'length'		=> 3,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->dropColumn( 'login_methods', array( 'login_installed', 'login_type', 'login_allow_create' ) );

		return TRUE;
	}

	/**
	 * Step 7
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		/* Root admin reset? */
		require( \IPS\ROOT_PATH . '/conf_global.php' );

		foreach( \IPS\Db::i()->select( '*', 'groups', 'g_id != ' . $INFO['admin_group'] . ' AND g_access_cp=1' ) as $row )
		{
			/* Insert blank perm row */
			\IPS\Db::i()->insert( 'admin_permission_rows', array( 'row_id' => $row['g_id'],
				'row_id_type'    => 'group',
				'row_perm_cache' => serialize( array() ),
				'row_updated'	=> time() ) );
		}

		\IPS\Db::i()->insert( 'core_sys_lang', array( 'lang_id' => 1, 'lang_short' => 'en_US', 'lang_title' => 'English (USA)', 'lang_currency_name' => 'USD', 'lang_currency_symbol' => '$', 'lang_decimal' => '.', 'lang_comma' => ',', 'lang_default' => 1, 'lang_isrtl' => 0 ) );

		\IPS\Db::i()->insert( 'pfields_groups', array( 'pf_group_id' => 1, 'pf_group_name' => 'Contact Methods', 'pf_group_key' => 'contact' ) );
		\IPS\Db::i()->insert( 'pfields_groups', array( 'pf_group_id' => 1, 'pf_group_name' => 'Profile Information', 'pf_group_key' => 'profile_info' ) );
		\IPS\Db::i()->insert( 'pfields_groups', array( 'pf_group_id' => 1, 'pf_group_name' => 'Previous Fields', 'pf_group_key' => 'previous' ) );

		\IPS\Db::i()->insert( 'pfields_data', array( 'pf_title' => 'AIM', 'pf_type' => 'input', 'pf_not_null' => 0, 'pf_member_hide' => 0, 'pf_max_input' => 0, 'pf_member_edit' => 1, 'pf_position' => 0, 'pf_input_format' => 0, 'pf_group_id' => 1, 'pf_icon' => 'style_extra/cprofile_icons/profile_aim.gif', 'pf_key' => 'aim' ) );
		\IPS\Db::i()->insert( 'pfields_data', array( 'pf_title' => 'MSN', 'pf_type' => 'input', 'pf_not_null' => 0, 'pf_member_hide' => 0, 'pf_max_input' => 0, 'pf_member_edit' => 1, 'pf_position' => 0, 'pf_input_format' => 0, 'pf_group_id' => 1, 'pf_icon' => 'style_extra/cprofile_icons/profile_msn.gif', 'pf_key' => 'msn' ) );
		\IPS\Db::i()->insert( 'pfields_data', array( 'pf_title' => 'Website URL', 'pf_type' => 'input', 'pf_not_null' => 0, 'pf_member_hide' => 0, 'pf_max_input' => 0, 'pf_member_edit' => 1, 'pf_position' => 0, 'pf_input_format' => 0, 'pf_group_id' => 1, 'pf_icon' => 'style_extra/cprofile_icons/profile_website.gif', 'pf_key' => 'website' ) );
		\IPS\Db::i()->insert( 'pfields_data', array( 'pf_title' => 'ICQ', 'pf_type' => 'input', 'pf_not_null' => 0, 'pf_member_hide' => 0, 'pf_max_input' => 0, 'pf_member_edit' => 1, 'pf_position' => 0, 'pf_input_format' => 0, 'pf_group_id' => 1, 'pf_icon' => 'style_extra/cprofile_icons/profile_icq.gif', 'pf_key' => 'icq' ) );
		\IPS\Db::i()->insert( 'pfields_data', array( 'pf_title' => 'Gender', 'pf_content' => 'm=Male|f=Female|u=Not Telling', 'pf_type' => 'drop', 'pf_not_null' => 0, 'pf_member_hide' => 0, 'pf_max_input' => 0, 'pf_member_edit' => 1, 'pf_position' => 0, 'pf_input_format' => 0, 'pf_group_id' => 1, 'pf_key' => 'gender', 'pf_topic_format' => '<dt>{title}:</dt><dd>{content}</dd>' ) );
		\IPS\Db::i()->insert( 'pfields_data', array( 'pf_title' => 'Location', 'pf_type' => 'input', 'pf_not_null' => 0, 'pf_member_hide' => 0, 'pf_max_input' => 0, 'pf_member_edit' => 1, 'pf_position' => 0, 'pf_input_format' => 0, 'pf_group_id' => 1, 'pf_key' => 'location', 'pf_topic_format' => '<dt>{title}:</dt><dd>{content}</dd>' ) );
		\IPS\Db::i()->insert( 'pfields_data', array( 'pf_title' => 'Interests', 'pf_type' => 'textarea', 'pf_not_null' => 0, 'pf_member_hide' => 0, 'pf_max_input' => 0, 'pf_member_edit' => 1, 'pf_position' => 0, 'pf_input_format' => 0, 'pf_group_id' => 1, 'pf_key' => 'interests', 'pf_topic_format' => '<dt>{title}:</dt><dd>{content}</dd>' ) );
		\IPS\Db::i()->insert( 'pfields_data', array( 'pf_title' => 'Yahoo', 'pf_type' => 'input', 'pf_not_null' => 0, 'pf_member_hide' => 0, 'pf_max_input' => 0, 'pf_member_edit' => 1, 'pf_position' => 0, 'pf_input_format' => 0, 'pf_group_id' => 1, 'pf_icon' => 'style_extra/cprofile_icons/profile_yahoo.gif', 'pf_key' => 'yahoo' ) );
		\IPS\Db::i()->insert( 'pfields_data', array( 'pf_title' => 'Jabber', 'pf_type' => 'input', 'pf_not_null' => 0, 'pf_member_hide' => 0, 'pf_max_input' => 0, 'pf_member_edit' => 1, 'pf_position' => 0, 'pf_input_format' => 0, 'pf_group_id' => 1, 'pf_icon' => 'style_extra/cprofile_icons/profile_jabber.gif', 'pf_key' => 'jabber' ) );
		\IPS\Db::i()->insert( 'pfields_data', array( 'pf_title' => 'Skype', 'pf_type' => 'input', 'pf_not_null' => 0, 'pf_member_hide' => 0, 'pf_max_input' => 0, 'pf_member_edit' => 1, 'pf_position' => 0, 'pf_input_format' => 0, 'pf_group_id' => 1, 'pf_icon' => 'style_extra/cprofile_icons/profile_skype.gif', 'pf_key' => 'skype' ) );

		\IPS\Db::i()->update( 'attachments_type', "atype_img=replace(atype_img,'folder_mime_types','style_extra/mime_types')" );

		\IPS\Db::i()->update( 'login_methods', array( 'login_alt_acp_html' => "<label for=''openid''>Open ID</label> <input type=''text'' size=''20'' id=''openid'' name=''openid_url'' value=''http://''>" ), array( 'login_folder_name=?', 'openid' ) );
		\IPS\Db::i()->update( 'login_methods', array( 'login_enabled' => 1, 'login_order' => 7 ), array( 'login_folder_name=?', 'internal' ) );

		\IPS\Db::i()->delete( 'skin_url_mapping' );
		\IPS\Db::i()->delete( 'task_manager' );

		\IPS\Db::i()->delete( 'cache_store', "cs_key='skin_id_cache'" );
		\IPS\Db::i()->delete( 'cache_store', "cs_key='forum_cache'" );

		\IPS\Db::i()->insert( 'reputation_levels', array( 'level_id' => 1, 'level_points' => '-20', 'level_title' => 'Bad' ) );
		\IPS\Db::i()->insert( 'reputation_levels', array( 'level_id' => 2, 'level_points' => '-10', 'level_title' => 'Poor' ) );
		\IPS\Db::i()->insert( 'reputation_levels', array( 'level_id' => 3, 'level_points' => '0', 'level_title' => 'Neutral' ) );
		\IPS\Db::i()->insert( 'reputation_levels', array( 'level_id' => 4, 'level_points' => '10', 'level_title' => 'Good' ) );
		\IPS\Db::i()->insert( 'reputation_levels', array( 'level_id' => 5, 'level_points' => '20', 'level_title' => 'Excellent' ) );

		return TRUE;
	}

	/**
	 * Step 8
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step8()
	{
		if( !\IPS\Db::i()->select( 'count(*)', 'profile_portal' )->first() )
		{
			$queryType	= 1;
		}
		else
		{
			$queryType	= 2;
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'members',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "members m, " . \IPS\Db::i()->prefix . "members_converge c SET m.members_pass_hash=c.converge_pass_hash WHERE c.converge_id=m.member_id;"
		),
		array(
			'table' => 'members',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "members m, " . \IPS\Db::i()->prefix . "members_converge c SET m.members_pass_salt=c.converge_pass_salt WHERE c.converge_id=m.member_id;"
		),
		array(
			'table' => 'members',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "members SET email=CONCAT( member_id, '-', UNIX_TIMESTAMP(), '@fakeemail.com' ) WHERE email='';"
		),
		array(
			'table' => 'profile_portal',
			'query' => $queryType == 1 ?
				"INSERT INTO " . \IPS\Db::i()->prefix . "profile_portal (pp_member_id,notes,links,bio,ta_size,signature,avatar_location,avatar_size,avatar_type) SELECT id,notes,links,bio,ta_size,signature,avatar_location,avatar_size,avatar_type FROM " . \IPS\Db::i()->prefix . "member_extra;" :
				"UPDATE " . \IPS\Db::i()->prefix . "profile_portal p, " . \IPS\Db::i()->prefix . "member_extra e SET p.notes=e.notes, p.links=e.links, p.bio=e.bio, p.ta_size=e.ta_size, p.signature=e.signature, p.avatar_location=e.avatar_location, p.avatar_size=e.avatar_size, p.avatar_type=e.avatar_type WHERE p.pp_member_id=e.id;"
		),
		array(
			'table' => 'profile_portal',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "profile_portal SET pp_setting_count_friends=5 WHERE pp_setting_count_friends=0;"
		),
		array(
			'table' => 'profile_portal',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "profile_portal SET pp_setting_count_comments=10 WHERE pp_setting_count_comments=0;"
		),
		array(
			'table' => 'profile_portal',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "profile_portal SET pp_setting_count_visitors=5 WHERE pp_setting_count_visitors=0;"
		)
		) );
		
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
		$apps	= array();

		foreach( new \DirectoryIterator( \IPS\ROOT_PATH . '/applications' ) as $location )
		{
			if( $location->isDir() AND !$location->isDot() )
			{
				$information	= $location->getPathname() . '/data/application.json';

				if( file_exists( $information ) )
				{
					$information	= json_decode( file_get_contents( $information ), TRUE );

					$apps[ $location->getFilename() ] = $information;
				}
			}
		}

		foreach( \IPS\Db::i()->select( '*', 'components' ) as $component )
		{
			switch( $component['com_section'] )
			{
				case 'gallery':
					if( \IPS\Db::i()->checkForTable( 'gallery_upgrade_history' ) )
					{
						$version	= \IPS\Db::i()->select( '*', 'gallery_upgrade_history', NULL, 'gallery_version_id DESC', array( 0, 1 ) )->first();

						$apps['gallery']['_currentLong']	= $version['gallery_version_id'];
						$apps['gallery']['_currentHuman']	= $version['gallery_version_human'];
					}
				break;

				case 'blog':
					if( \IPS\Db::i()->checkForTable( 'blog_upgrade_history' ) )
					{
						$version	= \IPS\Db::i()->select( '*', 'blog_upgrade_history', NULL, 'blog_version_id DESC', array( 0, 1 ) )->first();

						$apps['blog']['_currentLong']	= $version['blog_version_id'];
						$apps['blog']['_currentHuman']	= $version['blog_version_human'];
					}
				break;

				case 'downloads':
					if( \IPS\Db::i()->checkForTable( 'downloads_upgrade_history' ) )
					{
						$version	= \IPS\Db::i()->select( '*', 'downloads_upgrade_history', NULL, 'idm_version_id DESC', array( 0, 1 ) )->first();

						$apps['downloads']['_currentLong']	= $version['idm_version_id'];
						$apps['downloads']['_currentHuman']	= $version['idm_version_human'];
					}
				break;
			}
		}

		$apps['forums']['_currentLong']  = '30001';
		$apps['forums']['_currentHuman'] = '3.0.0';
		
		$apps['core']['_currentLong']  = '30001';
		$apps['core']['_currentHuman'] = '3.0.0';
		
		$apps['members']['_currentLong']  = '30001';
		$apps['members']['_currentHuman'] = '3.0.0';
		
		$apps['portal']['_currentLong']  = '30003';
		$apps['portal']['_currentHuman'] = '3.0.0';

		/* If we are upgrading from 2.3 and did not upload the calendar files, we still need to insert
			the applications table entry, otherwise you won't be able to upgrade down the road.  We will
			insert it as disabled, however, as calendar won't function since files are not available */
		if( !isset($apps['calendar']['name']) )
		{
			$apps['calendar']	= array(
												'application_title'		=> 'Calendar',
												'app_author'			=> "Invision Power Services, Inc.",
												'app_directory'			=> 'calendar',
												);
		}

		$apps['calendar']['_currentLong']  = '30003';
		$apps['calendar']['_currentHuman'] = '3.0.0';

		/* Now install them.. */
		$num = 0;
		
		foreach( $apps as $dir => $appData )
		{
			//-----------------------------------------
			// Had Gallery (e.g.) but didn't upload updated files
			//-----------------------------------------
			
			if( !$appData['name'] OR !$appData['_currentLong'] )
			{
				continue;
			}
			
			$num++;
			$_protected = ( \in_array( $dir, array( 'core', 'forums', 'members' ) ) ) ? 1 : 0;

			\IPS\Db::i()->insert( 'core_applications', array(
				'app_title'			=> $appData['application_title'],
				'app_public_title'	=> $appData['application_title'],
				'app_author'		=> $appData['app_author'],
				'app_version'		=> $appData['_currentHuman'],
				'app_long_version'	=> $appData['_currentLong'],
				'app_directory'		=> $appData['app_directory'],
				'app_added'			=> time(),
				'app_position'		=> $num,
				'app_protected'		=> $_protected,
				'app_location'		=> 'ips',
				'app_enabled'		=> 1,
				'app_website'		=> $appData['app_website']
			) );
		}

		return TRUE;
	}

	/**
	 * Step 10
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step10()
	{
		$fields	= array();

		/* First off, move all current profile fields to group ID 3 */
		\IPS\Db::i()->update( 'pfields_data', array( 'pf_group_id' => 3 ), 'pf_group_id=0' );
		
		/* Grab all custom fields */
		foreach( \IPS\Db::i()->select( '*', 'pfields_data' ) as $field )
		{
			$fields[ $field['pf_id'] ] = $field;
		}
		
		foreach( $fields as $id => $data )
		{
			/* Now add any missing content fields */
			if ( !\IPS\Db::i()->checkForColumn( 'pfields_content', "field_{$id}" ) )
			{
				\IPS\Db::i()->addColumn( 'pfields_content', array(
					'name'			=> "field_{$id}",
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				) );
			}
		}

		/* Now make add a key */
		foreach( \IPS\Db::i()->select( '*', 'pfields_data', 'pf_group_id=3' ) as $row )
		{
			/* Attempt basic conversion of data */
			if ( $row['pf_topic_format'] )
			{
				if ( $row['pf_topic_format'] == '{title}: {content}<br />' )
				{
					$row['pf_topic_format'] = '<span class="ft">{title}:</span><span class="fc">{content}</span>';
				}
				else if ( $row['pf_topic_format'] == '{title}: {key}<br />' )
				{
					$row['pf_topic_format'] = '<span class="ft">{title}:</span><span class="fc">{key}</span>';
				}
			}
			
			/* 2.3.x used 'text', 3.0.0 uses 'input'... */
			$row['pf_type'] = ( $row['pf_type'] == 'text' ) ? 'input' : $row['pf_type'];
			
			\IPS\Db::i()->update( 'pfields_data', array( 'pf_type' => $row['pf_type'], 'pf_topic_format' => $row['pf_topic_format'], 'pf_key' => md5( $row['pf_title'] ) ), 'pf_id=' . $row['pf_id'] );
		}
			
		/* Now, move profile data into the correct fields */
		foreach( array( 'aim_name'   => 'aim',
						'icq_number' => 'icq',
						'website'    => 'website',
						'yahoo'      => 'yahoo',
						'interests'  => 'interests',
						'msnname'    => 'msn',
						'location'   => 'location' ) as $old => $new )
		{
			$field = \IPS\Db::i()->select( '*', 'pfields_data', array( 'pf_key=?', $new ) )->first();
			
			if( empty( $field ) )
			{
				continue;
			}
			
			\IPS\Db::i()->query( "UPDATE " . \IPS\Db::i()->prefix . "pfields_content p, " . \IPS\Db::i()->prefix . "member_extra m SET p.field_{$field['pf_id']}=m.{$old} WHERE p.member_id=m.id" );
		}
		
		/* Update gender */
		$gender = \IPS\Db::i()->select( '*', 'pfields_data', array( 'pf_key=?', 'gender' ) )->first();
												
		if ( $gender['pf_id'] )
		{
			\IPS\Db::i()->query( "UPDATE " . \IPS\Db::i()->prefix . "profile_portal pp, " . \IPS\Db::i()->prefix . "pfields_content pfc SET pfc.field_{$gender['pf_id']}='f' WHERE pp.pp_gender='female' AND pp.pp_member_id=pfc.member_id" );
			\IPS\Db::i()->query( "UPDATE " . \IPS\Db::i()->prefix . "profile_portal pp, " . \IPS\Db::i()->prefix . "pfields_content pfc SET pfc.field_{$gender['pf_id']}='m' WHERE pp.pp_gender='male' AND pp.pp_member_id=pfc.member_id" );
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
		/* And now calendars */
		foreach( \IPS\Db::i()->select( '*', 'cal_calendars' ) as $row )
		{
			if ( strstr( $row['cal_permissions'], 'a:' ) )
			{
				$_perms = unserialize( stripslashes( $row['cal_permissions'] ) );
				
				if ( \is_array( $_perms ) )
				{
					$_view  = ( $_perms['perm_read'] )  ? ',' . implode( ',', explode( ',', $_perms['perm_read'] ) ) . ',' : '';
					$_start = ( $_perms['perm_post'] )  ? ',' . implode( ',', explode( ',', $_perms['perm_post'] ) ) . ',' : '';
					$_nomod = ( $_perms['perm_nomod'] ) ? ',' . implode( ',', explode( ',', $_perms['perm_nomod'] ) ). ',' : '';
					
					\IPS\Db::i()->insert( 'permission_index', array( 'app'          => 'calendar',
																  'perm_type'    => 'calendar',
																  'perm_type_id' => $row['cal_id'],
																  'perm_view'    => str_replace( ',*,', '*', $_view ),
																  'perm_2'		 => str_replace( ',*,', '*', $_start ),
																  'perm_3'		 => str_replace( ',*,', '*', $_nomod ),
																  'perm_4'		 => '',
																  'perm_5'		 => '',
																  'perm_6'		 => '',
																  'perm_7'		 => '' ) );
				}
				else
				{
					\IPS\Db::i()->insert( 'permission_index', array( 'app'          => 'calendar',
																  'perm_type'    => 'calendar',
																  'perm_type_id' => $row['cal_id'],
																  'perm_view'    => '',
																  'perm_2'		 => '',
																  'perm_3'		 => '',
																  'perm_4'		 => '',
																  'perm_5'		 => '',
																  'perm_6'		 => '',
																  'perm_7'		 => '' ) );
				}
			}
			else
			{
				\IPS\Db::i()->insert( 'permission_index', array( 'app'          => 'calendar',
															  'perm_type'    => 'calendar',
															  'perm_type_id' => $row['cal_id'],
															  'perm_view'    => '',
															  'perm_2'		 => '',
															  'perm_3'		 => '',
															  'perm_4'		 => '',
															  'perm_5'		 => '',
															  'perm_6'		 => '',
															  'perm_7'		 => '' ) );
			}
		}
		
		/* And now forums */
		foreach( \IPS\Db::i()->select( '*', 'forums' ) as $row )
		{
			/* Do we need to tidy up the title? */
			if ( strstr( $row['name'], '&' ) )
			{
				$row['name'] = preg_replace( "#& #", "&amp; ", $row['name'] );
				
				\IPS\Db::i()->update( 'forums', array( 'name' => $row['name'] ), 'id=' . $row['id'] );
			}
			
			if ( strstr( $row['permission_array'], 'a:' ) )
			{
				$_perms = unserialize( stripslashes( $row['permission_array'] ) );
				
				if ( \is_array( $_perms ) )
				{
					$_view     = ( $_perms['show_perms'] )     ? ',' . implode( ',', explode( ',', $_perms['show_perms'] ) ) . ',' : '';
					$_read     = ( $_perms['read_perms'] )     ? ',' . implode( ',', explode( ',', $_perms['read_perms'] ) ) . ',' : '';
					$_reply    = ( $_perms['reply_perms'] )    ? ',' . implode( ',', explode( ',', $_perms['reply_perms'] ) ) . ',' : '';
					$_start    = ( $_perms['start_perms'] )    ? ',' . implode( ',', explode( ',', $_perms['start_perms'] ) ) . ',' : '';
					$_upload   = ( $_perms['upload_perms'] )   ? ',' . implode( ',', explode( ',', $_perms['upload_perms'] ) ) . ',' : '';
					$_download = ( $_perms['download_perms'] ) ? ',' . implode( ',', explode( ',', $_perms['download_perms'] ) ) . ',' : '';
					
					\IPS\Db::i()->insert( 'permission_index', array( 'app'          => 'forums',
																  'perm_type'    => 'forum',
																  'perm_type_id' => $row['id'],
																  'perm_view'    => str_replace( ',*,', '*', $_view ),
																  'perm_2'		 => str_replace( ',*,', '*', $_read ),
																  'perm_3'		 => str_replace( ',*,', '*', $_reply ),
																  'perm_4'		 => str_replace( ',*,', '*', $_start ),
																  'perm_5'		 => str_replace( ',*,', '*', $_upload ),
																  'perm_6'		 => str_replace( ',*,', '*', $_download ),
																  'perm_7'		 => '' ) );
				}
				else
				{
					\IPS\Db::i()->insert( 'permission_index', array( 'app'          => 'forums',
																  'perm_type'    => 'forum',
																  'perm_type_id' => $row['id'],
																  'perm_view'    => '',
																  'perm_2'		 => '',
																  'perm_3'		 => '',
																  'perm_4'		 => '',
																  'perm_5'		 => '',
																  'perm_6'		 => '',
																  'perm_7'		 => '' ) );
				}
			}
			else
			{
				\IPS\Db::i()->insert( 'permission_index', array( 'app'          => 'forums',
															  'perm_type'    => 'forum',
															  'perm_type_id' => $row['id'],
															  'perm_view'    => '',
															  'perm_2'		 => '',
															  'perm_3'		 => '',
															  'perm_4'		 => '',
															  'perm_5'		 => '',
															  'perm_6'		 => '',
															  'perm_7'		 => '' ) );
			}
		}

		/* Fix up forum moderators */
		foreach( \IPS\Db::i()->select( '*', 'moderators' ) as $r )
		{
			\IPS\Db::i()->update( 'moderators', array( 'forum_id' => ',' . trim( $r['forum_id'], ',' ) . ',' ), 'mid=' . $r['mid'] );
		}
		
		/* Report center reset */
		$canReport	= array();
		$canView	= array();

		foreach( \IPS\Db::i()->select( 'g_id, g_view_board, g_access_cp, g_is_supmod', 'groups' ) as $r )
		{
			if( $r['g_access_cp'] OR $r['g_is_supmod'] )
			{
				$canView[]	= $r['g_id'];
			}
			
			if( $r['g_view_board'] AND $r['g_id'] != $INFO['guest_group'] )
			{
				$canReport[]	= $r['g_id'];
			}
		}
		
		\IPS\Db::i()->update( 'rc_classes', array( 'group_can_report' => ',' . implode( ',', $canReport ) . ',', 'mod_group_perm' => ',' . implode( ',', $canView ) . ',' ) );

		return TRUE;
	}

	/**
	 * Step 12
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step12()
	{
		/* Convert PMs */
		$pergo		= 200;
		$start		= \intval( \IPS\Request::i()->extra );
		$converted	= 0;
		$seen		= 0;

		/* Select max topic ID thus far */
		$_tmp = \IPS\Db::i()->select( 'MAX(mt_id)', 'message_topics' )->first();
												
		$topicID = \intval( $_tmp['max'] );

		foreach( \IPS\Db::i()->select( '*', 'message_text', NULL, 'msg_id ASC', array( $start, $pergo ) ) as $post )
		{
			$seen++;
			
			/* Make sure all data is valid */
			if ( \intval( $post['msg_sent_to_count'] ) < 1 )
			{
				continue;
			}
			
			/* a little set up */
			$oldTopics = array();
			
			/* Now fetch all topics */
			foreach( \IPS\Db::i()->select( '*', 'message_topics_old', array( 'mt_msg_id=?', \intval( $post['msg_id'] ) ) ) as $topic )
			{
				/* Got any data? */
				if ( ! $topic['mt_from_id'] OR ! $topic['mt_to_id'] )
				{
					continue;
				}
				
				$oldTopics[ $topic['mt_id'] ] = $topic;  # Luke added that space. That's his first contribution to the code vaults at IPS.
			}
			
			/* Fail safe */
			if ( ! \count( $oldTopics ) )
			{
				continue;
			}
			
			/* Increment number */
			$topicID++;
			
			/* Add in the post */
			$postID = \IPS\Db::i()->insert( 'message_posts', array( 'msg_topic_id'      => $topicID,
													   'msg_date'          => $post['msg_date'],
													   'msg_post'          => $post['msg_post'],
													   'msg_post_key'      => $post['msg_post_key'],
													   'msg_author_id'     => $post['msg_author_id'],
													   'msg_ip_address'    => $post['msg_ip_address'],
													   'msg_is_first_post' => 1 ) );

			/* Update attachments */
			\IPS\Db::i()->update( 'attachments', array( 'attach_rel_id' => $postID ), "attach_rel_module='msg' AND attach_rel_id=" . $post['msg_id'] );
			
			/* Define some stuff. "To" member is added last in IPB 2 */
			$_tmp       = $oldTopics;
			ksort( $_tmp );
			$topicData  = array_pop( $_tmp ); 
			$_invited   = array();
			$_seenOwner = array();
			$_isDeleted = 0;
			
			/* Add the member rows */
			foreach( $oldTopics as $mt_id => $data )
			{
				/* Prevent SQL error with unique index: Seen the owner ID already? */
				if ( $_seenOwner[ $data['mt_owner_id'] ] )
				{
					continue;
				}
				
				$_seenOwner[ $data['mt_owner_id'] ] = $data['mt_owner_id'];
				
				/* Build invited - does not include 'to' person */
				if ( $data['mt_owner_id'] AND ( $post['msg_author_id'] != $data['mt_owner_id'] ) AND ( $topicData['mt_to_id'] != $data['mt_owner_id'] ) )
				{
					$_invited[ $data['mt_owner_id'] ] = $data['mt_owner_id'];
				}
				
				$_isSent  = ( $data['mt_vid_folder'] == 'sent' )   ? 1 : 0;
				$_isDraft = ( $data['mt_vid_folder'] == 'unsent' ) ? 1 : 0;
				
				\IPS\Db::i()->insert( 'message_topic_user_map', array( 'map_user_id'     => $data['mt_owner_id'],
																	'map_topic_id'    => $topicID,
																	'map_folder_id'   => ( $_isDraft ) ? 'drafts' : 'myconvo',
																	'map_read_time'   => ( $data['mt_user_read'] ) ? $data['mt_user_read'] : ( $data['mt_read'] ? time() : 0 ),
																	'map_user_active' => 1,
																	'map_user_banned' => 0,
																	'map_has_unread'  => 0, //( $data['mt_read'] ) ? 0 : 1,
																	'map_is_system'   => 0,
																	'map_last_topic_reply' => $post['msg_date'],
																	'map_is_starter'  => ( $data['mt_owner_id'] == $post['msg_author_id'] ) ? 1 : 0 ) );
				
			}
			
			/* Now, did we see the author? If not, add them too but as inactive */
			if ( ! $_seenOwner[ $post['msg_author_id'] ] )
			{
				$_isDeleted = 1;
			}
			
			$_isSent  = ( $topicData['mt_vid_folder'] == 'sent' )   ? 1 : 0;
			$_isDraft = ( $topicData['mt_vid_folder'] == 'unsent' ) ? 1 : 0;

			/* Add the topic */
			\IPS\Db::i()->insert( 'message_topics', array( 'mt_id'			     => $topicID,
														'mt_date'		     => $topicData['mt_date'],
														'mt_title'		     => $topicData['mt_title'],
														'mt_starter_id'	     => $post['msg_author_id'],
														'mt_start_time'      => $post['msg_date'],
														'mt_last_post_time'  => $post['msg_date'],
														'mt_invited_members' => serialize( array_keys( $_invited ) ),
														'mt_to_count'		 => \count(  array_keys( $_invited ) ) + 1,
														'mt_to_member_id'	 => $topicData['mt_to_id'],
														'mt_replies'		 => 0,
														'mt_last_msg_id'	 => $postID,
														'mt_first_msg_id'    => $postID,
														'mt_is_draft'		 => $_isDraft,
														'mt_is_deleted'		 => $_isDeleted,
														'mt_is_system'		 => 0 ) );

			$converted++;
		}
		
		/* What to do? */
		if ( $seen )
		{
			return ( $start + $pergo );
		}
		else
		{
			/* Update all members */
			\IPS\Db::i()->update( 'members', array( 'msg_count_reset' => 1 ) );
			
			/* Nope, nothing to do - we're done! */
			return TRUE;
		}
	}

	/**
	 * Step 13
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step13()
	{
		/* Convert block lists */
		$pergo		= 200;
		$start		= \intval( \IPS\Request::i()->extra );
		$converted	= 0;
		$seen		= 0;

		foreach( \IPS\Db::i()->select( '*', 'members', "LENGTH(ignored_users) > 0", 'member_id ASC', array( $start, $pergo ) ) as $member )
		{
			$seen++;
			
			/* Got anything? */
			if ( strstr( $member['ignored_users'], ',' ) )
			{
				$ignored = explode( ',', $member['ignored_users'] );
			}
			
			if ( ! \is_array( $ignored ) )
			{
				continue;
			}
			
			/* Add it to the table */
			foreach( $ignored as $iid )
			{
				if ( ! $iid )
				{
					continue;
				}
				
				\IPS\Db::i()->insert( 'ignored_users', array( 'ignore_owner_id'  => $member['member_id'],
														   'ignore_ignore_id' => $iid,
														   'ignore_topics'    => 1 ) );
			}
			
			$converted++;
		}
		
		/* What to do? */
		if ( $seen )
		{
			return ( $start + $pergo );
		}
		else
		{
			/* Nope, nothing to do - we're done! */
			return TRUE;
		}
	}

	/**
	 * Step 14
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step14()
	{
		/* Convert PM block lists */
		$pergo		= 200;
		$start		= \intval( \IPS\Request::i()->extra );
		$converted	= 0;
		$seen		= 0;

		foreach( \IPS\Db::i()->select( '*', 'contacts', "allow_msg=0", 'id ASC', array( $start, $pergo ) ) as $contact )
		{
			$seen++;
			
			/* Already got an entry for this contact? */
			try
			{
				$test = \IPS\Db::i()->select( '*', 'ignored_users', array( "ignore_owner_id=?", \intval( $contact['member_id'] ) ) )->first();

				\IPS\Db::i()->update( 'ignored_users', array( 'ignore_messages' => 1 ), 'ignore_id=' . $test['ignore_id'] );
			}
			catch( \UnderflowException $e )
			{
				\IPS\Db::i()->insert( 'ignored_users', array( 'ignore_owner_id'  => $contact['member_id'],
														   'ignore_ignore_id' => $contact['contact_id'],
														   'ignore_messages'  => 1 ) );
			}
			
			$converted++;
		}
		
		/* What to do? */
		if ( $seen )
		{
			return ( $start + $pergo );
		}
		else
		{
			/* Nope, nothing to do - we're done! */
			return TRUE;
		}
	}

	/**
	 * Step 15
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step15()
	{
		/* Reset member languages and skins */
		\IPS\Db::i()->update( 'members', array( 'language' => 0, 'skin' => 0 ) );
		
		/* Empty caches */
		\IPS\Db::i()->delete( 'cache_store', "cs_key='forum_cache'" );
		\IPS\Db::i()->delete( 'cache_store', "cs_key='skin_id_cache'" );
		
		/* Reset admin permissions */
		\IPS\Db::i()->update( 'admin_permission_rows', array( 'row_perm_cache' => '' ) );
		
		/* Drop Tables */
		\IPS\Db::i()->dropTable( 'contacts' );
		\IPS\Db::i()->dropTable( 'skin_macro' );
		\IPS\Db::i()->dropTable( 'skin_template_links' );
		\IPS\Db::i()->dropTable( 'skin_sets' );
		\IPS\Db::i()->dropTable( 'languages' );
		\IPS\Db::i()->dropTable( 'topics_read' );
		\IPS\Db::i()->dropTable( 'topic_markers' );
		\IPS\Db::i()->dropTable( 'acp_help' );
		\IPS\Db::i()->dropTable( 'members_converge' );
		\IPS\Db::i()->dropTable( 'member_extra' );
		\IPS\Db::i()->dropTable( 'admin_sessions' );
		\IPS\Db::i()->dropTable( 'components' );
		\IPS\Db::i()->dropTable( 'admin_permission_keys' );
		\IPS\Db::i()->dropTable( 'conf_settings' );
		\IPS\Db::i()->dropTable( 'conf_settings_titles' );
		\IPS\Db::i()->dropTable( 'reg_antispam' );
		\IPS\Db::i()->dropTable( 'message_text' );
		\IPS\Db::i()->dropTable( 'message_topics_old' );

		return TRUE;
	}
}