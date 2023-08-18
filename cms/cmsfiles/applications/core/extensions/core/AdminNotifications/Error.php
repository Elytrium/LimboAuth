<?php
/**
 * @brief		ACP Notification: User Error
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 Jul 2018
 */

namespace IPS\core\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification: User Error
 */
class _Error extends \IPS\core\AdminNotification
{	
	/**
	 * @brief	Identifier for what to group this notification type with on the settings form
	 */
	public static $group = 'system';
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this group compared to others
	 */
	public static $groupPriority = 2;
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this notification type compared to others in the same group
	 */
	public static $itemPriority = 4;
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_Error';
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
	 * The default value for if this notification shows in the notification center
	 *
	 * @return	bool
	 */
	public static function defaultValue()
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
		return TRUE;
	}
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		return \IPS\Settings::i()->error_log_level and $member->hasAcpRestriction( 'core', 'support', 'system_logs_view' );
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
		return new \IPS\Helpers\Form\Select( $key, $value, FALSE, array( 'options' => array(
			0	=> 'no',
			1	=> 'level_number_1_full',
			2	=> 'level_number_2_full',
			3	=> 'level_number_3_full',
			4	=> 'level_number_4_full',
			5	=> 'level_number_5_full'
		), 'class' => 'ipsField_medium' ) );
	}
				
	/**
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'error_logs_notification' );
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
			$latest = \IPS\Db::i()->select( '*', 'core_error_logs', NULL, 'log_id DESC', 1 )->first();
			$othersToday = ( $latest['log_date'] > ( time() - 86400 ) ) ? ( \IPS\Db::i()->select( 'COUNT(*)', 'core_error_logs', 'log_date>' . ( time() - 86400 ) )->first() - 1 ) : 0;
			
			if ( $othersToday )
			{
				$othersToday = \IPS\Member::loggedIn()->language()->addToStack( 'error_logs_notification_subtitle_others', FALSE, array( 'pluralize' => array( $othersToday ) ) );
			}
			else
			{
				$othersToday = '';
			}
			
			return \IPS\Member::loggedIn()->language()->addToStack( 'error_logs_notification_subtitle', FALSE, array( 'sprintf' => array( $latest['log_error_code'], $latest['log_error'], \IPS\Member::load( $latest['log_member'] )->name, \IPS\DateTime::ts( $latest['log_date'] )->relative(), $othersToday ) ) );
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
		$table = \IPS\core\modules\admin\support\errorLogs::table( \IPS\Http\Url::internal('app=core&module=overview&controller=notifications&_table=core_Error') );
		$table->limit = 10;
		$table->quickSearch = NULL;
		$table->advancedSearch = NULL;
		
		return \IPS\Theme::i()->getTemplate( 'notifications', 'core' )->errorLog( $table );
	}
	
	/**
	 * Severity
	 *
	 * @return	string
	 */
	public function severity()
	{
		return static::SEVERITY_OPTIONAL;
	}
	
	/**
	 * Style
	 *
	 * @return	bool
	 */
	public function style()
	{
		return static::STYLE_WARNING;
	}
	
	/**
	 * Dismissible?
	 *
	 * @return	string
	 */
	public function dismissible()
	{
		return static::DISMISSIBLE_PERMANENT;
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	bool
	 */
	public function link()
	{
		return \IPS\Http\Url::internal('app=core&module=support&controller=errorLogs');
	}
	
	/**
	 * WHERE clause to use against core_acp_notifications_preferences for fetching members to email
	 *
	 * @param	mixed		$extraForEmail		Any additional information specific to this instance which is used for the email but not saved
	 * @return	bool
	 */
	public function emailWhereClause( $extraForEmail )
	{
		return array( 'email!=0 AND email<=?', \intval( \substr( $extraForEmail[0], 0, 1 ) ) );
	}
}