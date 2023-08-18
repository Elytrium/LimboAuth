<?php
/**
 * @brief		Base class for Geolocation
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 Aug 2018
 */

namespace IPS\Geolocation\Api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Base class for GeoLocation
 */
class _GeoLocationType extends ObjectType
{
	/**
	 * Get root type
	 *
	 * @return	array
	 */
	public function __construct()
	{		 
		$config = [
			'name' => 'GeoLocation',
			'description' => 'Returns GeoLocation data',
			'fields' => [
				'id' => [
					'type' => TypeRegistry::id(),
					'resolve' => function ($geolocation) {
						// This field allows Apollo to more effectively cache results.
						return md5( $geolocation->toString() );
					}
				],
				'lat' => [
					'type' => TypeRegistry::float(),
					'resolve' => function ($geolocation) {
						return $geolocation->lat;
					}
				],
				'long' => [
					'type' => TypeRegistry::float(),
					'resolve' => function ($geolocation) {
						return $geolocation->long;
					}
				],
				'addressLines' => [
					'type' => TypeRegistry::string(),
					'resolve' => function ($geolocation) {
						return self::buildAddressPiece($geolocation, 'addressLines');
					}
				],
				'city' => [
					'type' => TypeRegistry::string(),
					'resolve' => function ($geolocation) {
						return self::buildAddressPiece($geolocation, 'city');
					}
				],
				'region' => [
					'type' => TypeRegistry::string(),
					'resolve' => function ($geolocation) {
						return self::buildAddressPiece($geolocation, 'region');
					}
				],
				'postalCode' => [
					'type' => TypeRegistry::string(),
					'resolve' => function ($geolocation) {
						return self::buildAddressPiece($geolocation, 'postalCode');
					}
				],
				'country' => [
					'type' => TypeRegistry::string(),
					'resolve' => function ($geolocation) {
						return self::buildAddressPiece($geolocation, 'country');
					}
				],
				'address' => [
					'type' => TypeRegistry::string(),
					'resolve' => function ($geolocation) {
						$output = array();

						foreach ( array( 'addressLines', 'city', 'region', 'postalCode', 'country' ) as $k )
						{
							if( $piece = self::buildAddressPiece($geolocation, $k) )
							{
								$output[] = $piece;
							}
						}

						return implode( ",\n", $output );
					}
				]
			]
		];

		parent::__construct($config);
	}

	protected static function buildAddressPiece($geolocation, $type)
	{
		$output = array();
		if ( $geolocation->$type )
		{
			if ( $type == 'country' )
			{
				if( $geolocation->country !== \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->site_address )->country )
				{
					try
					{
						$output[] = \IPS\Member::loggedIn()->language()->get( htmlspecialchars( 'country-' . $geolocation->country, ENT_DISALLOWED, 'UTF-8', FALSE ), FALSE, array( 'strtoupper' => TRUE ) );
					}
					catch ( \UnderflowException $e )
					{
						$output[] = htmlspecialchars( $geolocation->country, ENT_DISALLOWED, 'UTF-8', FALSE );
					}
				}
			}
			else
			{
				if ( \is_array( $geolocation->$type ) )
				{
					foreach ( $geolocation->$type as $v )
					{
						if( $v )
						{
							$output[] = htmlspecialchars( $v, ENT_DISALLOWED, 'UTF-8', FALSE );
						}
					}
				}
				else
				{
					$output[] = htmlspecialchars( $geolocation->$type, ENT_DISALLOWED, 'UTF-8', FALSE );
				}
			}
		}

		return implode("\n", $output);
	}
}