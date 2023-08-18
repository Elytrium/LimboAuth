<?php
/**
 * @brief		Overview statistics extension: Subscriptions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		15 Jan 2020
 */

namespace IPS\nexus\extensions\core\OverviewStatistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Overview statistics extension: Subscriptions
 */
class _Subscriptions
{
	/**
	 * @brief	Which statistics page (activity or user)
	 */
	public $page	= 'user';

	/**
	 * Return the sub-block keys
	 *
	 * @note This is designed to allow one class to support multiple blocks, for instance using the ContentRouter to generate blocks.
	 * @return array
	 */
	public function getBlocks()
	{
		return array( 'subscriptions' );
	}

	/**
	 * Return block details (title and description)
	 *
	 * @param	string|NULL	$subBlock	The subblock we are loading as returned by getBlocks()
	 * @return	array
	 */
	public function getBlockDetails( $subBlock = NULL )
	{
		/* Description can be null and will not be shown if so */
		return \IPS\Settings::i()->nexus_subs_enabled ? array( 'app' => 'nexus', 'title' => 'stats_overview_subscriptions', 'description' => null, 'refresh' => 60 ) : NULL;
	}

	/** 
	 * Return the block HTML to show
	 *
	 * @param	array|NULL	$dateRange	NULL for all time, or an array with 'start' and 'end' \IPS\DateTime objects to restrict to
	 * @param	string|NULL	$subBlock	The subblock we are loading as returned by getBlocks()
	 * @return	string
	 */
	public function getBlock( $dateRange = NULL, $subBlock = NULL )
	{
		/* Init Chart */
		$pieBarData = array();
		
		/* Add Rows */
		$where			= array( array( 'ps_app=?', 'nexus' ), array( 'ps_type=?', 'subscription' ) );
		$previousCount	= NULL;

		if( \is_array( $dateRange ) )
		{
			$where[] = array( 'ps_start > ?', $dateRange['start']->getTimestamp() );
			$where[] = array( 'ps_start < ?', $dateRange['end']->getTimestamp() );
		}
		elseif( $dateRange !== NULL )
		{
			$currentDate	= new \IPS\DateTime;
			$interval		= NULL;

			switch( $dateRange )
			{
				case '7':
					$interval = new \DateInterval( 'P7D' );
				break;

				case '30':
					$interval = new \DateInterval( 'P1M' );
				break;

				case '90':
					$interval = new \DateInterval( 'P3M' );
				break;

				case '180':
					$interval = new \DateInterval( 'P6M' );
				break;

				case '365':
					$interval = new \DateInterval( 'P1Y' );
				break;
			}

			$initialTimestamp = $currentDate->sub( $interval )->getTimestamp();
			$where = array( array( 'ps_start > ?', $initialTimestamp ) );

			$previousCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( array( 'ps_app=?', 'nexus' ), array( 'ps_type=?', 'subscription' ), array( 'ps_start BETWEEN ? AND ?', $currentDate->sub( $interval )->getTimestamp(), $initialTimestamp ) ) )->first();
		}

		$total = 0;
		$chart = NULL;

		foreach( \IPS\nexus\Subscription\Package::roots() as $package )
		{
			$filter		= $where;
			$filter[]	= array( 'ps_item_id=?', $package->id );

			$value = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', $filter )->first();

			if( $value > 0 )
			{
				$total += $value;

				$pieBarData[] = array(
					'name' =>  \IPS\Member::loggedIn()->language()->addToStack('nexus_subs_' . $package->id ),
					'value' => $value,
					'percentage' => 0
				);
			}
		}

		foreach( $pieBarData as &$package )
		{
			$package['percentage'] = round( ( $package['value'] / $total ) * 100, 2 );
		}

		if( \count( $pieBarData ) )
		{
			$chart = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global'  )->applePieChart( $pieBarData );
		}

		return \IPS\Theme::i()->getTemplate( 'stats' )->overviewComparisonCount( $total, $previousCount, $chart );
	}
}