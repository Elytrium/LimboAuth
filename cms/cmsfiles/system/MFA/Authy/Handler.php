<?php
/**
 * @brief		Multi Factor Authentication Handler for Authy
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 March 2017
 */

namespace IPS\MFA\Authy;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Multi Factor Authentication Handler for Authy
 */
class _Handler extends \IPS\MFA\MFAHandler
{
	/**
	 * @brief	Key
	 */
	protected $key = 'authy';
	
	/* !Setup */
	
	/**
	 * Handler is enabled
	 *
	 * @return	bool
	 */
	public function isEnabled()
	{
		return \IPS\Settings::i()->authy_enabled;
	}
	
	/**
	 * Member *can* use this handler (even if they have not yet configured it)
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function memberCanUseHandler( \IPS\Member $member )
	{
		return \IPS\Settings::i()->authy_groups == '*' or $member->inGroup( explode( ',', \IPS\Settings::i()->authy_groups ) );
	}
	
	/**
	 * Member has configured this handler
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function memberHasConfiguredHandler( \IPS\Member $member )
	{
		return isset( $member->mfa_details['authy'] ) and $member->mfa_details['authy']['setup'];
	}
		
	/**
	 * Show a setup screen
	 *
	 * @param	\IPS\Member		$member						The member
	 * @param	bool			$showingMultipleHandlers	Set to TRUE if multiple options are being displayed
	 * @param	\IPS\Http\Url	$url						URL for page
	 * @return	string
	 */
	public function configurationScreen( \IPS\Member $member, $showingMultipleHandlers, \IPS\Http\Url $url )
	{
		$mfaDetails = $member->mfa_details;
		
		/* Starting again? */
		if ( isset( $mfaDetails['authy']['pendingId'] ) and isset( \IPS\Request::i()->_new ) )
		{
			unset( $mfaDetails['authy']['pendingId'] );
			$member->mfa_details = $mfaDetails;
			$member->save();
		}
				
		/* If we have already enterred our phone number, ask for the code */
		if ( isset( $mfaDetails['authy'] ) and isset( $mfaDetails['authy']['pendingId'] ) and !isset( $_SESSION['authyConfigureError'] ) )
		{	
			/* Asking for a text or call instead? */
			$availableMethods = explode( ',', \IPS\Settings::i()->authy_setup );
			if ( isset( \IPS\Request::i()->authy_method ) and $mfaDetails['authy']['setupMethod'] == 'authy' and \in_array( \IPS\Request::i()->authy_method, $availableMethods ) )
			{
				try
				{
					/* Send text or make call */
					if ( \IPS\Request::i()->authy_method == 'phone' )
					{
						static::totp( "call/{$mfaDetails['authy']['pendingId']}", 'get', array( 'force' => 'true' ) );
					}
					elseif ( \IPS\Request::i()->authy_method == 'sms' )
					{
						static::totp( "sms/{$mfaDetails['authy']['pendingId']}", 'get', array( 'force' => 'true' ) );
					}
					
					/* Update details */
					$mfaDetails['authy']['setupMethod'] = \IPS\Request::i()->authy_method;
					$member->mfa_details = $mfaDetails;
					$member->save();
				}
				catch ( \Exception $e )
				{
					\IPS\Log::log( $e, 'authy' );
					$_SESSION['authyAuthError'] = \IPS\Member::loggedIn()->isAdmin() ? $e->getMessage() : 'authy_error';
				}				
			}

			/* Display */
			return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authyAuthenticate( $mfaDetails['authy']['setupMethod'], TRUE, isset( $_SESSION['authyAuthError'] ) ? $_SESSION['authyAuthError'] : 'authy_error', TRUE, explode( ',', \IPS\Settings::i()->authy_setup ), NULL, $url );
		}
		else
		{
			/* If they have used their allowed attempts, make them wait */
			if ( isset( $mfaDetails['authy'] ) and isset( $mfaDetails['authy']['changeAttempts'] ) and $mfaDetails['authy']['changeAttempts'] >= \IPS\Settings::i()->authy_setup_tries )
			{
				$lockEndTime = $mfaDetails['authy']['lastChangeAttempt'] + ( \IPS\Settings::i()->authy_setup_lockout * 3600 );
				if ( $lockEndTime < time() )
				{
					$mfaDetails['authy']['changeAttempts'] = 0;
					$member->mfa_details = $mfaDetails;
					$member->save();
				}
				else
				{
					return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authySetupLockout( $showingMultipleHandlers, \IPS\DateTime::ts( $lockEndTime ) );
				}
			}
			
			/* Otherwise show the form */
			return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authySetup( isset( \IPS\Request::i()->countryCode ) ? \IPS\Request::i()->countryCode : \IPS\Helpers\Form\Address::calculateDefaultCountry(), isset( \IPS\Request::i()->phoneNumber ) ? \IPS\Request::i()->phoneNumber : '', $showingMultipleHandlers, explode( ',', \IPS\Settings::i()->authy_setup ), isset( $_SESSION['authyConfigureError'] ) ? $_SESSION['authyConfigureError'] : NULL );
		}
	}
	
