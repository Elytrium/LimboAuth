<?php
/**
 * @brief		Documentation Helper
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Nov 2021
 */

namespace IPS\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Documentation Helper
 */
abstract class _DocumentationHelper
{

	public static function getDescriptionForClass( $class )
	{
		if ( method_exists( $class, 'apiOutput') )
		{
			$reflection = new \ReflectionMethod( $class, 'apiOutput' );
			$decoded = \IPS\Api\Controller::decodeDocblock( $reflection->getDocComment() );
			return \IPS\Theme::i()->getTemplate('api','core')->referenceTable( $decoded['details']['apiresponse'] );
		}

		return \IPS\Theme::i()->getTemplate( 'global' )->block( '', \IPS\Member::loggedIn()->language()->addToStack( 'class_no_apioutput_method' ) );
	}

	/**
	 * Get any additional classes referenced in the return types of this class
	 *
	 * @param	string	$class 		The classname
	 * @param	bool	$exclude	If FALSE, will include this class itself in the return array
	 * @return	array
	 */
	public static function getAdditionalClasses( $class, $exclude=FALSE )
	{
		if( !class_exists( $class ) or !method_exists( $class, 'apiOutput'))
		{
			return [];
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
						if( $returned = static::getAdditionalClasses( mb_substr( $response[0], 1, -1 ) ) )
						{
							$return = array_merge( $return, $returned );
						}
					}
				}
				elseif ( !\in_array( $response[0], $return ) )
				{
					if( $returned = static::getAdditionalClasses( $response[0] ) )
					{
						$return = array_merge( $return, $returned );
					}
				}
			}
		}
		return $return;
	}
}