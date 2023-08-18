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
class _Content
{
	/**
	 * Get fields for configuration
	 *
	 * @param	\IPS\Member|null	$member		The member (to take out any notification types a given member will never see) or NULL if this is for the ACP
	 * @return	array
	 */
	public static function configurationOptions( \IPS\Member $member = NULL ): array
	{
		$autoFollow	= array();
		if ( $member )
		{
			if( $member->auto_follow['content'] )
			{
				$autoFollow[]	= 'content';
			}
			if( $member->auto_follow['comments'] )
			{
				$autoFollow[]	= 'comments';
			}
			
			$autoFollowField = new \IPS\Helpers\Form\CheckboxSet( 'auto_track', $autoFollow, FALSE, array( 'options' => array( 'content' => 'auto_track_content', 'comments' => 'auto_track_comments' ), 'multiple' => TRUE, 'showAllNone' => FALSE ) );
		}
		else
		{
			if ( \IPS\Settings::i()->auto_follow_new_content )
			{
				$autoFollow[]	= 'content';
			}
			if( \IPS\Settings::i()->auto_follow_replied_to )
			{
				$autoFollow[]	= 'comments';
			}
			
			$autoFollowField = new \IPS\Helpers\Form\CheckboxSet( 'auto_follow_defaults', $autoFollow, FALSE, array( 'options' => array( 'content' => 'auto_follow_new_content', 'comments' => 'auto_follow_replied_to' ), 'multiple' => TRUE, 'showAllNone' => FALSE ) );
		}
				
		return array(
			'auto_track'		=> array(
				'type'				=> 'custom',
				'adminCanSetDefault'=> TRUE,
				'field'				=> $autoFollowField,
				'admin_lang'		=> array(
					'header'			=> 'auto_track',
					'title'				=> 'auto_follow_defaults',
				),
			),
			'auto_track_type'	=> array(
				'type'				=> 'custom',
				'adminCanSetDefault'=> FALSE,
				'field'				=> new \IPS\Helpers\Form\Radio( 'auto_track_type', ( $member and $member->auto_follow['method'] ) ? $member->auto_follow['method'] : 'immediate', FALSE, array( 'options' => array(
					'immediate'			=> \IPS\Member::loggedIn()->language()->addToStack('follow_type_immediate'),
					'daily'				=> \IPS\Member::loggedIn()->language()->addToStack('follow_type_daily'),
					'weekly'			=> \IPS\Member::loggedIn()->language()->addToStack('follow_type_weekly'),
					'none'				=> \IPS\Member::loggedIn()->language()->addToStack('follow_type_none')
				) ), NULL, NULL, NULL, 'auto_track_type' )
			),
			'separator1'	=> array(
				'type'				=> 'separator',
			),
			'content'			=> array(
				'type'				=> 'standard',
				'notificationTypes'	=> array( 'new_content', 'new_comment', 'new_review' ),
				'title'				=> 'notifications__core_Content_content',
				'showTitle'			=> TRUE,
				'description'		=> 'notifications__core_Content_content_desc',
				'adminDescription'	=> 'notifications__core_Content_content_adminDdesc',
				'default'			=> array( 'inline', 'push' ),
				'disabled'			=> array()
			),
			'members'			=> array(
				'type'				=> 'standard',
				'notificationTypes'	=> array( 'follower_content' ),
				'title'				=> 'notifications__core_Content_members',
				'showTitle'			=> TRUE,
				'description'		=> 'notifications__core_Content_members_desc',
				'default'			=> array(),
				'disabled'			=> array()
			),
			'separator2'	=> array(
				'type'				=> 'separator',
			),
			'email_notifications_once' => array(
				'type'				=> 'custom',
				'adminCanSetDefault'=> TRUE,
				'field'				=> $member ? new \IPS\Helpers\Form\Checkbox( 'email_notifications_once', $member and $member->members_bitoptions['email_notifications_once'] ) : new \IPS\Helpers\Form\Radio( 'notification_prefs_one_per_view', \IPS\Settings::i()->notification_prefs_one_per_view ? 'default' : 'optional', FALSE, array(
					'options'			=> array(
						'default'			=> 'admin_notification_pref_default',
						'optional'			=> 'admin_notification_pref_optional',
					)
				) ),
				'admin_lang'		=> array(
					'header'			=> 'notification_prefs_one_per_view_header'
				)
			)
		);
	}
	
