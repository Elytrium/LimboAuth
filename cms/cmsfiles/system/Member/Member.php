<?php
/**
 * @brief		Member Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Model
 */
class _Member extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Application
	 */
	public static $application = 'core';
	
	/* !\IPS\Patterns\ActiveRecord */
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_members';
		
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'member_id';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'name', 'email' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	Bitwise values for members_bitoptions field
	 */
	public static $bitOptions = array(
		'members_bitoptions'	=> array(
			'members_bitoptions'	=> array(
				'bw_is_spammer'					=> 1,			// Flagged as spam?
				// 2 is deprecated
				// 4 (bw_vnc_type) is deprecated
				// 8 (bw_forum_result_type) is deprecated
				'bw_no_status_update'			=> 16,			// Can post status updates?, 1 means they CAN'T post status updates. 0 means they can.
				// 32 (bw_status_email_mine) is deprecated
				// 64 (bw_status_email_all) is deprecated
				// 128 is deprecated (previously bw_disable_customization)
				// 256 is deprecated (bw_local_password_set) - used to represent if a local passwnot was set and block the change password form if not
				'bw_disable_tagging'			=> 512,			// Tags disabled for this member? 1 means they are, 0 means they aren't.
				'bw_disable_prefixes'			=> 1024,		// Tag prefixes disabled? 1 means they are, 0 means they aren't.
				'bw_using_skin_gen'				=> 2048,		// 1 means the user has the easy mode editor active, 0 means they do not.
				// 4096 (bw_disable_gravatar) is deprecated
				// 8192 (bw_paste_plain) is deprecated
				// 16384 (bw_html_sig) is deprecated
				// 32768 (allow_admin_mails) is deprecated
				'view_sigs'						=> 65536,		// View signatures?
				// 131072 (view_img) is deprecated
				// 262144 is deprecated
				'coppa_user'					=> 524288,		// Was the member validated using coppa?
				// 1048576 (login_anonymous) is deprecated
				// 2097152 (login_anonymous_online) is deprecated
				// 4194304 (members_auto_dst) is deprecated
				// 8388608 (members_created_remote) is deprecated
				// 16777216 (members_disable_pm) is deprecated
				'unacknowledged_warnings'		=> 33554432,	// 1 means the member has at least one warning they have not acknowledged. 0 means they have none.
				// 67108864 (pp_setting_moderate_comments) is deprecated and replaced with global setting
				'pp_setting_moderate_followers'	=> 134217728,	// Previously pp_setting_moderate_friends. Replaced with setting that toggles whether or not member can be followed. 
				'pp_setting_count_visitors'		=> 268435456,	// If TRUE, last 5 visitors will be shown on profile
				// 536870912 (timezone_override) is deprecated
				'validating'					=> 1073741824,	// If TRUE user is validating and should have a corresponding row in core_validating
			),
			'members_bitoptions2'	=> array(
				'show_pm_popup'					=> 1, // "Show pop-up when I have a new message"
				'remove_gallery_access'			=> 2, // Remove access to Gallery
				'remove_gallery_upload'			=> 4, // Remove permission to upload images in Gallery
				'no_report_count'				=> 8, // 1 means the report count will not show
				'has_no_ignored_users'			=> 16, // If we know the user has no ignored users, we don't have to query for them
				'must_reaccept_privacy'			=> 32, // 1 means the member needs to re-accept the privacy policy
				'must_reaccept_terms'			=> 64, // 1 means the member needs to re-accept the registration terms
				'email_notifications_once'      => 128, // 1 means the member only wants one email per notification item until they revisit the community
				'disable_notification_sounds'	=> 256, /** @deprecated Notification sounds are no longer supported by default */
				'has_security_answers'			=> 512,
				'security_questions_opt_out'	=> 1024,
				'ignore_promotions'				=> 2048,// 1 means that the user will not be checked against group promotion rules. Set by Commerce to prevent "promoting" users incorrectly after a purchase.
				'profile_completed'				=> 4096,
				'profile_completion_dismissed'	=> 8192,
				'created_externally'			=> 16384,
				'is_support_account'			=> 32768, // Created by ACP support tool
				'is_anon'						=> 65536, // Has chosen to log in anonymously
				// 131072 (has_new_acp_features) is deprecated
				'password_reset_forced'			=> 262144, // A password reset has been forced
				'link_pref_unread'				=> 524288, // Take me to comments I haven't read
				'link_pref_last'				=> 1048576, // Take me to the latest comment
				'mobile_notifications'			=> 2097152, // Has the user has enabled push notifications on at least 1 mobile device?
				'link_pref_first'			    => 4194304, // Take me to the first comment
				'datalayer_event_fired'		    => 8388608,
				'new_device_email'				=> 16777216, // Send an email when a new device is used to log in.
				'no_solved_reenage'				=> 33554432, // User does not want to get topic  re-engagement emails
				'datalayer_pii_optout'			=> 67108864, // User has opted in to having their PII collected by datalayer
				'email_messages_bounce'			=> 134217728, // User's email keeps returning hard bounces so they need to change it
				// 268435456 - cookie_optout moved to cookie preferences
			)
		)
	);

	/**
	 * Member Changes to skip for the member_edited webhook
	 *
	 * @var string[]
	 */
	public static $changesToSkipForMemberEditWebhooks = [
		'last_visit',
		'last_activity',
		'profilesync_lastsync',
	];
	
	/* !Follow */
	
	/**
	 * @brief	Cache for current follow data, used on "My Followed Content" screen
	 */
	public $_followData;

	/**
	 * @brief	Following publicly
	 */
	const FOLLOW_PUBLIC = 1;

	/**
	 * @brief	Following anonymously
	 */
	const FOLLOW_ANONYMOUS = 2;
	
	/**
	 * @brief	Cached logged in member
	 */
	public static $loggedInMember	= NULL;
	
	/**
	 * @brief	If we change the photo_type, then we need to record the previous photo type to determin if set_pp_main_photo should attempt removal of existing images
	 */
	protected $_previousPhotoType = NULL;

	/**
	 * Get logged in member
	 *
	 * @return	\IPS\Member
	 */
	public static function loggedIn()
	{
		/* If we haven't loaded the member yet, or if the session member has changed since we last loaded the member, reload and cache */
		if( static::$loggedInMember === NULL )
		{
			if( \IPS\Request::isCliEnvironment() )
			{
				static::$loggedInMember = new \IPS\Member;
			}
			else
			{
				static::$loggedInMember = \IPS\Session::i()->member;
			}
			
			if ( isset( $_SESSION['logged_in_as_key'] ) )
			{
				if ( static::$loggedInMember->isAdmin() AND static::$loggedInMember->hasAcpRestriction( 'core', 'members', 'member_login' ) )
				{
					$key = $_SESSION['logged_in_as_key'];
	
					if ( isset( \IPS\Data\Store::i()->$key ) )
					{
						static::$loggedInMember	= static::load( \IPS\Data\Store::i()->$key );
						
						if ( !static::$loggedInMember->member_id )
						{
							unset( \IPS\Data\Store::i()->$key );
							unset( $_SESSION['logged_in_as_key'] );
						}
					}
				}
			}
			
			if ( !static::$loggedInMember->member_id and isset( \IPS\Request::i()->cookie['ipsTimezone'] ) )
			{
				/* Check for valid timezone identifier */
				$tz = \IPS\DateTime::getFixedTimezone( \IPS\Request::i()->cookie['ipsTimezone'] );
				if( \in_array( $tz, \DateTimeZone::listIdentifiers() ) )
				{
					static::$loggedInMember->timezone = $tz;
				}
			}
		}

		return static::$loggedInMember;
	}
	
	/**
	 * Load Record
	 * We override it so we return a guest object for a non-existant member
	 *
	 * @see		\IPS\Db::build
	 * @param	int|string	$id					ID
	 * @param	string		$idField			The database column that the $id parameter pertains to (NULL will use static::$databaseColumnId)
	 * @param	mixed		$extraWhereClause	Additional where clause (see \IPS\Db::build for details)
	 * @return	static
	 */
	public static function load( $id, $idField=NULL, $extraWhereClause=NULL )
	{
		try
		{
			if( $id === NULL OR $id === 0 OR $id === '' )
			{
				$classname = \get_called_class();
				return new $classname;
			}
			else
			{
				$member = parent::load( $id, $idField, $extraWhereClause );
				
				if ( $member->restrict_post > 0 and $member->restrict_post <= time() )
				{
					$member->restrict_post = 0;
					$member->save();
				}

				return $member;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			$classname = \get_called_class();
			return new $classname;
		}
	}
	
	/**
	 * Load record based on a URL
	 *
	 * @param	\IPS\Http\Url	$url	URL to load from
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function loadFromUrl( \IPS\Http\Url $url )
	{
		try
		{
			$member = parent::loadFromUrl( $url );
		}
		catch( \InvalidArgumentException $e )
		{
			throw new \OutOfRangeException;
		}

		if ( !$member->member_id )
		{
			throw new \OutOfRangeException;
		}
		return $member;
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		/* If we're in the installer - don't do this */
		if ( \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation === 'setup' )
		{
			return;
		}

		$this->member_group_id		= \IPS\Settings::i()->guest_group;
		$this->mgroup_others		= '';
		$this->joined				= time();
		$this->ip_address			= \IPS\Request::i()->ipAddress();
		$this->timezone				= 'UTC';
		$this->allow_admin_mails	= ( \IPS\Settings::i()->updates_consent_default == 'enabled' );
		$this->pp_photo_type        = '';
		$this->member_posts 		= 0;
		$this->last_visit			= NULL;
		$this->_data['pp_main_photo'] = NULL;
		$this->_data['pp_thumb_photo'] = NULL;
		$this->_data['failed_logins'] = NULL;
		$this->_data['mfa_details'] = NULL;
		$this->_data['pp_reputation_points'] = 0;
		$this->_data['signature'] = '';

		/* We need to enable the view_sigs bit if we want to show signatures to guests */
		if ( \IPS\Settings::i()->signatures_guests )
		{
			$this->members_bitoptions['view_sigs'] = TRUE;
		}

		$this->members_bitoptions['new_device_email'] = TRUE;
		$this->members_bitoptions['show_pm_popup'] = ( \IPS\Settings::i()->notification_prefs_popup  ) ? TRUE : FALSE;
		$this->members_bitoptions['email_notifications_once'] = ( \IPS\Settings::i()->notification_prefs_one_per_view  ) ? TRUE : FALSE;

		$this->_data['auto_track']	= json_encode( array(
			'content'	=> \IPS\Settings::i()->auto_follow_new_content ? 1 : 0,
			'comments'	=> \IPS\Settings::i()->auto_follow_replied_to ? 1 : 0,
			'method'	=> 'immediate'
		)	);

		if( isset( \IPS\Request::i()->cookie['language'] ) AND \IPS\Request::i()->cookie['language'] )
		{
			$this->language	= \IPS\Request::i()->cookie['language'];
		}
		
		if( isset( \IPS\Request::i()->cookie['theme'] ) AND \IPS\Request::i()->cookie['theme'] )
		{
			$this->skin	= \IPS\Request::i()->cookie['theme'];
		}
	}

	/**
	 * Allow optional cookies
	 *
	 * @param   bool    $allow
	 * @return  bool
	 */
	public function setAllowOptionalCookies( bool $allow = TRUE ): bool
	{
		$expire = ( new \IPS\DateTime )->add( new \DateInterval('P6M' ) );
		\IPS\Request::i()->setCookie( 'cookie_consent', 1, $expire, FALSE );

		if( !$allow )
		{
			/* Remove optional cookies */
			foreach( \IPS\Request::i()->cookie as $cookieName => $value )
			{
				if( !\in_array( $cookieName, \IPS\Request::i()->getEssentialCookies() ) )
				{
					\IPS\Request::i()->setCookie( $cookieName, NULL);
				}
			}
			\IPS\Request::i()->setCookie( 'cookie_consent_optional', NULL );
		}
		else
		{
			\IPS\Request::i()->setCookie( 'cookie_consent_optional', 1, $expire, FALSE );
		}

		return $allow;
	}

	/**
	 * Are optional cookies allowed?
	 *
	 * @return  bool
	 */
	public function get_optionalCookiesAllowed(): bool
	{
		return isset( \IPS\Request::i()->cookie[ 'cookie_consent' ] ) AND isset( \IPS\Request::i()->cookie[ 'cookie_consent_optional' ] );
	}

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @param bool $setAuthorToGuest	Sets the author id of all content to 0 ( guest )
	 * @param bool $keepAuthorName		Keeps the author name
	 * @return void
	 */
	public function delete( $setAuthorToGuest = TRUE, $keepAuthorName = TRUE )
	{
		/* Is this a guest object? */
		if( !$this->member_id )
		{
			\IPS\Log::log( [ $this->name, $this->email ], 'guest_delete_attempt' );
			return;
		}

        if( $setAuthorToGuest )
        {
            /* Clean up content - set to member ID 0 - We check $setAuthorToGuest because of member merging.
            As the member is immediately deleted we do not want to compete with the existing merge in progress. */
            $this->hideOrDeleteAllContent( 'merge', array( 'merge_with_id' => 0, 'merge_with_name' => $keepAuthorName  ?  $this->name : '' ) );
        }

		/* Let apps do their stuff */
		$this->memberSync( 'onDelete' );
		
		/* Actually delete from database */
		parent::delete();
		
		/* We may have deleted a member waiting validation */
		if ( !\IPS\Db::i()->select( 'COUNT(*)', 'core_validating', array( 'user_verified=?', TRUE ) )->first() )
		{
			\IPS\core\AdminNotification::remove( 'core', 'NewRegValidate' );
		}
		if ( $latestReg = \IPS\Db::i()->select( 'joined', 'core_members',  array( 'email != ?', '' ), 'joined DESC', 1 )->first() )
		{
			\IPS\core\AdminNotification::remove( 'core', 'NewRegComplete', NULL, \IPS\DateTime::ts( $latestReg ) );
		}
		else
		{
			\IPS\core\AdminNotification::remove( 'core', 'NewRegComplete', NULL );
		}
		if ( $this->members_bitoptions['is_support_account'] )
		{
			\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', "supportAdmin-{$this->member_id}" );
		}

		/* Delete from member map */
		\IPS\Db::i()->delete( 'core_item_member_map', array( 'map_member_id=?', $this->member_id ) );

		/* Reset statistics */
		\IPS\Widget::deleteCaches();
	}

	/**
	 * [ActiveRecord] Save Changed Columns
	 *
	 * @return	void
	 * @note	We have to be careful when upgrading in case we are coming from an older version
	 */
	public function save()
	{
		if ( $this->member_id AND ( !\IPS\Dispatcher::hasInstance() OR \IPS\Dispatcher::i()->controllerLocation != 'setup' ) )
		{
			$this->checkGroupPromotion();
		}

		$new		= $this->_new;
		$changes	= $this->changed;

		/* Set default status updates enabled/disabled status */
		if( $new AND !isset( $this->_data['pp_setting_count_comments'] ) )
		{
			$this->_data['pp_setting_count_comments'] = \IPS\Settings::i()->status_updates_mem_enable;
		}

		/* If we don't have a name or email address, account is not 'complete' */
		/* @note The completed column is added in the 4.4.0 Beta 1 routine, so we need to verify the upgrade has been performed otherwise
			the auto-upgrader can replace this file before the column is added, and an SQL error can prevent the admin from completing the
			auto-upgrade process */
		$fireRegistrationCompletedWebhook = FALSE;
		if ( \IPS\Application::load( 'core' )->long_version > 104000 )
		{
			if( $this->completed AND ( !$this->name OR !$this->email ) )
			{
				$this->completed = FALSE;
			}
			/* Conversely if it's flagged incomplete but we have all the details, change the flag */
			elseif( !$this->completed AND $this->name AND $this->email )
			{
				$this->completed = TRUE;
				$fireRegistrationCompletedWebhook = TRUE;

			}
		}

		/* If the email was changed, make sure that the bounce flag is unset so the member no longer sees the error message */
		if ( isset( $changes['email'] ) )
		{
			$this->members_bitoptions['email_messages_bounce'] = 0;
		}

		parent::save();

		if ( $new )
		{
			/* Profile Fields */
			\IPS\Db::i()->insert( 'core_pfields_content', array( 'member_id' => $this->member_id ), TRUE );
			
			$this->memberSync( 'onCreateAccount' );
		}
		else
		{
			if( $fireRegistrationCompletedWebhook )
			{
				\IPS\Api\Webhook::fire( 'member_registration_complete', $this, $this->webhookFilters() );
			}
			/* Run member sync, but not if the only change is the last_activity timestamp */
			if( \count( $this->changedCustomFields ) > 0 OR ( \count( $changes ) > 0 AND !( \count( $changes ) === 1 AND isset( $changes['last_activity'] ) ) ) )
			{
				$this->memberSync( 'onProfileUpdate', array( 'changes' => array_merge( $changes, $this->changedCustomFields ) ) );
			}
		}

		/* If we have updated custom fields, make sure we don't have any cached in the class - just wipe out cache so we will refetch if needed */
		if( \count( $this->changedCustomFields ) )
		{
			$this->rawProfileFieldsData	= NULL;
			$this->profileFields		= NULL;
		}

		/* rebuild create menu if the user changed status settings or group(s) */
		if ( !$new AND ( isset( $changes['pp_setting_count_comments'] ) or isset( $changes['member_group_id'] ) or isset( $changes['mgroup_others'] ) ) )
		{
			$this->create_menu = NULL;
			parent::save();
		}

		/* Remove pending validation entries if our email address has changed */
		if( !$new AND isset( $changes['email'] ) )
		{
			/* Delete any pending validation emails */
			\IPS\Db::i()->delete( 'core_validating', array( 'member_id=? AND email_chg=1', $this->member_id ) );

			/* Delete any pending password reset emails */
			\IPS\Db::i()->delete( 'core_validating', array( 'member_id=? AND lost_pass=1', $this->member_id ) );
		}
	}
	
	/* !Getters/Setters Data */
	
	/**
	 * Group Data, taking into consideration secondary groups
	 */
	public $_group = NULL;
	
	/**
	 * @brief	Admin CP Restrictions
	 */
	protected $restrictions = NULL;
	
	/**
	 * @brief	Moderator Permissions
	 */
	protected $modPermissions = NULL;
	
	/**
	 * @brief	Calculated language ID
	 */
	protected $calculatedLanguageId = NULL;
	
	/**
	 * @brief	Marker Cache
	 */
	public $markers = array();

	/**
	 * @brief	Markers reset times
	 */
	public $markersResetTimes = array();

	/**
	 * @brief	We have fetched markers already
	 */
	public $haveAllMarkers = FALSE;
	
	/**
	 * @brief	Default stream ID
	 */
	protected $defaultStreamId = FALSE;
	
	/**
	 * @brief	Keep track of any changed profile fields
	 */
	public $changedCustomFields = array();

	/**
	 * Get name, do not return "guest" name if not set
	 *
	 * @return	string
	 */
	public function get_real_name()
	{
		return ( isset( $this->_data['name'] ) ) ? $this->_data['name'] : '';
	}

	/**
	 * Get name
	 *
	 * @return	string
	 */
	public function get_name()
	{
		if( !isset( $this->_data['name'] ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('guest');
		}
		
		return $this->member_id ? $this->_data['name'] : \IPS\Member::loggedIn()->language()->addToStack( 'guest_name_shown', FALSE, array( 'sprintf' => array( $this->_data['name'] ) ) );
	}
	
	/**
	 * Get name, loading the guest_name_shown language string directly if needed
	 *
	 * @return	string
	 */
	public function get__name()
	{
		if ( !isset( $this->_data['name'] ) )
		{
			return \IPS\Member::loggedIn()->language()->get('guest');
		}
		
		return $this->member_id ? $this->_data['name'] : sprintf( \IPS\Member::loggedIn()->language()->get('guest_name_shown'), $this->_data['name'] );
	}

	/**
	 * @brief	Previous name - stored temporarily for display name history log
	 */
	protected $previousName	= NULL;

	/**
	 * Set name
	 *
	 * @param	string	$value	Value
	 * @return	void
	 */
	public function set_name( $value )
	{
		if( isset( $this->_data['name'] ) )
		{
			$this->previousName				= $this->_data['name'];
		}

		$this->_data['name']				= $value;
		$this->_data['members_seo_name']	= \IPS\Http\Url\Friendly::seoTitle( $value );
	}

	/**
	 * Set group
	 *
	 * @param	int	$value	Value
	 * @return	void
	 */
	public function set_member_group_id( $value )
	{
		$this->_data['member_group_id'] = (int) $value;
		$this->_group = NULL;
	}
	
	/**
	 * Set Secondary Groups
	 *
	 * @param	string	$value	Value
	 * @return	void
	 */
	public function set_mgroup_others( $value )
	{
		$groups = array_filter( explode( ",", $value ) );
		if ( \in_array( \IPS\Settings::i()->guest_group, $groups ) )
		{
			throw new \InvalidArgumentException;
		}
				
		$this->_data['mgroup_others'] = implode( ',', $groups );
		$this->_group = NULL;
	}
		
	/**
	 * Flag as spammer
	 *
	 * @return	void
	 */
	public function flagAsSpammer()
	{
		if ( !$this->members_bitoptions['bw_is_spammer'] )
		{
			$actions = explode( ',', \IPS\Settings::i()->spm_option );
						
			/* Hide or delete */
			if ( \in_array( 'unapprove', $actions ) or \in_array( 'delete', $actions ) )
			{
				/* Send to queue */
				$this->hideOrDeleteAllContent( \in_array( 'delete', $actions ) ? 'delete' : 'hide' );
				
				/* Clear out their profile */
				if ( \in_array( 'delete', $actions ) )
				{
					$this->signature		= '';
					$this->pp_main_photo	= NULL;
					$this->pp_photo_type	= NULL;

					$this->coverPhoto()->delete();
					$this->pp_cover_photo	= '';
					$this->pp_cover_offset	= 0;
					
					\IPS\Db::i()->delete( 'core_pfields_content', array( 'member_id=?', $this->member_id ) );
				}
			}
			
			/* Restrict from posting or ban */
			if ( \in_array( 'disable', $actions ) or \in_array( 'ban', $actions ) )
			{
				if ( \in_array( 'ban', $actions ) )
				{
					$this->temp_ban = -1;
				}
				else
				{
					$this->restrict_post = -1;
					$this->members_disable_pm = 2;
				}
			}
									
			/* Save */
			$this->members_bitoptions['bw_is_spammer'] = TRUE;
			$this->save();

			/* Run sync */
			$this->logHistory( 'core', 'account', array( 'type' => 'spammer', 'set' => TRUE, 'actions' => $actions ) );
			$this->memberSync( 'onSetAsSpammer' );
			
			/* Notify admin */
			\IPS\core\AdminNotification::send( 'core', 'Spammer', NULL, TRUE, $this->member_id, \IPS\Member::loggedIn() );
			
			/* Feedback to Spam Monitoring Service */
			if ( \IPS\Settings::i()->spam_service_enabled and \IPS\Settings::i()->spam_service_send_to_ips )
			{
				$this->spamService( 'markspam' );
			}
		}
	}
	
	/**
	 * Hide/Delete All Content
	 *
	 * @param	string	$action	'hide' or 'delete' or 'merge'
	 * @param	array 	$extra	Extra data needed by the MemberContent plugin
	 * @return	void
	 */
	public function hideOrDeleteAllContent( $action, $extra=array() )
	{
		/* Edited member, so clear widget caches (stats, widgets that contain photos, names and so on) */
		\IPS\Widget::deleteCaches();

		/* Send to the queue, include archived content */
		foreach ( \IPS\Content::routedClasses( FALSE, TRUE, FALSE ) as $class )
		{
			if ( isset( $class::$databaseColumnMap['author'] ) and ( $action == 'delete' or \in_array( 'IPS\Content\Hideable', class_implements( $class ) ) ) )
			{
				/* Comments run first when merging so rebuilding topic doesn't fail with incorrect author ID */
				switch( $action )
				{
					case 'hide':
						$order = 1;
						break;
					case 'merge':
						$order = ( !is_subclass_of( $class, '\IPS\Content\Comment' ) ) ? 3 : 2;
						break;
					case 'delete':
						$order = 1;
						break;
				}

				\IPS\Task::queue( 'core', 'MemberContent', array_merge( array( 'initiated_by_member_id' => \IPS\Member::loggedIn()->member_id, 'member_id' => $this->member_id, 'name' => $this->name, 'class' => $class, 'action' => $action ), $extra ), $order );
			}
		}

		/* And private messages */
		\IPS\Task::queue( 'core', 'MemberContent', array_merge( array( 'initiated_by_member_id' => \IPS\Member::loggedIn()->member_id, 'member_id' => $this->member_id, 'name' => $this->name, 'class' => 'IPS\\core\\Messenger\\Conversation', 'action' => $action ), $extra ), 2 );
	}
	
	/**
	 * Unflag as spammer
	 *
	 * @return	void
	 */
	public function unflagAsSpammer()
	{
		if ( $this->members_bitoptions['bw_is_spammer'] )
		{
			/* Save */
			$this->members_bitoptions['bw_is_spammer'] = FALSE;
			$this->save();
			
			/* Log */
			$this->logHistory( 'core', 'account', array( 'type' => 'spammer', 'set' => FALSE ) );

			/* Remove any pending hide or delete content queued tasks */
			foreach( \IPS\Db::i()->select( '*', 'core_queue', array( '`key`=?', 'MemberContent' ) ) as $task )
			{
				$data = json_decode( $task['data'], true );

				if( $data['member_id'] == $this->member_id )
				{
					\IPS\Db::i()->delete( 'core_queue', array( 'id=?', $task['id'] ) );
				}
			}
			
			/* Report back to spam service */
			if ( \IPS\Settings::i()->spam_service_enabled and \IPS\Settings::i()->spam_service_send_to_ips )
			{
				$this->spamService( 'notspam' );
			}

			/* Run sync */
			$this->memberSync( 'onUnSetAsSpammer' );
		}
	}

	/**
	 * Get auto-track data
	 *
	 * @return	array
	 */
	public function get_auto_follow()
	{
		return ( mb_substr( $this->_data['auto_track'], 0, 1 ) !== '{' ) ?
			array( 'method' => 'immediate', 'content' => 0, 'comments' => (int) $this->_data['auto_track'] ) :
			json_decode( $this->_data['auto_track'], TRUE );
	}
	
	/**
	 * Set banned
	 *
	 * @param	string	$value	Value
	 * @return	void
	 */
	public function set_temp_ban( $value )
	{
		$this->_data['temp_ban'] = $value;
		if ( $value == -1 )
		{
			\IPS\Db::i()->delete( 'core_validating', array( 'member_id=?', $this->member_id ) );
			
			if ( !\IPS\Db::i()->select( 'COUNT(*)', 'core_validating', array( 'user_verified=?', TRUE ) )->first() )
			{
				\IPS\core\AdminNotification::remove( 'core', 'NewRegValidate' );
			}
		}
		else
		{
			$this->members_bitoptions['validating'] = FALSE;
		}

		/* Revoke oAuth tokens */
		if( $value )
		{
			\IPS\Db::i()->update( 'core_oauth_server_access_tokens', array( 'status' => 'revoked' ), array( 'member_id=?', $this->member_id ) );
		}
	}
	
	/**
	 * Get Group Data
	 *
	 * @return	array
	 */
	public function get_group()
	{
		if ( $this->_group === NULL )
		{
			/* Load primary group */
			try
			{
				$group = \IPS\Member\Group::load( $this->_data['member_group_id'] );
			}
			catch ( \OutOfRangeException $e )
			{
				$group = \IPS\Member\Group::load( \IPS\Settings::i()->member_group );
			}
			
			$this->_group = array_merge( $group->data(), $group->g_bitoptions->asArray() );

			/* Merge in secondary group data */
			if ( !empty( $this->_data['mgroup_others'] ) )
			{
				$groups			= array_filter( explode( ',', $this->_data['mgroup_others'] ) );
				$exclude		= array();
				$lessIsMore		= array();
				$neg1IsBest		= array();
				$zeroIsBest		= array();
				$callback		= array();
	
				/* Get the limits we need to work out from apps */
				foreach ( \IPS\Application::allExtensions( 'core', 'GroupLimits', FALSE, 'core' ) as $key => $extension )
				{
					if( method_exists( $extension, 'getLimits' ) )
					{
						$appLimits = $extension->getLimits();
						
						if( !empty( $appLimits['neg1IsBest'] ) )
						{
							$neg1IsBest	= array_merge( $neg1IsBest, $appLimits['neg1IsBest'] );
						}
							
						if( !empty( $appLimits['zeroIsBest'] ) )
						{
							$zeroIsBest = array_merge( $zeroIsBest, $appLimits['zeroIsBest'] );
						}
							
						if( !empty( $appLimits['lessIsMore'] ) )
						{
							$lessIsMore	= array_merge( $lessIsMore, $appLimits['lessIsMore'] );
						}
							
						if( !empty( $appLimits['exclude'] ) )
						{
							$exclude = array_merge( $exclude, $appLimits['exclude'] );
						}
						
						if( !empty( $appLimits['callback'] ) )
						{
							$callback = array_merge( $callback, $appLimits['callback'] );
						}
					}
				}
				
				/* Do the merging */
				$skippedGroups	= array();
	
				foreach( $groups as $gid )
				{
					try
					{
						$group = \IPS\Member\Group::load( $gid );
					}
					catch( \OutOfRangeException $e )
					{
						$skippedGroups[]	= $gid;
						continue;
					}
	
					$_data = array_merge( $group->_data, $group->g_bitoptions->asArray() );
	
					foreach( $_data as $k => $v )
					{
						if ( ! \in_array( $k, $exclude ) )
						{
							if ( \in_array( $k, $zeroIsBest ) )
							{
								if ( empty( $this->_group[ $k ] ) )
								{
									continue;
								}
								else if( $v == 0 )
								{
									$this->_group[ $k ] = 0;
								}
								else if ( $v > $this->_group[ $k ] )
								{
									$this->_group[ $k ] = $v;
								}
							}
							else if( \in_array( $k, $neg1IsBest ) )
							{
								
								if ( $this->_group[ $k ] == -1 )
								{
									continue;
								}
								else if( $v == -1 )
								{
									$this->_group[ $k ] = -1;
								}
								else if ( $v > $this->_group[ $k ] )
								{
									$this->_group[ $k ] = $v;
								}
							}
							else if ( \in_array( $k, $lessIsMore ) )
							{
								if ( $v < $this->_group[ $k ] )
								{
									$this->_group[ $k ] = $v;
								}
							}
							else if ( array_key_exists( $k, $callback ) )
							{
								$callbackFunction = $callback[ $k ];
								$result = $callbackFunction( $this->_group, $_data, $k, $this->_data );
	
								if( \is_array( $result ) )
								{
									$this->_group	= array_merge( $this->_group, $result );
								}
								else if( $result !== NULL )
								{
									$this->_group[ $k ]	= $result;
								}
							}
							else
							{
								if ( !isset( $this->_group[ $k ] ) OR $v > $this->_group[ $k ] )
								{
									$this->_group[ $k ] = $v;
								}
							}
						}
					}
				}
	
				if( \count( $skippedGroups ) )
				{
					$this->mgroup_others = implode( ',', array_diff( $groups, $skippedGroups ) );
					
					parent::save();
				}
			}
		}

		return $this->_group;
	}

	/**
	 * Retrieve the group name
	 *
	 * @return string
	 */
	public function get_groupName()
	{
		if ( $this->_group === NULL )
		{
			$group = $this->group;
		}

		if( $this->_data['member_group_id'] )
		{
			$group = \IPS\Member\Group::load( $this->_data['member_group_id'] );
			$this->_group['name'] = $group->formatName( \IPS\Member::loggedIn()->language()->addToStack( "core_group_{$group->g_id}" ) );
		}

		return $this->_group['name'];
	}

	/**
	 * @brief	Cached groups check
	 */
	protected $_groups = NULL;
	
	/**
	 * Get an array of the group IDs (including secondary groups) this member belongs to
	 *
	 * @return	array
	 */
	public function get_groups()
	{
		if ( $this->_groups !== NULL )
		{
			return $this->_groups;
		}
		
		$this->_groups = array( $this->_data['member_group_id'] );

		if( $this->_data['mgroup_others'] )
		{
			foreach( explode( ',', $this->_data['mgroup_others'] ) as $id )
			{
				$this->_groups[] = \intval( $id );
			}
		}
		
		/* Sort for consistency when using permissions as part of a cache key */
		sort( $this->_groups, SORT_NUMERIC );
	
		return $this->_groups;
	}
	
	/**
	 * Social Groups
	 */
	protected $_socialGroups = NULL;
	
	/**
	 * Social Groups
	 *
	 * @param	bool	$bypassCache	Fetch directly from the database
	 * @return	array
	 */
	public function socialGroups( $bypassCache = FALSE )
	{
		if ( $this->_socialGroups === NULL )
		{
			/* If this is a guest, they will not have any social groups - save the query */
			if( !$this->member_id )
			{
				$this->_socialGroups = array();
			}
			else
			{
				if( $bypassCache )
				{
					$this->_socialGroups = iterator_to_array( \IPS\Db::i()
						->select( 'group_id', 'core_sys_social_group_members', array( 'member_id=?', $this->member_id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )
						->setKeyField( 'group_id' )
						->setValueField( 'group_id' ) );
				}
				else
				{
					$this->_socialGroups = array();

					foreach( $this->permissionArray() as $permissionRow )
					{
						if( mb_substr( $permissionRow, 0, 1 ) === 's' )
						{
							$groupId = (int) mb_substr( $permissionRow, 1 );

							$this->_socialGroups[ $groupId ] = $groupId;
						}
					}
				}
			}
		}
		return $this->_socialGroups;
	}
	
	/**
	 * Clubs
	 */
	protected $_clubs = array();
	
	/**
	 * Clubs
	 *
	 * @param	bool	$fromWriteServer	If Read/Write separation is enabled, this flag can be used to force reading from the write server, which can be used when rebuilding cached permission strings
	 * @param	bool	$moderatorOnly	If true will only return clubs the member is a modertor of
	 * @return	array
	 */
	public function clubs( $fromWriteServer = FALSE, $moderatorOnly = FALSE )
	{
		if ( !\IPS\Settings::i()->clubs )
		{
			return array();
		}
		if ( !isset( $this->_clubs[ \intval( $moderatorOnly ) ] ) )
		{
			/* If this is a guest, they will not have any clubs - save the query */
			if( !$this->member_id )
			{
				$this->_clubs[ \intval( $moderatorOnly ) ] = array();
			}
			else
			{
				$statuses = $moderatorOnly ? ( \IPS\Member\Club::STATUS_MODERATOR . "','" . \IPS\Member\Club::STATUS_LEADER ) : ( \IPS\Member\Club::STATUS_MEMBER . "','" . \IPS\Member\Club::STATUS_MODERATOR . "','" . \IPS\Member\Club::STATUS_LEADER ) ;
				
				$this->_clubs[ \intval( $moderatorOnly ) ] = iterator_to_array( \IPS\Db::i()->select( 'club_id', 'core_clubs_memberships', array( "member_id=? AND status IN('" . $statuses . "')", $this->member_id ), NULL, NULL, NULL, NULL, $fromWriteServer ? \IPS\Db::SELECT_FROM_WRITE_SERVER : 0 ) );
			}
		}
		return $this->_clubs[ \intval( $moderatorOnly ) ];
	}
	
	/**
	 * Permission Array
	 *
	 * @return	array
	 */
	public function permissionArray()
	{
		$return = $this->groups;

		if ( $this->member_id )
		{
			$return[] = "m{$this->member_id}";

			if ( \IPS\Settings::i()->clubs )
			{
				$return[] = "ca"; // Public clubs, which is everyone except guests
			}

			if ( $this->modPermission('can_access_all_clubs') )
			{
				$return[] = 'cm';
			}

			if ( $this->permission_array === NULL )
			{			
				$this->rebuildPermissionArray();
			}
			
			if( $this->permission_array )
			{
				$return = array_merge( $return, explode( ',', $this->permission_array ) );
			}
		}

		return $return;
	}
	
	/**
	 * Permission Array
	 *
	 * @return	void
	 */
	public function rebuildPermissionArray()
	{
		$permissionArray = array();
		foreach ( $this->socialGroups( TRUE ) as $socialGroupId )
		{
			$permissionArray[] = "s{$socialGroupId}";
		}
		if ( \IPS\Settings::i()->clubs )
		{
			/* Wipe club cache as when we are added, perm is rebuilt but the new club membership is not detected as we are using cached value */
			$this->_clubs = NULL;
			
			$clubs = $this->clubs( TRUE );
			if ( $clubs )
			{
				foreach ( $clubs as $clubId )
				{
					$permissionArray[] = "c{$clubId}";
				}
				foreach ( $this->clubs( TRUE, TRUE ) as $clubId )
				{
					$permissionArray[] = "cm{$clubId}";
				}
			}
		}
	
		$this->permission_array = implode( ',', $permissionArray );
		$this->save();
	}
	
	/**
	 * Get Joined Date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_joined()
	{
		return \IPS\DateTime::ts( $this->_data['joined'] );
	}
	
	/**
	 * Get SEO Name
	 *
	 * @return	string
	 */
	public function get_members_seo_name()
	{
		/* Set it so it will be saved */
		if( !isset( $this->_data['members_seo_name'] ) or !$this->_data['members_seo_name'] )
		{
			if ( !$this->name )
			{
				return NULL;
			}
			
			$this->members_seo_name	= \IPS\Http\Url\Friendly::seoTitle( $this->name );
		}

		return $this->_data['members_seo_name'] ?: \IPS\Http\Url\Friendly::seoTitle( $this->name );
	}

	/**
	 * Get localized birthday, taking into account optional year
	 *
	 * @return	string|null
	 */
	public function get_birthday()
	{
		try
		{
			if( $this->_data['bday_year'] )
			{
				$date	= new \IPS\DateTime( str_pad( $this->_data['bday_year'], 4, 0, STR_PAD_LEFT ) . str_pad( $this->_data['bday_month'], 2, 0, STR_PAD_LEFT ) . str_pad( $this->_data['bday_day'], 2, 0, STR_PAD_LEFT ) );

				return $date->fullYearLocaleDate();
			}
			else if( $this->_data['bday_month'] )
			{
				$date	= new \IPS\DateTime( $this->_data['bday_month'] . '/' . $this->_data['bday_day'] );

				return $date->dayAndMonth();
			}
			else
			{
				return NULL;
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Log::debug( "Member " . $this->id ." has a not valid birthday date" , 'birthday_error' );
			return NULL;
		}
	}

	/**
	 * Get the member's age
	 *
	 * @param	\IPS\DateTime|null	$date	If supplied, birthday is calculated from this point
	 * @note	If the member has not specified a birth year (which is optional), NULL is returned
	 * @return	int|null
	 */
	public function age( $date=NULL )
	{
		if( $this->_data['bday_year'] AND checkdate( $this->_data['bday_month'], $this->_data['bday_day'], $this->_data['bday_year'] ) )
		{
			/* We use dashes because DateTime accepts two digit years with it */
			$birthday	= new \IPS\DateTime( $this->_data['bday_year'] . '-' . $this->_data['bday_month'] . '-' . $this->_data['bday_day'] );
			$birthday->setTime( 0, 0, 1 );

			$today = $date ? new \IPS\DateTime( $date->format('Y') . '-' . $date->format('m') . '-' . $date->format('d') ) : new \IPS\DateTime();

			if( \IPS\Member::loggedIn()->timezone )
			{
				$today->setTimezone( new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) );
			}

			$today->setTime( 23, 59, 59 ); // We want how old they'll be at the end of the provided date

			return $birthday->diff( $today )->y;
		}
		else
		{
			return NULL;
		}
	}
			
	/**
	 * User's photo URL
	 *
	 * @param	bool	$thumb	Use thumbnail?
	 * @param	bool	$email	Is the photo going to be used in an email?
	 * @return string
	 */
	public function get_photo( $thumb=TRUE, $email=FALSE )
	{
		return static::photoUrl( $this->_data, $thumb, $email );
	}

	/**
	 * Set Photo Type
	 *
	 * @param	string	$type	Photo type
	 * @return	void
	 */
	public function set_pp_photo_type( $type )
	{
		if ( $this->_previousPhotoType === NULL and isset( $this->_data['pp_photo_type'] ) )
		{
			$this->_previousPhotoType = $this->_data['pp_photo_type'];
		}
		$this->_data['pp_photo_type'] = $type;
	}
	
	/**
	 * Set Photo
	 *
	 * @param	string	$photo	Photo location
	 * @return	void
	 */
	public function set_pp_main_photo( $photo )
	{
		$this->deletePhoto();
		
		$this->_data['pp_main_photo'] = $photo;
	}

	/**
	 * Delete the profile photo
	 *
	 * @return void
	 */
	public function deletePhoto()
	{
		/* It is common to update pp_photo_type before pp_main_photo */
		$photoType = ( $this->_previousPhotoType !== NULL ) ? $this->_previousPhotoType : $this->_data['pp_photo_type'];
		
		/* Attempt to delete existing images if they are from a profile sync, or uploaded/imported from URL */
		if ( mb_substr( $photoType, 0, 5 ) === 'sync-' or $photoType === 'custom' or $photoType === 'letter' )
		{
			if ( $this->_data['pp_main_photo'] )
			{
				try
				{
					\IPS\File::get( 'core_Profile', $this->_data['pp_main_photo'] )->delete();
				}
				catch ( \Exception $e ) {}
			}
			if ( $this->_data['pp_thumb_photo'] )
			{
				try
				{
					\IPS\File::get( 'core_Profile', $this->_data['pp_thumb_photo'] )->delete();
					$this->_data['pp_thumb_photo'] = NULL;
				}
				catch ( \Exception $e ) {}
			}
		}
	}
	
	/**
	 * Get reputation points
	 *
	 * @return	int
	 */
	public function get_pp_reputation_points()
	{
		return isset( $this->_data['pp_reputation_points'] ) ? (int) $this->_data['pp_reputation_points'] : 0;
	}
	
	/**
	 * Get warning points
	 *
	 * @return	int
	 */
	public function get_warn_level()
	{
		return isset( $this->_data['warn_level'] ) ? (int) $this->_data['warn_level'] : 0;
	}
	
	/**
	 * Get failed login details
	 *
	 * @return	array
	 */
	public function get_failed_logins()
	{
		return json_decode( $this->_data['failed_logins'], TRUE ) ?: array();
	}

	/**
	 * Get member title
	 *
	 * @return		array
	 * @deprecated	Use rank() instead
	 */
	public function get_rank()
	{
		if ( $rank = $this->rank() )
		{
			return [ 'title' => $rank->_title, 'image' => $rank->html() ];
		}
		return [ 'title' => NULL, 'image' => NULL ];
	}
	
	/**
	 * Get member location
	 *
	 * @return	string|null
	 */
	public function get_location()
	{
		return $this->location();
	}
	
	/**
	 * Get members posts for today
	 *
	 * @return	array
	 */
	public function get_members_day_posts()
	{
		return explode( ',', $this->_data['members_day_posts'] );
	}
	
	/**
	 * Set members posts for today
	 *
	 * @param	array	$value	Array of daily post data. Index 0 is the amount of posts posted in this time period, and optional index 1 is a timestamp of when we started counting
	 * @return	void
	 */
	public function set_members_day_posts( $value )
	{
		/* Are we updating time? */
		if ( ! isset( $value[1] ) )
		{
			$value[1] = $this->members_day_posts[1];
		}
		
		$this->_data['members_day_posts'] = implode( ',', $value );
	}
	
	/**
	 * Get member's default stream
	 *
	 * @return	int|null
	 */
	public function get_defaultStream()
	{
		if ( $this->defaultStreamId === FALSE )
		{
			if ( $this->member_streams and $streams = json_decode( $this->member_streams, TRUE ) and \count( $streams ) )
			{
				$this->defaultStreamId = ( isset( $streams['default'] ) ? $streams['default'] : NULL );
			}
			else
			{
				$this->defaultStreamId = NULL;
			}
		}
		
		return $this->defaultStreamId;
	}
	
	/**
	 * Set member's default stream
	 *
	 * @param	null|int	$value	Null or stream ID. 0 is for 'all activity'
	 * @return	void
	 */
	public function set_defaultStream( $value )
	{
		if ( $this->member_streams and $streams = json_decode( $this->member_streams, TRUE ) and \count( $streams ) )
		{
			$streams['default'] = $value;
		}
		else
		{
			$streams = array( 'streams' => array(), 'default' => $value );
		}
		
		$this->member_streams = json_encode( $streams );
		$this->save();
		
		$this->defaultStreamId = $value;
	}
	
	/**
	 * Get member location
	 *
	 * @return	string|null
	 */
	public function location()
	{
		if( $this->sessionData === FALSE )
		{
			return NULL;
		}

		if( $this->sessionData === NULL )
		{
			$this->sessionData = \IPS\Session\Store::i()->getLatestMemberSession( $this->member_id );
		}
		
		return ( $this->sessionData ) ? \IPS\Session::i()->getLocation( $this->sessionData ) : NULL;
	}

	/**
	 * Get validating description
	 *
	 * @param 	null	$validatingRow
	 * @return 	string
	 */
	public function validatingDescription( $validatingRow=NULL )
	{
		try
		{
			$validatingRow = ( $validatingRow ) ?: \IPS\Db::i()->select( '*', 'core_validating', array( 'member_id=?', $this->member_id ) )->first();
		}
		catch( \UnderflowException $ex )
		{
			return '';
		}
		
		$validatingDescription = '';
		if ( $validatingRow['new_reg'] )
		{
			if ( $validatingRow['reg_cancelled'] )
			{
				$validatingDescription = \IPS\Member::loggedIn()->language()->addToStack('members_validating_cancelled');
			}
			elseif ( $validatingRow['user_verified'] )
			{
				$validatingDescription = \IPS\Member::loggedIn()->language()->addToStack('members_validating_admin');
			}
			else
			{
				$validatingDescription = \IPS\Member::loggedIn()->language()->addToStack('members_validating_user');
			}
	
			if ( $validatingRow['coppa_user'] )
			{
				$validatingDescription .= \IPS\Member::loggedIn()->language()->addToStack('members_validating_coppa');
			}
	
			if ( $validatingRow['spam_flag'] )
			{
				$validatingDescription .= \IPS\Member::loggedIn()->language()->addToStack('members_validating_spam');
			}
		}
		elseif ( $validatingRow['email_chg'] )
		{
			$validatingDescription .= \IPS\Member::loggedIn()->language()->addToStack('members_validating_email_chg');
		}
		
		return $validatingDescription;
	}

	/**
	 * Followers Count
	 *
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @return	int
	 */
	public function followersCount( $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL )
	{
		if( $this->members_bitoptions['pp_setting_moderate_followers'] )
		{
			return 0;
		}

		/* Return the count */
		return static::_followersCount( 'member', $this->member_id, $privacy, $frequencyTypes, $date );
	}

	/**
	 * Followers
	 *
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @param	int|array				$limit			LIMIT clause
	 * @param	string					$order			Column to order by
	 * @return	\IPS\Db\Select|NULL
	 * @throws	\BadMethodCallException
	 */
	public function followers( $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL, $limit=array( 0, 25 ), $order=NULL )
	{
		if( $this->members_bitoptions['pp_setting_moderate_followers'] )
		{
			return NULL;
		}
		
		return static::_followers( 'member', $this->member_id, $privacy, $frequencyTypes, $date, $limit, $order );
	}
	
	/**
	 * Set failed login details
	 *
	 * @param	array	$data	Data
	 */
	public function set_failed_logins( $data )
	{
		$this->_data['failed_logins'] = json_encode( $data );
		
		$highest = 0;
		foreach ( $data as $ipAddress => $times )
		{
			if ( $highest < \count( $times ) )
			{
				$highest = \count( $times );
			}
		}
		$this->failed_login_count = $highest;
	}
	
	/**
	 * Get MFA details
	 *
	 * @return	array
	 */
	public function get_mfa_details()
	{
		return json_decode( $this->_data['mfa_details'], TRUE ) ?: array();
	}
	
	/**
	 * Set MFA details
	 *
	 * @param	array	$data	Data
	 */
	public function set_mfa_details( $data )
	{
		$this->_data['mfa_details'] = json_encode( $data );
	}
	
	/**
	 * Get profile sync settings
	 *
	 * @return	array
	 */
	public function get_profilesync()
	{
		$return = isset( $this->_data['profilesync'] ) ? ( json_decode( $this->_data['profilesync'], TRUE ) ?: array() ) : array();
		
		if ( isset( $return['facebook'] ) or isset( $return['Facebook'] ) or isset( $return['google'] ) or isset( $return['Google'] ) or isset( $return['linkedin'] ) or isset( $return['Linkedin'] ) or isset( $return['LinkedIn'] ) or isset( $return['microsoft'] ) or isset( $return['Microsoft'] ) or isset( $return['live'] ) or isset( $return['Live'] ) or isset( $return['twitter'] ) or isset( $return['Twitter'] ) )
		{
			$newVal = array();
			foreach ( $return as $loginKey => $prefs )
			{
				$classname = NULL;
				switch ( mb_strtolower( $loginKey ) )
				{
					case 'internal':
						$classname = 'IPS\\Login\\Handler\\Standard';
						break;
					case 'facebook':
						$classname = 'IPS\\Login\\Handler\\OAuth2\\Facebook';
						break;
					case 'google':
						$classname = 'IPS\\Login\\Handler\\OAuth2\\Google';
						break;
					case 'linkedin':
						$classname = 'IPS\\Login\\Handler\\OAuth2\\LinkedIn';
						break;
					case 'live':
					case 'microsoft':
						$classname = 'IPS\\Login\\Handler\\OAuth2\\Microsoft';
						$settings['legacy_redirect'] = TRUE;
						break;
					case 'twitter':
						$classname = 'IPS\\Login\\Handler\\OAuth1\\Twitter';
						break;
				}
				
				if ( $classname and class_exists( $classname ) )
				{
					try
					{
						$methodId = \IPS\Db::i()->select( 'login_id', 'core_login_methods', array( 'login_classname=? AND login_enabled=1', $classname ) )->first();
						
						foreach ( $prefs as $option => $v )
						{
							if ( $v )
							{
								$newVal[ $option ] = array( 'handler' => $methodId, 'ref' => NULL, 'error' => NULL );
							}
						}
					}
					catch ( \UnderflowException $e ) { }
				}
			}
			
			$this->_data['profilesync'] = json_encode( $newVal );
			return $newVal;
		}
		
		return $return;
	}
	
	/**
	 * Set profile sync settings
	 *
	 * @param	array	$data	Data
	 */
	public function set_profilesync( $data )
	{
		$this->_data['profilesync'] = json_encode( $data );
		
		if ( !$data )
		{
			$this->_data['profilesync_lastsync'] = 0;
		}
	}
	
	/**
	 * Get timezone
	 *
	 * @return	string
	 */
	public function get_timezone()
	{
		return \IPS\DateTime::getFixedTimezone( $this->_data['timezone'] );
	}
	
	/* !Photos */
	
	/**
	 * Columns needed to build photos
	 *
	 * @return	array
	 */
	public static function columnsForPhoto()
	{
		return array( 'member_id', 'name', 'members_seo_name', 'member_group_id', 'mgroup_others', 'pp_photo_type', 'pp_main_photo', 'pp_thumb_photo' );
	}
	
	/**
	 * Get photo from data
	 *
	 * @param	array	$memberData			Array of member data, must include values for at least the keys returned by columnsForPhoto()
	 * @param	bool	$thumb				Use thumbnail?
	 * @param	bool	$email				Is the photo going to be used in an email?
	 * @param	bool	$useDefaultPhoto	If there is no photo, should the default (rather than NULL) be returned?
	 * @return	string
	 */
	public static function photoUrl( $memberData, $thumb=TRUE, $email=FALSE, $useDefaultPhoto=TRUE )
	{
		$photoUrl = NULL;

		/* All this only applies to members... */
		if ( isset( $memberData['member_id'] ) and $memberData['member_id'] )
		{
			/* We have an uploaded or synced photo */
			if ( $memberData['pp_main_photo'] and ( mb_substr( $memberData['pp_photo_type'], 0, 5 ) === 'sync-' or $memberData['pp_photo_type'] === 'custom' ) )
			{
				try
				{
					$photoUrl = \IPS\File::get( 'core_Profile', ( $thumb and $memberData['pp_thumb_photo'] ) ? $memberData['pp_thumb_photo'] : $memberData['pp_main_photo'] )->url;
				}
				catch ( \InvalidArgumentException $e ) { }
			}
			/* Letter photos are enabled and we do not have a photo set, but only if this isn't for email */
			elseif( \IPS\Settings::i()->letter_photos == 'letters' AND $email === FALSE and isset( $memberData['name'] ) )
			{
				if( $photo = static::generateLetterPhoto( $memberData ) )
				{
					$photoUrl = $photo;
				}
			}
			/* Other - This allows an app (such as Gallery) to set the pp_photo_type to a storage container to support custom images without duplicating them */
			elseif( $memberData['pp_photo_type'] and $memberData['pp_photo_type'] != 'none' and mb_strpos( $memberData['pp_photo_type'], '_' ) !== FALSE )
			{
				try
				{
					$photoUrl = \IPS\File::get( $memberData['pp_photo_type'], $memberData['pp_main_photo'] )->url;
				}
				catch ( \InvalidArgumentException $e )
				{				
					/* If there was an exception, clear these values out - most likely the image or storage container is no longer valid */
					$member = \IPS\Member::load( $memberData['member_id'] );
					$member->pp_photo_type	= NULL;
					$member->pp_main_photo	= NULL;
					$member->save();
				}
			}

			/* Return */
			if( $photoUrl !== NULL )
			{
				return (string) $photoUrl;
			}
		}

		/* Still here? Return default photo */
		if ( !$photoUrl and $useDefaultPhoto )
		{
			if( $email )
			{
				return rtrim( \IPS\Settings::i()->base_url, '/' ) . '/applications/core/interface/email/default_photo.png';
			}
			else
			{
				if( \IPS\Settings::i()->letter_photos == 'letters' AND isset( $memberData['member_id'] ) AND $memberData['member_id'] AND isset( $memberData['name'] ) AND $memberData['name'] )
				{
					if( $photo = static::generateLetterPhoto( $memberData ) )
					{
						return (string) $photo;
					}
				}

				return (string) \IPS\Theme::i()->resource( 'default_photo.png', 'core', 'global' );
			}
		}
		return NULL;
	}

	/**
	 * Generate a letter photo (SVG)
	 *
	 * @param	array 	$memberData	Member data
	 * @param	bool	$returnJson		Return the letter and color as an array, instead of the SVG string
	 * @return	string|array
	 */
	public static function generateLetterPhoto( $memberData, $returnJson=FALSE )
	{
		/* Get the letter we'll use */
		$letter = static::getLettersForPhoto( $memberData['name'] );

		/* Have we already cached the color? */
		if( isset( $memberData['pp_main_photo'] ) AND $memberData['pp_main_photo'] AND \strlen( $memberData['pp_main_photo'] ) === 6 )
		{
			$color = $memberData['pp_main_photo'];
		}
		else
		{
			/* Generate a new unique color */
			$photo = new \IPS\Member\LetterPhoto( $memberData['name'] );

			$color = $photo->generateColorCode();

			/* Save the color for next time */
			if( isset( $memberData['member_id'] ) AND $memberData['member_id'] )
			{
				$member = static::load( $memberData['member_id'] );

				if( $member->member_id )
				{
					$member->pp_photo_type = 'none';
					$member->pp_main_photo = $color;
					$member->save();
				}
			}
		}

		if( $returnJson === TRUE )
		{
			return array( 'letter' => $letter, 'color' => $color );
		}

		return "data:image/svg+xml," . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" style="background:#' . $color . '"><g><text text-anchor="middle" dy=".35em" x="512" y="512" fill="#ffffff" font-size="700" font-family="-apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif">' . $letter . '</text></g></svg>' );
	}

	/**
	 * Return the first X non-punctuation characters of the name
	 *
	 * @param	string	$name		Name to use
	 * @param	int		$letters	Number of letters to return
	 * @return	string|NULL
	 */
	public static function getLettersForPhoto( $name, $letters=1 )
	{
		$name = str_replace( array( '<', '>', '=', '-', '+', '"', "'" ), '', $name );
		$name = preg_replace( "/(\pP)+/u", '', trim( $name ) );

		return $name ? mb_strtoupper( mb_substr( $name, 0, $letters ) ) : NULL;
	}

	/* !Get Calculated Properties */
	
	/**
	 * Get administrators
	 *
	 * @return	array
	 */
	public static function administrators()
	{
		if ( !isset( \IPS\Data\Store::i()->administrators ) )
		{
			\IPS\Data\Store::i()->administrators = array(
				'm'	=> iterator_to_array( \IPS\Db::i()->select( '*', 'core_admin_permission_rows', array( 'row_id_type=?', 'member' ) )->setKeyField( 'row_id' ) ),
				'g'	=> iterator_to_array( \IPS\Db::i()->select( '*', 'core_admin_permission_rows', array( 'row_id_type=?', 'group' ) )->setKeyField( 'row_id' ) ),
			);
		}
		return \IPS\Data\Store::i()->administrators;
	}
	
	/**
	 * Is an admin?
	 *
	 * @return	bool
	 */
	public function isAdmin()
	{
		return $this->acpRestrictions() !== FALSE;
	}

	/**
	 * @brief	Cache the session data if we pull it for location, etc.
	 */
	protected $sessionData	= NULL;
	
	/**
	 * Is online?
	 *
	 * @return	bool
	 */
	public function isOnline()
	{
		if( !$this->member_id )
		{
			return FALSE;
		}

		if ( $this->sessionData === NULL )
		{
	    	$this->sessionData	= \IPS\Session\Store::i()->getLatestMemberSession( $this->member_id );
		}
		
		if( $this->sessionData === FALSE )
		{
			return FALSE;
		}

		$diff = \IPS\DateTime::ts( $this->last_activity )->diff( \IPS\DateTime::create() );
		if ( $diff->y or $diff->m or $diff->d or $diff->h or $diff->i > 15 )
		{
			return FALSE;
		}
		return TRUE;
	}
	
	/**
	 * Is Online Anonymously
	 *
	 * @return	bool
	 */
	public function isOnlineAnonymously()
	{
		if ( !$this->member_id )
		{
			return FALSE;
		}

		/* If the group is forced to be anonymous */
		if( $this->group['g_hide_online_list'] == 1 )
		{
			return TRUE;
		}

		/* Anonymous status is not allowed */
		if( $this->group['g_hide_online_list'] == 2 )
		{
			return FALSE;
		}
		
		return $this->members_bitoptions['is_anon'];
	}
	
	/**
	 * Is banned?
	 * If is banned until a certain time, returns an \IPS\DateTime object
	 *
	 * @return	FALSE|\IPS\DateTime|TRUE
	 */
	public function isBanned()
	{
		if ( $this->temp_ban != 0 )
		{
			if ( $this->temp_ban != -1 and time() >= $this->temp_ban )
			{
				$this->temp_ban = 0;
				$this->save();
				return FALSE;
			}
			elseif ( $this->temp_ban > 0 )
			{
				return \IPS\DateTime::ts( $this->temp_ban );
			}
			
			return TRUE;
		}

		if( !$this->group['g_view_board'] )
		{
			return TRUE;
		}
		
		return FALSE;
	}
		
	/**
	 * Is the member in a certain group (including secondary groups)
	 *
	 * @param	int|\IPS\Member\Group|array	$group				The group, or array of groups
	 * @param	bool						$permissionArray	If TRUE, checks the permission array rather than the groups
	 * @return	bool
	 */
	public function inGroup( $group, $permissionArray=FALSE )
	{
		$group = array_filter( \is_array( $group ) ? $group : array( $group ) );
		$check = array_filter( $permissionArray ? $this->permissionArray() : $this->groups );

		foreach ( $group as $_group )
		{
			$groupId = ( $_group instanceof \IPS\Member\Group ) ? $_group->g_id : $_group;

			if ( \in_array( $groupId, $check ) )
			{
				return TRUE;
			}
		}
		
		return FALSE;
	}

	/**
	 * Store a reference to the language object
	 */
	protected $_lang	= NULL;
	
	/**
	 * Return the language object to use for this member - returns default if member has not selected a language
	 *
	 * @param	bool	$frontOnly	If TRUE, will only look at the the langauge for the front-end, not the AdminCP
	 * @return	\IPS\Lang
	 */
	public function language( $frontOnly=FALSE )
	{
		/* Did we already load the language object? */
		if( $this->_lang !== NULL )
		{
			return $this->_lang;
		}
		
		/* If in setup, create a "dummy" language */
		if ( \IPS\Dispatcher::hasInstance() and class_exists( 'IPS\Dispatcher', FALSE ) AND \IPS\Dispatcher::i()->controllerLocation === 'setup' AND \IPS\Dispatcher::i()->setupLocation === 'install' )
		{
			$this->_lang = \IPS\Lang::setupLanguage();
			return $this->_lang;
		}
		else if ( \IPS\Dispatcher::hasInstance() and class_exists( 'IPS\Dispatcher', FALSE ) AND \IPS\Dispatcher::i()->controllerLocation === 'setup' AND \IPS\Dispatcher::i()->setupLocation === 'upgrade' )
		{
			$this->_lang = \IPS\Lang::upgraderLanguage();
			return $this->_lang;
		}

		/* Work out if we are getting the ACP language or the normal language */
		$column	= 'language';
		if ( !$frontOnly and \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation == 'admin' and $this->member_id and $this->member_id == static::loggedIn()->member_id )
		{
			$column	= 'acp_language';
		}
		
		/* If the member has a language set, try that */
		if( $this->calculatedLanguageId !== NULL or $this->$column )
		{
			try
			{
				$this->_lang	= \IPS\Lang::load( $this->calculatedLanguageId ?: $this->$column );

				/* Disabled Languages are allowed to be used in the ACP */
				if( $this->_lang->enabled OR $column == 'acp_language' )
				{
					return $this->_lang;
				}
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		/* Otherwise, if this is us, try looking at HTTP_ACCEPT_LANGUAGE, if enabled */
		if ( \IPS\Dispatcher::hasInstance() and $this->member_id == static::loggedIn()->member_id and \IPS\Settings::i()->lang_auto_detect )
		{
			/* Work out what's in HTTP_ACCEPT_LANGUAGE */
			$preferredLanguage = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? \IPS\Lang::autoDetectLanguage( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) : NULL;
			
			/* If we worked one out, use that and save it on the account so it gets used for emails etc */
			if ( $preferredLanguage )
			{
				$this->calculatedLanguageId = $preferredLanguage;
				
				if ( $this->member_id )
				{
					$this->$column = $preferredLanguage;
					$this->save();
				}
			}
			/* Otherwise, just use the default */
			else
			{
				$this->calculatedLanguageId = \IPS\Lang::defaultLanguage();
			}
		}		
		else
		{
			/* Just return the default language */
			$this->calculatedLanguageId = \IPS\Lang::defaultLanguage();
		}
		
		/* Set it */
		$this->_lang = \IPS\Lang::load( $this->calculatedLanguageId );
		
		/* Add upgrader language bits if appropriate */
		if ( \IPS\Dispatcher::hasInstance() AND class_exists( 'IPS\Dispatcher', FALSE ) AND \IPS\Dispatcher::i()->controllerLocation === 'setup' AND \IPS\Dispatcher::i()->setupLocation === 'upgrade' )
		{
			$this->_lang->upgraderLanguage();
		}
		
		/* Return */
		return $this->_lang;
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * Get URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		if( $this->_url === NULL )
		{
			$this->_url = \IPS\Http\Url::internal( "app=core&module=members&controller=profile&id={$this->member_id}", 'front', 'profile', $this->members_seo_name );
		}

		return $this->_url;
	}
	
	/**
	 * URL to ACP "Edit Member"
	 *
	 * @return	\IPS\Http\Url
	 */
	public function acpUrl()
	{
		return \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=view&id={$this->member_id}", 'admin' );
	}
	
	/**
	 * HTML link to profile with hovercard
	 *
	 * @param	string|NULL		$warningRef			The reference key for warnings
	 * @param	boolean|NULL 	$groupFormatting		Apply the group prefix/suffix to the name?
	 * @param	boolean|NULL 	$anonymous			Is this shown with anonymous content?
	 * @return	string
	 */
	public function link( $warningRef=NULL, $groupFormatting=NULL, $anonymous=FALSE )
	{
		$groupFormatting = ( $groupFormatting === NULL ) ? ( ( \IPS\Settings::i()->group_formatting == 'legacy' ) ? FALSE : TRUE ) : $groupFormatting;

		if ( !\IPS\Settings::i()->warn_on )
		{
			$warningRef = NULL;
		}
		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userLink( $this, $warningRef, $groupFormatting, $anonymous );
	}
	
	/**
	 * Profile Fields shown next to users content
	 */
	public $rawProfileFieldsData = NULL;

	/**
	 * Profile Fields
	 *
	 * @param	int			$location	\IPS\core\ProfileFields\Field::PROFILE for profile, \IPS\core\ProfileFields\Field::REG for registration screen, \IPS\core\ProfileFields\Field::STAFF for ModCP/ACP, \IPS\core\ProfileFields\Field::EDIT for member editing
	 * @param	bool		$raw		Returns the raw value if true or the display value if false. Useful for comparisons for field types like Yes/NO to see if a value is set.
	 * @return	array
	 */
	public function profileFields( $location = 0, $raw = FALSE )
	{
		if ( !$this->member_id )
		{
			return array();
		}

		if( $this->rawProfileFieldsData !== NULL )
		{
			return $this->rawProfileFieldsData;
		}

		$this->rawProfileFieldsData = array();
		$values = array();
		
		try
		{
			$values = \IPS\Db::i()->select( '*', 'core_pfields_content', array( 'member_id = ?', $this->member_id ) )->first();
		}
		catch ( \UnderflowException $e ) {}

		if( !empty( $values ) )
		{
			foreach ( \IPS\core\ProfileFields\Field::values( $values, $location, $raw ) as $group => $fields )
			{
				$this->rawProfileFieldsData[ 'core_pfieldgroups_' . $group ] =  $fields;
			}
		}

		return $this->rawProfileFieldsData;
	}

	/**
	 * Profile Fields shown next to users content
	 */
	public $profileFields;

	/**
	 * Profile Fields shown next to users content
	 *
	 * @param	array $fieldValues	Field values already fetched from the database
	 *
	 * @return array
	 */
	public function contentProfileFields( $fieldValues=NULL )
	{
		if ( $this->profileFields === NULL )
		{
			$this->profileFields = array();
			if ( $this->member_id AND \IPS\core\ProfileFields\Field::fieldsForContentView() )
			{
				$select = '*';

				/* Can we view private fields? */
				if( !\IPS\Dispatcher::hasInstance() OR !( \IPS\Member::loggedIn()->isAdmin() OR \IPS\Member::loggedIn()->member_id === $this->member_id ) )
				{
					$select = 'member_id';
					$publicFields = \IPS\Db::i()->select( '*', 'core_pfields_data', array( 'pf_member_hide != ?', 'hide' ) );
					foreach( $publicFields as $field )
					{
						if( $field['pf_topic_hide'] == 'all' OR ( $field['pf_topic_hide'] == 'staff' AND ( \IPS\Member::loggedIn()->isAdmin() OR \IPS\Member::loggedIn()->modPermissions() ) )
						  )
						{
							$select .= ", field_{$field['pf_id']}";
						}
					}
				}
				
				if ( $fieldValues !== NULL and \is_array( $fieldValues ) )
				{
					if ( $select == '*' )
					{
						$values = $fieldValues;
					}
					else
					{
						$fields = explode( ', ', $select );
						$values = array();
						foreach( $fields as $f )
						{
							if ( isset( $fieldValues[ $f ] ) )
							{
								$values[ $f ] = $fieldValues[ $f ];
							}
						}
					}
				}
				else
				{
					try
					{
						$values = \IPS\Db::i()->select( $select, 'core_pfields_content', array( 'member_id = ?', $this->member_id ) )->first();
					}
					catch ( \UnderflowException $e ) {}
				}
				
				if ( \is_array( $values ) )
				{
					$this->setProfileFieldValuesInMemory( $values );
				}
			}
		}

		return $this->profileFields;
	}
	
	/**
	 * Store profile field values in memory
	 *
	 * @param	array	$values
	 * @return	void
	 */
	public function setProfileFieldValuesInMemory( array $values )
	{
		$this->profileFields = array();

		$values = array_filter( $values, function ( $val) { return ( $val !== '' AND $val !== NULL ); } );

		if( !empty( $values ) )
		{
			/* Make sure member_id is set */
			$values['member_id'] = $this->member_id;

			foreach ( \IPS\core\ProfileFields\Field::values( $values, \IPS\core\ProfileFields\Field::CONTENT ) as $group => $fields )
			{
				$this->profileFields[ 'core_pfieldgroups_' . $group ] = $fields;
			}
		}
	}
		
	/**
	 * IP Addresses
	 *
	 * @code
	 	return array(
	 		'::1' => array(
		 		'count'		=> ...	// int (number of times this member has used this IP)
		 		'first'		=> ... 	// \IPS\DateTime (first use)
		 		'last'		=> ... 	// \IPS\DateTime (last use)
		 	),
		 	...
	 	);
	 * @endcode
	 * @return	array
	 */
	public function ipAddresses()
	{
		$return = array();
		
		foreach ( \IPS\Application::allExtensions( 'core', 'IpAddresses' ) as $class )
		{
			$results	= $class->findByMember( $this );

			if( $results === NULL )
			{
				continue;
			}

			foreach ( $results as $ip => $data )
			{
				if ( isset( $return[ $ip ] ) )
				{
					$return[ $ip ]['count'] += $data['count'];
					if ( $data['first'] < $return[ $ip ]['first'] )
					{
						$return[ $ip ]['first'] = $data['first'];
					}
					if ( $data['last'] > $return[ $ip ]['last'] )
					{
						$return[ $ip ]['last'] = $data['last'];
					}
				}
				else
				{
					if ( $ip )
					{
						$return[ $ip ] = $data;
					}
				}
			}
		}
		
		return $return;
	}
	
	/**
	 * Mark the entire site as read
	 *
	 * @return void
	 */
	public function markAllAsRead()
	{
		/* Delete all member markers */
		\IPS\Db::i()->delete( 'core_item_markers', array( 'item_member_id=?', $this->member_id ) );
		
		$this->marked_site_read = time();
		$this->save();
	}
	
	/**
	 * Get read/unread markers
	 *
	 * @param	string	$app	Application key
	 * @param	string	$key	Marker key
	 * @return	array
	 */
	public function markersItems( $app, $key )
	{
		if ( !isset( $this->markers[ $app ] ) or !array_key_exists( $key, $this->markers[ $app ] ) )
		{
			try
			{
				$marker = \IPS\Db::i()->select( '*', 'core_item_markers', array( 'item_key=? AND item_member_id=? AND item_app=?', $key, $this->member_id, $app ) )->first();
				$this->markers[ $app ][ $key ] = $marker;
			}
			catch ( \UnderflowException $e )
			{
				$this->markers[ $app ][ $key ] = NULL;
			}
		}
		return $this->markers[ $app ][ $key ] ? json_decode( $this->markers[ $app ][ $key ]['item_read_array'], TRUE ) : array();
	}
	
	/**
	 * Get read/unread markers for containers
	 *
	 * @param	string|NULL	$app	Application key or NULL for all applications
	 * @return	array
	 */
	public function markersResetTimes( $app )
	{
		if ( ( !$app and !$this->haveAllMarkers ) or ( $app and !isset( $this->markersResetTimes[ $app ] ) ) )
		{
			try
			{
				$where = array( array( 'item_member_id=?', $this->member_id ) );
				if ( $app )
				{
					$this->markersResetTimes[ $app ] = array();
					$where[] = array( 'item_app=?', $app );
				}
				else
				{
					$this->markersResetTimes = array();
				}

				foreach ( \IPS\Db::i()->select( '*', 'core_item_markers', $where ) as $row )
				{
					$this->setMarkerResetTimes( $row );
				}
				
				if ( !$app )
				{
					$this->haveAllMarkers = TRUE;
				}
			}
			catch ( \UnderflowException $e )
			{
				if ( $app )
				{
					$this->markersResetTimes[ $app ] = array();
				}
				else
				{
					$this->markersResetTimes = array();
				}
			}
		}
		
		if ( $app )
		{
			return $this->markersResetTimes[ $app ];
		}
		else
		{
			return $this->markersResetTimes;
		}
	}

	/**
	 * Set read/unread markers for containers
	 *
	 * @param	array	$row	Row of marker data
	 * @return	void
	 */
	public function setMarkerResetTimes( $row )
	{
		if( !isset( $this->markersResetTimes[ $row['item_app'] ] ) or !\is_array( $this->markersResetTimes[ $row['item_app'] ] ) )
		{
			$this->markersResetTimes[ $row['item_app'] ] = array();
		}

		if ( $row['item_app_key_1'] )
		{
			if ( $row['item_app_key_2'] )
			{
				if( !isset( $this->markersResetTimes[ $row['item_app'] ][ $row['item_app_key_1'] ] ) OR !\is_array( $this->markersResetTimes[ $row['item_app'] ][ $row['item_app_key_1'] ] ) )
				{
					$this->markersResetTimes[ $row['item_app'] ][ $row['item_app_key_1'] ]	= array();
				}

				if ( $row['item_app_key_3'] )
				{
					if( !isset( $this->markersResetTimes[ $row['item_app'] ][ $row['item_app_key_1'] ][ $row['item_app_key_2'] ] ) OR !\is_array( $this->markersResetTimes[ $row['item_app'] ][ $row['item_app_key_1'] ][ $row['item_app_key_2'] ] ) )
					{
						$this->markersResetTimes[ $row['item_app'] ][ $row['item_app_key_1'] ][ $row['item_app_key_2'] ]	= array();
					}

					$this->markersResetTimes[ $row['item_app'] ][ $row['item_app_key_1'] ][ $row['item_app_key_2'] ][ $row['item_app_key_3'] ] = $row['item_global_reset'];
				}
				else
				{
					$this->markersResetTimes[ $row['item_app'] ][ $row['item_app_key_1'] ][ $row['item_app_key_2'] ] = $row['item_global_reset'];
				}
			}
			else
			{
				$this->markersResetTimes[ $row['item_app'] ][ $row['item_app_key_1'] ] = $row['item_global_reset'];
			}
		}
		else
		{
			$this->markersResetTimes[ $row['item_app'] ] = $row['item_global_reset'];
		}

		$this->markers[ $row['item_app'] ][ $row['item_key'] ] = $row;
	}
	
	/**
	 * Get Warnings
	 *
	 * @param	int			$limit			The number to get
	 * @param	bool|NULL	$acknowledged	If true, will only get warnings that have been acknowledged, if false will only get warnings that have not been knowledged. If NULL, will get both.
	 * @param	string|NULL	$type			If specified, will only pull warnings that applied a specific action.
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public function warnings( $limit, $acknowledged=NULL, $type=NULL )
	{
		if ( !$this->member_id )
		{
			return array();
		}
		
		if ( !\IPS\Settings::i()->warn_on )
		{
			return array();
		}
		
		$where = array( array( 'wl_member=?', $this->member_id ) );
		if ( $acknowledged !== NULL )
		{
			$where[] = array( 'wl_acknowledged=?', $acknowledged );
		}
		
		switch ( $type )
		{
			case 'mq':
				$where[] = array( 'wl_mq IS NOT NULL' );
				break;
			case 'rpa':
				$where[] = array( 'wl_rpa IS NOT NULL' );
				break;
			case 'suspend':
				$where[] = array( 'wl_suspend IS NOT NULL' );
				break;
		}
				
		return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members_warn_logs', $where, 'wl_date DESC', $limit, NULL, NULL, \IPS\Db::SELECT_DISTINCT ), 'IPS\core\Warnings\Warning' );
	}

	/**
	 * @brief	Cached reputation data
	 */
	protected $_reputationData	= NULL;
	
	/**
	 * Calculate and cache the member's reputation level data
	 *
	 * @return	void
	 */
	protected function getReputationData()
	{
		if( $this->_reputationData === NULL )
		{
			$this->_reputationData	= array();
			
			if ( isset( \IPS\Data\Store::i()->reputationLevels ) )
			{
				$reputationLevels = \IPS\Data\Store::i()->reputationLevels;
			}
			else
			{
				$reputationLevels = iterator_to_array( \IPS\Db::i()->select( '*', 'core_reputation_levels', NULL, 'level_points DESC' ) );
				\IPS\Data\Store::i()->reputationLevels = $reputationLevels;
			}
			
			foreach ( $reputationLevels as $level )
			{
				if ( $this->pp_reputation_points >= $level['level_points'] )
				{
					$this->_reputationData = $level;
					break;
				}
			}
		}

		return $this->_reputationData;
	}
	
	/**
	 * @brief	Cached reputation last day won
	 */
	protected $_reputationLastDayWon = NULL;
		
	/**
	 * Return the 'date' of the last day won, along with the 'rep_total'.
	 *
	 * @return array( 'date' => \IPS\DateTime, 'rep_total' => int )|FALSE
	 */
	public function getReputationLastDayWon()
	{
		if ( $this->_reputationLastDayWon === NULL )
		{
			try
			{
				$dayWon = \IPS\Db::i()->select( 'leader_date, leader_rep_total', 'core_reputation_leaderboard_history', array( 'leader_position=1 AND leader_member_id=?', $this->member_id ), 'leader_date DESC', array( 0, 1 ) )->first();
				/* The 'day won' must be in the leaderboard timezone otherwise it will be off for people in significantly different timezones */
				$this->_reputationLastDayWon = array( 'date' => \IPS\DateTime::ts( $dayWon['leader_date'], true )->setTimezone( new \DateTimeZone( \IPS\Settings::i()->reputation_timezone ) ), 'rep_total' => $dayWon['leader_rep_total'] );
				
			}
			catch( \UnderflowException $ex )
			{
				$this->_reputationLastDayWon = FALSE;
			}
		}
		
		return $this->_reputationLastDayWon;
	}
	
	/**
	 * @brief	Cached reputation days won count
	 */
	protected $_reputationDaysWonCount = NULL;
	
	/**
	 * Return the total number of days won
	 *
	 * @return int
	 */
	public function getReputationDaysWonCount()
	{
		if ( $this->_reputationDaysWonCount === NULL )
		{
			$this->_reputationDaysWonCount = \IPS\Db::i()->select( 'COUNT(*)', 'core_reputation_leaderboard_history', array( 'leader_position=1 AND leader_member_id=?', $this->member_id ) )->first();
		}
		
		return $this->_reputationDaysWonCount;
	}

	/**
	 * Reputation level description
	 *
	 * @return	string|NULL
	 */
	public function reputation()
	{
		$level	= $this->getReputationData();
		
		if( isset( $level['level_id'] ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'core_reputation_level_' . $level['level_id'] );
		}
		else
		{
			return NULL;
		}
	}

	/**
	 * Reputation image
	 *
	 * @return	string|NULL
	 */
	public function reputationImage()
	{
		$level	= $this->getReputationData();
		
		if( isset( $level['level_id'] ) )
		{
			return $level['level_image'];
		}
		else
		{
			return NULL;
		}
	}
		
	/**
	 * Verify legacy password
	 *
	 * @param	object	$password	The (legacy) password to verify, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	bool
	 */
	public function verifyLegacyPassword( $password )
	{
		return \IPS\Login::compareHashes( $this->members_pass_hash, md5( md5( $this->members_pass_salt ) . md5( \IPS\Request::legacyEscape( $password ) ) ) );
	}
	
	/**
	 * Set local password
	 *
	 * BE CAREFUL: The standard login handler may be disabled, only call this method
	 * if you have alreadu checked it is enabled. In most cases, it is better to let
	 * the available login handlers handle password management
	 *
	 * @param	object	$password	Password to encrypt, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	void
	 */
	public function setLocalPassword( $password )
	{
		/* We can safeuly assume that no matter what, this doesn't need to be set anymore */
		$this->members_bitoptions['password_reset_forced'] = FALSE;
		
		$this->members_pass_hash = password_hash( $password, PASSWORD_DEFAULT );
		$this->members_pass_salt = NULL;
	}
	
	/**
	 * Change member's password for all applicable login handlers
	 *
	 * @param	string	$newPassword		The new password in plaintext, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @param	string	$type			Type of change for log
	 * @return	bool
	 */
	public function changePassword( $newPassword, $type='manual' )
	{
		$return = FALSE;
		foreach ( \IPS\Login::methods() as $method )
		{
			if ( $method->canChangePassword( $this ) )
			{
				try
				{
					$method->changePassword( $this, $newPassword );
					$return = TRUE;
				}
				catch( \BadMethodCallException $e ){}
			}
		}
		$this->memberSync( 'onPassChange', array( $newPassword ) );
		$this->logHistory( 'core', 'password_change', $type );
		
		return $return;
	}
	
	/**
	 * Force user to reset their password.
	 *
	 * @return	void
	 */
	public function forcePasswordReset()
	{
		/* If the user is already forced, but hasn't yet, just return */
		if ( $this->members_bitoptions['password_reset_forced'] )
		{
			return;
		}
		
		$this->members_pass_hash = NULL; # Intentionally bypasses setLocalPassword() as it will try and hash the NULL value.
		$this->members_bitoptions['password_reset_forced'] = TRUE;
		$this->save();
		
		/* Invalidate login keys and sessions for all devices */
		$this->invalidateSessionsAndLogins();
		
		$key = md5( \IPS\SUITE_UNIQUE_KEY . $this->email . $this->real_name );
		
		$email = \IPS\Email::buildFromTemplate( 'core', 'password_reset_forced', array( $this, $key ), \IPS\Email::TYPE_TRANSACTIONAL );
		$email->send( $this );
	}
	
	/**
	 * Password Reset Forced
	 *
	 * @param	\IPS\Http\Url|NULL $ref		Referrer, or NULL for no referrer.
	 * @return	bool
	 */
	public function passwordResetForced( ?\IPS\Http\Url $ref = NULL ): ?\IPS\Http\Url
	{
		if ( $this->members_bitoptions['password_reset_forced'] AND !$this->members_pass_hash )
		{
			return \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=password', 'front', 'settings_password' )->setQueryString( 'ref', base64_encode( $ref ) );
		}
		
		return NULL;
	}

	/**
	 * @brief	Cached notifications configuration
	 * @note	This property is public so it can be populated en-masse in areas that may benefit from doing so
	 */
	public $notificationsConfiguration	= NULL;

	static protected $extCache = [];
	
	/**
	 * Notifications Configuration
	 *
	 * @return	array
	 */
	public function notificationsConfiguration()
	{
		if( $this->notificationsConfiguration === NULL )
		{
			$this->notificationsConfiguration = array();
			$defaultNotifications = [];

			foreach (
				\IPS\Db::i()->select(
					'd.*, p.preference',
					array( 'core_notification_defaults', 'd' )
				)->join(
					array( 'core_notification_preferences', 'p' ),
					array( 'd.notification_key=p.notification_key AND p.member_id=?', $this->member_id )
				)
				as $row
			) {
				if ( $row['preference'] === NULL or !$row['editable'] )
				{
					$defaultNotifications[ $row['notification_key'] ] = explode( ',', $row['default'] );
				}
				else
				{
					$this->notificationsConfiguration[ $row['notification_key'] ] = array_diff( explode( ',', $row['preference'] ), explode( ',', $row['disabled'] ) );
				}
			}

			/* Only cycle through if this member has any non-default notifications */
			if( \count( $this->notificationsConfiguration ) )
			{
				/* Cache extension references */
				if ( !\count( static::$extCache ) )
				{
					static::$extCache = \IPS\Application::allExtensions( 'core', 'Notifications' );
				}

				foreach ( $defaultNotifications as $k => $v )
				{
					foreach ( static::$extCache as $extension )
					{
						if ( method_exists( $extension, 'configurationOptions' ) )
						{
							foreach ( $extension->configurationOptions( null ) as $optionKey => $option )
							{
								if ( $option['type'] == 'standard' and \in_array( $k, $option['notificationTypes'] ) and \count( $option['notificationTypes'] ) > 1 )
								{
									if ( !empty( $this->notificationsConfiguration[ $option['notificationTypes'][0] ] ) )
									{
										$defaultNotifications[ $k ] = $this->notificationsConfiguration[ $option['notificationTypes'][0] ];
										\IPS\Db::i()->insert( 'core_notification_preferences', [
											'member_id' => $this->member_id,
											'notification_key' => $k,
											'preference' => implode( ',', $this->notificationsConfiguration[ $option['notificationTypes'][0] ] )
										] );
									}

									continue 2;
								}
							}
						}
					}
				}
			}

			$this->notificationsConfiguration = array_merge( $this->notificationsConfiguration, $defaultNotifications );
		}

		return $this->notificationsConfiguration;
	}
	
	/**
	 * @brief	Cached PWA Authorizations
	 */
	protected $_pwa = NULL;
	
	/**
	 * Get members PWA Authorizations
	 *
	 * @return	array
	 */
	public function getPwaAuths(): array
	{
		if ( $this->_pwa === NULL )
		{
			$this->_pwa = array();
			foreach( \IPS\Db::i()->select( '*', 'core_notifications_pwa_keys', array( "`member`=?", $this->member_id ) ) AS $auth )
			{
				$this->_pwa[ $auth['id'] ] = $auth;
			}
		}
		
		return $this->_pwa;
	}

	/**
	 * Get members PWA Authorizations
	 *
	 * @return	array
	 */
	public function clearPwaAuths()
	{
		$this->_pwa = NULL;
		\IPS\Db::i()->delete( 'core_notifications_pwa_keys', array( "`member`=?", $this->member_id ) );
	}
	
	/**
	 * @brief	Following?
	 */
	protected $_following	= array();

	/**
	 * Following
	 *
	 * @param	string	$app	Application key
	 * @param	string	$area	Area
	 * @param	int		$id		Item ID
	 * @return	bool
	 */
	public function following( $app, $area, $id )
	{
		$_key	= md5( $app . $area . $id );
		if( isset( $this->_following[ $_key ] ) )
		{
			return $this->_following[ $_key ];
		}

		try
		{
			\IPS\Db::i()->select( 'follow_id', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=? AND follow_member_id=?', $app, $area, $id, $this->member_id ) )->first();
			$this->_following[ $_key ]	= TRUE;
		}
		catch ( \UnderflowException $e )
		{
			$this->_following[ $_key ]	= FALSE;
		}

		return $this->_following[ $_key ];
	}
	
	/**
	 * Admin CP Restrictions
	 *
	 * @return	array
	 */
	protected function acpRestrictions()
	{
		if ( !$this->member_id )
		{
			return FALSE;
		}
		
		if ( $this->restrictions === NULL )
		{
			$administrators = static::administrators();
			
			$rows = array();
			if ( isset( $administrators['m'][ $this->member_id ] ) )
			{
				$rows[] = $administrators['m'][ $this->member_id ];
			}
			foreach ( $this->groups as $id )
			{
				if ( isset( $administrators['g'][ $id ] ) )
				{
					$rows[] = $administrators['g'][ $id ];
				}
			}
									
			$this->restrictions = FALSE;
			if ( \count( $rows ) > 0 )
			{
				$this->restrictions = array();
				foreach ( $rows as $row )
				{
					if ( $row['row_perm_cache'] === '*' )
					{
						$this->restrictions = '*';
						break;
					}
					
					$perms = json_decode( $row['row_perm_cache'], TRUE );
					if ( $row['row_id_type'] === 'member' )
					{
						$this->restrictions = $perms;
						break;
					}
					else if( \is_array( $perms ) )
					{
						if ( empty( $this->restrictions ) )
						{
							$this->restrictions = $perms;
						}
						else
						{
							if( isset( $perms['applications'] ) )
							{
								foreach ( $perms['applications'] as $app => $modules )
								{
									if ( !isset( $this->restrictions['applications'][ $app ] ) )
									{
										$this->restrictions['applications'][ $app ] = array();
									}

									foreach ( $modules as $module )
									{
										if ( !isset( $this->restrictions['applications'][ $app ][ $module ] ) )
										{
											$this->restrictions['applications'][ $app ][ $module ] = $module;
										}
									}
								}
							}
							if( isset( $perms['items'] ) )
							{
								foreach ( $perms['items'] as $app => $modules )
								{
									if ( !isset( $this->restrictions['items'][ $app ] ) )
									{
										$this->restrictions['items'][ $app ] = array();
									}

									foreach ( $modules as $module => $items )
									{
										if ( !isset( $this->restrictions['items'][ $app ][ $module ] ) )
										{
											$this->restrictions['items'][ $app ][ $module ] = array();
										}

										foreach ( $items as $item )
										{
											if ( !\in_array( $item, $this->restrictions['items'][ $app ][ $module ] ) )
											{
												$this->restrictions['items'][ $app ][ $module ][] = $item;
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
		
		return $this->restrictions;
	}
	
	/**
	 * @brief Cache moderator badge results
	 */
	protected static $modBadge = array();
	
	/**
	 * Show moderator badge?
	 *
	 * @return	boolean
	 */
	public function modShowBadge()
	{
		if ( ! $this->member_id )
		{
			return FALSE;
		}
		
		if ( ! array_key_exists( $this->member_id, static::$modBadge ) )
		{
			static::$modBadge[ $this->member_id ] = false;
			
			if ( $this->modPermission() )
			{
				if ( isset( \IPS\Data\Store::i()->moderators['m'][ $this->member_id ] ) )
				{
					static::$modBadge[ $this->member_id ] = \IPS\Data\Store::i()->moderators['m'][ $this->member_id ]['show_badge'];
				}
				else
				{
					foreach ( $this->groups as $id )
					{
						if ( isset( \IPS\Data\Store::i()->moderators['g'][ $id ] ) and \IPS\Data\Store::i()->moderators['g'][ $id ]['show_badge'] )
						{
							static::$modBadge[ $this->member_id ] = \IPS\Data\Store::i()->moderators['g'][ $id ];
						}
					}
				}
			}
		}
		
		return static::$modBadge[ $this->member_id ];
	}
	
	/**
	 * Moderator Permissions
	 *
	 * @return	array|false
	 */
	public function modPermissions()
	{
		/* Only members can be moderators of course */
		if ( !$this->member_id )
		{
			return FALSE;
		}
		
		/* Work out the permissions... */	
		if ( $this->modPermissions === NULL )
		{
			/* Start with FALSE (no moderator permissions) */
			$this->modPermissions = FALSE;
			
			/* If we don't have a datastore of moderator configuration, load that now */
			if ( !isset( \IPS\Data\Store::i()->moderators ) )
			{
				\IPS\Data\Store::i()->moderators = array(
					'm'	=> iterator_to_array( \IPS\Db::i()->select( '*', 'core_moderators', array( 'type=?', 'm' ) )->setKeyField( 'id' ) ),
					'g'	=> iterator_to_array( \IPS\Db::i()->select( '*', 'core_moderators', array( 'type=?', 'g' ) )->setKeyField( 'id' ) ),
				);
			}
			
			/* Member-level moderator permissions override all group-level permissions, so if this member is a moderator at a member-level, just use that */
			if ( isset( \IPS\Data\Store::i()->moderators['m'][ $this->member_id ] ) )
			{
				$perms = \IPS\Data\Store::i()->moderators['m'][ $this->member_id ]['perms'];
				$this->modPermissions = $perms == '*' ? '*' : json_decode( $perms, TRUE );
			}
			
			/* Otherwise, examine the groups and combine the permissions each group awards */
			else
			{
				/* Get all the groups the member is in which have moderator permissions... */
				$rows = array();
				foreach ( $this->groups as $id )
				{
					if ( isset( \IPS\Data\Store::i()->moderators['g'][ $id ] ) )
					{
						$rows[] = \IPS\Data\Store::i()->moderators['g'][ $id ];
					}
				}
				
				/* And if we have any... */			
				if ( \count( $rows ) > 0 )
				{
					/* Start with an empty array (indicates they are a moderator, but haven't get given them any permissions) */
					$this->modPermissions = array();
					
					/* Loop the groups and combine the permissions... */
					foreach ( $rows as $row )
					{
						/* If any group has all permissions, this user has all moderator permissions and we don't need to go further */
						if ( $row['perms'] === '*' )
						{
							$this->modPermissions = '*';
							break;
						}
						
						/* Otherwise, examine what permissions this group has... */
						$perms = json_decode( $row['perms'], TRUE );
						if( !empty( $perms ) )
						{
							foreach ( $perms as $k => $v )
							{
								/* If we haven't seen this permission key at all, give them the value */
								if ( !isset( $this->modPermissions[ $k ] ) )
								{
									$this->modPermissions[ $k ] = $v;
								}
								/* If it's an array, combine the values */
								elseif ( \is_array( $this->modPermissions[ $k ] ) AND \is_array( $v ) )
								{
									$this->modPermissions[ $k ] = array_merge( $this->modPermissions[ $k ], $v );
								}
								
								/* If it's a number, they get the higher one, or -1 is best */
								elseif ( $v == -1 or ( $this->modPermissions[ $k ] != -1 and $v > $this->modPermissions[ $k ] ) )
								{
									$this->modPermissions[ $k ] = $v;
								}
							}
						}
					}
				}
			}
		}

		/* Return */
		return $this->modPermissions;
	}
	
	/**
	 * @brief	Report count
	 */
	protected $reportCount = NULL;
	
	/**
	 * Get number of open reports that this member can see
	 *
	 * @param	bool	$force	Fetch the count even if the notification is off
	 * @return	int
	 */
	public function reportCount( $force=FALSE )
	{
		if ( $this->reportCount === NULL OR $force === TRUE )
		{
			if ( $this->canAccessModule( \IPS\Application\Module::get( 'core', 'modcp' ) ) )
			{
				if( !$this->members_bitoptions['no_report_count'] OR $force === TRUE )
				{
					$where = [ \IPS\core\Reports\Report::where( $this ), [ 'status IN( 1,2 )' ] ];

					$reportCount = \IPS\Db::i()->select(
						'COUNT(*)',
						'core_rc_index',
						$where
					)->first();

					if( $force === FALSE )
					{
						$this->reportCount	= $reportCount;
					}
					else
					{
						return $reportCount;
					}
				}
			}
			else
			{
				$this->reportCount = 0;
			}
		}
		
		return (int) $this->reportCount;
	}
	
	/**
	 * @brief	Ignore Preferences
	 * @see		isIgnoring
	 */
	protected $ignorePreferences = NULL;
	
	/**
	 * Is this member ignoring another member?
	 *
	 * @param	\IPS\Member|array	$member	The member
	 * @param	string				$type	The type (topics, messages, signatures)
	 * @return	bool
	 */
	public function isIgnoring( $member, $type )
	{
		if( \is_array( $member ) )
		{
			$member = \IPS\Member::load( $member['member_id'] );
		}

		$group = $member->group;
		$id    = $member->member_id;
		$cannotBeIgnored = !$member->canBeIgnored();

		if ( $cannotBeIgnored or !$this->member_id )
		{
			return FALSE;
		}

		/* We cannot ignore ourself */
		if( $this->member_id == $member->member_id )
		{
			return FALSE;
		}
		
		if ( $this->ignorePreferences === NULL )
		{
			if ( $this->members_bitoptions['has_no_ignored_users'] )
			{
				$this->ignorePreferences = array();
			}
			else
			{
				$this->ignorePreferences = iterator_to_array( \IPS\Db::i()->select( '*', 'core_ignored_users', array( 'ignore_owner_id=?', $this->member_id ) )->setKeyField( 'ignore_ignore_id' ) );
				
				if ( empty( $this->ignorePreferences ) )
				{
					$this->members_bitoptions['has_no_ignored_users'] = TRUE;
					$this->save();
				}
			}
		}
				
		if ( isset( $this->ignorePreferences[ $id ] ) )
		{
			return (bool) $this->ignorePreferences[ $id ][ 'ignore_' . $type ];
		}
		
		return FALSE;
	}
	
	/**
	 * Build the "Create" menu
	 *
	 * @return	array
	 */
	public function createMenu()
	{
		$menu = NULL;
		$rebuild = FALSE;

		/* Make sure that this is a valid member */
		if( !$this->member_id )
		{
			return array();
		}
		
		if ( ! \IPS\Settings::i()->member_menu_create_key )
		{
			/* Generate a new key */
			static::clearCreateMenu();
		}
		if ( \IPS\IN_DEV and !\IPS\DEV_USE_MENU_CACHE )
		{
			$rebuild = TRUE;
		}
		else if ( $this->create_menu !== NULL )
		{
			$menu = json_decode( $this->create_menu, TRUE );
			
			if ( ! isset( $menu['menu_key'] ) or $menu['menu_key'] != \IPS\Settings::i()->member_menu_create_key )
			{
				$rebuild = TRUE;
			}
		}
		else
		{
			$rebuild = TRUE;
		}
		
		if ( $rebuild === TRUE )
		{
			$createMenu = array();
			foreach ( \IPS\Application::allExtensions( 'core', 'CreateMenu', $this ) as $ext )
			{
				$createMenu = array_merge( $createMenu, array_map( function( $val )
				{
					$val['link'] = (string) $val['link'];
					return $val;
				}, $ext->getItems() ) );
			}
			
			$this->create_menu = json_encode( array( 'menu_key' => \IPS\Settings::i()->member_menu_create_key, 'menu' => $createMenu ) );
			$this->save();
			
			return $createMenu;
		}
		else
		{
			return $menu['menu'];
		}
	}
	
	/**
	 * Moderate New Content
	 *
	 * @param	bool	$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewContent( $considerPostBeforeRegistering = FALSE )
	{
		$modQueued = FALSE;
		
		/* Check the group */
		if ( $this->group['g_mod_preview'] )
		{
			if ( $this->group['g_mod_post_unit'] )
			{
				/* Days since joining */
				if ( $this->group['gbw_mod_post_unit_type'] )
				{
					$modQueued = $this->joined->add( new \DateInterval( "P{$this->group['g_mod_post_unit']}D" ) )->getTimestamp() > time();
				}
				/* Content items */
				else
				{
					$modQueued = $this->member_posts < $this->group['g_mod_post_unit'];
				}
			}
			else
			{
				$modQueued = TRUE;
			}
		}
		
		/* If we're not group moderated what about individual member */
		if ( !$modQueued )
		{
			if( $this->mod_posts == -1 or ( $this->mod_posts > 0 and $this->mod_posts > time() ) )
			{
				$modQueued = TRUE;
			}
		}
		
		/* Post before register check */
		if ( !$modQueued and $considerPostBeforeRegistering and $this->member_group_id == \IPS\Settings::i()->guest_group )
		{
			$modQueued = (bool) \IPS\Member\Group::load( \IPS\Settings::i()->member_group )->g_mod_preview;
		}

		/* Return */
		return $modQueued;
	}
	
	/**
	 * Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	public function coverPhoto()
	{
		$photo = new \IPS\Helpers\CoverPhoto;
		if ( $this->pp_cover_photo )
		{
			$photo->file = \IPS\File::get( 'core_Profile', $this->pp_cover_photo );
			$photo->offset = $this->pp_cover_offset;
		}
		$photo->editable	= ( \IPS\Member::loggedIn()->modPermission('can_modify_profiles') or ( \IPS\Member::loggedIn()->member_id == $this->member_id and $this->group['g_edit_profile'] and $this->group['gbw_allow_upload_bgimage'] ) );
		$photo->maxSize		= $this->group['g_max_bgimg_upload'];
		$photo->object		= $this;
		
		return $photo;
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @return	callable
	 */
	public function searchResultHtml()
	{
		return \IPS\Theme::i()->getTemplate('search')->member( $this );
	}
	
	/**
	 * Should a reply made by this member be highlighted?
	 *
	 * @return	boolean
	 */
	public function hasHighlightedReplies()
	{
		return (boolean) $this->group['gbw_post_highlight'];
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @param	array|NULL			$otherFields		Array of additional fields to return (raw values)
	 * @return	array
	 * @apiresponse			int											id						ID number
	 * @apiresponse			string										name					Username
	 * @apiresponse			string										title					Member title
	 * @clientapiresponse	string										timezone				Member timezone
	 * @apiresponse			string										formattedName			Username with group formatting
	 * @apiresponse			\IPS\Member\Group							primaryGroup			Primary group
	 * @clientapiresponse	[\IPS\Member\Group]							secondaryGroups			Secondary groups
	 * @clientapiresponse	string										email					Email address
	 * @apiresponse			datetime									joined					Registration date
	 * @clientapiresponse	string										registrationIpAddress	IP address when registered
	 * @clientapiresponse	int											warningPoints			Number of active warning points
	 * @apiresponse			int											reputationPoints		Number of reputation points
	 * @apiresponse			string										photoUrl				URL to photo (which will be the site's default if they haven't set one)
	 * @apiresponse			bool										photoUrlIsDefault		Indicates if the value of photoUrl is the site's default
	 * @apiresponse			string										coverPhotoUrl			URL to profile cover photo (will be blank if there isn't one)
	 * @apiresponse			string|null									profileUrl				URL to profile
	 * @clientapiresponse	bool										validating				Whether or not the validating flag is set on the member account
	 * @apiresponse			int											posts					Number of content item submissions member has made
	 * @apiresponse			datetime|null								lastActivity			Last activity date on the site.
	 * @clientapiresponse	datetime|null								lastVisit				Last distinct visit date on the site.
	 * @clientapiresponse	datetime|null								lastPost				Latest content submission date.
	 * @apiresponse			int											profileViews			Number of times member's profile has been viewed
	 * @apiresponse			string										birthday				Member birthday in MM/DD/YYYY format (or MM/DD format if no year has been supplied).
	 * @apiresponse			[\IPS\core\ProfileFields\Api\FieldGroup]	customFields			Custom profile fields. For requests using an OAuth Access Token for a particular member, only fields the authorized user can view will be included
	 * @apiresponse			[\IPS\core\Achievements\Rank]					rank					Rank
	 * @apiresponse			int											achievements_points		Points
	 * @apiresponse			bool										allowAdminEmails		Whether or not this member wants to receive admin emails
	 * @apiresponse			bool										completed				Whether or not the registration is completed
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL, $otherFields = NULL )
	{
		$group = \IPS\Member\Group::load( $this->_data['member_group_id'] );
		
		$secondaryGroups = array();
		foreach ( array_filter( array_map( "intval", explode( ',', $this->_data['mgroup_others'] ) ) ) as $secondaryGroupId )
		{
			try
			{
				$secondaryGroups[] = \IPS\Member\Group::load( $secondaryGroupId )->apiOutput( $authorizedMember );
			}
			catch ( \OutOfRangeException $e ) { }
		}

		/* Figure out custom fields if any */
		$fields = array();
		
		try
		{
			$fieldData		= \IPS\core\ProfileFields\Field::fieldData();
			$fieldValues	= \IPS\Db::i()->select( '*', 'core_pfields_content', array( 'member_id=?', $this->member_id ) )->first();
	
			foreach( $fieldData as $profileFieldGroup => $profileFields )
			{
				$groupValues = array();
	
				foreach( $profileFields as $field )
				{
					if ( !$authorizedMember or
						$field['pf_contains_pii'] == 1 AND ( $authorizedMember->member_id == $this->member_id ) or
						$field['pf_member_hide'] == 'all' or
						( $field['pf_member_hide'] == 'owner' and ( $authorizedMember->member_id == $this->member_id OR $authorizedMember->modPermissions() OR $authorizedMember->isAdmin() ) ) or
						( $field['pf_member_hide'] == 'staff' and ( $authorizedMember->modPermissions() OR $authorizedMember->isAdmin() ) )
						)
					{
						$groupValues[ $field['pf_id'] ] = new \IPS\core\ProfileFields\Api\Field( $this->language()->get( 'core_pfield_' . $field['pf_id'] ), $fieldValues[ 'field_' . $field['pf_id'] ] );
					}
				}
				
				$fields[ $profileFieldGroup ] = ( new \IPS\core\ProfileFields\Api\FieldGroup( $this->language()->get( 'core_pfieldgroups_' . $profileFieldGroup ), $groupValues ) )->apiOutput( $authorizedMember );
			}
		}
		catch( \UnderflowException $e ) { } # Guests will not have any profile field information.
		
		$return = array();
		$return['id']					= $this->member_id;
		$return['name']					= $this->_name;
		$return['title']				= null;
		if ( !$authorizedMember )
		{
			$return['timeZone']				= $this->timezone;
		}
		$return['formattedName']		= $group->formatName( $this->_name );
		$return['primaryGroup']			= $group->apiOutput( $authorizedMember );
		if ( !$authorizedMember )
		{
			$return['secondaryGroups']		= $secondaryGroups;
			$return['email']				= $this->email;
		}
		$return['joined']				= $this->joined->rfc3339();
		if ( !$authorizedMember )
		{
			$return['registrationIpAddress']= $this->ip_address;
			$return['warningPoints']		= $this->warn_level;
		}
		$return['reputationPoints']		= $this->pp_reputation_points;
		$return['photoUrl']				= static::photoUrl( $this->_data, FALSE );
		$return['photoUrlIsDefault']	= \in_array( $this->pp_photo_type, [ 'none', 'letter' ] ) or static::photoUrl( $this->_data, FALSE, FALSE, FALSE ) != $return['photoUrl'];
		$return['coverPhotoUrl']		= $this->pp_cover_photo ? ( (string) \IPS\File::get( 'core_Profile', $this->pp_cover_photo )->url ) : '';
		$return['profileUrl']			= ( $this->member_id ) ? (string) $this->url() : NULL;
		if ( !$authorizedMember )
		{
			$return['validating']			= (bool) $this->members_bitoptions['validating'];
		}
		$return['posts']				= $this->member_posts;
		$return['lastActivity'] 		= ( $this->last_activity AND !$this->isOnlineAnonymously() ) ? \IPS\DateTime::ts( $this->last_activity )->rfc3339() : NULL;
		
		if( !$authorizedMember )
		{
			$return['lastVisit'] 		= $this->last_visit ? \IPS\DateTime::ts( $this->last_visit )->rfc3339() : NULL;
			$return['lastPost'] 		= $this->member_last_post ? \IPS\DateTime::ts( $this->member_last_post )->rfc3339() : NULL;
		}

		if( !$authorizedMember OR $authorizedMember?->member_id === $this->member_id )
		{
			$return['birthday'] = $this->bday_month ? ( $this->bday_month . '/' . $this->bday_day . ( $this->bday_year ? '/' . $this->bday_year : '' ) ) : NULL;
		}
		$return['profileViews']		= $this->members_profile_views;
		$return['customFields']		= $fields;

		if ( !$authorizedMember )
		{
			if( $otherFields !== NULL AND \is_array( $otherFields ) )
			{
				foreach( $otherFields as $property )
				{
					$return[ $property ] = $this->$property;
				}
			}
		}

		$return['rank'] = $this->rank() ? $this->rank()->apiOutput( $authorizedMember ) : NULL;
		$return['achievements_points'] = $this->achievements_points;
		$return['allowAdminEmails'] = (bool) $this->allow_admin_emails;
		$return['completed'] = (bool) $this->completed;

		return $return;
	}
	
	/**
	 * Answers to security questions
	 *
	 * @return	\IPS\Db\Select
	 */
	public function securityAnswers()
	{
		return \IPS\Db::i()->select( array( 'answer_question_id', 'answer_answer' ), 'core_security_answers', array( 'answer_member_id=?', $this->member_id ) )->setKeyField('answer_question_id')->setValueField('answer_answer');
	}
	
	/**
	 * Last used device
	 *
	 * @return	\IPS\Member\Device|NULL
	 */
	public function lastUsedDevice()
	{
		try
		{
			return \IPS\Member\Device::constructFromData( \IPS\Db::i()->select( '*', 'core_members_known_devices', array( 'member_id=?', $this->member_id ), 'last_seen DESC', 1 )->first() );
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
	}
		
	/**
	 * Last used IP address
	 *
	 * @return	\IPS\Member\Device|NULL
	 */
	public function lastUsedIp()
	{
		try
		{
			return \IPS\Db::i()->select( 'ip_address', 'core_members_known_ip_addresses', array( 'member_id=?', $this->member_id ), 'last_seen DESC', 1 )->first();
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
	}
		
	/**
	 * Device count
	 *
	 * @return	int
	 */
	public function deviceCount()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_members_known_devices', array( 'member_id=?', $this->member_id ) )->first();
	}
	
	/**
	 * Check if account is locked - returns FALSE if account is unlocked, an \IPS\DateTime object if the account is locked until a certain time, or TRUE if account is locked indefinitely
	 *
	 * @return	\IPS\DateTime|bool
	 */
	public function unlockTime()
	{
		$failedLogins = $this->member_id ? $this->failed_logins : ( ( isset( \IPS\Data\Store::i()->failedLogins[ $this->email ] ) ) ? \IPS\Data\Store::i()->failedLogins[ $this->email ] : array() );

		if ( \IPS\Settings::i()->ipb_bruteforce_attempts and isset( $failedLogins[ \IPS\Request::i()->ipAddress() ] ) and \count( $failedLogins[ \IPS\Request::i()->ipAddress() ] ) >= \IPS\Settings::i()->ipb_bruteforce_attempts )
		{
			if ( \IPS\Settings::i()->ipb_bruteforce_period and \IPS\Settings::i()->ipb_bruteforce_unlock )
			{
				$failedLogins = $failedLogins[ \IPS\Request::i()->ipAddress() ];
				sort( $failedLogins );

				while ( \count( $failedLogins ) > \IPS\Settings::i()->ipb_bruteforce_attempts )
				{
					/* We want to remove the oldest logins here so the most recent are checked */
					array_shift( $failedLogins );
				}
				$unlockTime = \IPS\DateTime::ts( array_pop( $failedLogins ) );
				$unlockTime = $unlockTime->add( new \DateInterval( 'PT' . \IPS\Settings::i()->ipb_bruteforce_period . 'M' ) );
				
				/* If Unlock Time is in the past, return FALSE to avoid the exception and allow login */
				if ( $unlockTime->getTimestamp() < time() )
				{
					return FALSE;
				}
				
				/* Otherwise that is what we're returning */
				return $unlockTime;
			}
			
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Return the number of failed logins for a given IP address
	 *
	 * @param	string	$ipAddress	IP Address
	 * @return	int
	 */
	public function failedLoginCount( $ipAddress )
	{
		if( $this->member_id )
		{
			return ( isset( $this->failed_logins[ $ipAddress ] ) ) ? \count( $this->failed_logins[ $ipAddress ] ) : 0;
		}
		elseif( $this->email )
		{
			$failedLogins = ( isset( \IPS\Data\Store::i()->failedLogins[ $this->email ] ) ) ? \IPS\Data\Store::i()->failedLogins[ $this->email ] : array();

			return ( isset( $failedLogins[ $ipAddress ] ) ) ? \count( $failedLogins[ $ipAddress ] ) : 0;
		}
	}
			
	/* !Permissions */
	
	/**
	 * @var	array	ACP restriction keys which are hardcoded to FALSE when on CiC (even if admin has no restrictions)
	 */
	public static $cicBlockedAcpRestrictions = [ 'core.membersettings.member_history_prune', 'core.settings.datastore', 'core.customization.theme_designers_mode', 'core.membersettings.notifications_prune' ];
	
	/**
	 * Has access to a restricted ACP area?
	 *
	 * @param	\IPS\Application|string				$app	Application
	 * @param	\IPS\Application\Module|string|null	$module	Module
	 * @param	string|null							$key	Restriction Key
	 */
	public function hasAcpRestriction( $app, $module=NULL, $key=NULL )
	{		
		/* Load our ACP restrictions */
		$restrictions = $this->acpRestrictions();
		if ( $restrictions === FALSE )
		{
			return FALSE;
		}
		
		/* CiC Block */
		$appKey = \is_string( $app ) ? $app : $app->directory;
		$moduleKey = ( $module === NULL or \is_string( $module ) ) ? $module : $module->key;
		if ( \IPS\CIC and \in_array( $appKey . ( $moduleKey ? ( ".{$moduleKey}" . ( $key ? ".{$key}" : '' ) ) : '' ), static::$cicBlockedAcpRestrictions ) )
		{
			return FALSE;
		}

		/* If we have all permissions, return true */
		if ( $restrictions === '*' )
		{
			return TRUE;
		}

		/* If we don't have any permissions, return false */
		if( !\count( $restrictions ) )
		{
			return FALSE;
		}
		
		/* Otherwise, check 'em! */
		if ( array_key_exists( $appKey, $restrictions['applications'] ) )
		{
			if ( $module === NULL )
			{
				return TRUE;
			}
			else
			{
				if ( \in_array( $moduleKey, $restrictions['applications'][ $appKey ] ) )
				{
					if ( $key === NULL )
					{
						return TRUE;
					}
					elseif ( isset( $restrictions['items'][ $appKey ][ $moduleKey ] ) and \in_array( $key, $restrictions['items'][ $appKey ][ $moduleKey ] ) )
					{
						return TRUE;
					}
				}
			}
		}
		return FALSE;
	}
	
	/**
	 * Get moderator permission
	 *
	 * @param	string|NULL	$key	Permission Key to check, or NULL to just test if they have any moderator permissions.
	 * @return	mixed
	 */
	public function modPermission( $key=NULL )
	{
		/* Load our permissions */
		$permissions = $this->modPermissions();

		if ( $permissions == FALSE )
		{
			return FALSE;
		}
		
		/* If we have all permissions, return true */
		if ( $permissions === '*' or $key === NULL )
		{
			return TRUE;
		}
				
		/* Otherwise return it */
		return isset( $permissions[ $key ] ) ? $permissions[ $key ] : NULL;
	}
	
	/**
	 * Can warn
	 *
	 * @param	\IPS\Member	$member	The member to warn
	 * @return	bool
	 */
	public function canWarn( \IPS\Member $member )
	{
		if( !$this->modPermission('mod_can_warn') OR !$this->modPermission('mod_see_warn') )
		{
			return FALSE;
		}
		
		if( $member->inGroup( explode( ',', \IPS\Settings::i()->warn_protected ) ) or $member->member_id == \IPS\Member::loggedIn()->member_id )
		{
			return FALSE;
		}
		
		if ( $this->modPermission('warn_mod_day') !== TRUE and $this->modPermission('warn_mod_day') != -1 )
		{
			$oneDayAgo = \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) );
			$warningsGivenInTheLastDay = \IPS\Db::i()->select( 'COUNT(*)', 'core_members_warn_logs', array( 'wl_moderator=? AND wl_date>?', $this->member_id, $oneDayAgo->getTimestamp() ) )->first();
			if( $warningsGivenInTheLastDay >= $this->modPermission('warn_mod_day') )
			{
				return FALSE;
			}
		}
		
		return TRUE;
	}
	
	/* !Recounting */
	
	/**
	 * Recalculate notification count
	 *
	 * @return	void
	 */
	public function recountNotifications()
	{
		$this->notification_cnt = \IPS\Db::i()->select( 'COUNT(*)', 'core_notifications', array( '`member`=? AND read_time IS NULL', $this->member_id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$this->save();
	}

	/**
	 * Recounts content for this member
	 *
	 * @return void
	 */
	public function recountContent()
	{
		$this->member_posts = 0;
        foreach ( \IPS\Content::routedClasses( $this, TRUE, FALSE ) as $class )
		{			
			$this->member_posts += $class::memberPostCount( $this );
		}
		
		$this->save();
	}
	
	/**
	 * Recounts reputation for this member
	 *
	 * @return void
	 */
	public function recountReputation()
	{
		$this->pp_reputation_points = \IPS\Db::i()->select( 'SUM(rep_rating)', 'core_reputation_index', array( 'member_received=?', $this->member_id ) )->first();
		$this->save();
	}

	/**
	 * Removes reputation for this member
	 *
	 * @param	string	$type	given|received The type of reputation to remove
	 * @return void
	 */
	public function removeReputation( $type )
	{
		$where = ( $type == 'given' ) ? array( 'member_id=?', $this->member_id ) : array( 'member_received=?', $this->member_id );

		\IPS\Db::i()->delete( 'core_reputation_index', $where );

		if( $type == 'received' )
		{
			$this->recountReputation();
		}
	}
	
	/* !Do Stuff */
	
	/**
	 * Can use module
	 *
	 * @param	\IPS\Application\Module	$module	The module to test
	 * @return	bool
	 * @throws	\InvalidArgumentException
	 */
	public function canAccessModule( $module )
	{
		if ( !( $module instanceof \IPS\Application\Module ) )
		{
			throw new \InvalidArgumentException;
		}
		
		return \IPS\Application::load( $module->application )->canAccess( $this ) and ( $module->protected or $module->can( 'view', $this ) );
	}

	/**
	 * @brief		Store whitelist filters
	 */
	public static $whitelistFilters = NULL;

	/**
	 * Check Spam Defense Whitelist
	 *
	 * @param	string|NULL		$emailAddress			Email address to check, NULL for existing email address
	 * @return	boolean
	 */
	public function spamDefenseWhitelist( $emailAddress=NULL )
	{
		$email = $emailAddress ?: $this->email;

		if( static::$whitelistFilters === NULL )
		{
			static::$whitelistFilters = iterator_to_array( \IPS\Db::i()->select( 'whitelist_type, whitelist_content', 'core_spam_whitelist' ) );
		}

		foreach( static::$whitelistFilters as $whitelist )
		{
			if( $whitelist['whitelist_type'] == 'ip' )
			{
				if( preg_match( '/^' . str_replace( '\*', '.*', preg_quote( $whitelist['whitelist_content'], '/' ) ) . '$/i', $this->ip_address ) )
				{
					return TRUE;
				}
			}
			else
			{
				if( preg_match( '/' . str_replace( '\*', '.*', preg_quote( '@' . $whitelist['whitelist_content'], '/' ) ) . '$/i', $email ) )
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**
	 * IPS Spam Defense Service
	 *
	 * @param	string		$type			Request type
	 * @param	string		$emailAddress	Email address to check, NULL for existing email address
	 * @param	int			$spamCode		If set, will modify by reference with the raw value from the spam service
	 * @return	int|NULL					Action code based on spam service response, or NULL for no action
	 */
	public function spamService( $type='register', $emailAddress=NULL, &$spamCode=NULL )
	{
		$email = $emailAddress ?: $this->email;

		/* Check Whitelist */
		if( $this->spamDefenseWhitelist( $email ) )
		{
			/* Account details were whitelisted */
			return NULL;
		}

		try
		{
			$response = \IPS\Http\Url::ips( 'spam/' . $type )->request()->login( \IPS\Settings::i()->ipb_reg_number, '' )->post( array(
				'email'	=> $email,
				'ip'	=> $this->ip_address,
			) );

			if ( $response->httpResponseCode != 200 )
			{
				throw new \DomainException( print_r( $response, TRUE ) );
			}
			
			$spamCode = \intval( (string) $response );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::debug( $e, 'spam-service' );
			$spamCode = 0;
		}
				
		$action = NULL;
					
		if( $type == 'register' and $spamCode )
		{
			/* Log Request */
			\IPS\Db::i()->insert( 'core_spam_service_log', array(
															'log_date'		=> time(),
															'log_code'		=> $spamCode,
															'log_msg'		=> '',	// No value is returned unless it's a developer account making the call
															'email_address'	=> $email,
															'ip_address'	=> $this->ip_address
			) );
			
			/* Action to perform */
			$key = "spam_service_action_{$spamCode}";
			$action = \IPS\Settings::i()->$key;
			
			/* Perform Action */
			switch( $action )
			{
				/* Proceed with registration */
				case 1:
					break;
			
					/* Flag for admin approval */
				case 2:
					\IPS\Settings::i()->reg_auth_type = 'admin';
					break;
			
					/* Approve the account, but ban it */
				case 3:
					$this->temp_ban = -1;
					$this->members_bitoptions['bw_is_spammer'] = TRUE;
					break;
			
					/* Deny registration - we return the code and the controller is expected to show an error */
				case 4:
					break;

					/* Moderate posts */
				case 5:
					$days = json_decode( \IPS\Settings::i()->spam_service_days, TRUE );

					if ( isset( $days[ $spamCode ] ) )
					{
						if ( $days[ $spamCode ] == -1 )
						{
							$this->mod_posts = -1;
						}
						else if ( $days[ $spamCode ] > 0 )
						{
							$this->mod_posts = \IPS\DateTime::ts( time() )->add( new \DateInterval( "P{$days[ $spamCode ]}D" ) )->getTimestamp();
						}
					}
					break;
			}
		}

		return $action;
	}
	
	/**
	 * Member Sync
	 *
	 * @param	string	$method	Method
	 * @param	array	$params	Additional parameters to pass
	 * @return	void
	 */
	public function memberSync( $method, $params=array() )
	{
		/* Don't do this during an upgrade */
		if( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation === 'setup' )
		{
			return;
		}

		foreach ( \IPS\Application::allExtensions( 'core', 'MemberSync', FALSE ) as $class )
		{
			if ( method_exists( $class, $method ) )
			{
			    try
                {
                	/* Cannot unpack an associative array */
                	$params = array_values( $params );
					$class->$method( $this, ...$params );
                }
                /* 4.3 backwards compatibility for 4.2 applications */
                catch( \ArgumentCountError $e )
                {
                    if ( \IPS\IN_DEV )
                    {
                        throw $e;
                    }
                }
			}
		}
	}
			
	/**
	 * Merge
	 *
	 * @param	\IPS\Member	$otherMember	Member to merge with
	 * @return	void
	 */
	public function merge( \IPS\Member $otherMember )
	{
		if ( $this == $otherMember )
		{
			throw new \InvalidArgumentException( 'merge_self_error' );
		}
		
		/* Merge content */
		$otherMember->hideOrDeleteAllContent( 'merge', array( 'merge_with_id' => $this->member_id, 'merge_with_name' => $this->name ) );
		
		/* Log */
		$this->logHistory( 'core', 'account', array( 'type' => 'merge', 'id' => $otherMember->member_id, 'name' => $otherMember->name, 'email' => $otherMember->email ) );
		
		/* Let apps do their stuff */
		$this->memberSync( 'onMerge', array( $otherMember ) );
	}
	
	/**
	 * Add profile visitor
	 *
	 * @param   \IPS\Member $visitor	Member that viewed profile
	 * @return	void
	 */
	public function addVisitor( $visitor )
	{
		$visitors = json_decode( $this->pp_last_visitors, TRUE );
				
		/* If this member is already in the visitor list remove the entry so we can add back in the correct order */
		if( isset( $visitors[ $visitor->member_id ] ) )
		{
			unset( $visitors[ $visitor->member_id ] );
		}
		/* We want to limit to 5 members */
		else if ( \is_array( $visitors ) AND \count( $visitors ) >= 5 )
		{
			$visitors	= array_reverse( $visitors, TRUE );
			array_pop( $visitors );
			$visitors	= array_reverse( $visitors, TRUE );
		}
		
		/* Add the new entry */
		$visitors[ $visitor->member_id ] = time();
		
		/* Encode and save*/
		$this->pp_last_visitors = json_encode( $visitors );
		$this->save();
	}
	
	/**
	 * @brief	Posts Per Day Storage
	 */
	protected $_ppdLimit = NULL;
	
	/**
	 * Check posts per day to see if this member can post.
	 *
	 * @return	bool
	 */
	public function checkPostsPerDay()
	{
		/* Fetch our PPD limit - we should only need to do this once */
		if ( $this->_ppdLimit === NULL )
		{
			$this->_ppdLimit = $this->group['g_ppd_limit'];
		}
		/* We can't actually check guests as we can't store how often they have posted - simply counting content is not viable */
		if ( ! $this->member_id )
		{
			return TRUE;
		}
		
		/* Is there any limit at all? */
		if ( ! $this->_ppdLimit )
		{
			return TRUE;
		}
		
		$count	= $this->members_day_posts[0];
		$time	= $this->members_day_posts[1];
		
		/* Have we posted at all yet? */
		if ( ! $count OR ! $time )
		{
			return TRUE;
		}
		
		/* Are we beyond our 24 hours? */
		if ( $time AND $time < \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp() )
		{
			/* Update member immediately */
			$this->members_day_posts = array( 0, 0 );
			$this->save();
			return TRUE;
		}
		
		/* Still within 24 hours... have we hit the limit? */
		if ( $count >= $this->_ppdLimit )
		{
			if ( $this->group['g_ppd_unit'] )
			{
				/* The limit may have been removed due to number of total posts or days since joining */
				if( $this->group['gbw_ppd_unit_type'] )
				{
					/* Days */
					if ( $this->joined->add( new \DateInterval( "P{$this->group['g_ppd_unit']}D" ) )->getTimestamp() < time() )
					{
						return TRUE;
					}
				}
				else
				{
					/* Posts */
					if ( $this->member_posts >= $this->group['g_ppd_unit'] )
					{
						return TRUE;
					}
				}
			}
			
			return FALSE;
		}
		
		/* Still here? */
		return TRUE;
	}
	
	/**
	 * Check Group Promotion
	 *
	 * @return	void
	 */
	public function checkGroupPromotion()
	{
		/* If we should ignore promotions for this member, do so */
		if( $this->members_bitoptions['ignore_promotions'] )
		{
			return;
		}

		/* Default to member group if none. This shouldn't happen but can and the user error should be prevented. */
		try
		{
			$primaryGroup = \IPS\Member\Group::load( $this->member_group_id );
		}
		catch ( \OutOfRangeException $e )
		{
			$this->member_group_id = \IPS\Settings::i()->member_group;
			\IPS\Log::debug( "Group promotion found a member (#{$this->member_id}: {$this->name}) with an invalid member group", 'group_promotion' );
		}

		/* Just check the primary group, secondary groups should not prevent promoting */
		if( \IPS\Member\Group::load( $this->member_group_id )->g_promote_exclude )
		{
			return;
		}
		
		$ruleToUse = NULL;

		/* Loop over all group promotion rules and get the last one that matches us */
		foreach( \IPS\Member\GroupPromotion::roots() as $rule )
		{
			if( $rule->enabled and $rule->matches( $this ) )
			{
				$ruleToUse = $rule->id;
			}
		}

		/* If there's no rule, return now */
		if( $ruleToUse === NULL )
		{
			return;
		}

		/* If we matched a rule, get that rule now */
		$ruleToUse	= \IPS\Member\GroupPromotion::load( $ruleToUse );

		/* Set the primary and secondary groups as appropriate */
		$action = $ruleToUse->_actions;

		if( $action['primary_group'] AND $this->member_group_id != $action['primary_group'] )
		{
			try
			{
				$group = \IPS\Member\Group::load( $action['primary_group'] );

				/* Need to store the history before we actually change the group, otherwise history shows "promoted from group X to X" */
				$this->logHistory( 'core', 'group', array( 'type' => 'primary', 'by' => 'promotion', 'id' => $ruleToUse->id, 'old' => $this->member_group_id, 'new' => $action['primary_group'] ) );

				$this->member_group_id = $action['primary_group'];
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Log::debug( 'Promotion ' .  $ruleToUse->id . ' tried to set not existing member group' );
			}
		}

		if( \count( $action['secondary_group'] ) OR \count( $action['secondary_remove'] ) )
		{
			$secondaryGroups = array_filter( explode( ',', $this->_data['mgroup_others'] ) );
			$oldSecondaryGroups = $secondaryGroups;

			foreach ( $action['secondary_group'] as $key => $group )
			{
				try
				{
					$group = \IPS\Member\Group::load( $group );
				}
				catch ( \OutOfRangeException $e )
				{
					unset( $action['secondary_group'][$key] );
					\IPS\Log::debug( 'Promotion ' .  $ruleToUse->id . ' tried to set not existing member group' );
				}
			}
			if( \count( $action['secondary_group'] ) )
			{
				if( array_diff( $action['secondary_group'], $secondaryGroups ) )
				{
					$secondaryGroups = array_merge( $secondaryGroups, $action['secondary_group'] );
				}
			}

			if( \count( $action['secondary_remove'] ) )
			{
				foreach( $action['secondary_remove'] as $groupToRemove )
				{
					while( \in_array( $groupToRemove, $secondaryGroups ) )
					{
						$key = array_search( $groupToRemove, $secondaryGroups );

						unset( $secondaryGroups[ $key ] );
					}
				}
			}
			
			if ( array_diff( $secondaryGroups, $oldSecondaryGroups ) or array_diff( $oldSecondaryGroups, $secondaryGroups ) )
			{
				$this->logHistory( 'core', 'group', array( 'type' => 'secondary', 'by' => 'promotion', 'id' => $ruleToUse->id, 'old' => $oldSecondaryGroups, 'new' => $secondaryGroups ) );
			}

			$this->mgroup_others = implode( ',', array_unique( $secondaryGroups ) );
		}
	}

	/**
	 * Is the current user allowed to use the contact us form
	 *
	 * @return bool
	 */
	public function canUseContactUs()
	{
		try
		{
			$module = \IPS\Application\Module::get( 'core', 'contact', 'front' );
		}
		catch ( \OutOfRangeException $e )
		{
			return FALSE;
		}
		
		if ( !$this->canAccessModule( $module ) )
		{
			return FALSE;
		}

		/* If all groups have access, we can */
		if( \IPS\Settings::i()->contact_access != '*' )
		{
			/* Check member */
			$memberGroups	= array_merge( array( $this->member_group_id ), array_filter( explode( ',', $this->mgroup_others ) ) );
			$accessGroups	= explode( ',', \IPS\Settings::i()->contact_access );

			/* Are we in an allowed group? */
			if( \count( array_intersect( $accessGroups, $memberGroups ) ) )
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * Can use the messenger system
	 *
	 * @return bool
	 */
	public function canUseMessenger(): bool
	{
		/* Test member */
		if ( $this->members_disable_pm == 2 )
		{
			return false;
		}

		/* Test module permissions */
		return $this->canAccessModule( \IPS\Application\Module::get( 'core', 'messaging' ) );
	}

	/**
	 * Get a unique member hash for use in RSS keys and such.
	 *
	 * If the member has a current style password hash, the md5() of that value will be returned. Otherwise,
	 * one will be generated, stored in a new column in the members table, and its md5() will be returned.
	 *
	 * @return	string
	 */
	public function getUniqueMemberHash()
	{
		/* If this is a guest, just return a random string. */
		if ( !$this->member_id )
		{
			return \IPS\Login::generateRandomString();
		}
		
		/* Return the password hash if we already have it */
		if ( !empty( $this->members_pass_hash ) )
		{
			return md5( ( $this->members_pass_hash ?: $this->email ) . $this->members_pass_salt );
		}

		/* Do we already have one? */
		if ( !empty( $this->unique_hash ) )
		{
			return $this->unique_hash;
		}

		/* Otherwise, generate one */
		$this->unique_hash = md5( \IPS\Login::generateRandomString(32) );

		$this->save();

		return $this->unique_hash;
	}
	
	/**
	 * Perform a database update on all members
	 *
	 * Typically, updating the entire table locks the table which makes other queries stack up.
	 * On busy sites this is a real problem. We mitigate this by updating in batches via a background task
	 *
	 * We replace if the fields we want to update are the exact same on a subsequent call to this method, so if you had ( 'skin' => 2 ) and then ( 'skin' => 3 ),
	 * the row matching 'skin' => 2 will be removed from the queue table and the 'skin' => 3 row will replace it. This is to ensure that if you update the same fields while
	 * an existing task is running, it will update the members with the latest data.
	 *
	 * @note This task can be processing or waiting to process and the member can still change the value, so it could be possible for the member to set a 'skin' parameter and then have this
	 * overwritten when this task processes.
	 *
	 * @param	array	$update		array( field => value ) pairs to be used directly in a \IPS\Db::i()->update( 'core_members', $update ) query
	 * @param	int		$severity	Severity level. 1 being highest, 5 lowest
	 * @return	void
	 */
	public static function updateAllMembers( $update, $severity=3 )
	{
		\IPS\Task::queue( 'core', 'UpdateMembers', array( 'update' => $update ), $severity, array( 'update' ) );
	}

	/**
	 * Invalidate the current "create menu" key
	 *
	 * @return	void
	 */
	public static function clearCreateMenu()
	{
		\IPS\Settings::i()->changeValues( array( 'member_menu_create_key' => mt_rand() ) );
	}
	
	/**
	 * Invalidate all sessions and auto-login-keys. Called after the user changes their email address or password.
	 *
	 * @param	bool|string	$frontEndSessions	Boolean value indicating if front-end sessions should be cleared, or a string containing a session ID to wipe all except that one
	 * @param	bool|string	$acpSessions		Boolean value indicating if acp sessions should be cleared, or a string containing a session ID to wipe all except that one
	 * @param	bool		$loginKeys			Boolean value indicating if login keys (used for "Remember Me" logins) should be wiped
	 * @return	void
	 */
	public function invalidateSessionsAndLogins( $frontEndSessions=TRUE, $acpSessions=TRUE, $loginKeys=TRUE )
	{
		/* Terminate any active sessions */
		if ( $frontEndSessions !== FALSE )
		{
			\IPS\Session\Store::i()->deleteByMember( $this->member_id, NULL, $frontEndSessions !== TRUE ? array( $frontEndSessions ) : NULL );
		}
		if ( $acpSessions !== FALSE )
		{
			$where = array( array( 'session_member_id=?', $this->member_id ) );
			if ( \is_string( $acpSessions ) )
			{
				$where[] = array( 'session_id<>?', $acpSessions );
			}
			\IPS\Db::i()->delete( 'core_sys_cp_sessions', $where );
		}
		
		/* Wipe login keys to stop "Remember Me" cookies automatically logging us in */
		if ( $loginKeys )
		{
			\IPS\Db::i()->update( 'core_members_known_devices', array( 'login_key' => NULL ), array( 'member_id=?', $this->member_id ) );
		}
		
		/* Invalidate any pending "Forgot Password" or 2FA recovery emails, because they provide a doorway into accessing the account */
		\IPS\Db::i()->delete( 'core_validating', array( 'member_id=? AND ( lost_pass=1 OR forgot_security=1 )', $this->member_id ) );
	}
	
	/* !Registration/Validation */
	
	/**
	 * Call after completed registration to send email for validation if required or flag for admin validation
	 *
	 * @param	bool				$noEmailValidationRequired	If the user's email is implicitly trusted (for example, provided by a third party), set this to TRUE to bypass email validation
	 * @param	bool				$doNotDelete				If TRUE, the account will not be deleted in the normal cleanup of unvalidated accounts. Used for accounts created in Commerce checkout.
	 * @param	array|NULL			$postBeforeRegister			The row from core_post_before_registering if applicable
	 * @param	\IPS\Http\Url|NULL	$refUrl						The URL the user should be redirected to after validation
	 * @return	void
	 */
	public function postRegistration( $noEmailValidationRequired = FALSE, $doNotDelete = FALSE, $postBeforeRegister = NULL, $refUrl = NULL )
	{
		/* Work out validation type */
		$validationType = \IPS\Settings::i()->reg_auth_type;
		if ( $noEmailValidationRequired )
		{
			switch ( $validationType )
			{
				case 'user':
					$validationType = 'none';
					break;
				case 'admin_user':
					$validationType = 'admin';
					break;
			}
		}
		
		/* Validation */
		if ( $validationType != 'none' )
		{
			/* Set the flag */
			$this->members_bitoptions['validating'] = TRUE;
			$this->save();
			
			/* Prevent duplicates from double clicking, etc */
			\IPS\Db::i()->delete( 'core_validating', array( 'member_id=? and new_reg=1', $this->member_id ) );
			
			/* Insert a record */
			$vid = md5( $this->members_pass_hash . \IPS\Login::generateRandomString() );
			\IPS\Db::i()->insert( 'core_validating', array(
				'vid'		   	=> $vid,
				'member_id'	 	=> $this->member_id,
				'entry_date'	=> time(),
				'new_reg'	   	=> 1,
				'ip_address'	=> $this->ip_address,
				'spam_flag'	 	=> ( $this->members_bitoptions['bw_is_spammer'] ) ?: FALSE,
				'user_verified' => ( $validationType == 'admin' ) ?: FALSE,
				'email_sent'	=> ( $validationType != 'admin' ) ? time() : NULL,
				'do_not_delete'	=> $doNotDelete,
				'ref'			=> $refUrl ? ( (string) $refUrl ) : NULL
			) );
			
			/* Send email for validation */
			if ( $validationType != 'admin' )
			{
				\IPS\Email::buildFromTemplate( 'core', 'registration_validate', array( $this, $vid ), \IPS\Email::TYPE_TRANSACTIONAL )->send( $this );
			}
			
			/* Update core_post_before_registering */
			if ( $postBeforeRegister )
			{
				\IPS\Db::i()->update( 'core_post_before_registering', array( 'member' => $this->member_id ), array( 'secret=?', $postBeforeRegister['secret'] ) );
			}
		}
		
		/* If no email-related validation is required, send admin notification */
		if ( $validationType == 'admin' )
		{
			\IPS\core\AdminNotification::send( 'core', 'NewRegValidate', NULL, TRUE, $this );
		}
		elseif ( $validationType == 'none' )
		{
			\IPS\core\AdminNotification::send( 'core', 'NewRegComplete', NULL, TRUE, $this );
		}

		/* Send emails and handle post before register if validation is disabled */
	   if( $validationType == 'none' )
	   {
	   		$secret = $postBeforeRegister['secret'] ?? NULL ;
			$this->_processPostBeforeRegistering( $secret );
			$this->_sendWelcomeEmail();
			$this->_sendReferralNotification();
	   }
   }
   	
	/**
	 * Email Validation Confirmed
	 *
	 * @param	array	$record		validating record
	 * @return	void
	 */
	public function emailValidationConfirmed( $record )
	{
		/* Log */
		$this->logHistory( 'core', 'account', array( 'type' => 'email_validated' ) );
		
		/* If admin validation is required, set the flag and send an admin notification */
		if ( \IPS\Settings::i()->reg_auth_type == 'admin_user' )
		{
			\IPS\core\AdminNotification::send( 'core', 'NewRegValidate', NULL, TRUE, $this );
			
			\IPS\Db::i()->update( 'core_validating', array( 'user_verified' => TRUE ), array( 'member_id=?', $this->member_id ) );
		}
		
		/* Otherwise, validation is complete */
		else
		{
			$this->validationComplete();
		}		
	}
	
	/**
	 * Final Validation Complete
	 * If no validation is enabled: this is never called
	 * If email-only validation is enabled: this is called after the user has validated their email address or if the admin manually validates the account
	 * If admin (including email and admin) validation is enabled: this is called after the admin has validated the account
	 *
	 * @param	\IPS\Member|FALSE	$initiatedByAdmin			If an administrator performed the validation, the admin that did so
	 * @param	string				$postBeforeRegisterSecret	The secret from core_post_before_register, if applicable
	 * @return	void
	 */
	public function validationComplete( $initiatedByAdmin = FALSE, $postBeforeRegisterSecret = NULL )
	{
		/* Process any posts made before registering */
		$this->_processPostBeforeRegistering( $postBeforeRegisterSecret );
		
		/* Send a success email */
		$this->_sendWelcomeEmail();

		/* Send referral notification */
		$this->_sendReferralNotification();
		
		/* Send an admin notification */
		\IPS\core\AdminNotification::send( 'core', 'NewRegComplete', NULL, TRUE, $this, $initiatedByAdmin );
		
		/* Delete rows */
		\IPS\Db::i()->delete( 'core_validating', array( 'member_id=?', $this->member_id ) );
		
		/* Reset the flag */
		$this->members_bitoptions['validating'] = FALSE;
		$this->save();

		\IPS\Api\Webhook::fire( 'member_registration_complete', $this, $this->webhookFilters() );
		
		/* Sync */
		$this->memberSync( 'onValidate' );
	}
	
	/**
	 * Process any posts made before registering
	 *
	 * @param	string	$secret	The secret, if just created (necessary for avoiding R/W separation issues if no validation is required)
	 * @return	void
	 */
	protected function _processPostBeforeRegistering( $secret = NULL )
	{		
		$where = $secret ? array( '`member`=? OR secret=?', $this->member_id, $secret ) : array( '`member`=?', $this->member_id );
		
		foreach ( \IPS\Db::i()->select( '*', 'core_post_before_registering', $where ) as $row )
		{
			\IPS\Db::i()->delete( 'core_post_before_registering', array( 'class=? AND id=?', $row['class'], $row['id'] ) );
			
			try
			{
				$class = $row['class'];
				$content = $class::load( $row['id'] );
				
				if ( $content->author()->member_id )
				{
					throw new \OutOfRangeException;
				}
				
				$content->changeAuthor( $this, FALSE );
				
				if ( $content instanceof \IPS\Content\Review )
				{
					$moderated = $content->item()->moderateNewReviews( $this, FALSE );
				}
				elseif ( $content instanceof \IPS\Content\Comment )
				{
					$moderated = $content->item()->moderateNewComments( $this, FALSE );
				}
				else
				{
					$moderated = $content::moderateNewItems( $this, $content->containerWrapper(), FALSE );
				}
				
				if ( $moderated )
				{
					if ( isset( $content::$databaseColumnMap['hidden'] ) )
					{
						$column = $content::$databaseColumnMap['hidden'];
						$content->$column = 1;
					}
					elseif ( isset( $content::$databaseColumnMap['approved'] ) )
					{
						$column = $content::$databaseColumnMap['approved'];
						$content->$column = 0;
					}
										
					$content->save();
					$content->sendUnapprovedNotification();
					
					$container = NULL;
					if ( $content instanceof \IPS\Content\Comment )
					{
						if ( $content instanceof \IPS\Content\Review )
						{
							$content->item()->resyncReviewCounts();
						}
						else
						{
							$content->item()->resyncCommentCounts();
						}
						$content->item()->save();
						$container = $content->item()->containerWrapper();
					}
					else
					{
						$container = $content->containerWrapper();
					}
					
					if ( $container )
					{
						$container->resetCommentCounts();
						$container->save();
					}
				}
				else
				{
					/* Set the value so that checkProfanityFilters() thinks it's good */
					$column = NULL;
					if ( isset( $content::$databaseColumnMap['hidden'] ) )
					{
						$column = $content::$databaseColumnMap['hidden'];
						$oldVal = $content->$column;
						$content->$column = 0;
					}
					elseif ( isset( $content::$databaseColumnMap['approved'] ) )
					{
						$column = $content::$databaseColumnMap['approved'];
						$oldVal = $content->$column;
						$content->$column = 1;
					}
					
					/* Run through checkProfanityFilters() */
					if ( $content->checkProfanityFilters( $content instanceof \IPS\Content\Comment and $content->item()::$firstCommentRequired and $content->isFirst() ) )
					{
						$content->sendUnapprovedNotification();
					}
					else
					{
						if ( $column )
						{
							$content->$column = $oldVal;
						}
						
						$content->unhide( FALSE );
					}
				}
			}
			catch ( \OutOfRangeException $e ) { }
		}
	}

	/**
	 * Send a welcome email
	 * Called if no validation is enabled or after validation is complete
	 *
	 * @return	void
	 */
	protected function _sendWelcomeEmail()
	{
		try
		{
			\IPS\Email::buildFromTemplate( 'core', 'registration_complete', array( $this ), \IPS\Email::TYPE_TRANSACTIONAL )->send( $this );
		}
		catch( \ErrorException $e ) { }
	}

	/**
	 * Send a referral notification to referrer
	 * Called if no validation is enabled or after validation is complete
	 *
	 * @return	void
	 */
	protected function _sendReferralNotification()
	{
		try
		{
			$referrer = \IPS\Db::i()->select( 'referred_by', 'core_referrals', array( 'member_id=?', $this->member_id ) )->first();
			$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'referral', $this, array( $this ) );
			$notification->recipients->attach( \IPS\Member::load( $referrer ) );
			$notification->send();
		}
		catch( \OutOfRangeException | \UnderflowException $e ) {}
	}

	/**
	 * Get how often the member changed his name
	 *
	 * @return bool
	 */
	public function hasNameChanges()
	{
		try
		{
			return \IPS\Db::i()->select( 'count(*)', 'core_member_history', array( 'log_member=? AND log_app=? AND log_type=?', $this->member_id, 'core', 'display_name' ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			return FALSE;
		}
	}
		
	/**
	 * Profile Sync
	 *
	 * @return	array
	 */
	public function profileSync()
	{		
		$profileSync = $this->profilesync;
		
		foreach ( \IPS\Login::methods() as $method )
		{
			if ( $method->canProcess( $this ) )
			{
				foreach ( $method->forceSync() as $type )
				{
					if ( !isset( $profileSync[ $type ] ) or $profileSync[ $type ]['handler'] !== $method->_id )
					{
						$profileSync[ $type ] = array( 'handler' => $method->_id, 'ref' => NULL, 'error' => NULL );
					}
				}
			}
		}
						
		if ( \is_array( $profileSync ) )
		{
			foreach ( $profileSync as $k => $v )
			{
				if ( $k === 'status' AND \IPS\Settings::i()->profile_comments AND $this->canAccessModule( \IPS\Application\Module::get( 'core', 'members', 'front' ) ) and $this->pp_setting_count_comments and !$this->members_bitoptions['bw_no_status_update'] and !$this->group['gbw_no_status_import'] )
				{
					foreach ( $v as $methodId => $data )
					{
						try
						{
							$method = \IPS\Login\Handler::load( $methodId );
						}
						catch ( \OutOfRangeException $e )
						{
							unset( $profileSync['status'][ $methodId ] );
							continue;
						}

						/* Check the method is enabled */
						if( !$method->_enabled )
						{
							continue;
						}

						/* Check we're syncing this item */
						if ( !\in_array( 'status', $method->forceSync() ) and !\in_array( 'status', $method->syncOptions( $this ) ) )
						{
							continue;
						}
						
						try
						{
							$profileSync['status'][ $methodId ]['error'] = NULL;
							foreach ( $method->userStatuses( $this, $profileSync['status'][ $methodId ]['lastsynced'] ? \IPS\DateTime::ts( $profileSync['status'][ $methodId ]['lastsynced'] ) : NULL ) as $status )
							{
								$status->member_id = $this->member_id;
								$status->imported = TRUE;
								$status->save();
								
								\IPS\Content\Search\Index::i()->index( $status );
							}
							$profileSync['status'][ $methodId ]['lastsynced'] = time();
						}
						catch ( \IPS\Login\Exception $e )
						{
							unset( $profileSync['status'][ $methodId ] );
						}
						catch ( \DomainException $e )
						{
							$profileSync['status'][ $methodId ]['error'] = $e->getMessage();
						}
						catch ( \Exception $e )
						{
							\IPS\Log::log( $e, 'profilesync' );
							$profileSync['status'][ $methodId ]['error'] = 'profilesync_generic_error';
						}
					}
				}
				else
				{
					try
					{
						$method = \IPS\Login\Handler::load( $v['handler'] );
					}
					catch ( \OutOfRangeException $e )
					{
						unset( $profileSync[ $k ] );
						continue;
					}

					/* Check the method is enabled */
					if( !$method->_enabled )
					{
						continue;
					}

					/* Check we're syncing this item */
					if ( !\in_array( $k, $method->forceSync() ) and !\in_array( $k, $method->syncOptions( $this ) ) )
					{
						continue;
					}
					
					try
					{
						$profileSync[ $k ]['error'] = NULL;
						
						switch ( $k )
						{
							case 'email':
								$email = $method->userEmail( $this );
								if ( $email and $email != $this->email )
								{
									if ( $error = \IPS\Login::emailIsInUse( $email, $this ) )
									{
										throw new \DomainException('member_email_exists');
									}
									
									foreach ( \IPS\Db::i()->select( 'ban_content', 'core_banfilters', array( "ban_type=?", 'email' ) ) as $bannedEmail )
									{	 			
										if ( preg_match( '/^' . str_replace( '\*', '.*', preg_quote( $bannedEmail, '/' ) ) . '$/i', $this->value ) )
										{
											throw new \DomainException( 'form_email_banned' );
										}
									}
									
									if ( \IPS\Settings::i()->allowed_reg_email !== '' AND $allowedEmailDomains = explode( ',', \IPS\Settings::i()->allowed_reg_email )  )
									{
										$matched = FALSE;
										foreach ( $allowedEmailDomains AS $domain )
										{
											if( \mb_stripos( $email,  "@" . $domain ) !== FALSE )
											{
												$matched = TRUE;
											}
										}

										if ( \count( $allowedEmailDomains ) AND !$matched )
										{
											throw new \DomainException( 'form_email_banned' );
										}
									}
									
									$this->logHistory( 'core', 'email_change', array( 'old' => $this->email, 'new' => $email, 'by' => 'profilesync', 'id' => $method->id, 'service' => $method::getTitle() ) );
									$this->email = $email;
								}
								break;
								
							case 'name':
								$name = $method->userProfileName( $this );
								if ( $name and $name != $this->name )
								{
									if ( mb_strlen( $name ) < \IPS\Settings::i()->min_user_name_length )
									{
										throw new \DomainException('form_minlength_unspecific');
									}
									if ( mb_strlen( $name ) > \IPS\Settings::i()->max_user_name_length )
									{
										throw new \DomainException('form_minlength_unspecific');
									}

									if ( !\IPS\Login::usernameIsAllowed( $name ) )
									{
										throw new \DomainException('form_name_banned');
									}
									
									if ( \IPS\Login::usernameIsInUse( $name, $this ) )
									{
										throw new \DomainException('member_name_exists');
									}
									
									foreach( \IPS\Db::i()->select( 'ban_content', 'core_banfilters', array("ban_type=?", 'name') ) as $bannedName )
									{
										if( preg_match( '/^' . str_replace( '\*', '.*', preg_quote( $bannedName, '/' ) ) . '$/i', $this->value ) )
										{
											throw new \DomainException( 'form_name_banned' );
										}
									}
									
									$this->logHistory( 'core', 'display_name', array( 'old' => $this->name, 'new' => $name, 'by' => 'profilesync', 'id' => $method->id, 'service' => $method::getTitle() ) );
									$this->name = $name;
								}
								break;
		
							case 'photo':
								$photoUrl = $method->userProfilePhoto( $this );
								if ( (string) $photoUrl )
								{
									/* Make sure we have a scheme */
									if( !$photoUrl->data[ \IPS\Http\Url::COMPONENT_SCHEME ] )
									{
										$photoUrl = $photoUrl->setScheme( 'https' );
									}

									$contents = $photoUrl->request()->get();
									$md5 = md5( $contents );
									
									if ( $contents AND ( !isset( $v['ref'] ) or $md5 != $v['ref'] ) )
									{										
										$photoVars = explode( ':', $this->group['g_photo_max_vars'] );
										
										try
										{
											$image = \IPS\Image::create( $contents );
										}
										catch( \Exception $e )
										{
											throw new \DomainException('member_photo_bad_url');
										}
										if( $image->isAnimatedGif and !$this->group['g_upload_animated_photos'] )
										{
											throw new \DomainException('member_photo_upload_no_animated');
										}
										if ( $image->width > $photoVars[1] or $image->height > $photoVars[2] )
										{
											$image->resizeToMax( $photoVars[1], $photoVars[2] );
										}
										if ( $photoVars[0] and \strlen( $image ) > ( $photoVars[0] * 1024 ) )
										{
											throw new \DomainException('upload_too_big_unspecific');
										}
																														
										$newFile = \IPS\File::create( 'core_Profile', 'imported-photo-' . $this->member_id . '.' . $image->type, (string) $image );
										
										$this->pp_photo_type  = 'custom';
										$this->pp_main_photo  = NULL;
										$this->pp_main_photo  = (string) $newFile;
										$thumbnail = $newFile->thumbnail( 'core_Profile', \IPS\PHOTO_THUMBNAIL_SIZE, \IPS\PHOTO_THUMBNAIL_SIZE, TRUE );
										$this->pp_thumb_photo = (string) $thumbnail;
										if ( isset( $v['ref'] ) )
										{
											$this->photo_last_update = time();
										}
										$this->logHistory( 'core', 'photo', array( 'action' => 'new', 'type' => 'profilesync', 'id' => $method->id, 'service' => $method::getTitle() ) );
										
										$profileSync['photo']['ref'] = $md5;
									}
								}
								break;
								
							case 'cover':
								/* Sync the cover photo only if the user has permission to have one */
								if ( $this->group['gbw_allow_upload_bgimage'] )
								{
									$coverPhotoUrl = $method->userCoverPhoto( $this );
									if ( (string) $coverPhotoUrl )
									{
										/* Make sure we have a scheme */
										if( !$coverPhotoUrl->data[ \IPS\Http\Url::COMPONENT_SCHEME ] )
										{
											$coverPhotoUrl = $coverPhotoUrl->setScheme( 'https' );
										}

										$contents = $coverPhotoUrl->request()->get();
										$md5 = md5( $contents );

										if ( !isset( $v['ref'] ) or $md5 != $v['ref'] )
										{
											try
											{
												$image = \IPS\Image::create( $contents );
											}
											catch( \Exception $e )
											{
												throw new \DomainException('member_photo_bad_url');
											}

											if ( $this->group['g_max_bgimg_upload'] != -1 and \strlen( $image ) > ( $this->group['g_max_bgimg_upload'] * 1024 ) )
											{
												throw new \DomainException('upload_too_big_unspecific');
											}

											$newFile = \IPS\File::create( 'core_Profile', 'imported-cover-photo-' . $this->member_id . '.' . $image->type, (string) $image );

											if ( $this->pp_cover_photo )
											{
												try
												{
													\IPS\File::get( 'core_Profile', $this->pp_cover_photo )->delete();
												}
												catch ( \Exception $e ) { }
											}

											$this->pp_cover_photo = (string) $newFile;
											$this->logHistory( 'core', 'photo', array( 'action' => 'new', 'type' => 'profilesync', 'id' => $method->id, 'service' => $method::getTitle() ) );

											$profileSync['cover']['ref'] = $md5;
										}
									}
								}
								break;
						}
						
					}
					catch ( \IPS\Login\Exception $e )
					{
						unset( $profileSync[ $k ] );
					}
					catch ( \DomainException $e )
					{
						$profileSync[ $k ]['error'] = $e->getMessage();
					}
					catch ( \Exception $e )
					{
						\IPS\Log::log( $e, 'profilesync' );
						$profileSync[ $k ]['error'] = 'profilesync_generic_error';
					}
				}
			}

		}
				
		$this->profilesync = $profileSync;
		$this->profilesync_lastsync = time();
		$this->save();
	}

	/**
	 * Can this member be ignored?
	 *
	 * @return bool
	 */
	public function canBeIgnored()
	{
		if ( !\IPS\Settings::i()->ignore_system_on )
		{
			return FALSE;
		}
		
		if( !$this->member_id )
		{
			return FALSE;
		}

		if ( $this->modPermission() AND !$this->modPermission( 'can_moderator_be_ignored' ) )
		{
			return FALSE;
		}

		if ( $this->group['gbw_cannot_be_ignored'] )
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Log Member Action
	 *
	 * @param	mixed		$app			The application action applies to
	 * @param	string		$type			Log type
	 * @param	mixed		$extra			Any extra data for the type
	 * @param	mixed		$by				The member performing the action. NULL for currently logged in member or FALSE for no member
	 * 
	 * @return	void
	 */
	public function logHistory( $app, $type, $extra=NULL, $by=NULL )
	{
		if ( $this->member_id )
		{
			if ( $by === NULL and \IPS\Dispatcher::hasInstance() )
			{
				$by = \IPS\Session::i()->member; // Not \IPS\Member::loggedIn() because if this is an admin logged in as a member, we want to log that the action was done by the admin
			}

			\IPS\Db::i()->insert( 'core_member_history', array(
				'log_app'			=> $app,
				'log_member'		=> (int) $this->member_id,
				'log_by'			=> $by ? $by->member_id : NULL,
				'log_type'			=> $type,
				'log_data'			=> json_encode( $extra ),
				'log_date'			=> microtime( TRUE ),
				'log_ip_address'	=> \IPS\Request::i()->ipAddress()
			) );
		}
	}
	
	/* !Top Members */
	/**
	 * @brief	Top members overview
	 */
	const TOP_MEMBERS_OVERVIEW = 1;

	/**
	 * @brief	Top members with filters
	 */
	const TOP_MEMBERS_FILTERS = 2;
	
	/**
	 * @brief	Custom count for a top member resu;t
	 */
	public $_customCount = NULL;
	
	/**
	 * Get available Top Members options
	 *
	 * @param	int		$filter		See TOP_MEMBERS_* constants
	 * @return	array
	 */
	public static function topMembersOptions( $filter = 0 )
	{
		$filters = array(
			'pp_reputation_points' => \IPS\Member::loggedIn()->language()->addToStack('leaderboard_tab_reputation'),
			'member_posts' => \IPS\Member::loggedIn()->language()->addToStack('leaderboard_tab_posts')
		);
		
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', TRUE ) as $object )
		{
			foreach( $object->classes as $item )
			{
				$commentClass = NULL;
				if ( isset( $item::$commentClass ) )
				{
					$commentClass = $item::$commentClass;
				}
				
				if ( $item::$firstCommentRequired and isset( $commentClass::$databaseColumnMap['author'] ) )
				{
					$filters[ $commentClass ] = \IPS\Member::loggedIn()->language()->addToStack( 'leaderboard_tab_x', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack("{$commentClass::$title}_pl_lc") ) ) );
				}
				elseif ( isset( $item::$databaseColumnMap['author'] ) )
				{
					$filters[ $item ] = \IPS\Member::loggedIn()->language()->addToStack( 'leaderboard_tab_x', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack("{$item::$title}_pl_lc") ) ) );
				}
			}
		}
		
		if ( $filter )
		{
			$available = $filter === static::TOP_MEMBERS_OVERVIEW ? \IPS\Settings::i()->reputation_top_members_overview : \IPS\Settings::i()->reputation_top_members_filters;
			if ( $available != '*' )
			{
				$available = explode( ',', $available );
				$filters = array_filter( $filters, function( $k ) use ( $available ) {
					return \in_array( $k, $available );
				}, ARRAY_FILTER_USE_KEY );
			}
		}
		
		return $filters;
	}
	
	/**
	 * Get top members for a particular type
	 *
	 * @param	string	$type	The type (as returned by topMembersOptions())
	 * @param	int		$limit	Number to get
	 * @return	\Traversable
	 */
	public static function topMembers( $type, $limit )
	{
		if ( \in_array( $type, array( 'pp_reputation_points', 'member_posts' ) ) )
		{
			$where = array(
				array( "completed=1" ),
				array( "temp_ban != -1" ),
				array( $type . '>0' ),
				\IPS\Db::i()->in( 'member_group_id', explode( ',', \IPS\Settings::i()->leaderboard_excluded_groups ), TRUE )
			);
			$orderBy = $type . ' DESC';
			
			return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', $where, $orderBy, $limit ), 'IPS\Member' );
		}
		else
		{
			$storeKey = 'store_' . str_replace( '\\', '-', $type );
			$stored = isset( \IPS\Data\Store::i()->$storeKey ) ? \IPS\Data\Store::i()->$storeKey : NULL;
			
			if ( ! $stored or ( ( time() - $stored['time'] ) > 300 ) )
			{
				$members = array();
				foreach( $type::topMembersQuery( $limit ) as $row )
				{
					$members[ $row[ $type::$databasePrefix . $type::$databaseColumnMap['author'] ] ] = $row['count'];
				}
				
				/* Make sure these members actually exist */
				$memberIds = array();

				/* Make sure they're not in an excluded group */
				$memberWhere = array();
				$memberWhere[] = \IPS\Db::i()->in( 'member_id', array_keys( $members ) );
				$memberWhere[] = \IPS\Db::i()->in( 'member_group_id', explode( ',', \IPS\Settings::i()->leaderboard_excluded_groups ), TRUE );

				foreach( \IPS\Db::i()->select( 'member_id', 'core_members', $memberWhere ) AS $member_id )
				{
					$memberIds[ $member_id ] = $members[ $member_id ];
				}
			
				\IPS\Data\Store::i()->$storeKey = array( 'time' => time(), 'memberIds' => $memberIds );
				$stored = \IPS\Data\Store::i()->$storeKey;
			}

			$results = array();
			foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', \IPS\Db::i()->in( 'member_id', array_keys( $stored['memberIds'] ) ), NULL, $limit ), 'IPS\Member' ) as $member )
			{
				$member->_customCount = $stored['memberIds'][ $member->member_id ];
				$results[ $member->member_id ] = $member;
			}
			
			/* Sort by custom value DESC */
			uasort( $results, function( $a, $b ) {
				return ( $a->_customCount == $b->_customCount ) ? 0 : ( ( $a->_customCount < $b->_customCount ) ? 1 : -1 );
			});

			/* Preload follower data */
			if( \count( $results ) )
			{
				\IPS\Member::loggedIn()->preloadMemberFollowers( array_keys( $results ) );
			}

			return $results;
		}
	}

	/**
	 * Preload follower information for top members
	 *
	 * @note	Allows us to run two queries instead of two per member
	 * @param	array	$memberIds	Member IDs to preload
	 * @return	void
	 */
	public function preloadMemberFollowers( $memberIds )
	{
		/* Preload the "are we following" flag */
		foreach( $memberIds as $memberId )
		{
			$this->_following[ md5( 'coremember' . $memberId ) ]	= FALSE;
		}

		foreach( \IPS\Db::i()->select( 'follow_rel_id', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id IN(' . implode( ',', $memberIds ) . ') AND follow_member_id=?', 'core', 'member', $this->member_id ) ) as $followerId )
		{
			$this->_following[ md5( 'coremember' . $followerId ) ]	= TRUE;
		}

		/* And then preload the total follower counts */
		static::_followersCount( 'member', $memberIds );
	}
	
	/* !Profile Completion */
	
	/**
	 * @brief	Profile Completion Cache
	 */
	public $_profileCompletion = NULL;
	
	/**
	 * Returns suggested profile items
	 *
	 * @return	array
	 */
	public function profileCompletion()
	{
		if ( $this->_profileCompletion === NULL )
		{
			$this->_profileCompletion = array();
			$this->_profileCompletion['required'] = array();
			$this->_profileCompletion['suggested'] = array();
			
			if ( $this->member_id AND !$this->members_bitoptions['profile_completed'] )
			{
				foreach( Member\ProfileStep::loadAll() AS $id => $step )
				{
					$this->_profileCompletion[ ( $step->required ) ? 'required' : 'suggested' ][ $step->id ] = $step->completed( $this );
				}
			}
		}
		
		return $this->_profileCompletion;
	}
	
	/**
	 * Profile Completion Percentage
	 *
	 * @return	\IPS\Math\Number
	 */
	public function profileCompletionPercentage()
	{
		if ( $this->members_bitoptions['profile_completed'] )
		{
			return new \IPS\Math\Number( '100' );
		}
		
		$total	= 0;
		$done	= 0;
		
		/* Check Required Ones */
		$completion = $this->profileCompletion();
		foreach( $completion['required'] AS $key => $value )
		{
			$total++;
			if ( $value )
			{
				$done++;
			}
		}
		
		/* Now Suggested */
		foreach( $completion['suggested'] AS $key => $value )
		{
			$total++;
			if ( $value )
			{
				$done++;
			}
		}
		
		/* Are we actually done? */
		if ( $done === $total AND $this->members_bitoptions['profile_completed'] === FALSE )
		{
			$this->members_bitoptions['profile_completed'] = TRUE;
			$this->save();
		}
		
		if ( $this->members_bitoptions['profile_completed'] )
		{
			return new \IPS\Math\Number( '100' );
		}
		elseif( !$total )
		{
			return new \IPS\Math\Number( '100' );
		}
		else
		{
			return new \IPS\Math\Number( (string) round( 100 / $total * $done ) );
		}
	}
	
	/**
	 * Next Profile Step
	 *
	 * @return	\IPS\Member\ProfileStep|NULL
	 */
	public function nextProfileStep()
	{
		if ( $this->members_bitoptions['profile_completed'] )
		{
			return NULL;
		}
		
		$completed = $this->profileCompletion();
		
		if ( !\count( $completed['suggested'] ) )
		{
			return NULL;
		}
		
		$nextStep = NULL;
		foreach( $completed['suggested'] AS $id => $complete )
		{
			if ( !$complete )
			{
				$nextStep = \IPS\Member\ProfileStep::load( $id );
				break;
			}
		}
		
		return $nextStep;
	}

	/**
	 * Determine if the member can use signatures
	 *
	 * @return bool
	 */
	public function canEditSignature()
	{
		/* If signatures are globally disabled, we can't edit them */
		if( !\IPS\Settings::i()->signatures_enabled )
		{
			return FALSE;
		}

		/* Are they enabled for our group? */
		$sigLimits = explode( ":", $this->group['g_signature_limits'] );

		if( $sigLimits[0] OR $sigLimits[5] == 0 )
		{
			return FALSE;
		}

		/* Are there post count or day restrictions? */
		if( $this->group['g_sig_unit'] )
		{
			if( $this->group['gbw_sig_unit_type'] )
			{
				if ( $this->joined->diff( \IPS\DateTime::create() )->days < $this->group['g_sig_unit'] )
				{
					return FALSE;
				}
			}
			else
			{
				if ( $this->member_posts < $this->group['g_sig_unit'] )
				{
					return FALSE;
				}
			}
		}

		return TRUE;
	}

	/**
	 * Produce a random hex color for a background
	 *
	 * @return string
	 */
	public function coverPhotoBackgroundColor()
	{
		return $this->staticCoverPhotoBackgroundColor( $this->name );
	}

	/**
	 * returns the recent profile visitors
	 *
	 * @return array
	 */
	public function get_profileVisitors()
	{
		$visitors = array();
		$visitorData = array();
		$visitorInfo = json_decode( $this->pp_last_visitors, TRUE );
		if ( !\is_array( $visitorInfo ) )
		{
			$visitorInfo = array();
		}

		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', array( \IPS\Db::i()->in( 'member_id', array_keys( array_reverse( $visitorInfo, TRUE ) ) ) ) ), 'IPS\Member' ) AS $row )
		{
			$visitorData[$row->member_id] = $row;
		}

		foreach( array_reverse( $visitorInfo, TRUE ) as $id => $time )
		{
			if ( isset( $visitorData[$id] ) )
			{
				$visitors[$id]['member'] = $visitorData[$id];
				$visitors[$id]['visit_time'] = $time;
			}
		}

		return $visitors;
	}

	/* Referrals */
	protected $_referredBy = NULL;

	/**
	 * Referred By
	 *
	 * @return	\IPS\Member|NULL
	 */
	public function referredBy()
	{
		if ( $this->_referredBy === NULL )
		{
			try
			{
				$referredByID = \IPS\Db::i()->select( 'referred_by', 'core_referrals', array( 'member_id=?', $this->member_id ) )->first();
				$this->_referredBy = \IPS\Member::load( $referredByID );
			}
			catch ( \UnderflowException | \OutOfRangeException $e )
			{
				$this->_referredBy = NULL;
			}
		}

		return $this->_referredBy;
	}

	/**
	 * Add Referral
	 *
	 * @param	\IPS\Member		$member			The member being referred
	 *
	 * @return	void
	 */
	public function addReferral( $member )
	{
		if ( $this->member_id and $member->member_id )
		{
			\IPS\Db::i()->insert( 'core_referrals', array( 'member_id' => $member->member_id, 'referred_by' => $this->member_id ) );
		}
	}
    
	/**
	 * Get member link preference
	 *
	 * @return	string
	 */
	public function linkPref()
	{
		if ( $this->members_bitoptions['link_pref_unread'] )
		{
			return 'unread';
		}

		if ( $this->members_bitoptions['link_pref_last'] )
		{
			return 'last';
		}

		if ( $this->members_bitoptions['link_pref_first'] )
		{
			return 'first';
		}
		
		return NULL;
	}
	
	/**
	 * Has this member joined recently?
	 *
	 * @return boolean
	 */
	public function joinedRecently() : bool
	{
		if ( $this->member_id AND $this->joined->diff( \IPS\DateTime::ts( time() ) )->days < 30 )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/* !Achievements */

	/**
	 * Get current rank
	 *
	 * @return	array
	 */
	public function rank(): ?\IPS\core\Achievements\Rank
	{
		return \IPS\core\Achievements\Rank::fromPoints( $this->achievements_points );
	}
	
	/**
	 * Get when the current rank was earned
	 *
	 * @return	?\IPS\DateTime
	 */
	public function rankEarned(): ?\IPS\DateTime
	{
		if ( $rank = $this->rank() )
		{
			try
			{
				return \IPS\DateTime::ts( \IPS\Db::i()->select( 'datetime', 'core_points_log', [ '`member`=? AND new_rank=?', $this->member_id, $rank->id ], 'datetime DESC', 1 )->first() );
			}
			catch ( \UnderflowException $e ) { }
		}
		return NULL;
	}
	
	/**
	 * Get next rank
	 *
	 * @return	array
	 */
	public function nextRank(): ?\IPS\core\Achievements\Rank
	{
		foreach ( \IPS\core\Achievements\Rank::getStore() as $rank )
		{
			if ( $rank->points > $this->achievements_points )
			{
				return $rank;
			}
		}
		return NULL;
	}

	protected $rankHistory = NULL;

	/**
	 * Return rank history
	 *
	 * @return array
	 */
	public function rankHistory(): array
	{
		if ( ! $this->rankHistory )
		{
			$return = [ 'earned' => NULL, 'not_earned' => NULL ];
			$query = iterator_to_array( \IPS\Db::i()->select( 'new_rank, datetime', 'core_points_log', ['`member`=? and new_rank IS NOT NULL', $this->member_id] )->setKeyField('new_rank')->setValueField('datetime') );
			$currentRank = $this->rank();

			foreach ( \IPS\core\Achievements\Rank::getStore() as $rank )
			{
				if ( $rank->points == 0 )
				{
					$return['earned'][] = ['rank' => $rank, 'time' => $this->joined ];
				}
				elseif ( $rank->points <= $currentRank->points )
				{
					$return['earned'][] = ['rank' => $rank, 'time' => isset( $query[ $rank->id ] ) ? \IPS\DateTime::ts( $query[ $rank->id ] ) : NULL ];
				}
				else
				{
					$return['not_earned'][] = ['rank' => $rank];
				}
			}

			$this->rankHistory = $return;
		}

		return $this->rankHistory;
	}

	/**
	 * Return the number of days since the last rank upgrade
	 *
	 * @return int
	 */
	public function daysSinceLastRank(): int
	{
		$history = $this->rankHistory();
		$lastRank = NULL;

		foreach( $history['earned'] as $earned )
		{
			if ( $earned['rank']->id == $this->rank()->id )
			{
				break;
			}

			$lastRank = $earned;
		}

		$time = ( $lastRank  and $lastRank['time'] ) ? $lastRank['time'] : $this->joined;
		return \intval( \IPS\DateTime::ts( time() )->diff( $time )->days );
	}

	/**
	 * @brief	List of badge IDs that this member has earned
	 */
	protected $_badgeIds = NULL;
	
	/**
	 * Get list of badge IDs that this member has earned
	 *
	 * @return	array
	 */
	protected function badgeIds(): array
	{
		if ( $this->_badgeIds === NULL )
		{
			$this->_badgeIds = iterator_to_array( \IPS\Db::i()->select( 'badge', 'core_member_badges', [ '`member`=?', $this->member_id ] ) );
		}
		return $this->_badgeIds;
	}
	
	/**
	 * @brief	Iterator of badges this member has earned
	 */
	public $_badgeCount = NULL;
	
	/**
	 * Get count of badges that this member has earned
	 *
	 * @return	int
	 */
	public function badgeCount(): int
	{
		if ( $this->_badgeCount === NULL )
		{
			$this->_badgeCount = \IPS\Db::i()->select( 'count(*)', 'core_member_badges', [ '`member`=?', $this->member_id ] )->first();
		}
		return $this->_badgeCount;
	}
	
	/**
	 * Recent Badges
	 *
	 * @param	int|NULL	$numberToGet	Number to get
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public function recentBadges( $numberToGet ): \IPS\Patterns\ActiveRecordIterator
	{
		return new \IPS\Patterns\ActiveRecordIterator(
			\IPS\Db::i()->select( 'core_badges.*, core_member_badges.member, core_member_badges.badge, core_member_badges.datetime, core_member_badges.rule, core_member_badges.action_log, core_member_badges.actor, core_member_badges.recognize', 'core_member_badges', [ '`member`=?', $this->member_id ], 'datetime DESC', $numberToGet )
				->join( 'core_badges', 'core_member_badges.badge=core_badges.id' ),
			'IPS\core\Achievements\Badge'
		);
	}

	/**
	 * @var array Cache for excluded groups for rules
	 */
	protected static $rulesExcluded = NULL;

	/**
	 * Can this member partake in the fun an frivolity of achievements?
	 *
	 * @return bool
	 */
	public function canHaveAchievements(): bool
	{
		if ( ! \IPS\Settings::i()->achievements_enabled )
		{
			return FALSE;
		}

		if ( ! $this->member_id )
		{
			return FALSE;
		}

		if ( static::$rulesExcluded === NULL )
		{
			static::$rulesExcluded = json_decode( \IPS\Settings::i()->rules_exclude_groups, TRUE );
		}

		if ( ! \is_array( static::$rulesExcluded ) or ! \count( static::$rulesExcluded  ) )
		{
			return TRUE;
		}

		return ! $this->inGroup( static::$rulesExcluded  );
	}
	
	/**
	 * User has done something which could earn Points / Badges
	 *
	 * @param	string	$app				Application key for the action being performed
	 * @param	string	$extension			Extension key for the action being performed
	 * @param	?mixed	$extra				Any additional information to be passed along to the extension (e.g. if a post is being made: the post object)
	 * @param	?\IPS\DateTime	$date		Optional DateTime object to be used as the date the achievement was earned
	 * @return	void
	 */
	public function achievementAction( string $app, string $extension, $extra = NULL, $date = NULL )
	{
		if ( ! $this->canHaveAchievements() )
		{
			return;
		}

		if ( isset( \IPS\core\Achievements\Rule::getStore()["{$app}_{$extension}"] ) )
		{
			$enabled = FALSE;
			foreach ( \IPS\core\Achievements\Rule::getStore()["{$app}_{$extension}"] as $ruleId => $ruleData )
			{
				if ( $ruleData['enabled'] )
				{
					$enabled = TRUE;
				}
			}

			if ( $enabled === TRUE )
			{
				$extensionObject = \IPS\Application::load( $app )->extensions( 'core', 'AchievementAction' )[$extension];

				/* Log it first, because doing so allows us to tell if we have already ran the rules
					for this action (e.g. if someone unlikes and relikes a post, we don't want to keep
					doing the actions for that every time: only once) */
				$logId = \IPS\Db::i()->insert( 'core_achievements_log', [
					'action' => "{$app}_{$extension}",
					'identifier' => $extensionObject->identifier( $this, $extra ),
					'datetime' => ( $date ) ? $date->getTimestamp() : time()
				], FALSE, TRUE );
				if ( !$logId )
				{
					return;
				}
			}
			else
			{
				/* There are no enabled rules with this extension */
				return;
			}

			/* Work out what we have to dish out */
			$awards = [];
			$milestoneHitWithRules = [];
			foreach ( \IPS\core\Achievements\Rule::getStore()["{$app}_{$extension}"] as $ruleId => $ruleData )
			{
				if ( ! $ruleData['enabled'] )
				{
					continue;
				}

				if ( !$ruleData['filters'] or $extensionObject->filtersMatch( $this, $ruleData['filters'], $extra ) )
				{
					/* Did we already award for this milestone? */
					if ( \IPS\core\Achievements\Rule::ruleHasMilestone( $ruleData['filters'] ) )
					{
						$milestoneHitWithRules[ $ruleId ] = [];
					}

					if ( $ruleData['points_subject'] or $ruleData['badge_subject'] )
					{
						if ( array_key_exists( $ruleId, $milestoneHitWithRules ) )
						{
							$milestoneHitWithRules[ $ruleId ][ 'subject-' . $this->member_id ] = 'subject-' . $this->member_id;
						}

						if ( !isset( $awards[ $this->member_id ] ) )
						{
							$awards[ $this->member_id ] = [ 'points' => [], 'badges' => [], 'actor' => [] ];
						}
						if ( $ruleData['points_subject'] )
						{
							$awards[ $this->member_id ]['actor']['subject'] = 'subject';
							$awards[ $this->member_id ]['points'][ $ruleId ] = $ruleData['points_subject'];
						}
						if ( $ruleData['badge_subject'] and !\in_array( $ruleData['badge_subject'], $this->badgeIds() ) )
						{
							$awards[ $this->member_id ]['actor']['subject'] = 'subject';
							$awards[ $this->member_id ]['badges'][ $ruleId ] = $ruleData['badge_subject'];
						}
					}

					if ( $ruleData['points_other'] or $ruleData['badge_other'] )
					{
						foreach ( $extensionObject->awardOther( $extra, $ruleData['filters'] ) as $member )
						{
							/* If the "other" member is also the subject member (e.g. member is replying to their own topic), we
								only give them the "subject" points */
							if ( $member->member_id == $this->member_id )
							{
								continue;
							}

							if ( array_key_exists( $ruleId, $milestoneHitWithRules ) )
							{
								$milestoneHitWithRules[ $ruleId ][ 'other-' . $member->member_id ] = 'other-' . $member->member_id;
							}

							/* Otherwise carry on as usual... */
							if ( !isset( $awards[ $member->member_id ] ) )
							{
								$awards[ $member->member_id ] = [ 'points' => [], 'badges' => [], 'actor' => [] ]; // So given the above comment, why is actor an array? In case we change our minds.
							}
							if ( $ruleData['points_other'] )
							{
								$awards[ $member->member_id ]['actor']['other'] = 'other';
								$awards[ $member->member_id ]['points'][ $ruleId ] = $ruleData['points_other'];
							}
							if ( $ruleData['badge_other'] and !\in_array( $ruleData['badge_other'], $member->badgeIds() ) )
							{
								$awards[ $member->member_id ]['actor']['other'] = 'other';
								$awards[ $member->member_id ]['badges'][ $ruleId ] = $ruleData['badge_other'];
							}
						}
					}
				}
			}

			/* If we have stuff to dish out... */
			if ( $awards )
			{
				$alreadyHitMilestone = [];

				/* Insert these milestone maps */
				foreach( $milestoneHitWithRules as $ruleId => $members )
				{
					foreach( $members as $data )
					{
						[ $type, $memberId ] = explode( '-', $data );
						$insertId = \IPS\Db::i()->insert( 'core_achievements_log_milestones', [
							'milestone_member_id' => $memberId,
							'milestone_rule'      => $ruleId,
							'milestone_log_id'    => $logId,
							'milestone_type'	  => $type,
						], FALSE, TRUE );

						if ( ! $insertId )
						{
							$alreadyHitMilestone[ $memberId ][] = $ruleId;
						}
					}
				}

				foreach ( $awards as $memberId => $memberAwards )
				{
					if ( $memberAwards['points'] )
					{
						foreach( $memberAwards['points'] as $ruleId => $data )
						{
							if ( isset( $alreadyHitMilestone[ $memberId ] ) and \in_array( $ruleId, $alreadyHitMilestone[ $memberId ] ) )
							{
								unset( $memberAwards['points'][ $ruleId ] );
							}
						}

						if ( \array_key_exists( 'points', $memberAwards ) and \count( $memberAwards['points'] ) )
						{
							\IPS\Member::load( $memberId )->awardPoints( array_sum( $memberAwards['points'] ), $logId, array_keys( $memberAwards['points'] ), $memberAwards['actor'], 0, $date );
						}
					}
					if ( $memberAwards['badges'] )
					{
						foreach ( array_unique( $memberAwards['badges'] ) as $ruleId => $badgeId )
						{
							if ( ! isset( $alreadyHitMilestone[ $memberId ] ) or ! \in_array( $ruleId, $alreadyHitMilestone[ $memberId ] ) )
							{
								try
								{
									\IPS\Member::load( $memberId )->awardBadge( \IPS\core\Achievements\Badge::load( $badgeId ), $logId, $ruleId, $memberAwards['actor'], 0, $date );
								}
								catch ( \OutOfRangeException $e ) { }
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Award Points
	 *
	 * @param	int		$points		Number of points to award
	 * @param	int		$logId		The ID number of the log in core_achievements_log which will tell us specifically what ACTION caused this (eg what post was made)
	 * @param	array	$ruleIds	The ID number of the rules which matched that action which contributed to this number of points being awarded
	 * @param	array	$actor		If member is "subject", "other" or both
	 * @param	int		$recognize	The ID of the recognize table row (optional)
	 * @param	?\IPS\DateTime	$date		Optional DateTime object to be used as the date the achievement was earned
	 */
	public function awardPoints( int $points, int $logId, array $ruleIds, array $actor, int $recognize=0, $date=NULL )
	{
		if ( ! $this->canHaveAchievements() )
		{
			return;
		}

		/* What are they on now? */
		$oldPointsBalance = $this->achievements_points;
		$oldRank = $this->rank();
		
		/* Increase it */
		$this->achievements_points += $points;
		$newPointsBalance = $this->achievements_points;
		$newRank = $this->rank();
		if ( $oldRank and $newRank and ( $oldRank->id == $newRank->id ) )
		{
			$newRank = NULL;
		}
		\IPS\Db::i()->update( 'core_members', "achievements_points = achievements_points + " . \intval( $points ), [ 'member_id=?', $this->member_id ] );
		
		/* Log it */
		$logId = \IPS\Db::i()->insert( 'core_points_log', [
			'member'	=> $this->member_id,
			'points'	=> $points,
			'datetime'	=> ( $date ) ? $date->getTimestamp() : time(),
			'balance'	=> $newPointsBalance,
			'rules'		=> implode( ',', $ruleIds ),
			'action_log'=> $logId,
			'actor'		=> implode( ',', $actor ),
			'new_rank'	=> $newRank ? $newRank->id : NULL,
			'time_to_new_rank'	=> $newRank ? time() - $this->joined->getTimestamp() : NULL,
			'recognize' => $recognize
		], FALSE, TRUE );

		/* Did that push them over threshold for a new rank? Don't send a notification if rebuilds are in progress, and a random post made live triggers a new badge or award */
		if ( $newRank and ! \IPS\Settings::i()->achievements_rebuilding )
		{
			$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_rank', $this, [ $newRank, $this ], [ $newRank->id ] );
			$notification->recipients->attach( $this );
			$notification->send();
		}
		
		$this->save();

		if ( isset( static::$_memberRecognizedPoints[ $this->member_id ] ) )
		{
			unset( static::$_memberRecognizedPoints[ $this->member_id ] );
		}
	}
	
	/**
	 * Award Badge
	 *
	 * @param	\IPS\core\Achievements\Badge	$badge		The badge to award
	 * @param	int								$logId		The ID number of the log in core_achievements_log which will tell us specifically what ACTION caused this (eg what post was made)
	 * @param	int								$ruleId		The ID number of the rule which matched that action which caused this badge to be awarded
	 * @param	array							$actor		If member is "subject", "other" or both
	 * @param	int								$recognize	The ID of the recognize table row (optional)
	 * @param	?\IPS\DateTime					$date		Optional DateTime object to be used as the date the achievement was earned
	 */
	public function awardBadge( \IPS\core\Achievements\Badge $badge, int $logId, int $ruleId, array $actor, int $recognize=0, $date=NULL )
	{
		if ( ! $this->canHaveAchievements() )
		{
			return;
		}

		$insertId = \IPS\Db::i()->insert( 'core_member_badges', [
			'member'		=> $this->member_id,
			'badge'			=> $badge->id,
			'datetime'		=> ( $date ) ? $date->getTimestamp() : time(),
			'rule'			=> $ruleId,
			'action_log'	=> $logId,
			'actor'			=> implode( ',', $actor ),
			'recognize'     => $recognize
		], FALSE, TRUE );
		if ( !$insertId )
		{
			// Member has already earned this badge
			return;
		}

		/* Don't send a notification if rebuilds are in progress, and a random post made live triggers a new badge or award */
		if ( ! \IPS\Settings::i()->achievements_rebuilding )
		{
			$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_badge', $this, [ $this, $badge ], [ $badge->id ] );
			$notification->recipients->attach( $this );
			$notification->send();
		}

		$data = [
			'member' => $this->apiOutput(),
			'badge' => $badge->apiOutput(),
		];

		\IPS\Api\Webhook::fire( 'member_badge_awarded', $data );
		$this->save();
	}

	public static $_memberRecognizedPoints = array();
	/**
	 * Grab todays recognized points
	 *
	 * @return int
	 */
	public function todaysRecognizePoints(): int
	{
		if ( ! isset( static::$_memberRecognizedPoints[ $this->member_id ] ) )
		{
			$timezone = new \DateTimeZone( \IPS\Settings::i()->reputation_timezone );
			$today = \IPS\DateTime::create()->setTimezone( $timezone )->setTime( 0, 0 );

			$sum = \IPS\Db::i()->select( 'SUM(r_points)', 'core_member_recognize', [ 'r_member_id=? and r_added >=?', $this->member_id, $today->getTimestamp() ] )->first();

			static::$_memberRecognizedPoints[ $this->member_id ] = $sum !== NULL ? $sum : 0;
		}

		return static::$_memberRecognizedPoints[ $this->member_id ];
	}

	public static $_memberRecognizedBadges = array();
	/**
	 * Grab todays recognized points
	 *
	 * @return int
	 */
	public function todaysRecognizeBadges(): int
	{
		if ( ! isset( static::$_memberRecognizedBadges[ $this->member_id ] ) )
		{
			$timezone = new \DateTimeZone( \IPS\Settings::i()->reputation_timezone );
			$today = \IPS\DateTime::create()->setTimezone( $timezone )->setTime( 0, 0 );

			$sum = \IPS\Db::i()->select( 'COUNT(*)', 'core_member_recognize', [ 'r_member_id=? and r_badge > 0 and r_added >=?', $this->member_id, $today->getTimestamp() ] )->first();

			static::$_memberRecognizedBadges[ $this->member_id ] = $sum !== NULL ? $sum : 0;
		}

		return static::$_memberRecognizedBadges[ $this->member_id ];
	}
	
	/**
	 * @brief	Count of solutions member has given
	 */
	public $_solutionCount = NULL;
	
	/**
	 * Get count of solutions member has given
	 *
	 * @return	int
	 */
	public function solutionCount(): int
	{
		if ( $this->_solutionCount === NULL )
		{
			$this->_solutionCount = \IPS\Db::i()->select( 'COUNT(*)', 'core_solved_index', array( 'member_id=?', $this->member_id ) )->first();
		}
		return $this->_solutionCount;
	}

	/**
	 * Record this log in
	 *
	 * @return void
	 */
	public function recordLogin()
	{
		try
		{
			\IPS\Db::i()->insert( 'core_members_logins', [ 'member_id' => $this->member_id, 'member_timestamp' => time(), 'member_date' => date( 'Y-m-d', \IPS\DateTime::ts( time() )->getTimestamp() ) ] );
		}
		catch( \Exception $e ) { }
	}

	/**
	 * Webhook filters
	 *
	 * @return	array
	 */
	public function webhookFilters() : array
	{
		return [
			'completed' => $this->completed,
			'allowAdminEmails' => $this->allow_admin_emails,
		];
	}

	/**
	 * Get unread feature highlights
	 *
	 * @param	bool			$markRead	Mark returned features as read? Useful for debugging.
	 * @return	array|NULL
	 */
	public function getFeatureHighlights( bool $markRead = TRUE ): ?array
	{
		return NULL;
	}

	/**
	 * Get the member's personal information
	 *
	 * @return \IPS\Xml\SimpleXML
	 */
	public function getPiiData(): \IPS\Xml\SimpleXML
	{
		/* Init */
		$xml = \IPS\Xml\SimpleXML::create('member_export');
		$xml->addAttribute( 'created', \IPS\DateTime::ts( time() )->rfc3339() );

		/* Get the data */
		foreach( \IPS\Application::allExtensions( 'core', 'MemberExportPersonalInformation', TRUE, 'core' ) AS $key => $ext )
		{
			if ( $data = $ext->getData( $this ) and \is_array( $data ) and \count( $data ) )
			{
				$child = $xml->addChild( $key );
				foreach( $data as $k => $v )
				{
					$child->addChild( $k, $v );
				}
			}
		}
		return $xml;
	}

	/**
	 * Is there a deletion request pending?
	 *
	 * @return bool
	 */
	public function get_isDeletionPending(): bool
	{
		$where = [];
		$where[] = ['member_id=?', $this->member_id];
		$where[] = [ \IPS\Db::i()->in( 'action',[\IPS\Member\PrivacyAction::TYPE_REQUEST_DELETE, \IPS\Member\PrivacyAction::TYPE_REQUEST_DELETE_VALIDATION ] ) ];

		return !\IPS\Db::i()->select( 'count(*)', \IPS\Member\PrivacyAction::$databaseTable, $where )->first() == 0;
	}

	/**
	 * Delete all logged IP addresses which were logged after a certain time
	 * 
	 * @param int $time
	 * @return void
	 */
	public static function pruneAllLoggedIpAddresses(int $time )
	{
		foreach ( \IPS\Content::routedClasses( FALSE, TRUE ) as $class )
		{
			try
			{
				$class::pruneIpAddresses( \IPS\Settings::i()->ip_address_prune );
			}
			catch( \Exception $ex ) { }
		}



		/* PMs and Ratings are treated extra */
		\IPS\Db::i()->update( 'core_message_posts', array( 'msg_ip_address' => '' ), array( "msg_ip_address != '' AND msg_date <= " . $time ) );
		\IPS\Db::i()->update( 'core_ratings', array( 'ip' => '' ), array( "ip != '' AND rating_date IS NOT NULL AND rating_date <= " . $time ) );

		foreach ( \IPS\Application::allExtensions( 'core', 'IpAddresses', FALSE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'pruneIpAddress'))
			{
				$extension->pruneIpAddress( $time );
			}
		}
	}

	/**
	 * Can this member request that his account gets deleted via the frontend
	 * 
	 * @return bool
	 */
	public function canUseAccountDeletion(): bool
	{
		if( \IPS\Settings::i()->right_to_be_forgotten_type != 'on' )
		{
			return FALSE;
		}

		if( \IPS\Member::loggedIn()->isAdmin() )
		{
			return FALSE;
		}
		return TRUE;
	}
}