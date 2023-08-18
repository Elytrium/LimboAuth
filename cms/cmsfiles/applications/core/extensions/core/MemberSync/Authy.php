<?php
/**
 * @brief		Member Sync
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		28 Mar 2017
 */

namespace IPS\core\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Sync
 */
class _Authy
{
	/**
	 * Member is flagged as spammer
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onSetAsSpammer( $member )
	{
		if ( isset( $member->mfa_details['authy']['id'] ) )
		{
			try
			{
				$response = \IPS\MFA\Authy\Handler::totp( "users/{$member->mfa_details['authy']['id']}/register_activity", 'post', array(
					'type'		=> 'banned',
					'user_ip'	=> \IPS\Request::i()->ipAddress()
				) );
			}
			catch ( \Exception $e ) {}
		}
	}
	
	/**
	 * Member is unflagged as spammer
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onUnSetAsSpammer( $member )
	{
		if ( isset( $member->mfa_details['authy']['id'] ) )
		{
			try
			{
				$response = \IPS\MFA\Authy\Handler::totp( "users/{$member->mfa_details['authy']['id']}/register_activity", 'post', array(
					'type'		=> 'unbanned',
					'user_ip'	=> \IPS\Request::i()->ipAddress()
				) );
			}
			catch ( \Exception $e ) {}
		}
	}
	
	/**
	 * Member is deleted
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onDelete( $member )
	{
		if ( isset( $member->mfa_details['authy']['id'] ) )
		{
			try
			{
				$response = \IPS\MFA\Authy\Handler::totp( "users/{$member->mfa_details['authy']['id']}/delete", 'post', array(
					'user_ip'	=> \IPS\Request::i()->ipAddress()
				) );
			}
			catch ( \Exception $e ) {}
		}
	}

	/**
	 * Password is changed
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param 	string		$new		New password, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	void
	 */
	public function onPassChange( $member, $new )
	{
		if ( isset( $member->mfa_details['authy']['id'] ) )
		{
			try
			{
				$response = \IPS\MFA\Authy\Handler::totp( "users/{$member->mfa_details['authy']['id']}/register_activity", 'post', array(
					'type'		=> 'password_reset',
					'user_ip'	=> \IPS\Request::i()->ipAddress()
				) );
			}
			catch ( \Exception $e ) {}
		}
	}
}