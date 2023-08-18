<?php
/**
 * @brief		mycharts
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		09 Dec 2022
 */

namespace IPS\core\modules\admin\overview;

use IPS\core\Statistics\Chart;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * mycharts
 */
class _mycharts extends \IPS\Dispatcher\Controller
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
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$charts = [];
		foreach( Chart::getChartsForMember( \IPS\Http\Url::internal( "app=core&module=overview&controller=mycharts" ), TRUE ) AS $id )
		{
			$charts[] = $id;
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_overview_mycharts');
		if ( !\count( $charts ) )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('stats')->mychartsEmpty();
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('stats')->mycharts( $charts );
		}
	}
	
	/**
	 * Get chart
	 *
	 * @return	void
	 */
	public function getChart()
	{
		
		if ( !isset( \IPS\Request::i()->chartId ) )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->output = '';
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=overview&controller=mycharts" ) );
			}
		}
		
		try
		{
			$chart = Chart::constructMemberChartFromData( \IPS\Request::i()->chartId, \IPS\Http\Url::internal( "app=core&module=overview&controller=mycharts&do=getChart&chartId=" . \IPS\Request::i()->chartId ) );
			\IPS\Output::i()->output = (string) $chart;
		}
		catch( \Throwable )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->output = '';
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=overview&controller=mycharts" ) );
			}
		}
	}
}