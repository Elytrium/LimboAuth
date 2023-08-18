<?php
/**
 * @brief		ACP Notification Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/

 * @since		08 Mar 2023
 */

namespace IPS\core\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP  Notification Extension
 */
class _PiiDataRequest extends \IPS\core\AdminNotification
{	
	/**
	 * @brief	Identifier for what to group this notification type with on the settings form
	 */
	public static $group = 'members';
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this group compared to others
	 */
	public static $groupPriority = 2;
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this notification type compared to others in the same group
	 */
	public static $itemPriority = 1;
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle(): string
	{
		return 'acp_notification_PiiDataRequest';
	}
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member ): bool
	{
		return $member->hasAcpRestriction( 'core', 'members' );
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
	public static function mayRecur(): bool
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
		$others = \IPS\Db::i()->select( 'COUNT(*)', 'core_member_privacy_actions', [ 'action=?', \IPS\Member\PrivacyAction::TYPE_REQUEST_PII ] )->first();
		$names = [];
		foreach (
			\IPS\Db::i()->select(
				   "*",
				   'core_member_privacy_actions',
			where: [ 'action=?', \IPS\Member\PrivacyAction::TYPE_REQUEST_PII ],
			order: 'request_date asc',
			limit: [ 0, 2 ]
			)->join(
					'core_members',
					'core_member_privacy_actions.member_id=core_members.member_id'
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

		return \IPS\Member::loggedIn()->language()->addToStack( 'pii_data_request_adminnotification', FALSE, array( 'pluralize' => array( \count( $names ) ), 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $names ) ) ) );
	}

	/**
	 * Notification Body (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function body(): string
	{
		$users = [];

		$names = [];
		foreach(
		\IPS\Db::i()->select(
			'*',
			'core_member_privacy_actions',
			where: ['action=?', \IPS\Member\PrivacyAction::TYPE_REQUEST_PII],
			order: 'request_date asc',
			limit: [0, 2]
		)->join(
			'core_members',
			'core_member_privacy_actions.member_id=core_members.member_id'
		) as $user
		)
		{
			$users[ $user[ 'member_id' ] ] = \IPS\Member::constructFromData( $user );
			$users[ $user[ 'member_id' ] ]->_privacy_id = $user['id'];
		}
		
		if( \count( $users ) )
		{
			return \IPS\Theme::i()->getTemplate( 'notifications', 'core', 'admin' )->piiRequest( $users );
		}
		else
		{
			return '';
		}
	}

	/**
	 * Severity
	 *
	 * @return	string
	 */
	public function severity(): string
	{
		return static::SEVERITY_NORMAL;
	}
	
	/**
	 * Dismissible?
	 *
	 * @return	string
	 */
	public function dismissible(): string
	{
		return static::DISMISSIBLE_TEMPORARY;
	}
	
	/**
	 * Style
	 *
	 * @return	bool
	 */
	public function style(): string
	{
		return static::STYLE_INFORMATION;
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link(): \IPS\Http\Url
	{
		return \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy&filter=pii_data' );
	}
}