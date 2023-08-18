<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		24 Oct 2014
 */

namespace IPS\calendar\setup\upg_32000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Conversion from 3.0
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( 
			array(
				'table' => 'cal_events_bak',
				'query' => "INSERT INTO " . \IPS\Db::i()->prefix . "cal_events SELECT event_id, event_calendar_id, event_member_id, event_content, event_title, event_smilies, 0, 0, event_perms, event_private, event_approved, event_unixstamp, event_unixstamp,
					event_recurring, FROM_UNIXTIME(event_unix_from), CASE WHEN event_unix_to > 0 THEN FROM_UNIXTIME(event_unix_to) ELSE NULL END, '', 0, 0, 0, 0, MD5( CONCAT( event_id, event_title ) ), 0, 1 FROM " . \IPS\Db::i()->prefix . "cal_events_bak"
			),
			array(
				'table' => 'cal_events_bak',
				'query' => "DROP TABLE " . \IPS\Db::i()->prefix . "cal_events_bak"
			)
		) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'calendar', 'extra' => array( '_upgradeStep' => 2 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}
}