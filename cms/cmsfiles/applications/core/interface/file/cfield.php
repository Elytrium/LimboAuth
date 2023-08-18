<?php
/**
 * @brief		Upload Custom Field Download Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Feb 2016
 */

define('REPORT_EXCEPTIONS', TRUE);
require_once str_replace( 'applications/core/interface/file/cfield.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';

try
{
	/* Get the extension */
	list( $app, $extension ) = explode( '_', \IPS\Request::i()->storage );
	$classname = 'IPS\\' . $app . '\extensions\core\FileStorage\\' . $extension;	
	if ( !class_exists( $classname ) )
	{
		throw new \RuntimeException;
	}
	$extension = new $classname;

	if ( ! isset( \IPS\Request::i()->fileKey ) )
	{
		throw new \RuntimeException;
	}

	/* Get the actual filename from the extension */
	$realFileName = \IPS\Text\Encrypt::fromTag( \IPS\Request::i()->fileKey )->decrypt();

	/* Check the file is valid */
	$file = \IPS\File::get( \IPS\Request::i()->storage, $realFileName );
	if ( !$extension->isValidFile( $realFileName ) )
	{
		throw new \RuntimeException;
	}
	
	/* Send headers and print file */
	\IPS\Output::i()->sendStatusCodeHeader( 200 );
	\IPS\Output::i()->sendHeader( "Content-type: " . \IPS\File::getMimeType( \IPS\Request::i()->path ) . ";charset=UTF-8" );
	foreach( array_merge( \IPS\Output::getCacheHeaders( time(), 360 ), array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'attachment', \IPS\Request::i()->path ), "X-Content-Type-Options" => "nosniff" ) ) as $key => $header )
	{
		\IPS\Output::i()->sendHeader( $key . ': ' . $header );
	}
	\IPS\Output::i()->sendHeader( "Content-Length: " . $file->filesize() );
	\IPS\Output::i()->sendHeader( "Content-Security-Policy: default-src 'none'; sandbox" );
	\IPS\Output::i()->sendHeader( "X-Content-Security-Policy:  default-src 'none'; sandbox" );
	$file->printFile();
	exit;
}
catch ( \Exception $e )
{
	\IPS\Dispatcher\Front::i();
	\IPS\Output::i()->sendOutput( '', 404 );
}