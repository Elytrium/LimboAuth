<?php
/**
 * @brief		Template Plugin - Lang
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
 * Template Plugin - Lang
 */
class _Lang
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
	 * @return	array		array( 'pre' => Code to eval before 'return', 'return' => Code to eval to return desired value )
	 */
	public static function runPlugin( $data, $options )
	{		
		$return = array();
		$return['pre'] = null;
		$params = array();
		
		$data = \IPS\Theme::expandShortcuts( $data );
	
		if( \substr( $data, 0, 1 ) !== '\\' and \strpos( $data, '$' ) !== FALSE )
		{
			if ( \strpos( $data, '$' ) === 0 )
			{
			
				$data = '{' . $data . '}';
			}
			$return['pre'] .= '$val = "' . $data . "\"; ";
			$data = '$val';
		}
		else
		{
			$data = "'{$data}'";
		}
		
		if ( isset( $options['list'] ) )
		{
			$return['pre'] .= '$sprintf = array( \IPS\Member::loggedIn()->language()->formatList(' . $options['list'] . ') );';
			$params[]= '\'sprintf\' => $sprintf';
		}
		
		if ( isset( $options['htmllist'] ) )
		{
			$return['pre'] .= '$htmlsprintf = array( \IPS\Member::loggedIn()->language()->formatList(' . $options['htmllist'] . ') );';
			$params[]= '\'htmlsprintf\' => $htmlsprintf';
		}
		
		if( isset( $options['sprintf'] ) )
		{
			$return['pre'] .= '$sprintf = array(' . $options['sprintf'] . '); ';	
			$params[]= '\'sprintf\' => $sprintf';
		}

		if( isset( $options['htmlsprintf'] ) )
		{
			$return['pre'] .= '$htmlsprintf = array(' . $options['htmlsprintf'] . '); ';	
			$params[]= '\'htmlsprintf\' => $htmlsprintf';
		}
	
		if( isset( $options['pluralize'] ) )
		{
			$return['pre'] .= '$pluralize = array( ' . $options['pluralize'] . ' ); ';
			$params[] = '\'pluralize\' => $pluralize';
			
			if ( isset( $options['format'] ) )
			{
				$params[] = '\'format\' => ' . "'" . $options['format'] . "'";
			}
		}

		if( isset( $options['wordbreak'] ) )
		{
			$params[] = '\'wordbreak\' => TRUE';
		}

		if( isset( $options['escape'] ) )
		{
			$params[] = '\'escape\' => TRUE';
		}

		if( isset( $options['ucfirst'] ) )
		{
			$params[] = '\'ucfirst\' => TRUE';
		}

		$vle	= 'TRUE';

		if( \count( $params ) )
		{
			$vle	= 'FALSE';
		}
		
		$return['return'] = '\IPS\Member::loggedIn()->language()->addToStack( htmlspecialchars( ' . $data . ', ENT_DISALLOWED, \'UTF-8\', FALSE ), ' . $vle . ', array( ' . implode( ", ", $params ) . ' ) )';
	
		return $return;
	}

}