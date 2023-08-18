<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_30011;

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
	
			\IPS\Db::i()->changeColumn( 'core_sys_module', 'sys_module_version', array(
				'name'			=> 'sys_module_version',
				'type'			=> 'varchar',
				'length'		=> 32,
				'allow_null'	=> false
			) );
	
			\IPS\Db::i()->changeColumn( 'core_sys_lang_words', 'word_js', array(
				'name'			=> 'word_js',
				'type'			=> 'tinyint',
				'length'		=> 1,
				'unsigned'		=> true,
				'allow_null'	=> false,
				'default'		=> 0
			) );
	
			\IPS\Db::i()->query( "UPDATE " . \IPS\Db::i()->prefix . "core_sys_conf_settings SET conf_value=conf_default WHERE conf_key='links_external' AND conf_value=''" );
	
			\IPS\Db::i()->delete( "core_sys_conf_settings", array( 'conf_key=?', 'spider_suit' ) );
			\IPS\Db::i()->delete( "core_sys_conf_settings", array( 'conf_key=?', 'c_cache_days' ) );
	
			\IPS\Settings::i()->clearCache();
	
			\IPS\Db::i()->changeColumn( 'forums', 'permission_array', array(
				'name'			=> 'permission_array',
				'type'			=> 'mediumtext',
				'allow_null'	=> true,
				'default'		=> null
			) );
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
			'table' => 'topics',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics ADD INDEX start_date (start_date);"
		),
		array(
			'table' => 'members',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members CHANGE COLUMN fb_uid fb_uid BIGINT(20) NOT NULL DEFAULT 0;"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 3 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		/* @note We previously updated a media tag bbcode here, but it's no longer relevant in the latest release so there is no point in doing so */

		/* Finish */
		return TRUE;
	}
}