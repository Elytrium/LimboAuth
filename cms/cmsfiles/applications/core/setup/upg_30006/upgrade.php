<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_30006;

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
			if( !\IPS\Db::i()->checkForColumn( 'skin_templates', 'template_user_added' ) )
			{
				\IPS\Db::i()->addColumn( 'skin_templates', array(
					'name'			=> 'template_user_added',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				) );
	
				\IPS\Db::i()->addColumn( 'skin_templates', array(
					'name'			=> 'template_user_edited',
					'type'			=> 'int',
					'length'		=> 10,
					'allow_null'	=> false,
					'default'		=> 0
				) );
			}
	
			\IPS\Db::i()->changeColumn( 'rc_reports', 'report', array(
				'name'			=> 'report',
				'type'			=> 'mediumtext',
				'length'		=> null,
				'allow_null'	=> false
			) );
	
			\IPS\Db::i()->addIndex( 'skin_collections', array(
				'type'			=> 'key',
				'name'			=> 'parent_set_id',
				'columns'		=> array( 'set_parent_id', 'set_id' )
			) );
	
			\IPS\Db::i()->changeColumn( 'forums', 'last_poster_name', array(
				'name'			=> 'last_poster_name',
				'type'			=> 'varchar',
				'length'		=> 255,
				'allow_null'	=> true,
				'default'		=> null,
			) );
	
			\IPS\Db::i()->changeColumn( 'error_logs', 'log_error_code', array(
				'name'			=> 'log_error_code',
				'type'			=> 'varchar',
				'length'		=> 24,
				'allow_null'	=> false,
				'default'		=> '0',
			) );
	
			\IPS\Db::i()->addIndex( 'core_sys_lang', array(
				'type'			=> 'key',
				'name'			=> 'lang_default',
				'columns'		=> array( 'lang_default' )
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
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics CHANGE COLUMN starter_name starter_name VARCHAR(255) NULL DEFAULT NULL,
				CHANGE COLUMN last_poster_name last_poster_name VARCHAR(255) NULL DEFAULT NULL;"
		),
		array(
			'table' => 'tracker',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "tracker DROP INDEX tm_id,
				ADD INDEX tm_id (member_id, topic_id);"
		),
		array(
			'table' => 'forum_tracker',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forum_tracker DROP INDEX fm_id,
				ADD INDEX fm_id (forum_id);"
		)
		) );
		
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