<?php
/**
 * @brief		ACP Notification: New Registration Complete
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Jul 2018
 */

namespace IPS\core\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification: New Registration Complete
 */
class _NewRegComplete extends \IPS\core\AdminNotification
{	
	/**
	 * @brief	Identifier for what to group this notification type with on the settings form
	 */
	public static $group = 'members';
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this group compared to others
	 */
	public static $groupPriority = 3;
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this notification type compared to others in the same group
	 */
	public static $itemPriority = 4;
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_NewRegComplete';
	}
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		return $member->hasAcpRestriction( 'core', 'members' );
	}
	
	/**
	 * Is this type of notification ever optional (controls if it will be selectable as "viewable" in settings)
	 *
	 * @return	string
	 */
	public static function mayBeOptional()
	{
		return TRUE;
	}
	
	/**
	 * The default value for if this notification shows in the notification center
	 *
	 * @return	bool
	 */
	public static function defaultValue()
	{
		return FALSE;
	}
	
	/**
	 * Is this type of notification might recur (controls what options will be available for the email setting)
	 *
	 * @return	bool
	 */
	public static function mayRecur()
	{
		return FALSE;
	}
	
	/**
	 * WHERE clause to use against core_acp_notifications_preferences for fetching members to email
	 *
	 * @param	mixed		$extraForEmail		Any additional information specific to this instance which is used for the email but not saved
	 * @return	bool
	 */
	public function emailWhereClause( $extraForEmail )
	{
		/* Most notifications only send one email until the admin has "dealt" with it, but since this
			type of notification cannot be "dealt" with, we need to send an email every time rather
			than just the first time this notification occurs. */
		return array( "email='once'" );
	}
		
	/**
	 * Get the date/time that we need to use for the cutoff
	 *
	 * @return	\IPS\DateTime|NULL
	 */
	public function cutoff()
	{
		try
		{
			return \IPS\DateTime::ts( \IPS\Db::i()->select( 'time', 'core_acp_notifcations_dismissals', array( 'notification=? AND `member`=?', $this->id, \IPS\Member::loggedIn()->member_id ) )->first() );
		}
		catch ( \UnderflowException $e )
		{
			return NULL;
		}
	}
		
	/**
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'acp_notification_NewRegComplete_title' );
	}
	
	/**
	 * Notification Subtitle (no HTML)
	 *
	 * @return	string
	 */
	public function subtitle()
	{
		if ( $cutoff = $this->cutoff() )
		{
			try
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'member_joined_latest', FALSE, array( 'sprintf' => array( \IPS\DateTime::ts( \IPS\Db::i()->select( 'joined', 'core_members', array( 'joined>?', $cutoff->getTimestamp() ), 'joined desc' )->first() )->relative( \IPS\DateTime::RELATIVE_FORMAT_LOWER ) ) ) );
			}
			catch ( \UnderflowException $e )
			{
				return NULL;
			}
		}
		else
		{
			return NULL;
		}
	}
	
	/**
	 * Notification Body (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function body()
	{
		$limit = 12;
		$users = array();
		
		$where = array( array( 'email<>?', '' ) );
		if ( $cutoff = $this->cutoff() )
		{
			$where[] = array( 'joined>?', $cutoff->getTimestamp() );
		}	
		$more = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where )->first() - $limit + 1;	
		
		foreach (
			\IPS\Db::i()->select(
				'*',
				'core_members',
				$where,
				'joined desc',
				array( 0, ( $more === 1 ) ? $limit : ( $limit - 1 ) )
			) as $user
		)
		{
			$users[ $user['member_id'] ] = \IPS\Member::constructFromData( $user );
		}
				
		if ( \count( $users ) )
		{
			return \IPS\Theme::i()->getTemplate( 'notifications', 'core', 'admin' )->newMember( $users, $this, $more );
		}
		else
		{
			return '';
		}
	}
	
	/**
	 * Severity
	 *
	 * @return	string
	 */
	public function severity()
	{
		return static::SEVERITY_OPTIONAL;
	}
	
	/**
	 * Dismissible?
	 *
	 * @return	string
	 */
	public function dismissible()
	{
		return static::DISMISSIBLE_UNTIL_RECUR;
	}
}