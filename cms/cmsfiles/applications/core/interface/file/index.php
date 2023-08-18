<?php
/**
 * @brief		Database File Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		07 May 2013
 */

define('REPORT_EXCEPTIONS', TRUE);
require_once str_replace( 'applications/core/interface/file/index.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';

try
{	
	if ( isset( \IPS\Request::i()->id ) and isset( \IPS\Request::i()->salt ) )
	{
		$where = array( 'id=? AND salt=?', \IPS\Request::i()->id, \IPS\Request::i()->salt );
	}
	else
	{
		$exploded = explode( '/', trim( urldecode( \IPS\Request::i()->file ), '/' ) );
		$filename = array_pop( $exploded );
		$container = implode( '/', $exploded );
		
		$where = array( 'container=? AND filename=?', $container, $filename );
	}
	
	$file = \IPS\Db::i()->select( '*', 'core_files', $where )->first();
		
	if ( $file['id'] )
	{
		$headers	= array_merge( \IPS\Output::getCacheHeaders( time(), 360 ), array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'inline', $file['filename'] ), "X-Content-Type-Options" => "nosniff" ) );
		\IPS\Output::i()->sendOutput( $file['contents'], 200, \IPS\File::getMimeType( $file['filename'] ), $headers );
	}
}
catch ( \UnderflowException $e )
{
	\IPS\Output::i()->sendOutput( '', 404 );
}