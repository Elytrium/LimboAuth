<?php
/**
 * @brief		cloud
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		07 Oct 2022
 */

namespace IPS\core\modules\admin\smartcommunity;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * cloud
 */
class _cloud extends \IPS\Dispatcher\Controller
{
	/**
	 * Cloud
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/enhancements.css', 'core', 'admin' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__core_smartcommunity' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'smartcommunity' )->cloud();
	}
}