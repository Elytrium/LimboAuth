<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Jun 2014
 */

namespace IPS\core\setup\upg_33000;

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
		\IPS\Db::i()->createTable( array(
			'name'		=> 'members_warn_actions',
			'columns'	=> array(
				array(
					'name'			=> 'wa_id',
					'type'			=> 'int',
					'length'		=> 11,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'wa_points',
					'type'			=> 'int',
					'length'		=> 32,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wa_mq',
					'type'			=> 'int',
					'length'		=> 2,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wa_mq_unit',
					'type'			=> 'char',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wa_rpa',
					'type'			=> 'int',
					'length'		=> 2,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wa_rpa_unit',
					'type'			=> 'char',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wa_suspend',
					'type'			=> 'int',
					'length'		=> 2,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wa_suspend_unit',
					'type'			=> 'char',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wa_ban_group',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wa_override',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'wa_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'wa_points',
					'columns'	=> array( 'wa_points' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'members_warn_logs',
			'columns'	=> array(
				array(
					'name'			=> 'wl_id',
					'type'			=> 'int',
					'length'		=> 11,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'wl_member',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_moderator',
					'type'			=> 'mediumint',
					'length'		=> 8,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_date',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_reason',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_points',
					'type'			=> 'int',
					'length'		=> 5,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_note_member',
					'type'			=> 'text',
					'length'		=> null,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_note_mods',
					'type'			=> 'text',
					'length'		=> null,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_mq',
					'type'			=> 'int',
					'length'		=> 2,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_mq_unit',
					'type'			=> 'char',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_rpa',
					'type'			=> 'int',
					'length'		=> 2,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_rpa_unit',
					'type'			=> 'char',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_suspend',
					'type'			=> 'int',
					'length'		=> 2,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_suspend_unit',
					'type'			=> 'char',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_ban_group',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_expire',
					'type'			=> 'int',
					'length'		=> 2,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_expire_unit',
					'type'			=> 'char',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_acknowledged',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_content_app',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_content_id1',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_content_id2',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wl_expire_date',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'wl_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'wl_member',
					'columns'	=> array( 'wl_member' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'wl_moderator',
					'columns'	=> array( 'wl_moderator' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'wl_date',
					'columns'	=> array( 'wl_member', 'wl_date' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'content',
					'columns'	=> array( 'wl_content_app', 'wl_content_id1', 'wl_content_id2' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'wl_expire_date',
					'columns'	=> array( 'wl_expire_date' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'members_warn_reasons',
			'columns'	=> array(
				array(
					'name'			=> 'wr_id',
					'type'			=> 'int',
					'length'		=> 11,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'wr_name',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wr_points',
					'type'			=> 'float',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wr_points_override',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wr_remove',
					'type'			=> 'int',
					'length'		=> 2,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wr_remove_unit',
					'type'			=> 'char',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wr_remove_override',
					'type'			=> 'tinyint',
					'length'		=> 1,
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'wr_order',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'wr_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'wr_order',
					'columns'	=> array( 'wr_order' )
				)
			)
		)	);

		\IPS\Db::i()->insert( 'members_warn_reasons', array(
			array( 
				'wr_id'					=> 1,
				'wr_name'				=> 'Spamming',
				'wr_points'				=> 1,
				'wr_points_override'	=> 0,
				'wr_remove'				=> 0,
				'wr_remove_unit'		=> 'h',
				'wr_remove_override'	=> 0,
				'wr_order'				=> 1,
			),
			array( 
				'wr_id'					=> 2,
				'wr_name'				=> 'Inappropriate Language',
				'wr_points'				=> 1,
				'wr_points_override'	=> 0,
				'wr_remove'				=> 0,
				'wr_remove_unit'		=> 'h',
				'wr_remove_override'	=> 0,
				'wr_order'				=> 2,
			),
			array( 
				'wr_id'					=> 3,
				'wr_name'				=> 'Signature Violation',
				'wr_points'				=> 1,
				'wr_points_override'	=> 0,
				'wr_remove'				=> 0,
				'wr_remove_unit'		=> 'h',
				'wr_remove_override'	=> 0,
				'wr_order'				=> 3,
			),
			array( 
				'wr_id'					=> 4,
				'wr_name'				=> 'Abusive Behaviour',
				'wr_points'				=> 1,
				'wr_points_override'	=> 0,
				'wr_remove'				=> 0,
				'wr_remove_unit'		=> 'h',
				'wr_remove_override'	=> 0,
				'wr_order'				=> 4,
			),
			array( 
				'wr_id'					=> 5,
				'wr_name'				=> 'Topic Bumping',
				'wr_points'				=> 1,
				'wr_points_override'	=> 0,
				'wr_remove'				=> 0,
				'wr_remove_unit'		=> 'h',
				'wr_remove_override'	=> 0,
				'wr_order'				=> 5,
			),
		)	);

		\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key IN ('warn_min', 'warn_max', 'warn_past_max', 'warn_mod_ban', 'warn_mod_modq', 'warn_mod_post', 'warn_gmod_ban', 'warn_gmod_modq', 'warn_gmod_post', 'resize_img_percent')" );
		\IPS\Settings::i()->clearCache();

		\IPS\Db::i()->update( 'moderators', "mod_bitoptions=mod_bitoptions-496", "mod_bitoptions > 496" );

		\IPS\Db::i()->delete( 'core_item_markers' );

		if( \IPS\Db::i()->checkForIndex( 'core_item_markers', 'marker_index' ) )
		{
			\IPS\Db::i()->dropIndex( 'core_item_markers', 'marker_index' );
		}

		\IPS\Db::i()->addIndex( 'core_item_markers', array(
			'type'			=> 'key',
			'name'			=> 'marker_index',
			'columns'		=> array( 'item_member_id', 'item_app', 'item_app_key_1' )
		) );

		return TRUE;
	}

	/**
	 * Step 2
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'members',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members ADD COLUMN unacknowledged_warnings TINYINT(1) NULL DEFAULT NULL;"
		),
		array(
			'table' => 'reputation_cache',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "reputation_cache ADD COLUMN cache_date INT(10) NOT NULL DEFAULT 0,
				ADD INDEX cache_date (cache_date);"
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
		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_archive_log',
			'columns'	=> array(
				array(
					'name'			=> 'archlog_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'archlog_app',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'archlog_date',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archlog_ids',
					'type'			=> 'mediumtext',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'archlog_count',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archlog_is_restore',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archlog_is_error',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archlog_msg',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'archlog_id' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_archive_restore',
			'columns'	=> array(
				array(
					'name'			=> 'restore_min_tid',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'restore_max_tid',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'restore_manual_tids',
					'type'			=> 'mediumtext',
					'allow_null'	=> true,
					'default'		=> null
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'core_archive_rules',
			'columns'	=> array(
				array(
					'name'			=> 'archive_key',
					'type'			=> 'char',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'archive_app',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> 'core'
				),
				array(
					'name'			=> 'archive_field',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'archive_value',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'archive_text',
					'type'			=> 'text',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'archive_unit',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'archive_skip',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'archive_key' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'forums_archive_posts',
			'columns'	=> array(
				array(
					'name'			=> 'archive_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_author_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_author_name',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> '0'
				),
				array(
					'name'			=> 'archive_ip_address',
					'type'			=> 'varchar',
					'length'		=> 46,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'archive_content_date',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_content',
					'type'			=> 'mediumtext',
					'allow_null'	=> true,
					'default'		=> null
				),
				array(
					'name'			=> 'archive_queued',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 1
				),
				array(
					'name'			=> 'archive_topic_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_is_first',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_bwoptions',
					'type'			=> 'int',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_attach_key',
					'type'			=> 'char',
					'length'		=> 32,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'archive_html_mode',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_show_signature',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_show_emoticons',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_show_edited_by',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_edit_time',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_edit_name',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'archive_edit_reason',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false,
					'default'		=> ''
				),
				array(
					'name'			=> 'archive_added',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'archive_restored',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'archive_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'archive_topic_id',
					'columns'	=> array( 'archive_topic_id', 'archive_queued', 'archive_content_date' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'archive_author_id',
					'columns'	=> array( 'archive_author_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'archive_restored',
					'columns'	=> array( 'archive_restored' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'archive_content_date',
					'columns'	=> array( 'archive_content_date', 'archive_topic_id' )
				),
				array(
					'type'		=> 'fulltext',
					'name'		=> 'archive_content',
					'columns'	=> array( 'archive_content' )
				),
			)
		)	);

		\IPS\Db::i()->createTable( array(
			'name'		=> 'forums_recent_posts',
			'columns'	=> array(
				array(
					'name'			=> 'post_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'post_topic_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'post_forum_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'post_author_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'post_date',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'post_id' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'group_lookup',
					'columns'	=> array( 'post_author_id', 'post_forum_id', 'post_date', 'post_id' )
				),
			)
		)	);

		\IPS\Db::i()->addColumn( 'attachments', array(
			'name'			=> 'attach_is_archived',
			'type'			=> 'int',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'archived_topics',
			'type'			=> 'int',
			'length'		=> 10,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'forums', array(
			'name'			=> 'archived_posts',
			'type'			=> 'int',
			'length'		=> 10,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addIndex( 'rc_reports', array(
			'type'			=> 'key',
			'name'			=> 'rid',
			'columns'		=> array( 'rid' )
		) );

		\IPS\Db::i()->delete( "core_sys_conf_settings", "conf_key IN ('seo_bad_url', 'spider_sense')" );
		\IPS\Settings::i()->clearCache();

		\IPS\Db::i()->addColumn( 'announcements', array(
			'name'			=> 'announce_seo_title',
			'type'			=> 'varchar',
			'length'		=> 255,
			'allow_null'	=> false,
			'default'		=> ''
		) );

		\IPS\Db::i()->addColumn( 'core_uagents', array(
			'name'			=> 'uagent_default_regex',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->update( 'core_uagents', "uagent_default_regex=uagent_regex" );

		\IPS\Db::i()->dropColumn( 'rss_import', 'rss_import_inc_pcount' );

		\IPS\Db::i()->addIndex( 'mobile_notifications', array(
			'type'			=> 'key',
			'name'			=> 'notify_sent_notify_date',
			'columns'		=> array( 'notify_sent', 'notify_date' )
		) );

		return TRUE;
	}

	/**
	 * Step 4
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		$queries = array( array(
			'table' => 'topics',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics ADD COLUMN last_real_post INT(10) NOT NULL DEFAULT 0,
				ADD COLUMN topic_archive_status INT(1) NOT NULL DEFAULT 0,
				ADD INDEX topic_archive_status ( topic_archive_status, forum_id );"
		) );

		$memberQueries = array();

		if( \IPS\Db::i()->checkForColumn( 'members', 'sub_end' ) ) 
		{
			$memberQueries[] = "DROP COLUMN sub_end";
		}

		if( \IPS\Db::i()->checkForColumn( 'members', 'subs_pkg_chosen' ) ) 
		{
			$memberQueries[] = "DROP COLUMN subs_pkg_chosen";
		}

		if( \IPS\Db::i()->checkForColumn( 'members', 'members_editor_choice' ) ) 
		{
			$memberQueries[] = "DROP COLUMN members_editor_choice";
		}

		if( \count( $memberQueries ) )
		{
			$queries[] = array( 'table' => 'members', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members " . implode( ', ', $memberQueries ) );
		}

		if( !\IPS\Db::i()->checkForColumn( 'profile_portal', 'pp_profile_update' ) ) 
		{
			$queries[]	= array( 'table' => 'profile_portal', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "profile_portal ADD COLUMN pp_profile_update INT(10) UNSIGNED NOT NULL DEFAULT 0" );
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );
		
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
		/* Init */
		$pergo		= 100;
		$start		= \intval( \IPS\Request::i()->extra );
		$did		= 0;
						
		/* Get them */
		$total = \IPS\Db::i()->select( 'count(*)', 'warn_logs', "wlog_type<>'pos'" )->first();

		foreach( \IPS\Db::i()->select( '*', 'warn_logs', "wlog_type<>'pos'", 'wlog_id ASC', array( $start, $pergo ) ) as $row )
		{
			$did++;

			//-----------------------------------------
			// Get the PM / EMail we sent to the member
			// This will  now be our "note for member"
			//-----------------------------------------
		
			preg_match( "#<content>(.+?)</content>#is", $row['wlog_contact_content'], $content );
			$noteForMember = $content[1];
			
			//-----------------------------------------
			// Work out what punishment we gave
			//-----------------------------------------
			
			$noteForMods	= '';
			$mq				= 0;
			$mqUnit			= '';
			$rpa			= 0;
			$rpaUnit		= '';
			$suspend		= 0;
			$suspendUnit	= '';
					
			$unserialized = unserialize( $row['wlog_notes'] );
			
			/* Old style */
			if ( $unserialized == FALSE )
			{
				preg_match( "#<content>(.+?)</content>#is", $row['wlog_notes'], $content );
				$noteForMods = $content[1];
				
				foreach ( array( 'mod' => 'mq', 'post' => 'rpa', 'susp' => 'suspend' ) as $k => $v )
				{
					$data = array();
					preg_match( "#<{$k}>(.+?)</{$k}>#is", $row['wlog_notes'], $data );
					if ( $data[1] )
					{
						$data = explode( ',', $data[1] );
												
						if ( $data[2] == 1 || $data[0] > 999999 )
						{
							$$v = -1;
						}
						else
						{
							$$v = $data[0];
							$unitVar = "{$v}Unit";
							$$unitVar = $data[1];
						}
					}
				}
				
			}
			
			/* New style */
			else
			{
				$noteForMods	= $unserialized['content'];
				
				foreach ( array( 'mod' => 'mq', 'post' => 'rpa', 'susp' => 'suspend' ) as $k => $v )
				{
					if ( $unserialized[ $k . '_indef' ] == 1 || $unserialized[ $k ] > 999999 )
					{
						$$v = -1;
					}
					else
					{
						$$v = $unserialized[ $k ];
						$unitVar = "{$v}Unit";
						$$unitVar = $unserialized[ $k . '_unit' ];
					}
				}
			}
			
			//-----------------------------------------
			// Save
			//-----------------------------------------
		
			\IPS\Db::i()->insert( 'members_warn_logs', array(
				'wl_member'			=> $row['wlog_mid'],
				'wl_moderator'		=> $row['wlog_addedby'],
				'wl_date'			=> $row['wlog_date'],
				'wl_reason'			=> 0,
				'wl_points'			=> ( $row['wlog_type'] == 'neg' ) ? 1 : 0,
				'wl_note_member'	=> $noteForMember,
				'wl_note_mods'		=> $noteForMods,
				'wl_mq'				=> \intval( $mq ),
				'wl_mq_unit'		=> $mqUnit,
				'wl_rpa'			=> \intval( $rpa ),
				'wl_rpa_unit'		=> $rpaUnit,
				'wl_suspend'		=> \intval( $suspend ),
				'wl_suspend_unit'	=> $suspendUnit,
				'wl_ban_group'		=> 0,
				'wl_expire'			=> 0,
				'wl_expire_unit'	=> '',
				'wl_acknowledged'	=> 1,
				'wl_content_app'	=> '',
				'wl_content_id1'	=> '',
				'wl_content_id2'	=> ''
				) );
		}
		
		/* Next! */
		if( !$did )
		{
			return TRUE;
		}
		else
		{
			return ( $start + $pergo );
		}
	}

	/**
	 * Step 6
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
	{
		foreach( \IPS\Db::i()->select( '*', 'forums', "rules_text LIKE '%&%'" ) as $row )
		{
			$row['rules_text'] = str_replace( "&amp;" , "&", $row['rules_text'] );
			$row['rules_text'] = str_replace( "&#38;" , "&", $row['rules_text'] );
			$row['rules_text'] = str_replace( "&lt;"  , "<", $row['rules_text'] );
			$row['rules_text'] = str_replace( "&gt;"  , ">", $row['rules_text'] );
			$row['rules_text'] = str_replace( "&quot;", '"', $row['rules_text'] );
			$row['rules_text'] = str_replace( "&#039;", "'", $row['rules_text'] );
			$row['rules_text'] = str_replace( "&#39;" , "'", $row['rules_text'] );
			$row['rules_text'] = str_replace( "&#33;" , "!", $row['rules_text'] );
			$row['rules_text'] = str_replace( "&#34;" , '"', $row['rules_text'] );
			$row['rules_text'] = str_replace( "&#036;", '$', $row['rules_text'] );

			\IPS\Db::i()->update( 'forums', array( 'rules_text' => $row['rules_text'] ), 'id=' . $row['id'] );
		}

		return TRUE;
	}

	/**
	 * Step 7
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		foreach( \IPS\Db::i()->select( '*', 'groups' ) as $row )
		{
			\IPS\Db::i()->update( 'groups', "g_bitoptions = g_bitoptions | 8388608", 'g_id=' . $row['g_id'] );
			\IPS\Db::i()->update( 'groups', "g_bitoptions = g_bitoptions | 16777216", 'g_id=' . $row['g_id'] );
			\IPS\Db::i()->update( 'groups', "g_bitoptions = g_bitoptions &~ 33554432", 'g_id=' . $row['g_id'] );
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
		$queries	= array();

		if ( !\IPS\Db::i()->checkForColumn( 'posts', 'post_field_int' ) )
		{
			$queries[]	= array( 'table' => 'posts', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "posts ADD post_field_int INT(10) NOT NULL DEFAULT 0;" );
		}
		
		if ( !\IPS\Db::i()->checkForColumn( 'posts', 'post_field_t1' ) )
		{
			$queries[]	= array( 'table' => 'posts', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "posts ADD post_field_t1 TEXT NULL DEFAULT NULL;" );
		}
		
		if ( !\IPS\Db::i()->checkForColumn( 'posts', 'post_field_t2' ) )
		{
			$queries[]	= array( 'table' => 'posts', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "posts ADD post_field_t2 TEXT NULL DEFAULT NULL;" );
		}

		if( !\count( $queries ) )
		{
			return TRUE;
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 9 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		/* @note Previously we gave the option of flagging users in the banned group as banned, but we force that in 4.0 anyways so no need now */

		/* Finish */
		return TRUE;
	}
}