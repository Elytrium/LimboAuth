<?php
/**
 * @brief		Known Member Device Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Mar 2017
 */

namespace IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Known Member Device Model
 */
class _Device extends \IPS\Patterns\ActiveRecord
{	
	/**
	 * @brief	Login keys are valid for 3 months
	 */
	const LOGIN_KEY_VALIDITY = 'P3M';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_members_known_devices';
		
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'device_key';
	
	/**
	 * @brief	Is this a known device?
	 */
	public $known = TRUE;
	
	/**
	 * Load device for current request, or create one if there isn't one
	 *
	 * @param	\IPS\Member	$member				The member being authenticated
	 * @param	bool		$sendNewDeviceEmail	If true, and the associated setting is enabled, and this is a new device, an email will be sent to the member. Only set to FALSE if the user is registering and while the MFA where the email would be redundant.
	 * @return	\IPS\Member\Device
	 */
	public static function loadOrCreate( \IPS\Member $member, $sendNewDeviceEmail=TRUE )
	{
		if ( isset( \IPS\Request::i()->cookie['device_key'] ) and mb_strlen( \IPS\Request::i()->cookie['device_key'] ) === 32 )
		{
			try
			{
				$device = static::loadAndAuthenticate( \IPS\Request::i()->cookie['device_key'], $member );
			}
			catch ( \OutOfRangeException $e )
			{
				$device = new static;
				$device->known = FALSE;
				$device->device_key = \IPS\Request::i()->cookie['device_key'];
				$device->member_id = $member->member_id;
				
				if ( $sendNewDeviceEmail )
				{
					$device->sendNewDeviceEmail();
				}
			}
		}
		else
		{
			$device = static::createNew();
			$device->known = FALSE;
			$device->member_id = $member->member_id;
			
			if ( $sendNewDeviceEmail )
			{
				$device->sendNewDeviceEmail();
			}
		}
		
		\IPS\Request::i()->setCookie( 'device_key', $device->device_key, ( new \IPS\DateTime )->add( new \DateInterval( 'P1Y' ) ) );
		
		return $device;
	}
	
	/**
	 * Load, but only if it is valid for a particular member and optionally login key
	 *
	 * @param	string		$deviceId	The device ID
	 * @param	\IPS\Member	$member		The member
	 * @param	string|null	$loginKey	If you also want to authenticate by login key, the login key to check
	 * @return	\IPS\Member\Device
	 * @throws	\OutOfRangeException
	 */
	public static function loadAndAuthenticate( $deviceId, \IPS\Member $member, $loginKey = NULL )
	{
		/* Load the device */
		$device = static::load( $deviceId, NULL, array( 'member_id=?', $member->member_id ) );
		
		/* Check the login key is valid */
		if ( $loginKey !== NULL )
		{
			/* Login keys expire after 3 months - if it has been more than 3 months since it was generted, do not authenticate */
			if ( $device->last_seen < ( new \IPS\DateTime )->sub( new \DateInterval( static::LOGIN_KEY_VALIDITY ) )->getTimestamp() )
			{
				throw new \OutOfRangeException;
			}
			
			/* Validate login key is valid - if there is no login_key set for the device, it is because the device has been deauthorized */
			if ( !$device->login_key OR !\IPS\Login::compareHashes( (string) $device->login_key, (string) $loginKey ) )
			{
				throw new \OutOfRangeException;
			}
		}
		
		/* Return */		
		return $device;
	}
	
	/**
	 * Create a new device with unique key
	 *
	 * @return	\IPS\Member\Device
	 */
	public static function createNew()
	{
		do
		{
			$deviceKey = \IPS\Login::generateRandomString();
			
			try
			{
				\IPS\Db::i()->select( 'device_key', 'core_members_known_devices', array( 'device_key=?', $deviceKey ) )->first();
				$generatedDeviceKeyInUse = TRUE;
			}
			catch ( \UnderflowException $e )
			{
				$generatedDeviceKeyInUse = FALSE;
			}
		}
		while ( $generatedDeviceKeyInUse );
		
		$object = new self;
		$object->device_key = $deviceKey;
		return $object;
	}

