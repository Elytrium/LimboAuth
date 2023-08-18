<?php
/**
 * @brief		ACP Notification: New Registration Requires Admin Validation
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Jul 2018
 */

namespace IPS\core\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification: New Registration Requires Admin Validation
 */
class _NewRegValidate extends \IPS\core\AdminNotification
{
	/**
	 * @brief	Identifier for what to group this notification type with on the settings form
	 */
	public static $group = 'members';
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this group compared to others
	 */
	public static $groupPriority = 3;
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this notification type compared to others in the same group
	 */
	public static $itemPriority = 2;
	
	/**
	 * Get queue HTML
	 *
	 * @return	string
	 */
	public static function queueHtml()
	{
		$users = array();
		
		foreach (
			\IPS\Db::i()->select(
				"*",
				'core_validating',
				array( 'user_verified=?', TRUE ),
				'entry_date asc',
				array( 0, 12 )
			)->join(
					'core_members',
					'core_validating.member_id=core_members.member_id'
			) as $user
		)
		{
			$users[ $user['member_id'] ] = \IPS\Member::constructFromData( $user );
		}
		
		if ( \count( $users ) )
		{
			return \IPS\Theme::i()->getTemplate( 'notifications', 'core', 'admin' )->adminValidations( $users );
		}
		else
		{
			return '';
		}
	}
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		return $member->hasAcpRestriction( 'core', 'members' );
	}
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_NewRegValidate';
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
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function title()
	{
		$others = \IPS\Db::i()->select( 'COUNT(*)', 'core_validating', array( 'user_verified=?', TRUE ) )->first();
		$names = array();
		foreach (
			\IPS\Db::i()->select(
				"*",
				'core_validating',
				array( 'user_verified=?', TRUE ),
				'entry_date asc',
				array( 0, 2 )
			)->join(
					'core_members',
					'core_validating.member_id=core_members.member_id'
			) as $user
		)
		{
			$names[ $user['member_id'] ] = htmlentities( $user['name'], ENT_DISALLOWED, 'UTF-8', FALSE );
			$others--;
		}
		if ( $others )
		{
			$names[] = \IPS\Member::loggedIn()->language()->addToStack( 'and_x_others', FALSE, array( 'pluralize' => array( $others ) ) );
		}
		
		return \IPS\Member::loggedIn()->language()->addToStack( 'new_users_need_admin_validation', FALSE, array( 'pluralize' => array( \count( $names ) ), 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $names ) ) ) );
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
			return \IPS\DateTime::ts( \IPS\Db::i()->select( 'entry_date', 'core_validating', array( 'user_verified=?', TRUE ), 'entry_date asc', 1 )->first() )->relative();
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
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('admin_dashboard.js', 'core') );
		
		return static::queueHtml();
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
		return static::STYLE_WARNING;
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	bool
	 */
	public function link()
	{
		return \IPS\Http\Url::internal( 'app=core&module=members&controller=members&filter=members_filter_validating' );
	}
}