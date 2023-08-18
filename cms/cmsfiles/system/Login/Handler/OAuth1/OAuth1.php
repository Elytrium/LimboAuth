<?php
/**
 * @brief		Abstract OAuth1 Login Handler
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
 * Abstract OAuth1 Login Handler
 */
abstract class _OAuth1 extends \IPS\Login\Handler
{
	/* !Login Handler: Basics */
		
	/**
	 * ACP Settings Form
	 *
	 * @param	string	$url	URL to redirect user to after successful submission
	 * @return	array	List of settings to save - settings will be stored to core_login_methods.login_settings DB field
	 * @code
	 	return array( 'savekey'	=> new \IPS\Helpers\Form\[Type]( ... ), ... );
	 * @endcode
	 */
	public function acpForm()
	{		
		$return = array(
			array( 'login_handler_oauth_settings', \IPS\Member::loggedIn()->language()->addToStack( static::getTitle() . '_info', FALSE, array( 'sprintf' => array( (string) $this->redirectionEndpoint() ) ) ) ),
			'consumer_key'		=> new \IPS\Helpers\Form\Text( 'oauth_consumer_key', ( isset( $this->settings['consumer_key'] ) ) ? $this->settings['consumer_key'] : '', TRUE ),
			'consumer_secret'	=> new \IPS\Helpers\Form\Text( 'oauth_consumer_secret', ( isset( $this->settings['consumer_secret'] ) ) ? $this->settings['consumer_secret'] : '', TRUE ),
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
		), 'disabled' => $emailChangesDisabled  ), NULL, NULL, NULL, 'login_update_email_changes_inc_optional' );
		
		return $return;
	}
		
	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\LogicException
	 */
	public function testSettings()
	{
		try
		{
			$response = $this->_sendRequest( 'get', $this->tokenRequestEndpoint() );
		}
		catch ( \Exception $e )
		{
			throw new \LogicException( \IPS\Member::loggedIn()->language()->addToStack( 'oauth1_setup_error', FALSE, array( 'sprintf' => array( $e->getMessage() ) ) ) );
		}
		
		try
		{
			$response->decodeQueryString('oauth_token');
		}
		catch ( \Exception $e )
		{
			throw new \LogicException( \IPS\Member::loggedIn()->language()->addToStack( 'oauth1_setup_error', FALSE, array( 'sprintf' => array( (string) $response ) ) ) );
		}
	}
	
	/* !Authentication */
	
	use \IPS\Login\Handler\ButtonHandler;
	
	/**
	 * Authenticate
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticateButton( \IPS\Login $login )
	{
		if ( isset( \IPS\Request::i()->denied ) )
		{
			return NULL;
		}
		elseif ( isset( \IPS\Request::i()->oauth_token ) )
		{
			return $this->_handleAuthorizationResponse( $login );
		}
		else
		{		
			$this->_redirectToAuthorizationEndpoint( $login );
		}
	}
	
	/**
	 * Redirect to Resource Owner Authorization Endpoint
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	void
	 */
	protected function _redirectToAuthorizationEndpoint( \IPS\Login $login )
	{
		$callback = $this->redirectionEndpoint()->setQueryString( 'state' , $this->id . '-' . base64_encode( $login->url ) . '-' . \IPS\Session::i()->csrfKey . '-' . \IPS\Request::i()->ref );
		
		try
		{
			$response = $this->_sendRequest( 'get', $this->tokenRequestEndpoint(), array( 'oauth_callback' => (string) $callback ) )->decodeQueryString('oauth_token');
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'twitter' );
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		\IPS\Output::i()->redirect( $this->authorizationEndpoint( $login )->setQueryString( 'oauth_token', $response['oauth_token'] ) );
	}
	
	/**
	 * Handle authorization response
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	protected function _handleAuthorizationResponse( \IPS\Login $login )
	{		
		/* Authenticate */
		try
		{
			$response = $this->_sendRequest( 'post', $this->accessTokenEndpoint(), array( 'oauth_verifier' => \IPS\Request::i()->oauth_verifier ), \IPS\Request::i()->oauth_token )->decodeQueryString('oauth_token');
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'twitter' );
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
						
		/* Get user id */
		try
		{
			$userId = $this->authenticatedUserId( $response['oauth_token'], $response['oauth_token_secret'] );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'oauth' );
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
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
			
			/* Otherwise, update our token... */
			\IPS\Db::i()->update( 'core_login_links', array(
				'token_access_token'	=> $response['oauth_token'],
				'token_secret'			=> $response['oauth_token_secret'],
			), array( 'token_login_method=? AND token_member=?', $this->id, $oauthAccess['token_member'] ) );
			
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
				$name = $this->authenticatedUserName( $response['oauth_token'], $response['oauth_token_secret'] );
			}
			catch ( \Exception $e ) {}
			$email = NULL;
			try
			{
				$email = $this->authenticatedEmail( $response['oauth_token'], $response['oauth_token_secret'] );
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
				
				\IPS\Db::i()->insert( 'core_login_links', array(
					'token_login_method'	=> $this->id,
					'token_member'			=> $member->member_id,
					'token_identifier'		=> $userId,
					'token_linked'			=> 1,
					'token_access_token'	=> $response['oauth_token'],
					'token_secret'			=> $response['oauth_token_secret'],
				) );
				
				$member->logHistory( 'core', 'social_account', array(
					'service'		=> static::getTitle(),
					'handler'		=> $this->id,
					'account_id'	=> $this->userId( $member ),
					'account_name'	=> $this->userProfileName( $member ),
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
						\IPS\Db::i()->insert( 'core_login_links', array(
							'token_login_method'	=> $this->id,
							'token_member'			=> $exception->member->member_id,
							'token_identifier'		=> $userId,
							'token_linked'			=> 0,
							'token_access_token'	=> $response['oauth_token'],
							'token_secret'			=> $response['oauth_token_secret'],
						) );
					}
				}
				
				throw $exception;
			}
		}
	}
	
	/**
	 * Authorization Endpoint
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Http\Url
	 */
	abstract protected function authorizationEndpoint( \IPS\Login $login );
	
	/**
	 * Token Request Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	abstract protected function tokenRequestEndpoint();
	
	/**
	 * Access Token Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	abstract protected function accessTokenEndpoint();

	/**
	 * Redirection Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	public function redirectionEndpoint()
	{
		return \IPS\Http\Url::internal( 'oauth/callback/', 'none' );
	}
	
	/**
	 * Send Request
	 *
	 * @param	string			  $method			HTTP Method
	 * @param	\IPS\Http\Url	  $url				URL
	 * @param	array|string|NULL $params			Parameters
	 * @param	string			  $token			OAuth Token
	 * @param	array			  $otherParams		Other params to send obvs
	 * @param	string			  $mimeBoundary		Mime data to send (boundary => data )
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\Exception
	 */
	protected function _sendRequest( $method, $url, $params=array(), $token='', $secret='', $otherParams=array(), $mimeBoundary=array() )
	{		
		/* Generate the OAUTH Authorization Header */
		$OAuthAuthorization = array_merge( array(
			'oauth_consumer_key'	=> $this->settings['consumer_key'],
			'oauth_nonce'			=> md5( \IPS\Login::generateRandomString() ),
			'oauth_signature_method'=> 'HMAC-SHA1',
			'oauth_timestamp'		=> time(),
			'oauth_token'			=> $token,
			'oauth_version'			=> '1.0'
		) );
		
		$queryStringParams = array();
		foreach ( $params as $k => $v )
		{
			if ( mb_substr( $k, 0, 6 ) === 'oauth_' )
			{
				$OAuthAuthorization = array_merge( array( $k => $v ), $OAuthAuthorization );
				unset( $params[ $k ] );
			}
			elseif ( $method === 'get' )
			{
				$queryStringParams[ $k ] = $v;
			}
		}
		
		/* All keys sent in the signature must be in alphabetical order, that includes oAuth keys and user sent params */
		$allKeys = array_merge( $OAuthAuthorization, $params );
		ksort( $allKeys );
		
		$signatureBaseString = mb_strtoupper( $method ) . '&' . rawurlencode( (string) $url ) . '&' . rawurlencode( http_build_query( $allKeys, NULL, '&', PHP_QUERY_RFC3986 ) );	
		$signingKey = rawurlencode( $this->settings['consumer_secret'] ) . '&' . rawurlencode( $secret ?: $token );			
		$OAuthAuthorizationEncoded = array();
		foreach ( $OAuthAuthorization as $k => $v )
		{
			$OAuthAuthorizationEncoded[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
			
			if ( $k === 'oauth_nonce' )
			{
				$signature = base64_encode( hash_hmac( 'sha1', $signatureBaseString, $signingKey, TRUE ) );
				$OAuthAuthorizationEncoded[] = rawurlencode( 'oauth_signature' ) . '="' . rawurlencode( $signature ) . '"';
			}
		}
		$OAuthAuthorizationHeader = 'OAuth ' . implode( ', ', $OAuthAuthorizationEncoded );
		
		$headers = array( 'Authorization' => $OAuthAuthorizationHeader );
		
		/* Send the request */
		if ( ! \count( $mimeBoundary ) )
		{
			if ( $method === 'get' )
			{
				return $url->setQueryString( $queryStringParams )->request()->setHeaders( $headers )->get();
			}
			else
			{
				return $url->setQueryString( $queryStringParams )->request()->setHeaders( $headers )->$method( $params );
			}
		}
		else
		{
			$headers['Content-Type'] = 'multipart/form-data; boundary=' . $mimeBoundary[0];
			
			return $url->setQueryString( $queryStringParams )->request()->setHeaders( $headers )->$method( $mimeBoundary[1] );
		}
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
		if ( !( $link = $this->_link( $member ) ) )
		{
			throw new \IPS\Login\Exception( $error['message'], \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		return $this->authenticatedUserId( $link['token_access_token'], $link['token_secret'] );
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
		if ( !( $link = $this->_link( $member ) ) )
		{
			throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		return $this->authenticatedUserName( $link['token_access_token'], $link['token_secret'] );
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
		if ( !( $link = $this->_link( $member ) ) )
		{
			throw new \IPS\Login\Exception( $error['message'], \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		return $this->authenticatedEmail( $link['token_access_token'], $link['token_secret'] );
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
		if ( !isset( $this->settings['show_in_ucp'] ) )
		{
			return TRUE; // Default to showing
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
}