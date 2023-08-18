<?php

define('REPORT_EXCEPTIONS', TRUE);
require_once '../../../../init.php';

\IPS\Output::i()->pageCaching = FALSE;

if ( \IPS\IN_DEV !== true AND ! \IPS\Theme::designersModeEnabled() )
{
	exit();
}

/* The CSS is parsed by the theme engine, and the theme engine has plugins, and those plugins need to now which theme ID we're using */
if ( \IPS\Theme::designersModeEnabled() )
{
	\IPS\Session\Front::i();
}

$needsParsing = FALSE;

if( strstr( \IPS\Request::i()->css, ',' ) )
{
	$contents = '';
	foreach( explode( ',', \IPS\Request::i()->css ) as $css )
	{
		if ( mb_substr( $css, -4 ) !== '.css' )
		{
			continue;
		}
		
		$css	= str_replace( array( '../', '..\\' ), array( '&#46;&#46;/', '&#46;&#46;\\' ), $css );
		$file	= file_get_contents( \IPS\ROOT_PATH . '/' . $css );
		$params	= processFile( $file );
		
		if ( $params['hidden'] === 1 )
		{
			continue;
		}
		
		$contents .= "\n" . $file;
		
		if ( needsParsing( $css ) )
		{
			$needsParsing = TRUE;
		}
	}
}
else
{
	if ( mb_substr( \IPS\Request::i()->css, -4 ) !== '.css' )
	{
		exit();
	}

	$contents  = file_get_contents( \IPS\ROOT_PATH . '/' . str_replace( array( '../', '..\\' ), array( '&#46;&#46;/', '&#46;&#46;\\' ), \IPS\Request::i()->css ) );
	
	$params = processFile( $contents );
		
	if ( $params['hidden']  === 1 )
	{
		exit;
	}
	
	if ( needsParsing( \IPS\Request::i()->css ) )
	{
		$needsParsing = TRUE;
	}
}

if ( $needsParsing )
{
	if ( \IPS\Theme::designersModeEnabled() )
	{
		/* If we're in designer's mode, we need to reset the theme ID based on the CSS path as we could be in the ACP which may have a different theme ID set */
		preg_match( '#themes/(\d+)/css/(.+?)/(.+?)/(.*)\.css#', \IPS\Request::i()->css, $matches );
	
		if ( $matches[1] and $matches[1] !== \IPS\Theme::$memberTheme->id )
		{
			try
			{
				\IPS\Theme::$memberTheme = \IPS\Theme\Advanced\Theme::load( $matches[1] );
			}
			catch( \OutOfRangeException $ex ) { }
		}
	}
	
	$functionName = 'css_' . mt_rand();
	$contents = str_replace( '\\', '\\\\', $contents );
	/* If we have something like `{expression="\IPS\SOME_CONSTANT"}` we cannot double escape it, however we do need to escape font icons and similar. */
	$contents = preg_replace_callback( "/{expression=\"(.+?)\"}/ms", function( $matches ) {
		return '{expression="' . str_replace( '\\\\', '\\', $matches[1] ) . '"}';
	}, $contents );
	\IPS\Theme::makeProcessFunction( $contents, $functionName );
	$functionName = "IPS\Theme\\{$functionName}";
	
	\IPS\Output::i()->sendOutput( $functionName(), 200, 'text/css' );
}
else
{ 
	\IPS\Output::i()->sendOutput( $contents, 200, 'text/css' );
}

/**
 * Determine whether this file needs parsing or not
 *
 * @return boolean
 */
function needsParsing( $fileName )
{
	if( \IPS\IN_DEV === TRUE AND ! \IPS\Theme::designersModeEnabled() )
	{
		preg_match( '#applications/(.+?)/dev/css/(.+?)/(.*)\.css#', $fileName, $appMatches );
		preg_match( '#plugins/(.+?)/dev/css/(.*)\.css#', $fileName, $pluginMatches );
		return (bool) ( \count( $appMatches ) or \count( $pluginMatches ) );
	}
	else
	{
		preg_match( '#themes/(?:\d+)/css/(.+?)/(.+?)/(.*)\.css#', $fileName, $themeMatches );
		return \count( $themeMatches );
	}

	return FALSE;
}

/**
 * Process the file to extract the header tag params
 *
 * @return array
 */
function processFile( $contents )
{
	$return = array( 'module' => '', 'app' => '', 'pos' => '', 'hidden' => 0 );
	
	/* Parse the header tag */
	preg_match_all( '#^/\*<ips:css([^>]+?)>\*/\n#', $contents, $params, PREG_SET_ORDER );
	foreach( $params as $id => $param )
	{
		preg_match_all( '#([\d\w]+?)=\"([^"]+?)"#i', $param[1], $items, PREG_SET_ORDER );
			
		foreach( $items as $id => $attr )
		{
			switch( trim( $attr[1] ) )
			{
				case 'module':
					$return['module'] = trim( $attr[2] );
					break;
				case 'app':
					$return['app'] = trim( $attr[2] );
					break;
				case 'position':
					$return['pos'] = \intval( $attr[2] );
					break;
				case 'hidden':
					$return['hidden'] = \intval( $attr[2] );
					break;
			}
		}
	}
	
	return $return;
}