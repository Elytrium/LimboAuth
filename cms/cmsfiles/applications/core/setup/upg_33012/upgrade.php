<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		4 Jun 2014
 */

namespace IPS\core\setup\upg_33012;

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
		/* 3.3.0 fresh install still has this field */
		if( \IPS\Db::i()->checkForColumn( 'rss_import', 'rss_import_inc_pcount' ) )
		{
			\IPS\Db::i()->dropColumn( 'rss_import', 'rss_import_inc_pcount' );
		}

		/* 3.3.x install might still have this old/unused fields */
		if( \IPS\Db::i()->checkForColumn( 'core_sys_module', 'sys_module_parent' ) )
		{
			\IPS\Db::i()->dropColumn( 'core_sys_module', 'sys_module_parent' );
		}

		if( \IPS\Db::i()->checkForColumn( 'core_sys_module', 'sys_module_tables' ) )
		{
			\IPS\Db::i()->dropColumn( 'core_sys_module', 'sys_module_tables' );
		}

		if( \IPS\Db::i()->checkForColumn( 'core_sys_module', 'core_sys_module' ) )
		{
			\IPS\Db::i()->dropColumn( 'core_sys_module', 'core_sys_module' );
		}

		\IPS\Db::i()->changeColumn( 'cache_store', 'cs_value', array(
			'name'			=> 'cs_value',
			'type'			=> 'mediumtext',
			'length'		=> null,
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->createTable( array(
			'name'		=> 'reputation_totals',
			'columns'	=> array(
				array(
					'name'			=> 'rt_key',
					'type'			=> 'char',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'rt_app_type',
					'type'			=> 'char',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'rt_total',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'rt_type_id',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'rt_key' )
				),
				array(
					'type'		=> 'key',
					'name'		=> 'rt_app_type',
					'columns'	=> array( 'rt_app_type', 'rt_total' )
				)
			)
		)	);

		return TRUE;
	}

	/**
	 * Step 2
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$query = array( array( 'table' => 'reputation_index', 'query' => "INSERT INTO " . \IPS\Db::i()->prefix . "reputation_totals SELECT MD5( CONCAT( app, ';', type, ';', type_id) ), MD5( CONCAT( app, ';', type ) ), SUM(rep_rating), type_id FROM " . \IPS\Db::i()->prefix . "reputation_index GROUP BY app, type, type_id ON DUPLICATE KEY UPDATE rt_key=rt_key" ) );

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $query );

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
		/* New blog column */
		$addBlogColumn	= false;

		if( !\IPS\Db::i()->checkForColumn( 'members', 'blogs_recache' ) )
		{
			$addBlogColumn	= true;
		}

		$queries = array( array(
			'table' => 'message_posts',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "message_posts ADD INDEX msg_author_id (msg_author_id);"
		),
		array(
			'table' => 'forums_archive_posts',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forums_archive_posts ADD COLUMN archive_forum_id INT(10) NOT NULL DEFAULT 0;"
		),
		array(
			'table' => 'inline_notifications',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "inline_notifications ADD INDEX notify_from_id (notify_from_id);"
		) );

		if( $addBlogColumn )
		{
			$queries[]	= array(
				'table'	=> 'members',
				'query'	=> "ALTER TABLE " . \IPS\Db::i()->prefix . "members ADD COLUMN blogs_recache TINYINT(1) NULL DEFAULT NULL,
					ADD INDEX blogs_recache (blogs_recache);"
			);
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 4 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		/* Finish */
		return TRUE;
	}
}