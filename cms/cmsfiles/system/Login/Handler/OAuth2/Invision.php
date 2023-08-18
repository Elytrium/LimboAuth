<?php
/**
 * @brief		Invision Community Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 June 2017
 */

namespace IPS\Login\Handler\OAuth2;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invision Community Login Handler
 */
class _Invision extends \IPS\Login\Handler\OAuth2
{
	/**
	 * @brief	Can we have multiple instances of this handler?
	 */
	public static $allowMultiple = TRUE;
	
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public static function getTitle()
	{
		return 'login_handler_InvisionCommunity';
	}
	
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
		\IPS\Member::loggedIn()->language()->words['login_acp_desc'] = \IPS\Member::loggedIn()->language()->addToStack('login_acp_will_reauth');
		
		$return = array();
		
		$return[] = array( 'login_handler_InvisionCommunity_info_title', 'login_handler_InvisionCommunity_info' );
		$return['url'] = new \IPS\Helpers\Form\Url( 'oauth_invision_endpoint', isset( $this->settings['url'] ) ? $this->settings['url'] : NULL, TRUE, array( 'placeholder' => 'https://othercommunity.example.com' ), function( $val )
		{
			if ( rtrim( (string) $val, '/' ) === rtrim( \IPS\Settings::i()->base_url, '/' ) )
			{
				throw new \DomainException('oauth_invision_endpoint_internal');
			}
		} );
		$return['grant_type'] = new \IPS\Helpers\Form\Radio( 'oauth_invision_grant_type', isset( $this->settings['grant_type'] ) ? $this->settings['grant_type'] : 'authorization_code', TRUE, array(
			'options' => array(
				'authorization_code'	=> 'invision_grant_type_authorization_code',
				'password'				=> 'invision_grant_type_password',
			),
			'toggles' => array(
				'authorization_code'	=> array( 'button_color', 'button_text' ),
				'password'				=> array( 'oauth_custom_auth_types' )
			)
		) );
		$accountManagementSettings = array();
		$active = 'return';
		foreach ( parent::acpForm() as $k => $v )
		{
			if ( $v === 'account_management_settings' )
			{
				$active = 'accountManagementSettings';
			}
			if ( !\is_string( $v ) and !\is_array( $v ) )
			{
				${$active}[ $k ] = $v;
			}
		}
		
