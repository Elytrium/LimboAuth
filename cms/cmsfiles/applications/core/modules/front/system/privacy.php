<?php
/**
 * @brief		Privacy Policy
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 Jun 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Privacy Policy
 */
class _privacy extends \IPS\Dispatcher\Controller
{
	/**
	 * Privacy Policy
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( \IPS\Settings::i()->privacy_type == "none" )
		{
			\IPS\Output::i()->error( 'node_error', '2C381/1', 404, 'privacy_set_to_none_acp' );
		}
		
		if ( \IPS\Settings::i()->privacy_type == "external" )
		{
			if ( $url = \IPS\Settings::i()->privacy_link )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::external( $url ) );
			} 
			else 
			{
				\IPS\Output::i()->error( 'node_error', '2C381/1', 404, 'privacy_link_not_set_acp' );
			}
		}

		$subprocessors = array();
		/* Work out the main subprocessors that the user has no direct choice over */
		if ( \IPS\Settings::i()->privacy_show_processors )
		{
			foreach( \IPS\Application::enabledApplications() as $app )
			{
				$subprocessors = array_merge( $subprocessors, $app->privacyPolicyThirdParties() );
			}
		}
		
		/* Set Session Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=system&controller=privacy', NULL, 'privacy' ), array(), 'loc_viewing_privacy_policy' );
		
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('privacy') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('privacy');
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->privacy( $subprocessors );
	}
}