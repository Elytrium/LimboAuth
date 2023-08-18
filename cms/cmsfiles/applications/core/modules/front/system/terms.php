<?php
/**
 * @brief		Terms of Use
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Sept 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Terms of Use
 */
class _terms extends \IPS\Dispatcher\Controller
{
	/**
	 * Terms of Use
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=system&controller=terms', NULL, 'terms' ), array(), 'loc_viewing_reg_terms' );
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('reg_terms');
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->terms();
	}
	
	/**
	 * Dismiss Terms
	 *
	 * @return	void
	 */
	protected function dismiss()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Request::i()->setCookie( 'guestTermsDismissed', 1, NULL, FALSE );

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'message' => \IPS\Member::loggedIn()->language()->addToStack( 'terms_dismissed' ) ) );
		}
		else
		{
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
					\IPS\Output::i()->redirect( $url, 'terms_dismissed' );
				}
			}
			
			/* Still here? Just redirect to the index */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ), 'terms_dismissed' );
		}
	}
}