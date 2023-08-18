<?php
/**
 * @brief		Member Sync
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		7 Jan 2014
 */

namespace IPS\calendar\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Sync
 */
class _Calendar
{
	/**
	 * Member is merged with another member
	 *
	 * @param	\IPS\Member	$member		Member being kept
	 * @param	\IPS\Member	$member2	Member being removed
	 * @return	void
	 */
	public function onMerge( $member, $member2 )
	{
		\IPS\Db::i()->update( 'calendar_event_rsvp', array( 'rsvp_member_id' => $member->member_id ), array( 'rsvp_member_id=?', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );
		\IPS\Db::i()->update( 'calendar_import_feeds', array( 'feed_member_id' => $member->member_id ), array( 'feed_member_id=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'calendar_events', array( 'event_approved_by' => $member->member_id ), array( 'event_approved_by=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'calendar_event_reminders', array( 'reminder_member_id' => $member->member_id ), array( 'reminder_member_id=?', $member2->member_id ) );
	}
	
	/**
	 * Member is deleted
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onDelete( $member )
	{
		\IPS\Db::i()->delete( 'calendar_event_rsvp', array( 'rsvp_member_id=?', $member->member_id ) );
		\IPS\Db::i()->update( 'calendar_import_feeds', array( 'feed_member_id' => 0 ), array( 'feed_member_id=?', $member->member_id ) );
		\IPS\Db::i()->update( 'calendar_events', array( 'event_approved_by' => 0 ), array( 'event_approved_by=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'calendar_event_reminders', array( 'reminder_member_id=?', $member->member_id ) );
	}
}