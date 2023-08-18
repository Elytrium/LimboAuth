<?php
/**
 * @brief		Abstract OAuth2 Login Handler
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		31 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Login\Handler;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract OAuth2 Login Handler
 */
abstract class _OAuth2 extends \IPS\Login\Handler
{	
	/* !Login Handler: Basics */
	
	/**
	 * @brief	Any additional scopes to authenticate with
	 */
	public $additionalScopes = NULL;

	/**
	 * @brief	Does this handler support PKCE?
	 */
	public $pkceSupported = TRUE;
	
	/**
	 * Get type
	 *
	 * @return	int
	 */
	public function type()
	{
		if ( $this->grantType() === 'password' )
		{
			return \IPS\Login::TYPE_USERNAME_PASSWORD;
		}
		else
		{
			return \IPS\Login::TYPE_BUTTON;
		}
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
		$return = array(
			array( 'login_handler_oauth_settings', \IPS\Member::loggedIn()->language()->addToStack( static::getTitle() . '_info', FALSE, array( 'sprintf' => array( (string) $this->redirectionEndpoint() ) ) ) ),
			'client_id'		=> new \IPS\Helpers\Form\Text( 'oauth_client_id', isset( $this->settings['client_id'] ) ? $this->settings['client_id'] : NULL, TRUE ),
			'client_secret'	=> new \IPS\Helpers\Form\Text( 'oauth_client_client_secret', isset( $this->settings['client_secret'] ) ? $this->settings['client_secret'] : NULL, NULL, array(), NULL, NULL, NULL, 'client_secret' ),
		);
				
		$return[] = 'account_management_settings';
		$return['show_in_ucp'] = new \IPS\Helpers\Form\Radio( 'login_handler_show_in_ucp', isset( $this->settings['show_in_ucp'] ) ? $this->settings['show_in_ucp'] : 'always', FALSE, array(
			'options' => array(
				'always'		=> 'login_handler_show_in_ucp_always',
				'loggedin'		=> 'login_handler_show_in_ucp_loggedin',
				'disabled'		=> 'login_handler_show_in_ucp_disabled'
			),
		) );
		
		$nameChangesDisabled = array();
		if ( $forceNameHandler = static::handlerHasForceSync( 'name', $this ) )
		{
			$nameChangesDisabled[] = 'force';
			\IPS\Member::loggedIn()->language()->words['login_update_changes_yes_name_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'login_update_changes_yes_disabled', FALSE, array( 'sprintf' => $forceNameHandler->_title ) );
		}
		$return['update_name_changes'] = new \IPS\Helpers\Form\Radio( 'login_update_name_changes', isset( $this->settings['update_name_changes'] ) ? $this->settings['update_name_changes'] : 'disabled', FALSE, array( 'options' => array(
			'force'		=> 'login_update_changes_yes_name',
			'optional'	=> 'login_update_changes_optional',
			'disabled'	=> 'login_update_changes_no',
		), 'disabled' => $nameChangesDisabled ), NULL, NULL, NULL, 'login_update_name_changes_inc_optional' );
		
		$emailChangesDisabled = array();
		if ( $forceEmailHandler = static::handlerHasForceSync( 'email', $this ) )
		{
			$emailChangesDisabled[] = 'force';
			\IPS\Member::loggedIn()->language()->words['login_update_changes_yes_email_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'login_update_changes_yes_disabled', FALSE, array( 'sprintf' => $forceEmailHandler->_title ) );
		}
		$return['update_email_changes'] = new \IPS\Helpers\Form\Radio( 'login_update_email_changes', isset( $this->settings['update_email_changes'] ) ? $this->settings['update_email_changes'] : 'optional', FALSE, array( 'options' => array(
			'force'		=> 'login_update_changes_yes_email',
			'optional'	=> 'login_update_changes_optional',
			'disabled'	=> 'login_update_changes_no',
		), 'disabled' => $emailChangesDisabled ), NULL, NULL, NULL, 'login_update_email_changes_inc_optional' );
		
		return array_merge( $return, parent::acpForm() );		
	}
	
	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\LogicException
	 */
	public function testSettings()
	{
		parent::testSettings();
		
		try
		{
			/* Authorization Code / Implicit */
			if ( $this->grantType() === 'authorization_code' )
			{
				$response = $this->_authenticatedRequest( $this->tokenEndpoint(), array(
					'grant_type'	=> 'authorization_code',
					'code'			=> 'xxx',
					'redirect_uri'	=> (string) $this->redirectionEndpoint(),
				) )->decodeJson();
				
				if ( isset( $response['error'] ) and $response['error'] === 'invalid_client' )
				{
					throw new \LogicException( \IPS\Member::loggedIn()->language()->addToStack( 'oauth_setup_error_secret' ) );
				}
			}
			/* Password */
			elseif ( $this->grantType() === 'password' )
			{
				$response =  $this->_authenticatedRequest( $this->tokenEndpoint(), array(
					'grant_type'	=> 'password',
					'username'		=> 'username',
					'password'		=> 'password',
				) )->decodeJson();

				if ( !isset( $response['error'] ) or $response['error'] !== 'invalid_grant' )
				{
					throw new \LogicException( \IPS\Member::loggedIn()->language()->addToStack( 'oauth_setup_error_generic', FALSE, array( 'sprintf' => array( isset( $response['error_description'] ) ? $response['error_description'] : NULL ) ) ) );
				}
			}
		}
		catch( \IPS\Http\Request\Exception $e )
		{
			throw new \LogicException( \IPS\Member::loggedIn()->language()->addToStack( 'oauth_setup_error_generic', FALSE, array( 'sprintf' => array( $e->getMessage() ) ) ) );
		}
	}
	
	/* !Button Authentication */
	
	use ButtonHandler;
		
	/**
	 * Authenticate
	 *
	 * @param	\IPS\Login	$login		The login object
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticateButton( \IPS\Login $login )
	{
		/* If we have a code, process it */
		if ( $this->grantType() === 'authorization_code' and ( isset( \IPS\Request::i()->code ) or isset( \IPS\Request::i()->error ) ) )
		{
			return $this->_handleAuthorizationResponse( $login );
		}
		
		/* If we have a token, process that */
		elseif ( $this->grantType() === 'implicit' and ( isset( \IPS\Request::i()->access_token ) or isset( \IPS\Request::i()->error ) ) )
		{
			return $this->_handleAuthorizationResponse( $login );
		}
				
		/* Otherwise send them to the Authorization Endpoint */
		else
		{
			$data = array(
				'client_id'		=> $this->settings['client_id'],
				'response_type'	=> $this->grantType() === 'authorization_code' ? 'code' : 'token',
				'redirect_uri'	=> (string) $this->redirectionEndpoint(),
				'state'			=> $this->id . '-' . base64_encode( $login->url ) . '-' . \IPS\Session::i()->csrfKey . '-' . \IPS\Request::i()->ref,
			);
			
			if ( $this->grantType() === 'authorization_code' AND $this->pkceSupported === TRUE )
			{
				$_SESSION['codeVerifier'] = \IPS\Login::generateRandomString( 128 );
				$data['code_challenge'] = rtrim( strtr( base64_encode( pack( 'H*', hash( 'sha256', $_SESSION['codeVerifier'] ) ) ), '+/', '-_' ), '=' );
				$data['code_challenge_method'] = 'S256';
			}
			
			$target = $this->authorizationEndpoint( $login )->setQueryString( $data );
			
			if ( $scopes = $this->scopesToRequest( isset( \IPS\Request::i()->scopes ) ? explode( ',', \IPS\Request::i()->scopes ) : NULL ) )
			{
				$target = $target->setQueryString( 'scope', implode( ' ', $scopes ) );
			}
			
			\IPS\Output::i()->redirect( $target );
		}
	}
	
	/* !Username/Password Authentication */
	
	use UsernamePasswordHandler;
	
	/**
	 * Authenticate
	 *
	 * @param	\IPS\Login	$login				The login object
	 * @param	string		$usernameOrEmail	The username or email address provided by the user
	 * @param	object		$password			The plaintext password provided by the user, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticateUsernamePassword( \IPS\Login $login, $usernameOrEmail, $password )
	{
		if( !$usernameOrEmail )
		{
			$member = NULL;

			if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
			{
				$member = new \IPS\Member;
				$member->email = $usernameOrEmail;
			}

			throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_bad_username_or_password', FALSE, array( 'pluralize' => array( $this->authType() ) ) ), \IPS\Login\Exception::NO_ACCOUNT, NULL, $member );
		}

		$data =  array(
			'grant_type'		=> 'password',
			'username'		=> $usernameOrEmail,
			'password'		=> (string) $password,
		);
		if ( $scopes = $this->scopesToRequest() )
		{
			$data['scope'] = implode( ' ', $scopes );
		}
		
		try
		{
			$accessToken =  $this->_authenticatedRequest( $this->tokenEndpoint(), $data )->decodeJson();
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'oauth' );
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		if ( isset( $accessToken['access_token'] ) )
		{
			return $this->_processAccessToken( $login, $accessToken );
		}
		else
		{
			if ( isset( $accessToken['error'] ) and $accessToken['error'] === 'invalid_grant' )
			{
				$member = NULL;

				if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
				{
					$member = new \IPS\Member;
					$member->email = $usernameOrEmail;
				}

				throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_bad_username_or_password', FALSE, array( 'pluralize' => array( $this->authType() ) ) ), \IPS\Login\Exception::NO_ACCOUNT, NULL, $member );
			}
			
			\IPS\Log::log( print_r( $accessToken, TRUE ), 'oauth' );
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
	}
	
	/**
	 * Authenticate
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	object		$password	The plaintext password provided by the user, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	bool
	 */
	public function authenticatePasswordForMember( \IPS\Member $member, $password )
	{
		if ( $this->authType() & \IPS\Login::AUTH_TYPE_USERNAME )
		{
			try
			{
				$response = $this->_authenticatedRequest( $this->tokenEndpoint(), array(
					'grant_type'	=> 'password',
					'username'		=> $member->name,
					'password'		=> (string) $password,
				) )->decodeJson();
				if ( isset( $response['access_token'] ) )
				{
					return TRUE;
				}
			}
			catch ( \Exception $e ) { }
		}
		
		if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
		{
			try
			{
				$response =  $this->_authenticatedRequest( $this->tokenEndpoint(), array(
					'grant_type'	=> 'password',
					'username'		=> $member->email,
					'password'		=> (string) $password,
				) )->decodeJson();
				if ( isset( $response['access_token'] ) )
				{
					return TRUE;
				}
			}
			catch ( \Exception $e ) { }
		}
		
		return FALSE;
	}
	
	/* !OAuth Authentication */
	
	const AUTHENTICATE_HEADER = 'header';
	const AUTHENTICATE_POST  = 'post';
	
	/**
	 * Should client credentials be sent as an "Authoriation" header, or as POST data?
	 *
	 * @return	string
	 */
	protected function _authenticationType()
	{
		return static::AUTHENTICATE_HEADER;
	}
	
	/**
	 * Send request authenticated with client credentials
	 *
	 * @param	\IPS\Http\Url	$url	The URL
	 * @return	\IPS\Http\Response
	 */
	protected function _authenticatedRequest( \IPS\Http\Url $url, $data )
	{
		$request = $url->request();
		
		if ( $this->_authenticationType() === static::AUTHENTICATE_HEADER )
		{
			$request = $request->login( $this->settings['client_id'], $this->clientSecret() );
		}
		else
		{
			$data['client_id'] = $this->settings['client_id'];
			$data['client_secret'] = $this->clientSecret();
		}
		
		return $request->post( $data );
	}
	
	/**
	 * Handle authorization response
	 *
	 * @param	\IPS\Login	$login		The login object
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	protected function _handleAuthorizationResponse( \IPS\Login $login )
	{
		/* Did we get an error? */
		if ( isset( \IPS\Request::i()->error ) )
		{
			if ( \IPS\Request::i()->error === 'access_denied' )
			{
				return NULL;
			}
			else
			{
				\IPS\Log::log( print_r( $_GET, TRUE ), 'oauth' );
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}
		}
		
		/* If we have a code, swap it for an access token, otherwise, decode what we have */
		if ( isset( \IPS\Request::i()->code ) )
		{
			$accessToken = $this->_exchangeAuthorizationCodeForAccessToken( \IPS\Request::i()->code );
		}
		else
		{
			$accessToken = array(
				'access_token'	=> \IPS\Request::i()->access_token,
				'token_type'	=> isset( \IPS\Request::i()->token_type ) ? \IPS\Request::i()->token_type : 'bearer'
			);
			if ( isset( \IPS\Request::i()->expires_in ) )
			{
				$accessToken['expires_in'] = \IPS\Request::i()->expires_in;
			}
		}
		
		/* Process */
		return $this->_processAccessToken( $login, $accessToken );
	}
	
