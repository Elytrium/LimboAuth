<?php
/**
 * @brief		Notification Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2021
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
class _Achievements
{
	/**
	 * Get fields for configuration
	 *
	 * @param	\IPS\Member|null	$member		The member (to take out any notification types a given member will never see) or NULL if this is for the ACP
	 * @return	array
	 */
	public static function configurationOptions( \IPS\Member $member = NULL ): array
	{
		// Return an array defining each notification type, including the form elements necessary to allow its configuration
		if ( \IPS\Settings::i()->achievements_enabled )
		{
			return array(
				'core_Achievements' => array(
					'type'				=> 'standard',
					'notificationTypes'	=> array( 'new_rank', 'new_badge', 'new_recognize' ),
					'title'				=> 'notifications__core_Achievements',
					'showTitle'			=> FALSE,
					'description'		=> 'notifications__core_Achievements_desc',
					'default'			=> array( 'inline' ),
					'disabled'			=> array()
				)
			);
		}
		else
		{
			return [];
		}

	}

	/**
	 * Parse notification: new_rank
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @param	bool						$htmlEscape		TRUE to escape HTML in title
	 * @return	array
	 * @code
	 return array(
		 'title'		=> "Mark has replied to A Topic",	// The notification title
		 'url'			=> \IPS\Http\Url::internal( ... ),	// The URL the notification should link to
		 'content'		=> "Lorem ipsum dolar sit",			// [Optional] Any appropriate content. Do not format this like an email where the text
		 													// 	 explains what the notification is about - just include any appropriate content.
		 													// 	 For example, if the notification is about a post, set this as the body of the post.
		 'author'		=>  \IPS\Member::load( 1 ),			// [Optional] The user whose photo should be displayed for this notification
	 );
	 * @endcode
	 */
	public function parse_new_rank( \IPS\Notification\Inline $notification, $htmlEscape=TRUE )
	{
		if ( $notification->extra )
		{
			/* \IPS\Notification->extra will always be an array, but for bulk content notifications we are only storing a single badge ID,
				so we need to grab just the one array entry (the badge ID we stored) */
			$rankId = $notification->extra;

			if( \is_array( $rankId ) )
			{
				$rankId = array_pop( $rankId );
			}

			try
			{
				$rank = \IPS\core\Achievements\Rank::load( $rankId );
				$title = $rank->_title;
			}
			catch( \Exception $e )
			{
				throw new \OutOfRangeException;
			}
		}
		else
		{
			$title = $notification->member->rank['title'];
		}

		return array(
			'title'			=> $notification->member->language()->addToStack( 'notification__new_rank', FALSE, array( 'sprintf' => array( $title ) ) ),
			'url'			=> $notification->member->url(),
			'author'		=> $notification->member
		);
	}
	
	/**
	 * Parse notification for mobile: new_rank
	 *
	 * @param	\IPS\Lang			$language	The language that the notification should be in
	 * @param	\IPS\Member			$member		The member that earned the rank
	 * @return	array
	 */
	public static function parse_mobile_new_rank( \IPS\Lang $language, \IPS\core\Achievements\Rank $rank, \IPS\Member $member )
	{
		return array(
			'body'		=> $language->addToStack( 'notification__new_rank', FALSE, array( 'sprintf' => array( $member->rank['title'] ) ) ),
			'data'		=> array(
				'url'		=> (string) $member->url(),
				'author'	=> $member,
			),
			'channelId'	=> 'achievements',
		);
	}
	
	/**
	 * Parse notification: new_badge
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @param	bool						$htmlEscape		TRUE to escape HTML in title
	 * @return	array
	 * @code
	 return array(
		 'title'		=> "Mark has replied to A Topic",	// The notification title
		 'url'			=> \IPS\Http\Url::internal( ... ),	// The URL the notification should link to
		 'content'		=> "Lorem ipsum dolar sit",			// [Optional] Any appropriate content. Do not format this like an email where the text
		 													// 	 explains what the notification is about - just include any appropriate content.
		 													// 	 For example, if the notification is about a post, set this as the body of the post.
		 'author'		=>  \IPS\Member::load( 1 ),			// [Optional] The user whose photo should be displayed for this notification
	 );
	 * @endcode
	 */
	public function parse_new_badge( \IPS\Notification\Inline $notification, $htmlEscape=TRUE )
	{
		if ( $notification->extra )
		{
			/* \IPS\Notification->extra will always be an array, but for bulk content notifications we are only storing a single badge ID,
				so we need to grab just the one array entry (the badge ID we stored) */
			$badgeId = $notification->extra;

			if( \is_array( $badgeId ) )
			{
				$badgeId = array_pop( $badgeId );
			}

			try
			{
				$badge = \IPS\core\Achievements\Badge::load( $badgeId );
				$content = \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_badge_content', FALSE, [ 'sprintf' => [ $badge->_title ] ] );
				$title = \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_badge', FALSE, [ 'sprintf' => [ $badge->_title ] ] );
			}
			catch( \Exception $e )
			{
				throw new \OutOfRangeException;
			}
		}
		else
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( 'mailsub__core_notification_new_badge' );
			$content = NULL;
		}
		
		return array(
			'title'			=> $title,
			'url'			=> (string) \IPS\Http\Url::internal( "app=core&module=members&controller=profile&do=badges&id=" . $notification->member->member_id, 'front', 'profile_badges', $notification->member->members_seo_name ),
			'content'		=> $content,
			'author'		=> $notification->member
		);
	}
	
	/**
	 * Parse notification for mobile: new_badge
	 *
	 * @param	\IPS\Lang						$language	The language that the notification should be in
	 * @param	\IPS\Member						$member		The member that earned the badge
	 * @param	\IPS\core\Achievements\Badge	$badge		The badge the member earned
	 * @return	array
	 */
	public static function parse_mobile_new_badge( \IPS\Lang $language, \IPS\Member $member, \IPS\core\Achievements\Badge $badge )
	{
		return array(
			'body'		=> $language->addToStack( 'notification__new_badge', FALSE, [ 'sprintf' => [ $badge->_title ] ] ),
			'data'		=> array(
				'url'		=> (string) \IPS\Http\Url::internal( "app=core&module=members&controller=profile&do=badges&id={$member->member_id}", 'front', 'profile_badges', $member->members_seo_name ),
				'author'	=> $member,
			),
			'channelId'	=> 'achievements',
		);
	}
	
	/**
	 * Parse notification: new_badge
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @param	bool						$htmlEscape		TRUE to escape HTML in title
	 * @return	array
	 * @code
	 return array(
		 'title'		=> "Mark has replied to A Topic",	// The notification title
		 'url'			=> \IPS\Http\Url::internal( ... ),	// The URL the notification should link to
		 'content'		=> "Lorem ipsum dolar sit",			// [Optional] Any appropriate content. Do not format this like an email where the text
															 // 	 explains what the notification is about - just include any appropriate content.
															 // 	 For example, if the notification is about a post, set this as the body of the post.
		 'author'		=>  \IPS\Member::load( 1 ),			// [Optional] The user whose photo should be displayed for this notification
	 );
	 * @endcode
	 */
	public function parse_new_recognize( \IPS\Notification\Inline $notification, $htmlEscape=TRUE )
	{
		if ( $notification->extra )
		{
			/* \IPS\Notification->extra will always be an array, but for bulk content notifications we are only storing a single badge ID,
				so we need to grab just the one array entry (the badge ID we stored) */
			$recognizeId = $notification->extra;

			if( \is_array( $recognizeId ) )
			{
				$recognizeId = array_pop( $recognizeId );
			}

			try
			{
				$recognize = \IPS\core\Achievements\Recognize::load( $recognizeId );
				$url = $recognize->content()->url();
			}
			catch( \Exception $e )
			{
				throw new \OutOfRangeException;
			}
		}
		else
		{
			
			$url = NULL;
		}
		
		$content = \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_recognized' );
		$title = \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_recognized' );
		
		return array(
			'title'			=> $title,
			'url'			=> (string) $url,
			'content'		=> $content,
			'author'		=> \IPS\Member::loggedIn()
		);
	}
	
	/**
	 * Parse notification for mobile: new_badge
	 *
	 * @param	\IPS\Lang						$language	The language that the notification should be in
	 * @param	\IPS\Member						$member		The member that earned the badge
	 * @param	\IPS\core\Achievements\Badge	$badge		The badge the member earned
	 * @return	array
	 */
	public static function parse_mobile_new_recognize( \IPS\Lang $language, \IPS\Member $member, \IPS\core\Achievements\Recognize $recognize )
	{
		return array(
			'body'		=> $language->addToStack( 'notification__new_recognized' ),
			'data'		=> array(
				'url'		=> $recognize->content()->url(),
				'author'	=> $member,
			),
			'channelId'	=> 'achievements',
		);
	}
}