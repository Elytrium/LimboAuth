<?php
/**
 * @brief		Template Plugin - File
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 April 2015
 */

namespace IPS\Output\Plugin;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Template Plugin - File
 */
class _File
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
		$extension = ( isset( $options['extension'] )  ? $options['extension'] : 'core_Attachment' );
		$scheme    = ( isset( $options['scheme'] )  ? $options['scheme'] : NULL );
		$schemeString = '';

		if ( $scheme )
		{
			$fullScheme = ( \IPS\Request::i()->isSecure() ) ? 'https' : 'http';
			$schemeString = ( $scheme == 'full' ) ? '->setScheme("' . $fullScheme .'")' : '->setScheme(NULL)';
		}

		if ( $data instanceof \IPS\File )
		{
			return "(string) " . $data . "->url" . $schemeString;
		}
		
		if ( mb_substr( $extension, 0, 1 ) === '$' )
		{
			return "\\IPS\\File::get( {$extension}, " . $data . " )->url" . $schemeString;
		}
		else
		{
			return "\\IPS\\File::get( \"{$extension}\", " . $data . " )->url" . $schemeString;
		}
	}
}