	/**
	 * Save "extra" value
	 *
	 * @param	\IPS\Member|NULL	$member	The member or NULL if this is the admin setting defaults
	 * @param	string				$key	The key
	 * @param	bool				$value	The value
	 * @return	void
	 */
	public static function saveExtra( ?\IPS\Member $member, $key, $value )
	{		
		switch ( $key )
		{
			case 'auto_track':
				if ( $member )
				{
					$autoTrack = $member->auto_track ? json_decode( $member->auto_track, TRUE ) : array();
					foreach ( array( 'content', 'comments' ) as $k )
					{
						$autoTrack[ $k ] = \in_array( $k, $value );
					}				
					$member->auto_track = json_encode( $autoTrack );
				}
				else
				{
					\IPS\Settings::i()->changeValues( array(
						'auto_follow_new_content'	=> \in_array( 'content', $value ),
						'auto_follow_replied_to'	=> \in_array( 'comments', $value ),
					) );
				}
				break;
				
			case 'auto_track_type':
				$autoTrack = $member->auto_track ? json_decode( $member->auto_track, TRUE ) : array();
				$autoTrack['method'] = $value;
				$member->auto_track = json_encode( $autoTrack );
				break;
				
			case 'email_notifications_once':
				if ( $member )
				{
					$member->members_bitoptions['email_notifications_once'] = $value;
				}
				else
				{
					\IPS\Settings::i()->changeValues( array(
						'notification_prefs_one_per_view'	=> ( $value === 'default' )
					) );
				}
				break;
		}
	}
	
	/**
	 * Disable all "extra" values for a particular type
	 *
	 * @param	\IPS\Member|NULL	$member	The member or NULL if this is the admin setting defaults
	 * @param	string				$method	The method type
	 * @return	void
	 */
	public static function disableExtra( ?\IPS\Member $member, $method )
	{
		/* If we are disabling all emails, set any digest follows to be "no notification" */
		if ( $method === 'email' )
		{
			\IPS\Db::i()->update( 'core_follow', [
				'follow_added'			=> time(),
				'follow_notify_do'		=> 0,
				'follow_notify_freq' 	=> 'none',
			], [
				[ 'follow_member_id=?', $member->member_id ],
				[ "( follow_notify_freq='daily' OR follow_notify_freq='weekly' )" ],
			] );
		}
	}
	
	/**
	 * Reset "extra" value to the default for all accounts
	 *
	 * @return	void
	 */
	public static function resetExtra()
	{
		\IPS\Db::i()->update( 'core_members', array( 'auto_track' => json_encode( array(
			'content'	=> \IPS\Settings::i()->auto_follow_new_content ? 1 : 0,
			'comments'	=> \IPS\Settings::i()->auto_follow_replied_to ? 1 : 0,
			'method'	=> 'immediate'
		) ) ) );
		
		\IPS\Db::i()->update( 'core_members', 'members_bitoptions2 = members_bitoptions2 ' . ( \IPS\Settings::i()->notification_prefs_one_per_view ? '|' : '&~' ) . \IPS\Member::$bitOptions['members_bitoptions']['members_bitoptions2']['email_notifications_once'] );
	}
	
	/**
	 * Parse notification: new_content
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
	public function parse_new_content( $notification, $htmlEscape=TRUE )
	{
		$item = $notification->item;
		if ( !$item )
		{
			throw new \OutOfRangeException;
		}

		/* If the content item is queued for deletion, add the query string parameter so we can see it */
		$url = $notification->item->url();

		if( $notification->item->hidden() == -2 )
		{
			$url = $url->setQueryString( 'showDeleted', 1 );
		}

