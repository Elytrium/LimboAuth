<?php
/**
 * @brief		Session Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Mar 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Session Handler
 */
abstract class _Session
{
	/**
	 * @brief	Singleton Instance
	 */
	protected static $instance = NULL;

	/**
	 * @brief	User agent information
	 * @see		\IPS\Http\Useragent::parse()
	 */
	public $userAgent	= NULL;

	/**
	 * @brief	Session record - stored so plugins can access
	 */
	protected $sessionData	= NULL;

	/**
	 * Get instance
	 *
	 * @return	static
	 */
	public static function i()
	{
		if( static::$instance === NULL )
		{
			$classname = \get_called_class();

			if ( $classname === 'IPS\Session' )
			{
				if( class_exists( 'IPS\Dispatcher', FALSE ) )
				{
					$location = ( \IPS\Dispatcher::hasInstance() ) ? mb_ucfirst( \IPS\Dispatcher::i()->controllerLocation ) : 'Front';
					$classname = 'IPS\Session\\' . $location;
				}
				else
				{
					throw new \RuntimeException('LOCATION_UNKNOWN');
				}
			}
			else
			{
				$location = \substr( $classname, 12 );
			}
			
			if ( class_exists( $classname ) )
			{
				/* Create class */
				static::$instance = new $classname;

				/* Remove PHPs own cache headers */
				session_cache_limiter('');
				
				/* Name the session */
				$name = session_name( ( \IPS\COOKIE_PREFIX !== NULL ) ? \IPS\COOKIE_PREFIX . 'IPSSession' . $location : 'IPSSession' . $location );

				/* Set the handler */
				session_write_close();
				session_set_save_handler( array( static::$instance, 'open' ), array( static::$instance, 'close' ), array( static::$instance, 'read' ), array( static::$instance, 'write' ), array( static::$instance, 'destroy' ), array( static::$instance, 'gc' ) );
				
				/* Make sure we use HTTP-Only cookies */
				session_set_cookie_params( 
					'0', 
					( \IPS\COOKIE_PATH !== NULL ) ? \IPS\COOKIE_PATH : '/',
					( \IPS\COOKIE_DOMAIN !== NULL ) ? \IPS\COOKIE_DOMAIN : '',
					( \IPS\COOKIE_BYPASS_SSLONLY !== TRUE ) ? ( mb_substr( \IPS\Settings::i()->base_url, 0, 5 ) == 'https' ) : FALSE,
					TRUE
				);

				/* Start */
				session_start();
				
				/* Init */
				static::$instance->init();
				
				/* Register shutdown */
				register_shutdown_function('session_write_close');
			}
			else
			{
				static::$instance = new \StdClass;
				static::$instance->member = new \IPS\Member;
				static::$instance->csrfKey = '';

				/* Upgrader starts session already */
				if ( !\IPS\Dispatcher::hasInstance() or !\IPS\Dispatcher::i() instanceof \IPS\Dispatcher\Setup )
                {
					if( session_status() !== PHP_SESSION_ACTIVE )
					{
						session_start();
					}
				}
			}
		}
		
		return static::$instance;
	}
	
	/**
	 * @brief	Session ID
	 */
	public $id = NULL;
		
	/**
	 * @brief	Currently logged in member
	 */
	public $member = NULL;

	/**
	 * @brief	CSRF Key
	 */
	public $csrfKey = '';
	
	/**
	 * @brief	Validation Error
	 */
	public $error = NULL;

	/**
	 * Set Session Member
	 *
	 * @param	\IPS\Member	$member	Member object
	 * @return	void
	 */
	public function setMember( $member )
	{
		/* PHP 7.0.2 had a bug reported where session_regenerate_id() does not close opened sessions properly and in some situations can cause PHP to hang or crash.
		 * This issue is fixed in PHP 7.1.0 - https://bugs.php.net/bug.php?id=71394 */
		session_regenerate_id();

		/* Update our new session id */
		$this->id = session_id();
		
		$_SESSION['forcedWrite'] = time();
		$this->member = $member;

		/* Update CSRF Key based on new data */
		$this->regenerateCsrfKey();
	}

