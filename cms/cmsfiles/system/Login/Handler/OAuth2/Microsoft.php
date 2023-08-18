<?php
/**
 * @brief		Microsoft Login Handler
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
 * Microsoft Login Handler
 */
class _Microsoft extends \IPS\Login\Handler\OAuth2
{
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public static function getTitle()
	{
		return 'login_handler_Live';
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
		\IPS\Member::loggedIn()->language()->words['oauth_client_id'] = \IPS\Member::loggedIn()->language()->addToStack('login_live_client');
		\IPS\Member::loggedIn()->language()->words['oauth_client_client_secret'] = \IPS\Member::loggedIn()->language()->addToStack('login_live_secret');

		return array_merge( array(
			'real_name'	=> new \IPS\Helpers\Form\Radio( 'login_real_name', isset( $this->settings['real_name'] ) ? $this->settings['real_name'] : 1, FALSE, array(
				'options' => array(
					1			=> 'login_real_name_microsoft',
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
		return '#008b00';
	}
	
	/**
	 * Get the button icon
	 *
	 * @return	string
	 */
	public function buttonIcon()
	{
		return 'windows';
	}
	
	/**
	 * Get button text
	 *
	 * @return	string
	 */
	public function buttonText()
	{
		return 'login_live';
	}

	/**
	 * Get button class
	 *
	 * @return	string
	 */
	public function buttonClass()
	{
		return 'ipsSocial_microsoft';
	}
	
	/**
	 * Get logo to display in information about logins with this method
	 * Returns NULL for methods where it is not necessary to indicate the method, e..g Standard
	 *
	 * @return	\IPS\Http\Url
	 */
	public function logoForDeviceInformation()
	{
		return \IPS\Theme::i()->resource( 'logos/login/Microsoft.png', 'core', 'interface' );
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
			'openid',
			'User.Read',
			'offline_access'
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
		return \IPS\Http\Url::external('https://login.microsoftonline.com/common/oauth2/v2.0/authorize');
	}
	
	/**
	 * Token Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function tokenEndpoint()
	{
		return \IPS\Http\Url::external('https://login.microsoftonline.com/common/oauth2/v2.0/token');
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
			return \IPS\Http\Url::internal( 'applications/core/interface/microsoft/auth.php', 'none' );
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
			return $this->_userData( $accessToken )['displayName'];
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
		return $this->_userData( $accessToken )['userPrincipalName'];
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
		
		return $this->_userData( $link['token_access_token'] )['displayName'];
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
			$response = \IPS\Http\Url::external( "https://graph.microsoft.com/v1.0/me" )
				->request()
				->setHeaders( array(
					'Authorization' => "Bearer {$accessToken}"
				) )
				->get()
				->decodeJson();

			if ( isset( $response['error'] ) )
			{
				throw new \IPS\Login\Exception( $response['error']['message'], \IPS\Login\Exception::INTERNAL_ERROR );
			}
				
			$this->_cachedUserData[ $accessToken ] = $response;
		}

		return $this->_cachedUserData[ $accessToken ];
	}
}