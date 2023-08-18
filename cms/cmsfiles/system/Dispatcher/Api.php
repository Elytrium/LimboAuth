<?php
/**
 * @brief		API Dispatcher
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Dec 2015
 */

namespace IPS\Dispatcher;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	API Dispatcher
 */
class _Api extends \IPS\Dispatcher
{
	/**
	 * @brief Controller Location
	 */
	public $controllerLocation = 'api';
	
	/**
	 * @brief Path
	 */
	public $path = NULL;
	
	/**
	 * @brief Raw API Key
	 */
	public $rawApiKey = NULL;
	
	/**
	 * @brief Raw Access Token
	 */
	public $rawAccessToken = NULL;
	
	/**
	 * @brief API Key Object
	 */
	public $apiKey = NULL;
	
	/**
	 * @brief Access Token Details
	 */
	public $accessToken = NULL;
	
	/**
	 * @brief Language
	 */
	public $language = NULL;

	/**
	 * Can the Response be cached?
	 *
	 * @var bool
	 */
	public bool $cacheResponse = TRUE;
	
	/**
	 * Init
	 *
	 * @return	void
	 * @throws	\DomainException
	 */
	public function init()
	{
		try
		{
			/* Get the path */
			$this->_setPath();
			
			/* Check our IP address isn't banned */
			$this->_checkIpAddressIsAllowed();
			
			/* Set our credentials */
			$this->_setRawCredentials();
			if ( $this->rawAccessToken )
			{
				$this->_setAccessToken();
				if ( !$this->accessToken['scope'] or !json_decode( $this->accessToken['scope'] ) )
				{
					throw new \IPS\Api\Exception( 'NO_SCOPES', '3S290/B', 401, 'insufficient_scope' );
				}
			}
			elseif ( $this->rawApiKey )
			{
				$this->_setApiKey();
			}
			else
			{
				throw new \IPS\Api\Exception( 'NO_API_KEY', '2S290/6', 401 );
			}
			
			/* Set other data */
			$this->_setLanguage();

			/* We don't want to cache any output for Zapier Requests */
			if( \IPS\Request::i()->isZapier() )
			{
				$this->cacheResponse = FALSE;
			}

		}
		catch ( \IPS\Api\Exception $e )
		{
			/* Build response */
			$response = json_encode( array( 'errorCode' => $e->exceptionCode, 'errorMessage' => $e->getMessage() ), JSON_PRETTY_PRINT );
			
			/* Do we need to log this? */
			if ( $this->rawApiKey !== 'test' and \in_array( $e->exceptionCode, array( '2S290/8', '2S290/B', '3S290/7', '3S290/9' ) ) )
			{
				$this->_log( $response, $e->getCode(), \in_array( $e->exceptionCode, array( '3S290/7', '3S290/9', '3S290/B' ) ) );
			}
			
			/* Output */
			$this->_respond( $response, $e->getCode(), $e->oauthError );
		}
	}
	
