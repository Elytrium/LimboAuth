<?php
/**
 * @brief		Microsoft Account Login Handler Redirect URI Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Mar 2013
 */

define('REPORT_EXCEPTIONS', TRUE);
require_once str_replace( 'applications/core/interface/microsoft/auth.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';

$target = \IPS\Http\Url::internal( 'oauth/callback/', 'none' );

foreach ( array( 'code', 'state', 'scope', 'error', 'error_description', 'error_uri' ) as $k )
{
	if ( isset( \IPS\Request::i()->$k ) )
	{
		$target = $target->setQueryString( $k, \IPS\Request::i()->$k );
	}
}

\IPS\Output::i()->redirect( $target );
