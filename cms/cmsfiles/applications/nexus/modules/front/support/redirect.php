<?php
/**
 * @brief		Legacy Redirector
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		07 Sep 2016
 */

namespace IPS\nexus\modules\front\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Legacy Redirector
 */
class _redirect extends \IPS\Dispatcher\Controller
{
	/**
	 * Redirect
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$url = \IPS\Http\Url::internal( "app=core&module=system&controller=redirect" )->setQueryString( array(
			'url'		=> \IPS\Request::i()->url,
			'resource'	=> ( \IPS\Request::i()->resource ) ? 1 : NULL,
			'key'		=> hash_hmac( "sha256", \IPS\Request::i()->url, \IPS\Settings::i()->site_secret_key . 'r' ),
		) );
		
		\IPS\Output::i()->redirect( $url );
	}
}