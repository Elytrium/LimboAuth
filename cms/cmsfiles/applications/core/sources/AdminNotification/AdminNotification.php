<?php
/**
 * @brief		Admin Notification
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Block
 */
abstract class _AdminNotification extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Dynamic severity
	 * @note	Uses more resources than the other types. Use only when the notification may show to some admins but not others
	 */
	const SEVERITY_DYNAMIC = 'dynamic';

	/**
	 * @brief	Optional severity
	 */
	const SEVERITY_OPTIONAL = 'optional';

	/**
	 * @brief	Normal severity
	 */
	const SEVERITY_NORMAL = 'normal';

	/**
	 * @brief	High severity
	 */
	const SEVERITY_HIGH = 'high';

	/**
	 * @brief	Critical severity
	 */
	const SEVERITY_CRITICAL = 'critical';

	/**
	 * @brief	Not dismissable
	 */
	const DISMISSIBLE_NO = 'no';

	/**
	 * @brief	Temporarily dismissable
	 */
	const DISMISSIBLE_TEMPORARY = 'temp';
	
	/**
	 * @brief	Dismissible until it recurs
	 */
	const DISMISSIBLE_UNTIL_RECUR = 'recur';

	/**
	 * @brief	Fully dismissable
	 */
	const DISMISSIBLE_PERMANENT = 'perm';
	
	/**
	 * @brief	Styling: error
	 */
	const STYLE_ERROR = 'error';

	/**
	 * @brief	Styling: warning
	 */
	const STYLE_WARNING = 'warning';

	/**
	 * @brief	Styling: information
	 */
	const STYLE_INFORMATION = 'information';

	/**
	 * @brief	Styling: expiring
	 */
	const STYLE_EXPIRE = 'expire';
		
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons = array();
	
	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'acpNotifications', 'acpNotificationIds' );
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_acp_notifications';
	
	/**
	 * Get Number of Notifications
	 *
	 * @param	\IPS\Member|NULL	$member		The member viewing, or NULL for currently logged in
	 * @param	array				$severities	The severities
	 * @return	int
	 */
	public static function notificationCount( \IPS\Member $member = NULL, $severities = array( 'dynamic', 'optional', 'normal', 'high', 'critical' ) )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$notificationIds = static::notificationIdsForMember( $member );
				
		$return = 0;
		foreach ( $severities as $s )
		{
			if ( $s === 'dynamic' )
			{
				foreach ( $notificationIds['dynamic'] as $i )
				{
					$notification = static::load( $i );
					if ( $notification->dynamicShow( $member ) )
					{
						$return++;
					}
				}
			}
			else
			{
				$return += \count( $notificationIds[ $s ] );
			}
		}
						
		return $return;
	}
	
	/**
	 * Get Notifications that a particular member can see by severity
	 *
	 * @param	\IPS\Member|NULL	$member		The member viewing, or NULL for currently logged in
	 * @param	array				$severities	The severities
	 * @return	int
	 */
	public static function notifications( \IPS\Member $member = NULL, $severities = array( 'dynamic', 'optional', 'normal', 'high', 'critical' ) )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$notificationIds = static::notificationIdsForMember( $member );
		
		$return = array();
		foreach ( static::allNotifications() as $notification )
		{
			$severity = $notification->severity();
			if ( \in_array( $severity, $severities ) and \in_array( $notification->id, $notificationIds[ $severity ] ) and ( $severity !== static::SEVERITY_DYNAMIC or $notification->dynamicShow( $member ) ) )
			{
				/* Check if this notification should dismiss itself first */
				if ( $notification->selfDismiss() )
				{
					$notification->delete();
					continue;
				}

				$return[ $notification->id ] = $notification;
			}
		}
		
		return $return;
	}
	
	/**
	 * Get Cached IDs of Notifications that a particular member can see by severity
	 *
	 * @param	\IPS\Member|NULL	$member		The member
	 * @return	int
	 */
	protected static function notificationIdsForMember( \IPS\Member $member )
	{
		if ( !isset( \IPS\Data\Store::i()->acpNotificationIds ) or !isset( \IPS\Data\Store::i()->acpNotificationIds[ $member->member_id ] ) )
		{
			/* Init */
			$data = isset( \IPS\Data\Store::i()->acpNotificationIds ) ? \IPS\Data\Store::i()->acpNotificationIds : array();
			$data[ $member->member_id ] = array( static::SEVERITY_DYNAMIC => array(), static::SEVERITY_OPTIONAL => array(), static::SEVERITY_NORMAL => array(), static::SEVERITY_HIGH => array(), static::SEVERITY_CRITICAL => array() );
			
			/* Get our preferences */
			$preferences = iterator_to_array( \IPS\Db::i()->select( '*', 'core_acp_notifications_preferences', array( '`member`=?', $member->member_id ) )->setKeyField('type') );
			
			/* Get our dismissals */
			$dismissals = array();
			foreach ( \IPS\Db::i()->select( '*', 'core_acp_notifcations_dismissals', array( '`member`=?', $member->member_id ) ) as $row )
			{
				$dismissals[ $row['notification'] ] = $row['time'];
			}
			
			/* Loop through them */
			foreach ( static::allNotifications( $member ) as $notification )
			{
				/* Check we want to see it if it's optional */
				$exploded = explode( '\\', \get_class( $notification ) );
				$key = "{$exploded[1]}_{$exploded[5]}";
				$view = isset( $preferences[ $key ] ) ? $preferences[ $key ]['view'] : $notification::defaultValue();
				if ( !$view )
				{
					continue;
				}
				
				/* Check we haven't dismissed it */
				if ( isset( $dismissals[ $notification->id ] ) )
				{
					if ( $notification->dismissible() === static::DISMISSIBLE_TEMPORARY and $dismissals[ $notification->id ] < ( time() - 86400 ) )
					{
						\IPS\Db::i()->delete( 'core_acp_notifcations_dismissals', array( 'notification=? AND `member`=?', $notification->id, $member->member_id ) );
					}
					elseif ( $notification->dismissible() === static::DISMISSIBLE_UNTIL_RECUR and $dismissals[ $notification->id ] < $notification->sent->getTimestamp() )
					{
						// Nothing, we're showing it
					}
					else
					{
						continue;
					}
				}
				
				/* Check we can see it */
				if ( $notification->visibleTo( $member ) )
				{
					$data[ $member->member_id ][ $notification->severity() ][ $notification->id ] = $notification->id;
				}
			}
			
			\IPS\Data\Store::i()->acpNotificationIds = $data;
		}
		
		return \IPS\Data\Store::i()->acpNotificationIds[ $member->member_id ];
	}
	
	/**
	 * Get Notifications
	 *
	 * @return	array
	 */
	protected static function allNotifications()
	{
		if ( !isset( \IPS\Data\Store::i()->acpNotifications ) )
		{
			\IPS\Data\Store::i()->acpNotifications = iterator_to_array( \IPS\Db::i()->select( '*', 'core_acp_notifications', NULL, 'sent DESC' ) );
		}		
				
		$notifications = array();
		foreach ( \IPS\Data\Store::i()->acpNotifications as $notification )
		{
			if ( \IPS\Application::appIsEnabled( $notification['app'] ) )
			{
				$notificationObject = \IPS\core\AdminNotification::constructFromData( $notification );

				if( $notificationObject )
				{
					$notifications[ $notificationObject->id ] = $notificationObject;
				}
				else
				{
					/* Remove orphan entry */
					\IPS\Db::i()->delete( 'core_acp_notifications', array( 'id=?', $notification['id'] ) );
				}
			}
		}
		return $notifications;
	}
	
	/**
	 * Find Existing Notification
	 *
	 * @param	string		$app		Application key
	 * @param	string		$extension	Extension key
	 * @param	string|null	$extra		Any additional information
	 * @return	void
	 */
	public static function find( $app, $extension, $extra = NULL )
	{
		foreach ( static::allNotifications() as $notification )
		{
			if ( $notification->app === $app and $notification->ext === $extension and $notification->extra === $extra )
			{
				return $notification;
			}
		}
		return NULL;
	}
	
	/**
	 * Send Notification
	 *
	 * @param	string				$app				Application key
	 * @param	string				$extension			Extension key
	 * @param	string|null			$extra				Any additional information which persists if the notification is resent
	 * @param	bool|null			$resend				If an existing notification exists, it will be bumped / resent
	 * @param	mixed				$extraForEmail		Any additional information specific to this instance which is used for the email but not saved
	 * @param	bool|\IPS\Member	$bypassEmail		If TRUE, no email will be sent, regardless of admin preferences - or if a member object, that admin will be skipped. Should only be used if the action is initiated by an admin making an email unnecessary 
	 * @return	void
	 */
	public static function send( $app, $extension, $extra = NULL, $resend = TRUE, $extraForEmail = NULL, $bypassEmail = FALSE )
	{
		/* Create or update */
		if ( $notification = static::find( $app, $extension, $extra ) )
		{
			if ( !$resend )
			{
				return;
			}
			$notification->sent = time();
		}
		else
		{
			$classname = 'IPS\\' . $app . '\extensions\core\AdminNotifications\\' . mb_ucfirst( $extension );
			$notification = new $classname;
			$notification->app = $app;
			$notification->ext = $extension;
			$notification->extra = $extra;

			unset( \IPS\Data\Store::i()->acpNotifications );
		}		
		
		/* Is this a new notification? */
		if ( !$notification->_new and !$resend )
		{
			return;
		}
				
		/* Get where clause for email notifications */		
		$exploded = explode( '\\', \get_class( $notification ) );
		$key = "{$exploded[1]}_{$exploded[5]}";
		$where = array( array( 'type=?', $key ) );
		$where[] = $notification->emailWhereClause( $extraForEmail );
			
		/* Save */
		$notification->save();
		
		/* Email */
		if ( $bypassEmail !== TRUE )
		{
			/* Work out if we need to email this to anyone */
			$emailRecipients = array();
			foreach ( \IPS\Db::i()->select( '`member`', 'core_acp_notifications_preferences', $where ) as $memberId )
			{
				$member = \IPS\Member::load( $memberId );
				if ( $member->member_id and $notification->visibleTo( $member ) )
				{
					if ( !( $bypassEmail instanceof \IPS\Member ) or $bypassEmail->member_id !== $member->member_id )
					{
						$emailRecipients[] = $member;
					}
				}
			}
											
			/* And if we do, do it */
			if ( \count( $emailRecipients ) )
			{
				$email = \IPS\Email::buildFromTemplate( $exploded[1], 'acp_notification_' . $exploded[5], array( $notification, $extraForEmail ), \IPS\Email::TYPE_TRANSACTIONAL );
				$email->setUnsubscribe( 'core', 'unsubscribeAcpNotification', array( \get_class( $notification ) ) );
				foreach ( $emailRecipients as $member )
				{
					$email->send( $member );
				}
			}
		}
	}
	
	/**
	 * Delete Notification
	 *
	 * @param	string				$app		Application key
	 * @param	string				$extension	Extension key
	 * @param	string|null			$extra		Any additional information
	 * @param	\IPS\DateTime|null	$newTime		If provided, rather than deleting the notification, it will modify it's sent time to the specified time
	 * @return	void
	 */
	public static function remove( $app, $extension, $extra = NULL, \IPS\DateTime $newTime = NULL )
	{
		if ( $notification = static::find( $app, $extension, $extra ) )
		{
			if ( $newTime )
			{
				$notification->sent = $newTime->getTimestamp();
				$notification->save();
			}
			else
			{
				$notification->delete();
			}
		}
	}
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static|false
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE ): static|false
	{
		$classname = 'IPS\\' . $data['app'] . '\extensions\core\AdminNotifications\\' . mb_ucfirst( $data['ext'] );

		if( !class_exists( $classname ) )
		{
			return false;
		}

		/* Initiate an object */
		$obj = new $classname;
		$obj->_new = FALSE;
		
		/* Import data */
		$databasePrefixLength = \strlen( static::$databasePrefix );
		foreach ( $data as $k => $v )
		{
			if( static::$databasePrefix AND mb_strpos( $k, static::$databasePrefix ) === 0 )
			{
				$k = \substr( $k, $databasePrefixLength );
			}

			$obj->_data[ $k ] = $v;
		}
		$obj->changed = array();
		
		/* Init */
		if ( method_exists( $obj, 'init' ) )
		{
			$obj->init();
		}
				
		/* Return */
		return $obj;
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->sent = time();
	}
	
	/**
	 * Get sent time
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_sent()
	{
		return \IPS\DateTime::ts( $this->_data['sent'] );
	}
	
	/**
	 * @brief	Identifier for what to group this notification type with on the settings form
	 */
	public static $group = 'other';
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this group compared to others
	 */
	public static $groupPriority = 5;
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this notification type compared to others in the same group
	 */
	public static $itemPriority = 3;
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		return TRUE;
	}
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return \get_class(); // This is intended to be abstract but PHP 5.6 won't let you have abstract static functions
	}
		
	/**
	 * Can a member view this notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function visibleTo( \IPS\Member $member )
	{
		return static::permissionCheck( $member );
	}
	
	/**
	 * For dynamic notifications: should this show for this member?
	 *
	 * @note	This is checked every time the notification shows. Should be lightweight.
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function dynamicShow( \IPS\Member $member )
	{
		return FALSE;
	}
	
	/**
	 * Should this notification dismiss itself?
	 *
	 * @note	This is checked every time the notification shows. Should be lightweight.
	 * @return	bool
	 */
	public function selfDismiss()
	{
		return FALSE;
	}
	
	/**
	 * The default value for if this notification shows in the notification center
	 *
	 * @return	bool
	 */
	public static function defaultValue()
	{
		return TRUE;
	}
	
	/**
	 * Is this type of notification ever optional (controls if it will be selectable as "viewable" in settings)
	 *
	 * @return	bool
	 */
	public static function mayBeOptional()
	{
		return TRUE;
	}
	
	/**
	 * Is this type of notification might recur (controls what options will be available for the email setting)
	 *
	 * @return	bool
	 */
	public static function mayRecur()
	{
		return TRUE;
	}
	
	/**
	 * Custom per-admin setting for if email shoild be sent for this notification
	 *
	 * @param	string	$key	Setting field key
	 * @param	mixed	$value	Current value
	 * @return	\IPS\Helpers\Form\FormAbstract|NULL
	 */
	public static function customEmailConfigurationSetting( $key, $value )
	{
		return NULL;
	}
	
	/**
	 * WHERE clause to use against core_acp_notifications_preferences for fetching members to email
	 *
	 * @param	mixed		$extraForEmail		Any additional information specific to this instance which is used for the email but not saved
	 * @return	bool
	 */
	public function emailWhereClause( $extraForEmail )
	{
		if ( $this->_new )
		{
			return array( "( email='always' OR email='once' )" );
		}
		else
		{
			return array( "email='always'" );
		}
	}
		
	/**
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	abstract public function title();
	
	/**
	 * Notification Subtitle (no HTML)
	 *
	 * @return	string
	 */
	public function subtitle()
	{
		return NULL;
	}
	
	/**
	 * Notification Body (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	abstract public function body();
		
	/**
	 * Severity
	 *
	 * @return	string
	 */
	abstract public function severity();
	
	/**
	 * Dismissible?
	 *
	 * @return	bool
	 */
	abstract public function dismissible();
	
	/**
	 * Style
	 *
	 * @return	bool
	 */
	public function style()
	{
		switch ( $this->severity() )
		{
			case static::SEVERITY_CRITICAL:
				return static::STYLE_ERROR;
			case static::SEVERITY_HIGH:
				return static::STYLE_WARNING;
			default:
				return static::STYLE_INFORMATION;
		}
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	bool
	 */
	public function link()
	{
		return \IPS\Http\Url::internal( 'app=core&module=overview&controller=notifications&highlightedId=' . $this->id );
	}
		
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Db::i()->delete( 'core_acp_notifcations_dismissals', array( 'notification=?', $this->id ) );
		parent::delete();
	}


	/**
	 * Dismiss a notification for a member and rebuild the datastore
	 *
	 * @param $notificationId
	 * @param \IPS\Member|NULL $member
	 *
	 * @return void
	 */
	public static function dismissNotification( $notificationId, \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		\IPS\Db::i()->insert( 'core_acp_notifcations_dismissals', array(
			'notification'	=> $notificationId,
			'member'		=> $member->member_id,
			'time'			=> time()
		), TRUE );

		if( isset( \IPS\Data\Store::i()->acpNotificationIds ) )
		{
			$notificationCache = \IPS\Data\Store::i()->acpNotificationIds;

			if( isset( $notificationCache[ $member->member_id ] ) )
			{
				unset( $notificationCache[ $member->member_id ] );
			}
		
			\IPS\Data\Store::i()->acpNotificationIds = $notificationCache;
		}
	}
}