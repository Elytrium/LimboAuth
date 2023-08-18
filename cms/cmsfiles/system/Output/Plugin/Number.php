<?php
/**
 * @brief		Template Plugin - Number
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Jan 2014
 */

namespace IPS\Output\Plugin;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Template Plugin - Number
 */
class _Number
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
		if ( isset( $options['format'] ) and $options['format'] === 'short' )
		{
			return "\IPS\Member::loggedIn()->language()->formatNumberShort( {$data} )";
		}
		elseif ( isset( $options['decimals'] ) )
		{
			return "\IPS\Member::loggedIn()->language()->formatNumber( {$data}, {$options['decimals']} )";
		}
		
		return "\IPS\Member::loggedIn()->language()->formatNumber( {$data} )";
	}
}