	/**
	 * Process an Access Token
	 *
	 * @param	\IPS\Login	$login			The login object
	 * @param	array		$accessToken	Access Token
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	protected function _processAccessToken( \IPS\Login $login, $accessToken )
	{		
		/* Get user id */
		try
		{
			$userId = $this->authenticatedUserId( $accessToken['access_token'] );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'oauth' );
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		/* What scopes did we get? */
		if ( isset( $accessToken['scope'] ) )
		{
			$scope = explode( ' ', $accessToken['scope'] );
		}
		else
		{
			$scope = $this->scopesIssued( $accessToken['access_token'] );
		}
				
		/* Has this user signed in with this service before? */
		try
		{
			$oauthAccess = \IPS\Db::i()->select( '*', 'core_login_links', array( 'token_login_method=? AND token_identifier=?', $this->id, $userId ) )->first();
			$member = \IPS\Member::load( $oauthAccess['token_member'] );
			
			/* If the user never finished the linking process, or the account has been deleted, discard this access token */
			if ( !$oauthAccess['token_linked'] or !$member->member_id )
			{
				\IPS\Db::i()->delete( 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $oauthAccess['token_member'] ) );
				throw new \UnderflowException;
			}
			
			/* Otherwise, update our token without replacing values already set but not reset in this request... */
			$update = array( 
				'token_access_token'	=> $accessToken['access_token'],
				'token_expires'			=> ( isset( $accessToken['expires_in'] ) ) ? ( time() + \intval( $accessToken['expires_in'] ) ) : NULL
			);

