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
require_once str_replace( 'applications/cms/interface/external/external.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
\IPS\Dispatcher\External::i();

$id = \IPS\Request::i()->blockid;
$k = \IPS\Request::i()->widgetid;
$blockHtml = \IPS\cms\Blocks\Block::display( $id );

\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_external.js', 'cms', 'front' ) );
\IPS\Output::i()->globalControllers[] = 'cms.front.external.communication';

$cache = FALSE;

try
{
	if ( \is_numeric( $id ) )
	{
		$block = \IPS\cms\Blocks\Block::load( $id );
	}
	else if ( \is_string( $id ) )
	{
		$block = \IPS\cms\Blocks\Block::load( $id, 'block_key' );
	}

	if ( $block->active )
	{
		$cache = ( $block->type == 'custom' ) ? $block->cache : TRUE;
	}
}
catch( \OutOfRangeException $ex ){}

if( !$cache OR !\IPS\Settings::i()->widget_cache_ttl )
{
	\IPS\Output::i()->pageCaching = FALSE;
	$headers = array();
}
else
{
	$headers = \IPS\Output::getCacheHeaders( time(), \IPS\Settings::i()->widget_cache_ttl );
}

/* Remove protection headers. This is fine in this case because we only output */
if( isset( \IPS\Output::i()->httpHeaders['X-Frame-Options'] ) )
{
	foreach( [ 'Content-Security-Policy','X-Content-Security-Policy', 'X-Frame-Options' ]  as $toRemove )
	{
		unset( \IPS\Output::i()->httpHeaders[$toRemove] );
	}
}

\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( $blockHtml ), 200, 'text/html', $headers );