	/**
	 * Init
	 *
	 * @return	void
	 */
	public function init()
	{
		/* Set ID */
		$this->id = session_id();

		/* Create csrf key */
		$this->regenerateCsrfKey();

		/* Update member */
		if ( $this->member->member_id )
		{
			$save = FALSE;

			/* Set the last activity (but not if this is an ajax request or a partially registered member as we delete where last_visit=0) */
			if ( isset( $this->data ) and ! \IPS\Request::i()->isAjax() and ( $this->member->email and $this->member->name ) )
			{
				if ( time() - $this->member->last_activity > 3600 or !$this->member->last_visit )
				{
					$save = TRUE;
					$this->member->last_visit = $this->member->last_activity ?: time();
				}
				if ( time() - $this->member->last_activity > 180 )
				{
					$save = TRUE;
					$this->member->last_activity = time();
				}
			}

			/* Set timezone */
			if ( isset( \IPS\Request::i()->cookie['ipsTimezone'] ) and \IPS\Request::i()->cookie['ipsTimezone'] !== $this->member->timezone and \in_array( \IPS\DateTime::getFixedTimezone( \IPS\Request::i()->cookie['ipsTimezone'] ), \DateTimeZone::listIdentifiers() ) )
			{
				$save = TRUE;
				$this->member->timezone = \IPS\DateTime::getFixedTimezone( \IPS\Request::i()->cookie['ipsTimezone'] );
			}
			
			/* Save */
			if ( $save )
			{
				$this->member->save();
			}
		}
		else
		{
			/* Ensure any loggedIn cookies are removed */
			if( isset( \IPS\Request::i()->cookie['loggedIn'] ) )
			{
				\IPS\Request::i()->setCookie( 'loggedIn', NULL );
			}
		}
	}
	
	/**
	 * Do not update sessions
	 *
	 * @return void
	 */
	public function noUpdate()
	{
		/* Overridden methods do something (or not) */
	}

	/**
	 * CSRF Check
	 *
	 * @return	void
	 */
	public function csrfCheck()
	{
		$token = (string) \IPS\Request::i()->csrfKey;

		/* Guests may provide the csrf token via a header */
		if ( !\IPS\Member::loggedIn()->member_id && isset( $_SERVER['HTTP_X_CSRF_TOKEN'] ) )
		{
			$token = $_SERVER['HTTP_X_CSRF_TOKEN'];
		}

		if ( !\IPS\Login::compareHashes( (string) $this->csrfKey, $token ) )
		{
			\IPS\Output::i()->error( 'generic_error', '2S119/1', 403, 'admin_csrf_error' );
		}
	}
	
	/**
	 * Moderator Log
	 * @code
	 \IPS\Session::i()->modLog( 'modlog__spammer_flagged', array( $this->name => FALSE ) );
	 * @endcode
	 * @param	string	$langKey		Language key for log
	 * @param	array	$params			Key/Values - keys are variables to use in sprintf on $langKey, values are booleans indicating if they are language keys themselves (TRUE) or raw data (FALSE)
	 * @param	\IPS\Content\Item|NULL	$item	If moderation action is specific to an item
	 * @return	void
	 */
	public function modLog( $langKey, $params=array(), $item=null )
	{
		$class = NULL;
		
		if ( $item instanceof \IPS\Content\Item )
		{
			$class = \get_class( $item );			
			$idColumn = $class::$databaseColumnId;
		}	
		
		\IPS\Db::i()->insert( 'core_moderator_logs', array(
				'member_id'		=> \IPS\Member::loggedIn()->member_id,
				'member_name'	=> \IPS\Member::loggedIn()->name,
				'ctime'			=> time(),
				'note'			=> json_encode( $params ),
				'ip_address'	=> \IPS\Request::i()->ipAddress(),
				'appcomponent'	=> \IPS\Dispatcher::i()->application->directory,
				'module'		=> \IPS\Dispatcher::i()->module->key,
				'controller'	=> \IPS\Dispatcher::i()->controller,
				'do'			=> \IPS\Request::i()->do,
				'lang_key'		=> $langKey,
				'class'			=> $class,
				'item_id'		=> $item ? $item->$idColumn : NULL,
		) );
	}

	/**
	 * Regenerate CSRF Key
	 *
	 * @return	void
	 */
	public function regenerateCsrfKey()
	{
		$this->csrfKey = md5( \IPS\SUITE_UNIQUE_KEY . "&{$this->member->email}& " . ( $this->member->member_id ? $this->member->joined->getTimestamp() : 0 ) . '&' . $this->id );
	}

	/**
	 * Return the maximum session lifetime (in seconds)
	 *
	 * @return int
	 */
	public static function sessionLifetime()
	{
		$timeout = 1440;

		if( \function_exists('ini_get') )
		{
			$phpTimeout = @ini_get('session.gc_maxlifetime');
			$timeout	= $phpTimeout ?: $timeout;
		}

		return $timeout;
	}
}