	/**
	 * Update after logging in / automatic authentication with current request's user agent / IP address, etc.
	 *
	 * @param	bool|null			$rememberMe			Remember me? NULL can be provided if the login is being processed from somewhere that doesn't ask
	 * @param	\IPS\Login\Handler	$loginHandler		The login handler which processed the login, or NULL if updating an existing login. Can also be empty string for logins that weren't processed by any handler (such as after registration or using the lost password feature)
	 * @param	bool				$refreshLoginKey	If login key should be refreshed (FALSE for automatic logins which will need the same login key subsequently)
	 * @param	book				$setCookies			Should the cookies be set ( FALSE for ACP login )
	 * @return	void
	 */
	public function updateAfterAuthentication( $rememberMe, \IPS\Login\Handler $loginHandler=NULL, $refreshLoginKey=TRUE, $setCookies = TRUE )
	{
		$this->user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : NULL;
		if ( $refreshLoginKey )
		{
			$this->login_key = $rememberMe ? \IPS\Login::generateRandomString() : NULL;
		}
		$this->last_seen = time();
		if ( $loginHandler !== NULL )
		{
			$this->login_handler = $loginHandler->id;
		}
		$this->save();
		
		$this->logIpAddress( \IPS\Request::i()->ipAddress() );

		if ( $setCookies )
		{
			$cookieExpiration = ( new \IPS\DateTime )->add( new \DateInterval( static::LOGIN_KEY_VALIDITY ) );
			if ( $rememberMe === NULL )
			{
				\IPS\Request::i()->setCookie( 'member_id', $this->member_id, $cookieExpiration );
				\IPS\Request::i()->setCookie( 'loggedIn', time(), $cookieExpiration, FALSE );
			}
			elseif ( $rememberMe === TRUE )
			{
				\IPS\Request::i()->setCookie( 'member_id', $this->member_id, $cookieExpiration );
				\IPS\Request::i()->setCookie( 'login_key', $this->login_key, $cookieExpiration );
				\IPS\Request::i()->setCookie( 'loggedIn', time(), $cookieExpiration, FALSE );
			}
			else
			{
				\IPS\Request::i()->setCookie( 'member_id', $this->member_id, NULL ); // Just tells the guest caching mechanism that we are logged in, so it can expire on the session end
				\IPS\Request::i()->setCookie( 'login_key', NULL ); // Clear it in case they previously had chosen "Remember Me"
				\IPS\Request::i()->setCookie( 'loggedIn', time(), $cookieExpiration, FALSE );
			}
		}
	}
	
	/**
	 * Log an IP address as been having used by this devuce
	 *
	 * @param	string	$ipAddress	The IP Address
	 * @return	void
	 */
	public function logIpAddress( $ipAddress )
	{
		\IPS\Db::i()->insert( 'core_members_known_ip_addresses', array(
			'device_key'	=> $this->device_key,
			'member_id'		=> $this->member_id,
			'ip_address'	=> $ipAddress,
			'last_seen'		=> time()
		), TRUE );
	}
	
	/**
	 * Get user agent data
	 *
	 * @return	\IPS\Http\Useragent
	 */
	public function userAgent()
	{
		return \IPS\Http\Useragent::parse( $this->user_agent );
	}
	
	/**
	 * Get login method
	 *
	 * @return	\IPS\Login\Handler|NULL
	 */
	public function loginMethod()
	{
		if ( \is_numeric( $this->login_handler ) )
		{
			try
			{
				return \IPS\Login\Handler::load( $this->login_handler );
			}
			catch ( \OutOfRangeException $e )
			{
				return NULL;
			}
		}
		return NULL;
	}
	
	/**
	 * Send email to user notifying them about the new device
	 *
	 * @return	void
	 */
	protected function sendNewDeviceEmail()
	{
		$member = \IPS\Member::load( $this->member_id );

		if ( \IPS\Settings::i()->new_device_email and $member->members_bitoptions['new_device_email'] )
		{			
			try
			{
				$location = \IPS\GeoLocation::getByIp( \IPS\Request::i()->ipAddress() );
			}
			catch ( \Exception $e )
			{
				$location = NULL;
			}
			
			\IPS\Email::buildFromTemplate( 'core', 'new_device', array( $member, $this, $location ), \IPS\Email::TYPE_TRANSACTIONAL )->send( $member );
		}
		
		$member->logHistory( 'core', 'login', array( 'type' => 'new_device', 'device' => $this->device_key, 'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : NULL ), FALSE );
	}
	
	/**
	 * Get the WHERE clause for save()
	 *
	 * @return	void
	 */
	protected function _whereClauseForSave()
	{
		return array( 'device_key=? AND member_id=?', $this->device_key, $this->member_id );
	}
}