	/**
	 * Submit configuration screen. Return TRUE if was accepted
	 *
	 * @param	\IPS\Member		$member	The member
	 * @return	bool
	 */
	public function configurationScreenSubmit( \IPS\Member $member )
	{
		$mfaDetails = $member->mfa_details;
		
		/* If we've enterred a code, verify it */
		if ( isset( $mfaDetails['authy'] ) and isset( $mfaDetails['authy']['pendingId'] ) and isset( \IPS\Request::i()->authy_auth_code ) )
		{
			$_SESSION['authyAuthError'] = NULL;
			try
			{
				$response = static::totp( "verify/" . preg_replace( '/[^A-Z0-9]/i', '', \IPS\Request::i()->authy_auth_code ) . "/{$mfaDetails['authy']['pendingId']}", 'get' );
				
				$mfaDetails['authy'] = array( 'id' => $mfaDetails['authy']['pendingId'], 'setup' => true );
				$member->mfa_details = $mfaDetails;
				$member->save();
				
				$member->logHistory( 'core', 'mfa', array( 'handler' => $this->key, 'enable' => TRUE ) );
				
				return true;
			}
			catch ( Exception $e )
			{
				if ( \in_array( $e->getCode(), array( Exception::TOKEN_REUSED, Exception::TOKEN_INVALID ) ) )
				{
					$_SESSION['authyAuthError'] = $e->getUserMessage();
				}
				else
				{
					\IPS\Log::log( $e, 'authy' );
					$_SESSION['authyAuthError'] = \IPS\Member::loggedIn()->isAdmin() ? $e->getMessage() : $e->getUserMessage();
				}
				return false;
			}
			catch ( \Exception $e )
			{
				\IPS\Log::log( $e, 'authy' );
				$_SESSION['authyAuthError'] = \IPS\Member::loggedIn()->isAdmin() ? $e->getMessage() : 'authy_error';
				return false;
			}
		}
		
		/* Otherwise we need to generate an ID */
		elseif ( \IPS\Request::i()->phoneNumber )
		{
			/* Do we need to wait a while? */
			if ( isset( $mfaDetails['authy'] ) and isset( $mfaDetails['authy']['changeAttempts'] ) and $mfaDetails['authy']['changeAttempts'] >= \IPS\Settings::i()->authy_setup_tries )
			{
				return false;
			}
			
			/* Call Authy */
			$availableMethods = explode( ',', \IPS\Settings::i()->authy_setup );
			$method = ( isset( \IPS\Request::i()->method ) and \in_array( \IPS\Request::i()->method, $availableMethods ) ) ? \IPS\Request::i()->method : array_shift( $availableMethods );
			$_SESSION['authyConfigureError'] = NULL;
			try
			{
				/* Create User */
				$data = array(
					'user'						=> array(
						'email'						=> $member->email,
						'cellphone'					=> \IPS\Request::i()->phoneNumber,
						'country_code'				=> explode( '-', \IPS\Request::i()->countryCode )[1]
					)
				);
				if ( \IPS\Settings::i()->authy_method != 'authy' )
				{
					$data['send_install_link_via_sms'] = false;
				}
				$response = static::totp( 'users/new', 'post', $data );
				if ( isset( $mfaDetails['authy']['id'] ) and $mfaDetails['authy']['id'] == $response['user']['id'] )
				{
					return true;
				}
				
				/* Send text message or make phone call */
				if ( $method == 'phone' )
				{
					static::totp( "call/{$response['user']['id']}", 'get', array( 'force' => 'true' ) );
				}
				elseif ( $method == 'sms' )
				{
					static::totp( "sms/{$response['user']['id']}", 'get', array( 'force' => 'true' ) );
				}
			}
			catch ( Exception $e )
			{
				if ( \in_array( $e->getCode(), array( Exception::USER_INVALID, Exception::PHONE_NUMBER_INVALID ) ) )
				{
					$_SESSION['authyConfigureError'] = $e->getUserMessage();
				}
				else
				{
					\IPS\Log::log( $e, 'authy' );
					$_SESSION['authyConfigureError'] = \IPS\Member::loggedIn()->isAdmin() ? $e->getMessage() : 'authy_error';
				}
				return false;
			}
			catch ( \Exception $e )
			{
				\IPS\Log::log( $e, 'authy' );
				$_SESSION['authyConfigureError'] = \IPS\Member::loggedIn()->isAdmin() ? $e->getMessage() : 'authy_error';
				return false;
			}
			
			/* Log the details */
			$mfaDetails['authy']['pendingId'] = $response['user']['id'];
			if ( !isset( $mfaDetails['authy']['changeAttempts'] ) )
			{
				$mfaDetails['authy']['changeAttempts'] = 1;
			}
			else
			{
				$mfaDetails['authy']['changeAttempts']++;
			}
			$mfaDetails['authy']['lastChangeAttempt'] = time();
			if ( !isset( $mfaDetails['authy']['setup'] ) )
			{
				$mfaDetails['authy']['setup'] = false;
			}
			$mfaDetails['authy']['setupMethod'] = $method;
			$member->mfa_details = $mfaDetails;
			$member->save();
			
			return false;
		}
		
		return false;
	}
	
