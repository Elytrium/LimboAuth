<?php
/**
 * @brief		Cloud Sync Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		07 May 2013
 */

@header( "Cache-Control: no-cache, no-store, must-revalidate, max-age=0, s-maxage=0" );
@header( "Expires: 0" );

define('REPORT_EXCEPTIONS', TRUE);
require_once str_replace( 'applications/core/interface/cloud/sync.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';

if ( \IPS\Login::compareHashes( md5( \IPS\Settings::i()->sql_user . \IPS\Settings::i()->sql_pass ), \IPS\Request::i()->key ) === FALSE )
{
	echo "fail\n";
	exit;
}

\IPS\Data\Store::i()->syncCompleted = 1;
echo "success\n";