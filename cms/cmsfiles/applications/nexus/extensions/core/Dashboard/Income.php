<?php
/**
 * @brief		Dashboard extension: Income
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		18 Sep 2014
 */

namespace IPS\nexus\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Dashboard extension: Income
 */
class _Income
{
	/**
	* Can the current user view this dashboard item?
	*
	* @return	bool
	*/
	public function canView()
	{
		return \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus' , 'transactions', 'transactions_manage' );
	}

	/** 
	 * Return the block HTML show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		$chart = new \IPS\Helpers\Chart;
		
		$chart->addHeader( "Day", 'date' );
		foreach ( \IPS\nexus\Money::currencies() as $currency )
		{
			$chart->addHeader( $currency, 'number' );
		}
		
		$thirtyDaysAgo = \IPS\DateTime::create()->sub( new \DateInterval('P30D') );
				
		$results = array();
		foreach( \IPS\Db::i()->select( "t_currency, DATE_FORMAT( FROM_UNIXTIME( t_date ), '%e %c %Y' ) AS date, SUM(t_amount)-SUM(t_partial_refund) AS amount", 'nexus_transactions', array( 't_date>? AND (t_status=? OR t_status=?) AND t_method>0', $thirtyDaysAgo->getTimestamp(), \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_PART_REFUNDED ), NULL, NULL, array( 't_currency', 'date' ) ) as $result )
		{
			$results[ $result['date'] ][ $result['t_currency'] ] = $result['amount'];
		}
				
		$monthAndYear = date( 'n' ) . ' ' . date( 'Y' );
		foreach ( range( 30, 0 ) as $daysAgo )
		{
			$datetime = new \IPS\DateTime;
			$datetime->setTime( 0, 0, 0 );
			$datetime->sub( new \DateInterval( 'P' . $daysAgo . 'D' ) );
			$resultString = $datetime->format('j n Y');
			
			if ( isset( $results[ $resultString ] ) )
			{
				$row = array( $datetime );
				
				foreach ( \IPS\nexus\Money::currencies() as $currency )
				{
					if ( !isset( $results[ $resultString ][ $currency ] ) )
					{
						$row[] = 0;
					}
					else
					{
						$row[] = $results[ $resultString ][ $currency ];
					}
				}
				
				$chart->addRow( $row );
			}
			else
			{
				$row = array( $datetime );
				foreach ( \IPS\nexus\Money::currencies() as $currency )
				{
					$row[] = 0;
				}
				$chart->addRow( $row );
			}
		}
		
		return $chart->render( 'AreaChart', array(
			'backgroundColor' 	=> '#ffffff',
			'colors'			=> array( '#10967e' ),
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4,
		) );
	}

	/** 
	 * Return the block information
	 *
	 * @return	array	array( 'name' => 'Block title', 'key' => 'unique_key', 'size' => [1,2,3], 'by' => 'Author name' )
	 */
	public function getInfo()
	{
		return array();
	}

	/**
	 * Save the block data submitted.  This method is only necessary if your block accepts some sort of submitted data to save (such as the 'admin notes' block).
	 *
	 * @return	void
	 * @throws	\LogicException
	 */
	public function saveBlock()
	{
	}
}