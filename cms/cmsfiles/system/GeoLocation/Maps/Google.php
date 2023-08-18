<?php
/**
 * @brief		Google Maps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Apr 2013
 */

namespace IPS\GeoLocation\Maps;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Google Maps
 */
class _Google
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
	 * @param	int			$scale	Google maps scale to use (https://developers.google.com/maps/documentation/static-maps/intro#scale_values)
	 * @param	string		$maptype	Type of map to use. Valid values are roadmap (default), satellite, terrain, and hybrid (https://developers.google.com/maps/documentation/static-maps/intro#MapTypes)
	 * @return	string
	 */
	public function render( $width, $height, $zoom=NULL, $scale=1, $maptype='roadmap' )
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_core.js', 'core', 'global' ) );

		return \IPS\Settings::i()->google_maps_static_use_embed ?
			\IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->googleMap( array(
				'lat' => $this->geolocation->lat,
				'long' => $this->geolocation->long,
				'key' => \IPS\Settings::i()->google_maps_api_key,
				'zoom' => $zoom,
				'scale' => $scale,
				'maptype' => $maptype,
				'width' => $width,
				'height' => $height )) :
			\IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->staticMap( \IPS\Http\Url::external( 'https://maps.google.com/' )->setQueryString( 'q', $this->_getLocation() ), $this->mapUrl( $width, $height, $zoom, $scale, $maptype ), $this->geolocation->lat, $this->geolocation->long, $width, $height );
	}

	/**
	 * Return the map image URL
	 *
	 * @param	int			$width	Width
	 * @param	int			$height	Height
	 * @param	float|NULL	$zoom	The zoom amount (a value between 0 being totally zoomed out view of the world, and 1 being as fully zoomed in as possible) or NULL to zoom automatically based on how much data is available
	 * @param	int			$scale	Google maps scale to use (https://developers.google.com/maps/documentation/static-maps/intro#scale_values)
	 * @param	string		$maptype	Type of map to use. Valid values are roadmap (default), satellite, terrain, and hybrid (https://developers.google.com/maps/documentation/static-maps/intro#MapTypes)
	 * @return	\IPS\Http\Url|NULL
	 */
	public function mapUrl( $width, $height, $zoom=NULL, $scale=1, $maptype='roadmap' )
	{
		$location = $this->_getLocation();
		
		return \IPS\Http\Url::external( 'https://maps.googleapis.com/maps/api/staticmap' )->setQueryString( array(
			'center'	=> $location,
			'zoom'		=> $zoom === NULL ? NULL : ceil( $zoom * 8 ),
			'size'		=> "{$width}x{$height}",
			'markers'	=> $location,
			'key'		=> \IPS\Settings::i()->google_maps_api_key,
			'scale'		=> $scale,
			'maptype'	=> $maptype
		) );
	}

	/**
	 * Fetch the location
	 *
	 * @return	string
	 */
	protected function _getLocation()
	{
		if ( $this->geolocation->lat and $this->geolocation->long )
		{
			$location = sprintf( "%F", $this->geolocation->lat ) . ',' . sprintf( "%F", $this->geolocation->long );
		}
		else
		{
			$location = array();
			foreach ( array( 'postalCode', 'country', 'region', 'city', 'addressLines' ) as $k )
			{
				if ( $this->geolocation->$k )
				{
					if ( \is_array( $this->geolocation->$k ) )
					{
						foreach ( array_reverse( $this->geolocation->$k ) as $v )
						{
							if( $v )
							{
								$location[] = $v;
							}
						}
					}
					else
					{
						$location[] = $this->geolocation->$k;
					}
				}
			}
			$location = implode( ', ', array_reverse( $location ) );
		}

		return $location;
	}
}