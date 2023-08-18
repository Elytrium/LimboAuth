<?php
/**
 * @brief		Pages External Block Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		30 Jun 2015
 *
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once str_replace( 'applications/cms/interface/developer/developer.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
\IPS\Dispatcher\External::i();

if ( \IPS\IN_DEV !== true AND ! \IPS\Theme::designersModeEnabled() )
{
	exit();
}

/* The CSS is parsed by the theme engine, and the theme engine has plugins, and those plugins need to now which theme ID we're using */
if ( \IPS\Theme::designersModeEnabled() )
{
	\IPS\Session\Front::i();
}

if ( isset( \IPS\Request::i()->file ) )
{
	$realPath = realpath( \IPS\ROOT_PATH . '/themes/' . \IPS\Request::i()->file );
	$pathContainer = realpath(\IPS\ROOT_PATH . '/themes/' );

	if( $realPath === FALSE OR mb_substr( $realPath, 0, mb_strlen( $pathContainer ) ) !== $pathContainer )
	{
		\IPS\Output::i()->error( 'node_error', '3C171/8', 403, '' );
		exit;
	}

	$file = file_get_contents( \IPS\ROOT_PATH . '/themes/' . \IPS\Request::i()->file );
	
	\IPS\Output::i()->pageCaching = FALSE;
	\IPS\Output::i()->sendOutput( preg_replace( '#<ips:template.+?\n#', '', $file ), 200, ( mb_substr( \IPS\Request::i()->file, -4 ) === '.css' ) ? 'text/css' : 'text/javascript' );
}