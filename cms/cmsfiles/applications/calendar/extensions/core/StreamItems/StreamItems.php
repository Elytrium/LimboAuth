<?php
/**
 * @brief		Activity stream items extension: StreamItems
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		21 Feb 2017
 */

namespace IPS\calendar\extensions\core\StreamItems;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Activity stream items extension: StreamItems
 */
class _StreamItems
{
	/**
	 * Is there content to display?
	 *
	 * @param	\IPS\Member|NULL	$author		The author to limit extra items to
	 * @param	Timestamp|NULL	$lastTime	If provided, only items since this date are included. If NULL, it works out which to include based on what results are being shown
	 * @param	Timestamp|NULL	$firstTime	If provided, only items before this date are included. If NULL, it works out which to include based on what results are being shown
	 * @return	array Array of \IPS\Content\Search\Result\Custom objects
	 */
	public function extraItems( $author=NULL, $lastTime=NULL, $firstTime=NULL )
	{
		$rsvps = array();

		/* RSVP */
		$where = array( array( 'rsvp_date>? and calendar_event_rsvp.rsvp_response=?', $lastTime, 1 ) );
		if ( $firstTime )
		{
			$where[] = array( 'rsvp_date<?', $firstTime );
		}
		if ( $author )
		{
			$where[] = array( 'calendar_event_rsvp.rsvp_member_id=?', $author->member_id );
		}
		foreach ( \IPS\Db::i()->select( '*', 'calendar_event_rsvp', $where, 'rsvp_date DESC', 10 ) as $rsvp )
		{
			try
			{
				$event = \IPS\calendar\Event::load( $rsvp[ 'rsvp_event_id' ] );
				if( $event->canView() )
				{
					$member = \IPS\Member::load( $rsvp['rsvp_member_id'] );
					$title = htmlspecialchars( $event->title, ENT_DISALLOWED, 'UTF-8', FALSE );
					$rsvps[] = new \IPS\Content\Search\Result\Custom( \IPS\DateTime::ts( $rsvp[ 'rsvp_date' ] ), \IPS\Member::loggedIn()->language()->addToStack( 'calendar_activity_stream_rsvp', FALSE, array( 'htmlsprintf' => array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userLink( $member ), $event->url(), $title ) ) ) );
				}
			}
			catch ( \OutOfRangeException $e )
			{
				/* Event doesn't exist */
			}

		}

		/* Return */
		if ( !empty( $rsvps ) )
		{
			return $rsvps;
		}

		return array();
	}

}