<?php
/**
 * @brief		Notification Options: Clubs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Deb 2017
 */

namespace IPS\core\extensions\core\Notifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Notification Options: Clubs
 */
class _Clubs
{
	/**
	 * Get fields for configuration
	 *
	 * @param	\IPS\Member|null	$member		The member (to take out any notification types a given member will never see) or NULL if this is for the ACP
	 * @return	array
	 */
	public static function configurationOptions( \IPS\Member $member = NULL ): array
	{
		$return = array();
		if ( \IPS\Settings::i()->clubs and $module = \IPS\Application\Module::get( 'core', 'clubs', 'front' ) and $module->_enabled and ( $member === NULL or $member->canAccessModule( $module ) ) )
		{
			$haveClubsILead = (bool) \IPS\Member\Club::numberOfClubsMemberIsLeaderOf( \IPS\Member::loggedIn() );
			
			if ( !$member or $member->canAccessModule( $module ) )
			{
				$return['core_Clubs_invites'] = array(
					'type'				=> 'standard',
					'notificationTypes'	=> array( 'club_invitation', 'club_response' ),
					'title'				=> 'notifications__core_Clubs_invites',
					'showTitle'			=> $haveClubsILead,
					'description'		=> 'notifications__core_Clubs_invites_desc',
					'default'			=> array( 'inline', 'push' ),
					'disabled'			=> array(),
				);
			}
			
			if ( $haveClubsILead )
			{
				$return['core_Clubs_leader'] = array(
					'type'				=> 'standard',
					'notificationTypes'	=> array( 'club_request', 'club_join' ),
					'title'				=> 'notifications__core_Clubs_leader',
					'showTitle'			=> TRUE,
					'description'		=> 'notifications__core_Clubs_leader_desc',
					'default'			=> array( 'inline', 'push' ),
					'disabled'			=> array(),
				);
			}
		}
				
		return $return;
	}
	
