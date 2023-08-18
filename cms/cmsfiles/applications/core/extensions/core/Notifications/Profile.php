<?php
/**
 * @brief		Notification Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Apr 2013
 */

namespace IPS\core\extensions\core\Notifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Notification Options
 */
class _Profile
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
		
		$module = \IPS\Application\Module::get( 'core', 'members', 'front' );
		if ( $module->_enabled and ( $member === NULL or $member->canAccessModule( $module ) ) )
		{
			if( $member == NULL or $member->canAccessModule( \IPS\Application\Module::get( 'core', 'status', 'front' ) ) and \IPS\Settings::i()->profile_comments )
			{
				$return['core_Profile_status'] = array(
					'type'				=> 'standard',
					'notificationTypes'	=> array( 'profile_comment', 'profile_reply', 'new_status' ),
					'title'				=> 'notifications__core_Profile_status',
					'showTitle'			=> TRUE,
					'description'		=> 'notifications__core_Profile_status_desc',
					'default'			=> array( 'inline', 'push' ),
					'disabled'			=> array(),
				);
			}

			$return['core_Profile_follow'] = array(
				'type'				=> 'standard',
				'notificationTypes'	=> array( 'member_follow' ),
				'title'				=> 'notifications__core_Profile_follow',
				'showTitle'			=> TRUE,
				'description'		=> 'notifications__core_Profile_follow_desc',
				'default'			=> array( 'inline', 'push' ),
				'disabled'			=> array(),
			);
		}

		if( \IPS\Settings::i()->ref_on )
		{
			$return['referral'] = array(
				'type'				=> 'standard',
				'notificationTypes'	=> array( 'referral' ),
				'title'				=> 'notifications__referral',
				'showTitle'			=> TRUE,
				'description'		=> 'notifications__referral_desc',
				'default'			=> array( 'inline' ),
				'disabled'			=> array(),
			);
		}

		return $return;
	}
		
	/**
	 * Parse notification: member_follow
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
	public function parse_member_follow( $notification, $htmlEscape=TRUE )
	{
		$member = $notification->item;

		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__member_follow', FALSE, array( ( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( $member->name ) ) ),
			'url'		=> $member->url(),
			'author'	=> $member,
		);
	}
	
	/**
	 * Parse notification for mobile: member_follow
	 *
	 * @param	\IPS\Lang	$language	The language that the notification should be in
	 * @param	\IPS\Member	$member		The member that started following the recipient
	 * @return	array
	 */
	public static function parse_mobile_member_follow( \IPS\Lang $language, \IPS\Member $member )
	{
		return array(
			'title'		=> $language->addToStack( 'notification__member_follow_title' ),
			'body'		=> $language->addToStack( 'notification__member_follow', FALSE, array( 'htmlsprintf' => array( $member->name ) ) ),
			'data'		=> array(
				'url'		=> (string) $member->url(),
				'author	'=> $member
			),
			'channelId'	=> 'your-profile',
		);
	}
	
	/**
	 * Parse notification: profile_comment
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
	public function parse_profile_comment( $notification, $htmlEscape=TRUE )
	{
		$item = $notification->item;

		if ( !$item )
		{
			throw new \OutOfRangeException;
		}
		
		return array(
				'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__profile_comment', FALSE, array( ( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( $item->author()->name ) ) ),
				'url'		=> $item->url(),
				'content'	=> $item->content(),
				'author'	=> \IPS\Member::load( $item->author()->member_id ),
		);
	}
	
	/**
	 * Parse notification for mobile: profile_comment
	 *
	 * @param	\IPS\Lang					$language	The language that the notification should be in
	 * @param	\IPS\core\Statuses\Status		$comment		The thing that was written on the recipient's profile
	 * @return	array
	 */
	public static function parse_mobile_profile_comment( \IPS\Lang $language, \IPS\core\Statuses\Status $comment )
	{		
		return array(
			'body'		=> $language->addToStack( 'notification__profile_comment_title' ),
			'body'		=> $language->addToStack( 'notification__profile_comment', FALSE, array( 'htmlsprintf' => array( $comment->author()->name ) ) ),
			'data'		=> array(
				'url'		=> (string) $comment->url(),
				'author'	=> $comment->author()
			),
			'channelId'	=> 'your-profile',
		);
	}

	/**
	 * Parse notification: new_status
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
	public function parse_new_status( $notification, $htmlEscape=TRUE )
	{
		$item = $notification->item;

		if ( !$item )
		{
			throw new \OutOfRangeException;
		}
		
		return array(
				'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_status', FALSE, array( ( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( $item->author()->name ) ) ),
				'url'		=> $item->url(),
				'content'	=> $item->content(),
				'author'	=> \IPS\Member::load( $item->author()->member_id ),
		);
	}
	
	/**
	 * Parse notification for mobile: new_status
	 *
	 * @param	\IPS\Lang					$language	The language that the notification should be in
	 * @param	\IPS\core\Statuses\Status		$status		The status
	 * @return	array
	 */
	public static function parse_mobile_new_status( \IPS\Lang $language, \IPS\core\Statuses\Status $status )
	{		
		return array(
			'title'		=> $language->addToStack( 'notification__new_status_title' ),
			'body'		=> $language->addToStack( 'notification__new_status', FALSE, array( 'htmlsprintf' => array( $status->author()->name ) ) ),
			'data'		=> array(
				'url'		=> (string) $status->url(),
				'author'	=> $status->author()
			),
			'channelId'	=> 'your-profile',
		);
	}
	
	/**
	 * Parse notification: profile_reply
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
	public function parse_profile_reply( $notification, $htmlEscape=TRUE )
	{
		$item = $notification->item;

		if ( !$item )
		{
			throw new \OutOfRangeException;
		}
		
		return array(
				'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__profile_reply', FALSE, array( ( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( $item->author()->name ) ) ),
				'url'		=> $item->item()->url(),
				'content'	=> $item->content(),
				'author'	=> \IPS\Member::load( $item->author()->member_id ),
		);
	}
	
	/**
	 * Parse notification for mobile: profile_reply
	 *
	 * @param	\IPS\Lang					$language	The language that the notification should be in
	 * @param	\IPS\core\Statuses\Reply		$reply		The status replt
	 * @return	array
	 */
	public static function parse_mobile_profile_reply( \IPS\Lang $language, \IPS\core\Statuses\Reply $reply )
	{		
		return array(
			'title'		=> $language->addToStack( 'notification__profile_reply_title' ),
			'body'		=> $language->addToStack( 'notification__profile_reply', FALSE, array( 'htmlsprintf' => array( $reply->author()->name ) ) ),
			'data'		=> array(
				'url'		=> (string) $reply->item()->url(),
				'author'	=> $reply->author()
			),
			'channelId'	=> 'your-profile',
		);
	}
	
	/**
	 * Parse notification: referral
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
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
	public function parse_referral( $notification )
	{
		$member = $notification->item;

		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__referral', FALSE, array( 'sprintf' => array( $member->name ) ) ),
			'url'		=> $member->url(),
			'author'	=> $member,
		);
	}
}