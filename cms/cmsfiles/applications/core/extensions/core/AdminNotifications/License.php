<?php
/**
 * @brief		ACP Notification: License will/has expired
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 Jun 2018
 */

namespace IPS\core\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification: License will/has expired
 */
class _License extends \IPS\core\AdminNotification
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
	public static $itemPriority = 3;
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_License';
	}
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		return $member->hasAcpRestriction( 'core', 'settings', 'licensekey_manage' );
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
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function title()
	{
		switch ( $this->extra )
		{
			case 'missing':
			case 'url':
				return \IPS\Member::loggedIn()->language()->addToStack('license_error');
			case 'expireSoon':
				$licenseKeyData = \IPS\IPS::licenseKey();
				return \IPS\Member::loggedIn()->language()->addToStack( 'license_renewal_soon', FALSE, array( 'pluralize' => array( \intval( \IPS\DateTime::create()->diff( \IPS\DateTime::ts( strtotime( $licenseKeyData['expires'] ), TRUE ) )->format('%r%a') ) ) ) );
			case 'expired':
				return \IPS\Member::loggedIn()->language()->addToStack('license_expired');				
		}
	}
	
	/**
	 * Notification Subtitle (no HTML)
	 *
	 * @return	string
	 */
	public function subtitle()
	{
		switch ( $this->extra )
		{
			case 'missing':
			case 'url':
				return \IPS\Member::loggedIn()->language()->addToStack('license_error_subtitle');
			default:
				return \IPS\Member::loggedIn()->language()->addToStack('license_benefits_info');
		}
	}
	
	/**
	 * Notification Body (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function body()
	{
		switch ( $this->extra )
		{
			case 'missing':
				return \IPS\Member::loggedIn()->language()->addToStack('license_error_none');
			default:
				return \IPS\Theme::i()->getTemplate( 'notifications', 'core', 'admin' )->licenseKey( $this->id, $this->extra );				
		}
	}
	
	/**
	 * Severity
	 *
	 * @return	string
	 */
	public function severity()
	{
		switch ( $this->extra )
		{				
			case 'expireSoon':
				return static::SEVERITY_HIGH;
			
			default:
				return static::SEVERITY_CRITICAL;
		}
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
		switch ( $this->extra )
		{
			case 'missing':
			case 'url':
				return static::STYLE_ERROR;
			case 'expired':
				return static::STYLE_WARNING;				
			case 'expireSoon':
				return static::STYLE_EXPIRE;
		}
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	bool
	 */
	public function link()
	{
		return \IPS\Http\Url::internal( 'app=core&module=settings&controller=licensekey' );
	}
}