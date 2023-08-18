<?php
/**
 * @brief		Template Plugin - Vendor prefix. Takes a CSS property name and returns a prefixed string.
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Aug 2013
 */

namespace IPS\Output\Plugin;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Template Plugin - Vendor prefix
 */
class _Prefix
{
	/**
	 * @brief	Can be used when compiling CSS
	 */
	public static $canBeUsedInCss = TRUE;
	
	/**
	 * Defined here so that we can remove names later as browser support for the non-prefixed version improves, without changing our css.
	 *
	 * @brief	The styles this plugin will prefix
	 */
	protected static $supportedStyles = array(
		'user-select'
	);

	/**
	 * @brief	Supported vendor prefixes
	 */
	protected static $prefixes = array( '-webkit-', '-moz-', '-ms-', '-o-', '' );

	/**
	 * Run the plug-in
	 *
	 * @param	string 		$data	  The initial data from the tag
	 * @param	array		$options    Array of options
	 * @return	string		Code to eval
	 * @note	Occasionally a style isn't consistent between browsers, so simply adding a prefix isn't always sufficient - you may need other CSS to resolve browser inconsistencies
	 */
	public static function runPlugin( $data, $options )
	{
		if ( \in_array( $data, static::$supportedStyles ) )
		{
			$output = array();

			foreach ( static::$prefixes as $prefix )
			{
				$output[] = $prefix . $data . ': ' . $options['value'] . ";";
			}

			return '"' . implode( "\r\n\t", $output ) . '"';
		}
		else
		{
			return '"' . $data . ': ' . $options['value'] . ";\r\n" . '"';
		}
	}
}