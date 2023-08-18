<?php
/**
 * @brief		Template Plugin - URL
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
 * Template Plugin - URL
 */
class _Url
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
	 */
	public static function runPlugin( $data, $options )
	{
		$csrf = '';
		
		if ( isset( $options['csrf'] ) )
		{
			$csrf = ' . "&csrfKey=" . \IPS\Session::i()->csrfKey';
		}
		
		$location = ( \in_array( 'base', array_keys( $options ) ) ) ? '"' . $options['base'] . '"' : 'null';
		
		if ( !isset( $options['seoTemplate'] ) )
		{
			$options['seoTemplate'] = '';
		}
		if ( isset( $options['seoTitle'] ) )
		{
			$options['seoTitles'] = "array( {$options['seoTitle']} )";
		}
		elseif( !isset( $options['seoTitles'] ) )
		{
			$options['seoTitles'] = 'array()';
		}
		
		if ( !isset( $options['protocol'] ) )
		{
			$options['protocol'] = \IPS\Http\Url::PROTOCOL_AUTOMATIC;
		}
		
		$fragment = "";
		
		if ( isset( $options['fragment'] ) )
		{
			$fragment = "->setFragment(\"{$options['fragment']}\")";
		}
		
		$ref = '';
		if ( isset( $options['ref'] ) )
		{
			/* Is this a variable or other dynamic thing? */
			if ( \in_array( \substr( $options['ref'], 0, 1 ), array( '(', '$', '\\' ) ) )
			{
				$ref = "->addRef(";
				$ref .= $options['ref'];
				$ref .= ")";
			}
			else
			{
				/* It's not - just a normal string */
				$ref = "->addRef(\"{$options['ref']}\")";
			}
		}
		
		$_data = mb_substr( $data, 0, 1 ) == '$' ? $data : "\"$data\"";
		
		if ( isset( $options['plain'] ) )
		{
			$url = "\IPS\Http\Url::internal( {$_data}{$csrf}, {$location}, \"{$options['seoTemplate']}\", {$options['seoTitles']}, {$options['protocol']} )".$fragment.$ref;
		}
		elseif ( isset( $options['ips'] ) )
		{
			$url = "\IPS\Http\Url::ips( \"docs/$data\" )";
		}
		else
		{
			$url = "htmlspecialchars( \IPS\Http\Url::internal( {$_data}{$csrf}, {$location}, \"{$options['seoTemplate']}\", {$options['seoTitles']}, {$options['protocol']} ){$fragment}{$ref}, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', TRUE )";
		}

		if( isset( $options['noprotocol'] ) AND $options['noprotocol'] )
		{
			$url = "str_replace( array( 'http://', 'https://' ), '//', {$url} )";
		}

		return $url;
	}
}