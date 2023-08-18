<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_30012;

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
			if( !\IPS\Db::i()->checkForColumn( 'tags_index', 'tag_hidden' ) )
			{
				\IPS\Db::i()->addColumn( 'tags_index', array(
					'name'			=> 'tag_hidden',
					'type'			=> 'int',
					'length'		=> 1,
					'allow_null'	=> false,
					'default'		=> 0
				) );
	
				\IPS\Db::i()->addIndex( 'tags_index', array(
					'type'			=> 'key',
					'name'			=> 'tag_grab',
					'columns'		=> array( 'app', 'type', 'type_id', 'type_2', 'type_id_2', 'tag_hidden' )
				) );
			}
	
			\IPS\Db::i()->addIndex( 'message_topic_user_map', array(
				'type'			=> 'key',
				'name'			=> 'map_topic_id',
				'columns'		=> array( 'map_topic_id' )
			) );
	
			if( !\IPS\Db::i()->checkForColumn( 'message_topic_user_map', 'map_last_topic_reply' ) )
			{
				\IPS\Db::i()->addColumn( 'message_topic_user_map', array(
					'name'			=> 'map_last_topic_reply',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				) );
			}
	
			\IPS\Db::i()->query( "UPDATE " . \IPS\Db::i()->prefix . "message_topic_user_map m, " . \IPS\Db::i()->prefix . "message_topics t SET m.map_last_topic_reply=t.mt_last_post_time WHERE m.map_topic_id=t.mt_id" );
	
			\IPS\Db::i()->dropIndex( 'task_manager', 'task_next_run' );
	
			\IPS\Db::i()->addIndex( 'task_manager', array(
				'type'			=> 'key',
				'name'			=> 'task_next_run',
				'columns'		=> array( 'task_enabled', 'task_next_run' )
			) );
	
			\IPS\Db::i()->addIndex( 'captcha', array(
				'type'			=> 'key',
				'name'			=> 'captcha_date',
				'columns'		=> array( 'captcha_date' )
			) );
	
			\IPS\Db::i()->addIndex( 'rss_import', array(
				'type'			=> 'key',
				'name'			=> 'rss_import_enabled',
				'columns'		=> array( 'rss_import_enabled', 'rss_import_last_import' )
			) );
	
			\IPS\Db::i()->addIndex( 'core_sys_settings_titles', array(
				'type'			=> 'key',
				'name'			=> 'conf_title_keyword',
				'columns'		=> array( 'conf_title_keyword' )
			) );
	
			\IPS\Db::i()->addIndex( 'core_sys_conf_settings', array(
				'type'			=> 'key',
				'name'			=> 'conf_key',
				'columns'		=> array( 'conf_key' )
			) );
	
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_rss_imported',
				'columns'	=> array(
					array(
						'name'			=> 'rss_guid',
						'type'			=> 'char',
						'length'		=> 32,
						'allow_null'	=> false,
					),
					array(
						'name'			=> 'rss_foreign_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'rss_foreign_key',
						'type'			=> 'varchar',
						'length'		=> 100,
						'allow_null'	=> false,
						'default'		=> ''
					)
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'rss_guid' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'rss_grabber',
						'columns'	=> array( 'rss_guid', 'rss_foreign_key' )
					),
				)
			)	);
		}

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
			'table' => 'forum_tracker',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forum_tracker CHANGE COLUMN member_id member_id MEDIUMINT(8) NOT NULL DEFAULT 0;"
		),
		array(
			'table' => 'topics',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics ADD INDEX last_x_topics (forum_id, approved, start_date),
				DROP INDEX last_post,
				ADD INDEX last_post (forum_id, pinned, last_post, state);"
		),
		array(
			'table' => 'polls',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "polls ADD INDEX tid (tid);"
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
}