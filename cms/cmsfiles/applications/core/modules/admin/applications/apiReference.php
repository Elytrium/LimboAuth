<?php
/**
 * @brief		API Reference
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		03 Dec 2015
 */

namespace IPS\core\modules\admin\applications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * apiReference
 */
class _apiReference extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'api_reference' );
		parent::execute();
	}

	/**
	 * View Reference
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* If Opcache is enabled but opcache.save_comments is disabled, the API reference won't work */
		if ( ( \extension_loaded( 'opcache' ) OR \extension_loaded( 'Zend Opcache' ) ) AND ini_get( 'opcache.save_comments' ) == 0 )
		{
			\IPS\Output::i()->error( 'api_opcache_disable', '4C331/1', 403, '' );
		}

		/* Get endpoint details */
		$endpoints = \IPS\Api\Controller::getAllEndpoints();
		$selected = NULL;
		$content = '';
		
		/* If we're viewing a specific one, get information about it */
		if ( isset( \IPS\Request::i()->endpoint ) and array_key_exists( \IPS\Request::i()->endpoint, $endpoints ) )
		{
			$selected = \IPS\Request::i()->endpoint;
			
			$additionalClassesToReference = array();

			$params = array();
			if ( isset( $endpoints[ $selected ]['details']['reqapiparam'] ) )
			{
				$params = array_map( function( $v ) {
					$v[4] = 'required';
					return $v;
				}, $endpoints[ $selected ]['details']['reqapiparam'] );
			}
			if ( isset( $endpoints[ $selected ]['details']['apiparam'] ) )
			{
				$params = array_merge( $params, $endpoints[ $selected ]['details']['apiparam'] );
			}

			foreach( $params as $index => $data )
			{
				if ( mb_strpos( $data[0], '|' ) === FALSE AND !\in_array( $data[0], array( 'int', 'string', 'float', 'datetime', 'bool', 'object', 'array' ) ) )
				{
					if ( mb_substr( $data[0], 0, 1 ) == '[' )
					{
						if ( !\in_array( mb_substr( $data[0], 1, -1 ), array( 'int', 'string', 'float', 'datetime', 'bool', 'object', 'array' ) ) )
						{
							if( $returned = $this->_getAdditionalClasses( mb_substr( $data[0], 1, -1 ) ) )
							{
								$additionalClassesToReference = array_merge( $additionalClassesToReference, $returned );
							}
							elseif( $returned === NULL )
							{
								unset( $params[ $index ] );
							}
						}
					}
					else
					{
						if( $returned = $this->_getAdditionalClasses( $data[0] ) )
						{
							$additionalClassesToReference = array_merge( $additionalClassesToReference, $returned );
						}
						elseif( $returned === NULL )
						{
							unset( $params[ $index ] );
						}
					}
				}
			}

			$exceptions = isset( $endpoints[ $selected ]['details']['throws'] ) ? $endpoints[ $selected ]['details']['throws'] : NULL;
			if ( isset( $endpoints[ $selected ]['details']['apimemberonly'] ) )
			{
				$exceptions[] = array(
					'3S290/C',
					'MEMBER_ONLY',
					\IPS\Member::loggedIn()->language()->addToStack('api_endpoint_member_only_err')
				);
			}
			if ( isset( $endpoints[ $selected ]['details']['apiclientonly'] ) )
			{
				$exceptions[] = array(
					'3S290/D',
					'CLIENT_ONLY',
					\IPS\Member::loggedIn()->language()->addToStack('api_endpoint_client_only_err')
				);
			}

			$response = NULL;
			$return = array_filter( $endpoints[ $selected ]['details']['return'][0] );
			$return = array_pop( $return );
			if ( $return == 'array' )
			{
				if ( isset( $endpoints[ $selected ]['details']['apiresponse'] ) )
				{
					foreach ( $endpoints[ $selected ]['details']['apiresponse'] as $index => $data )
					{
						if ( mb_strpos( $data[0], '|' ) === FALSE AND !\in_array( $data[0], array( 'int', 'string', 'float', 'datetime', 'bool', 'object', 'array' ) ) )
						{
							if ( mb_substr( $data[0], 0, 1 ) == '[' )
							{
								if ( !\in_array( mb_substr( $data[0], 1, -1 ), array( 'int', 'string', 'float', 'datetime', 'bool', 'object', 'array' ) ) )
								{
									if( $returned = $this->_getAdditionalClasses( mb_substr( $data[0], 1, -1 ) ) )
									{
										$additionalClassesToReference = array_merge( $additionalClassesToReference, $returned );
									}
									elseif( $returned === NULL )
									{
										unset( $endpoints[ $selected ]['details']['apiresponse'][ $index ] );
									}
								}
							}
							else
							{
								if( $returned = $this->_getAdditionalClasses( $data[0] ) )
								{
									$additionalClassesToReference = array_merge( $additionalClassesToReference, $returned );
								}
								elseif( $returned === NULL )
								{
									unset( $endpoints[ $selected ]['details']['apiresponse'][ $index ] );
								}
							}
						}
					}
					$response = \IPS\Theme::i()->getTemplate('api')->referenceTable( $endpoints[ $selected ]['details']['apiresponse'] );
				}
			}
			elseif ( mb_substr( $return, 0, mb_strlen( '\IPS\Api\PaginatedResponse' ) ) == '\IPS\Api\PaginatedResponse' )
			{
				$class = mb_substr( trim( $return ), mb_strlen( '\IPS\Api\PaginatedResponse' ) + 1, -1 );
				$additionalClassesToReference = array_merge( $additionalClassesToReference, $this->_getAdditionalClasses( $class ) );
				$response = \IPS\Theme::i()->getTemplate('api')->referenceTable( array(
					array( 'int', 'page', 'api_int_page' ),
					array( 'int', 'perPage', 'api_int_perpage' ),
					array( 'int', 'totalResults', 'api_int_totalresults' ),
					array( 'int', 'totalPages', 'api_int_totalpages' ),
					array( "[{$class}]", 'results', 'api_results_thispage' ),
				) );
			}
			elseif ( $return = trim( $return ) and class_exists( $return ) and method_exists( $return, 'apiOutput' ) )
			{
				$additionalClassesToReference = array_merge( $additionalClassesToReference, $this->_getAdditionalClasses( $return, TRUE ) );
				$reflection = new \ReflectionMethod( $return, 'apiOutput' );
				$decoded = \IPS\Api\Controller::decodeDocblock( $reflection->getDocComment() );
				$response = \IPS\Theme::i()->getTemplate('api')->referenceTable( $decoded['details']['apiresponse'] );
			}
			
			$additionalClasses = array();
			foreach ( $additionalClassesToReference as $class )
			{
				$reflection = new \ReflectionMethod( $class, 'apiOutput' );
				$decoded = \IPS\Api\Controller::decodeDocblock( $reflection->getDocComment() );
				$additionalClasses[ mb_strtolower( mb_substr( $class, mb_strrpos( $class, '\\' ) + 1 ) ) ] = \IPS\Theme::i()->getTemplate('api')->referenceTable( $decoded['details']['apiresponse'] );
			}
			
			$content = \IPS\Theme::i()->getTemplate('api')->referenceEndpoint( $endpoints[ $selected ], $params, $exceptions, $response, $additionalClasses );
		}

		$endpointTree = array();
		foreach ( $endpoints as $key => $endpoint )
		{
			$pieces = explode('/', $key);
			$endpointTree[ $pieces[0] ][ $pieces[1] ][ $key ] = $endpoint;
		}
		
		if ( \IPS\Request::i()->endpoint and \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $content, 200, 'text/html' );
		}

		/* Output */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('api')->referenceTemplate( $endpoints, $endpointTree, $selected, $content );
	}
	
	/**
	 * Get any additional classes referenced in the return types of this class
	 *
	 * @param	string	$class 		The classname
	 * @param	bool	$exclude	If FALSE, will include this class itself in the return array
	 * @return	array|NULL
	 */
	protected function _getAdditionalClasses( $class, $exclude=FALSE )
	{
		if( !class_exists( $class ) )
		{
			return NULL;
		}

		$return = $exclude ? array() : array( $class => $class );
		$reflection = new \ReflectionMethod( $class, 'apiOutput' );
		$decoded = \IPS\Api\Controller::decodeDocblock( $reflection->getDocComment() );
		foreach ( $decoded['details']['apiresponse'] as $response )
		{
			if ( mb_strpos( $response[0], '|' ) === FALSE AND !\in_array( $response[0], array( 'int', 'string', 'float', 'datetime', 'bool', 'object', 'array' ) ) )
			{
				if ( mb_substr( $response[0], 0, 1 ) == '[' )
				{
					if ( !\in_array( mb_substr( $response[0], 1, -1 ), $return ) and !\in_array( mb_substr( $response[0], 1, -1 ), array( 'int', 'string', 'float', 'datetime', 'bool', 'object', 'array' ) ) )
					{
						if( $returned = $this->_getAdditionalClasses( mb_substr( $response[0], 1, -1 ) ) )
						{
							$return = array_merge( $return, $returned );
						}
					}
				}
				elseif ( !\in_array( $response[0], $return ) )
				{
					if( $returned = $this->_getAdditionalClasses( $response[0] ) )
					{
						$return = array_merge( $return, $returned );
					}
				}
			}
		}
		return $return;
	}
}