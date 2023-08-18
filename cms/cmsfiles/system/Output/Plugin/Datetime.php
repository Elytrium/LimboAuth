<?php
/**
 * @brief		Template Plugin - DateTime
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 July 2013
 */

namespace IPS\Output\Plugin;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Template Plugin - DateTime
 */
class _Datetime
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
		$return = array();
		$return['pre'] = '$val = ( ' . $data . ' instanceof \IPS\DateTime ) ? ' . $data . ' : \IPS\DateTime::ts( ' . $data . ' );';

		if( isset( $options['dateonly'] ) )
		{
			$return['return'] = '(string) $val->localeDate()';
		}
		else if( isset( $options['norelative'] ) )
		{
			$return['return'] = '(string) $val';
		}
		elseif ( isset( $options['lowercase'] ) )
		{
			$return['return'] = '$val->html(FALSE)';
		}
		elseif ( isset( $options['short'] ) )
		{
			$return['return'] = '$val->html(TRUE, TRUE)';
		}
		else
		{
			$return['return'] = '$val->html()';
		}
				
		return $return;
	}
}