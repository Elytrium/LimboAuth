<?php
/**
 * @brief		4.4.3 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Converter
 * @since		21 Mar 2019
 */

namespace IPS\convert\setup\upg_104021;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.3 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Update completed members.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* If upgrading from 4.4.0 or earlier we don't need to do anything */
		if( \IPS\Application::load('core')->long_version < 104000 )
		{
			return TRUE;
		}

		/* Get first upgrade date for versions since 4.4.0 */
		$firstUpgradeDate = (int) \IPS\Db::i()->select( 'MIN(upgrade_date)', 'core_upgrade_history', array( 'upgrade_app=? AND upgrade_version_id>=?', 'core', 104000 ) )->first();
		if( !$firstUpgradeDate )
		{
			return TRUE;
		}

		$conversions = \IPS\Db::i()->select( 'COUNT(app_id)', 'convert_apps', array( 'start_date>=?', $firstUpgradeDate ) )->first();
		if( !$conversions )
		{
			return TRUE;
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array(
			array(
				'table' => 'core_members',
				'query' => "UPDATE " . \IPS\Db::i()->prefix . "core_members SET completed=1 WHERE name != '' and name IS NOT NULL and email != '' and email IS NOT NULL"
			)
		) );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'convert', 'extra' => array( '_upgradeStep' => 2 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1ustomTitle()
	{
		return "Adjusting members table";
	}
}