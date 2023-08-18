<?php
/**
 * @brief		Guidelines
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		02 Sept 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Guidelines
 */
class _guidelines extends \IPS\Dispatcher\Controller
{
	/**
	 * Guidelines
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( \IPS\Settings::i()->gl_type == "none" )
		{
			\IPS\Output::i()->error( 'node_error', '2C380/1', 404, 'guidelines_set_to_none_admin' );
		}

		/* Set Session Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=system&controller=guidelines', NULL, 'guidelines' ), array(), 'loc_viewing_guidelines' );

		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('guidelines') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('guidelines');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->guidelines( \IPS\Settings::i()->gl_guidelines );
	}
}