<?php
/**
 * @brief		4.1.13 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 May 2016
 */

namespace IPS\core\setup\upg_101034;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.13 Upgrade Code
 */
class _Upgrade
{
	/**
	 * At some point it was possible to save wr_remove NULL which causes problems with 4.x's strict checking
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->update( 'core_members_warn_reasons', array( 'wr_remove' => -1 ), array( 'wr_remove IS NULL' ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing legacy warn reasons";
	}

	/**
	 * Update reports with missing report dates
	 * See commit 865c057c90bc98fbf05f85bd9c76b227b9805a8b
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->update( 'core_rc_index', 'last_updated=first_report_date', "last_updated IS NULL" );
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Fixing missing report center last updated dates";
	}
	
	/**
	 * Remove follows for members that no longer exist
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'core_follow',
			'query' => "DELETE FROM " . \IPS\Db::i()->prefix . "core_follow WHERE follow_member_id NOT IN (SELECT member_id FROM " . \IPS\Db::i()->prefix . "core_members)"
		) ) );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 4 ) ) );

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
	public function step3CustomTitle()
	{
		return "Removing follows for members that no longer exist";
	}
}