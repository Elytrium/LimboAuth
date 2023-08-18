<?php
/**
 * @brief		Notification Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		11 Dec 2014
 */

namespace IPS\downloads\extensions\core\Notifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Notification Options
 */
class _Files
{
	/**
	 * Get fields for configuration
	 *
	 * @param	\IPS\Member|null	$member		The member (to take out any notification types a given member will never see) or NULL if this is for the ACP
	 * @return	array
	 */
	public static function configurationOptions( \IPS\Member $member = NULL ): array
	{
		return array(
			'new_file_version'	=> array(
				'type'				=> 'standard',
				'notificationTypes'	=> array( 'new_file_version' ),
				'title'				=> 'notifications__downloads_Files',
				'showTitle'			=> FALSE,
				'description'		=> 'notifications__downloads_Files_desc',
				'default'			=> array( 'inline', 'push', 'email' ),
				'disabled'			=> array()
			)
		);
	}
	
	/**
	 * Parse notification: new_file_version
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @param	bool						$htmlEscape		TRUE to escape HTML in title
	 * @return	array
	 * @code
	 	return array(
	 		'title'		=> "Mark has replied to A Topic",			// The notification title
	 		'url'		=> \IPS\Http\Url\Friendly::internal( ... ),	// The URL the notification should link to
	 		'content'	=> "Lorem ipsum dolar sit",					// [Optional] Any appropriate content. Do not format this like an email where the text
	 																// explains what the notification is about - just include any appropriate content.
	 																// For example, if the notification is about a post, set this as the body of the post.
	 		'author'	=>  \IPS\Member::load( 1 ),					// [Optional] The user whose photo should be displayed for this notification
	 	);
	 * @endcode
	 */
	public function parse_new_file_version( $notification, $htmlEscape=TRUE )
	{
		$item = $notification->item;
		if ( !$item )
		{
			throw new \OutOfRangeException;
		}
                
		return array(
			'title'		=> ( $item->container()->version_numbers ) ? \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_file_version_with', FALSE, array( 'sprintf' => array( $item->author()->name, $item->version, $item->mapped('title') ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_file_version', FALSE, array( 'sprintf' => array( $item->author()->name, $item->mapped('title') ) ) ),
			'url'		=> $notification->item->url(),
			'content'	=> $notification->item->content(),
			'author'	=> $notification->extra ?: $notification->item->author(),
			'unread'	=> (bool) ( $item->unread() )
		);
	}
	
	/**
	 * Parse notification for mobile: new_file_version
	 *
	 * @param	\IPS\Lang			$language	The language that the notification should be in
	 * @param	\IPS\downloads\File	$file		The file
	 * @return	array
	 */
	public static function parse_mobile_new_file_version( \IPS\Lang $language, \IPS\downloads\File $file )
	{
		return array(
			'title'		=> $language->addToStack( 'notification__new_file_version_title' ),
			'body'		=> $language->addToStack( 'notification__new_file_version', FALSE, array( 'htmlsprintf' => array(
				$file->author()->name,
				$file->mapped('title')
			) ) ),
			'data'		=> array(
				'url'		=> (string) $file->url(),
				'author'	=> $file->author()
			),
			'channelId'	=> 'files',
		);
	}
}