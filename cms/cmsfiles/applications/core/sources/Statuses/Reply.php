<?php
/**
 * @brief		Status Update Reply Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Feb 2014
 */

namespace IPS\core\Statuses;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Status Update Reply
 */
class _Reply extends \IPS\Content\Comment implements \IPS\Content\Hideable
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable;
	
	/**
	 * @brief	Application
	 */
	public static $application = 'core';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_member_status_replies';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'reply_';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[Content\Comment]	Title
	 */
	public static $title = 'status_reply';
	
	/**
	 * @brief	[Content\Comment]	Icon
	 */
	public static $icon = 'comment-o';
	
	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = 'IPS\core\Statuses\Status';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[Content\Comment]	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'			=> 'status_id',
		'date'			=> 'date',
		'content'		=> 'content',
		'author'		=> 'member_id',
		'approved'		=> 'approved',
		'ip_address'	=> 'ip_address',
	);

	/**
	 * @brief	[Content\Comment]	Language prefix for forms
	 */
	public static $formLangPrefix = 'status_';
	
	/**
	 * @brief	[Content\Comment]	Comment Template
	 */
	public static $commentTemplate = array( array( 'statuses', 'core', 'front' ), 'statusReplyContainer' );
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'status_reply';
	
	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();

	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action='find' )
	{
		/* Status Posts and comments don't support get PrefComment */
		if ( $action === 'find' or $action === 'getPrefComment' )
		{
			return $this->item()->url();
		}
		
		$_key	= md5( $action );

		if( !isset( $this->_url[ $_key ] ) )
		{
			$member = \IPS\Member::load( $this->item()->member_id );
			$this->_url[ $_key ] = \IPS\Http\Url::internal( "app=core&module=members&controller=profile&id={$member->member_id}&status={$this->item()->id}&reply={$this->id}", 'front', 'profile', array( $member->members_seo_name ) )->setQueryString( 'type', 'status'  );
		
			if ( $action )
			{
				$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( array( 'do' => $action, 'type' => 'reply' ) );
			}
		}
			
		return $this->_url[ $_key ];
	}

	/**
	 * Send notifications
	 *
	 * @return	void
	 */
	public function sendNotifications()
	{
		$sentTo = parent::sendNotifications();
		
		/* Notify when somebody replies to status updates I am connected to */
		$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'profile_reply', $this, array( $this ) );
		
		foreach ( 
			\IPS\Db::i()->select( 
				'core_members.*', 'core_member_status_replies',
				array( 'reply_status_id=? and reply_member_id !=?', $this->item()->id, $this->author()->member_id ) 
			)->join(
				'core_members',
				'core_members.member_id=core_member_status_replies.reply_member_id'
			) as $member
		)
		{
			$notification->recipients->attach( \IPS\Member::constructFromData( $member ) );
		}
		
		if( $this->author()->member_id != $this->item()->author()->member_id )
		{
			$notification->recipients->attach( $this->item()->author() );
		}
		
		if( $this->author()->member_id != $this->item()->member_id )
		{
			$notification->recipients->attach( \IPS\Member::load( $this->item()->member_id ) );
		}
		
		$notification->send( $sentTo );
	}
	
	/**
	 * Can delete?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canDelete( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
	
		/* Can we delete our own status updates? */
		if ( $this->item()->canDelete() )
		{
			return TRUE;
		}
	
		return parent::canDelete( $member );
	}

	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'statuses', 'core', 'front' ), 'statusReplyContentRows' );
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
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'status_reply_id';
	}

	/**
	 * Check Moderator Permission
	 *
	 * @param	string						$type		'edit', 'hide', 'unhide', 'delete', etc.
	 * @param	\IPS\Member|NULL			$member		The member to check for or NULL for the currently logged in member
	 * @param	\IPS\Node\Model|NULL		$container	The container
	 * @return	bool
	 */
	public static function modPermission( $type, \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return \IPS\core\Statuses\Status::modPermission( $type, $member, $container );
	}
}