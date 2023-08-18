<?php
/**
 * @brief		Twitter Authentication
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 February 2017
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Twitter auth for ACP
 */
class _twitter extends \IPS\Dispatcher\Controller
{	
	/**
	 * View Announcement
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( ! \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'front', 'login' )->addRef( \IPS\Http\Url::internal( 'app=core&module=system&controller=twitter' ) ) );
		}

		/* Check the member has permission to manage promote settings */
		if ( ! \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'promote_manage' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '3C375/2', 403, '' );
		}

		$twitter = \IPS\Login\Handler::findMethod('IPS\Login\Handler\Oauth1\Twitter');

		/* Required handler present and enabled? */
		if( $twitter === NULL )
		{
			\IPS\Output::i()->error( 'promote_twitter_setup_app', '3C375/3', 403, '' );
		}

		$promote = \IPS\core\Promote::getPromoter( 'Twitter' );
		$url = \IPS\Http\Url::internal( 'app=core&module=system&controller=twitter' );
		$callback = $twitter->redirectionEndpoint()->setQueryString( 'state', $twitter->id . '-' . base64_encode( $url ) . '-' . \IPS\Session::i()->csrfKey . '-'  );

		try
		{
			/* Get a request token */
			if ( !isset( \IPS\Request::i()->oauth_token ) )
			{
				$response = $twitter->requestToken( (string) $callback );
				\IPS\Output::i()->redirect( "https://api.twitter.com/oauth/authorize?force_login=1&oauth_token={$response['oauth_token']}" );
			}
			
			/* CSRF Check */
			if ( \IPS\Request::i()->csrfKey !== \IPS\Session::i()->csrfKey )
			{
				throw new \IPS\Login\Exception( 'CSRF_FAIL', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Authenticate */
			$response = $twitter->exchangeToken( \IPS\Request::i()->oauth_verifier, \IPS\Request::i()->oauth_token );
						
			/* Store the settings */
			$promote->saveSettings( array(
				'id' => $response['user_id'],
				'owner' => \IPS\Member::loggedIn()->member_id,
				'secret' => $response['oauth_token_secret'],
				'token' => $response['oauth_token'],
				'name' => $response['screen_name']
			) );
			
			/* Show a done page */
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('promote_twitter_sorted');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'promote' )->promoteTwitterComplete( $response );
				
		}
		catch ( \Exception $e )
   		{
   			\IPS\Log::log( $e, 'twitter_promote' );

   			\IPS\Output::i()->error( 'generic_error', '4C375/1', 403, $e->getMessage() );
   		}
	}
}