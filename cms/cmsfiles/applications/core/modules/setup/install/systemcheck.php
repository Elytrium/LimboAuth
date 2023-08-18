<?php
/**
 * @brief		Installer: System Check
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Dec 2014
 */
 
namespace IPS\core\modules\setup\install;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Installer: System Check
 */
class _systemcheck extends \IPS\Dispatcher\Controller
{
	/**
	 * Show Form
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Clear previous session data */
		if( !isset( \IPS\Request::i()->sessionCheck ) AND \count( $_SESSION ) )
		{
			foreach( $_SESSION as $k => $v )
			{
				unset( $_SESSION[ $k ] );
			}
		}

		/* Store a session variable and then check it on the next page load to make sure PHP sessions are working */
		if( !isset( \IPS\Request::i()->sessionCheck ) )
		{
			$_SESSION['sessionCheck'] = TRUE;
			\IPS\Output::i()->redirect( \IPS\Request::i()->url()->setQueryString( 'sessionCheck', 1 ) );
		}
		else
		{
			if( !isset( $_SESSION['sessionCheck'] ) OR !$_SESSION['sessionCheck'] )
			{
				\IPS\Output::i()->error( 'session_check_fail', '5C348/1', 500, '' );
			}
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('healthcheck');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global' )->healthcheck( \IPS\core\Setup\Install::systemRequirements() );
	}
}