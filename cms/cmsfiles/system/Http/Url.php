<?php
/**
 * @brief		URL Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Jun 2013
 */

namespace IPS\Http;
 
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * URL Class
 *
 * This class represents a URL using the RFC 3986 definition of a URI (i.e. that
 * RFC is used, but objects of this class can only represent URLs, not URIs which
 * are not URLs or URNs) with the extra provision that we allow protocol-relative URLs
 *
 * @see	<a href="https://www.ietf.org/rfc/rfc3986.txt">RFC 3986</a>
 */
class _Url
{
	/**
	 * @brief	Automatically determine the protocol
	 */
	const PROTOCOL_AUTOMATIC = 0;

	/**
	 * @brief	Use https://
	 */
	const PROTOCOL_HTTPS = 1;

	/**
	 * @brief	Use http://
	 */
	const PROTOCOL_HTTP = 2;

	/**
	 * @brief	Use // (protoool-relative)
	 */
	const PROTOCOL_RELATIVE = 3;

	/**
	 * @brief	Use no protocol at all!
	 */
	const PROTOCOL_WITHOUT = 4;

	/**
	 * @brief	Scheme component
	 */
	const COMPONENT_SCHEME = 'scheme';

	/**
	 * @brief	Username component
	 */
	const COMPONENT_USERNAME = 'user';

	/**
	 * @brief	Password component
	 */
	const COMPONENT_PASSWORD = 'pass';

	/**
	 * @brief	Host component
	 */
	const COMPONENT_HOST = 'host';

	/**
	 * @brief	Port component
	 */
	const COMPONENT_PORT = 'port';

	/**
	 * @brief	Path component
	 */
	const COMPONENT_PATH = 'path';

	/**
	 * @brief	Querystring component
	 */
	const COMPONENT_QUERY = 'query';

	/**
	 * @brief	Query string key
	 */
	const COMPONENT_QUERY_KEY = 'queryKey';

	/**
	 * @brief	Query string value
	 */
	const COMPONENT_QUERY_VALUE = 'queryValue';

	/**
	 * @brief	Fragment component
	 */
	const COMPONENT_FRAGMENT = 'fragment';
		
	/* !Factory Methods */
	
