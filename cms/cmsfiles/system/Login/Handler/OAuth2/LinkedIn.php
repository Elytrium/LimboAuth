<?php
/**
 * @brief		LinkedIn Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 June 2017
 */

namespace IPS\Login\Handler\OAuth2;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * LinkedIn Login Handler
 */
class _LinkedIn extends \IPS\Login\Handler\OAuth2
{
	/**
	 * @brief	Does this handler support PKCE?
	 */
	public $pkceSupported = FALSE;

	/**
	 * Get title
	 *
	 * @return	string
	 */
	public static function getTitle()
	{
		return 'login_handler_Linkedin';
	}
	
	/**
	 * @brief Enable AdminCP logins by default
	 */
	protected static $enableAcpLoginByDefault = FALSE;
	
	/**
	 * ACP Settings Form
	 *
	 * @return	array	List of settings to save - settings will be stored to core_login_methods.login_settings DB field
	 * @code
	 	return array( 'savekey'	=> new \IPS\Helpers\Form\[Type]( ... ), ... );
	 * @endcode
	 */
	public function acpForm()
	{
		\IPS\Member::loggedIn()->language()->words['login_acp_desc'] = \IPS\Member::loggedIn()->language()->addToStack('login_acp_cannot_reauth');
		\IPS\Member::loggedIn()->language()->words['oauth_client_id'] = \IPS\Member::loggedIn()->language()->addToStack('login_linkedin_key');

		return array_merge( array(
			'real_name'	=> new \IPS\Helpers\Form\Radio( 'login_real_name', isset( $this->settings['real_name'] ) ? $this->settings['real_name'] : 1, FALSE, array(
				'options' => array(
					1			=> 'login_real_name_linkedin',
					0			=> 'login_real_name_disabled',
				),
				'toggles' => array(
					1			=> array( 'login_update_name_changes_inc_optional' ),
				)
			), NULL, NULL, NULL, 'login_real_name'
		) ), parent::acpForm() );
	}

	/**
	 * Get the button color
	 *
	 * @return	string
	 */
	public function buttonColor()
	{
		return '#007eb3';
	}
	
	/**
	 * Get the button icon
	 *
	 * @return	string
	 */
	public function buttonIcon()
	{
		return 'linkedin';
	}
	
	/**
	 * Get button text
	 *
	 * @return	string
	 */
	public function buttonText()
	{
		return 'login_linkedin';
	}

	/**
	 * Get button class
	 *
	 * @return	string
	 */
	public function buttonClass()
	{
		return 'ipsSocial_linkedin';
	}
	
	/**
	 * Get logo to display in information about logins with this method
	 * Returns NULL for methods where it is not necessary to indicate the method, e..g Standard
	 *
	 * @return	\IPS\Http\Url
	 */
	public function logoForDeviceInformation()
	{
		return \IPS\Theme::i()->resource( 'logos/login/Linkedin.png', 'core', 'interface' );
	}
	
	/**
	 * Should client credentials be sent as an "Authoriation" header, or as POST data?
	 *
	 * @return	string
	 */
	protected function _authenticationType()
	{
		return static::AUTHENTICATE_POST;
	}
	
	/**
	 * Grant Type
	 *
	 * @return	string
	 */
	protected function grantType()
	{
		return 'authorization_code';
	}
	
	/**
	 * Get scopes to request
	 *
	 * @param	array|NULL	$additional	Any additional scopes to request
	 * @return	array
	 */
	protected function scopesToRequest( $additional=NULL )
	{
		return array(
			'r_liteprofile',
			'r_emailaddress',
		);
	}
	
	/**
	 * Authorization Endpoint
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Http\Url
	 */
	protected function authorizationEndpoint( \IPS\Login $login )
	{
		return \IPS\Http\Url::external('https://www.linkedin.com/oauth/v2/authorization');
	}
	
	/**
	 * Token Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function tokenEndpoint()
	{
		return \IPS\Http\Url::external('https://www.linkedin.com/oauth/v2/accessToken');
	}
	
	/**
	 * Redirection Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function redirectionEndpoint()
	{
		if ( isset( $this->settings['legacy_redirect'] ) and $this->settings['legacy_redirect'] )
		{
			return \IPS\Http\Url::internal( 'applications/core/interface/linkedin/auth.php', 'none' );
		}
		return parent::redirectionEndpoint();
	}
	
	/**
	 * Get authenticated user's identifier (may not be a number)
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string
	 */
	protected function authenticatedUserId( $accessToken )
	{
		return $this->_userData( $accessToken )['id'];
	}
	
	/**
	 * Get authenticated user's username
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string|NULL
	 */
	protected function authenticatedUserName( $accessToken )
	{
		if ( isset( $this->settings['real_name'] ) and $this->settings['real_name'] )
		{
			return $this->_getProfileName( $accessToken );
		}
		return NULL;
	}

