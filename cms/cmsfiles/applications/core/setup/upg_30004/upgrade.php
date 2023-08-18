<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_30004;

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
		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_mod_post_unit',
			'type'			=> 'int',
			'length'		=> 5,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_ppd_limit',
			'type'			=> 'int',
			'length'		=> 5,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_ppd_unit',
			'type'			=> 'int',
			'length'		=> 5,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_displayname_unit',
			'type'			=> 'int',
			'length'		=> 5,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_sig_unit',
			'type'			=> 'int',
			'length'		=> 5,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'groups', array(
			'name'			=> 'g_pm_flood_mins',
			'type'			=> 'int',
			'length'		=> 5,
			'unsigned'		=> true,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'banfilters', array(
			'name'			=> 'ban_nocache',
			'type'			=> 'int',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addIndex( 'banfilters', array(
			'type'			=> 'key',
			'name'			=> 'ban_content',
			'columns'		=> array( 'ban_content' ),
			'length'		=> array( 200 )
		) );

		\IPS\Db::i()->addIndex( 'banfilters', array(
			'type'			=> 'key',
			'name'			=> 'ban_nocache',
			'columns'		=> array( 'ban_nocache' )
		) );

		\IPS\Db::i()->update( 'groups', array( 'g_promotion' => '-1&-1' ), array( 'g_access_cp=?', 1 ) );

		\IPS\Db::i()->addColumn( 'faq', array(
			'name'			=> 'app',
			'type'			=> 'varchar',
			'length'		=> 32,
			'allow_null'	=> false,
			'default'		=> 'core'
		) );

		\IPS\Db::i()->changeColumn( 'core_sys_lang', 'lang_short', array(
			'name'			=> 'lang_short',
			'type'			=> 'varchar',
			'length'		=> 18,
			'allow_null'	=> false
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
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members ADD COLUMN members_day_posts VARCHAR(32) NOT NULL DEFAULT '0,0',
				ADD INDEX members_bitoptions (members_bitoptions);"
		),
		array(
			'table' => 'message_topics',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "message_topics ADD INDEX mt_date (mt_date);"
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