<?php
/**
 * @brief		Output Class for use when the templating engine isn't available
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Oct 2013
 */

namespace IPS\Output;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Output Class
 */
class _System extends \IPS\Output
{
	/**
	 * Display Error Screen
	 *
	 * @param	string	$message		language key for error message
	 * @param	mixed	$code			Error code
	 * @param	int		$httpStatusCode	HTTP Status Code
	 * @param	string	$adminMessage	language key for error message to show to admins
	 * @param	array	$httpHeaders	Additional HTTP Headers
	 * @param	string	$extra			Additional information (such as API error)
	 * @return	void
	 */
	public function error( $message, $code, $httpStatusCode=500, $adminMessage=NULL, $httpHeaders=array(), $extra=NULL )
	{
		/* Send output */
		$this->sendOutput( \IPS\Theme\System\Theme::i()->getTemplate('global', 'core', 'global')->error( $message ), $httpStatusCode, 'text/html', $httpHeaders );
	}
	
	/**
	 * Send output
	 *
	 * @param	string	$output			Content to output
	 * @param	int		$httpStatusCode	HTTP Status Code
	 * @param	string	$contentType	HTTP Content-type
	 * @param	array	$httpHeaders	Additional HTTP Headers
	 * @param	bool	$cache			Allows the page to be cached - set to FALSE when sending a cached page
	 * @return	void
	 */
	public function sendOutput( $output='', $httpStatusCode=200, $contentType='text/html', $httpHeaders=array(), $cache=TRUE )
	{
		/* Set HTTP status */
		if( isset( $_SERVER['SERVER_PROTOCOL'] ) and \strstr( $_SERVER['SERVER_PROTOCOL'], '/1.0' ) !== false )
		{
			header( "HTTP/1.0 {$httpStatusCode} " . static::$httpStatuses[ $httpStatusCode ] );
		}
		else
		{
			header( "HTTP/1.1 {$httpStatusCode} " . static::$httpStatuses[ $httpStatusCode ] );
		}
				
		/* Buffer output */
		if ( $output )
		{
			if( isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) and \strstr( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) !== false and (bool) ini_get('zlib.output_compression') === false )
			{
				ob_start('ob_gzhandler');
			}
			else
			{
				ob_start();
			}
			
			print \IPS\Theme\System\Theme::i()->getTemplate('global', 'core', 'global')->globalTemplate( $output, static::i()->title, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) );
		}

		/* Send headers */
		$size = ob_get_length();
		header( "Content-type: {$contentType};charset=UTF-8" );
		header( "Content-Length: {$size}" );
		foreach ( $httpHeaders as $header )
		{
			header( $header );
		}
		header( "Connection: close" );
		
		/* Flush and exit */
		ob_end_flush();
		flush();
		exit;
	}
	
	/**
	 * Redirect
	 *
	 * @param	\IPS\Http\Url	$url			URL to redirect to
	 * @param	string			$message		Optional message to display
	 * @param	int				$httpStatusCode	HTTP Status Code
	 * @param	bool			$forceScreen	If TRUE, an intermeditate screen will be shown
	 * @return	void
	 */
	public function redirect( $url, $message='', $httpStatusCode=303, $forceScreen=FALSE )
	{
		if ( $forceScreen === TRUE )
		{
			$this->sendOutput( \IPS\Theme\System\Theme::i()->getTemplate( 'global', 'core', 'global' )->redirect( $url, $message ), $httpStatusCode );
		}
		
		$this->sendOutput( '', $httpStatusCode, '', array( "Location: {$url}" ) );
	}
}