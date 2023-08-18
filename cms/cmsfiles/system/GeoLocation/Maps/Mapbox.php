<?php
/**
 * @brief		Mapbox Maps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Nov 2017
 */

namespace IPS\GeoLocation\Maps;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Mapbox Maps
 */
class _Mapbox
{	
	/**
	 * @brief	GeoLocation
	 */
	public $geoLocation;

	/**
	 * Constructor
	 *
	 * @param	\IPS\GeoLocation	$geoLocation	Location
	 * @return	void
	 */
	public function __construct( \IPS\GeoLocation $geoLocation )
	{
		$this->geolocation	= $geoLocation;
	}
	
	/**
	 * Render
	 *
	 * @param	int			$width	Width
	 * @param	int			$height	Height
	 * @param	float|NULL	$zoom	The zoom amount (a value between 0 being totally zoomed out view of the world, and 1 being as fully zoomed in as possible) or NULL to zoom automatically based on how much data is available
	 * @return	string
	 */
	public function render( $width, $height, $zoom=NULL )
	{
		if( !($this->geolocation->long and $this->geolocation->lat ) )
		{
			return "";
		}

		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->staticMap( NULL, $this->mapUrl( $width, $height, $zoom ), $this->geolocation->lat, $this->geolocation->long, $width, $height );
	}

	/**
	 * Return the map image URL
	 *
	 * @param	int			$width	Width
	 * @param	int			$height	Height
	 * @param	float|NULL	$zoom	The zoom amount (a value between 0 being totally zoomed out view of the world, and 1 being as fully zoomed in as possible) or NULL to zoom automatically based on how much data is available
	 * @return	\IPS\Http\Url|NULL
	 */
	public function mapUrl( $width, $height, $zoom=NULL )
	{
		if( !($this->geolocation->long and $this->geolocation->lat ) )
		{
			return NULL;
		}

		$location = str_replace( ',', '.', $this->geolocation->long ) . ',' . str_replace( ',', '.', $this->geolocation->lat );

		return \IPS\Http\Url::external( "https://api.mapbox.com/styles/v1/mapbox/streets-v10/static/pin-l-marker+f00({$location})/{$location},14,0,60/{$width}x{$height}@2x" )->setQueryString( array(
			'access_token'	=> \IPS\Settings::i()->mapbox_api_key,
		) );
	}
}