<?php
/**
 * @brief		Google Login Handler
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
 * Google Login Handler
 */
class _Google extends \IPS\Login\Handler\OAuth2
{
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public static function getTitle()
	{
		return 'login_handler_Google';
	}
	
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
		\IPS\Member::loggedIn()->language()->words['oauth_client_id'] = \IPS\Member::loggedIn()->language()->addToStack('login_google_id');

		return array_merge(
			array(
				'real_name'	=> new \IPS\Helpers\Form\Radio( 'login_real_name', isset( $this->settings['real_name'] ) ? $this->settings['real_name'] : 1, FALSE, array(
					'options' => array(
						1			=> 'login_real_name_google',
						0			=> 'login_real_name_disabled',
					),
					'toggles' => array(
						1			=> array( 'login_update_name_changes_inc_optional' ),
					)
				), NULL, NULL, NULL, 'login_real_name' )
			),
			parent::acpForm()
		);
	}

	/**
	 * Get the button color
	 *
	 * @return	string
	 */
	public function buttonColor()
	{
		return '#4285F4';
	}
	
	/**
	 * Get the button icon
	 *
	 * @return	string
	 */
	public function buttonIcon()
	{
		return 'google';
	}
	
	/**
	 * Get button text
	 *
	 * @return	string
	 */
	public function buttonText()
	{
		return 'login_google';
	}
	
	/**
	 * Get button class
	 *
	 * @return	string
	 */
	public function buttonClass()
	{
		return 'ipsSocial_google';
	}

	/**
	 * Get logo to display in information about logins with this method
	 * Returns NULL for methods where it is not necessary to indicate the method, e..g Standard
	 *
	 * @return	\IPS\Http\Url
	 */
	public function logoForDeviceInformation()
	{
		return \IPS\Theme::i()->resource( 'logos/login/Google.png', 'core', 'interface' );
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
		$return = array(
			'profile',
			'email',
		);

		return $return;
	}
	
	/**
	 * Scopes Issued
	 *
	 * @param	string		$accessToken	Access Token
	 * @return	array|NULL
	 */
	public function scopesIssued( $accessToken )
	{
		try
		{
			$response = \IPS\Http\Url::external( "https://www.googleapis.com/oauth2/v2/tokeninfo" )
				->setQueryString( 'access_token', $accessToken )
				->request()
				->get()
				->decodeJson();
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
				
		return isset( $response['scope'] ) ? explode( ' ', $response['scope'] ) : array();
	}
	
	/**
	 * Authorization Endpoint
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Http\Url
	 */
	protected function authorizationEndpoint( \IPS\Login $login )
	{
		$return = \IPS\Http\Url::external('https://accounts.google.com/o/oauth2/v2/auth?access_type=offline');
		
		if ( $login->type === \IPS\Login::LOGIN_ACP or $login->type === \IPS\Login::LOGIN_REAUTHENTICATE )
		{
			$return = $return->setQueryString( 'prompt', 'consent' );
		}
		
		if ( $login->type === \IPS\Login::LOGIN_REAUTHENTICATE )
		{
			try
			{
				$return = $return->setQueryString( 'login_hint', $this->authenticatedEmail( \IPS\Db::i()->select( 'token_access_token', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $login->reauthenticateAs->member_id ) )->first() ) );
			}
			catch ( \UnderflowException $e ) {}
		}
		
		return $return;
	}
	
	/**
	 * Token Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function tokenEndpoint()
	{
		return \IPS\Http\Url::external('https://www.googleapis.com/oauth2/v4/token');
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
			return \IPS\Http\Url::internal( 'applications/core/interface/google/auth.php', 'none' );
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
		return $this->_userData( $accessToken )['sub'];
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
			return $this->_userData( $accessToken )['name'];
		}
		return NULL;
	}
	
	/**
	 * Get authenticated user's email address
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string|NULL
	 */
	protected function authenticatedEmail( $accessToken )
	{
		return $this->_userData( $accessToken )['email'];
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
				
		$userData = $this->_userData( $link['token_access_token'] );
		if ( isset( $userData['picture'] ) and $userData['picture'] )
		{
			return \IPS\Http\Url::external( $userData['picture'] )->setQueryString( 'sz', NULL );
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
		
		return $this->_userData( $link['token_access_token'] )['name'];
	}

	/**
	 * Get link to user's remote profile
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$identifier	The ID Nnumber/string from remote service
	 * @param	string	$username	The username from remote service
	 * @return	\IPS\Http\Url|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
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
		$authorizedScopes = $this->authorizedScopes( $member );

		if( $authorizedScopes === NULL )
		{
			$authorizedScopes = array();
		}
		
		$return = array();
		
		if ( ( !isset( $this->settings['update_email_changes'] ) or $this->settings['update_email_changes'] === 'optional' ) and ( \in_array( 'email', $authorizedScopes ) or \in_array( 'https://www.googleapis.com/auth/userinfo.email', $authorizedScopes ) ) )
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
			$response = \IPS\Http\Url::external( "https://www.googleapis.com/oauth2/v3/userinfo" )
				->request()
				->setHeaders( array(
					'Authorization' => "Bearer {$accessToken}"
				) )
				->get()
				->decodeJson();
				
			if ( isset( $response['error'] ) )
			{
				if ( isset( $response['error_description'] ) )
				{
					throw new \IPS\Login\Exception( $response['error_description'], \IPS\Login\Exception::INTERNAL_ERROR );
				}
				// Keeping this for backwards compatibility..
				else if( isset( $response['error']['errors'][0]['message'] ) )
				{
					throw new \IPS\Login\Exception( $response['error']['errors'][0]['message'], \IPS\Login\Exception::INTERNAL_ERROR );
				}

			}
			
			$this->_cachedUserData[ $accessToken ] = $response;
		}
		return $this->_cachedUserData[ $accessToken ];
	}
}