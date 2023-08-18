<?php
/**
 * @brief		Cookie Policy
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Dec 2017
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */

use IPS\Helpers\Form\YesNo;
use IPS\Output;

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Cookie Information Page
 */
class _cookies extends \IPS\Dispatcher\Controller
{
	public function execute()
	{
		parent::execute();

		\IPS\Output::i()->pageCaching = FALSE;
	}

	/**
	 * Cookie Information Page
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Set Session Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=system&controller=cookies', NULL, 'cookies' ), array(), 'loc_viewing_cookie_policy' );
		
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('cookies_about') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('cookies_about');
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->metaTags['robots'] = 'noindex';
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->cookies();
	}

	/**
	 * Opt out of optional cookies
	 *
	 * @return void
	 */
	protected function cookieConsentToggle()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Member::loggedIn()->setAllowOptionalCookies( (bool) \IPS\Request::i()->status );
		if ( isset( \IPS\Request::i()->ref ) )
		{
			try
			{
				$url = \IPS\Http\Url::createFromString( base64_decode( \IPS\Request::i()->ref ) );
			}
			catch( \IPS\Http\Url\Exception $e )
			{
				$url = NULL;
			}

			if ( $url instanceof \IPS\Http\Url\Internal and !$url->openRedirect() )
			{
				\IPS\Output::i()->redirect( $url );
			}
		}
	}
}