		$name = ( $item->isAnonymous() ) ? \IPS\Member::loggedIn()->language()->addToStack( 'post_anonymously_placename' ) : $item->author()->name;
		
		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_content', FALSE, array(
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array(
					$name,
					mb_strtolower( $item->indefiniteArticle() ), 
					$item->searchIndexContainerClass()->getTitleForLanguage( \IPS\Member::loggedIn()->language(), $htmlEscape ? array( 'escape' => TRUE ) : array() ),
					$item->mapped('title')
				)
			) ),
			'url'		=> $url,
			'content'	=> $notification->item->content(),
			'author'	=> $notification->item->author(),
			'unread'	=> (bool) ( $item->unread() )
		);
	}
	
	/**
	 * Parse notification for mobile: new_content
	 *
	 * @param	\IPS\Lang			$language	The language that the notification should be in
	 * @param	\IPS\Content\Item	$item		The item that was posted
	 * @return	array
	 */
	public static function parse_mobile_new_content( \IPS\Lang $language, \IPS\Content\Item $item )
	{
		$name = ( $item->isAnonymous() ) ? \IPS\Member::loggedIn()->language()->addToStack( 'post_anonymously_placename' ) : $item->author()->name;
		$container = $item->containerWrapper();
		$containerId = $container ? $container->_id : "-"; // This is used to generate the tag. Use ID if we have one, otherwise just a dash

		return array(
			'title'		=> $language->addToStack( 'notification__new_content_title', FALSE, array( 'htmlsprintf' => array(
				mb_strtolower( $item->definiteArticle( $language ) ),
			) ) ),
			'body'		=> $language->addToStack( 'notification__new_content', FALSE, array( 'htmlsprintf' => array(
				$name,
				mb_strtolower( $item->indefiniteArticle( $language ) ),
				$container ? 
					$language->addToStack( 'notification__container', FALSE, array( 'sprintf' => array( $item->searchIndexContainerClass()->getTitleForLanguage( $language ) ) ) ) 
					: "",
				$item->mapped('title')
			) ) ),
			'data'		=> array(
				'url'		=> (string) $item->url(),
				'author'	=> $item->author(),
				'grouped'	=> $language->addToStack( 'notification__new_content_grouped', FALSE, array(
					'htmlsprintf'	=> array(
						$item->definiteArticle( $language, TRUE ),
						$container ? 
							$language->addToStack( 'notification__container', FALSE, array( 'sprintf' => array( $item->searchIndexContainerClass()->getTitleForLanguage( $language ) ) ) ) 
							: "",
					)
				) ),
				'groupedTitle' => $language->addToStack( 'notification__new_content_grouped_title', FALSE, array( 'htmlsprintf' => array(
					$item->definiteArticle( $language, TRUE ),
				) ) ),
				'groupedUrl' => $container ? $container->url() : NULL
			),
			'tag' => md5( 'newcontent' . \get_class( $item ) . $containerId ), // Group new item notifications by container ID (if available)
			'channelId'	=> 'followed',
		);
	}
	
	/**
	 * Parse notification: new_content_bulk
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
	public function parse_new_content_bulk( $notification, $htmlEscape=TRUE )
	{
		$node = $notification->item;
		
		if ( !$node )
		{
			throw new \OutOfRangeException;
		}
				
		if ( $notification->extra )
		{
			/* \IPS\Notification->extra will always be an array, but for bulk content notifications we are only storing a single member ID,
				so we need to grab just the one array entry (the member ID we stored) */
			$memberId = $notification->extra;

			if( \is_array( $memberId ) )
			{
				$memberId = array_pop( $memberId );
			}

			$author = \IPS\Member::load( $memberId );
		}
		else
		{
			$author = new \IPS\Member;
		}
		
		$contentClass = $node::$contentItemClass;
		
		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_content_bulk', FALSE, array( ( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( $author->name, \IPS\Member::loggedIn()->language()->get( $contentClass::$title . '_pl_lc' ), $node->_title ) ) ),
			'url'		=> $node->url(),
			'author'	=> $author
		);
	}
	
	/**
	 * Parse notification for mobile: new_content_bulk
	 *
	 * @param	\IPS\Lang			$language		The language that the notification should be in
	 * @param	\IPS\Node\Model		$node			The node with the new content
	 * @param	\IPS\Member			$author			The author
	 * @param	string				$contentClass	The content class
	 * @return	array
	 */
	public static function parse_mobile_new_content_bulk( \IPS\Lang $language, \IPS\Node\Model $node, \IPS\Member $author, $contentClass )
	{
		return array(
			'title'		=> $language->addToStack( 'notification__new_content_bulk_title', FALSE, array( 'htmlsprintf' => array(
				$language->get( $contentClass::$title . '_pl_lc' ),
			) ) ),
			'body'		=> $language->addToStack( 'notification__new_content_bulk', FALSE, array( 'htmlsprintf' => array(
				$author->name,
				$language->get( $contentClass::$title . '_pl_lc' ),
				$node->getTitleForLanguage( $language )
			) ) ),
			'data'		=> array(
				'url'		=> (string) $node->url(),
				'author'	=> $author
			),
			'channelId'	=> 'followed',
		);
	}
		
	
	/**
	 * Parse notification: new_comment
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
	public function parse_new_comment( $notification, $htmlEscape=TRUE )
	{
		$item = $notification->item;
		if ( !$item )
		{
			throw new \OutOfRangeException;
		}
		
		$idColumn = $item::$databaseColumnId;
		$commentClass = $item::$commentClass;
		
		$between = time();
		try
		{
			/* Is there a newer notification for this item? */
			$between = \IPS\Db::i()->select( 'sent_time', 'core_notifications', array( '`member`=? AND item_id=? AND item_class=? AND sent_time>? AND notification_key=?', \IPS\Member::loggedIn()->member_id, $item->$idColumn, \get_class( $item ), $notification->sent_time->getTimestamp(), $notification->notification_key ) )->first();
		}
		catch( \UnderflowException $e ) {}
		
		$where = array();
		$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $item->$idColumn );
		$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['date'] . '>=?', $notification->sent_time->getTimestamp() );
		$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['date'] . '<?', $between ); 
		$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'] . ' !=?', \IPS\Member::loggedIn()->member_id );
		
		if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
		{
			$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . ' NOT IN(?,?)', -2, -3 );
		}
		elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
		{
			$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . ' NOT IN(?,?)', -2, -3 );
		}
			
		$commenters = \IPS\Db::i()->select( 'DISTINCT ' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'], $commentClass::$databaseTable, $where );
						
		$names = array();
		foreach ( $commenters as $member )
		{
			$name = ( $item->isAnonymous() ) ? \IPS\Member::loggedIn()->language()->addToStack( 'post_anonymously_placename' ) : \IPS\Member::load( $member )->name;
			if ( \count( $names ) > 2 )
			{
				$names[] = \IPS\Member::loggedIn()->language()->addToStack( 'x_others', FALSE, array( 'pluralize' => array( \count( $commenters ) - 3 ) ) );
				break;
			}
			$names[] = $name;
		}
		
		$comment = $commentClass::loadAndCheckPerms( $notification->item_sub_id );

		/* If the comment is in the deletion queue, add the showDeleted=1 parameter otherwise it just won't show up */
		$url = $comment->url('find');

		if( $comment->hidden() == -2 )
		{
			$url = $url->setQueryString( 'showDeleted', 1 );
		}
		
		/* Unread? */
		$unread = false;
		if ( $item->timeLastRead() instanceof \IPS\DateTime )
		{
			$unread = (bool) ( $item->timeLastRead()->getTimestamp() < $notification->updated_time->getTimestamp() );
		}
		
		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_comment', FALSE, array(
				'pluralize'									=> array( \count( $commenters ) ),
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' )	=> array(
					\IPS\Member::loggedIn()->language()->formatList( $names ), $item->mapped('title') ) )
				),
			'url'		=> $url,
			'content'	=> $comment->content(),
			'author'	=> $comment->author(),
			'unread'	=> $unread,
		);
	}
	
	/**
	 * Parse notification for mobile: new_comment
	 *
	 * @param	\IPS\Lang			$language		The language that the notification should be in
	 * @param	\IPS\Content\Comment	$comment			The comment
	 * @return	array
	 */
	public static function parse_mobile_new_comment( \IPS\Lang $language, \IPS\Content\Comment $comment )
	{
		$item = $comment->item();
		$idColumn = $item::$databaseColumnId;

		return array(
			'title'		=> $language->addToStack( 'notification__new_comment_title', FALSE, array(
				'pluralize'	=> array( 1 )
			) ),
			'body'		=> $language->addToStack( 'notification__new_comment', FALSE, array(
				'pluralize'	=> array( 1 ),
				'htmlsprintf'=> array(
					$language->formatList( array( $comment->author()->name ) ),
					$comment->item()->mapped('title')
				)
			) ),
			'data'		=> array(
				'url'		=> (string) $comment->url(),
				'author'	=> $comment->author(),
				'grouped'	=> $language->addToStack( 'notification__new_comment_grouped', FALSE, array(
					'htmlsprintf'	=> array(
						$comment->item()->mapped('title')
					)
				) ),
				'groupedTitle' => $language->addToStack( 'notification__new_comment_title' ), // Pluralized on the client
				// No need for groupedUrl here - latest comment url will do
			),
			'tag' => md5( 'newcomment' . \get_class( $item ) . $item->$idColumn ), // Group comment notifications by item ID (if available)
			'channelId'	=> 'followed',
		);
	}
	
	/**
	 * Parse notification: new_review
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
	public function parse_new_review( $notification, $htmlEscape = TRUE )
	{
		$item = $notification->item;

		if ( !$item )
		{
			throw new \OutOfRangeException;
		}
		
		$idColumn = $item::$databaseColumnId;
		$between = time();
		try
		{
			/* Is there a newer notification for this item? */
			$between = \IPS\Db::i()->select( 'sent_time', 'core_notifications', array( '`member`=? AND item_id=? AND item_class=? AND sent_time>?', \IPS\Member::loggedIn()->member_id, $item->$idColumn, \get_class( $item ), $notification->sent_time->getTimestamp() ) )->first();
		}
		catch( \UnderflowException $e ) {}
		
		$commentClass = $item::$reviewClass;
		
		$where = array();
		$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $item->$idColumn );
		$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['date'] . '>=?', $notification->sent_time->getTimestamp() );
		$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['date'] . '<?', $between ); 
		$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'] . ' !=?', \IPS\Member::loggedIn()->member_id );
		
		if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
		{
			$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . ' NOT IN(?,?)', -2, -3 );
		}
		elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
		{
			$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . ' NOT IN(?,?)', -2, -3 );
		}
		
		$commenters = \IPS\Db::i()->select( 'DISTINCT ' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'], $commentClass::$databaseTable, $where );
						
		$names = array();
		foreach ( $commenters as $member )
		{
			if ( \count( $names ) > 2 )
			{
				$names[] = \IPS\Member::loggedIn()->language()->addToStack( 'x_others', FALSE, array( 'pluralize' => array( \count( $commenters ) - 3 ) ) );
				break;
			}
			$names[] = \IPS\Member::load( $member )->name;
		}
		
		$review = $commentClass::loadAndCheckPerms( $notification->item_sub_id );
		
		/* Unread? */
		$unread = false;
		if ( $item->timeLastRead() instanceof \IPS\DateTime )
		{
			$unread = (bool) ( $item->timeLastRead()->getTimestamp() > $notification->updated_time->getTimestamp() );
		}
		
		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__new_review', FALSE, array(
				'pluralize'									=> array( \count( $commenters ) ),
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( \IPS\Member::loggedIn()->language()->formatList( $names ), $item->mapped('title') ) )
			),
			'url'		=> $review->url('find'),
			'content'	=> $review->content(),
			'author'	=> $review->author(),
			'unread'	=> $unread,
		);
	}
	
	/**
	 * Parse notification for mobile: new_review
	 *
	 * @param	\IPS\Lang			$language		The language that the notification should be in
	 * @param	\IPS\Content\Review	$review			The review
	 * @return	array
	 */
	public static function parse_mobile_new_review( \IPS\Lang $language, \IPS\Content\Review $review )
	{
		$item = $review->item();
		$idColumn = $item::$databaseColumnId;

		return array(
			'title'		=> $language->addToStack( 'notification__new_review_title', FALSE, array(
				'pluralize' => array(1)
			) ),
			'body'		=> $language->addToStack( 'notification__new_review', FALSE, array(
				'pluralize'	=> array( 1 ),
				'htmlsprintf'=> array(
					$language->formatList( array( $review->author()->name ) ),
					$review->item()->mapped('title')
				)
			) ),
			'data'		=> array(
				'url'		=> (string) $review->url(),
				'author'	=> $review->author(),
				'grouped'	=> $language->addToStack( 'notification__new_review_grouped', FALSE, array(
					'htmlsprintf'	=> array(
						$review->item()->mapped('title')
					)
				) ),
				'groupedTitle' => $language->addToStack( 'notification__new_review_title' ), // Pluralized on the client
				// No need for groupedUrl here - latest comment url will do
			),
			'tag' => md5( 'newreview' . \get_class( $item ) . $item->$idColumn ), // Group review notifications by item ID (if available)
			'channelId'	=> 'followed',
		);
	}
}