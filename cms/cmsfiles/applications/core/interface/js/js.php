<?php
/**
 * @brief		Return JS files
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 Aug 2013
 */

define('REPORT_EXCEPTIONS', TRUE);
require_once '../../../../init.php';

function loadResource ($url)
{
	$response =  \IPS\Http\Url::external( $url )->request()->get();
	if ( strpos( $response->httpHeaders['Content-Type'], 'javascript' ) !== FALSE )
	{
		return $response . "\n\n\n\n";
	}
	else
	{
		return '';
	}
}

if ( \IPS\Request::i()->src )
{
	$output = '';
	
	foreach ( explode( ',', \IPS\Request::i()->src ) as $src )
	{
		if ( mb_substr( $src, -3 ) !== '.js' )
		{
			continue;
		}
		
		$src		= str_replace( array( '../', '..\\' ), array( '&#46;&#46;/', '&#46;&#46;\\' ), $src );
		$exploded	= explode( '/', $src );
		$app		= array_shift( $exploded );
		$location	= array_shift( $exploded );

		/* Interface files are never written to remote locations and subsequently can be loaded directly from disk, which is more efficient */
		if( $location == 'interface' )
		{
			$output .= file_get_contents( \IPS\ROOT_PATH . '/applications/' . $app . '/interface/' . implode( '/', $exploded ) ) . "\n\n\n\n";
			
			/* jquery ui requires a special file for touch compatibility, and it must be included
				after jquery ui itself. Rather than doing it manually then having to change it when jUI gets
				built-in support, we'll append it automatically here */
			if ( $exploded[1] == 'jquery-ui.js' )
			{
				$exploded[1] = 'jquery-touchpunch.js';
				$output .= file_get_contents( \IPS\ROOT_PATH . '/applications/' . $app . '/interface/' . implode( '/', $exploded ) ) . "\n\n\n\n";
			}
		}
		elseif ( \IPS\IN_DEV && $exploded[0] == 'ckeditor' && $exploded[1] == 'ckeditor.js' )
		{
			$output .= file_get_contents( \IPS\ROOT_PATH . '/applications/core/dev/ckeditor/ckeditor.js' ) . "\n\n\n\n";
		}
		else
		{
			foreach ( \IPS\Output::i()->js( implode( '/', $exploded ), $app, $location ) as $url )
			{
				/* If we are in_dev, ckeditor comes from the /dev/ directory instead */
				if ( \IPS\IN_DEV && $exploded[0] == 'ckeditor' && $exploded[1] == 'ckeditor.js' )
				{
					continue;
				}
		
				try
				{
					/* Make sure we have a protocol defined. \IPS\Http\Url::external() does not like protocol relative URL's */
					if ( mb_substr( $url, 0, 2 ) == '//' )
					{
						$url = ( \IPS\Request::i()->isSecure() ) ? 'https:' . $url : 'http:' . $url;
					}
					$output .= loadResource( $url );				
				}
				catch ( \Exception $e )
				{
					\IPS\Output::i()->sendOutput( '', 500, 'text/javascript' );
				}
			}
		}
	}
	
	$cacheHeaders	= ( \IPS\IN_DEV !== true AND \IPS\Theme::designersModeEnabled() !== true ) ? \IPS\Output::getCacheHeaders( time(), 360 ) : array();
	
	\IPS\Output::i()->sendOutput( $output, 200, 'text/javascript', $cacheHeaders );
}