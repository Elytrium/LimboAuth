<?php
/**
 * @brief		Downloads screenshot handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		4 Dec 2014
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once str_replace( 'applications/downloads/interface/legacy/screenshot.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
\IPS\Session\Front::i();

try
{
	/* Get file and data */
	$file		= \IPS\File::get( 'downloads_Screenshots', ltrim( \IPS\Request::i()->path, '/' ) );

	$headers	= array_merge( \IPS\Output::getCacheHeaders( time(), 360 ), array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'inline', $file->originalFilename ), "X-Content-Type-Options" => "nosniff" ) );

	/* Send headers and print file */
	\IPS\Output::i()->sendStatusCodeHeader( 200 );
	\IPS\Output::i()->sendHeader( "Content-type: " . \IPS\File::getMimeType( $file->originalFilename ) . ";charset=UTF-8" );

	foreach( $headers as $key => $header )
	{
		\IPS\Output::i()->sendHeader( $key . ': ' . $header );
	}
	\IPS\Output::i()->sendHeader( "Content-Length: " . $file->filesize() );
	
	\IPS\Output::i()->sendHeader( "Content-Security-Policy: default-src 'none'; sandbox" );
	\IPS\Output::i()->sendHeader( "X-Content-Security-Policy:  default-src 'none'; sandbox" );

	$file->printFile();
	exit;
}
catch ( \UnderflowException $e )
{
	\IPS\Output::i()->sendOutput( '', 404 );
}