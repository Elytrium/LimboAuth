<?php
/**
 * @brief		Dashboard extension: Member Stats
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Jul 2013
 */

namespace IPS\core\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Dashboard extension: Member Stats
 */
class _MemberStats
{
	/**
	* Can the current user view this dashboard item?
	*
	* @return	bool
	*/
	public function canView()
	{
		return TRUE;
	}

	/**
	 * Return the block to show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		$stats = NULL;
		
		/* check the cache */
		try
		{
			$stats = \IPS\Data\Store::i()->acpWidget_memberStats;
			
			if ( ! isset( $stats['_cached'] ) or $stats['_cached'] < time() - ( 60 * 30 ) )
			{
				$stats = NULL;
			}
		}
		catch( \Exception $ex ) { }

		if ( $stats === NULL )
		{
			$stats = array();
			
			/* fetch only successful registered members ; if this needs to be changed, please review the other areas where we have the name<>? AND email<>? condition */
			$where = array( array( 'completed=?', true ) );
	
			/* Member count */
			$stats['member_count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where )->first();
			
			/* Opt in members */
			$where[] = 'allow_admin_mails=1';
			$stats['member_optin'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where )->first();
			
			$stats['_cached'] = time();
			
			/* stil here? */
			\IPS\Data\Store::i()->acpWidget_memberStats = $stats;
		}
		
		/* Init Chart */
		$chart = new \IPS\Helpers\Chart;
		
		/* Specify headers */
		$chart->addHeader( \IPS\Member::loggedIn()->language()->get('chart_email_marketing_type'), "string" );
		$chart->addHeader( \IPS\Member::loggedIn()->language()->get('chart_members'), "number" );
		
		/* Add Rows */
		$chart->addRow( array( \IPS\Member::loggedIn()->language()->addToStack( 'memberStatsDashboard_optin' ), $stats['member_optin'] ) );
		$chart->addRow( array( \IPS\Member::loggedIn()->language()->addToStack( 'memberStatsDashboard_optout' ), $stats['member_count'] - $stats['member_optin'] ) );
		
				
		/* Output */
		return \IPS\Theme::i()->getTemplate( 'dashboard' )->memberStats( $stats, $chart->render( 'PieChart', array( 
			'backgroundColor' 	=> '#ffffff',
			'pieHole' => 0.4,
			'colors' => array( '#44af94', '#cc535f' ),
			'chartArea' => array( 
				'width' =>"90%", 
				'height' => "90%" 
			) 
		) ) );
	}
}