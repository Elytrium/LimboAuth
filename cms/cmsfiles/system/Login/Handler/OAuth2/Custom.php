<?php
/**
 * @brief		Custom OAuth 2 Login Handler
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		31 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Login\Handler\OAuth2;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Custom OAuth 2 Login Handler
 */
class _Custom extends \IPS\Login\Handler\OAuth2
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
		return 'login_handler_custom_oauth';
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
		$return = array();
		
		$return[] = array( 'login_handler_oauth_settings', 'login_handler_custom_oauth_info' );
		
		$return['grant_type'] = new \IPS\Helpers\Form\Radio( 'oauth_custom_grant_type', isset( $this->settings['grant_type'] ) ? $this->settings['grant_type'] : 'authorization_code', TRUE, array(
			'options' => array(
				'authorization_code'	=> 'client_grant_type_authorization_code',
				'implicit'				=> 'client_grant_type_implicit',
				'password'				=> 'client_grant_type_password',
			),
			'toggles' => array(
				'authorization_code'	=> array( 'authorization_endpoint', 'authorization_endpoint_secure', 'button_color', 'button_text', 'client_secret' ),
				'implicit'				=> array( 'authorization_endpoint', 'authorization_endpoint_secure', 'button_color', 'button_text' ),
				'password'				=> array( 'client_secret', 'oauth_custom_auth_types', 'forgot_password_url' )
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
		
		$return['authentication_type'] = new \IPS\Helpers\Form\Radio( 'oauth_custom_authentication_type', isset( $this->settings['authentication_type'] ) ? $this->settings['authentication_type'] : static::AUTHENTICATE_HEADER, TRUE, array(
			'options' => array(
				static::AUTHENTICATE_HEADER	=> 'oauth_custom_authentication_type_header',
				static::AUTHENTICATE_POST	=> 'oauth_custom_authentication_type_post',
			)
		) );
		
		$return['scopes'] = new \IPS\Helpers\Form\Stack( 'oauth_scopes_to_request', isset( $this->settings['scopes'] ) ? $this->settings['scopes'] : array(), FALSE, array() );
		
		$authorizationEndpointValidation = function( $val )
		{
			if ( \IPS\OAUTH_REQUIRES_HTTPS and $val and $val instanceof \IPS\Http\Url )
			{
				if ( $val->data[ \IPS\Http\Url::COMPONENT_SCHEME ] !== 'https' )
				{
					throw new \DomainException('authorization_endpoint_https');
				}
				if ( $val->data[ \IPS\Http\Url::COMPONENT_FRAGMENT ] )
				{
					throw new \DomainException('authorization_endpoint_fragment');
				}
			}
		};

		$return['authorization_endpoint'] = new \IPS\Helpers\Form\Url( 'oauth_authorization_endpoint', isset( $this->settings['authorization_endpoint'] ) ? $this->settings['authorization_endpoint'] : NULL, NULL, array( 'placeholder' => 'https://example.com/oauth/authorize' ), $authorizationEndpointValidation, NULL, NULL, 'authorization_endpoint' );
		$return['authorization_endpoint_secure'] = new \IPS\Helpers\Form\Url( 'oauth_authorization_endpoint_secure', ( isset( $this->settings['authorization_endpoint_secure'] ) AND $this->settings['authorization_endpoint_secure'] ) ? $this->settings['authorization_endpoint_secure'] : NULL, NULL, array( 'nullLang' => 'oauth_authorization_endpoint_same', 'placeholder' => 'https://example.com/oauth/authorize/?prompt=login' ), $authorizationEndpointValidation, NULL, NULL, 'authorization_endpoint_secure' );
		$return['token_endpoint'] = new \IPS\Helpers\Form\Url( 'oauth_token_endpoint', isset( $this->settings['token_endpoint'] ) ? $this->settings['token_endpoint'] : NULL, TRUE, array( 'placeholder' => 'https://www.example.com/oauth/token' ) );
		$return['user_endpoint'] = new \IPS\Helpers\Form\Url( 'oauth_user_endpoint', isset( $this->settings['user_endpoint'] ) ? $this->settings['user_endpoint'] : NULL, TRUE, array( 'placeholder' => 'https://www.example.com/oauth/me' ) );
		$return['uid_field'] = new \IPS\Helpers\Form\Text( 'oauth_custom_uid_field', isset( $this->settings['uid_field'] ) ? $this->settings['uid_field'] : NULL, TRUE, array() );
		$return['name_field'] = new \IPS\Helpers\Form\Text( 'oauth_custom_name_field', isset( $this->settings['name_field'] ) ? $this->settings['name_field'] : NULL, FALSE, array(), NULL, NULL, NULL, 'login_real_name' );
		$return['email_field'] = new \IPS\Helpers\Form\Text( 'oauth_custom_email_field', isset( $this->settings['email_field'] ) ? $this->settings['email_field'] : NULL, FALSE, array(), NULL, NULL, NULL, 'login_real_email' );
		$return['photo_field'] = new \IPS\Helpers\Form\Text( 'oauth_custom_photo_field', isset( $this->settings['photo_field'] ) ? $this->settings['photo_field'] : NULL );
		if ( \IPS\Settings::i()->allow_forgot_password == 'normal' or \IPS\Settings::i()->allow_forgot_password == 'handler' )
		{
			$return['forgot_password_url'] = new \IPS\Helpers\Form\Url( 'handler_forgot_password_url', isset( $this->settings['forgot_password_url'] ) ? $this->settings['forgot_password_url'] : NULL, FALSE, array(), NULL, NULL, NULL, 'forgot_password_url' );
			\IPS\Member::loggedIn()->language()->words['handler_forgot_password_url_desc'] = \IPS\Member::loggedIn()->language()->addToStack( \IPS\Settings::i()->allow_forgot_password == 'normal' ? 'handler_forgot_password_url_desc_normal' : 'handler_forgot_password_url_deschandler' );
		}
		
		$return[] = 'login_handler_oauth_ui';
		$return['auth_types'] = new \IPS\Helpers\Form\Select( 'oauth_custom_auth_types', isset( $this->settings['auth_types'] ) ? $this->settings['auth_types'] : ( \IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL ), TRUE, array( 'options' => array(
			\IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL => 'username_or_email',
			\IPS\Login::AUTH_TYPE_EMAIL	=> 'email_address',
			\IPS\Login::AUTH_TYPE_USERNAME => 'username',
		) ), NULL, NULL, NULL, 'oauth_custom_auth_types' );
		$return['button_color'] = new \IPS\Helpers\Form\Color( 'oauth_custom_button_color', isset( $this->settings['button_color'] ) ? $this->settings['button_color'] : '#478F79', NULL, array(), NULL, NULL, NULL, 'button_color' );		
		$return['button_text'] = new \IPS\Helpers\Form\Translatable( 'oauth_custom_button_text',  NULL, NULL, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('oauth_custom_button_text_custom_placeholder'), 'app' => 'core', 'key' => ( $this->id ? "core_custom_oauth_{$this->id}" : NULL ) ), NULL, NULL, NULL, 'button_text' );
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
		$return['button_icon'] = (string) $return['button_icon'];
		$return['authorization_endpoint'] = (string) $return['authorization_endpoint'];
		$return['token_endpoint'] = (string) $return['token_endpoint'];
		$return['user_endpoint'] = (string) $return['user_endpoint'];
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
		$parent = parent::formatFormValues( $values );

		if( isset( $values['oauth_custom_button_text'] ) )
		{
			if ( !$this->id )
			{
				$this->save();
			}
			\IPS\Lang::saveCustom( 'core', "core_custom_oauth_{$this->id}", $values['oauth_custom_button_text'] );
			unset( $values['button_text'] );
		}
		
		return $parent;
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
	 * Grant Type
	 *
	 * @return	string
	 */
	protected function grantType()
	{
		return isset( $this->settings['grant_type'] ) ? $this->settings['grant_type'] : 'authorization_code';
	}
	
	/**
	 * Should client credentials be sent as an "Authoriation" header, or as POST data?
	 *
	 * @return	string
	 */
	protected function _authenticationType()
	{
		return isset( $this->settings['authentication_type'] ) ? $this->settings['authentication_type'] : static::AUTHENTICATE_HEADER;
	}
	
	/**
	 * Get scopes to request
	 *
	 * @param	array|NULL	$additional	Any additional scopes to request
	 * @return	array
	 */
	protected function scopesToRequest( $additional=NULL )
	{
		return $this->settings['scopes'];
	}
	
	/**
	 * Authorization Endpoint
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Http\Url
	 */
	protected function authorizationEndpoint( \IPS\Login $login )
	{
		if ( isset( $this->settings['authorization_endpoint_secure'] ) and $this->settings['authorization_endpoint_secure'] and ( $login->type === \IPS\Login::LOGIN_ACP or $login->type === \IPS\Login::LOGIN_REAUTHENTICATE ) )
		{
			return \IPS\Http\Url::external( $this->settings['authorization_endpoint_secure'] );
		}
		
		return \IPS\Http\Url::external( $this->settings['authorization_endpoint'] );
	}
	
	/**
	 * Token Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function tokenEndpoint()
	{
		return \IPS\Http\Url::external( $this->settings['token_endpoint'] );
	}
	
	/**
	 * Get authenticated user's identifier (may not be a number)
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string
	 */
	protected function authenticatedUserId( $accessToken )
	{		
		if ( $userId = static::getValueFromArray( $this->_userData( $accessToken, $this->settings['uid_field'] ), $this->settings['uid_field'] ) )
		{
			return $userId;
		}
		throw new \Exception;
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
		if ( isset( $this->settings['name_field'] ) and $this->settings['name_field'] and $username = static::getValueFromArray( $this->_userData( $accessToken, $this->settings['name_field'] ), $this->settings['name_field'] ) )
		{
			return $username;
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
		if ( isset( $this->settings['email_field'] ) and $this->settings['email_field'] and $email = static::getValueFromArray( $this->_userData( $accessToken, $this->settings['email_field'] ), $this->settings['email_field'] ) )
		{
			return $email;
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
		if ( isset( $this->settings['photo_field'] ) and $this->settings['photo_field'] )
		{
			if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
			{
				throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
			}
						
			if ( $photo = static::getValueFromArray( $this->_userData( $link['token_access_token'], $this->settings['photo_field'] ), $this->settings['photo_field'] ) )
			{
				return \IPS\Http\Url::external( $photo );
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
		
		return $this->authenticatedUserName( $link['token_access_token'] );
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
		
		if ( isset( $this->settings['email_field'] ) and $this->settings['email_field'] and ( !isset( $this->settings['update_email_changes'] ) or $this->settings['update_email_changes'] === 'optional' ) )
		{
			$return[] = 'email';
		}
		
		if ( isset( $this->settings['name_field'] ) and $this->settings['name_field'] and isset( $this->settings['update_name_changes'] ) and $this->settings['update_name_changes'] === 'optional' )
		{
			$return[] = 'name';
		}
		
		if ( isset( $this->settings['photo_field'] ) and $this->settings['photo_field'] )
		{
			$return[] = 'photo';
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
	protected function _userData( $accessToken, $expectedParam = NULL )
	{
		if ( !isset( $this->_cachedUserData[ $accessToken ] ) )
		{
			/* Try the most sensible way first */
			$response = \IPS\Http\Url::external( $this->settings['user_endpoint'] )->request()
				->setHeaders( array(
					'Authorization' => "Bearer {$accessToken}"
				) )
				->get()
				->decodeJson();
			
			/* Check if we got what we were expecting. If we didn't, try sending the access token in the query string.
				While the spec discourages this usage, it is still valid and some providers may require it */ 
			if ( $expectedParam !== NULL )
			{
				if ( static::getValueFromArray( $response, $expectedParam ) === NULL )
				{
					$response = \IPS\Http\Url::external( $this->settings['user_endpoint'] )->setQueryString( 'access_token', $accessToken )->request()
						->get()
						->decodeJson();
				}
			}

			/* Check for any errors */
			if ( $response === NULL OR static::getValueFromArray( $response, $this->settings['uid_field'] ) === NULL )
			{
				\IPS\Log::log( print_r( $response, TRUE ), 'oauth_custom' );
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}

			/* Set */						
			$this->_cachedUserData[ $accessToken ] = $response;
		}
		return $this->_cachedUserData[ $accessToken ];
	}
	
	/**
	 * Get value from an array
	 *
	 * @param	array	$array	The array with the data
	 * @param	string	$key	The key using[square][brackets]
	 * @return	mixed
	 */
	protected static function getValueFromArray( $array, $key )
	{
		while ( $pos = mb_strpos( $key, '[' ) )
		{
			preg_match( '/^(.+?)\[([^\]]+?)?\](.*)?$/', $key, $matches );
			
			if ( !array_key_exists( $matches[1], $array ) )
			{
				return NULL;
			}
				
			$array = $array[ $matches[1] ];
			$key = $matches[2] . $matches[3];
		}
		
		if ( !isset( $array[ $key ] ) )
		{
			return NULL;
		}
				
		return $array[ $key ];
	}
	
	/**
	 * Forgot Password URL
	 *
	 * @return	\IPS\Http\Url|NULL
	 */
	public function forgotPasswordUrl()
	{
		return ( isset( $this->settings['forgot_password_url'] ) and $this->settings['forgot_password_url'] ) ? \IPS\Http\Url::external( $this->settings['forgot_password_url'] ) : NULL;
	}
}