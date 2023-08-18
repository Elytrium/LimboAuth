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
class _Moderation
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
		
		/* Reports */
		if ( $member === NULL or $member->modPermission( 'can_view_reports' ) )
		{
			$return['report_center'] = array(
				'type'				=> 'standard',
				'notificationTypes'	=> array( 'report_center' ),
				'title'				=> 'notifications__core_Moderation_report_center',
				'showTitle'			=> TRUE,
				'description'		=> 'notifications__core_Moderation_report_center_desc',
				'default'			=> array( 'email' ),
				'disabled'			=> array( 'inline', 'push' ),
				'extra'				=> array(
					'report_count'		=> array(
						'title'				=> 'report_count',
						'description'		=> 'report_count_desc',
						'icon'				=> 'circle-o',
						'value'				=> $member ? ( !$member->members_bitoptions['no_report_count'] ) : NULL,
						'adminCanSetDefault'=> FALSE,
					)
				)
			);
			
			if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_automatic_moderation_rules', array( 'rule_enabled=1' ) )->first() )
			{
				$return['automatic_moderation'] = array(
					'type'				=> 'standard',
					'notificationTypes'	=> array( 'automatic_moderation' ),
					'title'				=> 'notifications__core_Moderation_automatic_moderation',
					'showTitle'			=> FALSE,
					'description'		=> 'notifications__core_Moderation_automatic_moderation_desc',
					'default'			=> array( 'inline', 'email' ),
					'disabled'			=> array(),
				);
			}
		}
		
		/* Content/Clubs needing approval */
		$canSeePendingContent = ( !$member or $member->modPermission( 'can_view_hidden_content' ) );
		if ( !$canSeePendingContent )
		{
			foreach ( \IPS\Content::routedClasses( TRUE, TRUE ) as $class )
			{
				if ( \in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
				{
					if ( $member->modPermission( 'can_view_hidden_' . $class::$title ) )
					{
						$canSeePendingContent = TRUE;
						break;
					}
				}
			}
		}
		$canApproveClubs = FALSE;
		if ( \IPS\Settings::i()->clubs and \IPS\Settings::i()->clubs_require_approval and $module = \IPS\Application\Module::get( 'core', 'clubs', 'front' ) and $module->_enabled )
		{
			$canApproveClubs = ( !$member or $member->modPermission( 'can_access_all_clubs' ) );
		}
		if ( $canSeePendingContent or $canApproveClubs )
		{
			$return['unapproved_content'] = array(
				'type'				=> 'standard',
				'notificationTypes'	=> array( 'unapproved_content', 'unapproved_club' ),
				'title'				=> 'notifications__core_Moderation_unapproved_content',
				'showTitle'			=> TRUE,
				'description'		=> ( $canSeePendingContent and $canApproveClubs ) ? 'notifications__core_Moderation_unapproved_content_desc_both' : ( $canSeePendingContent ? 'notifications__core_Moderation_unapproved_content_desc_content' : 'notifications__core_Moderation_unapproved_content_desc_clubs' ),
				'default'			=> array( 'inline', 'email' ),
				'disabled'			=> array(),
			);
		} 
		
		/* Warnings */
		if ( !$member or $member->modPermission( 'mod_see_warn' ) )
		{
			$return['warning_mods'] = array(
				'type'				=> 'standard',
				'notificationTypes'	=> array( 'warning_mods' ),
				'title'				=> 'notifications__core_Moderation_warning_mods',
				'showTitle'			=> TRUE,
				'description'		=> 'notifications__core_Moderation_warning_mods_desc',
				'default'			=> array( 'inline' ),
				'disabled'			=> array(),
			);
		}
		
		return $return;
	}
	
	/**
	 * Save "extra" value
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string		$key	The key
	 * @param	bool		$value	The value
	 * @return	void
	 */
	public static function saveExtra( ?\IPS\Member $member, $key, $value )
	{
		switch ( $key )
		{
			case 'report_count':
				$member->members_bitoptions['no_report_count'] = !$value;
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
		// Do nothing
	}
	
	/**
	 * Reset "extra" value to the default for all accounts
	 *
	 * @return	void
	 */
	public static function resetExtra()
	{
		\IPS\Db::i()->update( 'core_members', 'members_bitoptions2 = members_bitoptions2 &~' . \IPS\Member::$bitOptions['members_bitoptions']['members_bitoptions2']['no_report_count'] );
	}
	
	/**
	 * Get configuration
	 *
	 * @param	\IPS\Member|null	$member	The member
	 * @return	array
	 */
	public function getConfiguration( $member )
	{
		$return = array();
										
		if ( $member === NULL or $member->modPermission( 'can_view_hidden_content' ) )
		{
			$return['unapproved_content'] = array( 'default' => array( 'email' ), 'disabled' => array(), 'icon' => 'lock' );
		}
		else
		{
			foreach ( \IPS\Content::routedClasses( TRUE, TRUE ) as $class )
			{
				if ( \in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
				{
					if ( $member->modPermission( 'can_view_hidden_' . $class::$title ) )
					{
						$return['unapproved_content'] = array( 'default' => array( 'email' ), 'disabled' => array(), 'icon' => 'lock' );
						break;
					}
				}
			}
		}
		
		return $return;
	}
		
	/**
	 * Parse notification: unapproved_content_bulk
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
	public function parse_unapproved_content_bulk( $notification, $htmlEscape=TRUE )
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
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__unapproved_content_bulk', FALSE, array(
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array(
					$author->name,
					\IPS\Member::loggedIn()->language()->get( $contentClass::$title . '_pl_lc' ),
					$node->getTitleForLanguage( \IPS\Member::loggedIn()->language(), $htmlEscape ? array( 'escape' => TRUE ) : array() )
				)
			) ),
			'url'		=> $node->url(),
			'author'	=> $author
		);
	}
		
	/**
	 * Parse notification: warning_mods
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
	public function parse_warning_mods( $notification, $htmlEscape=TRUE )
	{
		if ( !$notification->item )
		{
			throw new \OutOfRangeException;
		}
		
		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__warning_mods', FALSE, array(
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( \IPS\Member::load( $notification->item->member )->name, \IPS\Member::load( $notification->item->moderator )->name ) )
			),
			'url'		=> $notification->item->url(),
			'content'	=> $notification->item->note_mods,
			'author'	=> \IPS\Member::load( $notification->item->moderator ),
		);
	}
	
	/**
	 * Parse notification for mobile: warning_mods
	 *
	 * @param	\IPS\Lang					$language	The language that the notification should be in
	 * @param	\IPS\core\Warnings\Warning	$warning		The warning
	 * @return	array
	 */
	public static function parse_mobile_warning_mods( \IPS\Lang $language, \IPS\core\Warnings\Warning $warning )
	{
		return array(
			'title'		=> $language->addToStack( 'notification__warning_mods_title', FALSE, array( 'pluralize' => array(1) ) ),
			'body'		=> $language->addToStack( 'notification__warning_mods', FALSE, array( 'htmlsprintf' => array(
				\IPS\Member::load( $warning->member )->name,
				\IPS\Member::load( $warning->moderator )->name
			) ) ),
			'data'		=> array(
				'url'		=> (string) $warning->url(),
				'author'	=> $warning->moderator,
				'grouped'	=> $language->addToStack( 'notification__warning_mods_grouped'), // Pluralized on the client
				'groupedTitle' => $language->addToStack( 'notification__warning_mods_title' ), // Pluralized on the client
				'groupedUrl' => \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=recent_warnings', 'front', 'modcp_recent_warnings' )
			),
			'tag' => md5('recent-warnings'), // Group warning notifications
			'channelId'	=> 'moderation',
		);
	}
	
	/**
	 * Parse notification for mobile: unapproved_content_bulk
	 *
	 * @param	\IPS\Lang			$language		The language that the notification should be in
	 * @param	\IPS\Node\Model		$node			The node with the new content
	 * @param	\IPS\Member			$author			The author
	 * @param	string				$contentClass	The content class
	 * @return	array
	 */
	public static function parse_mobile_unapproved_content_bulk( \IPS\Lang $language, \IPS\Node\Model $node, \IPS\Member $author, $contentClass )
	{
		return array(
			'title'		=> $language->addToStack( 'notification__unapproved_content_bulk_title', FALSE, array( 'htmlsprintf' => array(
				$language->get( $contentClass::$title . '_pl_lc' ),
			) ) ),
			'body'		=> $language->addToStack( 'notification__unapproved_content_bulk', FALSE, array( 'htmlsprintf' => array(
				$author->name,
				$language->get( $contentClass::$title . '_pl_lc' ),
				$node->getTitleForLanguage( $language )
			) ) ),
			'data'		=> array(
				'url'		=> (string) $node->url(),
				'author'	=> $author
			),
			'channelId'	=> 'moderation',
		);
	}
	
	/**
	 * Parse notification: report_center
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
	public function parse_report_center( $notification, $htmlEscape=TRUE )
	{
		if ( !$notification->item_sub )
		{
			throw new \OutOfRangeException;
		}

		$reported = $notification->item_sub;
		$item = ( $reported instanceof \IPS\Content\Item ) ? $reported : $reported->item();

		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__report_center', FALSE, array(
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( $notification->item->author()->name, mb_strtolower( $reported->indefiniteArticle() ), $item->mapped('title' ) ) )
			),
			'url'		=> $notification->item->url(),
			'content'	=> NULL,
			'author'	=> $notification->item->author(),
		);
	}
	
	/**
	 * Parse notification for mobile: report_center
	 *
	 * @param	\IPS\Lang					$language		The language that the notification should be in
	 * @param	\IPS\core\Reports\Report	$report			The report
	 * @param	array						$latestReport	Information about this specific report
	 * @param	\IPS\Content				$reportedContent	The content that was reported
	 * @return	array
	 */
	public static function parse_mobile_report_center( \IPS\Lang $language, \IPS\core\Reports\Report $report, array $latestReport, \IPS\Content $reportedContent )
	{
		return array(
			'title'		=> $language->addToStack( 'notification__report_center_title' ),
			'body'		=> $language->addToStack( 'notification__report_center', FALSE, array( 'htmlsprintf' => array(
				\IPS\Member::load( $latestReport['report_by'] )->name,
				mb_strtolower( $reportedContent->indefiniteArticle( $language ) ),
				$report->mapped('title')
			) ) ),
			'data'		=> array(
				'url'		=> (string) $report->url(),
				'author'	=> $report->author(),
				'grouped'	=> $language->addToStack( 'notification__report_center_grouped'), // Pluralized on the client
				'groupedTitle' => $language->addToStack( 'notification__report_center_title' ), // Pluralized on the client
				'groupedUrl' => \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=reports', NULL, 'modcp_reports' )
			),
			'tag' => md5('report-center'),
			'channelId'	=> 'moderation',
		);
	}
	
	/**
	 * Parse notification: automatic_moderation
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
	public function parse_automatic_moderation( $notification, $htmlEscape=TRUE )
	{
		if ( !$notification->item_sub )
		{
			throw new \OutOfRangeException;
		}

		$reported = $notification->item_sub;
		$item = ( $reported instanceof \IPS\Content\Item ) ? $reported : $reported->item();

		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__automatic_moderation', FALSE, array(
				( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( mb_strtolower( $reported->indefiniteArticle() ), $item->mapped('title' ) ) )
			),
			'url'		=> $notification->item->url(),
			'content'	=> NULL,
			'author'	=> $notification->item->author(),
		);
	}
	
	/**
	 * Parse notification for mobile: automatic_moderation
	 *
	 * @param	\IPS\Lang					$language		The language that the notification should be in
	 * @param	\IPS\core\Reports\Report		$report			The report
	 * @param	array						$latestReport	Information about this specific report
	 * @param	\IPS\Content					$reportedContent	The content that was reported
	 * @return	array
	 */
	public static function parse_mobile_automatic_moderation( \IPS\Lang $language, \IPS\core\Reports\Report $report, array $latestReport, \IPS\Content $reportedContent )
	{
		return array(
			'title'		=> $language->addToStack( 'notification__automatic_moderation_title' ),
			'body'		=> $language->addToStack( 'notification__automatic_moderation', FALSE, array( 'htmlsprintf' => array(
				mb_strtolower( $reportedContent->indefiniteArticle( $language ) ),
				$report->mapped('title')
			) ) ),
			'data'		=> array(
				'url'		=> (string) $report->url(),
				'author'	=> $report->author()
			),
			'channelId'	=> 'moderation',
		);
	}
	
	/**
	 * Parse notification: unapproved_content
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
	public function parse_unapproved_content( $notification, $htmlEscape=TRUE )
	{
		if ( !$notification->item )
		{
			throw new \OutOfRangeException;
		}
		
		$item = $notification->item;
		
		if ( $item instanceof \IPS\Content\Comment OR $item instanceof \IPS\Content\Review )
		{
			$title = $item->item()->mapped('title');
		}
		else
		{
			$title = $item->mapped('title');
		}
		
		$name = ( $item->isAnonymous() ) ? \IPS\Member::loggedIn()->language()->addToStack( 'post_anonymously_placename' ) : $item->author()->name;
		
		return array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'notification__unapproved_content', FALSE, array( ( $htmlEscape ? 'sprintf' : 'htmlsprintf' ) => array( $name, mb_strtolower( $item->indefiniteArticle() ), $title ) ) ),
			'url'		=> $item->url(),
			'content'	=> $item->content(),
			'author'	=> $item->author(),
		);
	}
	
	/**
	 * Parse notification for mobile: unapproved_content
	 *
	 * @param	\IPS\Lang		$language		The language that the notification should be in
	 * @param	\IPS\Content		$content			The content
	 * @return	array
	 */
	public static function parse_mobile_unapproved_content( \IPS\Lang $language, \IPS\Content $content )
	{
		$item = ( $content instanceof \IPS\Content\Item ) ? $content : $content->item();
		$container = $item->containerWrapper();
		$containerId = $container ? $container->_id : "-"; // This is used to generate the tag. Use ID if we have one, otherwise just a dash

		return array(
			'title'		=> $language->addToStack( 'notification__unapproved_content_title', FALSE, array( 'htmlsprintf' => array(
				( $content instanceof \IPS\Content ) ? $content->definiteArticle( $language ) : $language->get( $content::$title . '_lc' ),
			) ) ),
			'body'		=> $language->addToStack( 'notification__unapproved_content', FALSE, array( 'htmlsprintf' => array(
				$content->author()->name,
				mb_strtolower( $content->indefiniteArticle( $language ) ),
				$item->mapped('title')
			) ) ),
			'data'		=> array(
				'url'		=> (string) $content->url(),
				'author'	=> $content->author(),
				'grouped'	=> $language->addToStack( 'notification__unapproved_content_grouped', FALSE, array( 'htmlsprintf' => array(
					$content->definiteArticle( $language, TRUE ),
					$container ? 
						$language->addToStack( 'notification__container', FALSE, array( 'sprintf' => array( $container->_title ) ) ) 
						: ""
				))), // Pluralized on the client
				'groupedTitle' => $language->addToStack( 'notification__unapproved_content_title', FALSE, array( 'htmlsprintf' => array(
					$content->definiteArticle( $language, TRUE ),
				)) ), // Pluralized on the client
				'groupedUrl' => $container ? $container->url() : NULL
			),
			'tag' => md5('unapproved' . \get_class( $item ) . $containerId ),
			'channelId'	=> 'moderation',
		);
	}
}