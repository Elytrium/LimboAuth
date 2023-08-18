<?php
/**
 * @brief		cURL REST Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Mar 2013
 */

namespace IPS\Http\Request;
 
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * cURL REST Class
 */
class _Curl
{
	/**
	 * @brief	URL
	 */
	protected $url = NULL;
	
	/**
	 * @brief	Curl Handle
	 */
	protected $curl = NULL;
	
	/**
	 * @brief	Has the Content-Type header been set?
	 * @note	Because cURL will automatically set the Content-Type header to multipart/form-data if we send a POST request with an array, we need to change it to a string if we want to send a different Content-Type
	 * @see		<a href='http://www.php.net/manual/en/function.curl-setopt.php'>PHP: curl_setopt - Manual</a>
	 */
	protected $modifiedContentType = FALSE;
	
	/**
	 * @brief	HTTP Version
	 */
	protected $httpVersion = '1.1';
	
	/**
	 * @brief	Timeout
	 */
	protected $timeout = 5;
	
	/**
	 * @brief	Follow redirects?
	 */
	protected $followRedirects = TRUE;

	/**
	 * @brief	Allowed protocols
	 */
	protected $allowedProtocols	= array();
	
	/**
	 * @brief	Data sent
	 */
	protected $dataForLog = NULL;

	/**
	 * @brief	Headers sent
	 */
	protected $headersForLog = [];
	
