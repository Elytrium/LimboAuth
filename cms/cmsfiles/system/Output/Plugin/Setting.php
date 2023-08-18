<?php
/**
 * @brief		Template Plugin - Setting
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
 * Template Plugin - Setting
 */
class _Setting
{
	/**
	 * @brief	Can be used when compiling CSS
	 * @note	Using setting in plugins will require a rebuild of the CSS file they're used in when the setting is changed
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
		if ( isset( $options['escape'] ) )
		{
			return "htmlspecialchars( \IPS\Settings::i()->$data, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', TRUE )";
		}
		else
		{
			return "\IPS\Settings::i()->$data";
		}
	}
}