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
class _Search extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_activitystats_search';
	
	/**
	 * @brief	Default limit to number of graphed results
	 */
	protected $defaultLimit = 25;
	
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

		if( \IPS\Settings::i()->stats_search_prune )
		{
			$minimumDate = \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->stats_search_prune . 'D' ) );
		}

		$chart = new \IPS\Helpers\Chart\Database(
			$url,
			'core_statistics',
			'time',
			'',
			array(
				'isStacked' => FALSE,
				'backgroundColor' 	=> '#ffffff',
				'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde', '#b09be4', '#eec766', '#9fc973', '#e291bf', '#55c1a6', '#5fb9da' ),
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4,
				'limitSearch'		=> 'stats_search_term_menu',
			),
			'LineChart',
			'daily',
			array( 'start' => \IPS\DateTime::create()->sub( new \DateInterval( 'P90D' ) ), 'end' => \IPS\DateTime::ts( time() ) ),
			array(),
			'',
			$minimumDate
		);
		$chart->setExtension( $this );
		$chart->where	= array( array( 'type=?', 'search' ) );
		$chart->groupBy	= 'value_4';

		$terms = [];
		foreach( $this->getTerms( $chart->searchTerm ) as $v )
		{
			$terms[] = $v;
			$chart->addSeries( $v, 'number', 'COUNT(*)', FALSE );
		}

		if ( \count( $terms ) )
		{
			$chart->where[] = [ \IPS\Db::i()->in( 'value_4', $terms ) ];
		}

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('search_stats_chart');
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );
		
		return $chart;
	}
	
	/**
	 * @brief	Cached top search terms
	 */
	protected $_topSearchTerms = array();

	/**
	 * Get the top search terms
	 *
	 * @param	string|null		$term	Term we searched for
	 * @return	array
	 */
	public function getTerms( $term=NULL )
	{
		if( !isset( $this->_topSearchTerms[ $term ] ) )
		{
			$this->_topSearchTerms[ $term ] = array();

			$where = array( array( 'type=?', 'search' ) );

			if( $term !== NULL )
			{
				$where[] = \IPS\Db::i()->like( 'value_4', $term, TRUE, TRUE, TRUE );
			}

			foreach( \IPS\Db::i()->select( 'SQL_BIG_RESULT value_4, COUNT(*) as total', 'core_statistics', $where, 'total DESC', $this->defaultLimit, 'value_4' ) as $searchedValue )
			{
				$this->_topSearchTerms[ $term ][] = $searchedValue['value_4'];
			}
		}

		return $this->_topSearchTerms[ $term ];
	}
}