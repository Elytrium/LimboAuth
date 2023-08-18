<?php
/**
 * @brief		Base API Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Dec 2015
 */

namespace IPS\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Base API Controller
 */
abstract class _Controller
{
	/**
	 * @brief	API Key
	 */
	protected $apiKey;
	
	/**
	 * @brief	OAuth Client
	 */
	protected $client;
	
	/**
	 * @brief	OAuth Authenticated Member
	 */
	protected $member;
	
	/**
	 * @brief	OAuth Scopes
	 */
	protected $scopes;
	
	/**
	 * @brief	Used OAuth Scope
	 */
	protected $usedScope;

	/**
	 * @brief	Parameters to mask in logs. Keys are the method names and values an array of field or request keys.
	 */
	public $parametersToMask = array();

	/**
	 * @brief	Name of the method we called
	 */
	public $methodCalled	 = NULL;

	/**
	 * Constructor
	 *
	 * @param	\IPS\Api\Key	$apiKey			The API key being used to access, if applicable
	 * @param	array|null		$accessToken	Access token for OAuth-authenticated requests
	 * @return	void
	 */
	public function __construct( \IPS\Api\Key $apiKey = NULL, $accessToken = NULL )
	{
		$this->apiKey = $apiKey;
		
		if ( $accessToken )
		{
			$this->client = \IPS\Api\OAuthClient::load( $accessToken['client_id'] );
			$this->scopes = $accessToken['scope'] ? json_decode( $accessToken['scope'] ) : NULL;
			if ( $accessToken['member_id'] )
			{
				$this->member = \IPS\Member::load( $accessToken['member_id'] );
			}
		}
	}
	
	/**
	 * Check access
	 *
	 * @param	string	$app		Application key
	 * @param	string	$controller	Controller
	 * @param	string	$method		Method
	 * @return	bool
	 */
	protected function canAccess( $app, $controller, $method )
	{
		if ( $this->apiKey )
		{
			return $this->apiKey->canAccess( $app, $controller, $method );
		}
		else
		{
			if ( $this->usedScope = $this->client->scopesCanAccess( $this->scopes, $app, $controller, $method ) )
			{
				return TRUE;
			}
		}
		return FALSE;
	}
	
	/**
	 * Do we need to log this response?
	 *
	 * @param	string	$app		Application key
	 * @param	string	$controller	Controller
	 * @param	string	$method		Method
	 * @return	bool
	 */
	protected function shouldLog( $app, $controller, $method )
	{
		if ( $this->apiKey )
		{
			return $this->apiKey->shouldLog( $app, $controller, $method );
		}
		else
		{
			return $this->client->scopeShouldLog( $this->usedScope, $app, $controller, $method );
		}
	}
	