	/**
	 * Set the path and request data
	 *
	 * @return	void
	 */
	protected function _setPath()
	{
		/* Decode URL */
		if ( \IPS\Settings::i()->use_friendly_urls and \IPS\Settings::i()->htaccess_mod_rewrite and mb_substr( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_PATH ], -14 ) !== '/api/index.php' )
		{
			/* We are using Mod Rewrite URL's, so look in the path */
			$this->path = mb_substr( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_PATH ], mb_strpos( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_PATH ], '/api/' ) + 5 );
			
			/* nginx won't convert the 'fake' query string to $_GET params, so do this now */
			if ( ! empty( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_QUERY ] ) )
			{
				parse_str( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_QUERY ], $params );
				foreach ( $params as $k => $v )
				{
					if ( ! isset( \IPS\Request::i()->$k ) )
					{
						\IPS\Request::i()->$k = $v;
					}
				}
			}
		}
		else
		{
			/* Otherwise we are not, so we need the query string instead, which is actually easier */
			$this->path = \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_QUERY ];

			/* However, if we passed any actual query string arguments, we need to strip those */
			if( mb_strpos( $this->path, '&' ) )
			{
				$this->path = mb_substr( $this->path, 0, mb_strpos( $this->path, '&' ) );
			}
		}
	}
	
	/**
	 * Work out if this is an API Key request, or an OAuth request
	 *
	 * @note	OAuth requires Access Tokens only be transmitted over TLS, so if the request isn't secure, we ignore OAuth credentials
	 * @return	void
	 */
	public function _setRawCredentials()
	{
		if ( $authorizationHeader = \IPS\Request::i()->authorizationHeader() )
		{
			if ( mb_substr( $authorizationHeader, 0, 7 ) === 'Bearer ' and ( !\IPS\OAUTH_REQUIRES_HTTPS or \IPS\Request::i()->isSecure() ) )
			{
				$this->rawAccessToken = mb_substr( $authorizationHeader, 7 );
			}
			else
			{
				$exploded = explode( ':', base64_decode( mb_substr( $authorizationHeader, 6 ) ) );
				if ( isset( $exploded[0] ) )
				{
					$this->rawApiKey = $exploded[0];
				}
			}
		}
	}
	
	/**
	 * Check the IP Address isn't banned
	 *
	 * @return	void
	 * @throws	\IPS\Api\Exception
	 */
	protected function _checkIpAddressIsAllowed()
	{
		/* Check the IP address is banned */
		if ( \IPS\Request::i()->ipAddressIsBanned() )
		{
			throw new \IPS\Api\Exception( 'IP_ADDRESS_BANNED', '1S290/A', 403 );
		}
		
		/* If we have tried to access the API with a bad key more than 10 times, ban the IP address */
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_api_logs', array( 'ip_address=? AND is_bad_key=1', \IPS\Request::i()->ipAddress() ) )->first() > 10 )
		{
			/* Remove the flag from these logs so that if the admin unbans the IP we aren't immediately banned again */
			\IPS\Db::i()->update( 'core_api_logs', array( 'is_bad_key' => 0 ), array( 'ip_address=?', \IPS\Request::i()->ipAddress() ) );
			
			/* Then insert the ban... */
			\IPS\Db::i()->insert( 'core_banfilters', array(
				'ban_type'		=> 'ip',
				'ban_content'	=> \IPS\Request::i()->ipAddress(),
				'ban_date'		=> time(),
				'ban_reason'	=> 'API',
			) );
			unset( \IPS\Data\Store::i()->bannedIpAddresses );
			
			/* And throw an error */
			throw new \IPS\Api\Exception( 'IP_ADDRESS_BANNED', '1S290/C', 403 );
		}
		
		/* If we have tried to access the API with a bad key more than once in the last 5 minutes, throw an error to prevent brute-forcing */
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_api_logs', array( 'ip_address=? AND is_bad_key=1 AND date>?', \IPS\Request::i()->ipAddress(), \IPS\DateTime::create()->sub( new \DateInterval( 'PT5M' ) )->getTimestamp() ) )->first() > 1 )
		{
			throw new \IPS\Api\Exception( 'TOO_MANY_REQUESTS_WITH_BAD_KEY', '1S290/D', 429 );
		}
	}
	
	/**
	 * Set API Key
	 *
	 * @return	void
	 */
	public function _setApiKey()
	{
		try
		{
			$this->apiKey = \IPS\Api\Key::load( $this->rawApiKey );
			
			if ( $this->apiKey->allowed_ips and !\in_array( \IPS\Request::i()->ipAddress(), explode( ',', $this->apiKey->allowed_ips ) ) )
			{
				throw new \IPS\Api\Exception( 'IP_ADDRESS_NOT_ALLOWED', '2S290/8', 403 );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_API_KEY', '3S290/7', 401 );
		}
	}
	
	/**
	 * Set Access Token
	 *
	 * @return	void
	 */
	public function _setAccessToken()
	{
		try
		{
			$this->accessToken = \IPS\Api\OAuthClient::accessTokenDetails( $this->rawAccessToken );
		}
		catch ( \UnderflowException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ACCESS_TOKEN', '3S290/9', 401, 'invalid_token' );
		}
	}
	
	/**
	 * Set Language
	 *
	 * @return	void
	 */
	public function _setLanguage()
	{
		try
		{
			if ( isset( $_SERVER['HTTP_X_IPS_LANGUAGE'] ) )
			{
				$this->language = \IPS\Lang::load( \intval( $_SERVER['HTTP_X_IPS_LANGUAGE'] ) );
			}
			else
			{
				$this->language = \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_LANGUAGE', '2S290/9', 400, 'invalid_request' );
		}
	}
	
	/**
	 * Run
	 *
	 * @return	void
	 */
	public function run()
	{
		$shouldLog = FALSE;
		try
		{
			/* Work out the app and controller. Both can only be alphanumeric - prevents include injections */
			$pathBits = array_filter( explode( '/', $this->path ) );
			$app = array_shift( $pathBits );
			if ( !preg_match( '/^[a-z0-9]+$/', $app ) )
			{
				throw new \IPS\Api\Exception( 'INVALID_APP', '3S290/3', 400 );
			}
			$controller = array_shift( $pathBits );
			if ( !preg_match( '/^[a-z0-9]+$/', $controller ) )
			{
				throw new \IPS\Api\Exception( 'INVALID_CONTROLLER', '3S290/4', 400 );
			}
			
			/* Load the app */
			try
			{
				$app = \IPS\Application::load( $app );
			}
			catch ( \OutOfRangeException $e )
			{
				throw new \IPS\Api\Exception( 'INVALID_APP', '2S290/1', 404 );
			}
				
			/* Check it's enabled */
			if ( !$app->enabled )
			{
				throw new \IPS\Api\Exception( 'APP_DISABLED', '1S290/2', 503 );
			}
			
			/* Get the controller */
			$class = 'IPS\\' . $app->directory . '\\api\\' . $controller;
			if ( !class_exists( $class ) )
			{
				throw new \IPS\Api\Exception( 'INVALID_CONTROLLER', '2S290/5', 404 );
			}
			
			/* Run it */
			$controller = new $class( $this->apiKey, $this->accessToken );
			$response = $controller->execute( $pathBits, $shouldLog );

			/* Store if we need to mask anything in logs */
			$this->parametersToMask = ( $controller->methodCalled AND isset( $controller->parametersToMask[ $controller->methodCalled ] ) ) ? $controller->parametersToMask[ $controller->methodCalled ] : NULL;
			
			/* Send Output */
			$output = $response->getOutput();
			$this->language->parseOutputForDisplay( $output );

			$this->_respond( json_encode( $output, JSON_PRETTY_PRINT ), $response->httpCode, NULL, $shouldLog, TRUE );
		}
		catch ( \IPS\Api\Exception $e )
		{
			$this->_respond( json_encode( array( 'errorCode' => $e->exceptionCode, 'errorMessage' => $e->getMessage() ), JSON_PRETTY_PRINT ), $e->getCode(), $e->oauthError, $shouldLog );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'api' );
			
			$this->_respond( json_encode( array( 'errorCode' => 'EX' . $e->getCode(), 'errorMessage' => \IPS\IN_DEV ? $e->getMessage() : 'UNKNOWN_ERROR' ), JSON_PRETTY_PRINT ), 500 );
		}
	}

	/**
	 * @brief	Parameters to mask in logs per the controller
	 */
	protected $parametersToMask = NULL;
	
	/**
	 * Log
	 *
	 * @param	array	$response			Response to output
	 * @param	int		$httpResponseCode	HTTP Response Code
	 * @param	bool	$isBadKey			Was the ley invalid?
	 * @return	void
	 */
	protected function _log( $response, $httpResponseCode, $isBadKey=FALSE )
	{
		try
		{
			$_requestData = $_REQUEST;

			if( $this->parametersToMask AND \count( $this->parametersToMask ) )
			{
				foreach( $_requestData as $k => $v )
				{
					if( \in_array( $k, $this->parametersToMask ) )
					{
						$_requestData[ $k ] = '******';
					}
				}
			}

			\IPS\Db::i()->insert( 'core_api_logs', array(
				'endpoint'			=> $this->path,
				'method'			=> $_SERVER['REQUEST_METHOD'],
				'api_key'			=> $this->rawApiKey,
				'ip_address'		=> \IPS\Request::i()->ipAddress(),
				'request_data'		=> json_encode( $_requestData, JSON_PRETTY_PRINT ),
				'response_code'		=> $httpResponseCode,
				'response_output'	=> $response,
				'date'				=> time(),
				'is_bad_key'		=> $isBadKey,
				'client_id'			=> $this->accessToken ? $this->accessToken['client_id'] : NULL,
				'member_id'			=> $this->accessToken ? $this->accessToken['member_id'] : NULL,
				'access_token'		=> $this->rawAccessToken,
			) );
		}
		catch ( \IPS\Db\Exception $e ) {}
	}
	
	/**
	 * Output response
	 *
	 * @param	string		$response			Response to output
	 * @param	int			$httpResponseCode	HTTP Response Code
	 * @param	NULL|string	$oauthError			OAuth error
	 * @param	bool		$log				Whether or not to log the response
	 * @return	void
	 */
	protected function _respond( $response, $httpResponseCode, $oauthError=NULL, $log=FALSE )
	{
		$headers = $this->canBeCached() ? \IPS\Output::getCacheHeaders( time(), 60 ) : \IPS\Output::getNoCacheHeaders();

		if ( $this->rawAccessToken and $oauthError )
		{
			$headers['WWW-Authenticate'] = "Bearer error=\"{$oauthError}\"";
		}
		
		if ( $log )
		{
			$this->_log( $response, $httpResponseCode );
		}
		
		\IPS\Output::i()->sendOutput( $response, $httpResponseCode, 'application/json', $headers );
	}

	/**
	 * Can this response be cached?
	 *
	 * @return bool
	 */
	protected function canBeCached() : bool
	{
		return $this->cacheResponse AND mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'get' AND !$this->rawAccessToken;
	}
	
	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		
	}
}