<?php
/**
 * @brief		4.0.12 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		21 Jul 2015
 */

namespace IPS\calendar\setup\upg_100042;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.12 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix all day calendar events
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 500;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		/* We need all day events where the time is not already 00:00 - these are the ones that are time zone adjusted and need to be fixed */
		foreach( \IPS\Db::i()->select( '*', 'calendar_events', array( "event_all_day=? AND DATE_FORMAT(event_start_date,'%H:%i') != ?", 1, '00:00' ), 'event_id ASC', array( $limit, $perCycle ) ) as $event )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$update = array();

			/* We need to figure out the submitter's time zone offset so we can put the event back to where it belongs */
			if( $event['event_member_id'] )
			{
				$member 	= \IPS\Member::load( $event['event_member_id'] );
				$startDate	= new \IPS\DateTime( $event['event_start_date'], $member->timezone ? new \DateTimeZone( $member->timezone ) : NULL );
			}
			else
			{
				$startDate	= new \IPS\DateTime( $event['event_start_date'], NULL );
			}

			/* Then move it back to UTC */
			$startDate->setTimezone( new \DateTimeZone('UTC') );

			/* And then return our datetime */
			$update['event_start_date']	= $startDate->format( 'Y-m-d 00:00:00' );

			/* Do the same if we have an end date */
			if( $event['event_end_date'] )
			{
				/* We need to figure out the submitter's time zone offset so we can put the event back to where it belongs */
				if( $event['event_member_id'] )
				{
					$member 	= \IPS\Member::load( $event['event_member_id'] );
					$endDate	= new \IPS\DateTime( $event['event_end_date'], $member->timezone ? new \DateTimeZone( $member->timezone ) : NULL );
				}
				else
				{
					$endDate	= new \IPS\DateTime( $event['event_end_date'], NULL );
				}

				/* Then move it back to UTC */
				$endDate->setTimezone( new \DateTimeZone('UTC') );

				/* And then return our datetime */
				$update['event_end_date']	= $endDate->format( 'Y-m-d 00:00:00' );
			}

			\IPS\Db::i()->update( 'calendar_events', $update, array( 'event_id=?', $event['event_id'] ) );
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( $_SESSION['_step1Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step1Count'] ) )
		{
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'calendar_events', array( "event_all_day=? AND DATE_FORMAT(event_start_date,'%H:%i') != ?", 1, '00:00' ) )->first();
		}

		return "Fixing all day calendar events (Updated so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}
}