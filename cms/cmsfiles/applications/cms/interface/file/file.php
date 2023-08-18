<?php
/**
 * @brief		Pages Download Handler for custom record upload fields
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		27 May 2015
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once str_replace( 'applications/cms/interface/file/file.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
\IPS\Dispatcher\External::i();

try
{
	/* Load member */
	$member = \IPS\Member::loggedIn();
	
	/* Set up autoloader for CMS */

	/* Init */
	$databaseId  = \intval( \IPS\Request::i()->database );
	$database    = \IPS\cms\Databases::load( $databaseId );
	$recordId    = \intval( \IPS\Request::i()->record );
	$fileName    = urldecode( \IPS\Request::i()->file );
	$recordClass = '\IPS\cms\Records' . $databaseId;
	$realFileName = NULL;

	try
	{
		$record = $recordClass::load( $recordId );
	}
	catch( \OutOfRangeException $ex )
	{
		\IPS\Output::i()->error( 'no_module_permission', '2T279/1', 403, '' );
	}
	
	if ( ! $record->canView() )
	{
		\IPS\Output::i()->error( 'no_module_permission', '2T279/2', 403, '' );
	}

	$realFileName = \IPS\Text\Encrypt::fromTag( \IPS\Request::i()->fileKey )->decrypt();

	if ( ! $realFileName )
	{
		\IPS\Output::i()->error( 'no_module_permission', '2T279/4', 403, '' );
	}

	/* Get file and data */
	try
	{
		$file = \IPS\File::get( 'cms_Records', $realFileName );
	}
	catch( \Exception $ex )
	{
		\IPS\Output::i()->error( 'no_module_permission', '2T279/3', 404, '' ); 
	}
		
	$headers = array_merge( \IPS\Output::getCacheHeaders( time(), 360 ), array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'attachment', \IPS\Request::i()->file ), "X-Content-Type-Options" => "nosniff" ) );
	
	/* Send headers and print file */
	\IPS\Output::i()->sendStatusCodeHeader( 200 );
	\IPS\Output::i()->sendHeader( "Content-type: " . \IPS\File::getMimeType( \IPS\Request::i()->file ) . ";charset=UTF-8" );

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
	\IPS\Dispatcher\Front::i();
	\IPS\Output::i()->sendOutput( '', 404 );
}