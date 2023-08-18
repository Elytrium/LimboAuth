<?php
/**
 * @brief		Income Report
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
 * Income Report
 */
class _income extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'income_manage' );
		parent::execute();
	}

	/**
	 * View Report
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$currencies = \IPS\nexus\Money::currencies();

		$tabs = array( 'totals' => 'nexus_report_income_totals' );

		if( \count( $currencies ) == 1 )
		{
			$tabs['members'] = 'nexus_report_income_members';
		}
		else
		{
			foreach ( $currencies as $currency )
			{
				$tabs[ 'members_' . $currency ] = \IPS\Member::loggedIn()->language()->addToStack( 'nexus_report_income_by_member', FALSE, array( 'sprintf' => array( $currency ) ) );
			}
		}

		foreach ( $currencies as $currency )
		{
			$tabs[ $currency ] = \IPS\Member::loggedIn()->language()->addToStack( 'nexus_report_income_by_method', FALSE, array( 'sprintf' => array( $currency ) ) );
		}
		
		\IPS\Request::i()->tab ??= 'totals';
		$activeTab = ( array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'totals';

		$extension = \IPS\core\Statistics\Chart::loadFromExtension( 'nexus', 'Income' );
		$chart = $extension->getChart( \IPS\Http\Url::internal( 'app=nexus&module=reports&controller=income&tab=' . $activeTab ) );
		$extension->setExtra( $chart, $activeTab );
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) $chart;
		}
		else
		{	
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__nexus_reports_income');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string) $chart, \IPS\Http\Url::internal( "app=nexus&module=reports&controller=income" ) );
		}
	}
}