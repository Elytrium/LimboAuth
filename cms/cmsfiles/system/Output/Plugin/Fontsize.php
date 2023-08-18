<?php
/**
 * @brief		Template Plugin - Font size
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		9 Apr 2020
 */

namespace IPS\Output\Plugin;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Template Plugin - Font size
 */
class _Fontsize
{
	/**
	 * @brief	Can be used when compiling CSS
	 */
	public static $canBeUsedInCss = TRUE;
	
	/**
	 * Run the plug-in
	 *
	 * @param	string 		$data	  The initial data from the tag
	 * @param	array		$options    Array of options
	 * @return	string		Code to eval
	 */
	public static function runPlugin( $data, $options )
	{
		$number = 14;

		// Is this a theme setting or a number?
		if( preg_match('/^[0-9]+?$/', $data ) )
		{
			$number = \intval( $data );
		} 
		else if ( isset( \IPS\Theme::i()->settings[ 'font_' . $data ] ) ) 
		{
			$number = \IPS\Theme::i()->settings[ 'font_' . $data ];
		}

		$scale = isset( \IPS\Theme::i()->settings['font_size'] ) ? \intval( \IPS\Theme::i()->settings['font_size'] ) : 100;

		// Should we be scaling?
		if( $scale !== 100 && ( !isset( $options['scale'] ) || $options['scale'] !== false ) )
		{
			$number = round( $scale * ( $number / 100 ), 1 );
		}

        // e.g. 100 * 0.14 = 14px
        return '"' . number_format( $number, 1 ) . 'px"';
	}
}