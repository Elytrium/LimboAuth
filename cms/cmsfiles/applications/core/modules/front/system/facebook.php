<?php
/**
 * @brief		Facebook Authentication
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		09 August 2018
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Facebook auth for ACP
 */
class _facebook extends \IPS\Dispatcher\Controller
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
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'front', 'login' )->addRef( \IPS\Http\Url::internal( 'app=core&module=system&controller=facebook' ) ) );
		}

		/* Check the member has permission to manage promote settings */
		if ( ! \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'promote_manage' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '3C396/1', 403, '' );
		}

		$promote = \IPS\core\Promote::getPromoter( 'Facebook' );
		$facebook = $promote::getLoginHandler();

		/* Required handler present and enabled? */
		if( $facebook === NULL )
		{
			\IPS\Output::i()->error( 'promote_facebook_setup_app', '3C396/2', 403, '' );
		}

		$url = \IPS\Http\Url::internal( 'app=core&module=system&controller=facebook' );

		try
		{
			/* Get a request token */
			if ( !isset( \IPS\Request::i()->code ) )
			{
				$target = \IPS\Http\Url::external('https://www.facebook.com/dialog/oauth')->setQueryString( array(
					'client_id'		=> $facebook->settings['client_id'],
					'response_type'	=> 'code',
					'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'oauth/callback/', 'none' ),
					'state'			=> $facebook->id . '-' . base64_encode( $url ) . '-' . \IPS\Session::i()->csrfKey . '-x',
					'scope'			=> 'manage_pages publish_pages'
				) );
			
				\IPS\Output::i()->redirect( $target );
			}
			
			/* CSRF Check */
			if ( \IPS\Request::i()->csrfKey !== \IPS\Session::i()->csrfKey )
			{
				throw new \IPS\Login\Exception( 'CSRF_FAIL', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Did we get an error? */
			if ( isset( \IPS\Request::i()->error ) )
			{
				if ( \IPS\Request::i()->error === 'access_denied' )
				{
					return NULL;
				}
				else
				{
					\IPS\Log::log( print_r( $_GET, TRUE ), 'oauth' );
					throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
				}
			}
		
			/* If we have a code, swap it for an access token, otherwise, decode what we have */
			if ( isset( \IPS\Request::i()->code ) )
			{
				$accessToken = $facebook->exchangeMemberToken( \IPS\Request::i()->code );
			}
						
			/* Store the settings */
			$promote->saveSettings( array(
				'member_token' => $accessToken
			) );
			
			/* Show a done page */
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('promote_facebook_sorted');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'promote' )->promoteFacebookComplete();
				
		}
		catch ( \Exception $e )
   		{
   			\IPS\Log::log( $e, 'facebook_promote' );

   			\IPS\Output::i()->error( 'generic_error', '4C375/1', 403, $e->getMessage() );
   		}
	}
}
