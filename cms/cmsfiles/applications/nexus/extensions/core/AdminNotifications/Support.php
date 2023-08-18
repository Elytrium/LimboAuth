<?php
/**
 * @brief		ACP Notification: Open Support Requests
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Jul 2018
 */

namespace IPS\nexus\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification: Open Support Requests
 */
class _Support extends \IPS\core\AdminNotification
{
	/**
	 * @brief	Identifier for what to group this notification type with on the settings form
	 */
	public static $group = 'commerce';
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this group compared to others
	 */
	public static $groupPriority = 4;
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this notification type compared to others in the same group
	 */
	public static $itemPriority = 1;
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_Support';
	}
	
	/**
	 * Is this type of notification ever optional (controls if it will be selectable as "viewable" in settings)
	 *
	 * @return	string
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
	 * @brief	Current count (that this admin can see)
	 */
	protected $count = NULL;
	
	/**
	 * Get count
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function count( \IPS\Member $member = NULL )
	{
		if ( $this->count === NULL )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$where = array();
			
			$openStatuses = array();
			foreach ( \IPS\nexus\Support\Status::roots() as $status )
			{
				if ( $status->open )
				{
					$openStatuses[] = $status->_id;
				}
			}
			$where[] = array( \IPS\Db::i()->in( 'r_status', $openStatuses ) );
			
			$departments = array_keys( iterator_to_array( \IPS\nexus\Support\Department::departmentsWithPermission( $member ) ) );
			if ( $departments )
			{
				$where[] = array( \IPS\Db::i()->in( 'r_department', $departments ) );
			}
			
			$this->count = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_requests', $where )->first();
		}
		return $this->count;
	}
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		return $member->hasAcpRestriction( 'nexus', 'support', 'requests_manage' ) and \count( \IPS\nexus\Support\Department::departmentsWithPermission( $member ) );
	}
	
	/**
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function title()
	{		
		return \IPS\Member::loggedIn()->language()->addToStack( 'acpNotification_nexusSupport', FALSE, array( 'pluralize' => array( $this->count() ) ) );
	}
	
	/**
	 * Notification Subtitle (no HTML)
	 *
	 * @return	string
	 */
	public function subtitle()
	{
		try
		{
			$where = array();
			
			$openStatuses = array();
			foreach ( \IPS\nexus\Support\Status::roots() as $status )
			{
				if ( $status->open )
				{
					$openStatuses[] = $status->_id;
				}
			}
			$where[] = array( \IPS\Db::i()->in( 'r_status', $openStatuses ) );
			
			$departments = array_keys( iterator_to_array( \IPS\nexus\Support\Department::departmentsWithPermission( \IPS\Member::loggedIn() ) ) );
			if ( $departments )
			{
				$where[] = array( \IPS\Db::i()->in( 'r_department', $departments ) );
			}
						
			return \IPS\DateTime::ts( \IPS\Db::i()->select( 'r_started', 'nexus_support_requests', $where, 'r_started asc', 1 )->first() )->relative();
		}
		catch ( \UnderflowException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Notification Body (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function body()
	{
		$sortBy = ( isset( \IPS\Request::i()->cookie['support_sort'] ) and \in_array( \IPS\Request::i()->cookie['support_sort'], array( 'r_started', 'r_last_new_reply', 'r_last_reply', 'r_last_staff_reply' ) ) ) ? \IPS\Request::i()->cookie['support_sort'] : 'r_last_new_reply';
		$sortDir = ( isset( \IPS\Request::i()->cookie['support_order'] ) and \in_array( \IPS\Request::i()->cookie['support_order'], array( 'ASC', 'DESC' ) ) ) ? \IPS\Request::i()->cookie['support_order'] : 'ASC';
				
		$i = 0;
		$results = array();
		$allIds = array();
		foreach ( \IPS\nexus\Support\Stream::myStreams( \IPS\Member::loggedIn() ) as $stream )
		{
			$results[ $stream->id ] = $stream->results( \IPS\Member::loggedIn(), "{$sortBy} {$sortDir}", 1, 3 );
			$allIds += array_keys( iterator_to_array( $results[ $stream->id ] ) );
						
			$i++;
			if ( $i >= 3 )
			{
				break;
			}
		}
		
		$tracked = iterator_to_array( \IPS\Db::i()->select( array( 'request_id', 'notify' ), 'nexus_support_tracker', array( array( 'member_id=?', \IPS\Member::loggedIn()->member_id ), array( \IPS\Db::i()->in( 'request_id', $allIds ) ) ) )->setKeyField( 'request_id' )->setValueField( 'notify' ) );
		$participatedIn = iterator_to_array( \IPS\Db::i()->select( 'DISTINCT reply_request', 'nexus_support_replies', array( array( 'reply_member=?', \IPS\Member::loggedIn()->member_id ), array( \IPS\Db::i()->in( 'reply_request', $allIds ) ) ) ) );
		
		$resultsHtml = array();
		foreach ( $results as $streamId => $resultSet )
		{
			$resultsHtml[ $streamId ] = \IPS\Theme::i()->getTemplate( 'support', 'nexus' )->requestsTableResults( $resultSet, NULL, FALSE, $tracked, $participatedIn, FALSE );
		}
				
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'support.css', 'nexus', 'admin' ) );
		return \IPS\Theme::i()->getTemplate( 'notifications', 'nexus' )->support( $resultsHtml );
	}
	
	/**
	 * Severity
	 *
	 * @return	string
	 */
	public function severity()
	{
		return static::SEVERITY_DYNAMIC;
	}
	
	/**
	 * Dismissible?
	 *
	 * @return	string
	 */
	public function dismissible()
	{	
		return static::DISMISSIBLE_NO;
	}
	
	/**
	 * Style
	 *
	 * @return	bool
	 */
	public function style()
	{
		return static::STYLE_INFORMATION;
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	bool
	 */
	public function link()
	{
		return \IPS\Http\Url::internal('app=nexus&module=support&controller=requests');
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
		return (bool) $this->count( $member );
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
		return new \IPS\Helpers\Form\Custom( $key, NULL, FALSE, array( 'getHtml' => function() {
			return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( \IPS\Http\Url::internal('app=nexus&module=support&controller=requests&do=preferences'), TRUE, \IPS\Member::loggedIn()->language()->addToStack('acp_notifications_email_configure') );
		} ) );
	}
}