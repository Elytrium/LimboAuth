<?php
/**
 * @brief		warnings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		20 Sep 2021
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * warnings
 */
class _warnings extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'warnings_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$tabs		= array(
			'reason'			=> 'stats_warnings_reason',
			'suspended'		=> 'stats_warnings_suspended',
		);
		
		/* Make sure tab is set, otherwise saved charts may not show up when loading the page. */
		\IPS\Request::i()->tab ??= 'reason';
		$activeTab	= ( array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'reason';

		if ( $activeTab === 'reason' )
		{
			$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'WarningReasons' )->getChart( \IPS\Http\Url::internal( 'app=core&module=stats&controller=warnings&tab=reason' ) );
		}
		else if ( $activeTab === 'suspended' )
		{
			$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'WarningSuspended' )->getChart( \IPS\Http\Url::internal( 'app=core&module=stats&controller=warnings&tab=suspended' ) );
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) $chart;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_warnings');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string) $chart, \IPS\Http\Url::internal( "app=core&module=stats&controller=warnings" ), 'tab', '', 'ipsPad' );
		}
	}
	
}