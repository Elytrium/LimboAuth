<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Jun 2014
 */

namespace IPS\core\setup\upg_34009;

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
			\IPS\Db::i()->changeColumn( 'seo_meta', 'url', array(
				'name'			=> 'url',
				'type'			=> 'text',
				'allow_null'	=> true,
				'default'		=> null
			) );
		}
		
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'members',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "members SET members_bitoptions=members_bitoptions | 16384 WHERE member_group_id IN( SELECT g_id FROM " . \IPS\Db::i()->prefix . "groups WHERE g_dohtml=1 );"
		) ) );
		
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