	/**
	 * Build Internal URL
	 *
	 * @param	string			$queryString	The query string
	 * @param	string|null		$base			Key for the URL base. If NULL, defaults to current controller location
	 * @param	string			$seoTemplate	The key for making this a friendly URL
	 * @param	string|array	$seoTitles		The title(s) needed for the friendly URL
	 * @param	int				$protocol		Protocol (one of the PROTOCOL_* constants)
	 * @return	\IPS\Http\Url\Internal
	 */
	public static function internal( $queryString, $base=NULL, $seoTemplate=NULL, $seoTitles=array(), $protocol = 0 )
	{
		/* If we don't have a base, assume the template location */
		if ( !$base )
		{
			if ( \IPS\Dispatcher::hasInstance() )
			{
				if ( \IPS\Dispatcher::i()->controllerLocation === 'setup' )
				{
					return new static( ( \IPS\Request::i()->isSecure()  ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . ( $_SERVER['QUERY_STRING'] ? rtrim( mb_substr( $_SERVER['REQUEST_URI'], 0, -mb_strlen( $_SERVER['QUERY_STRING'] ) ), '?' ) : $_SERVER['REQUEST_URI'] ) . '?' . $queryString );
				}
				else
				{
					$base = \IPS\Dispatcher::i()->controllerLocation;
				}
			}
			else
			{
				$base = 'front';
			}
		}
		
		/* Front-End Friendly */
		if ( $base === 'front' and $seoTemplate and \IPS\Settings::i()->use_friendly_urls )
		{
			return \IPS\Http\Url\Friendly::friendlyUrlFromQueryString( $queryString, $seoTemplate, $seoTitles, $protocol );
		}
		/* Front-End not friendly */
		elseif ( $base === 'front' )
		{
			return \IPS\Http\Url\Internal::createInternalFromComponents( 'front', $protocol, $queryString ? 'index.php' : '', $queryString );
		}
		/* Admin */
		elseif ( $base === 'admin' or $base === 'admin_redirect' )
		{
			return \IPS\Http\Url\Internal::createInternalFromComponents(
				'admin',
				$protocol,
				\IPS\CP_DIRECTORY . '/',
				static::convertQueryAsStringToArray( $queryString )
			);
		}
		/* None */
		else
		{		
			return static::createFromString( static::baseUrl( $protocol ) . $queryString, FALSE ); 
		}
	}
	
	/**
	 * Build External URL
	 *
	 * @param	string			$url
	 * @return	\IPS\Http\Url
	 * @throws	\InvalidArgumentException
	 */
	public static function external( $url )
	{
		return new static( $url, TRUE );
	}
	
	/**
	 * Build IPS-External URL
	 *
	 * @param	string			$url
	 * @return	\IPS\Http\Url
	 */
	final public static function ips( $url )
	{			
		return new static( "https://remoteservices.invisionpower.com/{$url}/?version=" . \IPS\Application::getAvailableVersion('core') );
	}
	
	/**
	 * Create from components - all arguments should be UNENCODED
	 *
	 * @param	string				$host		Host
	 * @param	string|NULL			$scheme		Scheme (NULL for protocol-relative)
	 * @param	string|NULL			$path		Path
	 * @param	string|array|NULL	$query		Query
	 * @param	int|NULL			$port		Port
	 * @param	string|NULL			$username	Username
	 * @param	string|NULL			$password	Password
	 * @param	string|NULL			$fragment	Fragment
	 * @return	\IPS\Http\Url
	 */
	public static function createFromComponents( $host, $scheme = NULL, $path = NULL, $query = NULL, $port = NULL, $username = NULL, $password = NULL, $fragment = NULL )
	{
		$obj = new static('');
		
		$obj->data[ static::COMPONENT_SCHEME ] = $scheme;
		$obj->data[ static::COMPONENT_HOST ] = $host;
		$obj->data[ static::COMPONENT_PORT ] = $port;
		$obj->data[ static::COMPONENT_USERNAME ] = $username;
		$obj->data[ static::COMPONENT_PASSWORD ] = $password;
		$obj->data[ static::COMPONENT_PATH ] = $path;
		$obj->data[ static::COMPONENT_FRAGMENT ] = $fragment;
		
		if ( \is_array( $query ) )
		{
			$obj->data[ static::COMPONENT_QUERY ] = static::convertQueryAsArrayToString( $query );
			$obj->queryString = $query;
		}
		elseif ( \is_string( $query ) )
		{
			$obj->data[ static::COMPONENT_QUERY ] = $query;
			$obj->queryString = static::convertQueryAsStringToArray( $query );
		}
						
		$obj->reconstructUrlFromData();
		
		return $obj;
	}
	
	/**
	 * Create from string
	 * This method is somewhat performance-intensive and should only be used either for creating friendly URLs, or if it is not known
	 * if the URL will be internal or external. If you know the URL will be external, use new \IPS\Http\Url( ... )
	 *
	 * @param	string	$url				A valid URL as per our definition (see phpDoc on class)
	 * @param	bool	$couldBeFriendly	If the URL is known to not be friendly, FALSE can be passed here to save the performance implication of checking the URL
	 * @param	bool	$autoEncode			If true, any invalid components will be automatically encoded rather than an exception thrown - useful if the entire link is user-provided
	 * @return	\IPS\Http\Url
	 * @throws	\IPS\Http\Url\Exception
	 */
	public static function createFromString( $url, $couldBeFriendly=TRUE, $autoEncode=FALSE )
	{
		/* Decode it */
		$components = static::componentsFromUrlString( $url, $autoEncode );
		
		/* Is it internal? */
		$baseUrlComponents = static::componentsFromUrlString( static::baseUrl() );

		if ( $components[ static::COMPONENT_HOST ] === $baseUrlComponents[ static::COMPONENT_HOST ] and
			$components[ static::COMPONENT_USERNAME ] === $baseUrlComponents[ static::COMPONENT_USERNAME ] and
			$components[ static::COMPONENT_PASSWORD ] === $baseUrlComponents[ static::COMPONENT_PASSWORD ] and
			$components[ static::COMPONENT_PORT ] === $baseUrlComponents[ static::COMPONENT_PORT ] and
			mb_substr( $components[ static::COMPONENT_PATH ], 0, mb_strlen( $baseUrlComponents[ static::COMPONENT_PATH ] ) ) === $baseUrlComponents[ static::COMPONENT_PATH ]
		)
		{		
			$pathFromBaseUrl = mb_substr( $components[ static::COMPONENT_PATH ], mb_strlen( $baseUrlComponents[ static::COMPONENT_PATH ] ) );
			
			/* Admin */
			if ( preg_match( '/^' . preg_quote( \IPS\CP_DIRECTORY, '/' ) . '($|\/)/', $pathFromBaseUrl ) )
			{
				return \IPS\Http\Url\Internal::createInternalFromComponents( 'admin', $components[ static::COMPONENT_SCHEME ], $pathFromBaseUrl, $components[ static::COMPONENT_QUERY ], $components[ static::COMPONENT_FRAGMENT ] );
			}
			/* Front-End: Not friendly or unrewritten friendly */
			elseif ( !$pathFromBaseUrl or $pathFromBaseUrl === 'index.php' )
			{
				$queryString = \IPS\Http\Url::convertQueryAsArrayToString( $components[ static::COMPONENT_QUERY ] );
				$potentialFurl = trim( mb_substr( $queryString, 0, mb_strpos( $queryString, '&' ) ?: NULL ), '/' );
				if ( $couldBeFriendly and $return = \IPS\Http\Url\Friendly::createFriendlyUrlFromComponents( $components, $potentialFurl ) )
				{
					return $return;
				}
				else
				{
					return \IPS\Http\Url\Internal::createInternalFromComponents( 'front', $components[ static::COMPONENT_SCHEME ], $pathFromBaseUrl, $components[ static::COMPONENT_QUERY ], $components[ static::COMPONENT_FRAGMENT ] );
				}
			}
			/* Front-End: Rewritten friendly */
			elseif ( $couldBeFriendly and $return = \IPS\Http\Url\Friendly::createFriendlyUrlFromComponents( $components, rtrim( mb_substr( $components[ static::COMPONENT_PATH ], mb_strlen( $baseUrlComponents[ static::COMPONENT_PATH ] ) ), '/' ) ) )
			{
				return $return;
			}
			/* Other */
			else
			{
				return \IPS\Http\Url\Internal::createInternalFromComponents( 'none', $components[ static::COMPONENT_SCHEME ], $pathFromBaseUrl, $components[ static::COMPONENT_QUERY ], $components[ static::COMPONENT_FRAGMENT ] );
			}
		}
		/* Nope, external */
		else
		{
			return \IPS\Http\Url::createFromComponents( $components['host'], $components['scheme'], $components['path'], $components['query'], $components['port'], $components['user'], $components['pass'], $components['fragment'] );
		}
	}
		
	/* !Instance */
	
	/**
	 * @brief	URL, with all components appropriately encoded
	 */
	protected $url = NULL;
	
	/**
	 * @brief	All the different components, decoded
	 */
	public $data = array(
		self::COMPONENT_SCHEME => NULL,
		self::COMPONENT_HOST => NULL,
		self::COMPONENT_PORT => NULL,
		self::COMPONENT_USERNAME => NULL,
		self::COMPONENT_PASSWORD => NULL,
		self::COMPONENT_PATH => NULL,
		self::COMPONENT_QUERY => NULL,
		self::COMPONENT_FRAGMENT => NULL,
	);
		
	/**
	 * @brief	Query string as decoded array
	 */
	public $queryString = array();

	/**
	 * @brief	Hidden Query String Parameters
	 */
	public $hiddenQueryString = array();
		
	/**
	 * Constructor
	 *
	 * @param	string|NULL	$url		A valid URL as per our definition (see phpDoc on class) or NULL if being called by createFromComponents()
	 * @param	bool		$autoEncode	If true, any invalid components will be automatically encoded rather than an exception thrown - useful if the entire link is user-provided
	 * @return	void
	 * @throws	\IPS\Http\Url\Exception
	 *	@li	INVALID_URL			The URL did not start with // to indicate relative protocol or contain ://
	 *	@li	INVALID_SCHEME		The scheme was invalid
	 *	@li	INVALID_USERNAME	The username was invalid
	 *	@li	INVALID_PASSWORD	The password was invalid
	 *	@li	INVALID_HOST		The host name was invalid
	 *	@li	INVALID_PATH		The path was invalid
	 *	@li	INVALID_QUERY		The query was invalid
	 *	@li	INVALID_FRAGMENT	The fragment was invalid
	 */
	public function __construct( $url = NULL, $autoEncode = FALSE )
	{
		if ( $url )
		{
			/* Set the URL */
			$this->url = $url;
			
			/* Set the components */
			$this->data = static::componentsFromUrlString( $url, $autoEncode );
			$this->queryString = $this->data['query'];
			$this->data['query'] = static::convertQueryAsArrayToString( $this->queryString );
		}
	}
	
	/**
	 * Adjust scheme
	 *
	 * @param	string|NULL		$scheme	Scheme (NULL for protocol-relative)
	 * @return	\IPS\Http\Url
	 */
	public function setScheme( $scheme )
	{
		$obj = clone $this;
		$obj->data[ static::COMPONENT_SCHEME ] = $scheme;
		$obj->reconstructUrlFromData();
		return $obj;
	}
	
	/**
	 * Adjust host
	 *
	 * @param	string	$host	Host
	 * @return	\IPS\Http\Url
	 */
	public function setHost( $host )
	{
		$obj = clone $this;
		$obj->data[ static::COMPONENT_HOST ] = $host;
		$obj->reconstructUrlFromData();
		return $obj;
	}
	
	/**
	 * Adjust path
	 *
	 * @param	string|NULL	$path Path
	 * @return	\IPS\Http\Url
	 */
	public function setPath( $path )
	{
		$obj = clone $this;
		$obj->data[ static::COMPONENT_PATH ] = $path;
		$obj->reconstructUrlFromData();
		return $obj;
	}
	
	/**
	 * Adjust Query String
	 *
	 * @param	string|array	$keyOrArray	Key, or array of key/value pairs
	 * @param	string|null		$value		Value, or NULL if $key is an array
	 * @return	\IPS\Http\Url
	 */
	public function setQueryString( $keyOrArray, $value=NULL )
	{
		$newQueryArray = $this->queryString;
		
		if ( \is_array( $keyOrArray ) )
		{
			foreach ( $keyOrArray as $k => $v )
			{
				if ( $v === NULL )
				{
					unset( $newQueryArray[ $k ] );
				}
				else
				{
					$newQueryArray[ $k ] = $v;
				}
			}
		}
		else
		{
			if ( $value === NULL )
			{
				unset( $newQueryArray[ $keyOrArray ] );
			} 
			else
			{
				$newQueryArray[ $keyOrArray ] = $value;
			}
		}
		
		$obj = clone $this;
		$obj->data[ static::COMPONENT_QUERY ] = static::convertQueryAsArrayToString( $newQueryArray );
		$obj->queryString = $newQueryArray;
		$obj->reconstructUrlFromData();
		return $obj;
	}
	
	/**
	 * Reset the $url property after changing data
	 *
	 * @return	void
	 */
	protected function reconstructUrlFromData()
	{
		/* Scheme */
		$scheme = '';
		if ( $this->data[ static::COMPONENT_SCHEME ] )
		{
			$scheme = $this->data[ static::COMPONENT_SCHEME ] . '://';
		}
		else
		{
			$scheme = '//';
		}
		
		/* Username and password */
		$usernameAndPassword = '';
		if ( $this->data[ static::COMPONENT_USERNAME ] )
		{
			$usernameAndPassword = static::encodeComponent( static::COMPONENT_USERNAME, $this->data[ static::COMPONENT_USERNAME ] );
			if ( $this->data[ static::COMPONENT_PASSWORD ] )
			{
				$usernameAndPassword .= ':' . static::encodeComponent( static::COMPONENT_PASSWORD, $this->data[ static::COMPONENT_PASSWORD ] );
			}
			$usernameAndPassword .= '@';
		}
		
		/* Host */
		$host = static::encodeComponent( static::COMPONENT_HOST, $this->data[ static::COMPONENT_HOST ] );
		
		/* Port */
		$port = '';
		if ( $this->data[ static::COMPONENT_PORT ] )
		{
			$port = ':' . \intval( $this->data[ static::COMPONENT_PORT ] );
		}
		
		/* Path */
		$path = '';
		if ( $this->data[ static::COMPONENT_PATH ] )
		{
			$path = '/' . ltrim( static::encodeComponent( static::COMPONENT_PATH, $this->data[ static::COMPONENT_PATH ] ), '/' );
		}
		
		/* Query String */
		$queryString = '';
		if ( $this->data[ static::COMPONENT_QUERY ] )
		{
			if ( empty( $path ) )
			{
				$path = '/';
			}
			
			$queryString = '?' . static::convertQueryAsArrayToString( $this->queryString, TRUE ); 
		}
		
		/* Fragment */
		$fragment = '';
		if ( $this->data[ static::COMPONENT_FRAGMENT ] )
		{
			$fragment = '#' . static::encodeComponent( static::COMPONENT_FRAGMENT, $this->data[ static::COMPONENT_FRAGMENT ] );
		}
		
		/* Put it all together */
		$this->url = $scheme . $usernameAndPassword . $host . $port . $path . $queryString . $fragment;
	}
	
	/**
	 * Strip Query String
	 *
	 * @param	string|array	$keys	The key(s) to strip - if omitted, entire query string is wiped
	 * @return	\IPS\Http\Url
	 */
	public function stripQueryString( $keys=NULL )
	{
		$newQueryArray = array();

		if( $keys !== NULL )
		{
			if( !\is_array( $keys ) )
			{
				$keys = array( $keys => $keys );
			}

			$newQueryArray = array_diff_key( $this->queryString, array_combine( array_values( $keys ), array_values( $keys ) ) );
		}

		return static::createFromComponents( $this->data[ static::COMPONENT_HOST ], $this->data[ static::COMPONENT_SCHEME ], $this->data[ static::COMPONENT_PATH ], $newQueryArray, $this->data[ static::COMPONENT_PORT ], $this->data[ static::COMPONENT_USERNAME ], $this->data[ static::COMPONENT_PASSWORD ], $this->data[ static::COMPONENT_FRAGMENT ] );
	}
	
	/**
	 * Adjust Fragment
	 *
	 * @param	string	$fragment	Fragment
	 * @return	\IPS\Http\Url
	 */
	public function setFragment( $fragment )
	{
		return static::createFromComponents( $this->data[ static::COMPONENT_HOST ], $this->data[ static::COMPONENT_SCHEME ], $this->data[ static::COMPONENT_PATH ], $this->data[ static::COMPONENT_QUERY ], $this->data[ static::COMPONENT_PORT ], $this->data[ static::COMPONENT_USERNAME ], $this->data[ static::COMPONENT_PASSWORD ], $fragment );
	}
	
	/**
	 * Make safe for ACP
	 *
	 * @deprecated	No longer needed as of 4.5, ACP URLs no longer have the session ID in the URL
	 * @return	\IPS\Http\Url
	 */
	public function makeSafeForAcp( $resource=FALSE )
	{
		return $this;
		
		//return static::internal( "app=core&module=system&controller=redirect", 'front' )->setQueryString( array(
		//	'url'		=> (string) $this,
		//	'key'		=> hash_hmac( "sha256", (string) $this, \IPS\Settings::i()->site_secret_key . 'r' ),
		//	'resource'	=> $resource
		//) );
	}

	/**
	 * Make a HTTP Request
	 *
	 * @param	int|null	$timeout				Timeout
	 * @param	string		$httpVersion			HTTP Version
	 * @param	bool|int	$followRedirects		Automatically follow redirects? If a number is provided, will follow up to that number of redirects
	 * @param	array|null	$allowedProtocols		Protocols allowed (if NULL we default to array( 'http', 'https', 'ftp', 'scp', 'sftp', 'ftps' ))
	 * @return	\IPS\Http\Request
	 */
	public function request( $timeout=null, $httpVersion=null, $followRedirects=5, $allowedProtocols=NULL )
	{
		$allowedProtocols = $allowedProtocols ?: array( 'http', 'https', 'ftp', 'scp', 'sftp', 'ftps' );

		/* Check the scheme is valid. Some areas accept user-submitted information to create a Url object. To avoid
			security issues from using file:// telnet:// etc. We reject everything not used by the suite here */
		if( isset( $this->data['scheme'] ) AND !\in_array( $this->data['scheme'], $allowedProtocols ) )
		{
			throw new \RuntimeException( mb_strtoupper( $this->data['scheme'] ) . '_SCHEME_NOT_PERMITTED' );
		}
		
		/* Set a timeout */
		if( $timeout === null )
		{
			$timeout = \IPS\DEFAULT_REQUEST_TIMEOUT;
		}

		/* Use cURL if we can. BYPASS_CURL constant can be set to TRUE to force us to fallback to Sockets */
		if ( \function_exists( 'curl_init' ) and \function_exists( 'curl_exec' ) and \IPS\BYPASS_CURL === false )
		{
			/* We require 7.36 or higher because older versions can't handle chunked encoding properly - FORCE_CURL constant can be set to override this and use it anyway */
			$version = curl_version();
			if( \IPS\FORCE_CURL or version_compare( $version['version'], '7.36', '>=' ) )
			{
				$requestObj	= new \IPS\Http\Request\Curl( $this, $timeout, $httpVersion, $followRedirects, $allowedProtocols );
			}
		}

		/* Fallback to Sockets if we can't use cURL */
		if( !isset( $requestObj ) )
		{
			$requestObj = new \IPS\Http\Request\Sockets($this, $timeout, $httpVersion, $followRedirects, $allowedProtocols);
		}

		/* Set a default user-agent (some services, e.g. spotify, block requests without one but it's good to do so anyway) */
		$requestObj->setHeaders( array( 'User-Agent' => 'Invision Community 4' ) );
		
		/* Return */
		return $requestObj;
	}
	
	/**
	 * Import as file
	 *
	 * @param	string	$storageExtension	The extension which specified the storage location to use
	 * @return	\IPS\File
	 * @throws	\RuntimeException
	 */
	public function import( $storageExtension )
	{
		$response = $this->request()->get();

		/* We should not attempt to "import" 404, 403, 500, etc. responses */
		if( (int) $response->httpResponseCode !== 200 )
		{
			throw new \RuntimeException( "COULD_NOT_IMPORT" );
		}

		return \IPS\File::create( $storageExtension, basename( $this->data[ static::COMPONENT_PATH ] ), (string) $response );
	}
			
	/**
	 * To string
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return (string) $this->url;
	}
	
	/* !Encoding and Decoding */
	
	/**
	 * @brief	Allowed characters for the different components, everything apart from schemes and ports can also contain percent-encoded characters
	 */
	protected static $allowedCharacters = array(
		/* A scheme must is allowed to contain letters, digits, plus ("+"), period ("."), or hyphen ("-"). */
		self::COMPONENT_SCHEME => 'A-Za-z0-9\+\.\-',
		/* A username is allowed to contain Unreserved Characters and Subcomponent Delimiters */
		self::COMPONENT_USERNAME => 'A-Za-z0-9\-\._~!\$&\'\(\)\*\+,;=',
		/* A password is allowed to contain Unreserved Characters, Subcomponent Delimiters, and ":" */
		self::COMPONENT_PASSWORD => 'A-Za-z0-9\-\._~!\$&\'\(\)\*\+,;=:',
		/* Hosts can be several different things (IP lireral, IPv4 address or registered name), but for the sake of validation it comes down to Unreserved Characters and Subcomponent delimiters */
		self::COMPONENT_HOST => 'A-Za-z0-9\-\._~!\$&\'\(\)\*\+,;=',
		/* Ports must be numeric */
		self::COMPONENT_PORT => '0-9',
		/* A path is made up of path segments separated by a single /. A path segment is allowed to contain Unreserved Characters, Percent-Encoded Characters, Subcomponent Delimiters, plus ":", "@" */
		self::COMPONENT_PATH => 'A-Za-z0-9\-\._~!\$&\'\(\)\*\+,;=:@\/',
		/* A query string can contain Unreserved Characters, Subcomponent Delimiters, plus ":", "@", "/" and "?".
			While "[" and "]" are not in the RFC, common convention is to use them for multi-dimensional key-value pairs, so they are also included which should be fine as square brackets are
			 only part of the general delimiters because of their use in the host for IPv6 characters - they have no special meaning in the query string or beyond
			While "{" and "}" are also not in the RFC, we need to allow them without throwing an error because older versions allowed them and otherwise the upgrader will throw an error half-way through
		*/
		self::COMPONENT_QUERY => 'A-Za-z0-9\-\._~!\$&\'\(\)\*\+,;=:@\/\?\[\]\{\}',
		/* In a query string which is using the "k1=v1&k2=v2" convention, the keys  can contain anything a query string can contain except & and = */
		self::COMPONENT_QUERY_KEY => 'A-Za-z0-9\-\._~!\$\'\(\)\*\+,;:@\/\?\[\]',
		/* In a query string which is using the "k1=v1&k2=v2" convention, the value  can contain anything a query string can contain except & */
		self::COMPONENT_QUERY_VALUE => 'A-Za-z0-9\-\._~!\$\'\(\)\*\+,;=:@\/\?\[\]',
		/* A fragment can contain Unreserved Characters, Subcomponent Delimiters, plus ":", "@", "/" and "?" */
		self::COMPONENT_FRAGMENT => 'A-Za-z0-9\-\._~!\$&\'\(\)\*\+,;=:@\/\?',
	);
	
	/**
	 * Validate a component
	 *
	 * @param	string	$component	One of the COMPONENT_* constants
	 * @param	string	$value		The value
	 * @return	bool
	 */
	public static function validateComponent( $component, $value )
	{
		/* Get the allowed characters */
		$allowedCharacters = static::$allowedCharacters[ $component ];
		
		/* These ones can also include percent-encoded characters and non-latin characters */
		if ( !\in_array( $component, array( static::COMPONENT_SCHEME, static::COMPONENT_PORT ) ) )
		{
			$regex = '(' . '(%[A-Fa-z0-9]{2})|' . '[' . $allowedCharacters . ']\X*' . ')*';
		}
		else
		{
			$regex = '[' . $allowedCharacters . ']*';
		}
		
		/* Schemes must begin with a letter */
		if ( $component === static::COMPONENT_SCHEME )
		{
			$regex = '[A-Za-z]' . $regex;
		}
		
		/* Return */
		return (bool) preg_match( '/^' . $regex . '$/', $value );
	}
	
	/**
	 * Percent-Encode a component
	 *
	 * @param	string			$component			One of the COMPONENT_* constants
	 * @param	string			$value				The value
	 * @return	string
	 * @throws	\InvalidArgumentException
	 */
	public static function encodeComponent( $component, $value )
	{
		/* These ones cannot be percent-encoded */
		if ( \in_array( $component, array( static::COMPONENT_SCHEME, static::COMPONENT_PORT ) ) )
		{
			throw new \InvalidArgumentException;
		}
				
		/* Get the allowed characters */
		$allowedCharacters = static::$allowedCharacters[ $component ];
		
		/* Do it */
		$return = preg_replace_callback( '/[^' . $allowedCharacters . ']/i', function( $matches )
		{
			return rawurlencode( $matches[0] );
		}, $value );
		
		/* While + is technically valid in query strings, PHP will interpret it as a space in $_GET data if
			it isn't encoded (for example, if you use the internal redirector for a URL with a + in it - like
			a Google+ profile - when that hits PHP, it will convert it to a space, making the URL invalid.
			There is no downside to percent-encoding any particular character even if it doesn't technically
			need to be, so to avoid this issue, we'll just encode it */
		if ( \in_array( $component, array( static::COMPONENT_QUERY, static::COMPONENT_QUERY_KEY, static::COMPONENT_QUERY_VALUE ) ) )
		{
			$return = str_replace( '+', '%2B', $return );
		}
		
		/* Return */
		return $return;
	}
	
	/**
	 * Un-Percent-Encode a component
	 *
	 * @param	string	$component	One of the COMPONENT_* constants
	 * @param	string	$value		The value
	 * @return	bool
	 * @throws	\InvalidArgumentException
	 */
	public static function decodeComponent( $component, $value )
	{
		return rawurldecode( $value );
	}
	
	/* !Utilities */
	
	/**
	 * Return the base URL
	 *
	 * @param	bool			$protocol		Protocol (one of the PROTOCOL_* constants)
	 * @return	string
	 */
	public static function baseUrl( $protocol = 0 )
	{
		/* Get the base URL */
		$url = \IPS\Settings::i()->base_url;
		
		/* Adjust the protocol */
		if ( $protocol )
		{
			switch ( $protocol )
			{
				case static::PROTOCOL_HTTPS:
					$url = 'https://' . mb_substr( $url, mb_strpos( $url, '://' ) + 3 );
					break;
					
				case static::PROTOCOL_HTTP:
					$url = 'http://' . mb_substr( $url, mb_strpos( $url, '://' ) + 3 );
					break;
					
				case static::PROTOCOL_RELATIVE:
					$url = '//' . mb_substr( $url, mb_strpos( $url, '://' ) + 3 );
					break;
				case static::PROTOCOL_WITHOUT:
					$url =  mb_substr( $url, mb_strpos( $url, '://' ) + 3 );
					break;
			}
		}
		
		/* Add a trailing slash */
		if ( mb_substr( $url, -1 ) !== '/' )
		{
			$url .= '/';
		}
		
		/* Return */
		return $url;
	}
	
	/**
	 * Add a referrer to the URL
	 *
	 * @param	string	$url		The URL.
	 * @return	\IPS\Http\Url
	 */
	public function addRef( string $url ): \IPS\Http\Url
	{
		return $this->setQueryString( 'ref', base64_encode( $url ) );
	}
	
	/**
	 * @brief Punycode object
	 */
	protected static $punycode = NULL;
	
	/**
	 * Convert a full URL into it's components
	 *
	 * @param	string|NULL	$url		A valid URL as per our definition (see phpDoc on class) or NULL if being called by createFromComponents()
	 * @param	bool	$autoEncode		If true, any invalid components will be automatically encoded rather than an exception thrown - useful if the entire link is user-provided
	 * @return	array
	 * @throws	\IPS\Http\Url\Exception
	 *	@li	INVALID_URL			The URL did not start with // to indicate relative protocol or contain ://
	 *	@li	INVALID_SCHEME		The scheme was invalid
	 *	@li	INVALID_USERNAME	The username was invalid
	 *	@li	INVALID_PASSWORD	The password was invalid
	 *	@li	INVALID_HOST		The host name was invalid
	 *	@li	INVALID_PATH		The path was invalid
	 *	@li	INVALID_QUERY		The query was invalid
	 *	@li	INVALID_FRAGMENT	The fragment was invalid
	 */
	protected static function componentsFromUrlString( $url, $autoEncode = FALSE )
	{
		/* Init */
		$return = array(
			static::COMPONENT_SCHEME => NULL,
			static::COMPONENT_HOST => NULL,
			static::COMPONENT_PORT => NULL,
			static::COMPONENT_USERNAME => NULL,
			static::COMPONENT_PASSWORD => NULL,
			static::COMPONENT_PATH => NULL,
			static::COMPONENT_QUERY => array(),
			static::COMPONENT_FRAGMENT => NULL,
		);
		
		/* If the URL doesn't start with the protocol-relative marker ("//"), it needs a scheme: */
		if ( mb_substr( $url, 0, 2 ) !== '//' )
		{
			/* Work out where the :// is */
			$colonAndDoubleForwardSlashPosition = mb_strpos( $url, '://' );
			if ( $colonAndDoubleForwardSlashPosition === FALSE )
			{
				if ( $autoEncode )
				{
					$scheme = 'http';
				}
				else
				{
					throw new \IPS\Http\Url\Exception('INVALID_URL');
				}
			}
			else
			{
				$scheme = mb_substr( $url, 0, $colonAndDoubleForwardSlashPosition );

				/* Take the scheme off the URL for the rest of of our processing */
				$url = mb_substr( $url, mb_strlen( $scheme ) + 3 );
			}
			
			/* Validate the scheme */
			if ( !static::validateComponent( static::COMPONENT_SCHEME, $scheme ) )
			{
				if ( $autoEncode )
				{
					$scheme = 'http';
				}
				else
				{
					throw new \IPS\Http\Url\Exception('INVALID_SCHEME');
				}
			}
			
			/* Set it. Though case-insensitve, we should only produce lowercase schemes */
			$return[ static::COMPONENT_SCHEME ] = mb_strtolower( $scheme );
			
		}
		/* If it's protocol relative, take the // off the URL for the rest of of our processing */
		else
		{
			$url = mb_substr( $url, 2 );
		}
		
		/* The authority component is preceded by a double slash ("//") and is  terminated by the next slash ("/"), question mark ("?"), or number
			sign ("#") character, or by the end of the URI */
		preg_match( '/^(.+?)(\/|\?|\#|$)/', $url, $matches );
		$authority = $matches[1];
		$url = mb_substr( $url, mb_strlen( $matches[1] ) );
		
		/* If there's an @ in the authority, everything before it is user info */
		$atSymbolPosition = mb_strpos( $authority, '@' );
		if ( $atSymbolPosition !== FALSE )
		{
			/* Take out the user information... */
			$userInfo = mb_substr( $authority, 0, $atSymbolPosition );
			$authority = mb_substr( $authority, $atSymbolPosition + 1 );
			
			/* If there's a : in the user information, we have a username and password */
			$colonPosition = mb_strpos( $userInfo, ':' );
			if ( $colonPosition !== FALSE )
			{
				$username = mb_substr( $userInfo, 0, $colonPosition );
				$password = mb_substr( $userInfo, $colonPosition + 1 );
			}
			/* Otherwise it's just a username */
			else
			{
				$username = $userInfo;
				$password = NULL;
			}
			
			/* Validate and set the username */
			if ( !static::validateComponent( static::COMPONENT_USERNAME, $username ) )
			{
				if ( $autoEncode )
				{
					$return[ static::COMPONENT_USERNAME ] = $username;
				}
				else
				{
					throw new \IPS\Http\Url\Exception('INVALID_USERNAME');
				}
			}
			else
			{
				$return[ static::COMPONENT_USERNAME ] = static::decodeComponent( static::COMPONENT_USERNAME, $username );
			}
			
			/* Validate and set the password if there is one */
			if ( $password !== NULL )
			{
				if ( !static::validateComponent( static::COMPONENT_PASSWORD, $password ) )
				{
					if ( $autoEncode )
					{
						$return[ static::COMPONENT_PASSWORD ] = $password;
					}
					else
					{
						throw new \IPS\Http\Url\Exception('INVALID_PASSWORD');
					}
				}
				else
				{
					$return[ static::COMPONENT_PASSWORD ] = static::decodeComponent( static::COMPONENT_PASSWORD, $password );
				}
			}
		}
		
		/* If the authority ends in a : followed by a number, that's the port */
		if ( preg_match( '/:(\d*)$/', $authority, $matches ) )
		{
			$return[ static::COMPONENT_PORT ] = \intval( $matches[1] );
			$authority = mb_substr( $authority, 0, -mb_strlen( $matches[0] ) );
		}
		
		/* If the host contains non-ASCII characters, Puncody encode them */
		if ( !preg_match( '/^[\x00-\x7F]*$/', $authority ) )
		{
			if ( static::$punycode === NULL )
			{
				\IPS\IPS::$PSR0Namespaces['TrueBV'] = \IPS\ROOT_PATH . "/system/3rd_party/php-punycode";
				static::$punycode = new \TrueBV\Punycode();
			}

			try
			{
				$authority = static::$punycode->encode( $authority );
			}
			catch( \TrueBV\Exception\OutOfBoundsException $e )
			{
				/* If we are not auto-encoding, through an exception, otherwise we can just fix it */
				if( !$autoEncode )
				{
					throw new \IPS\Http\Url\Exception('INVALID_HOST');
				}
			}
		}
		
		/* Set the host */
		if ( !static::validateComponent( static::COMPONENT_HOST, $authority ) )
		{
			if ( $autoEncode )
			{
				$return[ static::COMPONENT_HOST ] = $authority;
			}
			else
			{
				throw new \IPS\Http\Url\Exception('INVALID_HOST');
			}
		}
		else
		{
			$return[ static::COMPONENT_HOST ] = static::decodeComponent( static::COMPONENT_HOST, $authority );
		}
		
		/* There might be nothing left at this point, in which case we're done */
		if ( !$url )
		{
			return $return;
		}
					
		/* The path is terminated by the first question mark ("?") or number sign ("#") character, or by the end of the URI. */
		preg_match( '/^(.*?)(\?|\#|$)/', $url, $matches );
		if ( !static::validateComponent( static::COMPONENT_PATH, $matches[1] ) )
		{
			if ( $autoEncode )
			{
				$return[ static::COMPONENT_PATH ] = $matches[1];
			}
			else
			{
				throw new \IPS\Http\Url\Exception('INVALID_PATH');
			}
		}
		else
		{
			$return[ static::COMPONENT_PATH ] = static::decodeComponent( static::COMPONENT_PATH, $matches[1] );
		}
		$url = mb_substr( $url, mb_strlen( $matches[0] ) );
		
		/* There might be nothing left at this point, in which case we're done */
		if ( !$url )
		{
			return $return;
		}
		
		/* If the terminating character was a ? in $matches[2], then we have a query string */
		if ( $matches[2] === '?' )
		{
			/* The query component is terminated by a number sign ("#") character or by the end of the URI. */
			preg_match( '/^(.*?)(\#|$)/', $url, $matches );
			if ( !static::validateComponent( static::COMPONENT_QUERY, $matches[1] ) )
			{
				if ( $autoEncode )
				{
					$return[ static::COMPONENT_QUERY ] = $matches[1];
				}
				else
				{
					throw new \IPS\Http\Url\Exception('INVALID_QUERY');
				}
			}
			else
			{
				$return[ static::COMPONENT_QUERY ] = static::convertQueryAsStringToArray( $matches[1], TRUE );
			}
			$url = mb_substr( $url, mb_strlen( $matches[1] ) );
		}
		
		/* If there's anything left, it's the fragment */
		if ( $url )
		{
			$url = ltrim( $url, '#' );
			if ( !static::validateComponent( static::COMPONENT_FRAGMENT, $url ) )
			{
				if ( $autoEncode )
				{
					$return[ static::COMPONENT_FRAGMENT ] = $url;
				}
				else
				{
					throw new \IPS\Http\Url\Exception('INVALID_FRAGMENT');
				}
			}
			else
			{
				$return[ static::COMPONENT_FRAGMENT ] = static::decodeComponent( static::COMPONENT_FRAGMENT, $url );
			}
		}

		/* Return */
		return $return;
	}
	
	/**
	 * Convert an array of query parameters to a query string
	 *
	 * @param	array	$query	The query as an array (e.g. [ 'foo'=>'bar', 'moo'=>'baz' ])
	 * @param	bool	$encode	If true, will encode for output
	 * @return	string
	 */
	public static function convertQueryAsArrayToString( $query, $encode = FALSE )
	{
		$return = array();
		foreach ( $query as $k => $v )
		{
			if ( $encode )
			{
				$k = static::encodeComponent( static::COMPONENT_QUERY_KEY, $k );
			}
			
			if ( \is_array( $v ) )
			{
				$return[] = static::squashQueryStringArray( $k, $v );
			}
			elseif ( $v !== NULL )
			{
				if ( $encode )
				{
					$v = static::encodeComponent( static::COMPONENT_QUERY_VALUE, $v );
				}
				$return[] = "{$k}={$v}";
			}
			else
			{
				$return[] = "{$k}";
			}
		}
		return implode( '&', $return );
	}
	
	/**
	 * Convert an array within an array of query parameters to a query string
	 *
	 * @param	string	$key	The key for this query string parameter
	 * @param	array	$value	The query as an array (e.g. [ 'foo'=>'bar', 'moo'=>'baz' ])
	 * @param	bool	$encode	If true, will encode for output
	 * @return	string
	 */
	protected static function squashQueryStringArray( $key, $value, $encode = FALSE )
	{
		$return = array();
		foreach ( $value as $k => $v )
		{
			if ( $encode )
			{
				$k = static::encodeComponent( static::COMPONENT_QUERY_KEY, $k );
			}
			
			if ( \is_array( $v ) )
			{
				$return[] = static::squashQueryStringArray( "{$key}[{$k}]", $v, $encode );
			}
			else
			{
				if ( $encode )
				{
					$v = static::encodeComponent( static::COMPONENT_QUERY_VALUE, $v );
				}
				
				$return[] = "{$key}[{$k}]={$v}";
			}
		}
		return \count( $return ) ? implode( '&', $return ) : '';
	}
		
	/**
	 * Convert a query string to an array of query parameters
	 *
	 * @param	array	$query	The query string as a string (e.g. "foo=bar&moo=baz")
	 * @param	bool	$decode	If true, will decode
	 * @return	array
	 */
	protected static function convertQueryAsStringToArray( $query, $decode=FALSE )
	{
		$return = array();
		
		if ( $query !== NULL )
		{
			foreach ( explode( '&', $query ) as $part )
			{
				$keyAndValue = explode( '=', $part, 2 );
				
				if ( $decode )
				{
					$keyAndValue[0] = static::decodeComponent( static::COMPONENT_QUERY_KEY, $keyAndValue[0] );
					if ( isset( $keyAndValue[1] ) )
					{
						$keyAndValue[1] = static::decodeComponent( static::COMPONENT_QUERY_VALUE, $keyAndValue[1] );
					}
				}
				
				if ( isset( $keyAndValue[1] ) )
				{
					if ( preg_match( '/^([^\[]*)(\[.*\])$/', $keyAndValue[0], $matches ) )
					{
						static::_pushQueryStringPartIntoArray( $return, $matches[1], $matches[2], $keyAndValue[1] );
					}
					else
					{
						$return[ $keyAndValue[0] ] = (string) $keyAndValue[1];
					}
				}
				else
				{
					$return[ $keyAndValue[0] ] = NULL;
				}
			}
		}
		
		return $return;
	}
	
	/**
	 * Utility function used by convertQueryAsStringToArray()
	 *
	 * @param	array	$return			The current $return value being worked on, passed by reference
	 * @param	string	$mainKey		The main key, for example, if parsing "foo[x][y]=bar", the value will be "foo"
	 * @param	string	$subKeyNames	The sub keys in square brackets, for example, if parsing "foo[x][y]=bar", the value will be "[x][y]"
	 * @param	string	$value			The value, for example, if parsing "foo[x][y]=bar", the value will be "bar"
	 * @return	void
	 */
	protected static function _pushQueryStringPartIntoArray( &$return, $mainKey, $subKeyNames, $value )
	{
		preg_match_all( '/\[([^\]]*)\]/', $subKeyNames, $matches );
		
		if ( !isset( $return[ $mainKey ] ) )
		{
	        $return[ $mainKey ] = array();
	    }
	    
	    if ( \is_array( $return[ $mainKey ] ) )
	    {
		    $workingArray =& $return[ $mainKey ];
	    }
	    else
	    {
		    $workingArray[] =& $return[ $mainKey ];
	    }
		
		$last = array_pop( $matches[1] );
		foreach ( $matches[1] as $k )
		{
			if ( $k === '' )
			{
				$workingArray[] = array();
				$workingArray =& $workingArray[ \count( $workingArray ) - 1 ];
			}
			elseif ( !isset( $workingArray[ $k ] ) )
			{
				$workingArray[ $k ] = array();
				$workingArray =& $workingArray[ $k ];
			}
		}
		
		if ( $last === '' )
		{
	        $workingArray[] = $value;
	    }
	    else
	    {
	        $workingArray[ $last ] = $value;
	    }
	}

	/**
	 * Get FURL Definition
	 *
	 * @param	bool	$revert	If TRUE, ignores all customisations and reloads from json
	 * @return	array
	 */
	public static function furlDefinition( $revert=FALSE )
	{
		return \IPS\Http\Url\Friendly::furlDefinition( $revert );
	}

	/**
	 * Convert a value into an "SEO Title" for friendly URLs
	 *
	 * @param	string	$value	Value
	 * @return	string
	 * @note	Many places require an SEO title, so we always need to return something, so when no valid title is available we return a dash
	 */
	public static function seoTitle( $value )
	{
		return \IPS\Http\Url\Friendly::seoTitle( $value );
	}

	/**
	 * Return a partial query string based on any filters or sort options
	 *
	 * @param array $additionalParams
	 * @return	self
	 */
	public function getSafeUrlFromFilters( array $additionalParams = [] ): self
	{
		$extraUrlParams = [];

		foreach( array_merge( ['filter', 'sortby', 'sortdirection'], $additionalParams ) as $param )
		{
			if ( isset( \IPS\Request::i()->$param ) )
			{
				$extraUrlParams[ $param ] = \IPS\Request::i()->$param;
			}
		}

		if ( \count( $extraUrlParams ) )
		{
			return $this->setQueryString( $extraUrlParams );
		}
		else
		{
			return $this;
		}
	}
}