		$return[] = 'login_handler_oauth_ui';
		$return['auth_types'] = new \IPS\Helpers\Form\Select( 'oauth_custom_auth_types', isset( $this->settings['auth_types'] ) ? $this->settings['auth_types'] : ( \IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL ), TRUE, array( 'options' => array(
			\IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL => 'username_or_email',
			\IPS\Login::AUTH_TYPE_EMAIL	=> 'email_address',
			\IPS\Login::AUTH_TYPE_USERNAME => 'username',
		) ), NULL, NULL, NULL, 'oauth_custom_auth_types' );
		$return['button_color'] = new \IPS\Helpers\Form\Color( 'oauth_custom_button_color', isset( $this->settings['button_color'] ) ? $this->settings['button_color'] : '#3E4148', NULL, array(), NULL, NULL, NULL, 'button_color' );		
		$return['button_text'] = new \IPS\Helpers\Form\Translatable( 'oauth_custom_button_text',  NULL, NULL, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('oauth_custom_button_text_invision_placeholder'), 'app' => 'core', 'key' => ( $this->id ? "core_custom_oauth_{$this->id}" : NULL ) ), NULL, NULL, NULL, 'button_text' );
		$return['button_icon'] = new \IPS\Helpers\Form\Upload( 'oauth_custom_button_icon',  ( isset( $this->settings['button_icon'] ) and $this->settings['button_icon'] ) ? \IPS\File::get( 'core_Login', $this->settings['button_icon'] ) : NULL, FALSE, array( 'storageExtension' => 'core_Login' ), NULL, NULL, NULL, 'button_icon' );
		
		$return[] = 'account_management_settings';
		foreach ( $accountManagementSettings as $k => $v )
		{
			$return[ $k ] = $v;
		}
		
		return $return;
	}
	
	/**
	 * Save Handler Settings
	 *
	 * @param	array	$values	Values from form
	 * @return	array
	 */
	public function acpFormSave( &$values )
	{
		$return = parent::acpFormSave( $values );
		$return['url'] = (string) $return['url'];
		$return['button_icon'] = (string) $return['button_icon'];
		return $return;
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if( isset( $values['oauth_custom_button_text'] ) )
		{
			if ( !$this->id )
			{
				$this->save();
			}
			\IPS\Lang::saveCustom( 'core', "core_custom_oauth_{$this->id}", $values['oauth_custom_button_text'] );
			unset( $values['button_text'] );
		}
		
		return parent::formatFormValues( $values );
	}
	
	/**
	 * Get the button color
	 *
	 * @return	string
	 */
	public function buttonColor()
	{
		return $this->settings['button_color'];
	}
	
	/**
	 * Get the button icon
	 *
	 * @return	string
	 */
	public function buttonIcon()
	{
		return ( isset( $this->settings['button_icon'] ) and $this->settings['button_icon'] ) ? \IPS\File::get( 'core_Login', $this->settings['button_icon'] ) : NULL;
	}

	/**
	 * Get button class
	 *
	 * @return	string
	 */
	public function buttonClass()
	{
		return 'ipsSocial_ips';
	}
	
	/**
	 * Get logo to display in information about logins with this method
	 * Returns NULL for methods where it is not necessary to indicate the method, e..g Standard
	 *
	 * @return	\IPS\Http\Url
	 */
	public function logoForDeviceInformation()
	{
		return ( isset( $this->settings['button_icon'] ) and $this->settings['button_icon'] ) ? \IPS\File::get( 'core_Login', $this->settings['button_icon'] )->url : NULL;
	}
	
	/**
	 * Get logo to display in user cp sidebar
	 *
	 * @return	\IPS\Http\Url
	 */
	public function logoForUcp()
	{
		return $this->logoForDeviceInformation();
	}
	
	/**
	 * Get button text
	 *
	 * @return	string
	 */
	public function buttonText()
	{
		return "core_custom_oauth_{$this->id}";
	}
	
	/**
	 * Should client credentials be sent as an "Authoriation" header, or as POST data?
	 *
	 * @return	string
	 */
	protected function _authenticationType()
	{
		return static::AUTHENTICATE_POST; // Just because it's possible their server isn't configured to accept HTTP Authorization whereas we know this will always work
	}
	
	/**
	 * Grant Type
	 *
	 * @return	string
	 */
	protected function grantType()
	{
		return isset( $this->settings['grant_type'] ) ? $this->settings['grant_type'] : 'authorization_code';
	}
	
	/**
	 * Get scopes to request
	 *
	 * @param	array|NULL	$additional	Any additional scopes to request
	 * @return	array
	 */
	protected function scopesToRequest( $additional=NULL )
	{
		return array( 'profile', 'email' );
	}
	
	/**
	 * Authorization Endpoint
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Http\Url
	 */
	protected function authorizationEndpoint( \IPS\Login $login )
	{
		$return = \IPS\Http\Url::external( $this->settings['url'] . '/oauth/authorize/' );
		
		if ( $login->type === \IPS\Login::LOGIN_ACP or $login->type === \IPS\Login::LOGIN_REAUTHENTICATE )
		{
			$return = $return->setQueryString( 'prompt', 'login' );
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
		return \IPS\Http\Url::external( $this->settings['url'] . '/oauth/token/' );
	}
	
	/**
	 * Get authenticated user's identifier (may not be a number)
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string
	 */
	protected function authenticatedUserId( $accessToken )
	{
		$userData = $this->_userData( $accessToken );
		if ( isset( $userData['id'] ) )
		{
			return $userData['id'];
		}
		return NULL;
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
		$userData = $this->_userData( $accessToken );
		if ( isset( $userData['name'] ) )
		{
			return $userData['name'];
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
		$userData = $this->_userData( $accessToken );
		if ( isset( $userData['email'] ) )
		{
			return $userData['email'];
		}
		return NULL;
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
		if ( ( !isset( $userData['photoUrlIsDefault'] ) or !$userData['photoUrlIsDefault'] ) AND isset( $userData['photoUrl'] ) )
		{
			return \IPS\Http\Url::external( $userData['photoUrl'] );
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
		
		$userData = $this->_userData( $link['token_access_token'] );
		if( isset( $userData['name'] ) )
		{
			return $userData['name'];
		}

		return NULL;
	}
	
	/**
	 * Get user's cover photo
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	\IPS\Http\Url|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userCoverPhoto( \IPS\Member $member )
	{
		if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
		{
			throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
		}
				
		$userData = $this->_userData( $link['token_access_token'] );		
		if ( isset( $userData['coverPhotoUrl'] ) and $userData['coverPhotoUrl'] )
		{
			return \IPS\Http\Url::external( $userData['coverPhotoUrl'] );
		}
		
		return NULL;
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
		return \IPS\Http\Url::external( $this->settings['url'] )->setQueryString( 'showuser', $identifier );
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
		
		if ( !isset( $this->settings['update_email_changes'] ) or $this->settings['update_email_changes'] === 'optional' or ( $defaultOnly and $this->settings['update_email_changes'] === 'force' ) )
		{
			$return[] = 'email';
		}
		
		if ( isset( $this->settings['update_name_changes'] ) and $this->settings['update_name_changes'] === 'optional' and isset( $this->settings['real_name'] ) and $this->settings['real_name'] )
		{
			$return[] = 'name';
		}
		
		$return[] = 'photo';
		$return[] = 'cover';
		
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
	 * @return	array|null
	 */
	protected function _userData( $accessToken )
	{
		if ( !isset( $this->_cachedUserData[ $accessToken ] ) )
		{
			$response = \IPS\Http\Url::external( $this->settings['url'] . '/api/index.php?/core/me' )
				->request()
				->setHeaders( array(
					'Authorization' => "Bearer {$accessToken}"
				) )
				->get()
				->decodeJson();
			
			if ( isset( $response['errorCode'] ) )
			{
				throw new \IPS\Login\Exception( $response['errorMessage'], \IPS\Login\Exception::INTERNAL_ERROR );
			}
						
			try
			{
				$email = \IPS\Http\Url::external( $this->settings['url'] . '/api/index.php?/core/me/email' )
					->request()
					->setHeaders( array(
						'Authorization' => "Bearer {$accessToken}"
					) )
					->get()
					->decodeJson();
				
				if ( isset( $email['email'] ) )
				{
					$response['email'] = $email['email'];
				}
			}
			catch ( \Exception $e ) { }
						
			$this->_cachedUserData[ $accessToken ] = $response;
		}
		return $this->_cachedUserData[ $accessToken ];
	}

	/**
	 * Forgot Password URL
	 *
	 * @return	\IPS\Http\Url|NULL
	 */
	public function forgotPasswordUrl()
	{
		return \IPS\Http\Url::external( $this->settings['url'] . '/index.php?app=core&module=system&controller=lostpass' );
	}
}