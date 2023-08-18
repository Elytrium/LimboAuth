<?php
/**
 * @brief		Template Plugin - Money
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		6 Mar 2014
 */

namespace IPS\nexus\extensions\core\OutputPlugins;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Template Plugin - Money
 */
class _Money
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
		if ( isset( $options['currency'] ) )
		{
			if ( mb_substr( $options['currency'], 0, 1 ) !== '\\' and mb_substr( $options['currency'], 0, 1 ) !== '$' )
			{
				$options['currency'] = "'{$options['currency']}'";
			}
		}
		else
		{
			$options['currency'] = "( ( isset( \\IPS\\Request::i()->cookie['currency'] ) and \in_array( \\IPS\\Request::i()->cookie['currency'], \\IPS\\nexus\\Money::currencies() ) ) ? \\IPS\\Request::i()->cookie['currency'] : \\IPS\\nexus\\Customer::loggedIn()->defaultCurrency() )";
		}
		
		return 'new \IPS\nexus\Money( ' . $data . ", {$options['currency']} )";
	}
}