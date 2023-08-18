<?php
/**
 * @brief		Letter photo generator for member accounts
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Feb 2017
 */

namespace IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Letter photo generator for member accounts
 */
class _LetterPhoto
{
	/**
	 * @brief	Member name
	 */
	protected $memberName		= NULL;

	/**
	 * Create a new photo object
	 *
	 * @param	string	$name	Member name
	 * @return	void
	 */
	public function __construct( $name )
	{
		$this->memberName		= $name;
	}

	/**
	 * @brief	Color saturation to use for HSV colors
	 */
	protected $colorCodeSaturation	= .45;

	/** 
	 * @brief	Color value to use for HSV colors
	 */
	protected $colorCodeValue		= 0.575;

	/**
	 * @brief	If we have just generated a hue on this page load, store it to better randomize the next color
	 */
	protected static $lastHue		= NULL;

	/**
	 * Return a unique hex color code based on the name.
	 * 
	 * @return	string
	 */
	public function generateColorCode()
	{
		/* If we've already generated a hue on this page load, use the golden ratio to find a number sufficiently distinct */
		if( static::$lastHue )
		{
			$hue = static::$lastHue + 0.618033988749895;

			if( $hue > 1.0 )
			{
				$hue -= 1.0;
			}

			static::$lastHue = $hue;

			return $this->convertRgbToHex( $this->convertHsvToRgb( $hue, $this->colorCodeSaturation, $this->colorCodeValue ) );
		}

		$hue	= ( ( \ord( \mb_strtolower( $this->memberName ) ) - 97 ) / 25.0 ) + 0.25;
		$hue	*= 1.61803398875;
		$hue	*= (float) ( '1.' . rand() ) / 1;

		if( rand( 0, 1 ) )
		{
			$float = (float) ( "1." . \ord( array_rand( range( 'a', 'z' ) ) ) );
			$hue = abs( $float - $hue );
		}

		while( $hue > 1.0 )
		{
			$hue -= 1.0;
		}

		static::$lastHue = $hue;

		return $this->convertRgbToHex( $this->convertHsvToRgb( $hue, $this->colorCodeSaturation, $this->colorCodeValue ) );
	}

	/**
	 * Convert the HSV value to RGB
	 *
	 * @param	float	$h	Hue
	 * @param	float	$s	Saturation
	 * @param	float	$v	Value
	 * @return	array
	 */
	protected function convertHsvToRgb( $h, $s, $v )
	{
		$rgb	= array( $v, $v, $v );
		$diff	= ( $v <= 0.5 ) ? ( $v * ( 1.0 + $s ) ) : ( $v + $s - $v * $s );

		if( $diff )
		{
			$m	= $v + $v - $diff;
			$sv	= ( $diff - $m ) / $diff;
			$h	*= 6.0;
			$fract	= $h - floor( $h );
			$vsf	= $diff * $sv * $fract;
			$mid1	= $m + $vsf;
			$mid2	= $diff - $vsf;

			switch( floor( $h ) )
			{
				case 0:
					$rgb = array( $diff, $mid1, $m );
				break;

				case 1:
					$rgb = array( $mid2, $diff, $m );
				break;

				case 2:
					$rgb = array( $m, $diff, $mid1 );
				break;

				case 3:
					$rgb = array( $m, $mid2, $diff );
				break;

				case 4:
					$rgb = array( $mid1, $m, $diff );
				break;

				case 5:
					$rgb = array( $diff, $m, $mid2 );
				break;
			}
		}

		return array_map( function( $value ) { return round( $value * 256.0 ); }, $rgb );
	}

	/**
	 * Convert an RGB color value to hex
	 *
	 * @param	array 	$rgb	RGB color values
	 * @return	string
	 */
	protected function convertRgbToHex( array $rgb )
	{
		return sprintf( "%02x%02x%02x", $rgb[0], $rgb[1], $rgb[2] );
	}
}