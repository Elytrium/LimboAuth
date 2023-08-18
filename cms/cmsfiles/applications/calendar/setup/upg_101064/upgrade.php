<?php
/**
 * @brief		4.1.16 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		30 Sep 2016
 */

namespace IPS\calendar\setup\upg_101064;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.16 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix broken end dates
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* At one point we set the default value for an end date field as 0000-00-00 00:00:00, however more recent versions of MySQL and/or
			STRICT mode does not allow this date, so we changed over to storing NULL instead, which is of course more appropriate. We then
			attempted to fix these dates in an upgrade routine, however even querying with a value of 0000-00-00 00:00:00 is not allowed with STRICT
			mode enabled, so it caused errors. This upgrade routine will now reset the end date as we used to, but catch any exceptions and ignore
			them for people with strict mode enabled. */
		try
		{
			\IPS\Db::i()->update( 'calendar_events', array( 'event_end_date' => NULL ), array( 'event_end_date=?', '0000-00-00 00:00:00' ) );
		}
		catch( \Exception $e ){}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing incorrectly stored event dates";
	}
}