<?php

/**
 * @brief		XenForo Tools Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		09 Jul 2019
 */

namespace IPS\convert\Tools;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Converter software exception
 */
trait Xenforo
{

	/**
	 * Helper to unpack an either serialized or json encoded value
	 *
	 * XenForo < 2.1 used serialize to store information, XenForo 2.1 moved to json encoded data
	 */
	public static function unpack( string $value )
	{
		if ( static::$useJson )
		{
			return json_decode( $value, TRUE );
		}
		else
		{
			return \unserialize( $value );
		}
	}

	/**
	 * Helper to fetch a xenforo phrase
	 *
	 * @param	string			$xfOneTitle		XF1 Phrase title
	 * @param	string			$xfTwoTitle		XF2 Phrase Title
	 * @return	string|null
	 */
	protected function getPhrase( $xfOneTitle, $xfTwoTitle )
	{
		try
		{
			$title = ( static::$isLegacy === FALSE OR \is_null( static::$isLegacy ) ) ? $xfTwoTitle : $xfOneTitle;
			return $this->db->select( 'phrase_text', 'xf_phrase', array( "title=?", $title ) )->first();
		}
		catch( \UnderflowException $e )
		{
			return NULL;
		}
	}

	/**
	 * @brief	Cache sprite image objects
	 */
	protected $_spriteImages = array();

	/**
	 * Return image from sprite - We must use GD for this, it's generally available on most servers
	 * And we don't have any relevant methods in either image handler for doing this completly within
	 * the image class
	 *
	 * @param	string		$sprite			Sprite path
	 * @param	array		$spriteParams	Sprite parameters
	 * @return	array
	 *
	 * @throws	\OutOfRangeException
	 * @throws	\InvalidArgumentException
	 */
	protected function _imageFromSprite( $sprite, $spriteParams )
	{
		$key = md5( $sprite );

		/* Set up image canvas */
		if( !isset( $this->_spriteImages[ $key ] ) )
		{
			if( !\file_exists( $sprite ) )
			{
				throw new \OutOfRangeException;
			}
			
			// Check valid image
			$image = \file_get_contents( $sprite );
			\IPS\Image::create( $image );
			$this->_spriteImages[ $key ] = new \IPS\Image\Gd( $image );
		}

		/* x2 image? */
		$multiplier = 1;
		if( $this->_spriteImages[ $key ]->width > $spriteParams['w'] )
		{
			$multiplier = 2;
		}

		$image = \IPS\Image\Gd::newImageCanvas( $spriteParams['w'] * $multiplier, $spriteParams['h'] * $multiplier, array( 0, 0, 0 ) );

		/* Set the background to transparent */
		imagefill( $image->image, 0, 0, imagecolorallocatealpha( $image->image, 0, 0, 0, 127 ) );

		/* Extract sprite */
		imagecopy( $image->image, $this->_spriteImages[ $key ]->image, 0, 0, abs( (int) $spriteParams['x'] ) * $multiplier, abs( (int) $spriteParams['y'] ) * $multiplier, $spriteParams['w'] * $multiplier, $spriteParams['h'] * $multiplier );

		/* x2 image? */
		$return = array();
		if( $multiplier == 2 )
		{
			$return['image_x2'] = (string) $image;

			/* Resize */
			$image->resize( $spriteParams['w'], $spriteParams['h'] );
		}

		$return['image'] = (string) $image;

		unset( $image );

		return $return;
	}
}