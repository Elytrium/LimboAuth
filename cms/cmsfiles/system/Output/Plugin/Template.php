<?php
/**
 * @brief		Template Plugin - Template
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
 * Template Plugin - Template
 */
class _Template
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
	public static function runPlugin( $data, $options, $functionName=NULL, $calledClass='IPS\Theme' )
	{
		$params = ( \in_array( 'params', array_keys( $options ) ) ) ? $options['params'] : '';
		if( mb_strpos( $data, '$' ) === 0 )
		{
			$data = '{' . $data . '}';
		}
		
		if ( isset( $options['object'] ) )
		{
			return $options['object'] . "->{$data}( {$params} )";
		}
		else
		{
			$app    = isset( $options['app'] ) ? "\"{$options['app']}\"" : '\IPS\Request::i()->app';
			if ( isset( $options['location'] ) )
			{
				$app .= ", '{$options['location']}'";
			}
			
			if ( isset( $options['themeClass'] ) )
			{
				$calledClass = $options['themeClass'];
			}	
			
			$it = array( 'return' => '\\' . $calledClass . "::i()->getTemplate( \"{$options['group']}\", {$app} )->{$data}( {$params} )" );
			
			if ( isset( $options['if'] ) )
			{
				$it['pre']  = "if ( " . \IPS\Theme::expandShortcuts( $options['if'] ) . " ):\n";
				$it['post'] = "\nendif;";
			}
			
			return $it;
		}
	}
}