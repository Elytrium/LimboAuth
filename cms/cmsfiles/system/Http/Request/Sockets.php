<?php
/**
 * @brief		Sockets REST Class
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
 * Sockets REST Class
 */
class _Sockets
{
	/**
	 * @brief	URL
	 */
	protected $url = NULL;

	/**
	 * @brief   Stream context
	 */
	protected $context;

	/**
	 * @brief	HTTP Version
	 */
	protected $httpVersion = '1.1';

	/**
	 * @brief	Timeout
	 */
	protected $timeout = 5;

	/**
	 * @brief	Headers
	 */
	protected $headers = array();

	/**
	 * @brief	Follow redirects?
	 */
	protected $followRedirects = TRUE;

	/**
	 * @brief	Allowed protocols
	 */
	protected $allowedProtocols	= array();

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
		$this->url						= $url;
		$this->context					= stream_context_create();
		$this->httpVersion				= $httpVersion ?: '1.1';
		$this->timeout					= $timeout;
		$this->followRedirects			= $followRedirects;
		$this->allowedProtocols			= $allowedProtocols ?: array( 'http', 'https', 'ftp', 'scp', 'sftp', 'ftps' );

		/* Set our basic settings */
		stream_context_set_option( $this->context, array(
			'http'  => array(
				'protocol_version'  => $httpVersion,
				'follow_location'   => $followRedirects,
				'timeout'           => $timeout,
				'ignore_errors'     => TRUE,
			),
			'ssl'   => array(
				'verify_peer'       => FALSE,
				'crypto_method'		=> STREAM_CRYPTO_METHOD_ANY_CLIENT
			)
		) );
	}

	/**
	 * Login
	 *
	 * @param	string	$username	Username
	 * @param	string	$password	Password
	 * @return	static (for daisy chaining)
	 */
	public function login( $username, $password )
	{
		$this->setHeaders( array( 'Authorization' => 'Basic ' . base64_encode( "{$username}:{$password}" ) ) );
		return $this;
	}

	/**
	 * Set Headers
	 *
	 * @param	array	$headers	Key/Value pair of headers
	 * @return	static
	 */
	public function setHeaders( $headers )
	{
		$this->headers = array_merge( $this->headers, $headers );
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
		stream_context_set_option( $this->context, array(
			'ssl'   => array(
				'verify_peer_name'  => ( $value ) ? 2 : FALSE,
				'verify_peer'       => (boolean) $value,
			)
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
		if ( \defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') )
		{
			stream_context_set_option( $this->context, array(
				'ssl'   => array(
					'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
				)
			) );
		}
		elseif ( \defined('STREAM_CRYPTO_METHOD_TLS_CLIENT') )
		{
			stream_context_set_option( $this->context, array(
				'ssl'   => array(
					'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT
				)
			) );
		}

		return $this;
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
		$method = mb_strtoupper( $method );

		/* The data (string or array) will be the first parameter */
		if ( isset( $params[0] ) && \is_array( $params[0] ) )
		{
			$this->setHeaders( array( 'Content-Type' => 'application/x-www-form-urlencoded' ) );
			$data = http_build_query( $params[0], '', '&' );
		}
		else
		{
			$data = ( isset( $params[0] ) ? $params[0] : NULL );
		}

		/* Set the method and the Content-Length header if this is a POST, PUT or PATCH request */
		stream_context_set_option( $this->context, 'http', 'method', $method );

		if( $data )
		{
			$this->setHeaders( array( 'Content-Length' => \strlen( $data ) ) );
		}

		/* Parse URL */
		if ( isset( $this->url->data['user'] ) or isset( $this->url->data['pass'] ) )
		{
			$this->login( isset( $this->url->data['user'] ) ? $this->url->data['user'] : NULL, isset( $this->url->data['pass'] ) ? $this->url->data['pass'] : NULL );
		}

		$hostname = sprintf( '%s%s:%d',
			( $this->url->data['scheme'] === 'https' ) ? 'ssl://' : '',
			$this->url->data['host'],
			isset( $this->url->data['port'] )
				? $this->url->data['port']
				: ( $this->url->data['scheme'] === 'http' ? 80 : 443 )
		);

		/* Open connection */
		try
		{
			$resource = stream_socket_client( $hostname, $errno, $errstr, $this->timeout, \STREAM_CLIENT_CONNECT, $this->context );
		}
			/* Catch issues that may arise, such as DNS failure */
		catch( \ErrorException $e )
		{
			throw new SocketsException( $e->getMessage(), $e->getCode() );
		}

		if ( $resource === FALSE )
		{
			throw new SocketsException( $errstr, $errno );
		}

		/* Get the location */
		$location	= $this->url->data['path'] ? \IPS\Http\Url::encodeComponent( \IPS\Http\Url::COMPONENT_PATH, $this->url->data['path'] ) : '';
		$location	.= ( \count( $this->url->queryString ) ) ? '?' . \IPS\Http\Url::convertQueryAsArrayToString( $this->url->queryString, true ) : '';
		$location	.= $this->url->data['fragment'] ? '#' . \IPS\Http\Url::encodeComponent( \IPS\Http\Url::COMPONENT_FRAGMENT, $this->url->data['fragment'] ) : '';

		/* Send request */
		$request  = mb_strtoupper( $method ) . ' /' . ltrim( $location, '/' ) . " HTTP/{$this->httpVersion}\r\n";
		$request .= "Host: {$this->url->data['host']}" . ( isset( $this->url->data['port'] ) ? ":{$this->url->data['port']}" : '' ) . "\r\n";

		$headersForLog = [];
		foreach ( $this->headers as $k => $v )
		{
			$request .= "{$k}: {$v}\r\n";
			$headersForLog[ $k ] = "{$k}: {$v}";
		}

		$request .= "Connection: Close\r\n";
		$request .= "\r\n";

		if ( $data )
		{
			$request .= $data;
		}

		\fwrite( $resource, $request );

		/* Read response */
		stream_set_timeout( $resource, $this->timeout );
		$status = stream_get_meta_data( $resource );

		$response = '';
		while( !feof($resource) and !$status['timed_out'] )
		{
			$response .= \fgets( $resource, 8192 );
			$status = stream_get_meta_data( $resource );
		}

		/* Close connection */
		\fclose( $resource );

		/* Log - but because the output can be large, only do this if we explicitly have debug logging enabled */
		if ( \defined('\IPS\DEBUG_LOG') and \IPS\DEBUG_LOG )
		{
			\IPS\Log::debug( "\n\n------------------------------------\nSOCKETS REQUEST: {$this->url}\n------------------------------------\n\n" . implode( "\n", $headersForLog ) . "\n\n{$request}\n\n------------------------------------\nRESPONSE\n------------------------------------\n\n" . $response, 'request' );
		}

		/* Interpret response */
		$response = new \IPS\Http\Response( $response );

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
 * Sockets Exception Class
 */
class SocketsException extends \IPS\Http\Request\Exception { }