			if( isset( $accessToken['refresh_token'] ) )
			{
				$update['token_refresh_token'] = $accessToken['refresh_token'];
			}

			if( $scope )
			{
				$update['token_scope'] = json_encode( $scope );
			}

			\IPS\Db::i()->update( 'core_login_links', $update, array( 'token_login_method=? AND token_member=?', $this->id, $oauthAccess['token_member'] ) );
			
			/* ... and return the member object */
			return $member;
		}
		/* No, create or link the account */
		catch ( \UnderflowException $e )
		{
			/* Get the username + email */
			$name = NULL;
			try
			{
				$name = $this->authenticatedUserName( $accessToken['access_token'] );
			}
			catch ( \Exception $e ) {}
			
			$email = NULL;
			try
			{
				$email = $this->authenticatedEmail( $accessToken['access_token'] );
			}
			catch ( \Exception $e ) {}
			
			try
			{
				if ( $login->type === \IPS\Login::LOGIN_UCP )
				{
					$exception = new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT );
					$exception->handler = $this;
					$exception->member = $login->reauthenticateAs;
					throw $exception;
				}
				
				$member = $this->createAccount( $name, $email );
				
				\IPS\Db::i()->replace( 'core_login_links', array(
					'token_login_method'	=> $this->id,
					'token_member'			=> $member->member_id,
					'token_identifier'		=> $userId,
					'token_linked'			=> 1,
					'token_access_token'	=> $accessToken['access_token'],
					'token_expires'			=> isset( $accessToken['expires_in'] ) ? ( time() + \intval( $accessToken['expires_in'] ) ) : NULL,
					'token_refresh_token'	=> isset( $accessToken['refresh_token'] ) ? $accessToken['refresh_token'] : NULL,
					'token_scope'			=> $scope ? json_encode( $scope ) : NULL,
				) );
				
				$member->logHistory( 'core', 'social_account', array(
					'service'		=> static::getTitle(),
					'handler'		=> $this->id,
					'account_id'	=> $userId,
					'account_name'	=> $name,
					'linked'		=> TRUE,
					'registered'	=> TRUE
				) );
				
				if ( $syncOptions = $this->syncOptions( $member, TRUE ) )
				{
					$profileSync = array();
					foreach ( $syncOptions as $option )
					{
						$profileSync[ $option ] = array( 'handler' => $this->id, 'ref' => NULL, 'error' => NULL );
					}
					$member->profilesync = $profileSync;
					$member->save();
				}
				
				return $member;
			}
			catch ( \IPS\Login\Exception $exception )
			{
				if ( $exception->getCode() === \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT )
				{
					try
					{
						$identifier = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $exception->member->member_id ) )->first();

						if( $identifier != $userId )
						{
							$exception->setCode( \IPS\Login\Exception::LOCAL_ACCOUNT_ALREADY_MERGED );
							throw $exception;
						}
					}
					catch( \UnderflowException $e )
					{
						\IPS\Db::i()->replace( 'core_login_links', array(
							'token_login_method'	=> $this->id,
							'token_member'			=> $exception->member->member_id,
							'token_identifier'		=> $userId,
							'token_linked'			=> 0,
							'token_access_token'	=> $accessToken['access_token'],
							'token_expires'			=> isset( $accessToken['expires_in'] ) ? ( time() + \intval( $accessToken['expires_in'] ) ) : NULL,
							'token_refresh_token'	=> isset( $accessToken['refresh_token'] ) ? $accessToken['refresh_token'] : NULL,
							'token_scope'			=> $scope ? json_encode( $scope ) : NULL,
						) );
					}
				}
				
				throw $exception;
			}
		}
	}
	
	/**
	 * Exchange authorization code for access token
	 *
	 * @param	string	$code	Authorization code
	 * @return	array
	 * @throws	\IPS\Login\Exception
	 */
	protected function _exchangeAuthorizationCodeForAccessToken( $code )
	{
		/* Make the request */
		$data = NULL;
		$response = array();
		try
		{
			$post = array(
				'grant_type'	=> 'authorization_code',
				'code'			=> $code,
				'redirect_uri'	=> (string) $this->redirectionEndpoint(),
			);

			if( $this->pkceSupported === TRUE )
			{
				$post['code_verifier'] = $_SESSION['codeVerifier'];
			}

			$data = $this->_authenticatedRequest( $this->tokenEndpoint(), $post );

			$response = $data->decodeJson();
			unset( $_SESSION['codeVerifier'] );
		}
		catch( \RuntimeException $e )
		{
			\IPS\Log::log( var_export( $data, true ), 'oauth' );
		}
		
		/* Check for any errors */
		if ( isset( $response['error'] ) or !isset( $response['access_token'] ) or ( isset( $response['token_type'] ) and mb_strtolower( $response['token_type'] ) !== 'bearer' ) )
		{
			\IPS\Log::log( print_r( $response, TRUE ), 'oauth' );
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}		
		
		/* Return */
		return $response;
	}
	
	/**
	 * Get link
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	array
	 */
	protected function _link( \IPS\Member $member )
	{
		$link = parent::_link( $member );

		if ( $link and $link['token_expires'] and $link['token_expires'] < time() and $link['token_refresh_token'] )
		{
			try
			{
				$newAccessToken =  $this->_authenticatedRequest( $this->tokenEndpoint(), array(
					'grant_type'	=> 'refresh_token',
					'refresh_token'	=> $link['token_refresh_token'],
				) )->decodeJson();
				
				if ( isset( $newAccessToken['error'] ) or !isset( $newAccessToken['access_token'] ) or ( isset( $newAccessToken['token_type'] ) and mb_strtolower( $newAccessToken['token_type'] ) !== 'bearer' ) )
				{
					if( !isset( $newAccessToken['error'] ) OR $newAccessToken['error'] != 'invalid_grant' )
					{
						\IPS\Log::log( print_r( $newAccessToken, TRUE ), 'oauth' );
					}

					\IPS\Db::i()->update( 'core_login_links', array( 'token_refresh_token' => NULL ), array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) );
					return $link;
				}
				
				$update = array();
				if ( isset( $newAccessToken['access_token'] ) )
				{
					$update['token_access_token'] = $newAccessToken['access_token'];
				}
				if ( isset( $newAccessToken['expires_in'] ) )
				{
					$update['token_expires'] = ( time() + $newAccessToken['expires_in'] );
				}
				if ( isset( $newAccessToken['refresh_token'] ) )
				{
					$update['token_refresh_token'] = $newAccessToken['refresh_token'];
				}
				
				foreach ( $update as $k => $v )
				{
					$link[ $k ] = $v;
					$this->_cachedLinks[ $member->member_id ][ $k ] = $v;
				}
				\IPS\Db::i()->update( 'core_login_links', $update, array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) );
			}
			catch ( \Exception $e )
			{
				\IPS\Log::log( $e, 'oauth' );
			}
		}
		
		return $link;
	}
		
	/* !OAuth Abstract */
	
	/**
	 * Grant Type
	 *
	 * @return	string
	 */
	abstract protected function grantType();
	
	/**
	 * Get scopes to request
	 *
	 * @param	array|NULL	$additional	Any additional scopes to request
	 * @return	array
	 */
	protected function scopesToRequest( $additional=NULL )
	{
		return array();
	}
	
	/**
	 * Scopes Issued
	 *
	 * @param	string		$accessToken	Access Token
	 * @return	array|NULL
	 */
	public function scopesIssued( $accessToken )
	{
		return $this->scopesToRequest(); // Unless the individual handler overrides this, we'll just assume it's given us what we asked for (which is how the OAuth spec says you're supposed to do it anyway)
	}
	
	/**
	 * Authorized scopes
	 *
	 * @return	array|NULL
	 */
	public function authorizedScopes( \IPS\Member $member )
	{
		if ( !( $link = $this->_link( $member ) ) )
		{
			return NULL;
		}
						
		return $link['token_scope'] ? json_decode( $link['token_scope'] ) : NULL;
	}
	
	/**
	 * Authorization Endpoint
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Http\Url
	 */
	abstract protected function authorizationEndpoint( \IPS\Login $login );
	
	/**
	 * Token Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	abstract protected function tokenEndpoint();
	
	/**
	 * Redirection Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function redirectionEndpoint()
	{
		return \IPS\Http\Url::internal( 'oauth/callback/', 'none' );
	}
	
	/**
	 * Get authenticated user's identifier (may not be a number)
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string
	 */
	abstract protected function authenticatedUserId( $accessToken );
	
	/**
	 * Get authenticated user's username
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string|NULL
	 */
	protected function authenticatedUserName( $accessToken )
	{
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
		return NULL;
	}
	
	/**
	 * Get user's identifier (may not be a number)
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	string|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userId( \IPS\Member $member )
	{
		if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
		{
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		return $this->authenticatedUserId( $link['token_access_token'] );
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
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		return $this->authenticatedUserName( $link['token_access_token'] );
	}
	
	/**
	 * Get user's email address
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	string|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userEmail( \IPS\Member $member )
	{
		if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
		{
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		return $this->authenticatedEmail( $link['token_access_token'] );
	}
	
	/* !UCP */
	
	/**
	 * Show in Account Settings?
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for if it should show generally
	 * @return	bool
	 */
	public function showInUcp( \IPS\Member $member = NULL )
	{
		if ( !isset( $this->settings['show_in_ucp'] ) ) // Default to showing
		{
			return TRUE;
		}
		return parent::showInUcp( $member );
	}


	/**
	 * Has any sync options
	 *
	 * @return	bool
	 */
	public function hasSyncOptions()
	{
		return TRUE;
	}
	
	/**
	 * Client Secret
	 *
	 * @return	string | NULL
	 */
	public function clientSecret()
	{
		return isset( $this->settings['client_secret'] ) ? $this->settings['client_secret'] : NULL;
	}
	
	/**
	 * [Node] Save Add/Edit Form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function saveForm( $values )
	{
        /* If we are prompting users we need to disable force syncing */
		if( isset( $values['login_settings']['real_name'] ) AND $values['login_settings']['real_name'] == 0 )
		{
			$values['login_settings']['update_name_changes'] = "disabled";
		}
		
		parent::saveForm( $values );
	}	
}