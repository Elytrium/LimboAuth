<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_30010;

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
		\IPS\Db::i()->addIndex( 'admin_logs', array(
			'type'			=> 'key',
			'name'			=> 'ip_address',
			'columns'		=> array( 'ip_address' )
		) );

		\IPS\Db::i()->addIndex( 'dnames_change', array(
			'type'			=> 'key',
			'name'			=> 'dname_ip_address',
			'columns'		=> array( 'dname_ip_address' )
		) );

		\IPS\Db::i()->addIndex( 'error_logs', array(
			'type'			=> 'key',
			'name'			=> 'log_ip_address',
			'columns'		=> array( 'log_ip_address' )
		) );

		\IPS\Db::i()->addIndex( 'moderator_logs', array(
			'type'			=> 'key',
			'name'			=> 'ip_address',
			'columns'		=> array( 'ip_address' )
		) );

		\IPS\Db::i()->addIndex( 'validating', array(
			'type'			=> 'key',
			'name'			=> 'ip_address',
			'columns'		=> array( 'ip_address' )
		) );

		\IPS\Db::i()->addIndex( 'sessions', array(
			'type'			=> 'key',
			'name'			=> 'member_id',
			'columns'		=> array( 'member_id' )
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
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members ADD INDEX ip_address (ip_address),
				ADD COLUMN live_id VARCHAR(32) NULL DEFAULT NULL;"
		),
		array(
			'table' => 'message_posts',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "message_posts ADD INDEX msg_ip_address (msg_ip_address);"
		),
		array(
			'table' => 'profile_comments',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "profile_comments ADD INDEX comment_ip_address (comment_ip_address);"
		),
		array(
			'table' => 'topic_ratings',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "topic_ratings ADD INDEX rating_ip_address (rating_ip_address);"
		),
		array(
			'table' => 'voters',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "voters ADD INDEX ip_address (ip_address);"
		),
		array(
			'table' => 'profile_portal',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "profile_portal ADD INDEX pp_status (pp_status(128),pp_status_update);"
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