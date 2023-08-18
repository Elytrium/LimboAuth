<?php
/**
 * @brief		moderators
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		22 Sep 2021
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * moderators
 */
class _moderators extends \IPS\Dispatcher\Controller
{	
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'moderatorstats_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Moderators' )->getChart( \IPS\Http\Url::internal( 'app=core&module=stats&controller=moderators' ) );
		}
		else
		{	
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_moderators');
			\IPS\Output::i()->output = (string) \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Moderators' )->getChart( \IPS\Http\Url::internal( 'app=core&module=stats&controller=moderators' ) );
		}
	}
}