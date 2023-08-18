<?php
/**
 * @brief		Notification Options: Bulk Mails
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 May 2019
 */

namespace IPS\core\extensions\core\Notifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Notification Options: Bulk Mails
 */
class _BulkMails
{
	/**
	 * Get fields for configuration
	 *
	 * @param	\IPS\Member|null	$member		The member (to take out any notification types a given member will never see) or NULL if this is for the ACP
	 * @return	array
	 */
	public static function configurationOptions( \IPS\Member $member = NULL ): array
	{
		return array(
			'allow_admin_mails'	=> array(
				'type'				=> 'standard',
				'notificationTypes'	=> array(),
				'title'				=> 'notifications__core_BulkMails',
				'showTitle'			=> FALSE,
				'description'		=> 'notifications__core_BulkMails_desc',
				'default'			=> array(),
				'disabled'			=> array( 'inline', 'push', 'email' ),
				'extra'				=> array(
					'newsletter'		=> array(
						'title'				=> 'member_notifications_email',
						'icon'				=> 'envelope-o',
						'value'				=> $member ? ( $member->allow_admin_mails ) : NULL,
						'adminCanSetDefault'=> TRUE,
						'default'			=> ( \IPS\Settings::i()->updates_consent_default === 'enabled' ),
						'admin_lang'		=> array(
							'title'		=> 'updates_consent_default',
							'desc'		=> 'updates_consent_default_desc',
							'default'	=> 'updates_consent_enabled',
							'optional'	=> 'updates_consent_disabled'
						),
					)
				)
			),
		);
	}
	
	/**
	 * Save "extra" value
	 *
	 * @param	\IPS\Member|NULL	$member	The member or NULL if this is the admin setting defaults
	 * @param	string				$key	The key
	 * @param	bool				$value	The value
	 * @return	void
	 */
	public static function saveExtra( ?\IPS\Member $member, $key, $value )
	{
		switch ( $key )
		{
			case 'newsletter':
				if ( $member )
				{
					if ( $member->allow_admin_mails != $value )
					{
						$member->allow_admin_mails = $value;
						$member->logHistory( 'core', 'admin_mails', array( 'enabled' => $value ) );
					}
				}
				else
				{
					\IPS\Settings::i()->changeValues( array( 'updates_consent_default' => $value ? 'enabled' : 'disabled' ) );
				}
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
		if ( $method === 'email' and $member->allow_admin_mails )
		{
			$member->allow_admin_mails = false;
			$member->save();
			$member->logHistory( 'core', 'admin_mails', array( 'enabled' => false ) );
		}
	}
	
	/**
	 * Reset "extra" value to the default for all accounts
	 *
	 * @return	void
	 */
	public static function resetExtra()
	{
		// Deliberately does nothing. There is no way to mass update the bulk mail preferences
	}
}