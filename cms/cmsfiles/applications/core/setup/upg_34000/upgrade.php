<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Jun 2014
 */

namespace IPS\core\setup\upg_34000;

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
		\IPS\Db::i()->dropTable( 'converge_local', TRUE );

		\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key IN( 'ipconverge_enabled', 'ipconverge_url', 'ipconverge_pid' )" );
		\IPS\Db::i()->delete( 'core_sys_settings_titles', "conf_title_keyword='ipconverge'" );
		\IPS\Db::i()->delete( 'login_methods', "login_folder_name='ipconverge'" );

		if( !\IPS\Db::i()->checkForColumn( 'mail_queue', 'mail_html_content' ) )
		{
			\IPS\Db::i()->addColumn( 'mail_queue', array(
				'name'			=> 'mail_html_content',
				'type'			=> 'mediumtext',
				'allow_null'	=> true,
				'default'		=> null
			) );
		}

		if( !\IPS\Db::i()->checkForColumn( 'forums', 'viglink' ) )
		{
			\IPS\Db::i()->addColumn( 'forums', array(
				'name'			=> 'viglink',
				'type'			=> 'tinyint',
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> 1
			) );
		}

		\IPS\Db::i()->update( 'custom_bbcode', array( 'bbcode_replace' => '', 'bbcode_php_plugin' => 'defaults.php' ), "bbcode_tag='code'" );

		if( !\IPS\Db::i()->checkForColumn( 'core_sys_login', 'sys_bookmarks' ) )
		{
			\IPS\Db::i()->addColumn( 'core_sys_login', array(
				'name'			=> 'sys_bookmarks',
				'type'			=> 'mediumtext',
				'allow_null'	=> true,
				'default'		=> null
			) );
		}

		if( !\IPS\Db::i()->checkForTable( 'core_sys_bookmarks' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'core_sys_bookmarks',
				'columns'	=> array(
					array(
						'name'			=> 'bookmark_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'bookmark_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'bookmark_title',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'bookmark_url',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'bookmark_home',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'bookmark_pos',
						'type'			=> 'int',
						'length'		=> 5,
						'allow_null'	=> false,
						'default'		=> 0
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'bookmark_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'bookmark_member_id',
						'columns'	=> array( 'bookmark_member_id' )
					),
				)
			)	);
		}

		\IPS\Db::i()->delete( 'core_uagents', "uagent_key='mob_saf'" );
		\IPS\Db::i()->delete( 'bbcode_mediatag', "mediatag_name='GameTrailers'" );

		if( \IPS\Db::i()->checkForColumn( 'forums', 'rules_raw_html' ) )
		{
			\IPS\Db::i()->dropColumn( 'forums', 'rules_raw_html' );
		}
		
		\IPS\Db::i()->dropTable( 'core_share_links_caches', TRUE );

		if( !\IPS\Db::i()->checkForTable( 'backup_vars' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'backup_vars',
				'columns'	=> array(
					array(
						'name'			=> 'backup_var_key',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'backup_var_value',
						'type'			=> 'text',
						'allow_null'	=> true,
						'default'		=> null
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'backup_var_key' ),
						'length'	=> array( 190 )
					),
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable( 'backup_log' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'backup_log',
				'columns'	=> array(
					array(
						'name'			=> 'log_id',
						'type'			=> 'bigint',
						'length'		=> 20,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'log_row_count',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'log_result',
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
				)
			)	);
		}


		if( !\IPS\Db::i()->checkForTable( 'backup_queue' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'backup_queue',
				'columns'	=> array(
					array(
						'name'			=> 'queue_id',
						'type'			=> 'bigint',
						'length'		=> 20,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'queue_entry_date',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'queue_entry_type',
						'type'			=> 'int',
						'length'		=> 1,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'queue_entry_table',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'queue_entry_key',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'queue_entry_value',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'queue_entry_sql',
						'type'			=> 'mediumtext',
						'allow_null'	=> true,
						'default'		=> null
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'queue_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'date',
						'columns'	=> array( 'queue_entry_date' )
					),
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable('seo_meta') )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'seo_meta',
				'columns'	=> array(
					array(
						'name'			=> 'url',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> false,
						'default'		=> '*'
					),
					array(
						'name'			=> 'name',
						'type'			=> 'varchar',
						'length'		=> 50,
						'allow_null'	=> false,
						'default'		=> ''

					),
					array(
						'name'			=> 'content',
						'type'			=> 'text',
						'allow_null'	=> false
					),
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable('seo_acronyms') )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'seo_acronyms',
				'columns'	=> array(
					array(
						'name'			=> 'a_id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'a_short',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'a_long',
						'type'			=> 'varchar',
						'length'		=> 255,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'a_semantic',
						'type'			=> 'tinyint',
						'length'		=> 1,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'a_casesensitive',
						'type'			=> 'tinyint',
						'length'		=> 1,
						'allow_null'	=> true,
						'default'		=> null
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'a_id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'a_short',
						'columns'	=> array( 'a_short' ),
						'length'	=> array( 191 )
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
			'table' => 'members',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members ADD COLUMN ipsconnect_id INT(10) NOT NULL DEFAULT 0;"
		),
		array(
			'table' => 'topics',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics ADD COLUMN topic_answered_pid INT(10) NOT NULL DEFAULT 0;"
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
		if ( \IPS\Settings::i()->seo_index_md )
		{
			\IPS\Db::i()->insert( 'seo_meta', array( 'url' => '', 'name' => 'description', 'content' => \IPS\Settings::i()->seo_index_md ) );
		}
		if ( \IPS\Settings::i()->seo_index_mk )
		{
			\IPS\Db::i()->insert( 'seo_meta', array( 'url' => '', 'name' => 'keywords', 'content' => \IPS\Settings::i()->seo_index_mk ) );
		}
		if ( \IPS\Settings::i()->seo_index_title )
		{
			\IPS\Db::i()->insert( 'seo_meta', array( 'url' => '', 'name' => 'title', 'content' => \IPS\Settings::i()->seo_index_title ) );
		}
		
		\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key IN( 'seo_index_md', 'seo_index_mk', 'seo_index_title', 'ipseo_ping_services', 'ipseo_guest_skin' )" );
		\IPS\Db::i()->delete( 'core_sys_settings_titles', "conf_title_keyword='ipseo'" );
		\IPS\Settings::i()->clearCache();
		
		if ( !\IPS\Db::i()->checkForColumn( 'forums', 'ipseo_priority' ) )
		{
			\IPS\Db::i()->addColumn( 'forums', array(
				'name'			=> 'ipseo_priority',
				'type'			=> 'varchar',
				'length'		=> 3,
				'allow_null'	=> false,
				'default'		=> '-1'
			) );
		}
		
		\IPS\Db::i()->delete( 'core_applications', "app_directory='ipseo'" );
		\IPS\Db::i()->delete( 'upgrade_history', "upgrade_app='ipseo'" );

		return TRUE;
	}
}