<?php
/**
 * @brief		Template Plugin - Theme Resource (image, font, theme-specific JS, etc)
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Output\Plugin;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Template Plugin - Theme Resource (image, font, theme-specific JS, etc)
 */
class _Resource
{
	/**
	 * @brief	Can be used when compiling CSS
	 */
	public static $canBeUsedInCss = TRUE;
	
	/**
	 * Run the plug-in
	 *
	 * @param	string 		$data		The initial data from the tag
	 * @param	array		$options    Array of options
	 * @param	string		$context	The name of the calling function
	 * @return	string		Code to eval
	 */
	public static function runPlugin( $data, $options, $context )
	{	
		$exploded = explode( '_', $context );
		$app      = ( isset( $options['app'] )      ? $options['app']      : ( isset( $exploded[1] ) ? $exploded[1] : '' ) );
		$location = ( isset( $options['location'] ) ? $options['location'] : ( isset( $exploded[2] ) ? $exploded[2] : '' ) );
		$noProtocol =  ( isset( $options['noprotocol'] ) ) ? $options['noprotocol'] : "false";

		return "\\IPS\\Theme::i()->resource( \"{$data}\", \"{$app}\", '{$location}', {$noProtocol} )";
	}
}