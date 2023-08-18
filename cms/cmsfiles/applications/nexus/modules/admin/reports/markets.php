<?php
/**
 * @brief		Markets Report
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		14 Aug 2014
 */

namespace IPS\nexus\modules\admin\reports;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Markets Report
 */
class _markets extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * @brief	Allow MySQL RW separation for efficiency
	 */
	public static $allowRWSeparation = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'markets_manage' );
		parent::execute();
	}

	/**
	 * View Chart
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$tabs['count'] = 'nexus_report_count';
		foreach ( \IPS\nexus\Money::currencies() as $currency )
		{
			$tabs[ $currency ] = \IPS\Member::loggedIn()->language()->addToStack( 'nexus_report_income', FALSE, array( 'sprintf' => array( $currency ) ) );
		}
		
		\IPS\Request::i()->tab ??= 'count';
		$activeTab = ( array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'count';
		$extension = \IPS\core\Statistics\Chart::loadFromExtension( 'nexus', 'Market' );
		$chart = $extension->getChart( \IPS\Http\Url::internal( "app=nexus&module=reports&controller=markets&tab={$activeTab}" ) );
		
		if ( $activeTab !== 'count' )
		{
			try
			{
				$extension->setCurrency( $chart, $activeTab );
			}
			catch( \InvalidArgumentException ) {}
		}
		
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) $chart;
		}
		else
		{	
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__nexus_reports_markets');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string) $chart, \IPS\Http\Url::internal( "app=nexus&module=reports&controller=markets" ) );
		}
	}
}