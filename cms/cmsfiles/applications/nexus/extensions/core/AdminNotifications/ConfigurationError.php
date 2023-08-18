<?php
/**
 * @brief		ACP Notification Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Commerce
 * @since		29 Oct 2019
 */

namespace IPS\nexus\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP  Notification Extension
 */
class _ConfigurationError extends \IPS\core\AdminNotification
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
	public static $itemPriority = 5;
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_ConfigurationError';
	}
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		return $member->hasAcpRestriction( 'nexus', 'payments', 'gateways_manage' );
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
		if( mb_substr( $this->extra, 0, 2 ) === 'pm' )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('acp_notification_nexus_config_error_paymethod');
		}
		elseif( mb_substr( $this->extra, 0, 2 ) === 'po' )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'acp_notification_nexus_config_error_payoutmethod' );
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'acpNotification_nexusBACancelErrors', FALSE, array( 'pluralize' => array( \count( json_decode( $this->extra, TRUE ) ) ) ) );
		}
	}
	
	/**
	 * Notification Subtitle (no HTML)
	 *
	 * @return	string
	 */
	public function subtitle()
	{
		if( mb_substr( $this->extra, 0, 2 ) === 'pm' )
		{
			try
			{
				$method = \IPS\nexus\Gateway::load( \substr( $this->extra, 2 ) );
				return \IPS\Member::loggedIn()->language()->addToStack( 'acp_notification_nexus_config_error_paymethod_desc', FALSE, array( 'sprintf' => array( $method->_title ) ) );
			}
			catch ( \OutOfRangeException $e ) { }
		}
		elseif( mb_substr( $this->extra, 0, 2 ) === 'po' )
		{
			$gateway = mb_substr( $this->extra, 2 );
			return \IPS\Member::loggedIn()->language()->addToStack( 'acp_notification_nexus_config_error_payoutmethod_desc', FALSE, array( 'sprintf' => array( $gateway ) ) );
		}
		else
		{
			return '';
		}
	}
	
	/**
	 * Notification Body (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function body()
	{
		if( mb_substr( $this->extra, 0, 2 ) === 'pm' )
		{
			try
			{
				$method = \IPS\nexus\Gateway::load( \substr( $this->extra, 2 ) );
				return \IPS\Theme::i()->getTemplate( 'notifications', 'nexus', 'admin' )->paymentMethodError( $method );
			}
			catch ( \OutOfRangeException $e )
			{
				return '';
			}
		}
		elseif( mb_substr( $this->extra, 0, 2 ) === 'po' )
		{
			$gateway = mb_substr( $this->extra, 2 );
			return \IPS\Theme::i()->getTemplate( 'notifications', 'nexus', 'admin' )->payoutSettingsError( $gateway );
		}
		else
		{
			return \IPS\Theme::i()->getTemplate( 'notifications', 'nexus', 'admin' )->baCancellationError( json_decode( $this->extra, TRUE ) );
		}
	}
	
	/**
	 * Severity
	 *
	 * @return	string
	 */
	public function severity()
	{
		return ( mb_substr( $this->extra, 0, 2 ) === 'pm' OR mb_substr( $this->extra, 0, 2 ) === 'po' ) ? static::SEVERITY_CRITICAL : static::SEVERITY_OPTIONAL;
	}
	
	/**
	 * Dismissible?
	 *
	 * @return	string
	 */
	public function dismissible()
	{
		return ( mb_substr( $this->extra, 0, 2 ) === 'pm' OR mb_substr( $this->extra, 0, 2 ) === 'po' ) ? static::DISMISSIBLE_NO : static::DISMISSIBLE_PERMANENT;
	}
	
	/**
	 * Should this notification dismiss itself?
	 *
	 * @note	This is checked every time the notification shows. Should be lightweight.
	 * @return	bool
	 */
	public function selfDismiss()
	{
		if( mb_substr( $this->extra, 0, 2 ) === 'pm' )
		{
			try
			{
				\IPS\nexus\Gateway::load( \substr( $this->extra, 2 ) );
				return FALSE;
			}
			catch( \OutOfRangeException $e )
			{
				return TRUE;
			}
		}
		else
		{
			return parent::selfDismiss();
		}
	}
	
	/**
	 * Style
	 *
	 * @return	bool
	 */
	public function style()
	{
		return ( mb_substr( $this->extra, 0, 2 ) === 'pm' OR mb_substr( $this->extra, 0, 2 ) === 'po' ) ? static::STYLE_ERROR : static::STYLE_WARNING;
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		if( mb_substr( $this->extra, 0, 2 ) === 'pm' )
		{
			return \IPS\Http\Url::internal( 'app=nexus&module=payments&controller=paymentsettings&tab=gateways&do=form&id=' . \substr( $this->extra, 2 ) );
		}
		elseif( mb_substr( $this->extra, 0, 2 ) === 'po' )
		{
			return \IPS\Http\Url::internal( 'app=nexus&module=payments&controller=payouts&do=settings' );
		}

		parent::link();
	}
}