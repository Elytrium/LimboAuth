<?php
/**
 * @brief		User CP Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Jun 2013
 */
 
namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * User CP Controller
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief These properties are used to specify datalayer context properties.
	 *
	 */
	public static $dataLayerContext = array(
		'community_area' =>  [ 'value' => 'settings', 'odkUpdate' => true]
	);

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Only logged in members */
		if ( !\IPS\Member::loggedIn()->member_id and !\in_array( \IPS\Request::i()->do, array( 'mfarecovery', 'mfarecoveryvalidate', 'invite' ) ) )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C122/1', 403, '' );
		}
		
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_system.js', 'core' ) );

		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		parent::execute();
	}

	/**
	 * Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Work out output */
		$area = \IPS\Request::i()->area ?: 'overview';
		$methodName = "_{$area}";
		if ( method_exists( $this, $methodName ) )
		{
			$output = $this->$methodName();
		}
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('settings');
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('settings') );
		if ( !\IPS\Request::i()->isAjax() )
		{
			if ( \IPS\Request::i()->service )
			{
				$area = "{$area}_" . \IPS\Request::i()->service;
			}
            
            \IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/settings.css' ) );
            
            if ( \IPS\Theme::i()->settings['responsive'] )
            {
                \IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/settings_responsive.css' ) );
            }
            
            if ( $output )
            {
				\IPS\Output::i()->output .= $this->_wrapOutputInTemplate( $area, $output );
			}
		}
		elseif ( $output )
		{
			\IPS\Output::i()->output .= $output;
		}
	}
	
	/**
	 * Wrap output in template
	 *
	 * @param	string	$area	Active area
	 * @param	string	$output	Output
	 * @return	string
	 */
	protected function _wrapOutputInTemplate( $area, $output )
	{
		/* What can we do? */
		$canChangePassword = ( \IPS\Settings::i()->allow_password_changes == 'redirect' );
		$canConfigureMfa = FALSE;
		if ( \IPS\Settings::i()->allow_password_changes == 'normal' )
		{
			foreach ( \IPS\Login::methods() as $method )
			{
				if ( $method->canChangePassword( \IPS\Member::loggedIn() ) )
				{
					$canChangePassword = TRUE;
					break;
				}
			}
		}	

		foreach ( \IPS\MFA\MFAHandler::handlers() as $handler )
		{
			if ( $handler->isEnabled() and $handler->memberCanUseHandler( \IPS\Member::loggedIn() ) )
			{
				$canConfigureMfa = TRUE;
				break;
			}
		}
		
		/* Any value other than zero means the user is either forced anonymous, or cannot be anonymous at all. */
		if ( !\IPS\Member::loggedIn()->group['g_hide_online_list'] )
		{
			$canConfigureMfa = TRUE;
		}

		$canChangeSignature = (bool) \IPS\Member::loggedIn()->canEditSignature();
				
		/* Add login handlers */
		$loginMethods = \IPS\Login::methods();
		
		/* Show our own oauth clients? */
		$showApps = (bool) \IPS\Db::i()->select( 'COUNT(*)', 'core_oauth_clients', array( array( 'oauth_enabled=1 AND oauth_ucp=1' ) ) )->first();
		/* Return */
		return \IPS\Theme::i()->getTemplate( 'system' )->settings( $area, $output, ( \IPS\Settings::i()->allow_email_changes != 'disabled' ), $canChangePassword, \IPS\Member::loggedIn()->group['g_dname_changes'], $canChangeSignature, $loginMethods, $canConfigureMfa, $showApps );
	}
	
	/**
	 * Overview
	 *
	 * @return	string
	 */
	protected function _overview()
	{
		$loginMethods = array();
		$canChangePassword = FALSE;
		
		foreach ( \IPS\Login::methods() as $method )
		{
			if ( $method->showInUcp( \IPS\Member::loggedIn() ) )
			{
				if ( $method->canProcess( \IPS\Member::loggedIn() ) )
				{
					try
					{
						$name = $method->userProfileName( \IPS\Member::loggedIn() );
						
						$loginMethods[ $method->id ] = array(
							'title'	=> $method->_title,
							'blurb'	=> $name ? \IPS\Member::loggedIn()->language()->addToStack( 'profilesync_headline', FALSE, array( 'sprintf' => array( $name ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'profilesync_signed_in' ),
							'icon'	=> $method->userProfilePhoto( \IPS\Member::loggedIn() )
						);
					}
					catch ( \IPS\Login\Exception $e )
					{
						$loginMethods[ $method->id ] = array( 'title' => $method->_title, 'blurb' => \IPS\Member::loggedIn()->language()->addToStack('profilesync_reauth_needed') );
					}
				}
				else
				{
					$loginMethods[ $method->id ] = array( 'title' => $method->_title, 'blurb' => \IPS\Member::loggedIn()->language()->addToStack('profilesync_not_synced') );
				}
			}
			
			
			if ( $method->canChangePassword( \IPS\Member::loggedIn() ) )
			{
				$canChangePassword = TRUE;
			}
		}

		if( \IPS\Settings::i()->allow_password_changes == 'disabled' )
		{
			$canChangePassword = FALSE;
		}	
				
		return \IPS\Theme::i()->getTemplate( 'system' )->settingsOverview( $loginMethods, $canChangePassword );
	}
	
	/**
	 * Email
	 *
	 * @return	string
	 */
	protected function _email()
	{
		if ( \IPS\Settings::i()->allow_email_changes == 'redirect' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::external( \IPS\Settings::i()->allow_email_changes_target ) );
		}

		if( \IPS\Settings::i()->allow_email_changes != 'normal' )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C122/U', 403, '' );
		}
		
		if( \IPS\Member::loggedIn()->isAdmin() )
		{
			return \IPS\Theme::i()->getTemplate( 'system' )->settingsEmail();
		}
				
		$mfaOutput = \IPS\MFA\MFAHandler::accessToArea( 'core', 'EmailChange', \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=email', 'front', 'settings_email' ) );
		if ( $mfaOutput )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=email', 'front', 'settings_email' ) );
			}
			\IPS\Output::i()->output = $mfaOutput;
		}
		
		/* Do we have any pending validation emails? */
		try
		{
			$pending = \IPS\Db::i()->select( '*', 'core_validating', array( 'member_id=? AND email_chg=1', \IPS\Member::loggedIn()->member_id ), 'entry_date DESC' )->first();
		}
		catch( \UnderflowException $e )
		{
			$pending = null;
		}
		
		/* Build the form */
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_collapseTablet';
		$currentEmail = htmlspecialchars( \IPS\Member::loggedIn()->email, ENT_DISALLOWED, 'UTF-8', FALSE );
		$form->addDummy( 'current_email', \IPS\Member::loggedIn()->members_bitoptions["email_messages_bounce"] ? \IPS\Theme::i()->getTemplate( 'global', 'core' )->memberEmailBlockedMessage( $currentEmail ) : $currentEmail );
		$form->add( new \IPS\Helpers\Form\Email(
			'new_email',
			'',
			TRUE,
			array( 'accountEmail' => \IPS\Member::loggedIn() )
		) );
		
		/* Handle submissions */
		$values = NULL;
		if ( !$mfaOutput and $values = $form->values() )
		{
			$_SESSION['newEmail'] = $values['new_email'];
		}
		if ( isset( $_SESSION['newEmail'] ) )
		{
			/* Reauthenticate */
			$login = new \IPS\Login( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=email', 'front', 'settings_email' ), \IPS\Login::LOGIN_REAUTHENTICATE );
			
			/* After re-authenticating, change the email */
			$error = NULL;
			try
			{
				if ( $success = $login->authenticate() )
				{
					/* Disable syncing */
					$profileSync = \IPS\Member::loggedIn()->profilesync;
					if ( isset( $profileSync['email'] ) )
					{
						unset( $profileSync['email'] );
						\IPS\Member::loggedIn()->profilesync = $profileSync;
						\IPS\Member::loggedIn()->save();
					}
							
					/* Change the email */
					$oldEmail = \IPS\Member::loggedIn()->email;
					\IPS\Member::loggedIn()->email = $_SESSION['newEmail'];
					\IPS\Member::loggedIn()->save();
					foreach ( \IPS\Login::methods() as $method )
					{
						try
						{
							$method->changeEmail( \IPS\Member::loggedIn(), $oldEmail, $_SESSION['newEmail'] );
						}
						catch( \BadMethodCallException $e ){}
					}
					\IPS\Member::loggedIn()->logHistory( 'core', 'email_change', array( 'old' => $oldEmail, 'new' => \IPS\Member::loggedIn()->email, 'by' => 'manual' ) );
					
					/* Invalidate sessions except this one */
					\IPS\Member::loggedIn()->invalidateSessionsAndLogins( \IPS\Session::i()->id );
					if( isset( \IPS\Request::i()->cookie['login_key'] ) )
					{
						\IPS\Member\Device::loadOrCreate( \IPS\Member::loggedIn() )->updateAfterAuthentication( TRUE );
					}
								
					/* Send a validation email if we need to */
					if ( \IPS\Settings::i()->reg_auth_type == 'user' or \IPS\Settings::i()->reg_auth_type == 'admin_user' )
					{
						unset( $_SESSION['newEmail'] );
						
						$vid = \IPS\Login::generateRandomString();
						
						\IPS\Db::i()->insert( 'core_validating', array(
							'vid'			=> $vid,
							'member_id'		=> \IPS\Member::loggedIn()->member_id,
							'entry_date'	=> time(),
							'email_chg'		=> TRUE,
							'ip_address'	=> \IPS\Request::i()->ipAddress(),
							'prev_email'	=> $oldEmail,
							'email_sent'	=> time(),
						) );
		
						\IPS\Member::loggedIn()->members_bitoptions['validating'] = TRUE;
						\IPS\Member::loggedIn()->save();
						
						\IPS\Email::buildFromTemplate( 'core', 'email_change', array( \IPS\Member::loggedIn(), $vid ), \IPS\Email::TYPE_TRANSACTIONAL )->send( \IPS\Member::loggedIn() );
									
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ) );
					}
					
					/* Or just redirect */
					else
					{
						\IPS\Member::loggedIn()->memberSync( 'onEmailChange', array( $_SESSION['newEmail'], $oldEmail ) );
						unset( $_SESSION['newEmail'] );

						/* Send a confirmation email */
						\IPS\Email::buildFromTemplate( 'core', 'email_address_changed', array( \IPS\Member::loggedIn(), $oldEmail ), \IPS\Email::TYPE_TRANSACTIONAL )->send( $oldEmail, array(), array(), NULL, NULL, array( 'Reply-To' => \IPS\Settings::i()->email_in ) );
		
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=email', 'front', 'settings' ), 'email_changed' );
					}
				}
			}
			catch ( \IPS\Login\Exception $e )
			{
				$error = $e->getMessage();
			}
			
			/* Otherwise show the reauthenticate form */
			return \IPS\Theme::i()->getTemplate( 'system' )->settingsEmail( NULL, $login, $error );
			
		}
		return \IPS\Theme::i()->getTemplate( 'system' )->settingsEmail( $form );
	}
	
	/**
	 * Password
	 *
	 * @return	string
	 */
	protected function _password()
	{
		if ( \IPS\Settings::i()->allow_password_changes == 'redirect' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::external( \IPS\Settings::i()->allow_password_changes_target ) );
		}

		if( \IPS\Settings::i()->allow_password_changes != 'normal' )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C122/T', 403, '' );
		}

		$canChangePassword = FALSE;

		foreach ( \IPS\Login::methods() as $method )
		{
			if ( $method->canChangePassword( \IPS\Member::loggedIn() ) )
			{
				$canChangePassword = TRUE;
				break;
			}
		}

		if( !$canChangePassword )
		{
			\IPS\Output::i()->error( 'no_module_permission', '3C122/W', 403, '' );
		}
		
		if( \IPS\Member::loggedIn()->isAdmin() )
		{
			return \IPS\Theme::i()->getTemplate( 'system' )->settingsPassword();
		}
		
		$mfaOutput = \IPS\MFA\MFAHandler::accessToArea( 'core', 'PasswordChange', \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=password', 'front', 'settings_password' ) );
		if ( $mfaOutput )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=password', 'front', 'settings_password' ) );
			}
			\IPS\Output::i()->output = $mfaOutput;
		}
				
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_collapseTablet';
		if ( !\IPS\Member::loggedIn()->members_bitoptions['password_reset_forced'] )
		{
			$form->add( new \IPS\Helpers\Form\Password( 'current_password', '', TRUE, array( 'protect' => TRUE, 'validateFor' => \IPS\Member::loggedIn(), 'bypassProfanity' => \IPS\Helpers\Form\Text::BYPASS_PROFANITY_ALL, 'htmlAutocomplete' => "current-password" ) ) );
		}
		$form->add( new \IPS\Helpers\Form\Password( 'new_password', '', TRUE, array( 'protect' => TRUE, 'showMeter' => \IPS\Settings::i()->password_strength_meter, 'checkStrength' => TRUE, 'strengthMember' => \IPS\Member::loggedIn(), 'bypassProfanity' => \IPS\Helpers\Form\Text::BYPASS_PROFANITY_ALL, 'htmlAutocomplete' => "new-password" ) ) );
		$form->add( new \IPS\Helpers\Form\Password( 'confirm_new_password', '', TRUE, array( 'protect' => TRUE, 'confirm' => 'new_password', 'bypassProfanity' => \IPS\Helpers\Form\Text::BYPASS_PROFANITY_ALL, 'htmlAutocomplete' => "new-password" ) ) );
		
		if ( !$mfaOutput and $values = $form->values() )
		{
			/* Change password */
			\IPS\Member::loggedIn()->changePassword( $values['new_password'] );

			/* Invalidate sessions except this one */
			\IPS\Member::loggedIn()->invalidateSessionsAndLogins( \IPS\Session::i()->id );
			if( isset( \IPS\Request::i()->cookie['login_key'] ) )
			{
				\IPS\Member\Device::loadOrCreate( \IPS\Member::loggedIn() )->updateAfterAuthentication( TRUE );
			}
			
			/* Delete any pending validation emails */
			\IPS\Db::i()->delete( 'core_validating', array( 'member_id=? AND lost_pass=1', \IPS\Member::loggedIn()->member_id ) );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=password&success=1', 'front', 'settings' ) );
		}
		
		return \IPS\Theme::i()->getTemplate( 'system' )->settingsPassword( $form );
	}
	
	
	/**
	 * Devices
	 *
	 * @return	string
	 */
	protected function _devices()
	{
		/* Can users manage devices? */
		if ( !\IPS\Settings::i()->device_management )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C122/S' );
		}

		$mfaOutput = \IPS\MFA\MFAHandler::accessToArea( 'core', 'DeviceManagement', \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=devices', 'front', 'settings_devices' ) );
		if ( $mfaOutput )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=devices', 'front', 'settings_devices' ) );
			}
			\IPS\Output::i()->output = $mfaOutput;
			return \IPS\Theme::i()->getTemplate( 'system' )->settingsDevices( array(), array() );
		}
		
		$devices = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members_known_devices', array( 'member_id=? AND last_seen>?', \IPS\Member::loggedIn()->member_id, ( new \DateTime )->sub( new \DateInterval( \IPS\Member\Device::LOGIN_KEY_VALIDITY ) )->getTimestamp() ), 'last_seen DESC' ), 'IPS\Member\Device' );

		$locations = array();
		$ipAddresses = array();
		foreach ( $devices as $device )
		{
			try
			{
				$log = \IPS\Db::i()->select( '*', 'core_members_known_ip_addresses', array( 'member_id=? AND device_key=?', \IPS\Member::loggedIn()->member_id, $device->device_key ), 'last_seen DESC' )->first();
			}
			catch ( \UnderflowException $e )
			{
				continue;
			}
			
			if ( \IPS\Settings::i()->ipsgeoip )
			{
				if ( !array_key_exists( $log['ip_address'], $locations ) )
				{
					try
					{
						$locations[ $log['ip_address'] ] = \IPS\GeoLocation::getByIp( $log['ip_address'] );
					}
					catch ( \Exception $e )
					{
						$locations[ $log['ip_address'] ] = \IPS\Member::loggedIn()->language()->addToStack('unknown');
					}
				}
				
				$ipAddresses[ $log['device_key'] ][ $log['ip_address'] ] = array(
					'location'	=> $locations[ $log['ip_address'] ],
					'date'		=> $log['last_seen']
				);
			}
			else
			{
				$ipAddresses[ $log['device_key'] ][ $log['ip_address'] ] = array(
					'date'		=> $log['last_seen']
				);
			}
		}
		
		$oauthClients = \IPS\Api\OAuthClient::roots();
		$apps = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_oauth_server_access_tokens', array( 'member_id=?', \IPS\Member::loggedIn()->member_id ), 'issued DESC' ) as $accessToken )
		{
			if ( $accessToken['device_key'] and isset( $oauthClients[ $accessToken['client_id'] ] ) )
			{				
				$apps[ $accessToken['device_key'] ][ $accessToken['client_id'] ] = array(
					'date'	=> $accessToken['issued'],
				);
			}
		}
		
		return \IPS\Theme::i()->getTemplate( 'system' )->settingsDevices( $devices, $ipAddresses, $apps, $oauthClients );
	}
	
	/**
	 * Secure Account
	 *
	 * @return	string
	 */
	protected function secureAccount()
	{
		/* Only logged in members */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C122/Q', 403, '' );
		}

		$canChangePassword = FALSE;
		foreach ( \IPS\Login::methods() as $method )
		{
			if ( $method->canChangePassword( \IPS\Member::loggedIn() ) )
			{
				$canChangePassword = TRUE;
			}
		}
		
		$canConfigureMfa = FALSE;
		$hasConfiguredMfa = FALSE;
		foreach ( \IPS\MFA\MFAHandler::handlers() as $handler )
		{
			if ( $handler->isEnabled() and $handler->memberCanUseHandler( \IPS\Member::loggedIn() ) )
			{
				$canConfigureMfa = TRUE;
				
				if ( $handler->memberHasConfiguredHandler( \IPS\Member::loggedIn() ) )
				{
					$hasConfiguredMfa = TRUE;
					break;
				}
			}
		}
				
		$loginMethods = array();
		foreach ( \IPS\Login::methods() as $method )
		{
			if ( $method->showInUcp( \IPS\Member::loggedIn() ) )
			{
				if ( $method->canProcess( \IPS\Member::loggedIn() ) )
				{
					try
					{
						$name = $method->userProfileName( \IPS\Member::loggedIn() );
						
						$loginMethods[ $method->id ] = array(
							'title'	=> $method->_title,
							'blurb'	=> $name ? \IPS\Member::loggedIn()->language()->addToStack( 'profilesync_headline', FALSE, array( 'sprintf' => array( $name ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'profilesync_headline' ),
							'icon'	=> $method->userProfilePhoto( \IPS\Member::loggedIn() )
						);
					}
					catch ( \IPS\Login\Exception $e )
					{
						$loginMethods[ $method->id ] = array( 'title' => $method->_title, 'blurb' => \IPS\Member::loggedIn()->language()->addToStack('profilesync_reauth_needed') );
					}
				}
			}
		}	
		
		$oauthApps = \IPS\Db::i()->select( 'COUNT(DISTINCT client_id)', 'core_oauth_server_access_tokens', array( "member_id=? AND oauth_enabled=1 AND oauth_ucp=1 AND status='active'", \IPS\Member::loggedIn()->member_id ) )
			->join( 'core_oauth_clients', 'oauth_client_id=client_id' )
			->first();
				
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'secure_account' );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings', 'front', 'settings' ), \IPS\Member::loggedIn()->language()->addToStack('settings') );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('secure_account') );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->settingsSecureAccount( $canChangePassword, $canConfigureMfa, $hasConfiguredMfa, $loginMethods, $oauthApps );
	}
	
	/**
	 * Disable Automatic Login
	 *
	 * @return	string
	 */
	protected function disableAutomaticLogin()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$device = \IPS\Member\Device::loadAndAuthenticate( \IPS\Request::i()->device, \IPS\Member::loggedIn() );
			$device->login_key = NULL;
			$device->save();
			
			\IPS\Db::i()->update( 'core_oauth_server_access_tokens', array( 'status' => 'revoked' ), array( 'member_id=? AND device_key=?', $device->member_id, $device->device_key ) );
			
			\IPS\Member::loggedIn()->logHistory( 'core', 'login', array( 'type' => 'logout', 'device' => $device->device_key ) );
			
			\IPS\Session\Store::i()->deleteByMember( $device->member_id, $device->user_agent, array( \IPS\Session::i()->id ) );
		}
		catch ( \Exception $e ) { } 
						
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=devices', 'front', 'settings_devices' ) );
	}
	
	/**
	 * MFA
	 *
	 * @return	string
	 */
	protected function _mfa()
	{
		\IPS\Output::i()->bypassCsrfKeyCheck = true;

		/* Validate password */
		if ( !isset( $_SESSION['passwordValidatedForMfa'] ) )
		{
			$login = new \IPS\Login( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa', 'front', 'settings_mfa' ), \IPS\Login::LOGIN_REAUTHENTICATE );
			$usernamePasswordMethods = $login->usernamePasswordMethods();
			$buttonMethods = $login->buttonMethods();

			/* Only prompt for re-authentication if it is possible */
			if( $usernamePasswordMethods OR $buttonMethods )
			{
				$error = NULL;
				try
				{
					if ( $success = $login->authenticate() )
					{
						$_SESSION['passwordValidatedForMfa'] = TRUE;
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa', 'front', 'settings_mfa' ) );
					}
				}
				catch ( \IPS\Login\Exception $e )
				{
					$error = $e->getMessage();
				}
				return \IPS\Theme::i()->getTemplate( 'system' )->settingsMfaPassword( $login, $error );
			}
		}

		/* Get our handlers and the output, even if it's just for a backdrop */
		$handlers = array();
		foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
		{
			if ( $handler->isEnabled() and $handler->memberCanUseHandler( \IPS\Member::loggedIn() ) )
			{
				$handlers[ $key ] = $handler;
			}
		}
		$output = \IPS\Theme::i()->getTemplate( 'system' )->settingsMfa( $handlers );
		
		/* Do MFA check */
		$mfaOutput = \IPS\MFA\MFAHandler::accessToArea( 'core', 'SecurityQuestions', \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa', 'front', 'settings_mfa' ) );
		if ( $mfaOutput )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa', 'front', 'settings_mfa' ) );
			}
			return $output . $mfaOutput;
		}
		
		/* Do any enabling/disabling */
		if ( isset( \IPS\Request::i()->act ) )
		{
			\IPS\Session::i()->csrfCheck();

			/* Get the handler */
			$key = \IPS\Request::i()->type;
			if ( !isset( $handlers[ $key ] ) or \IPS\MFA\MFAHandler::accessToArea( 'core', 'SecurityQuestions', \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa', 'front', 'settings_mfa' ) ) )
			{
				\IPS\Output::i()->error( 'node_error', '2C122/M', 404, '' );
			}
			
			/* Do it */
			if ( \IPS\Request::i()->act === 'enable' )
			{
				/* Include the CSS we'll need */
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( '2fa.css', 'core', 'global' ) );
								
				/* Did we just submit it? */
				if ( isset( \IPS\Request::i()->mfa_setup ) and $handlers[ $key ]->configurationScreenSubmit( \IPS\Member::loggedIn() ) )
				{
					$_SESSION['MFAAuthenticated'] = time();
					
					\IPS\Member::loggedIn()->members_bitoptions['security_questions_opt_out'] = FALSE;
					\IPS\Member::loggedIn()->save();

					/* Invalidate other sessions */
					\IPS\Member::loggedIn()->invalidateSessionsAndLogins( \IPS\Session::i()->id );

					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa', 'front', 'settings_mfa' ) );
				}

				/* Show the configuration modal */
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('settings');
				return $output . \IPS\Theme::i()->getTemplate( 'system' )->settingsMfaSetup( $handlers[ $key ]->configurationScreen( \IPS\Member::loggedIn(), FALSE, \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa&act=enable&type=' . $key, 'front', 'settings_mfa' ) ), \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa&act=enable&type=' . $key, 'front', 'settings_mfa' ) );
			}
			elseif ( \IPS\Request::i()->act === 'disable' )
			{
				/* Disable it */
				$handlers[ $key ]->disableHandlerForMember( \IPS\Member::loggedIn() );
				\IPS\Member::loggedIn()->save();
		
				/* If we have now disabled everything, save that we have opted out */
				if ( \IPS\Settings::i()->mfa_required_groups != '*' and !\IPS\Member::loggedIn()->inGroup( explode( ',', \IPS\Settings::i()->mfa_required_groups ) ) )
				{
					$enabledHandlers = FALSE;
					foreach ( $handlers as $handler )
					{
						if ( $handler->memberHasConfiguredHandler( \IPS\Member::loggedIn() ) )
						{
							$enabledHandlers = TRUE;
							break;
						}
					}
					if ( !$enabledHandlers )
					{
						\IPS\Member::loggedIn()->members_bitoptions['security_questions_opt_out'] = TRUE;
						\IPS\Member::loggedIn()->save();
					}
				}
				
				/* Redirect */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa', 'front', 'settings_mfa' ) );
				return;
			}
		}


		$output.= \IPS\Theme::i()->getTemplate( 'system' )->settingsPrivacy();
		
		/* If we're still here, just show the screen */
		return $output;
	}

	/**
	 * Request personal identifiable information
	 *
	 * @return void
	 */
	protected function requestPiiData()
	{
		if( !isset( $_SESSION['passwordValidatedForMfa'] ) OR !\IPS\Member\PrivacyAction::canRequestPiiData() OR \IPS\Settings::i()->pii_type !== 'on' )
		{
			\IPS\Output::i()->error( 'node_error', '1C122/10', 403, '' );
		}
		\IPS\Member\PrivacyAction::requestPiiData();
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa', 'front')->setFragment('piiDataRequest'), 'pii_requested' );
	}

	/**
	 * Download personal identifiable information
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function downloadPiiData()
	{
		\IPS\Session::i()->csrfCheck();
		
		if( !isset( $_SESSION['passwordValidatedForMfa'] ) OR !\IPS\Member\PrivacyAction::canDownloadPiiData() OR \IPS\Settings::i()->pii_type !== 'on' )
		{
			\IPS\Output::i()->error( 'node_error', '1C122/11', 403, '' );
		}

		$xml = \IPS\Member::loggedIn()->getPiiData();

		\IPS\Db::i()->delete( 'core_member_privacy_actions', array( 'member_id=? AND action=?', \IPS\Member::loggedIn()->member_id, \IPS\Member\PrivacyAction::TYPE_REQUEST_PII ) );
		\IPS\Db::i()->delete( 'core_notifications', array( 'member=? AND notification_key=?', \IPS\Member::loggedIn()->member_id, 'pii_data' ) );
		\IPS\Member::loggedIn()->logHistory( 'core', 'privacy', array( 'type' => 'pii_download' ) );
		\IPS\Output::i()->sendOutput( $xml->asXML(), 200, 'application/xml', [ 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', \IPS\Member::loggedIn()->name.'_personal_information.xml' ) ], FALSE, FALSE, FALSE );
	}

	/**
	 * Request account deletion
	 *
	 * @return void
	 */
	protected function requestAccountDeletion()
	{
		\IPS\Session::i()->csrfCheck();

		if( !isset( $_SESSION['passwordValidatedForMfa'] ) OR !\IPS\Member::loggedIn()->canUseAccountDeletion() OR \IPS\Settings::i()->right_to_be_forgotten_type !== 'on' )
		{
			\IPS\Output::i()->error( 'node_error', '2C122/13', 403, '' );
		}
		
		if( !\IPS\Member\PrivacyAction::canDeleteAccount() )
		{
			\IPS\Output::i()->error( 'node_error', '1C122/12', 403, '' );
		}
		if( \IPS\Request::i()->vkey )
		{
			\IPS\Member\PrivacyAction::requestAccountDeletion( NULL, FALSE );
		}
		else
		{
			\IPS\Member\PrivacyAction::requestAccountDeletion(NULL, TRUE );
		}


		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa', 'front')->setFragment('requestAccountDeletion'), 'account_deletion_requested' );
	}

	/**
	 * Cancel account deletion
	 *
	 * @return void
	 */
	protected function cancelAccountDeletion()
	{
		\IPS\Session::i()->csrfCheck();
		try
		{
			$where = [];
			$where[] = ['member_id=?', \IPS\Member::loggedIn()->member_id];
			$where[] = [ \IPS\Db::i()->in( 'action',[\IPS\Member\PrivacyAction::TYPE_REQUEST_DELETE, \IPS\Member\PrivacyAction::TYPE_REQUEST_DELETE_VALIDATION ] ) ];
			$row = \IPS\Db::i()->select( '*', \IPS\Member\PrivacyAction::$databaseTable, $where  )->first();
			\IPS\Member\PrivacyAction::constructFromData( $row )->delete();
			\IPS\Member::loggedIn()->logHistory( 'core', 'privacy', [ 'type' => 'account_deletion_cancelled' ] );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=mfa', 'front'), 'account_deletion_cancelled' );
		}
		catch( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C122/Y', 404, '' );
		}
	}

	/**
	 * Confirm account deletion
	 *
	 * @return void
	 */
	protected function confirmAccountDeletion()
	{
		$key = \IPS\Request::i()->vid;
		try
		{
			$request = \IPS\Member\PrivacyAction::getDeletionRequestByMemberAndKey( \IPS\Member::loggedIn(), $key );
		
			$request->confirmAccountDeletion();
			\IPS\Output::i()->redirect( $this->url->setQueryString( 'area','mfa'), 'account_deletion_confirmed' );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C122/Z', 404, '' );
		}

	}
	
	/**
	 * Initial MFA Setup
	 *
	 * @return	string
	 */
	protected function initialMfa()
	{
		$handlers = array();
		foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
		{
			if ( $handler->isEnabled() and $handler->memberCanUseHandler( \IPS\Member::loggedIn() ) )
			{
				$handlers[ $key ] = $handler;
			}
		}
		
		if ( isset( \IPS\Request::i()->mfa_setup ) )
		{
			\IPS\Session::i()->csrfCheck();
			
			foreach ( $handlers as $key => $handler )
			{
				if ( ( \count( $handlers ) == 1 ) or $key == \IPS\Request::i()->mfa_method )
				{
					if ( $handler->configurationScreenSubmit( \IPS\Member::loggedIn() ) )
					{							
						$_SESSION['MFAAuthenticated'] = time();
						$this->_performRedirect( \IPS\Http\Url::internal('') );
					}
				}
			}
		}
		
		foreach ( $handlers as $key => $handler )
		{
			if ( $handler->memberHasConfiguredHandler( \IPS\Member::loggedIn() ) )
			{
				$this->_performRedirect( \IPS\Http\Url::internal('') );
			}
		}

		if ( isset( \IPS\Request::i()->_mfa ) and \IPS\Request::i()->_mfa == 'optout' )
		{
			\IPS\Session::i()->csrfCheck();
			
			\IPS\Member::loggedIn()->members_bitoptions['security_questions_opt_out'] = TRUE;
			\IPS\Member::loggedIn()->save();
			\IPS\Member::loggedIn()->logHistory( 'core', 'mfa', array( 'handler' => 'questions', 'enable' => FALSE, 'optout' => TRUE ) );
			$this->_performRedirect( \IPS\Http\Url::internal('') );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('reg_complete_2fa_title');
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( '2fa.css', 'core', 'global' ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->mfaSetup( $handlers, \IPS\Member::loggedIn(), \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&do=initialMfa', 'front', 'settings' )->addRef( $this->_performRedirect( \IPS\Http\Url::internal(''), '', TRUE ) ) );
	}
		
	/**
	 * Security Questions
	 *
	 * @return	string
	 */
	protected function _securityquestions()
	{
		$handler = new \IPS\MFA\SecurityQuestions\Handler();
		
		if ( !$handler->isEnabled() )
		{
			\IPS\Output::i()->error( 'requested_route_404', '2C122/J', 404, '' );
		}
				
		$url = \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=securityquestions', 'front', 'settings_securityquestions' );
		if ( isset( \IPS\Request::i()->initial ) )
		{
			if ( isset( \IPS\Request::i()->ref ) )
			{
				$url = $url->setQueryString( 'ref', \IPS\Request::i()->ref );
			}
						
			if ( !$handler->memberCanUseHandler( \IPS\Member::loggedIn() ) or $handler->memberHasConfiguredHandler( \IPS\Member::loggedIn() ) )
			{
				$this->_performRedirect( \IPS\Http\Url::internal('') );
			}
			
			$url = $url->setQueryString( 'initial', 1 );
		}
		elseif ( $handler->memberHasConfiguredHandler( \IPS\Member::loggedIn() ) )
		{
			if ( isset( \IPS\Request::i()->_securityQuestionSetup ) )
			{
				return \IPS\Theme::i()->getTemplate( 'system', 'core' )->securityQuestionsFinished();
			}
			elseif ( $output = \IPS\MFA\MFAHandler::accessToArea( 'core', 'SecurityQuestions', $url ) )
			{
				return $output;
			}
		}
		
		$output = $handler->configurationForm( \IPS\Member::loggedIn(), $url, !isset( \IPS\Request::i()->initial ) );
		
		if ( isset( \IPS\Request::i()->initial ) )
		{
			\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
			\IPS\Output::i()->sidebar['enabled'] = FALSE;
			\IPS\Output::i()->output = $output;
			return;
		}
		else
		{
			return $output;
		}
	}
	
	/**
	 * MFA Email Recovery
	 *
	 * @return	string
	 */
	protected function mfarecovery()
	{
		/* Who are we */
		if ( isset( $_SESSION['processing2FA'] ) )
		{
			$member = \IPS\Member::load( $_SESSION['processing2FA']['memberId'] );
		}
		else
		{
			$member = \IPS\Member::loggedIn();
		}
				
		/* Can we use this? */
		if ( !$member->member_id or !( ( $member->failed_mfa_attempts >= \IPS\Settings::i()->security_questions_tries and \IPS\Settings::i()->mfa_lockout_behaviour == 'email' ) or \in_array( 'email', explode( ',', \IPS\Settings::i()->mfa_forgot_behaviour ) ) ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C122/L', 403, '' );
		}
				
		/* If we have an existing validation record, we can just reuse it */
		$sendEmail = TRUE;
		try
		{
			$existing = \IPS\Db::i()->select( array( 'vid', 'email_sent' ), 'core_validating', array( 'member_id=? AND forgot_security=1', $member->member_id ) )->first();
			$vid = $existing['vid'];
			
			/* If we sent an email within the last 15 minutes, don't send another one otherwise someone could be a nuisence */
			if ( $existing['email_sent'] and $existing['email_sent'] > ( time() - 900 ) )
			{
				$sendEmail = FALSE;
			}
			else
			{
				\IPS\Db::i()->update( 'core_validating', array( 'email_sent' => time() ), array( 'vid=?', $vid ) );
			}
		}
		catch ( \UnderflowException $e )
		{
			$vid = md5( $member->members_pass_hash . \IPS\Login::generateRandomString() );

			\IPS\Db::i()->insert( 'core_validating', array(
				'vid'         		=> $vid,
				'member_id'   		=> $member->member_id,
				'entry_date'  		=> time(),
				'forgot_security'   => 1,
				'ip_address'  		=> \IPS\Request::i()->ipAddress(),
				'email_sent'  		=> time(),
			) );
		}
					
		/* Send email */
		if ( $sendEmail )
		{
			\IPS\Email::buildFromTemplate( 'core', 'mfaRecovery', array( $member, $vid ), \IPS\Email::TYPE_TRANSACTIONAL )->send( $member );
			$message = "mfa_recovery_email_sent";
		}
		else
		{
			$message = "mfa_recovery_email_already_sent";
		}
		
		/* Show confirmation page with further instructions */
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('mfa_account_recovery');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->mfaAccountRecovery( $message );
	}
	
	/**
	 * Validate MFA Email Recovery
	 *
	 * @return	void
	 */
	protected function mfarecoveryvalidate()
	{
		/* Validate */
		try
		{
			$record = \IPS\Db::i()->select( '*', 'core_validating', array( 'vid=? AND member_id=? AND forgot_security=1', \IPS\Request::i()->vid, \IPS\Request::i()->mid ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'mfa_recovery_no_validation_key', '2C122/K', 410, '' );
		}
				
		/* Remove all MFA */
		$member = \IPS\Member::load( $record['member_id'] );
		foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
		{
			$handler->disableHandlerForMember( $member );
		}
		$member->failed_mfa_attempts = 0;
		$member->save();
		
		/* Delete validating record  */
		\IPS\Db::i()->delete( 'core_validating', array( 'member_id=? AND forgot_security=1', $member->member_id ) );
		
		/* Log in if necessary */
		if ( !\IPS\Member::loggedIn()->member_id and isset( $_SESSION['processing2FA'] ) )
		{
			( new \IPS\Login\Success( $member, \IPS\Login\Handler::load( $_SESSION['processing2FA']['handler'] ), $_SESSION['processing2FA']['remember'], $_SESSION['processing2FA']['anonymous'] ) )->process();
		}
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ) );
	}
	
	/**
	 * Username
	 *
	 * @return	string
	 */
	protected function _username()
	{
		/* Check they have permission to change their username */
		if( !\IPS\Member::loggedIn()->group['g_dname_changes'] )
		{
			\IPS\Output::i()->error( 'username_err_nochange', '1C122/4', 403, '' );
		}
				
		if ( \IPS\Member::loggedIn()->group['g_displayname_unit'] )
		{
			if ( \IPS\Member::loggedIn()->group['gbw_displayname_unit_type'] )
			{
				if ( \IPS\Member::loggedIn()->joined->diff( \IPS\DateTime::create() )->days < \IPS\Member::loggedIn()->group['g_displayname_unit'] )
				{
					\IPS\Output::i()->error(
						\IPS\Member::loggedIn()->language()->addToStack( 'username_err_days', FALSE, array( 'sprintf' => array(
						\IPS\Member::loggedIn()->joined->add(
							new \DateInterval( 'P' . \IPS\Member::loggedIn()->group['g_displayname_unit'] . 'D' )
						)->localeDate()
						), 'pluralize' => array( \IPS\Member::loggedIn()->group['g_displayname_unit'] ) ) ),
					'1C122/5', 403, '' );
				}
			}
			else
			{
				if ( \IPS\Member::loggedIn()->member_posts < \IPS\Member::loggedIn()->group['g_displayname_unit'] )
				{
					\IPS\Output::i()->error( 
						\IPS\Member::loggedIn()->language()->addToStack( 'username_err_posts' , FALSE, array( 'sprintf' => array(
						( \IPS\Member::loggedIn()->group['g_displayname_unit'] - \IPS\Member::loggedIn()->member_posts )
						), 'pluralize' => array( \IPS\Member::loggedIn()->group['g_displayname_unit'] ) ) ),
					'1C122/6', 403, '' );
				}
			}
		}
		
		/* How many changes */
		$nameCount = \IPS\Db::i()->select( 'COUNT(*) as count, MIN(log_date) as min_date', 'core_member_history', array(
			'log_member=? AND log_app=? AND log_type=? AND log_date>?',
			\IPS\Member::loggedIn()->member_id,
			'core',
			'display_name',
			\IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Member::loggedIn()->group['g_dname_date'] . 'D' ) )->getTimestamp()
		) )->first();

		if ( \IPS\Member::loggedIn()->group['g_dname_changes'] != -1 and $nameCount['count'] >= \IPS\Member::loggedIn()->group['g_dname_changes'] )
		{
			return \IPS\Theme::i()->getTemplate( 'system' )->settingsUsernameLimitReached( \IPS\Member::loggedIn()->language()->addToStack('username_err_limit', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->group['g_dname_date'] ), 'pluralize' => array( \IPS\Member::loggedIn()->group['g_dname_changes'] ) ) ) );
		}
		else
		{
			/* Build form */
			$form = new \IPS\Helpers\Form;
			$form->class = 'ipsForm_collapseTablet';
			$form->add( new \IPS\Helpers\Form\Text( 'new_username', '', TRUE, array( 'accountUsername' => \IPS\Member::loggedIn(), 'htmlAutocomplete' => "username" ) ) );
						
			/* Handle submissions */
			if ( $values = $form->values() )
			{
				/* Disable syncing */
				$profileSync = \IPS\Member::loggedIn()->profilesync;
				if ( isset( $profileSync['name'] ) )
				{
					unset( $profileSync['name'] );
					\IPS\Member::loggedIn()->profilesync = $profileSync;
					\IPS\Member::loggedIn()->save();
				}
				
				/* Save */
				$oldName = \IPS\Member::loggedIn()->name;
				\IPS\Member::loggedIn()->name = $values['new_username'];
				\IPS\Member::loggedIn()->save();
				\IPS\Member::loggedIn()->logHistory( 'core', 'display_name', array( 'old' => $oldName, 'new' => $values['new_username'], 'by' => 'manual' ) );
				
				/* Sync with login handlers */
				foreach ( \IPS\Login::methods() as $method )
				{
					try
					{
						$method->changeUsername( \IPS\Member::loggedIn(), $oldName, $values['new_username'] );
					}
					catch( \BadMethodCallException $e ){}
				}
				
				/* Redirect */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=username', 'front', 'settings_username' ), 'username_changed' );
			}
		}

		return \IPS\Theme::i()->getTemplate( 'system' )->settingsUsername( $form, $nameCount['count'], \IPS\Member::loggedIn()->group['g_dname_changes'], $nameCount['min_date'] ? \IPS\DateTime::ts( $nameCount['min_date'] ) : \IPS\Member::loggedIn()->joined, \IPS\Member::loggedIn()->group['g_dname_date'] );
	}

	/**
	 * Link Preference
	 *
	 * @return	string
	 */
	protected function _links()
	{
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_collapseTablet';
		$form->add( new \IPS\Helpers\Form\Radio( 'link_pref', \IPS\Member::loggedIn()->linkPref() ?: \IPS\Settings::i()->link_default, FALSE, array( 'options' => array(
			'unread'	=> 'profile_settings_cvb_unread',
			'first'	=> 'profile_settings_cvb_first',
			'last'	=> 'profile_settings_cvb_last'
		) ) ) );

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			switch( $values['link_pref'] )
			{
				case 'last':
					\IPS\Member::loggedIn()->members_bitoptions['link_pref_unread'] = FALSE;
					\IPS\Member::loggedIn()->members_bitoptions['link_pref_last'] = TRUE;
					\IPS\Member::loggedIn()->members_bitoptions['link_pref_first'] = FALSE;
					break;
				case 'unread':
					\IPS\Member::loggedIn()->members_bitoptions['link_pref_unread'] = TRUE;
					\IPS\Member::loggedIn()->members_bitoptions['link_pref_last'] = FALSE;
					\IPS\Member::loggedIn()->members_bitoptions['link_pref_first'] = FALSE;
					break;
				default:
					\IPS\Member::loggedIn()->members_bitoptions['link_pref_unread'] = FALSE;
					\IPS\Member::loggedIn()->members_bitoptions['link_pref_last'] = FALSE;
					\IPS\Member::loggedIn()->members_bitoptions['link_pref_first'] = TRUE;
					break;
			}

			\IPS\Member::loggedIn()->save();
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=links', 'front', 'settings_links' ), 'saved' );
		}

		return \IPS\Theme::i()->getTemplate( 'system' )->settingsLinks( $form );
	}

	/**
	 * Signature
	 *
	 * @return	string
	 */
	protected function _signature()
	{
		/* Check they have permission to change their signature */
		$sigLimits = explode( ":", \IPS\Member::loggedIn()->group['g_signature_limits']);
		
		if( !\IPS\Member::loggedIn()->canEditSignature() )
		{
			\IPS\Output::i()->error( 'signatures_disabled', '2C122/C', 403, '' );
		}
		
		/* Check limits */
		if ( \IPS\Member::loggedIn()->group['g_sig_unit'] )
		{
			/* Days */
			if ( \IPS\Member::loggedIn()->group['gbw_sig_unit_type'] )
			{
				if ( \IPS\Member::loggedIn()->joined->diff( \IPS\DateTime::create() )->days < \IPS\Member::loggedIn()->group['g_sig_unit'] )
				{
					\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->pluralize(
							sprintf(
									\IPS\Member::loggedIn()->language()->get('sig_err_days'),
									\IPS\Member::loggedIn()->joined->add(
											new \DateInterval( 'P' . \IPS\Member::loggedIn()->group['g_sig_unit'] . 'D' )
									)->localeDate()
							), array( \IPS\Member::loggedIn()->group['g_sig_unit'] ) ),
							'1C122/D', 403, '' );
				}
			}
			/* Posts */
			else
			{
				if ( \IPS\Member::loggedIn()->member_posts < \IPS\Member::loggedIn()->group['g_sig_unit'] )
				{
					\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->pluralize(
							sprintf(
									\IPS\Member::loggedIn()->language()->get('sig_err_posts'),
									( \IPS\Member::loggedIn()->group['g_sig_unit'] - \IPS\Member::loggedIn()->member_posts )
							), array( \IPS\Member::loggedIn()->group['g_sig_unit'] ) ),
							'1C122/E', 403, '' );
				}
			}
		}
	
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_collapseTablet';
		$form->add( new \IPS\Helpers\Form\YesNo( 'view_sigs', \IPS\Member::loggedIn()->members_bitoptions['view_sigs'], FALSE ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'signature', \IPS\Member::loggedIn()->signature, FALSE, array( 'app' => 'core', 'key' => 'Signatures', 'autoSaveKey' => "frontsig-" .\IPS\Member::loggedIn()->member_id, 'attachIds' => array( \IPS\Member::loggedIn()->member_id ) ) ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			if( $values['signature'] )
			{
				/* Check Limits */
				$signature = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
				$signature->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $values['signature'] ) );
				
				$errors = array();
				
				/* Links */
				if ( \is_numeric( $sigLimits[4] ) and ( $signature->getElementsByTagName('a')->length + $signature->getElementsByTagName('iframe')->length ) > $sigLimits[4] )
				{
					$errors[] = \IPS\Member::loggedIn()->language()->addToStack('sig_num_links_exceeded');
				}

				/* Number of Images */
				if ( \is_numeric( $sigLimits[1] ) )
				{
					$imageCount = 0;
					foreach ( $signature->getElementsByTagName('img') as $img )
					{
						if( !$img->hasAttribute("data-emoticon") )
						{
							$imageCount++;
						}
					}

					/* Look for background-image URLs too */
					$xpath = new \DOMXpath( $signature );

					foreach ( $xpath->query("//*[contains(@style, 'url') and contains(@style, 'background')]") as $styleUrl )
					{
						$imageCount++;
					}

					if( $imageCount > $sigLimits[1] )
					{
						$errors[] = \IPS\Member::loggedIn()->language()->addToStack('sig_num_images_exceeded');
					}
				}
				
				/* Size of images */
				if ( ( \is_numeric( $sigLimits[2] ) and $sigLimits[2] ) or ( \is_numeric( $sigLimits[3] ) and $sigLimits[3] ) )
				{
					foreach ( $signature->getElementsByTagName('img') as $image )
					{
						$attachId			= $image->getAttribute('data-fileid');
						$imageProperties	= NULL;

						if( $attachId )
						{
							try
							{
								$attachment = \IPS\Db::i()->select( 'attach_location, attach_thumb_location', 'core_attachments', array( 'attach_id=?', $attachId ) )->first();
								$imageProperties = \IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] ?: $attachment['attach_location'] )->getImageDimensions();
								$src = (string) \IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->url;
							}
							catch( \UnderflowException $e ){}
						}
						
						if( \is_array( $imageProperties ) AND \count( $imageProperties ) )
						{
							if( $imageProperties[0] > $sigLimits[2] OR $imageProperties[1] > $sigLimits[3] )
							{
								$errors[] = \IPS\Member::loggedIn()->language()->addToStack( 'sig_imagetoobig', FALSE, array( 'sprintf' => array( $src, $sigLimits[2], $sigLimits[3] ) ) );
							}
						}
					}
				}
				
				/* Lines */
				$preBreaks = 0;
				
				/* Make sure we are not trying to bypass the limit by using <pre> tags, which will not have <p> or <br> tags in its content */
				foreach( $signature->getElementsByTagName('pre') AS $pre )
				{
					$content = nl2br( trim( $pre->nodeValue ) );
					$preBreaks += \count( explode( "<br />", $content ) );
				}

				/* Line limit with a sensible length restriction to prevent broken html */
				if ( ( \is_numeric( $sigLimits[5] ) and ( $signature->getElementsByTagName('p')->length + $signature->getElementsByTagName('br')->length + $preBreaks ) > $sigLimits[5] ) or \strlen( $values['signature'] ) > 20000 )
				{
					$errors[] = \IPS\Member::loggedIn()->language()->addToStack('sig_num_lines_exceeded');
				}
			}
			
			if( !empty( $errors ) )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack('sig_restrictions_exceeded');
				$form->elements['']['signature']->error = \IPS\Member::loggedIn()->language()->formatList( $errors );
				
				return \IPS\Theme::i()->getTemplate( 'system' )->settingsSignature( $form, $sigLimits );
			}
			
			\IPS\Member::loggedIn()->signature = $values['signature'];
			\IPS\Member::loggedIn()->members_bitoptions['view_sigs'] = $values['view_sigs'];
			
			\IPS\Member::loggedIn()->save();
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=signature', 'front', 'settings_signature' ), 'signature_changed' );
		}

		return \IPS\Theme::i()->getTemplate( 'system' )->settingsSignature( $form, $sigLimits );
	}
	
	/**
	 * Login Method
	 *
	 * @return	string
	 */
	protected function _login()
	{
		/* Load method */
		try
		{
			$method = \IPS\Login\Handler::load( \IPS\Request::i()->service );
			if ( !$method->showInUcp( \IPS\Member::loggedIn() ) )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'page_doesnt_exist', '2C122/B', 404, '' );
		}
		
		/* Are we connected? */
		$blurb				= 'profilesync_blurb';
		$canDisassociate	= FALSE;

		try
		{
			$connected = $method->canProcess( \IPS\Member::loggedIn() );
			if ( $connected )
			{
				$photoUrl = $method->userProfilePhoto( \IPS\Member::loggedIn() );
				$profileName = $method->userProfileName( \IPS\Member::loggedIn() );

				/* Can we disassociate? */
				foreach ( \IPS\Login::methods() as $_method )
				{
					if ( $_method->id != $method->id and $_method->canProcess( \IPS\Member::loggedIn() ) )
					{
						$canDisassociate = TRUE;
						break;
					}
				}
			}
		}
		catch ( \IPS\Login\Exception $e )
		{
			$connected = FALSE;
			$blurb = 'profilesync_expire_blurb';

			/* If we previously associated an account but that link has expired, we should still allow you to disassociate */
			if( $method->canProcess( \IPS\Member::loggedIn() ) )
			{
				$canDisassociate = TRUE;
			}
		}

		if ( $canDisassociate and isset( \IPS\Request::i()->disassociate ) )
		{				
			\IPS\Session::i()->csrfCheck();
			$method->disassociate();
			
			if ( $method->showInUcp( \IPS\Member::loggedIn() ) )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=login&service=' . $method->id, 'front', 'settings_login' ) );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings', 'front', 'settings' ) );
			}
		}

		/* Are we connected? */
		if ( $connected )
		{			
			/* Are we forcing syncing of anything? */
			$syncOptions = $method->syncOptions( \IPS\Member::loggedIn() );
			$forceSync = array();
			foreach ( $method->forceSync() as $type )
			{
				$forceSync[ $type ] = array(
					'label'	=> \IPS\Member::loggedIn()->language()->addToStack( "profilesync_{$type}_force", FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $method->_title ) ) ) ),
					'error'	=> isset( \IPS\Member::loggedIn()->profilesync[ $type ]['error'] ) ? \IPS\Member::loggedIn()->profilesync[ $type ]['error'] : NULL
				);
			}
			
			/* Show sync options */
			$form = NULL;
			if ( $syncOptions )
			{
				$form = new \IPS\Helpers\Form( 'sync', 'profilesync_save' );
				$form->class = 'ipsForm_vertical';
				foreach ( $syncOptions as $option )
				{
					if ( !\IPS\Login\Handler::handlerHasForceSync( $option, NULL, \IPS\Member::loggedIn() ) )
					{
						if ( $option == 'photo' and !\IPS\Member::loggedIn()->group['g_edit_profile'] )
						{
							continue;
						}
						if ( $option == 'cover' and ( !\IPS\Member::loggedIn()->group['g_edit_profile'] or !\IPS\Member::loggedIn()->group['gbw_allow_upload_bgimage'] ) )
						{
							continue;
						}
						if ( $option == 'status' and ( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) or !\IPS\core\Statuses\Status::canCreateFromCreateMenu( \IPS\Member::loggedIn() ) or !\IPS\Settings::i()->profile_comments or \IPS\Member::loggedIn()->group['gbw_no_status_update'] ) )
						{
							continue;
						}
						
						if ( $option == 'status' )
						{
							$checked = ( isset( \IPS\Member::loggedIn()->profilesync[ $option ] ) and array_key_exists( $method->id, \IPS\Member::loggedIn()->profilesync[ $option ]) );
						}
						else
						{
							$checked = ( isset( \IPS\Member::loggedIn()->profilesync[ $option ] ) and  \IPS\Member::loggedIn()->profilesync[ $option ]['handler'] == $method->id );
						}
						$field = new \IPS\Helpers\Form\Checkbox( "profilesync_{$option}", $checked, FALSE, array( 'labelSprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $method->_title ) ) ), NULL, NULL, NULL, "profilesync_{$option}_{$method->id}" );
						if ( $checked and ( ( $option == 'status' and $error = \IPS\Member::loggedIn()->profilesync[ $option ][ $method->id ]['error'] ) or ( $option != 'status' and $error = \IPS\Member::loggedIn()->profilesync[ $option ]['error'] ) ) )
						{
							$field->description = \IPS\Theme::i()->getTemplate( 'system' )->settingsLoginMethodSynError( $error );
						}		
						$form->add( $field );
					}
				}
				if ( $values = $form->values() )
				{
					$profileSync = \IPS\Member::loggedIn()->profilesync;
					$changes = array();
					
					foreach ( $values as $k => $v )
					{
						$option = mb_substr( $k, 12 );
						if ( $option === 'status' )
						{
							if ( isset( \IPS\Member::loggedIn()->profilesync[ $option ][ $method->id ] ) )
							{
								if ( !$v )
								{
									unset( $profileSync[ $option ][ $method->id ] );
									$changes[ $option ] = FALSE;
								}
							}
							else
							{
								if ( $v )
								{
									$profileSync[ $option ][ $method->id ] = array( 'lastsynced' => NULL, 'error' => NULL );
									$changes[ $option ] = TRUE;
								}
							}
						}
						else
						{
							if ( isset( \IPS\Member::loggedIn()->profilesync[ $option ] ) and  \IPS\Member::loggedIn()->profilesync[ $option ]['handler'] == $method->id )
							{
								if ( !$v )
								{
									unset( $profileSync[ $option ] );
									$changes[ $option ] = FALSE;
								}
							}
							else
							{
								if ( $v )
								{
									$profileSync[ $option ] = array( 'handler' => $method->id, 'ref' => NULL, 'error' => NULL );
									$changes[ $option ] = TRUE;
								}
							}
						}
					}
					
					if ( \count( $changes ) )
					{
						\IPS\Member::loggedIn()->logHistory( 'core', 'social_account', array( 'changed' => $changes, 'handler' => $method->id, 'service' => $method::getTitle() ) );
					}
					
					\IPS\Member::loggedIn()->profilesync = $profileSync;
					\IPS\Member::loggedIn()->save();
					\IPS\Member::loggedIn()->profileSync();
					
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=login&service=' . $method->id, 'front', 'settings_login' ) );
				}
			}
			
			$extraPermissions = NULL;
			$login = NULL;
			if ( isset( \IPS\Request::i()->scopes ) )
			{
				$method = \IPS\Login\Handler::findMethod('IPS\Login\Handler\Oauth2\Facebook');
				$extraPermissions = \IPS\Request::i()->scopes;
				$login = new \IPS\Login( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=login&service=' . $method->id . '&scopes=' . \IPS\Request::i()->scopes, 'front', 'settings_login' ), \IPS\Login::LOGIN_UCP );
				$login->reauthenticateAs = \IPS\Member::loggedIn();

				try
				{
					if ( $success = $login->authenticate( $method ) )
					{				
						if ( $success->member->member_id === \IPS\Member::loggedIn()->member_id )
						{
							$method->completeLink( \IPS\Member::loggedIn(), NULL );
							\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=login&service=' . $method->id, 'front', 'settings_login' ) );
						}
					}
				}
				catch( \Exception $ex ) { }
			}
					
			/* Display */
			return \IPS\Theme::i()->getTemplate( 'system' )->settingsLoginMethodOn( $method, $form, $canDisassociate, $photoUrl, $profileName, $extraPermissions, $login, $forceSync );
		}
		
		/* No - show option to connect */
		else
		{			
			$login = new \IPS\Login( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=login&service=' . $method->id, 'front', 'settings_login' ), \IPS\Login::LOGIN_UCP );
			$login->reauthenticateAs = \IPS\Member::loggedIn();
			$error = NULL;
			try
			{
				if ( $success = $login->authenticate( $method ) )
				{					
					if ( $success->member->member_id === \IPS\Member::loggedIn()->member_id )
					{
						$method->completeLink( \IPS\Member::loggedIn(), NULL );
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=login&service=' . $method->id, 'front', 'settings_login' ) );
					}
					else
					{
						$error = \IPS\Member::loggedIn()->language()->addToStack( 'profilesync_already_associated', FALSE, array( 'sprintf' => array( $method->_title ) ) );
					}
				}
			}
			catch ( \IPS\Login\Exception $e )
			{
				if ( $e->getCode() === \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT )
				{
					if ( $e->member->member_id === \IPS\Member::loggedIn()->member_id )
					{
						$method->completeLink( \IPS\Member::loggedIn(), NULL );
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=login&service=' . $method->id, 'front', 'settings_login' ) );
					}
					else
					{
						$error = \IPS\Member::loggedIn()->language()->addToStack( 'profilesync_email_exists', FALSE, array( 'sprintf' => array( $method->_title ) ) );
					}
				}
				elseif( $e->getCode() === \IPS\Login\Exception::LOCAL_ACCOUNT_ALREADY_MERGED )
				{
					$error = \IPS\Member::loggedIn()->language()->addToStack( 'profilesync_already_merged', FALSE, array( 'sprintf' => array( $method->_title, $method->_title, $method->_title ) ) );
				}
				else
				{
					$error = $e->getMessage();
				}
			}
			
			return \IPS\Theme::i()->getTemplate( 'system' )->settingsLoginMethodOff( $method, $login, $error, $blurb, $canDisassociate );
		}
	}
	
	/**
	 * Apps
	 *
	 * @return	string
	 */
	protected function _apps()
	{
		$apps = array();
		
		foreach ( \IPS\Db::i()->select( '*', 'core_oauth_server_access_tokens', array( 'member_id=?', \IPS\Member::loggedIn()->member_id ), 'issued DESC' ) as $accessToken )
		{
			if ( $accessToken['status'] == 'revoked' )
			{
				continue;
			}
			try
			{
				$client = \IPS\Api\OAuthClient::load( $accessToken['client_id'] );
				if ( !$client->enabled )
				{
					throw new \OutOfRangeException;
				}
				if ( !$client->ucp )
				{
					continue;
				}
				
				if ( !isset( $apps[ $client->client_id ] ) )
				{
					$apps[ $client->client_id ] = array(
						'issued'	=> $accessToken['issued'],
						'client'	=> $client,
						'scopes'	=> array()
					);
				}
				else
				{
					if ( $accessToken['issued'] < $apps[ $client->client_id ]['issued'] )
					{
						$apps[ $client->client_id ]['issued'] = $accessToken['issued'];
					}
				}
				
				$scopes = array();
				if ( $accessToken['scope'] and $authorizedScopes = json_decode( $accessToken['scope'] ) )
				{
					$availableScopes = json_decode( $client->scopes, TRUE );
					foreach ( $authorizedScopes as $scope )
					{
						if ( isset( $availableScopes[ $scope ] ) and !isset( $apps[ $client->client_id ]['scopes'][ $scope ] ) )
						{
							$apps[ $client->client_id ]['scopes'][ $scope ] = $availableScopes[ $scope ]['description'];
						}
					}
				}
			}
			catch ( \OutOfRangeException $e ) { }
		}
				
		return \IPS\Theme::i()->getTemplate( 'system' )->settingsApps( $apps );
	}
		
	/**
	 * Change App Permissions
	 *
	 * @return	string
	 */
	protected function revokeApp()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$client = \IPS\Api\OAuthClient::load( \IPS\Request::i()->client_id );
			\IPS\Db::i()->update( 'core_oauth_server_access_tokens', array( 'status' => 'revoked' ), array( 'client_id=? AND member_id=?', $client->client_id, \IPS\Member::loggedIn()->member_id ) );
			\IPS\Member::loggedIn()->logHistory( 'core', 'oauth', array( 'type' => 'revoked_access_token', 'client' => $client->client_id ) );
		}
		catch ( \Exception $e ) { } 
				
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=apps', 'front', 'settings_apps' ) );
	}
	
	/**
	 * Disable All Signatures
	 *
	 * @return	void
	 */
	protected function toggleSigs()
	{
		if ( !\IPS\Settings::i()->signatures_enabled )
		{
			\IPS\Output::i()->error( 'signatures_disabled', '2C122/F', 403, '' );
		}
			
		\IPS\Session::i()->csrfCheck();
			
		if ( \IPS\Member::loggedIn()->members_bitoptions['view_sigs'] )
		{
			\IPS\Member::loggedIn()->members_bitoptions['view_sigs'] = 0;
		}
		else
		{
			\IPS\Member::loggedIn()->members_bitoptions['view_sigs'] = 1;
		}
		
		\IPS\Member::loggedIn()->save();
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		
		$redirectUrl = \IPS\Request::i()->referrer() ?: \IPS\Http\Url::internal( "app=core&module=system&controller=settings", 'front', 'settings' );
		\IPS\Output::i()->redirect( $redirectUrl, 'signature_pref_toggled' );
	}
	
	/**
	 * Dismiss Profile Completion
	 *
	 * @return	void
	 */
	protected function dismissProfile()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Member::loggedIn()->members_bitoptions['profile_completion_dismissed'] = TRUE;
		\IPS\Member::loggedIn()->save();
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			$redirectUrl = \IPS\Request::i()->referrer() ?: \IPS\Http\Url::internal( "app=core&module=system&controller=settings", 'front', 'settings' );
			\IPS\Output::i()->redirect( $redirectUrl );
		}
	}
	
	/**
	 * Completion Wizard
	 *
	 * @return	void
	 */
	protected function completion()
	{
		$steps = array();
		$url = \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&do=completion', 'front', 'settings' )->setQueryString( 'ref', \IPS\Request::i()->ref );

		foreach( \IPS\Application::allExtensions( 'core', 'ProfileSteps' ) AS $extension )
		{
			if ( method_exists( $extension, 'wizard') AND \is_array( $extension::wizard() ) AND \count( $extension::wizard() ) )
			{
				$steps = array_merge( $steps, $extension::wizard() );
			}
			if ( method_exists( $extension, 'extraStep') AND \count( $extension::extraStep() ) )
			{
				$steps = array_merge( $steps, $extension::extraStep() );
			}
		}

		$steps = \IPS\Member\ProfileStep::setOrder( $steps );

		$steps = array_merge( $steps, array( 'profile_done' => function( $data ) use ( $url ) {

			unset( $_SESSION[ 'wizard-' . md5( $url ) . '-step' ] );
			unset( $_SESSION[ 'wizard-' . md5( $url ) . '-data' ] );

			if( isset( $_SESSION['profileCompletionData'] ) )
			{
				unset( $_SESSION['profileCompletionData'] );
			}

			$this->_performRedirect( \IPS\Http\Url::internal( "app=core&module=system&controller=settings", 'front', 'settings' ), 'saved' );
		} ) );

		$wizard = new \IPS\Helpers\Wizard( $steps, $url, FALSE, NULL, TRUE );
		$wizard->template = array( \IPS\Theme::i()->getTemplate( 'system', 'core', 'front' ), 'completeWizardTemplate' );

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( '2fa.css', 'core', 'global' ) );
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/settings.css' ) );
		
		\IPS\Output::i()->bodyClasses[]			= 'ipsLayout_minimal';
		\IPS\Output::i()->sidebar['enabled']	= FALSE;
		\IPS\Output::i()->title					= \IPS\Member::loggedIn()->language()->addToStack( 'complete_your_profile' );
		\IPS\Output::i()->output 				= (string) $wizard;
	}

	/**
	 * Subscribe to newsletter
	 *
	 * @return	void
	 */
	protected function newsletterSubscribe()
	{
		\IPS\Session::i()->csrfCheck();


		\IPS\Member::loggedIn()->allow_admin_mails = TRUE;
		\IPS\Member::loggedIn()->save();

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}

		$this->_performRedirect( \IPS\Http\Url::internal( "app=core&module=system&controller=settings", 'front', 'settings' ), 'block_newsletter_subscribed' );
	}

	/**
	 * Toggle Anonymously Online
	 *
	 * @return	void
	 */
	protected function updateAnon()
	{
		\IPS\Session::i()->csrfCheck();

		/* Check validation */
		if( !isset( $_SESSION['passwordValidatedForMfa'] ) )
		{
			\IPS\Output::i()->error( 'node_error', '1C122/14', 403, '' );
		}

		/* Check this value can be toggled */
		if( \IPS\Member::loggedIn()->group['g_hide_online_list'] >= 1 )
		{
			\IPS\Output::i()->error( 'online_status_cannot_change', '2C122/X', 403, '' );
		}

		/* Update the bitwise flag */
		\IPS\Member::loggedIn()->members_bitoptions['is_anon'] = (bool) \IPS\Request::i()->value;
		\IPS\Member::loggedIn()->save();

		/* Update users devices */
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members_known_devices', array( "member_id=?", \IPS\Member::loggedIn()->member_id ) ), 'IPS\Member\Device' ) AS $device )
		{
			$device->anonymous = ( (bool) \IPS\Request::i()->value ) ? 1 : 0;
			$device->save();
		}

		/* Update the session */
		\IPS\Session::i()->setType( ( (bool) \IPS\Request::i()->value ) ? \IPS\Session\Front::LOGIN_TYPE_ANONYMOUS : \IPS\Session\Front::LOGIN_TYPE_MEMBER );

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}

		$this->_performRedirect( \IPS\Http\Url::internal( "app=core&module=system&controller=settings&area=mfa", 'front', 'settings_mfa' ), 'saved' );
	}

	/**
	 * Toggle Whether PII is included in the data layer
	 *
	 * @return	void
	 */
	protected function togglePii()
	{
		if ( ! \IPS\Settings::i()->core_datalayer_member_pii_choice )
		{
			\IPS\Output::i()->error( 'page_not_found', '3T251/7', 404 );
		}
		\IPS\Session::i()->csrfCheck();

		/* Update the bitwise flag */
		\IPS\Member::loggedIn()->members_bitoptions['datalayer_pii_optout'] = (bool) ! \IPS\Member::loggedIn()->members_bitoptions['datalayer_pii_optout'];
		\IPS\Member::loggedIn()->save();

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}

		$this->_performRedirect( \IPS\Http\Url::internal( "app=core&module=system&controller=settings&area=mfa", 'front', 'settings_mfa' ), 'saved' );
	}

	/**
	 * Invite
	 *
	 * @return	void
	 */
	protected function invite()
	{
		$url = \IPS\Http\Url::internal( "" );

		if( \IPS\Settings::i()->ref_on and \IPS\Member::loggedIn()->member_id )
		{
			$url = $url->setQueryString( array( '_rid' => \IPS\Member::loggedIn()->member_id  ) );
		}

		$links = \IPS\core\ShareLinks\Service::getAllServices( $url, \IPS\Settings::i()->board_name, NULL );
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack( 'block_invite' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->invite( $links, $url );
	}

	/**
	 * Referrals 
	 *
	 * @return	string
	 */
	protected function _referrals()
	{
		if( !\IPS\Settings::i()->ref_on )
		{
			\IPS\Output::i()->error( 'referrals_disabled', '2C122/V', 403, '' );
		}

        \IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/referrals.css' ) );
        
        if ( \IPS\Theme::i()->settings['responsive'] )
        {
            \IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/referrals_responsive.css' ) );
        }
            
		$table = new \IPS\Helpers\Table\Db( 'core_referrals', \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&area=referrals', 'front', 'settings_referrals' ), array( array( 'core_referrals.referred_by=?', \IPS\Member::loggedIn()->member_id ) ) );
		$table->joins = array(
			array( 'select' => 'm.joined', 'from' => array( 'core_members', 'm' ), 'where' => "m.member_id=core_referrals.member_id" )
		);
		
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'system', 'core', 'front' ), 'referralTable' );
		$table->rowsTemplate  = array( \IPS\Theme::i()->getTemplate( 'system', 'core', 'front' ), 'referralsRows' );

		$url = \IPS\Http\Url::internal('')->setQueryString( '_rid', \IPS\Member::loggedIn()->member_id );
		
		$rules = NULL;
		if ( \IPS\Application::appIsEnabled('nexus') )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'clients.css', 'nexus' ) );
			$rules = new \IPS\nexus\CommissionRule\Iterator( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_referral_rules' ), 'IPS\nexus\CommissionRule' ), \IPS\Member::loggedIn() );
		}
			
		return \IPS\Theme::i()->getTemplate( 'system' )->settingsReferrals( $table, $url, $rules );
	}

	/**
	 * Toggle New Device Email
	 *
	 * @return	void
	 */
	protected function updateDeviceEmail()
	{
		\IPS\Session::i()->csrfCheck();

		/* Update the bitwise flag */
		\IPS\Member::loggedIn()->members_bitoptions['new_device_email'] = (bool) \IPS\Request::i()->value;
		\IPS\Member::loggedIn()->save();

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}

		$this->_performRedirect( \IPS\Http\Url::internal( "app=core&module=system&controller=settings&area=devices", 'front', 'settings_devices' ), 'saved' );
	}

	/**
	 * Redirect the user
	 * -consolidated to reduce duplicate code
	 *
	 * @param	\IPS\Http\Url	$fallbackUrl		URL to send user to if no referrer was passed
	 * @param	string			$message			(Optional) message to show during redirect
	 * @param	bool			$return				Return URL instead of redirecting
	 * @return	void
	 */
	protected function _performRedirect( $fallbackUrl, $message='', $return=FALSE )
	{
		/* Redirect */
		$ref = \IPS\Request::i()->referrer();
		if ( $ref === NULL )
		{
			$ref = $fallbackUrl;
		}

		if( $return === TRUE )
		{
			return $ref;
		}

		\IPS\Output::i()->redirect( $ref, $message );	
	}
}