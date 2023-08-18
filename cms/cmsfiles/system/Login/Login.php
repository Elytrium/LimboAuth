<?php
/**
 * @brief		Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Login Handler
 */
class _Login
{
	/**
	 * @brief	Front end login form
	 */
	const LOGIN_FRONT = 1;

	/**
	 * @brief	AdminCP login form
	 */
	const LOGIN_ACP = 2;

	/**
	 * @brief	Login form shown on registration form
	 */
	const LOGIN_REGISTRATION_FORM = 3;

	/**
	 * @brief	Requesting reauthentication
	 */
	const LOGIN_REAUTHENTICATE = 4;

	/**
	 * @brief	Reauthentication required for account changes
	 */
	const LOGIN_UCP = 5;

	/**
	 * @brief	Using username for login
	 */
	const AUTH_TYPE_USERNAME	= 1;

	/**
	 * @brief	Using email for login
	 */
	const AUTH_TYPE_EMAIL		= 2;

	/**
	 * @brief	Username/password form
	 */
	const TYPE_USERNAME_PASSWORD = 1;

	/**
	 * @brief	Button form (i.e. for OAuth-style logins)
	 */
	const TYPE_BUTTON = 2;
	
	/**
	 * @brief	URL
	 */
	public $url;
		
	/**
	 * @brief	Login form type
	 */
	public $type;
	