	/**
	 * Contructor
	 *
	 * @param	\IPS\Http\Url	$url				URL
	 * @param	int				$timeout			Timeout (in seconds)
	 * @param	string			$httpVersion		HTTP Version
	 * @param	bool|int		$followRedirects	Automatically follow redirects? If a number is provided, will follow up to that number of redirects
	 * @param	array|null		$allowedProtocols	Protocols allowed (if NULL we default to array( 'http', 'https', 'ftp', 'scp', 'sftp', 'ftps' ))
	 * @return	void
	 */
	public function __construct( $url, $timeout=5, $httpVersion=NULL, $followRedirects=TRUE, $allowedProtocols=NULL )
	{
		/* Init */
		$this->url						= $url;
		$this->curl						= curl_init();
		$this->httpVersion				= $httpVersion ?: '1.1';
		$this->timeout					= $timeout;
		$this->followRedirects			= $followRedirects;
		$this->allowedProtocols			= $allowedProtocols ?: array( 'http', 'https', 'ftp', 'scp', 'sftp', 'ftps' );

		/* Need to adjust if this is FTP */
		$user	= null;
		$pass	= null;

		if( isset( $this->url->data['scheme'] ) AND $this->url->data['scheme'] == 'ftp' )
		{
			if( isset( $this->url->data['user'] ) AND $this->url->data['user'] AND isset( $this->url->data['pass'] ) AND $this->url->data['pass'] )
			{
				$user	= $this->url->data['user'];
				$pass	= $this->url->data['pass'];

				$this->url->data['user']	= null;
				$this->url->data['pass']	= null;
				$this->url	= $this->url->setFragment( null );
			}

			/* Set our basic settings */
			curl_setopt_array( $this->curl, array(
				CURLOPT_HEADER			=> TRUE,								// Specifies that we want the headers
				CURLOPT_RETURNTRANSFER	=> TRUE,								// Specifies that we want the response
				CURLOPT_SSL_VERIFYPEER	=> FALSE,								// Specifies that we don't need to validate the SSL certificate, if applicable (causes issues with, for example, API calls to CPanel in Nexus)
				CURLOPT_TIMEOUT			=> $timeout,							// The timeout
				CURLOPT_URL				=> (string) $this->url,					// The URL we're requesting
				) );

			/* Need to set user and pass if this is FTP */
			if( $user !== null AND $pass !== null )
			{
				curl_setopt( $this->curl, CURLOPT_USERPWD, $user . ':' . $pass );
			}
		}
		else
		{
			/* Work out HTTP version */
			if( $httpVersion === null )
			{
				$version = curl_version();

				/* Before 7.36 there are some issues handling chunked-encoded data */
				if( version_compare( $version['version'], '7.36', '>=' ) )
				{
					$httpVersion = '1.1';
				}
				else
				{
					$httpVersion = '1.0';
				}
			}

			$httpVersion = ( $httpVersion == '1.1' ? CURL_HTTP_VERSION_1_1 : CURL_HTTP_VERSION_1_0 );
						
			/* Set our basic settings */
			curl_setopt_array( $this->curl, array(
				CURLOPT_HEADER			=> TRUE,								// Specifies that we want the headers
				CURLOPT_HTTP_VERSION	=> $httpVersion,						// Sets the HTTP version
				CURLOPT_RETURNTRANSFER	=> TRUE,								// Specifies that we want the response
				CURLOPT_SSL_VERIFYPEER	=> FALSE,								// Specifies that we don't need to validate the SSL certificate, if applicable (causes issues with, for example, API calls to CPanel in Nexus)
				CURLOPT_TIMEOUT			=> $timeout,							// The timeout
				CURLOPT_URL				=> (string) $this->url,					// The URL we're requesting
				) );
		}
	}
	
	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		curl_close( $this->curl );
		if( static::$_multiHandle instanceof \CurlMultiHandle )
		{
			curl_multi_close( static::$_multiHandle );
		}
	}
	
	/**
	 * Login
	 *
	 * @param	string	$username		Username
	 * @param	string	$password		Password
	 * @return	static
	 */
	public function login( $username, $password )
	{
		curl_setopt_array( $this->curl, array(
			CURLOPT_HTTPAUTH		=> CURLAUTH_BASIC,
			CURLOPT_USERPWD			=> "{$username}:{$password}"
			) );
		
		return $this;
	}
	
	/**
	 * Set Headers
	 *
	 * @param	array	$headers		Key/Value pair of headers
	 * @return	static
	 */
	public function setHeaders( $headers )
	{
		$extra = array();
	
		foreach ( $headers as $k => $v )
		{
			switch ( $k )
			{
				case 'Cookie':
					curl_setopt( $this->curl, CURLOPT_COOKIE, $v );
					break;
				
				case 'Accept-Encoding':
					curl_setopt( $this->curl, CURLOPT_ENCODING, $v );
					break;
				
				case 'Referer':
					curl_setopt( $this->curl, CURLOPT_REFERER, $v );
					break;
					
				case 'User-Agent':
					curl_setopt( $this->curl, CURLOPT_USERAGENT, $v );
					break;
					
				default:
					if ( $k === 'Content-Type' )
					{
						$this->modifiedContentType = TRUE;
					}
					$extra[] = "{$k}: {$v}";
					break;
			}

			$this->headersForLog[ $k ] = "{$k}: {$v}";
		}
		
		if ( !empty( $extra ) )
		{
			curl_setopt( $this->curl, CURLOPT_HTTPHEADER, $extra );
		}
		
		return $this;
	}
	
	
	/**
	 * Toggle SSL checks
	 *
	 * @param	boolean		$value	True will enable SSL checks, false will disable them
	 * @return	static
	 */
	public function sslCheck( $value=TRUE )
	{
		curl_setopt_array( $this->curl, array(
			CURLOPT_SSL_VERIFYHOST  => ( $value ) ? 2 : FALSE,
			CURLOPT_SSL_VERIFYPEER	=> (boolean) $value
		) );
		
		return $this;
	}
	
	/**
	 * Force TLS
	 *
	 * @return	static
	 */
	public function forceTls()
	{
		$curlVersionData = curl_version();
		if ( preg_match( '/^OpenSSL\/(\d+\.\d+\.\d+)/', $curlVersionData['ssl_version'], $openSSLVersionData ) )
		{
			if ( version_compare( $openSSLVersionData[1], '1.0.1', '>=' ) )
			{
				if ( !\defined('CURL_SSLVERSION_TLSv1_2') ) // constant not defined in PHP < 5.5
	            {
	                \define( 'CURL_SSLVERSION_TLSv1_2', 6 );
	            }
	            curl_setopt( $this->curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2 );
			}
			else
			{
				if ( !\defined('CURL_SSLVERSION_TLSv1') ) // constant not defined in PHP < 5.5
	            {
	                \define( 'CURL_SSLVERSION_TLSv1', 1 );
	            }
	            curl_setopt( $this->curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1 );
			}
		}
        		
		return $this;
	}
	
	/**
	 * HTTP GET
	 *
	 * @param	mixed	$data	Data to send with the GET request
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	public function get( $data=NULL )
	{
		/* Specify that this is a GET request */
		curl_setopt( $this->curl, CURLOPT_HTTPGET, TRUE );
		$this->dataForLog = NULL;
		
		if ( $data )
		{
			curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $data );
		}
					
		return $this->_executeAndFollowRedirects( 'GET', NULL );
	}
	
	/**
	 * HTTP POST
	 *
	 * @param	mixed	$data	Data to post (can be array or string)
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	public function post( $data=NULL )
	{		
		/* Specify that this is a POST request */
		curl_setopt( $this->curl, CURLOPT_POST, TRUE );
		
		/* Set the data */
		curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $this->_dataToSend( $data ) );
		
		/* Execute */
		return $this->_executeAndFollowRedirects( 'POST', $data );
	}
	
	/**
	 * HTTP HEAD
	 *
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	public function head()
	{	
		/* Specify the request method */
		curl_setopt( $this->curl, CURLOPT_CUSTOMREQUEST, 'HEAD' );

		/* For HEAD requests, do not try to fetch the body or curl times out */
		curl_setopt( $this->curl, CURLOPT_NOBODY, true );
		
		/* Execute */
		return $this->_executeAndFollowRedirects( 'HEAD', NULL );
	}
		
	/**
	 * Magic Method: __call
	 * Used for other HTTP methods (like PUT and DELETE)
	 *
	 * @param	string	$method	Method (A HTTP method)
	 * @param	array	$params	Parameters (a single parameter with data to post, which can be an array or a string)
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	public function __call( $method, $params )
	{		
		/* Specify the request method */
		curl_setopt( $this->curl, CURLOPT_CUSTOMREQUEST, mb_strtoupper( $method ) );

		/* If we have any data to send, set it */
		if ( isset( $params[0] ) )
		{
			curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $this->_dataToSend( $params[0] ) );
		}
		
		/* Execute */
		return $this->_executeAndFollowRedirects( mb_strtoupper( $method ), $params );
	}
	
	/**
	 * Data to send
	 *
	 * @param	mixed	$data	Data to post (can be array or string)
	 * @return	mixed
	 */
	protected function _dataToSend( $data=NULL )
	{	
		$this->dataForLog = $data;
		if ( !$this->modifiedContentType and \is_array( $data ) )
		{
			$data = http_build_query( $data, '', '&' );
		}
		return $data;
	}
	
	/**
	 * Execute the request
	 *
	 * @todo    Remove if multi handle works out long term.
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	protected function _execute()
	{
		/* Execute */
		$output = curl_exec( $this->curl );
				
		/* Log - but because the output can be large, only do this if we explicitly have debug logging enabled */
		if ( \defined('\IPS\DEBUG_LOG') and \IPS\DEBUG_LOG )
		{
			\IPS\Log::debug( "\n\n------------------------------------\ncURL REQUEST: {$this->url}\n------------------------------------\n\n" . implode( "\n", $this->headersForLog ) . "\n\n"  . var_export( $this->dataForLog, TRUE ) . "\n\n------------------------------------\nRESPONSE\n------------------------------------\n\n" . $output, 'request' );
		}
		
		/* Errors? */
		if ( $output === FALSE )
		{
			throw new CurlException( $this->url . "\n" . curl_error( $this->curl ), curl_errno( $this->curl ) );
		}

		/* If this is FTP we need to fudge the headers a little */
		if( isset( $this->url->data['scheme'] ) and $this->url->data['scheme'] == 'ftp' )
		{
			$output = "HTTP/1.1 200 OK\nFTP: True\r\n\r\n" . $output;
		}
				
		/* Return it */
		return new \IPS\Http\Response( $output );
	}

	/**
	 * @breif   Store the cURL Multi Handle
	 */
	protected static $_multiHandle;

	/**
	 * Execute the request via cURL Multi - We use this to cache and share connections between requests
	 *
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	protected function _executeMh(): \IPS\Http\Response
	{
		/* Init multi handle if needed */
		if( empty( static::$_multiHandle ) )
		{
			static::$_multiHandle = curl_multi_init();
		}

		/* Store handle for this request */
		curl_multi_add_handle( static::$_multiHandle, $this->curl );

		/* Execute request and wait for response */
		$active = NULL;
		do
		{
			$ret = curl_multi_exec( static::$_multiHandle, $active );
		}
		while( $ret == CURLM_CALL_MULTI_PERFORM );

		while( $active && $ret == CURLM_OK )
		{
			if( curl_multi_select( static::$_multiHandle ) != -1 )
			{
				do
				{
					$mrc = curl_multi_exec( static::$_multiHandle, $active );
				}
				while( $mrc == CURLM_CALL_MULTI_PERFORM );
			}
		}

		$output = curl_multi_getcontent( $this->curl );

		/* Remove handles we no longer need */
		curl_multi_remove_handle( static::$_multiHandle, $this->curl );

		/* Log - but because the output can be large, only do this if we explicitly have debug logging enabled */
		if ( \defined('\IPS\DEBUG_LOG') and \IPS\DEBUG_LOG )
		{
			\IPS\Log::debug( "\n\n------------------------------------\ncURL REQUEST: {$this->url}\n------------------------------------\n\n" . implode( "\n", $this->headersForLog ) . "\n\n"  . var_export( $this->dataForLog, TRUE ) . "\n\n------------------------------------\nRESPONSE\n------------------------------------\n\n" . $output, 'request' );
		}

		/* Errors? */
		if ( $output === FALSE )
		{
			throw new CurlException( $this->url . "\n" . curl_error( $this->curl ), curl_errno( $this->curl ) );
		}

		/* If this is FTP we need to fudge the headers a little */
		if( isset( $this->url->data['scheme'] ) and $this->url->data['scheme'] == 'ftp' )
		{
			$output = "HTTP/1.1 200 OK\nFTP: True\r\n\r\n" . $output;
		}

		/* Return it */
		return new \IPS\Http\Response( $output );
	}
	
	/**
	 * Execute the request and follow redirects id necessary
	 *
	 * @param	string		$method		Request method to use
	 * @param	NULL|array 	$params		Parameters to send with request
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	protected function _executeAndFollowRedirects( $method, $params=NULL )
	{
		/* Execute */
		$response = $this->_executeMh();
		
		/* Either return it or follow it */
		if ( $this->followRedirects and \in_array( $response->httpResponseCode, array( 301, 302, 303, 307, 308 ) ) )
		{
			/* Fix missing hostname in location */
			foreach( $response->httpHeaders as $k => $v )
			{
				if( mb_strtolower( $k ) == 'location' )
				{
					$location = $v;
				}
			}

			if( parse_url( $location, PHP_URL_HOST ) === NULL )
			{
				$location = $this->url->data['scheme'] . '://' . $this->url->data['host'] . $location;
			}

			$newRequest = \IPS\Http\Url::external( $location );

			if( !\in_array( $newRequest->data['scheme'], $this->allowedProtocols ) )
			{
				throw new \IPS\Http\Request\Exception( 'protocol_not_followed' );
			}

			$newRequest = $newRequest->request( $this->timeout, $this->httpVersion, \is_int( $this->followRedirects ) ? ( $this->followRedirects - 1 ) : $this->followRedirects );
			return $newRequest->$method( $params );
		}
		return $response;
	}
}

/**
 * CURL Exception Class
 */
class CurlException extends \IPS\Http\Request\Exception { }