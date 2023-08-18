<?php
/**
 * @brief		Template Plugin - Theme Setting
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
 * Template Plugin - Theme settings
 */
class _Theme
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
	 * @note	Using this plugin to call the sharer logos or favicon is deprecated and will be removed in a future version
	 */
	public static function runPlugin( $data, $options )
	{
		switch( $data )
		{
			case 'headerHtml':
				return \IPS\Theme::i()->getHeaderAndFooter()['header'];
			break;
			case 'footerHtml':
				return \IPS\Theme::i()->getHeaderAndFooter()['footer'];
			break;
			case 'logo_front':
				return "\IPS\Theme::i()->logo_front";
			break;
			case 'logo_height':
				return ( isset( \IPS\Theme::i()->logo['front']['height'] ) ) ? '\IPS\Theme::i()->logo[\'front\'][\'height\']' : 100;
			break;
			default:
				return ( isset( \IPS\Theme::i()->settings[ $data ] ) ) ? "\IPS\Theme::i()->settings['{$data}']" : '';
			break;
		}
	}
}