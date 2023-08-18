<?php
/**
 * @brief		Template Plugin - Member Data
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
 * Template Plugin - Member Data
 */
class _Member
{
	/**
	 * @brief	Can be used when compiling CSS
	 */
	public static $canBeUsedInCss = FALSE;
	
	/**
	 * Run the plug-in
	 *
	 * @param	string 		$data	  The initial data from the tag
	 * @param	array		$options    Array of options
	 * @return	string		Code to eval
	 */
	public static function runPlugin( $data, $options )
	{
		if ( $data !== 'link()' and isset( $options['group'] ) )
		{
			$data = "group['{$data}']";
		}

		$raw	= ( isset( $options['raw'] ) AND $options['raw'] ) ? TRUE : FALSE;
		
		if ( isset( $options['id'] ) )
		{
			$member = "\IPS\Member::load( {$options['id']} )";
		}
		else
		{
			$member = "\IPS\Member::loggedIn()";
		}

		if( $raw )
		{
			return "{$member}->$data";
		}
		else
		{
			return "htmlspecialchars( {$member}->$data, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE )";
		}
	}
}