	/**
	 * @brief	Reauthenticating member
	 */
	public $reauthenticateAs;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url		The URL page for the login screen
	 * @param	int				$type		One of the LOGIN_* constants
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url = NULL, $type=1 )
	{
		$this->url = $url;
		$this->type = $type;
		
		if ( $type === static::LOGIN_REAUTHENTICATE or $type === static::LOGIN_UCP )
		{
			$this->reauthenticateAs = \IPS\Member::loggedIn();
		}
	}
	
	/* !Methods */
	
	/**
	 * Get methods
	 *
	 * @return	array
	 */
	public static function methods()
	{
		$return = array();
		foreach ( static::getStore() as $row )
		{
			try
			{
				$return[ $row['login_id'] ] = \IPS\Login\Handler::constructFromData( $row );
			}
			catch ( \OutOfRangeException $e ) { }
		}
		return $return;
	}

	/**
	 * Login method Store
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->loginMethods ) )
		{
			\IPS\Data\Store::i()->loginMethods = iterator_to_array( \IPS\Db::i()->select( '*', 'core_login_methods', array( 'login_enabled=1' ), 'login_order' )->setKeyField( 'login_id' ) );
		}
		
		return \IPS\Data\Store::i()->loginMethods;
	}
	
	/**
	 * Get methods
	 *
	 * @return	array
	 */
	protected function _methods()
	{
		$methods = array();
		foreach ( static::methods() as $k => $method )
		{
			if (
				( $this->type === static::LOGIN_FRONT and $method->front )
				or ( $this->type === static::LOGIN_UCP and $method->front )
				or ( $this->type === static::LOGIN_ACP and $method->acp )
				or ( $this->type === static::LOGIN_REGISTRATION_FORM and $method->register )
				or ( $this->type === static::LOGIN_REAUTHENTICATE and $method->canProcess( $this->reauthenticateAs ) and !( $method instanceof \IPS\Login\LoginAbstract ) )
			) {
				$methods[ $k ] = $method;
			}
		}
		
		return $methods;
	}
	
	/**
	 * Get methods which use a username and password
	 *
	 * @return	array
	 */
	public function usernamePasswordMethods()
	{
		$return = array();
		foreach ( $this->_methods() as $method )
		{
			if ( $method->type() === static::TYPE_USERNAME_PASSWORD )
			{
				$return[ $method->id ] = $method;
			}
		}
		return $return;
	}
	
	/**
	 * Should the username/password form ask for username or email address?
	 *
	 * @return	array
	 */
	public function authType()
	{
		$authType = 0;
		foreach ( $this->usernamePasswordMethods() as $method )
		{
			$authType = $authType | $method->authType();
		}
		return $authType;
	}
	
	/**
	 * Get methods which use a button
	 *
	 * @return	array
	 */
	public function buttonMethods()
	{
		$return = array();
		foreach ( $this->_methods() as $method )
		{
			if ( $method->type() === static::TYPE_BUTTON )
			{
				$return[ $method->id ] = $method;
			}
		}
		return $return;
	}
	
	/* !Authentication */
	
	/**
	 * Authenticate
	 *
	 * @param	\IPS\Login\Handler\NULL	$onlyCheck	If provided, will only check the given method
	 * @return	\IPS\Login\Success|NULL
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticate( \IPS\Login\Handler $onlyCheck = NULL )
	{
		try
		{
			if ( isset( \IPS\Request::i()->_processLogin ) )
			{
				\IPS\Session::i()->csrfCheck();
				
				/* Username/Password */
				if ( \IPS\Request::i()->_processLogin === 'usernamepassword' )
				{
					$leastOffensiveException = NULL;
					$success = NULL;
					$fails = array();
					$failsNoAccount = array();
					
					foreach ( $this->usernamePasswordMethods() as $method )
					{
						if ( !$onlyCheck or $method->id == $onlyCheck->id )
						{
							try
							{
								if ( $this->type === static::LOGIN_REAUTHENTICATE )
								{
									if ( $method->authenticatePasswordForMember( $this->reauthenticateAs, \IPS\Request::i()->protect('password') ) )
									{
										$member = $this->reauthenticateAs;
									}
									else
									{
										throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_bad_password', FALSE, array( 'pluralize' => array( $this->authType() ) ) ), \IPS\Login\Exception::BAD_PASSWORD, NULL, $this->reauthenticateAs );
									}
								}
								else
								{
									$member = $method->authenticateUsernamePassword( $this, \IPS\Request::i()->auth, \IPS\Request::i()->protect('password') );
									if ( $member === TRUE )
									{
										$member = $this->reauthenticateAs;
									}
								}
								
								if ( $member )
								{
									static::checkIfAccountIsLocked( $member, TRUE );
									$success = new Login\Success( $member, $method, isset( \IPS\Request::i()->remember_me ) );
									break;
								}
							}
							catch ( \IPS\Login\Exception $e )
							{
								if ( $e->getCode() === \IPS\Login\Exception::BAD_PASSWORD and $e->member )
								{
									$fails[ $e->member->member_id ] = $e->member;
								}
								elseif( $e->getCode() === \IPS\Login\Exception::NO_ACCOUNT and $e->member AND $e->member->email )
								{
									$failsNoAccount[ $e->member->email ] = $e->member;
								}
								
								if ( $leastOffensiveException === NULL or $leastOffensiveException->getCode() < $e->getCode() )
								{
									$leastOffensiveException = $e;
								}
							}
						}
					}
					
					foreach ( $fails as $failedMember )
					{
						if ( !$success or $success->member->member_id != $failedMember->member_id )
						{
							$failedLogins = \is_array( $failedMember->failed_logins ) ? $failedMember->failed_logins : array();
							$failedLogins[ \IPS\Request::i()->ipAddress() ][] = time();
							$failedMember->failed_logins = $failedLogins;
							$failedMember->save();
						}
					}

					foreach( $failsNoAccount as $failedMember )
					{
						if ( !$success or $success->member->email != $failedMember->email )
						{
							try
							{
								$failedLogins = \is_array( \IPS\Data\Store::i()->failedLogins ) ? \IPS\Data\Store::i()->failedLogins : array();
							}
							catch( \OutOfRangeException $e )
							{
								$failedLogins = array();
							}

							$failedLogins[ $failedMember->email ][ \IPS\Request::i()->ipAddress() ][] = time();
							\IPS\Data\Store::i()->failedLogins = $failedLogins;
						}
					}
					
					if ( $success )
					{
						return $success;
					}
					elseif ( $leastOffensiveException )
					{
						throw $leastOffensiveException;
					}
					else
					{
						throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::NO_ACCOUNT );
					}
				}
				/* Buttons */
				elseif ( isset( $this->buttonMethods()[ \IPS\Request::i()->_processLogin ] ) and ( !$onlyCheck or $onlyCheck->id == $this->buttonMethods()[ \IPS\Request::i()->_processLogin ]->id ) )
				{
					$method = $this->_methods()[ \IPS\Request::i()->_processLogin ];
					
					if ( $member = $method->authenticateButton( $this ) )
					{
						if ( $this->type === static::LOGIN_REAUTHENTICATE and $member !== $this->reauthenticateAs )
						{
							throw new \IPS\Login\Exception( 'login_err_wrong_account', \IPS\Login\Exception::BAD_PASSWORD );
						}
						
						static::checkIfAccountIsLocked( $member, TRUE );
						return new Login\Success( $member, $method );
					}
				}
			}
			/* Backwards Compatibility for login handlers created before 4.3 */
			elseif ( isset( \IPS\Request::i()->loginProcess ) )
			{
				foreach ( $this->buttonMethods() as $method )
				{
					if ( $method instanceof \IPS\Login\LoginAbstract and \get_class( $method ) === 'IPS\Login\\' . mb_ucfirst( \IPS\Request::i()->loginProcess ) and ( !$onlyCheck or $method->id == $onlyCheck->id ) )
					{
						if ( $member = $method->authenticateButton( $this ) )
						{
							static::checkIfAccountIsLocked( $member, TRUE );
							return new Login\Success( $member, $method, isset( \IPS\Request::i()->remember_me ) );
						}
					}
				}
			}
		}
		catch ( \IPS\Login\Exception $e )
		{
			/* If we're about to say the password is incorrect, check if the account is locked and throw that error rather than a bad password error first */
			if ( $e->getCode() === \IPS\Login\Exception::BAD_PASSWORD and $e->member )
			{
				static::checkIfAccountIsLocked( $e->member );
			}
			/* Or if the account doesn't exist but we've tried the brute-force number of times, show the account is locked even though it doesn't exist */
			elseif( $e->getCode() === \IPS\Login\Exception::NO_ACCOUNT and $e->member AND $e->member->email )
			{
				static::checkIfAccountIsLocked( $e->member );
			}

			/* If we're still here, throw the error we got */
			throw $e;
		}
	}
	
	/* !Account Management Utility Methods */
	
	/**
	 * After authentication (successful or failed) but before
	 * processing the login, check if the account is locked
	 *
	 * @param	\IPS\Member	$member		The account
	 * @param	bool		$success	Boolean value indicating if the login was successful. If TRUE, and the account is not locked, failed logins will be removed.
	 * @note	The $member object may be a guest object with an email address set
	 * @return	void
	 * @throws	\Exception
	 */
	public static function checkIfAccountIsLocked( $member, $success = FALSE )
	{
		$unlockTime = $member->unlockTime();
		if ( $unlockTime !== FALSE )
		{
			/* Notify the member if they've been locked */
			if( $member->failedLoginCount( \IPS\Request::i()->ipAddress() ) == \IPS\Settings::i()->ipb_bruteforce_attempts )
			{
				/* Can we get a physical location */
				try
				{
					$location = \IPS\GeoLocation::getByIp( \IPS\Request::i()->ipAddress() );
				}
				catch ( \Exception $e )
				{
					$location = \IPS\Request::i()->ipAddress();
				}

				if( $member->member_id )
				{
					\IPS\Email::buildFromTemplate( 'core', 'account_locked', array( $member, $location, isset( $unlockTime ) ? $unlockTime : NULL ), \IPS\Email::TYPE_TRANSACTIONAL )->send( $member );
					$member->logHistory( 'core', 'login', array( 'type' => 'lock', 'count' => \count( $member->failed_logins[ \IPS\Request::i()->ipAddress() ] ), 'unlockTime' => isset( $unlockTime ) ? $unlockTime->getTimestamp() : NULL ) );
				}
			}

			if ( \IPS\Settings::i()->ipb_bruteforce_period and \IPS\Settings::i()->ipb_bruteforce_unlock )
			{
				$diffValue = $unlockTime->diff( new DateTime() )->format('%i') ?: 1;

				throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_locked_unlock', FALSE, array( 'pluralize' => array( $diffValue ) ) ), \IPS\Login\Exception::ACCOUNT_LOCKED );
			}
			else
			{
				throw new \IPS\Login\Exception( 'login_err_locked_nounlock', \IPS\Login\Exception::ACCOUNT_LOCKED );
			}
		}
		elseif ( $success )
		{
			$failedLogins = \is_array( $member->failed_logins ) ? $member->failed_logins : array();
			unset( $failedLogins[ \IPS\Request::i()->ipAddress() ] );
			$member->failed_logins = $failedLogins;
			$member->save();
		}
	}

	/**
	 * Check if a given username is allowed
	 *
	 * @param 	string 	$username	Desired username
	 * @return 	bool
	 */
	public static function usernameIsAllowed( string $username ): bool
	{
		if( \IPS\Settings::i()->username_characters and !preg_match( \IPS\Settings::i()->username_characters, $username ) )
		{
			return FALSE;
		}
		return TRUE;
	}
	
	/**
	 * Check if a given username is in use
	 * Returns string with error message or FALSE if not in use
	 *
	 * @param	string		$username	Desired username
	 * @param	\IPS\Member	$exclude	If provided, that member will be excluded from the check
	 * @param	\IPS\Member	$admin		Boolean value indicating if error message can include details about which login method has claimed it
	 * @return	string|false
	 */
	public static function usernameIsInUse( $username, $exclude = NULL, $admin = FALSE )
	{
		/* Check locally */
		$existingMember = \IPS\Member::load( $username, 'name' );
		if ( $existingMember->member_id and ( !$exclude or $exclude->member_id != $existingMember->member_id ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('member_name_exists');
		}
		
		/* Check each handler */
		foreach( static::methods() as $k => $handler )
		{
			if( $handler->usernameIsInUse( $username, $exclude ) === TRUE )
			{
				if( $admin )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'member_name_exists_admin', FALSE, array('sprintf' => array( $handler->_title ) ) );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('member_name_exists');
				}
			}
		}
		
		/* Still here? We're good */
		return FALSE;
	}
	
	/**
	 * Check if a given email address is in use
	 * Returns string with error message or FALSE if not in use
	 *
	 * @param	string		$email		Desired email address
	 * @param	\IPS\Member	$exclude	If provided, that member will be excluded from the check
	 * @param	\IPS\Member	$admin		Boolean value indicating if error message can include details about which login method has claimed it
	 * @return	string|false
	 */
	public static function emailIsInUse( $email, $exclude = NULL, $admin = FALSE )
	{
		/* Check locally */
		$existingMember = \IPS\Member::load( $email, 'email' );
		if ( $existingMember->member_id and ( !$exclude or $exclude->member_id != $existingMember->member_id ) )
		{
			$url = \IPS\Http\Url::internal( "app=core&module=system&controller=lostpass", "front", "lostpassword" );
			return \IPS\Member::loggedIn()->language()->addToStack( 'member_email_exists', FALSE, array( 'sprintf' => array( (string) $url ) ) );
		}
		
		/* Check each handler */
		foreach( static::methods() as $k => $handler )
		{
			if( $handler->emailIsInUse( $email, $exclude ) === TRUE )
			{
				if( $admin )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'member_email_exists_admin', FALSE, array('sprintf' => array( $handler->_title ) ) );
				}
				else
				{
					$url = \IPS\Http\Url::internal( "app=core&module=system&controller=lostpass", "front", "lostpassword" );
					return \IPS\Member::loggedIn()->language()->addToStack( 'member_email_exists', FALSE, array( 'sprintf' => array( (string) $url ) ) );
				}
			}
		}
		
		/* Still here? We're good */
		return FALSE;
	}
	
	/* !Misc Utility Methods */
	
	/**
	 * Compare hashes in fixed length, time constant manner.
	 *
	 * @param	string	$expected	The expected hash
	 * @param	string	$provided	The provided input
	 * @return	boolean
	 */
	public static function compareHashes( $expected, $provided )
	{
		if ( !\is_string( $expected ) || !\is_string( $provided ) || $expected === '*0' || $expected === '*1' || $provided === '*0' || $provided === '*1' ) // *0 and *1 are failures from crypt() - if we have ended up with an invalid hash anywhere, we will reject it to prevent a possible vulnerability from deliberately generating invalid hashes
		{
			return FALSE;
		}
	
		$len = \strlen( $expected );
		if ( $len !== \strlen( $provided ) )
		{
			return FALSE;
		}
	
		$status = 0;
		for ( $i = 0; $i < $len; $i++ )
		{
			$status |= \ord( $expected[ $i ] ) ^ \ord( $provided[ $i ] );
		}
		
		return $status === 0;
	}
	
	/**
	 * Return a random string
	 *
	 * @param	int		$length		The length of the final string
	 * @return	string
	 */
	public static function generateRandomString( $length=32 )
	{
		$return = '';

		if ( \function_exists( 'random_bytes' ) )
		{
			$return = \substr( bin2hex( random_bytes( $length ) ), 0, $length );
		}
		elseif( \function_exists( 'openssl_random_pseudo_bytes' ) )
		{
			$return = \substr( bin2hex( openssl_random_pseudo_bytes( ceil( $length / 2 ) ) ), 0, $length );
		}

		/* Fallback JUST IN CASE */
		if( !$return OR \strlen( $return ) != $length )
		{
			$return = \substr( md5( uniqid( '', true ) ) . md5( uniqid( '', true ) ), 0, $length );
		}

		return $return;
	}
	
	/**
	 * @brief	Cached registration type
	 */
	protected static $_registrationType = NULL;
	
	/**
	 * Registration Type
	 *
	 * @return	string
	 */
	public static function registrationType()
	{
		if ( static::$_registrationType === NULL )
		{
			/* If registrations are enabled */
			if ( \IPS\Settings::i()->allow_reg )
			{
				switch( \IPS\Settings::i()->allow_reg )
				{
					// just kept this here for legacy reasons, even if we have an upgrade step to change this now
					case 1 :
					case 'full':
						static::$_registrationType ='full';
						break;
					default:
						return \IPS\Settings::i()->allow_reg;
				}
			}
			else
			{
				static::$_registrationType = 'disabled';
			}

			if ( \in_array( static::$_registrationType, array( 'normal', 'full' ) ) and !\IPS\Login\Handler::findMethod( 'IPS\Login\Handler\Standard' ) )
			{
				static::$_registrationType = 'disabled';
			}
		}

		return static::$_registrationType;
	}
	
	/**
	 * Log a user out
	 *
	 * @param	\IPS\Http\Url	$redirectUrl	The URL the user will be redirected to after logging out
	 * @return	void
	 */
	public static function logout( \IPS\Http\Url $redirectUrl = NULL )
	{
		/* Do not allow the login_key to be re-used */
		if ( isset( \IPS\Request::i()->cookie['device_key'] ) )
		{
			try
			{
				$device = \IPS\Member\Device::loadAndAuthenticate( \IPS\Request::i()->cookie['device_key'], \IPS\Member::loggedIn() );
				$device->login_key = NULL;
				$device->save();
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		/* Clear cookies */
		\IPS\Request::i()->clearLoginCookies();

		/* Destroy the session (we have to explicitly reset the session cookie, see http://php.net/manual/en/function.session-destroy.php) */
		$_SESSION = array();
		$params = session_get_cookie_params();
		setcookie( session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"] );
		session_destroy();
		session_start();

		/* Member sync callback */
		\IPS\Member::loggedIn()->memberSync( 'onLogout', array( $redirectUrl ?: \IPS\Http\Url::internal('') ) );
	}
}