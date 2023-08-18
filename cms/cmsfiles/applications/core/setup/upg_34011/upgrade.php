<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Jun 2014
 */

namespace IPS\core\setup\upg_34011;

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
			try
			{
				$hook	= \IPS\Db::i()->select( '*', 'core_hooks', array( 'hook_key=?', 'othRandomTopic' ) )->first();
	
				\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key IN( 'oth_rand_topics_no_archived_plz', 'oth_rand_topic_fids', 'oth_rand_enabled' )" );
				\IPS\Db::i()->delete( 'core_sys_settings_titles', "conf_title_keyword='othRandomTopic'" );
				\IPS\Settings::i()->clearCache();
			}
			catch( \UnderflowException $e ) {}
	
			if( \IPS\Db::i()->checkForIndex( 'forums', 'last_poster_id' ) )
			{
				\IPS\Db::i()->dropIndex( 'forums', 'last_poster_id' );
			}
	
			\IPS\Db::i()->addIndex( 'forums', array(
				'type'			=> 'key',
				'name'			=> 'last_poster_id',
				'columns'		=> array( 'last_poster_id' )
			) );
		}
		
		$queries	= array();

		if( \IPS\Db::i()->checkForIndex( 'topics', 'last_poster_id' ) )
		{
			$queries[]	= array( 'table' => 'topics', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics DROP INDEX last_poster_id;" );
		}

		$queries[]	= array( 'table' => 'topics', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topics ADD INDEX last_poster_id (last_poster_id);" );

		\IPS\Db::i()->changeColumn( 'core_hooks', 'hook_key', array(
			'name'			=> 'hook_key',
			'type'			=> 'varchar',
			'length'		=> 128,
			'allow_null'	=> true,
			'default'		=> null
		) );

		$queries[]	= array( 'table' => 'core_like', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_like DROP INDEX like_lookup_area;" );
		$queries[]	= array( 'table' => 'core_like', 'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_like ADD INDEX like_lookup_area (like_lookup_area, like_visible, like_added);" );

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );
		
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