<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Jun 2014
 */

namespace IPS\core\setup\upg_33015;

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
		\IPS\Db::i()->addIndex( 'sessions', array(
			'type'			=> 'key',
			'name'			=> 'ip_address',
			'columns'		=> array( 'ip_address' )
		) );

		\IPS\Db::i()->addIndex( 'twitter_connect', array(
			'type'			=> 'primary',
			'columns'		=> array( 't_key' )
		) );

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
		if ( ! isset( \IPS\Request::i()->run_anyway ) )
		{
			\IPS\Db::i()->changeColumn( 'upgrade_history', 'upgrade_notes', array(
				'name'			=> 'upgrade_notes',
				'type'			=> 'text',
				'allow_null'	=> true,
				'default'		=> null
			) );
	
			\IPS\Db::i()->addIndex( 'members_warn_logs', array(
				'type'			=> 'key',
				'name'			=> 'wl_expire',
				'columns'		=> array( 'wl_expire', 'wl_expire_date', 'wl_date' )
			) );
		}
		
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'core_like',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_like CHANGE COLUMN like_id like_id VARCHAR(32) NOT NULL DEFAULT '',
				CHANGE COLUMN like_lookup_id like_lookup_id VARCHAR(32) NOT NULL DEFAULT '';"
		),
		array(
			'table' => 'core_like',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "core_like SET like_id=MD5( CONCAT( like_app, ';', like_area, ';', like_rel_id, ';', like_member_id  ) ), like_lookup_id=MD5( CONCAT( like_app, ';', like_area, ';', like_rel_id ) );"
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