	/**
	 * @brief Cached email address to make sure we don't request more than once
	 */
	protected $_cachedEmail = NULL;
	
	/**
	 * Get authenticated user's email address
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string|NULL
	 */
	protected function authenticatedEmail( $accessToken )
	{
		if ( $this->_cachedEmail === NULL )
		{
			$response = \IPS\Http\Url::external( "https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))" )
				->request()
				->setHeaders( array( 'Authorization' => "Bearer {$accessToken}" ) )
				->get()
				->decodeJson();
				
			if ( isset( $response['errorCode'] ) )
			{
				throw new \IPS\Login\Exception( $response['message'], \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			$this->_cachedEmail = $response['elements'][0]['handle~']['emailAddress'];
		}
		return $this->_cachedEmail;
	}
	
	/**
	 * Get user's profile photo
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	\IPS\Http\Url|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userProfilePhoto( \IPS\Member $member )
	{
		if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
		{
			throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		if( !isset( $this->_userData( $link['token_access_token'] )['profilePicture'] ) )
		{
			return NULL;
		}

		$userData = $this->_userData( $link['token_access_token'] )['profilePicture']['displayImage~']['elements'];

		foreach( array_reverse( $userData ) as $_userData )
		{
			if ( isset( $_userData['identifiers'] ) )
			{
				foreach( $_userData['identifiers'] as $identifier )
				{
					if( isset( $identifier['identifier'] ) AND $identifier['identifier'] )
					{
						return \IPS\Http\Url::external( $identifier['identifier'] );
					}
				}
			}
		}
		return NULL;
	}
	
	/**
	 * Get user's profile name
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	string|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userProfileName( \IPS\Member $member )
	{
		if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
		{
			throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
		}

		return $this->_getProfileName( $link['token_access_token'] );

		$preferredLocale = $this->_userData( $link['token_access_token'] )['firstName']['preferredLocale']['language'] . '_' . $this->_userData( $link['token_access_token'] )['firstName']['preferredLocale']['country'];
	}

	/**
	 * Get the user's profile name
	 *
	 * @param	string	$accessToken	Access token
	 * @return	string
	 */
	protected function _getProfileName( $accessToken )
	{
		$preferredLocale = $this->_userData( $accessToken )['firstName']['preferredLocale']['language'] . '_' . $this->_userData( $accessToken )['firstName']['preferredLocale']['country'];

		return ( $this->_userData( $accessToken )['firstName']['localized'][ $preferredLocale ] . ' ' . $this->_userData( $accessToken )['lastName']['localized'][ $preferredLocale ] );
	}
	
	/**
	 * Get link to user's remote profile
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$identifier	The ID Number/string from remote service
	 * @param	string	$username	The username from remote service
	 * @return	\IPS\Http\Url|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 * @note	You have to apply to LinkedIn's Partnership program manually to get the profile data necessary to generate the public profile URL (specifically, the vanityName field). As such, we can't generate the URL to your profile beginning with the v2 API.
	 */
	public function userLink( $identifier, $username )
	{		
		return NULL;
	}
		
	/**
	 * Syncing Options
	 *
	 * @param	\IPS\Member	$member			The member we're asking for (can be used to not show certain options iof the user didn't grant those scopes)
	 * @param	bool		$defaultOnly	If TRUE, only returns which options should be enabled by default for a new account
	 * @return	array
	 */
	public function syncOptions( \IPS\Member $member, $defaultOnly = FALSE )
	{
		$return = array();
		
		if ( !isset( $this->settings['update_email_changes'] ) or $this->settings['update_email_changes'] === 'optional' )
		{
			$return[] = 'email';
		}
		
		if ( isset( $this->settings['update_name_changes'] ) and $this->settings['update_name_changes'] === 'optional' and isset( $this->settings['real_name'] ) and $this->settings['real_name'] )
		{
			$return[] = 'name';
		}
		
		$return[] = 'photo';

		
		return $return;
	}
	
	/**
	 * @brief	Cached user data
	 */
	protected $_cachedUserData = array();
	
	/**
	 * Get user data
	 *
	 * @param	string	$accessToken	Access Token
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	protected function _userData( $accessToken )
	{
		if ( !isset( $this->_cachedUserData[ $accessToken ] ) )
		{
			$response = \IPS\Http\Url::external( "https://api.linkedin.com/v2/me" )
				->setQueryString( 'projection', '(id,firstName,lastName,vanityName,profilePicture(displayImage~:playableStreams))' )
				->request()
				->setHeaders( array( 'Authorization' => "Bearer {$accessToken}" ) )
				->get()
				->decodeJson();

			if ( isset( $response['errorCode'] ) )
			{
				throw new \IPS\Login\Exception( $response['message'], \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			$this->_cachedUserData[ $accessToken ] = $response;
		}
		return $this->_cachedUserData[ $accessToken ];
	}
}