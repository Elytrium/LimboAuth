<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/

 * @since		26 Jan 2023
 */

namespace IPS\core\extensions\core\Statistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _Emails extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_activitystats_emailstats_emails';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		/* Determine minimum date */
		$minimumDate = NULL;

		if( \IPS\Settings::i()->prune_log_emailstats > 0 )
		{
			$minimumDate = \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->prune_log_emailstats . 'D' ) );
		}

		/* We can't retrieve any stats prior to the new tracking being implemented */
		try
		{
			$oldestLog = \IPS\Db::i()->select( 'MIN(time)', 'core_statistics', array( 'type=?', 'emails_sent' ) )->first();

			if( !$minimumDate OR $oldestLog < $minimumDate->getTimestamp() )
			{
				$minimumDate = \IPS\DateTime::ts( $oldestLog );
			}
		}
		catch( \UnderflowException $e )
		{
			/* We have nothing tracked, set minimum date to today */
			$minimumDate = \IPS\DateTime::create();
		}

		$startDate = \IPS\DateTime::ts( time() - ( 60 * 60 * 24 * 30 ) );
		
		/* If our start date is older than our minimum date, use that as the start date instead */
		if ( $startDate->getTimestamp() < $minimumDate->getTimestamp() )
		{
			$startDate = $minimumDate;
		}

		$chart = new \IPS\Helpers\Chart\Callback( 
			$url, 
			array( $this, 'getResults' ),
			'', 
			array( 
				'isStacked' => TRUE,
				'backgroundColor' 	=> '#ffffff',
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4,
				'height'			=> 450
			), 
			'LineChart', 
			'daily',
			array( 'start' => $startDate, 'end' => \IPS\DateTime::create() ),
			'',
			$minimumDate
		);
		$chart->setExtension( $this );

		$chart->availableTypes	= array( 'LineChart', 'ColumnChart', 'BarChart' );
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_emailstats_emails');

		/* Force the notice that the chart is displayed in the server time zone */
		$chart->timezoneError = TRUE;
		$chart->hideTimezoneLink = TRUE;

		foreach( $this->_getEmailTypes() as $series )
		{
			$chart->addSeries( $series, 'number' );
		}
		
		return $chart;
	}
	
	/**
	 * Fetch the results
	 *
	 * @param	\IPS\Helpers\Chart\Callback	$chart	Chart object
	 * @return	array
	 */
	public function getResults( $chart )
	{
		$where = array( array( "time>?", 0 ) );
		$where[] = array( 'type=?', 'emails_sent' );

		if ( $chart->start )
		{
			$where[] = array( "value_4>=?", $chart->start->format('Y-m-d') );
		}
		if ( $chart->end )
		{
			$where[] = array( "value_4<=?", $chart->end->format('Y-m-d') );
		}

		$results = array();

		foreach( \IPS\Db::i()->select( '*', 'core_statistics', $where, 'value_4 ASC' ) as $row )
		{
			/* We need to use month/days NOT prefixed with '0' - i.e. 2019-2-12 instead of 2019-02-12 - to match the chart helper */
			$_date = new \IPS\DateTime( $row['value_4'] );

			switch( $chart->timescale )
			{
				case 'daily':
					$_date = $_date->format( 'Y-n-j' );
				break;

				case 'weekly':
					$_date = $_date->format( 'o-W' );
				break;

				case 'monthly':
					$_date = $_date->format( 'Y-n' );
				break;
			}
			

			if( !isset( $results[ $_date ] ) )
			{
				$results[ $_date ] = array( 'time' => $_date );

				foreach( $this->_getEmailTypes() as $series )
				{
					$results[ $_date ][ $series ] = 0;
				}
			}

			$lang = $row['extra_data'];

			try
			{
				$lang = \IPS\Member::loggedIn()->language()->get( 'emailstats__' . $row['extra_data'] );
			}
			catch( \UnderflowException $e ){}

			$results[ $_date ][ $lang ] += (int) $row['value_1'];
		}

		return $results;
	}
	
	/**
	 * @brief	Cached email types
	 */
	protected $_emailTypes = NULL;

	/**
	 * Get all possible email types logged
	 *
	 * @return array
	 */
	protected function _getEmailTypes()
	{
		if( $this->_emailTypes === NULL )
		{
			$this->_emailTypes = array();

			foreach( \IPS\Db::i()->select( 'extra_data', 'core_statistics', array( 'type=?', 'emails_sent' ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_DISTINCT ) as $series )
			{
				$lang = $series;

				try
				{
					$lang = \IPS\Member::loggedIn()->language()->get( 'emailstats__' . $series );
				}
				catch( \UnderflowException $e ){}

				$this->_emailTypes[] = $lang;
			}
		}

		return array_unique( $this->_emailTypes );
	}
}