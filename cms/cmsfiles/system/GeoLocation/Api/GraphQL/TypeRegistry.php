<?php
/**
 * @brief		Type registry for \IPS\GeoLocation types
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		29 Aug 2018
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\GeoLocation\Api\GraphQL;
use GraphQL\Type\Definition\Type;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * \IPS\GeoLocation base types
 */
class _TypeRegistry
{
	protected static $geolocation;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Defined to suppress static warnings
	}
	
	/**
	 * @return GeoLocationType
	 */
	public static function geolocation()
	{
		return self::$geolocation ?: (self::$geolocation = new \IPS\GeoLocation\Api\GraphQL\GeoLocationType());
	}
}