<?php
/**
 * @brief		Overview statistics extension: Badges
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		10 Mar 2021
 */

namespace IPS\core\extensions\core\OverviewStatistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Overview statistics extension: Badges
 */
class _Badges
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
		return array( 'subblockKey' );
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
		return array( 'app' => 'core', 'title' => 'stats_member_badges_overview', 'description' => null, 'refresh' => 60 );
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
		$where			= NULL;
		$previousCount	= NULL;

		if( $dateRange !== NULL )
		{
			if( \is_array( $dateRange ) )
			{
				$where = array(
					array( 'datetime > ?', $dateRange['start']->getTimestamp() ),
					array( 'datetime < ?', $dateRange['end']->getTimestamp() ),
				);
			}
			else
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
				$where = array( array( 'datetime > ?', $initialTimestamp ) );

				$previousCount = \IPS\Db::i()->select( 'COUNT(*)', 'core_member_badges', array( array( 'datetime BETWEEN ? AND ?', $currentDate->sub( $interval )->getTimestamp(), $initialTimestamp ) ) )->first();
			}
		}

		$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_member_badges', $where )->first();

		return \IPS\Theme::i()->getTemplate( 'stats' )->overviewComparisonCount( $count, $previousCount );
	}
}