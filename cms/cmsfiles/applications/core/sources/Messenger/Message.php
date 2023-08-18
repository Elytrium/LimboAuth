<?php
/**
 * @brief		Personal Conversation Message Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8 Jul 2013
 */

namespace IPS\core\Messenger;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Personal Conversation Message
 */
class _Message extends \IPS\Content\Comment
{
	use \IPS\Content\Reportable;
	
	/**
	 * @brief	Application
	 */
	public static $application = 'core';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_message_posts';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'msg_';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[Content\Comment]	Title
	 */
	public static $title = 'personal_conversation_message';
	
	/**
	 * @brief	[Content\Comment]	Icon
	 */
	public static $icon = 'envelope';
	
	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = 'IPS\core\Messenger\Conversation';
	
	/**
	 * @brief	[Content\Comment]	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'		=> 'topic_id',
		'date'		=> 'date',
		'content'	=> 'post',
		'author'	=> 'author_id',
		'ip_address'=> 'ip_address',
		'first'		=> 'is_first_post'
	);
	
	/**
	 * @brief	[Content\Comment]	Language prefix for forms
	 */
	public static $formLangPrefix = 'messenger_';
	
	/**
	 * @brief	[Content\Comment]	The ignore type
	 */
	public static $ignoreType = 'messages';
	
	/**
	 * Should this comment be ignored?
	 * Override so that the person who starts the conversation sees all messages - if you send a
	 * message to someone, you're always going to want to be able to see their replies.
	 *
	 * @param	\IPS\Member|null	$member	The member to check for - NULL for currently logged in member
	 * @return	bool
	 */
	public function isIgnored( $member=NULL )
	{
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}
		
		if ( $this->item()->author()->member_id == $member->member_id )
		{
			return FALSE;
		}
		
		return parent::isIgnored( $member );
	}

	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return FALSE;
	}

	/**
	 * Can promote this comment/item?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	boolean
	 */
	public function canPromoteToSocialMedia( $member=NULL )
	{
		return FALSE;
	}
	
	/**
	 * Create comment
	 *
	 * @param	\IPS\Content\Item		$item				The content item just created
	 * @param	string					$comment			The comment
	 * @param	bool					$first				Is the first comment?
	 * @param	string					$guestName			If author is a guest, the name to use
	 * @param	bool|NULL				$incrementPostCount	Increment post count? If NULL, will use static::incrementPostCount()
	 * @param	\IPS\Member|NULL		$member				The author of this comment. If NULL, uses currently logged in member.
	 * @param	\IPS\DateTime|NULL		$time				The time
	 * @param	string|NULL				$ipAddress			The IP address or NULL to detect automatically
	 * @param	int|NULL				$hiddenStatus		NULL to set automatically or override: 0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @param	int|NULL				$anonymous			NULL for no value, 0 or 1 for a value (0=no, 1=yes)
	 * @return	static
	 */
	public static function create( $item, $comment, $first=FALSE, $guestName=NULL, $incrementPostCount=NULL, $member=NULL, \IPS\DateTime $time=NULL, $ipAddress=NULL, $hiddenStatus=NULL, $anonymous=NULL )
	{
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}
		
		$comment = parent::create( $item, $comment, $first, $guestName, $incrementPostCount, $member, $time, $ipAddress, $hiddenStatus, $anonymous );
		
		/* Mark unread for this person */
		\IPS\Db::i()->update( 'core_message_topic_user_map', array( 'map_has_unread' => FALSE, 'map_read_time' => time() ), array( 'map_user_id=? AND map_topic_id=?', $member->member_id, $item->id ) );
		
		return $comment;
	}
	
	/**
	 * Send notifications
	 *
	 * @return	void
	 */
	public function sendNotifications()
	{
		/* Update topic maps for other participants */
		\IPS\Db::i()->update( 'core_message_topic_user_map', array( 'map_has_unread' => 1, 'map_last_topic_reply' => time() ), array( 'map_topic_id=? AND map_user_id!=?', $this->item()->id, $this->author()->member_id ) );
		
		/* Update topic map for this author */
		\IPS\Db::i()->update( 'core_message_topic_user_map', array( 'map_has_unread' => 0, 'map_last_topic_reply' => time(), 'map_read_time' => time() ), array( 'map_topic_id=? AND map_user_id=?', $this->item()->id, $this->author()->member_id ) );
			
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'new_private_message', $this->item(), array( $this ) );
		$messengerModule = \IPS\Application\Module::get( 'core', 'messaging', 'front' );

		foreach ( $this->item()->maps() as $map )
		{
			if ( $map['map_user_id'] !== $this->author()->member_id and $map['map_user_active'] and !$map['map_ignore_notification'] )
			{
				$member = \IPS\Member::load( $map['map_user_id'] );
				/* skip this for members which can't or don't want to use the messenger or for deleted users */
				if ( $member->members_disable_pm == 2 or !$member->canAccessModule( $messengerModule ) or !$member->member_id )
				{
					continue;
				}

				\IPS\core\Messenger\Conversation::rebuildMessageCounts( $member );
				
				$notification->recipients->attach( $member );
				
				if ( $member->members_bitoptions['show_pm_popup'] )
				{
					$member->msg_show_notification = TRUE;
					$member->save();
				}
			}
		}

		$notification->send();
	}
	
	/**
	 * Move Comment to another item
	 *
	 * @param	\IPS\Content\Item	$item	The item to move this comment to
	 * @param	bool				$skip	Skip rebuilding new/old content item data (used for multimod where we can do it in one go after)
	 * @return	void
	 */
	public function move( \IPS\Content\Item $item, $skip=FALSE )
	{
		/* Make sure all active participants in the old conversation are in the new one */
		$activeParticipants = array_keys( array_filter( $this->item()->maps( TRUE ), function ( $map ) {
			return $map['map_user_active'];
		} ) );
		
		$item->authorize( $activeParticipants );
		
		parent::move( $item );
	}
}