	/**
	 * Execute
	 *
	 * @param	array	$pathBits	The parts to the path called
	 * @param	bool	$shouldLog	Gets set to TRUE if this call should log
	 * @return	\IPS\Api\Response
	 * @throws	\IPS\Api\Exception
	 */
	public function execute( $pathBits, &$shouldLog )
	{
		$method = ( isset( $_SERVER['REQUEST_METHOD'] ) and \in_array( mb_strtoupper( $_SERVER['REQUEST_METHOD'] ), array( 'GET', 'POST', 'PUT', 'DELETE' ) ) ) ? mb_strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
		$params = array();
		
		try
		{
			$endpointData = $this->_getEndpoint( $pathBits, $method );
		}
		catch ( \RuntimeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_ENDPOINT', '2S291/1', 404 );
		}
				
		if ( method_exists( $this, "{$method}{$endpointData['endpoint']}" ) )
		{
			preg_match( '/^IPS\\\(.+?)\\\api\\\(.+?)$/', \get_called_class(), $matches );
			
			$shouldLog = $this->shouldLog( $matches[1], $matches[2], "{$method}{$endpointData['endpoint']}" );
			if ( !$this->canAccess( $matches[1], $matches[2], "{$method}{$endpointData['endpoint']}" ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2S291/7', 403, 'insufficient_scope' );
			}
			
			$reflection = new \ReflectionMethod( $this, "{$method}{$endpointData['endpoint']}" );
			$docBlock = static::decodeDocblock( $reflection->getDocComment() );
			if ( !$this->member )
			{
				if ( isset( $docBlock['details']['apimemberonly'] ) )
				{
					throw new \IPS\Api\Exception( 'NO_PERMISSION', '2S291/5', 403, 'insufficient_scope' );
				}
			}
			else
			{
				if ( isset( $docBlock['details']['apiclientonly'] ) )
				{
					throw new \IPS\Api\Exception( 'NO_PERMISSION', '2S291/6', 403, 'insufficient_scope' );
				}
			}
			
			$methodName = "{$method}{$endpointData['endpoint']}";
			$parameters = $endpointData['params'];

			$this->methodCalled = $methodName;

			return $this->$methodName( ...$parameters );
		}
		else
		{
			throw new \IPS\Api\Exception( 'BAD_METHOD', '3S291/2', 405 );
		}
	}
	
	/**
	 * Get endpoint data
	 *
	 * @param	array	$pathBits	The parts to the path called
	 * @param	string	$method		HTTP method verb
	 * @return	array
	 * @throws	\RuntimeException
	 */
	protected function _getEndpoint( $pathBits, $method = 'GET' )
	{
		$endpoint = NULL;
		$params = array();
		
		if ( \count( $pathBits ) === 0 )
		{
			$endpoint = 'index';
		}
		elseif ( \count( $pathBits ) === 1 )
		{
			/* Sometimes we want to do something like /api/core/clubs/contenttypes but this is treated as an item */
			if( \method_exists( $this, $method . $pathBits[0] ) )
			{
				$endpoint = array_shift( $pathBits );
			}
			else
			{
				$params[] = array_shift( $pathBits );
				$endpoint = 'item';
			}
		}
		elseif ( \count( $pathBits ) === 2 )
		{
			$params[] = array_shift( $pathBits );
			$endpoint = 'item_' . array_shift( $pathBits );
		}
		elseif ( \count( $pathBits ) === 3 )
		{
			$params[] = array_shift( $pathBits );
			$endpoint = 'item_' . array_shift( $pathBits );
			$params[] = array_shift( $pathBits );
		}
		elseif ( \count( $pathBits ) === 4 )
		{
			$params[] = array_shift( $pathBits );
			$endpoint = 'item_' . array_shift( $pathBits );
			$params[] = array_shift( $pathBits );
			$endpoint .= '_' . array_shift( $pathBits );
		}
		else
		{
			throw new \RuntimeException;
		}
		
		return array( 'endpoint' => $endpoint, 'params' => $params );
	}
	
	/**
	 * Get all endpoints
	 *
	 * @param	string|null	$type		If 'client' or 'member', will not include endpoints that are't applicable
	 * @param	bool		$includeAll	Should also disabled applications be returned
	 * @return	array
	 */
	public static function getAllEndpoints( $type = NULL, $includeAll = FALSE )
	{
		$return = array();
		foreach ( \IPS\Application::applications() as $app )
		{
			if ( $app->enabled OR $includeAll )
			{
				$apiDir = $app->getApplicationPath() . '/api';
				if ( file_exists( $apiDir ) )
				{
					$directory = new \DirectoryIterator( $apiDir );
					foreach ( $directory as $file )
					{
						if ( !$file->isDot() and mb_substr( $file, 0, 1 ) != '.' )
						{
							$controllerName = mb_substr( $file, 0, -4 );
							$class = 'IPS\\' . $app->directory . '\\api\\' . $controllerName;
							if( class_exists( $class ) )
							{
								$reflection = new \ReflectionClass( $class );
								foreach ( $reflection->getMethods() as $method )
								{
									if ( $method->getName() != 'execute' and !$method->isStatic() and $method->isPublic() and mb_substr( $method->getName(), 0, 1 ) != '_' )
									{
										$details = static::decodeDocblock( $method->getDocComment() );

										if ( ( $type !== 'client' or !isset( $details['details']['apimemberonly'] ) ) and ( $type !== 'member' or !isset( $details['details']['apiclientonly'] ) ) )
										{
											$return[ $app->directory . '/' . $controllerName . '/' . $method->getName() ] = $details;
										}
									}
								}
							}
						}
					}
				}
			}
		}
		return $return;
	}
	
	/**
	 * Decode docblock
	 *
	 * @param	string	$comment	The docblock comment
	 * @return	array
	 */
	public static function decodeDocblock( $comment )
	{
		$comment = explode( "\n", $comment );
		array_shift( $comment );
		$title = preg_replace( '/^\s*\*\s+?/', '', array_shift( $comment ) );
		$description = '';
		while ( $nextLine = array_shift( $comment ) )
		{
			if ( preg_match( '/^\s*\*\s*\/?\s*$/', $nextLine ) )
			{
				break;
			}
			$description .= preg_replace( '/^\s*\*\s+?/', '', $nextLine ) . "\n";
		}
		
		$params = array();
		while ( $nextLine = array_shift( $comment ) )
		{
			if ( preg_match( '/^\s*\*\s*@([a-z]*)(\t+([^\t]*))?(\t+([^\t]*))?(\t+([^\t]*))?$/', trim( $nextLine ), $matches ) )
			{
				$details = array();
				foreach ( array( 3, 5, 7 ) as $k )
				{
					if ( isset( $matches[ $k ] ) )
					{
						$details[] = trim( $matches[ $k ] );
					}
				}
				
				$key = $matches[1];
				if ( $key === 'clientapiresponse' )
				{
					$key = 'apiresponse';
					$details[4] = 'client';
				}
				
				$params[ $key ][] = $details;
			}
		}
		
		return array(
			'title'			=> $title,
			'description'	=> trim( $description ),
			'details'		=> $params
		);
	}

	/**
	 * Parses an endpoint key and modifies it for display
	 *
	 * @param	string	$endpoint	The endpoint (e.g: GET /core/members)
	 * @param 	string 	$size 		Size of badge to show
	 * @param	bool	$includeBaseUrl	Whether or not to include the URL base in the endpoint URL
	 * @return	string
	 */
	public static function parseEndpointForDisplay( $endpoint, $size='small', $includeBaseUrl=FALSE )
	{
		$badgeStyles = array(
			'GET' => 'ipsBadge_positive',
			'POST' => 'ipsBadge_style2',
			'DELETE' => 'ipsBadge_negative',
			'PUT' => 'ipsBadge_intermediary'
		);

		$pieces = explode( ' ', $endpoint );
		if ( !\in_array( $pieces[0], array_keys( $badgeStyles ) ) )
		{
			\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'api_endpoint_phpdoc_error', FALSE, array( "sprintf" => array( $endpoint ) ) ), '3S291/4', 500 );
		}
		$pieces[0] = "<span class='ipsBadge ipsBadge_{$size} " . $badgeStyles[ $pieces[0] ] . "'>" . $pieces[0] . "</span>";
		
		if ( $includeBaseUrl )
		{
			if ( \IPS\Settings::i()->use_friendly_urls and \IPS\Settings::i()->htaccess_mod_rewrite )
			{
				$url = \IPS\Http\Url::external( rtrim( \IPS\Settings::i()->base_url, '/' ) . '/api' );
			}
			else
			{
				$url = \IPS\Http\Url::external( rtrim( \IPS\Settings::i()->base_url, '/' ) . '/api/index.php?' );
			}
			$pieces[1] = $url . $pieces[1];
		}

		return implode( ' ', $pieces );
	}
}