	/* !Authentication */
	
	/**
	 * Get the form for a member to authenticate
	 *
	 * @param	\IPS\Member		$member		The member
	 * @param	\IPS\Http\Url	$url		URL for page
	 * @return	string
	 */
	public function authenticationScreen( \IPS\Member $member, \IPS\Http\Url $url )
	{
		$mfaDetails = $member->mfa_details;
		$availableMethods = explode( ',', \IPS\Settings::i()->authy_method );
		
		/* If we sent a code, but it was more than one minute ago, log a failure and reset */
		if ( isset( $mfaDetails['authy']['sent'] ) and $mfaDetails['authy']['sent']['time'] < ( time() - 60 ) )
		{
			unset( $mfaDetails['authy']['sent'] );
			$member->mfa_details = $mfaDetails;
			$member->failed_mfa_attempts++;
			$member->save();
		}
				
		/* If Authy app is one of the available options... */
		if ( \in_array( 'authy', $availableMethods ) )
		{
			/* Are we getting a onetouch status? */
			if ( \IPS\Request::i()->onetouchCheck and \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'status' => \intval( $this->_onetouchCheck( $member, \IPS\Request::i()->onetouchCheck ) ) ) );
			}
			
			/* If it is not the only option... */
			if ( \count( $availableMethods ) > 1 )
			{
				/* If they have asked for a text/call instead, do that */
				if ( !isset( $mfaDetails['authy']['sent'] ) and isset( \IPS\Request::i()->authy_method ) and \in_array( \IPS\Request::i()->authy_method, $availableMethods ) )
				{
					try
					{
						/* Send text or make call */
						if ( \IPS\Request::i()->authy_method == 'phone' )
						{
							static::totp( "call/{$mfaDetails['authy']['id']}", 'get', array( 'force' => 'true' ) );
						}
						elseif ( \IPS\Request::i()->authy_method == 'sms' )
						{
							static::totp( "sms/{$mfaDetails['authy']['id']}", 'get', array( 'force' => 'true' ) );
						}
						
						/* Update details */
						$mfaDetails['authy']['sent'] = array( 'method' => \IPS\Request::i()->authy_method, 'time' => time() );
						$member->mfa_details = $mfaDetails;
						$member->save();
					}
					catch ( \Exception $e )
					{
						\IPS\Log::log( $e, 'authy' );
						$_SESSION['authyAuthError'] = \IPS\Member::loggedIn()->isAdmin() ? $e->getMessage() : 'authy_error';
					}				
				}
				if ( isset( $mfaDetails['authy']['sent'] ) )
				{
					return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authyAuthenticate( $mfaDetails['authy']['sent']['method'], TRUE, isset( $_SESSION['authyAuthError'] ) ? $_SESSION['authyAuthError'] : 'authy_error', FALSE, $availableMethods, NULL, $url );
				}
				
				/* Otherwise, check if they have the app installed. If they do, show the Authy authenticate page */
				try
				{
					$userDetails = static::totp("users/{$mfaDetails['authy']['id']}/status");
					$userHasAuthyApp = FALSE;
					foreach ( $userDetails['status']['devices'] as $device )
					{
						if ( $device != 'sms' )
						{
							$userHasAuthyApp = TRUE;
						}
					}
					
					if ( $userHasAuthyApp )
					{						
						return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authyAuthenticate( 'authy', TRUE, isset( $_SESSION['authyAuthError'] ) ? $_SESSION['authyAuthError'] : 'authy_error', FALSE, $availableMethods, $this->_onetouchInit( $member, $url ), $url );
					}
				}
				catch ( \Exception $e ) { }
			}
			
