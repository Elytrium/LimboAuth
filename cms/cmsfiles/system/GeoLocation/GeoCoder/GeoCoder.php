<?php
/**
 * @brief		GeoCoder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Dec 2017
 */

namespace IPS\GeoLocation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * GeoCoder abstract class
 */
abstract class _GeoCoder
{
	/**
	 * @brief	Cached GeoCoder instance
	 */
	protected static $instance = NULL;

	/**
	 * Return instance of GeoCoder
	 *
	 * @return	\IPS\GeoCoder
	 * @throws	\BadMethodCallException
	 */
	public static function i()
	{
		if( static::$instance === NULL )
		{
			if ( \IPS\Settings::i()->googlemaps and \IPS\Settings::i()->google_maps_api_key )
			{
				static::$instance = new \IPS\GeoLocation\GeoCoder\Google();
			}
			elseif ( \IPS\Settings::i()->mapbox and \IPS\Settings::i()->mapbox_api_key )
			{
				static::$instance = new \IPS\GeoLocation\GeoCoder\Mapbox();
			}
			else
			{
				throw new \BadMethodCallException;
			}
		}

		return static::$instance;
	}
}
