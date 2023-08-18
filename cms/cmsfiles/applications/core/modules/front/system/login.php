<?php
/**
 * @brief		Login
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		7 Jun 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Login
 */
class _login extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Is this for displaying "content"? Affects if advertisements may be shown
	 */
	public $isContentPage = FALSE;

	/**
	 * Log In
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->pageCaching = FALSE;

		/* Init login class */
		$login = new \IPS\Login( \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'front', 'login' ) );
		
		/* What's our referrer? */
		$postBeforeRegister = NULL;
		$ref = \IPS\Request::i()->referrer( FALSE, FALSE );

		if ( !$ref and isset( \IPS\Request::i()->cookie['post_before_register'] ) )
		{
			try
			{
				$postBeforeRegister = \IPS\Db::i()->select( '*', 'core_post_before_registering', array( 'secret=?', \IPS\Request::i()->cookie['post_before_register'] ) )->first();
			}
			catch( \UnderflowException $e ){}
		}
		
		/* Process */
		$error = NULL;
		try
		{
			if ( $success = $login->authenticate() )
			{
				if ( \IPS\Request::i()->referrer( FALSE, TRUE ) )
				{
					$ref = \IPS\Request::i()->referrer( FALSE, TRUE );
				}
				elseif ( $postBeforeRegister )
				{
					try
					{
						$class = $postBeforeRegister['class'];
						$ref = $class::load( $postBeforeRegister['id'] )->url();
					}
					catch ( \OutOfRangeException $e )
					{
						$ref = \IPS\Http\Url::internal('');
					}
				}
				elseif( !empty( $_SERVER['HTTP_REFERER'] ) )
				{
					$_ref = \IPS\Http\Url::createFromString( $_SERVER['HTTP_REFERER'] );
					$ref = ( $_ref instanceof \IPS\Http\Url\Internal and ( !isset( $_ref->queryString['do'] ) or $_ref->queryString['do'] != 'validating' ) ) ? $_ref : \IPS\Http\Url::internal('');
				}
				else
				{
					$ref = \IPS\Http\Url::internal( '' );
				}
				
				if ( $success->mfa() )
				{
					$_SESSION['processing2FA'] = array( 'memberId' => $success->member->member_id, 'anonymous' => $success->anonymous, 'remember' => $success->rememberMe, 'destination' => (string) $ref, 'handler' => $success->handler->id );
					\IPS\Output::i()->redirect( $ref->setQueryString( '_mfaLogin', 1 ) );
				}
				$success->process();
								
				\IPS\Output::i()->redirect( $ref->setQueryString( '_fromLogin', 1 ) );
			}
		}
		catch ( \IPS\Login\Exception $e )
		{
			if ( $e->getCode() === \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT )
			{
				$e->member = $e->member->member_id;
				$e->handler = $e->handler->id;
				$_SESSION['linkAccounts'] = json_encode( $e );
								
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=login&do=link', 'front', 'login' )->setQueryString( 'ref', $ref ), '', 303 );
			}
			
			$error = $e->getMessage();
		}

		/* Are we already logged in? */
		if ( \IPS\Member::loggedIn()->member_id AND ( !\IPS\Request::i()->_err OR \IPS\Request::i()->_err != 'login_as_user_login' ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('') );
		}

		/* If there is only one button handler, redirect */
        if ( !isset( \IPS\Request::i()->_processLogin ) and !$login->usernamePasswordMethods() and \count( $login->buttonMethods() ) == 1 )
		{
			$buttonMethod = $login->buttonMethods()[ array_key_first( $login->buttonMethods() ) ];
			if( method_exists( $buttonMethod, 'authenticateButton' ) )
			{
				$buttonMethod->authenticateButton( $login );
			}
		}

		/* Display Login Form */
		\IPS\Output::i()->allowDefaultWidgets = FALSE;
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('login');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->login( $login, base64_encode( $ref ), $error );
		
		/* Don't cache for a short while to ensure sessions work */
		\IPS\Request::i()->setCookie( 'noCache', 1 );
		
		/* Set Session Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', NULL, 'login' ), array(), 'loc_logging_in' );
	}
		
	/**
	 * MFA
	 *
	 * @return	void
	 */
	protected function mfa()
	{		
		/* Have we logged in? */
		$member = NULL;
		if ( isset( $_SESSION['processing2FA']  ) )
		{
			$member = \IPS\Member::load( $_SESSION['processing2FA']['memberId'] );
		}
		if ( !$member->member_id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login' ) );
		}
		
		/* Where do we want to go? */
		$destination = \IPS\Http\Url::internal( '' );
		try
		{
			$destination = \IPS\Http\Url::createFromString( $_SESSION['processing2FA']['destination'] );
		}
		catch ( \Exception $e ) { }	
		
		/* Have we already done 2FA? */
		$device = \IPS\Member\Device::loadOrCreate( $member, FALSE );
		$output = \IPS\MFA\MFAHandler::accessToArea( 'core', $device->known ? 'AuthenticateFrontKnown' : 'AuthenticateFront', \IPS\Http\Url::internal( 'app=core&module=system&controller=login&do=mfa', 'front', 'login' ), $member );		
		if ( !$output )
		{
			( new \IPS\Login\Success( $member, \IPS\Login\Handler::load( $_SESSION['processing2FA']['handler'] ), $_SESSION['processing2FA']['remember'], $_SESSION['processing2FA']['anonymous'], FALSE ) )->process();
			\IPS\Output::i()->redirect( $destination->setQueryString( '_fromLogin', 1 ), '', 303 );
		}
		
		/* Nope, just send us where we want to go not logged in */
		$qs = array( '_mfaLogin' => 1 );
		if ( isset( \IPS\Request::i()->_mfa ) )
		{
			$qs['_mfa'] = \IPS\Request::i()->_mfa;
			if ( isset( \IPS\Request::i()->_mfaMethod ) )
			{
				$qs['_mfaMethod'] = \IPS\Request::i()->_mfaMethod;
			}
		}
		elseif ( isset( \IPS\Request::i()->mfa_auth ) )
		{
			$qs['mfa_auth'] = \IPS\Request::i()->mfa_auth;
		}
		elseif ( isset( \IPS\Request::i()->mfa_setup ) )
		{
			$qs['mfa_setup'] = \IPS\Request::i()->mfa_setup;
		}
		\IPS\Output::i()->redirect( $destination->setQueryString( $qs ) );
	}
	
	/**
	 * Link Accounts
	 *
	 * @return	void
	 */
	protected function link()
	{
		/* Get the member we're linking with */
		if ( !isset( $_SESSION['linkAccounts'] ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login' ) );
		}
		$details = json_decode( $_SESSION['linkAccounts'], TRUE );
		$member = \IPS\Member::load( $details['member'] );
		if ( !$member->member_id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login' ) );
		}
		
		/* And then handler to link with */
		$handler = \IPS\Login\Handler::load( $details['handler'] );
		
		/* Init reauthentication */
		$login = new \IPS\Login( \IPS\Http\Url::internal( 'app=core&module=system&controller=login&do=link', 'front', 'login' )->setQueryString( 'ref', isset( \IPS\Request::i()->ref ) ? \IPS\Request::i()->ref : NULL ), \IPS\Login::LOGIN_REAUTHENTICATE );
		$login->reauthenticateAs = $member;
		$error = NULL;
		
		/* If successful (or if there's no way to reauthenticate which would only happen if a login handler has been deleted)) complete the link... */
		try
		{
			if ( $success = $login->authenticate() or ( !\count( $login->usernamePasswordMethods() ) and !\count( $login->buttonMethods() ) ) )
			{	
				$handler->completeLink( $member, $details['details'] );
				
				unset( $_SESSION['linkAccounts'] );			
				
				$destination = \IPS\Request::i()->referrer( FALSE, TRUE ) ?: \IPS\Http\Url::internal( '' );
				
				$success = new \IPS\Login\Success( $member, $handler );
				if ( $success->mfa() )
				{
					$_SESSION['processing2FA'] = array( 'memberId' => $success->member->member_id, 'anonymous' => $success->anonymous, 'remember' => $success->rememberMe, 'destination' => (string) $destination, 'handler' => $success->handler->id );
					\IPS\Output::i()->redirect( $destination->setQueryString( '_mfaLogin', 1 ) );
				}
				$success->process();
				\IPS\Output::i()->redirect( $destination->setQueryString( '_fromLogin', 1 ) );
			}
		}
		catch ( \IPS\Login\Exception $e )
		{
			$error = $e->getMessage();
		}
		
		/* Otherwise show the reauthenticate form */
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->pageCaching = FALSE;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('login');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->mergeSocialAccount( $handler, $member, $login, $error );
	}
	
	/**
	 * Log Out
	 *
	 * @return void
	 */
	protected function logout()
	{
		$member = \IPS\Member::loggedIn();
		
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Work out where we will be going after log out */
		if( !empty( $_SERVER['HTTP_REFERER'] ) )
		{
			$referrer = \IPS\Http\Url::createFromString( $_SERVER['HTTP_REFERER'] );
			$redirectUrl = ( $referrer instanceof \IPS\Http\Url\Internal and ( !isset( $referrer->queryString['do'] ) or $referrer->queryString['do'] != 'validating' ) ) ? $referrer : \IPS\Http\Url::internal('');
		}
		else
		{
			$redirectUrl = \IPS\Http\Url::internal( '' );
		}
		
		/* Are we logging out back to an admin user? */
		if( isset( $_SESSION['logged_in_as_key'] ) )
		{
			$key = $_SESSION['logged_in_as_key'];
			unset( \IPS\Data\Store::i()->$key );
			unset( $_SESSION['logged_in_as_key'] );
			unset( $_SESSION['logged_in_from'] );
			
			\IPS\Output::i()->redirect( $redirectUrl );
		}
		
		/* Do it */
		\IPS\Login::logout( $redirectUrl );
		
		/* Redirect */
		\IPS\Output::i()->redirect( $redirectUrl->setQueryString( '_fromLogout', 1 ) );
	}
	
	/**
	 * Log in as user
	 *
	 * @return void
	 */
	protected function loginas()
	{
		if ( !\IPS\Request::i()->key or !\IPS\Login::compareHashes( (string) \IPS\Data\Store::i()->admin_login_as_user, (string) \IPS\Request::i()->key ) )
		{
			\IPS\Output::i()->error( 'invalid_login_as_user_key', '3S167/1', 403, '' );
		}
	
		/* Load member and admin user */
		$member = \IPS\Member::load( \IPS\Request::i()->id );
		$admin 	= \IPS\Member::load( \IPS\Request::i()->admin );
		
		/* Not logged in as admin? */
		if ( $admin->member_id != \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'front', 'login' )->addRef( (string) \IPS\Request::i()->url() )->setQueryString( '_err', 'login_as_user_login' ) );
		}
		
		/* Do it */
		$_SESSION['logged_in_from']			= array( 'id' => $admin->member_id, 'name' => $admin->name );
		$unique_id							= \IPS\Login::generateRandomString();
		$_SESSION['logged_in_as_key']		= $unique_id;
		\IPS\Data\Store::i()->$unique_id	= $member->member_id;
		
		/* Ditch the key */
		unset( \IPS\Data\Store::i()->admin_login_as_user );
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ) );
	}
}