			/* If it is the only option, show it anyway */
			else
			{
				return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authyAuthenticate( 'authy', TRUE, isset( $_SESSION['authyAuthError'] ) ? $_SESSION['authyAuthError'] : 'authy_error', FALSE, $availableMethods, $this->_onetouchInit( $member, $url ), $url );
			}
		}
		
		/* If text message is the only available option, or we have chosen that option, do that... */
		if ( \in_array( 'sms', $availableMethods ) and ( \count( $availableMethods ) == 1 ) or ( isset( $mfaDetails['authy']['sent'] ) and $mfaDetails['authy']['sent']['method'] == 'sms' ) or ( isset( \IPS\Request::i()->authy_method ) and \IPS\Request::i()->authy_method == 'sms' ) )
		{
			/* Send the text if we haven't already */
			if ( !isset( $mfaDetails['authy']['sent'] ) )
			{
				try
				{
					static::totp( "sms/{$mfaDetails['authy']['id']}", 'get', array( 'force' => 'true' ) );
				}
				catch ( \Exception $e )
				{
					\IPS\Log::log( $e, 'authy' );
					return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authyError( $e->getMessage() );
				}
				
				$mfaDetails['authy']['sent'] = array( 'method' => 'sms', 'time' => time() );
				$member->mfa_details = $mfaDetails;
				$member->save();
			}
			
			/* Show screen */
			return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authyAuthenticate( 'sms', TRUE, isset( $_SESSION['authyAuthError'] ) ? $_SESSION['authyAuthError'] : 'authy_error', FALSE, $availableMethods, NULL, $url );
		}
		
		/* If we have confirmed the phone call, do that now */
		if ( ( isset( $mfaDetails['authy']['sent'] ) and $mfaDetails['authy']['sent']['method'] == 'phone' ) or ( isset( \IPS\Request::i()->authy_method ) and \IPS\Request::i()->authy_method == 'phone' ) )
		{
			/* Send the text if we haven't already */
			if ( !isset( $mfaDetails['authy']['sent'] ) )
			{
				try
				{
					static::totp( "call/{$mfaDetails['authy']['id']}", 'get', array( 'force' => 'true' ) );
				}
				catch ( \Exception $e )
				{
					\IPS\Log::log( $e, 'authy' );
					return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authyError( $e->getMessage() );
				}
				
				$mfaDetails['authy']['sent'] = array( 'method' => 'call', 'time' => time() );
				$member->mfa_details = $mfaDetails;
				$member->save();
			}
			
			/* Show screen */
			return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authyAuthenticate( 'phone', TRUE, isset( $_SESSION['authyAuthError'] ) ? $_SESSION['authyAuthError'] : 'authy_error', FALSE, $availableMethods, NULL, $url );
		}
		
		/* Otherwise we're going to show a screen */
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->authyAuthenticate( \count( $availableMethods ) == 1 ? 'phone' : 'choose', FALSE, isset( $_SESSION['authyAuthError'] ) ? $_SESSION['authyAuthError'] : 'authy_error', FALSE, $availableMethods, NULL, $url );
	}
	
	/**
	 * If enabled, initiate a OneTouch request and get the ID
	 *
	 * @param	\IPS\Member		$member		The member
	 * @param	\IPS\Http\Url	$url		URL for page
	 * @return	string|null
	 */
	protected function _onetouchInit( \IPS\Member $member, \IPS\Http\Url $url )
	{		
		if ( \IPS\Settings::i()->authy_onetouch )
		{
			$mfaDetails = $member->mfa_details;

			if ( isset( $mfaDetails['onetouch'] ) and $mfaDetails['onetouch']['time'] > ( time() - 30 ) )
			{
				$response = static::onetouch( "approval_requests/" . preg_replace( '/[^A-Z0-9\-]/i', '', $mfaDetails['onetouch']['id'] ), 'get' );
				
				if ( $response['approval_request']['status'] === 'pending' )
				{
					return $mfaDetails['onetouch']['id'];
				}
			}
			
			try
			{
				$response = static::onetouch( "users/{$mfaDetails['authy']['id']}/approval_requests", 'post', array(
					'message'			=> $member->language()->get('authy_onetouch_message'),
					'seconds_to_expire'	=> 300
				) );
				
				$mfaDetails['onetouch'] = array( 'id' => $response['approval_request']['uuid'], 'time' => time() );
				$member->mfa_details = $mfaDetails;
				$member->save();
			}
			catch ( \Exception $e )
			{
				return NULL;
			}
			
			return $mfaDetails['onetouch']['id'];
		}
		return NULL;
	}
	
	/**
	 * Check the status of a onetouch request
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string		$id		The onetouch request ID
	 * @return	string|null
	 */
	protected function _onetouchCheck( \IPS\Member $member, $id )
	{
		if ( \IPS\Settings::i()->authy_onetouch )
		{
			$mfaDetails = $member->mfa_details;
			
			try
			{				
				$response = static::onetouch( "approval_requests/" . preg_replace( '/[^A-Z0-9\-]/i', '', $id ), 'get' );
				return $response['approval_request']['status'] === 'approved';
			}
			catch ( \Exception $e ) {}
		}
		return FALSE;
	}
	
	/**
	 * Submit authentication screen. Return TRUE if was accepted
	 *
	 * @param	\IPS\Member		$member	The member
	 * @return	bool
	 */
	public function authenticationScreenSubmit( \IPS\Member $member )
	{
		$mfaDetails = $member->mfa_details;

		$_SESSION['authyAuthError'] = NULL;
		
		try
		{
			if ( isset( \IPS\Request::i()->authy_auth_code ) )
			{
				$response = static::totp( "verify/" . preg_replace( '/[^A-Z0-9]/i', '', \IPS\Request::i()->authy_auth_code ) . "/{$mfaDetails['authy']['id']}", 'get' );
				$mfaDetails['authy'] = array( 'id' => $mfaDetails['authy']['id'], 'setup' => true );
			}
			elseif ( isset( \IPS\Request::i()->onetouch ) )
			{
				return $this->_onetouchCheck( $member, \IPS\Request::i()->onetouch );
			}
			else
			{
				return false;
			}
			$member->mfa_details = $mfaDetails;
			$member->save();
			
			return true;
		}
		catch ( Exception $e )
		{
			if ( \in_array( $e->getCode(), array( Exception::TOKEN_REUSED, Exception::TOKEN_INVALID ) ) )
			{
				$_SESSION['authyAuthError'] = $e->getUserMessage();
			}
			else
			{
				\IPS\Log::log( $e, 'authy' );
				$_SESSION['authyAuthError'] = \IPS\Member::loggedIn()->isAdmin() ? $e->getMessage() : $e->getUserMessage();
			}
			return false;
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'authy' );
			$_SESSION['authyAuthError'] = \IPS\Member::loggedIn()->isAdmin() ? $e->getMessage() : 'authy_error';
			return false;
		}
	}
	
	/* !ACP */
	
	/**
	 * Toggle
	 *
	 * @param	bool	$enabled	On/Off
	 * @return	bool
	 */
	public function toggle( $enabled )
	{
		/* This handler is deprecated, so if it's already disabled, don't allow it to be re-enabled */
		if( !$this->isEnabled() )
		{
			return FALSE;
		}

		if ( $enabled )
		{
			static::verifyApiKey( \IPS\Settings::i()->authy_key );
		}
		
		\IPS\Settings::i()->changeValues( array( 'authy_enabled' => $enabled ) );
	}
	
	/**
	 * ACP Settings
	 *
	 * @return	string
	 */
	public function acpSettings()
	{
		if( !$this->isEnabled() )
		{
			\IPS\Output::i()->error( 'authy_deprecated_message', '2C345/3' );
		}

		$form = new \IPS\Helpers\Form;
		
		$form->add( new \IPS\Helpers\Form\Text( 'authy_key', \IPS\Settings::i()->authy_key, TRUE, array(), function( $val ) {
			$details = \IPS\MFA\Authy\Handler::verifyApiKey( $val );
			if ( !$details['app']['sms_enabled'] and ( array_key_exists( 'sms', \IPS\Request::i()->authy_setup ) or array_key_exists( 'sms', \IPS\Request::i()->authy_method ) ) )
			{
				throw new \DomainException('authy_key_no_sms');
			}
			if ( !$details['app']['phone_calls_enabled'] and ( array_key_exists( 'phone', \IPS\Request::i()->authy_setup ) or array_key_exists( 'phone', \IPS\Request::i()->authy_method ) ) )
			{
				throw new \DomainException('authy_key_no_sms');
			}
			if ( !$details['app']['onetouch_enabled'] and \IPS\Request::i()->authy_onetouch )
			{
				throw new \DomainException('authy_key_no_onetouch');
			}
		}, NULL, \IPS\Member::loggedIn()->language()->addToStack('authy_key_suffix') ) );
		
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'authy_groups', \IPS\Settings::i()->authy_groups == '*' ? '*' : explode( ',', \IPS\Settings::i()->authy_groups ), FALSE, array(
			'multiple'		=> TRUE,
			'options'		=> array_combine( array_keys( \IPS\Member\Group::groups() ), array_map( function( $_group ) { return (string) $_group; }, \IPS\Member\Group::groups() ) ),
			'unlimited'		=> '*',
			'unlimitedLang'	=> 'everyone',
			'impliedUnlimited' => TRUE
		) ) );
				
		$form->addHeader('authy_setup_header');
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'authy_setup', explode( ',', \IPS\Settings::i()->authy_setup ), TRUE, array( 'options' => array(
			'authy'			=> 'authy_method_authy',
			'sms'			=> 'authy_method_sms',
			'phone'			=> 'authy_method_phone',
		) ) ) );
		$form->add( new \IPS\Helpers\Form\Custom( 'authy_setup_protection', array( \IPS\Settings::i()->authy_setup_tries, \IPS\Settings::i()->authy_setup_lockout ), FALSE, array(
			'getHtml' => function( $field ) {
				return \IPS\Theme::i()->getTemplate('settings')->authySetupProtection( $field->value );
			}
		) ) );
		
		$form->addHeader('authy_authenticate_header');
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'authy_method', explode( ',', \IPS\Settings::i()->authy_method ), TRUE, array(
			'options' => array(
				'authy'			=> 'authy_method_authy',
				'sms'			=> 'authy_method_sms',
				'phone'			=> 'authy_method_phone',
			),
			'toggles' => array(
				'authy'			=> array( 'authy_onetouch' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'authy_onetouch', \IPS\Settings::i()->authy_onetouch, TRUE, array(
			'options' => array(
				'1'			=> 'authy_onetouch_on',
				'0'			=> 'authy_onetouch_off',
			),
		), NULL, NULL, NULL, 'authy_onetouch' ) );
		
		if ( $values = $form->values() )
		{
			$values['authy_groups'] = ( $values['authy_groups'] == '*' ) ? '*' : implode( ',', $values['authy_groups'] );
			$values['authy_setup'] = isset( $values['authy_setup'] ) ? implode( ',', $values['authy_setup'] ) : '';
			$values['authy_setup_tries'] = $values['authy_setup_protection'][0];
			$values['authy_setup_lockout'] = $values['authy_setup_protection'][1];
			unset( $values['authy_setup_protection'] );
			$values['authy_method'] = isset( $values['authy_method'] ) ? implode( ',', $values['authy_method'] ) : '';
			$form->saveAsSettings( $values );	

			\IPS\Session::i()->log( 'acplogs__mfa_handler_enabled', array( "mfa_authy_title" => TRUE ) );		
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=mfa' ), 'saved' );
		}
		
		return (string) $form;
	}
	
	
	
	/* !Misc */
	
	/**
	 * If member has configured this handler, disable it
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function disableHandlerForMember( \IPS\Member $member )
	{
		$mfaDetails = $member->mfa_details;
		
		if ( isset( $mfaDetails['authy']['id'] ) )
		{
			try
			{
				static::totp( "users/{$mfaDetails['authy']['id']}/delete", 'post', array(
					'user_ip'	=> \IPS\Request::i()->ipAddress()
				) );
			}
			catch ( \Exception $e ) { }
		}
		
		unset( $mfaDetails['authy'] );
		$member->mfa_details = $mfaDetails;
		$member->save();

		/* Log MFA Disable */
		$member->logHistory( 'core', 'mfa', array( 'handler' => $this->key, 'enable' => FALSE ) );
	}
	
	/**
	 * Get title for UCP
	 *
	 * @return	string
	 */
	public function ucpTitle()
	{
		$availableMethods = explode( ',', \IPS\Settings::i()->authy_method );
		
		if ( \in_array( 'authy', $availableMethods ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('mfa_authy_title');
		}
		elseif ( \in_array( 'sms', $availableMethods ) and \count( $availableMethods ) == 1 )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('mfa_sms_title');
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack('mfa_phone_title');
		}
	}
	
	/**
	 * Get description for UCP
	 *
	 * @return	string
	 */
	public function ucpDesc()
	{
		$availableMethods = explode( ',', \IPS\Settings::i()->authy_method );
		
		if ( \in_array( 'authy', $availableMethods ) )
		{
			if ( \count( $availableMethods ) == 1 )
			{
				return \IPS\Member::loggedIn()->language()->addToStack('mfa_authy_only_desc_user');
			}
			else
			{
				return \IPS\Member::loggedIn()->language()->addToStack('mfa_authy_mixed_desc_user');
			}
		}
		elseif ( \in_array( 'sms', $availableMethods ) and \count( $availableMethods ) == 1 )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('mfa_sms_desc_user');
		}
		elseif ( \in_array( 'phone', $availableMethods ) and \count( $availableMethods ) == 1 )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('mfa_phone_desc_user');
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack('mfa_sms_or_phone_desc_user');
		}
	}
	
	/**
	 * Get label for recovery button
	 *
	 * @return	string
	 */
	public function recoveryButton()
	{
		$availableMethods = explode( ',', \IPS\Settings::i()->authy_method );
		
		if ( \in_array( 'authy', $availableMethods ) and \count( $availableMethods ) == 1 )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('mfa_authy_recovery');
		}
		elseif ( \in_array( 'sms', $availableMethods ) and \count( $availableMethods ) == 1 )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('mfa_sms_recovery');
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack('mfa_phone_recovery');
		}
	}
	
	/* !Helper Methods */
	
	/**
	 * Make TOTP API Call
	 *
	 * @param	string	$endpoint	The endpoint to call
	 * @param	string	$method		'get' or 'post'
	 * @param	array	$data		Post data or additional query string parameters
	 * @return	array
	 */
	public static function totp( $endpoint, $method='get', $data=NULL )
	{
		return static::_api( "protected/json/{$endpoint}", $method, $data );
	}
	
	/**
	 * Make OneTouch API Call
	 *
	 * @param	string	$endpoint	The endpoint to call
	 * @param	string	$method		'get' or 'post'
	 * @param	array	$data		Post data or additional query string parameters
	 * @return	array
	 */
	public static function onetouch( $endpoint, $method='get', $data=NULL )
	{
		return static::_api( "onetouch/json/{$endpoint}", $method, $data );
	}
	
	/**
	 * Make API Call
	 *
	 * @param	string	$endpoint	The endpoint to call
	 * @param	string	$method		'get' or 'post'
	 * @param	array	$data		Post data or additional query string parameters
	 * @return	array
	 */
	protected static function _api( $endpoint, $method='get', $data=NULL )
	{
		$url = \IPS\Http\Url::external("https://api.authy.com/{$endpoint}")->setQueryString( 'api_key', \IPS\Settings::i()->authy_key );
		
		if ( $method == 'get' )
		{
			$response = $url->setQueryString( $data )->request()->get();
		}
		else
		{
			$response = $url->request()->post( $data );
		}
		
		$response = $response->decodeJson();
		
		if ( !$response['success'] )
		{
			throw new Exception( $response['message'], $response['error_code'] );
		}
		
		return $response;
	}
	
	/**
	 * Verify an Authy API Key
	 *
	 * @param	string	$val	The API key submitted
	 * @return	array
	 * @throws	\DomainException
	 */
	public static function verifyApiKey( $val )
	{
		try
		{
			return \IPS\Http\Url::external("https://api.authy.com/protected/json/app/details")->setQueryString( 'api_key', $val )->request()->get()->decodeJson();
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			throw new \DomainException( $e->getMessage() );
		}
		if ( !$response['success'] )
		{
			throw new \DomainException( $response['message'] );
		}
	}
	
}