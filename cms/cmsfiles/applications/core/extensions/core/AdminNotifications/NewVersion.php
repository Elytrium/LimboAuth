<?php
/**
 * @brief		ACP Notification: New Version Available
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Jun 2018
 */

namespace IPS\core\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification: New Version Available
 */
class _NewVersion extends \IPS\core\AdminNotification
{	
	/**
	 * @brief	Identifier for what to group this notification type with on the settings form
	 */
	public static $group = 'important';
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this group compared to others
	 */
	public static $groupPriority = 1;
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this notification type compared to others in the same group
	 */
	public static $itemPriority = 1;

	/**
	 * @brief	Temporarily store the upgrade data
	 */
	public $_details;
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_NewVersion';
	}
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		if ( \IPS\CIC AND \IPS\IPS::isManaged() )
		{
			return FALSE;
		}
		
		return $member->hasAcpRestriction( 'core', 'overview', 'upgrade_manage' );
	}
	
	/**
	 * Is this type of notification ever optional (controls if it will be selectable as "viewable" in settings)
	 *
	 * @return	string
	 */
	public static function mayBeOptional()
	{
		return FALSE;
	}
	
	/**
	 * Is this type of notification might recur (controls what options will be available for the email setting)
	 *
	 * @return	bool
	 */
	public static function mayRecur()
	{
		return FALSE;
	}
		
	/**
	 * Is this a security update?
	 *
	 * @return	bool
	 */
	public function __construct()
	{
		$this->_details = \IPS\Application::load('core')->availableUpgrade( TRUE );
		return parent::__construct();
	}
	
	/**
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function title()
	{
		return ! empty( $this->_details['security'] ) ? \IPS\Member::loggedIn()->language()->addToStack( 'dashboard_version_info_security', FALSE, array( 'sprintf' => array( $this->_details['version'] ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'dashboard_version_info', FALSE, array( 'sprintf' => array( $this->_details['version'] ) ) );
	}
	
	/**
	 * Notification Subtitle (no HTML)
	 *
	 * @return	string
	 */
	public function subtitle()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'regular_update', FALSE, array( 'sprintf' => array( \IPS\DateTime::ts( $this->_details['released'] )->relative( \IPS\DateTime::RELATIVE_FORMAT_LOWER ) ) ) );
	}
	
	/**
	 * Notification Body (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function body()
	{
		return \IPS\Theme::i()->getTemplate( 'notifications', 'core', 'global' )->newVersion( $this->_details );
	}
	
	/**
	 * Severity
	 *
	 * @return	string
	 */
	public function severity()
	{
		return static::SEVERITY_CRITICAL;
	}
	
	/**
	 * Dismissible?
	 *
	 * @return	string
	 */
	public function dismissible()
	{
		return static::DISMISSIBLE_TEMPORARY;
	}
	
	/**
	 * Style
	 *
	 * @return	bool
	 */
	public function style()
	{
		return ! empty( $this->_details['security'] ) ? static::STYLE_ERROR : static::STYLE_INFORMATION;
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	bool
	 */
	public function link()
	{
		return \IPS\Http\Url::internal( 'app=core&module=system&controller=upgrade&_new=1', 'admin' );
	}

	/**
	 * Should this notification dismiss itself?
	 *
	 * @note	This is checked every time the notification shows. Should be lightweight.
	 * @return	bool
	 */
	public function selfDismiss()
	{
		return empty( $this->_details['version'] );
	}
}