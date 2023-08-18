<?php
/**
 * @brief		Abstract Multi Factor Authentication Handler and Factory
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Aug 2016
 */

namespace IPS\MFA;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Multi Factor Authentication Handler and Factory
 */
abstract class _MFAHandler
{
	/* !Access Methods */
	
	/**
	 * Get areas
	 *
	 * @return	array
	 */
	public static function areas()
	{
		$return = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'MFAArea', FALSE, 'core', NULL, FALSE ) as $k => $v )
		{
			$return[ $k ] = "MFA_{$k}";
		}
		return $return;
	}
	
	/**
	 * Get handlers
	 *
	 * @return	array
	 */
	public static function handlers()
	{
		return array(
			'authy'		=> new \IPS\MFA\Authy\Handler(),
			'google'	=> new \IPS\MFA\GoogleAuthenticator\Handler(),
			'questions'	=> new \IPS\MFA\SecurityQuestions\Handler(),
			'verify'	=> new \IPS\MFA\Verify\Handler()
		);
	}
	
	/**
	 * Removes any previous authentication settings for this user
	 *
	 * @return void
	 */
	public static function resetAuthentication()
	{
		if ( isset( $_SESSION['MFAAuthenticated'] ) )
		{
			unset( $_SESSION['MFAAuthenticated'] );
		}
	}
	
	/**
	 * Display output when trying to access an area
	 *
	 * @param	string			$app		The application which owns the MFAArea extension
	 * @param	string			$area		The MFAArea key
	 * @param	\IPS\Http\Url	$url		URL for page
	 * @param	\IPS\Member		$member		The member, or NULL for currently logged in member
	 * @return	string
	 */
	public static function accessToArea( $app, $area, \IPS\Http\Url $url, \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Constant to disable MFA for emergency recovery */
		if ( \IPS\DISABLE_MFA )
		{
			return NULL;
		}
		
		/* If MFA is not enabled for this area, do nothing */
		if ( !\IPS\Settings::i()->security_questions_areas or !\in_array( "{$app}_{$area}", explode( ',', \IPS\Settings::i()->security_questions_areas ) ) )
		{
			return NULL;
		}
				
		/* Are we already authenticated? */
		if ( !\IPS\DEV_FORCE_MFA and ( isset( $_SESSION['MFAAuthenticated'] ) and ( !\IPS\Settings::i()->security_questions_timer or ( ( $_SESSION['MFAAuthenticated'] + ( \IPS\Settings::i()->security_questions_timer * 60 ) ) > time() ) ) ) )
		{
			return NULL;
		}
		
		/* "Opt Out" */
		if ( \IPS\Settings::i()->mfa_required_groups != '*' and !$member->inGroup( explode( ',', \IPS\Settings::i()->mfa_required_groups ) ) )
		{
			if ( $member->members_bitoptions['security_questions_opt_out'] )
			{
				return NULL;
			}
			if ( isset( \IPS\Request::i()->_mfa ) and \IPS\Request::i()->_mfa == 'optout' )
			{
				\IPS\Session::i()->csrfCheck();
				
				$member->members_bitoptions['security_questions_opt_out'] = TRUE;
				$member->save();

				/* Log MFA Optout */
				$member->logHistory( 'core', 'mfa', array( 'handler' => 'questions', 'enable' => FALSE, 'optout' => TRUE ) );

				return NULL;
			}
		}
		
		/* Gather all the one we *can* use */
		$acceptableHandlers = array();
		foreach ( static::handlers() as $key => $handler )
		{
			/* If it's enabled and we can use it... */
			if ( $handler->isEnabled() and $handler->memberCanUseHandler( $member ) )
			{
				$acceptableHandlers[ $key ] = $handler;
			}
		}
		if ( !$acceptableHandlers )
		{
			return NULL;
		}
		
		/* Locked out? */
		if ( $lockedOutScreen = static::_lockedOutScreen( $member ) )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( '2fa.css', 'core', 'global' ) );
			return $lockedOutScreen;
		}
				
		/* "Try another way to sign in" */
		if ( isset( \IPS\Request::i()->_mfa ) )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( '2fa.css', 'core', 'global' ) );
			if ( \IPS\Request::i()->_mfa == 'alt' )
			{			
				/* What handlers have we configured? */
				$configuredHandlers = array();
				foreach ( $acceptableHandlers as $key => $handler )
				{
					if ( $handler->memberHasConfiguredHandler( $member ) )
					{
						$configuredHandlers[ $key ] = $handler;
					}
				}
				
				if ( isset( \IPS\Request::i()->_mfaMethod ) and array_key_exists( \IPS\Request::i()->_mfaMethod, $configuredHandlers ) )
				{
					return static::_showHandlerAuthScreen( $configuredHandlers[ \IPS\Request::i()->_mfaMethod ], $url->setQueryString( array( '_mfa' => 'alt', '_mfaMethod' => \IPS\Request::i()->_mfaMethod ) ), $member );
				}
				
				/* Display */
				$knownDevicesAvailable = FALSE;
				if ( $app === 'core' and $area === 'AuthenticateFront' and !\in_array( "app_AuthenticateFrontKnown", explode( ',', \IPS\Settings::i()->security_questions_areas ) ) )
				{
					if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_members_known_devices', array( 'member_id=?', $member->member_id ) )->first() )
					{
						$knownDevicesAvailable = TRUE;
					}
				} 
				
				return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->mfaRecovery( $configuredHandlers, $url, $knownDevicesAvailable );
			}
			elseif ( \IPS\Request::i()->_mfa == 'knownDevice' )
			{
				return \IPS\Theme::i()->getTemplate( 'system', 'core' )->mfaKnownDeviceInfo( $url );
			}
		}
						
		/* Normal authentication form */
		foreach ( $acceptableHandlers as $handler )
		{
			if ( $handler->memberHasConfiguredHandler( $member ) )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( '2fa.css', 'core', 'global' ) );
				return static::_showHandlerAuthScreen( $handler, $url, $member );
			}
		}
		
		/* Setup form */
		$showSetupForm = ( \IPS\Settings::i()->mfa_required_groups == '*' or $member->inGroup( explode( ',', \IPS\Settings::i()->mfa_required_groups ) ) ) ? 'mfa_required_prompt' : 'mfa_optional_prompt';
		if ( \IPS\Settings::i()->$showSetupForm === 'access' or ( $app === 'core' and $area === 'AuthenticateAdmin' and \IPS\Settings::i()->$showSetupForm === 'immediate' ) )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( '2fa.css', 'core', 'global' ) );
			
			/* Did we just submit it? */
			if ( isset( \IPS\Request::i()->mfa_setup ) and isset( \IPS\Request::i()->csrfKey ) )
			{
				\IPS\Session::i()->csrfCheck();
				foreach ( $acceptableHandlers as $key => $handler )
				{
					if ( ( \count( $acceptableHandlers ) == 1 ) or $key == \IPS\Request::i()->mfa_method )
					{
						if ( $handler->configurationScreenSubmit( $member ) )
						{							
							$_SESSION['MFAAuthenticated'] = time();
							return NULL;
						}
						elseif ( $lockedOutScreen = static::_lockedOutScreen( $member ) )
						{
							return $lockedOutScreen;
						}
					}
				}
			}
			
			/* No, show it */			
			return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->mfaSetup( $acceptableHandlers, $member, $url );
		}
	}
	
	/**
	 * Show a handler's authentication screen
	 *
	 * @param	\IPS\MFA\MFAHandler	$handler	The handler to use
	 * @param	\IPS\Http\Url		$url		URL for page
	 * @param	\IPS\Member			$member		The member
	 * @return	string|null
	 */
	protected static function _showHandlerAuthScreen( \IPS\MFA\MFAHandler $handler, \IPS\Http\Url $url, \IPS\Member $member )
	{
		/* Did we just submit it? */
		if ( isset( \IPS\Request::i()->mfa_auth ) )
		{
			\IPS\Session::i()->csrfCheck();
			if ( $handler->authenticationScreenSubmit( $member ) )
			{
				$member->failed_mfa_attempts = 0;
				$member->save();
				$_SESSION['MFAAuthenticated'] = time();
				return NULL;
			}
			else
			{
				$member->failed_mfa_attempts++;
				$member->save();
				
				\IPS\Request::i()->mfa_auth = NULL;
			}
		}
		
		/* No, show it */
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->mfaAuthenticate( $handler->authenticationScreen( $member, $url ), $url );
	}
	
	/**
	 * Show the locked out screen, if necessary
	 *
	 * @param	\IPS\Member			$member		The member
	 * @return	string|null
	 */
	protected static function _lockedOutScreen( \IPS\Member $member )
	{
		if ( $member->failed_mfa_attempts >= \IPS\Settings::i()->security_questions_tries )
		{
			if ( \IPS\Settings::i()->mfa_lockout_behaviour == 'lock' )
			{
				$mfaDetails = $member->mfa_details;
				if ( !isset( $mfaDetails['_lockouttime'] ) )
				{
					$mfaDetails['_lockouttime'] = time();
					$member->mfa_details = $mfaDetails;
					$member->save();
					
					$member->logHistory( 'core', 'login', array( 'type' => 'mfalock', 'count' => $member->failed_mfa_attempts, 'unlockTime' => \IPS\DateTime::create()->add( new \DateInterval( 'PT' . \IPS\Settings::i()->mfa_lockout_time . 'M' ) )->getTimestamp() ) );
				}
								
				$lockEndTime = \IPS\DateTime::ts( $mfaDetails['_lockouttime'] )->add( new \DateInterval( 'PT' . \IPS\Settings::i()->mfa_lockout_time . 'M' ) );
				if ( $lockEndTime->getTimestamp() < time() )
				{
					unset( $mfaDetails['_lockouttime'] );
					$member->mfa_details = $mfaDetails;
					$member->failed_mfa_attempts = 0;
					$member->save();
				} 
				else
				{					
					return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->mfaLockout( ( \IPS\Settings::i()->mfa_lockout_time > 1440 ) ? $lockEndTime : $lockEndTime->localeTime( FALSE ) );
				}
			}
			else
			{
				if ( $member->failed_mfa_attempts == \IPS\Settings::i()->security_questions_tries )
				{
					$member->logHistory( 'core', 'login', array( 'type' => 'mfalock', 'count' => $member->failed_mfa_attempts ) );
				}
				return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->mfaLockout();
			}
		}
	}
	
	/* !Setup */
	
	/**
	 * Handler is enabled
	 *
	 * @return	bool
	 */
	abstract public function isEnabled();
	
	/**
	 * Member *can* use this handler (even if they have not yet configured it)
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	abstract public function memberCanUseHandler( \IPS\Member $member );
	
	/**
	 * Member has configured this handler
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	abstract public function memberHasConfiguredHandler( \IPS\Member $member );
		
	/**
	 * Show a setup screen
	 *
	 * @param	\IPS\Member		$member						The member
	 * @param	bool			$showingMultipleHandlers	Set to TRUE if multiple options are being displayed
	 * @param	\IPS\Http\Url	$url						URL for page
	 * @return	string
	 */
	abstract public function configurationScreen( \IPS\Member $member, $showingMultipleHandlers, \IPS\Http\Url $url );
	
	/**
	 * Submit configuration screen. Return TRUE if was accepted
	 *
	 * @param	\IPS\Member		$member	The member
	 * @return	bool
	 */
	abstract public function configurationScreenSubmit( \IPS\Member $member );
	
	/* !Authentication */
	
	/**
	 * Get the form for a member to authenticate
	 *
	 * @param	\IPS\Member		$member		The member
	 * @param	\IPS\Http\Url	$url		URL for page
	 * @return	string
	 */
	abstract public function authenticationScreen( \IPS\Member $member, \IPS\Http\Url $url );
	
	/**
	 * Submit authentication screen. Return TRUE if was accepted
	 *
	 * @param	\IPS\Member		$member	The member
	 * @return	string
	 */
	abstract public function authenticationScreenSubmit( \IPS\Member $member );
	
	/* !ACP */
	
	/**
	 * Toggle
	 *
	 * @param	bool	$enabled	On/Off
	 * @return	bool
	 */
	abstract public function toggle( $enabled );
	
	/**
	 * ACP Settings
	 *
	 * @return	string
	 */
	abstract public function acpSettings();
	
	/**
	 * Configuration options when editing member account in ACP
	 *
	 * @param	\IPS\Member			$member		The member
	 * @return	array
	 */
	public function acpConfiguration( \IPS\Member $member )
	{
		if ( $this->memberHasConfiguredHandler( $member ) )
		{
			return array( new \IPS\Helpers\Form\YesNo( "mfa_{$this->key}_title", $this->memberHasConfiguredHandler( $member ), FALSE, array(), NULL, NULL, NULL, "mfa_{$this->key}_title" ) );
		}
		return array();
	}
	
	/**
	 * Save configuration when editing member account in ACP
	 *
	 * @param	\IPS\Member		$member		The member
	 * @param	array			$values		Values from form
	 * @return	array
	 */
	public function acpConfigurationSave( \IPS\Member $member, $values )
	{
		if ( isset( $values["mfa_{$this->key}_title"] ) and !$values["mfa_{$this->key}_title"] )
		{
			if ( isset( $member->mfa_details[ $this->key ] ) and $this->memberHasConfiguredHandler( $member ) )
			{
				$this->disableHandlerForMember( $member );
			}
		}
	}
	
	/* !Misc */
	
	/**
	 * If member has configured this handler, disable it
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	abstract public function disableHandlerForMember( \IPS\Member $member );
	
	/**
	 * Get title for UCP
	 *
	 * @return	string
	 */
	public function ucpTitle()
	{
		return \IPS\Member::loggedIn()->language()->addToStack("mfa_{$this->key}_title");
	}
	
	/**
	 * Get description for UCP
	 *
	 * @return	string
	 */
	public function ucpDesc()
	{
		return \IPS\Member::loggedIn()->language()->addToStack("mfa_{$this->key}_desc_user");
	}
	
	/**
	 * Get label for recovery button
	 *
	 * @return	string
	 */
	public function recoveryButton()
	{
		return \IPS\Member::loggedIn()->language()->addToStack("mfa_recovery_{$this->key}");
	}
	
}