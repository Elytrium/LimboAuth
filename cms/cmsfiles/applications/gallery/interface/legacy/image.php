<?php
/**
 * @brief		Gallery image download handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		30 May 2013
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once str_replace( 'applications/gallery/interface/legacy/image.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
\IPS\Dispatcher\External::i();

try
{
	/* Get file and data */
	$file		= \IPS\File::get( 'gallery_Images', \IPS\Request::i()->path );

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