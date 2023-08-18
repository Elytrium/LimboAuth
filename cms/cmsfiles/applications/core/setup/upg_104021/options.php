<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Apr 2019
 */

$options = array();

/* If the table doesn't exist yet, just return now */
if( !\IPS\Db::i()->checkForTable( 'core_login_methods' ) )
{
	return $options;
}

/* If we have a LinkedIn login handler, check to see if it needs to be upgraded. */
try
{
	$loginHandler	= \IPS\Db::i()->select( '*', 'core_login_methods', array( 'login_classname=?', 'IPS\Login\Handler\OAuth2\LinkedIn' ) )->first();
	$settings		= json_decode( $loginHandler['login_settings'], TRUE );

	try
	{
		$response = \IPS\Http\Url::external( "https://www.linkedin.com/oauth/v2/authorization/" )->setQueryString( array(
			'client_id'		=> $settings['client_id'],
			'response_type'	=> 'code',
			'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'oauth/callback/', 'none' ),
			'scope'			=> 'r_liteprofile'
		) )->request( NULL, NULL, FALSE )->get();

		/* A valid response should be a redirect without an 'error' parameter */
		if ( $response->httpResponseCode != 303 OR !isset( $response->httpHeaders['Location'] ) )
		{
			throw new \IPS\Http\Request\Exception;
		}

		$redirectUrl = new \IPS\Http\Url( $response->httpHeaders['Location'] );

		if ( isset( $redirectUrl->queryString['error'] ) )
		{
			throw new \IPS\Http\Request\Exception;
		}
	}
	catch( \IPS\Http\Request\Exception $e )
	{
		/* Disable the login handler */
		\IPS\Db::i()->update( 'core_login_methods', array( 'login_enabled' => 0 ), array( 'login_id=?', $loginHandler['login_id'] ) );
		unset( \IPS\Data\Store::i()->loginMethods );

		$url = \IPS\Http\Url::ips( 'docs/login_linkedin' );
		$options[] = new \IPS\Helpers\Form\Custom( '104020_linkedin', NULL, FALSE, array( 'getHtml' => function( $element ) use ( $url ) {
			return "LinkedIn have recently made some changes to their API. If you would like to keep using the LinkedIn login method, follow <a href='{$url}' target='_blank' rel='noopener'>these instructions</a> after the upgrade is finished to create a new LinkedIn application.";
		} ), function( $val ) {}, NULL, NULL, '104020_linkedin' );
	}
}
catch( \UnderflowException $e ){}