	/**
	 * Parse notification: club_invitation
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @param	bool						$htmlEscape		TRUE to escape HTML in title
	 * @return	array
	 * @code
	 return array(
	 'title'		=> "Mark has replied to A Topic",	// The notification title
	 'url'		=> \IPS\Http\Url::internal( ... ),	// The URL the notification should link to
	 'content'	=> "Lorem ipsum dolar sit",			// [Optional] Any appropriate content. Do not format this like an email where the text
	 // explains what the notification is about - just include any appropriate content.
	 // For example, if the notification is about a post, set this as the body of the post.
	 'author'	=>  \IPS\Member::load( 1 ),			// [Optional] The user whose photo should be displayed for this notification
	 );
	 * @endcode
	 */
	public function parse_club_invitation( $notification, $htmlEscape=TRUE )
	{		
		$club = $notification->item;
		if ( !$club )
		{
			throw new \OutOfRangeException;
		}
		
		$invitedBy = \IPS\Member::load( $notification->extra['invitedBy'] );
		
		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( $invitedBy->member_id ? 'notification__club_invitation_by' : 'notification__club_invitation_generic', FALSE, array(
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( $invitedBy->name, $notification->item->name )
			) ),
			'url'		=> $notification->item->url(),
			'author'	=> $invitedBy,
		);
	}
	
	/**
	 * Parse notification for mobile: club_invitation
	 *
	 * @param	\IPS\Lang			$language	The language that the notification should be in
	 * @param	\IPS\Member\Club		$club		The club
	 * @param	\IPS\Member			$invitedBy	The member that sent the invitation
	 * @return	array
	 */
	public static function parse_mobile_club_invitation( \IPS\Lang $language, \IPS\Member\Club $club, \IPS\Member $invitedBy )
	{
		return array(
			'title'			=> $language->addToStack( 'notification__club_invitation_by_title' ),
			'body'		=> $language->addToStack( $invitedBy->member_id ? 'notification__club_invitation_by' : 'notification__club_invitation_generic', FALSE, array( 'htmlsprintf' => array( $invitedBy->name, $club->name ) ) ),
			'data'		=> array(
				'url'		=> (string) $club->url(),
				'author'	=> $invitedBy,
			),
			'channelId'	=> 'clubs',
		);
	}
	
	/**
	 * Parse notification: club_response
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @param	bool						$htmlEscape		TRUE to escape HTML in title
	 * @return	array
	 * @code
	 return array(
	 'title'		=> "Mark has replied to A Topic",	// The notification title
	 'url'		=> \IPS\Http\Url::internal( ... ),	// The URL the notification should link to
	 'content'	=> "Lorem ipsum dolar sit",			// [Optional] Any appropriate content. Do not format this like an email where the text
	 // explains what the notification is about - just include any appropriate content.
	 // For example, if the notification is about a post, set this as the body of the post.
	 'author'	=>  \IPS\Member::load( 1 ),			// [Optional] The user whose photo should be displayed for this notification
	 );
	 * @endcode
	 */
	public function parse_club_response( $notification, $htmlEscape=TRUE )
	{		
		$club = $notification->item;
		if ( !$club )
		{
			throw new \OutOfRangeException;
		}
		
		$memberStatus = $club->memberStatus( \IPS\Member::loggedIn(), 2 );
				
		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( $memberStatus['status'] === $club::STATUS_DECLINED ? 'notification__club_response_declined' : 'notification__club_response_accepted', FALSE, array(
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( $club->name )
			) ),
			'url'		=> $notification->item->url(),
			'author'	=> \IPS\Member::load( $memberStatus['added_by'] ),
		);
	}
	
	/**
	 * Parse notification for mobile: club_response
	 *
	 * @param	\IPS\Lang			$language	The language that the notification should be in
	 * @param	\IPS\Member\Club		$club		The club
	 * @param	bool					$response	If the request to join was accepted
	 * @return	array
	 */
	public static function parse_mobile_club_response( \IPS\Lang $language, \IPS\Member\Club $club, $response )
	{
		$memberStatus = $club->memberStatus( \IPS\Member::loggedIn(), 2 );

		return array(
			'title'		=> $language->addToStack( "notification__club_response_title" ),
			'body'		=> $language->addToStack( $response ? 'notification__club_response_accepted' : 'notification__club_response_declined', FALSE, array( 'htmlsprintf' => array( $club->name ) ) ),
			'data'		=> array(
				'url'		=> (string) $club->url(),
				'author'	=> \IPS\Member::load( $memberStatus['added_by'] )
			),
			'channelId'	=> 'clubs',
		);
	}
	
	/**
	 * Parse notification: club_request
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @param	bool						$htmlEscape		TRUE to escape HTML in title
	 * @return	array|NULL
	 * @code
	 return array(
	 'title'		=> "Mark has replied to A Topic",	// The notification title
	 'url'		=> \IPS\Http\Url::internal( ... ),	// The URL the notification should link to
	 'content'	=> "Lorem ipsum dolar sit",			// [Optional] Any appropriate content. Do not format this like an email where the text
	 // explains what the notification is about - just include any appropriate content.
	 // For example, if the notification is about a post, set this as the body of the post.
	 'author'	=>  \IPS\Member::load( 1 ),			// [Optional] The user whose photo should be displayed for this notification
	 );
	 * @endcode
	 */
	public function parse_club_request( $notification, $htmlEscape=TRUE )
	{
		$club = $notification->item;
		if ( !\IPS\Settings::i()->clubs or !$club or $club->memberStatus )
		{
			throw new \OutOfRangeException;
		}
		
		$between = time();
		try
		{
			/* Is there a newer notification for this item? */
			$between = \IPS\Db::i()->select( 'sent_time', 'core_notifications', array( '`member`=? AND item_id=? AND item_class=? AND sent_time>? AND notification_key=?', \IPS\Member::loggedIn()->member_id, $club->id, 'IPS\Member\Club', $notification->sent_time->getTimestamp(), $notification->notification_key ) )->first();
		}
		catch( \UnderflowException $e ) {}
		
		$requests = \IPS\Db::i()->select( array( 'member_id', 'joined' ), 'core_clubs_memberships', array( 'club_id=? AND joined>=? AND joined<? AND status=?', $club->id, $notification->sent_time->getTimestamp()-1, $between, \IPS\Member\Club::STATUS_REQUESTED ), 'joined desc', NULL )->setValueField('member_id');
		
		$names	= array();
		$first	= NULL;

		foreach( $requests AS $member )
		{
			if( $first === NULL )
			{
				$first = $member;
			}

			if ( \count( $names ) > 2 )
			{
				$names[] = \IPS\Member::loggedIn()->language()->addToStack( 'x_others', FALSE, array( 'pluralize' => array( \count( $requests ) - 3 ) ) );
				break;
			}
			$names[] = \IPS\Member::load( $member )->name;
		}

		if( $first === NULL )
		{
			throw new \OutOfRangeException;
		}

		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__club_request', FALSE, array(
				'pluralize'									=> array( \count( $requests ) ),
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( \IPS\Member::loggedIn()->language()->formatList( $names ), $club->name )
			) ),
			'url'		=> $club->url()->setQueryString( array( 'do' => 'members', 'filter' => \IPS\Member\Club::STATUS_REQUESTED ) ),
			'author'	=> \IPS\Member::load( $first )
		);
	}
	
	/**
	 * Parse notification for mobile: club_request
	 *
	 * @param	\IPS\Lang			$language	The language that the notification should be in
	 * @param	\IPS\Member\Club	$club		The club
	 * @param	\IPS\Member			$member		The member asking to join
	 * @return	array
	 */
	public static function parse_mobile_club_request( \IPS\Lang $language, \IPS\Member\Club $club, \IPS\Member $member )
	{
		return array(
			'title'		=> $language->addToStack( 'notification__club_request_title' ),
			'body'		=> $language->addToStack( 'notification__club_request', FALSE, array(
				'pluralize'		=> array( 1 ),
				'htmlsprintf'	=> array(
					$language->formatList( array( $member->name ) ),
					$club->name
				)
			) ),
			'data'		=> array(
				'url'		=> (string) $club->url()->setQueryString( array( 'do' => 'members', 'filter' => \IPS\Member\Club::STATUS_REQUESTED ) ),
				'author'	=> $member
			),
			'channelId'	=> 'clubs',
		);
	}
	
	/**
	 * Parse notification: club_join
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @param	bool						$htmlEscape		TRUE to escape HTML in title
	 * @return	array|NULL
	 * @code
	 return array(
	 'title'		=> "Mark has replied to A Topic",	// The notification title
	 'url'		=> \IPS\Http\Url::internal( ... ),	// The URL the notification should link to
	 'content'	=> "Lorem ipsum dolar sit",			// [Optional] Any appropriate content. Do not format this like an email where the text
	 // explains what the notification is about - just include any appropriate content.
	 // For example, if the notification is about a post, set this as the body of the post.
	 'author'	=>  \IPS\Member::load( 1 ),			// [Optional] The user whose photo should be displayed for this notification
	 );
	 * @endcode
	 */
	public function parse_club_join( $notification, $htmlEscape=TRUE )
	{
		$club = $notification->item;
		if ( !\IPS\Settings::i()->clubs or !$club or $club->memberStatus )
		{
			throw new \OutOfRangeException;
		}
		
		$between = time();
		try
		{
			/* Is there a newer notification for this item? */
			$between = \IPS\Db::i()->select( 'sent_time', 'core_notifications', array( '`member`=? AND item_id=? AND item_class=? AND sent_time>? AND notification_key=?', \IPS\Member::loggedIn()->member_id, $club->id, 'IPS\Member\Club', $notification->sent_time->getTimestamp(), $notification->notification_key ) )->first();
		}
		catch( \UnderflowException $e ) {}
		
		$requests = \IPS\Db::i()->select( array( 'member_id', 'joined' ), 'core_clubs_memberships', array( 'club_id=? AND joined>=? AND joined<? AND ( status=? OR status=? )', $club->id, $notification->sent_time->getTimestamp()-1, $between, \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_EXPIRED, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR ), 'joined desc' )->setValueField('member_id');
				
		$names	= array();
		$first	= NULL;

		foreach( $requests AS $member )
		{
			if( $first === NULL )
			{
				$first = $member;
			}

			if ( \count( $names ) > 2 )
			{
				$names[] = \IPS\Member::loggedIn()->language()->addToStack( 'x_others', FALSE, array( 'pluralize' => array( \count( $requests ) - 3 ) ) );
				break;
			}
			$names[] = \IPS\Member::load( $member )->name;
		}
				
		if( $first === NULL )
		{
			throw new \OutOfRangeException;
		}

		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__club_join', FALSE, array(
				'pluralize' 								=> array( \count( $requests ) ),
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( \IPS\Member::loggedIn()->language()->formatList( $names ), $club->name )
			) ),
			'url'		=> $club->url(),
			'author'	=> \IPS\Member::load( $first )
		);
	}
	
	/**
	 * Parse notification for mobile: club_join
	 *
	 * @param	\IPS\Lang			$language	The language that the notification should be in
	 * @param	\IPS\Member\Club	$club		The club
	 * @param	\IPS\Member			$member		The member who joined
	 * @return	array
	 */
	public static function parse_mobile_club_join( \IPS\Lang $language, \IPS\Member\Club $club, \IPS\Member $member )
	{
		return array(
			'title'		=> $language->addToStack( 'notification__club_join_title' ),
			'body'		=> $language->addToStack( 'notification__club_join', FALSE, array(
				'pluralize'		=> array( 1 ),
				'htmlsprintf'	=> array(
					$language->formatList( array( $member->name ) ),
					$club->name
				)
			) ),
			'data'		=> array(
				'url'		=> (string) $club->url(),
				'author'	=> $member
			),
			'channelId'	=> 'clubs',
		);
	}

	/**
	 * Parse notification: unapproved_club
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @param	bool						$htmlEscape		TRUE to escape HTML in title
	 * @return	array|NULL
	 * @code
	return array(
	'title'		=> "Mark has replied to A Topic",	// The notification title
	'url'		=> \IPS\Http\Url::internal( ... ),	// The URL the notification should link to
	'content'	=> "Lorem ipsum dolar sit",			// [Optional] Any appropriate content. Do not format this like an email where the text
	// explains what the notification is about - just include any appropriate content.
	// For example, if the notification is about a post, set this as the body of the post.
	'author'	=>  \IPS\Member::load( 1 ),			// [Optional] The user whose photo should be displayed for this notification
	);
	 * @endcode
	 */
	public function parse_unapproved_club( $notification, $htmlEscape=TRUE )
	{
		$club = $notification->item;
		if ( !\IPS\Settings::i()->clubs or !$club or $club->memberStatus )
		{
			throw new \OutOfRangeException;
		}

		try
		{
			return array(
				'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_club_unapproved', FALSE, array(
					( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( $club->owner->name, $club->name )
				) ),
				'url'		=> $club->url(),
				'author'	=> $club->owner,
			);
		}
		catch( \UnderflowException $ex )
		{
			throw new \OutOfRangeException;
		}
	}
	
	/**
	 * Parse notification for mobile: unapproved_club
	 *
	 * @param	\IPS\Lang			$language	The language that the notification should be in
	 * @param	\IPS\Member\Club	$club		The club
	 * @return	array
	 */
	public static function parse_mobile_unapproved_club( \IPS\Lang $language, \IPS\Member\Club $club )
	{
		return array(
			'title'		=> $language->addToStack( 'notification__new_club_unapproved_title' ),
			'body'		=> $language->addToStack( 'notification__new_club_unapproved', FALSE, array( 'htmlsprintf' => array( $club->owner->name, $club->name ) ) ),
			'data'		=> array(
				'url'		=> (string) $club->url(),
				'author'	=> $club->owner
			),
			'channelId'	=> 'clubs',
		);
	}
}