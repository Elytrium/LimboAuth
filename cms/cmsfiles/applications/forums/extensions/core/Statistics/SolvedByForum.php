<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Forums
 * @since		26 Jan 2023
 */

namespace IPS\forums\extensions\core\Statistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _SolvedByForum extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = NULL;
	
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
		
		/* We can't retrieve any stats prior to the new tracking being implemented */
		try
		{
			$oldestLog = \IPS\Db::i()->select( 'MIN(time)', 'core_statistics', array( 'type=?', 'solved' ) )->first();
		
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
		
		$chart = new \IPS\Helpers\Chart\Database( $url, 'core_solved_index', 'solved_date', '', array( 
				'isStacked' => FALSE,
				'backgroundColor' 	=> '#ffffff',
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4,
				'chartArea'			=> array( 'width' => '70%', 'left' => '5%' ),
				'height'			=> 400,
			),
			'LineChart',
			'monthly',
			array( 'start' => ( new \IPS\DateTime )->sub( new \DateInterval('P90D') ), 'end' => new \IPS\DateTime ),
			array(),
			'solved' 
		);
		$chart->setExtension( $this );
		
		$chart->joins = array( array( 'forums_topics', array( 'comment_class=? and core_solved_index.item_id=forums_topics.tid', 'IPS\forums\Topic\Post' ) ) );
		$chart->where = array( array( \IPS\Db::i()->in( 'state', array( 'link', 'merged' ), TRUE ) ), array( \IPS\Db::i()->in( 'approved', array( -2, -3 ), TRUE ) ) ); 
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack( 'stats_topics_title_solved' );
		$chart->availableTypes = array( 'LineChart', 'ColumnChart' );
	
		$chart->groupBy = 'forum_id';
		$customValues = ( isset( $chart->savedCustomFilters['chart_forums'] ) ? array_values( explode( ',', $chart->savedCustomFilters['chart_forums'] ) ) : 0 );
		
		$chart->customFiltersForm = array(
			'form' => array(
				new \IPS\Helpers\Form\Node( 'chart_forums', $customValues, FALSE, array( 'class' => 'IPS\forums\Forum', 'zeroVal' => 'any', 'multiple' => TRUE, 'permissionCheck' => function ( $forum )
				{
					return $forum->sub_can_post and !$forum->redirect_url;
				} ), NULL, NULL, NULL, 'chart_forums' )
			),
			'where' => function( $values )
			{
				$forumIds = \is_array( $values['chart_forums'] ) ? array_keys( $values['chart_forums'] ) : explode( ',', $values['chart_forums'] );
				return \IPS\Db::i()->in( 'forum_id', $forumIds );
			},
			'groupBy' => 'forum_id',
			'series'  => function( $values )
			{
				$series = array();
				$forumIds = \is_array( $values['chart_forums'] ) ? array_keys( $values['chart_forums'] ) : explode( ',', $values['chart_forums'] );
				foreach( $forumIds as $id )
				{
					$series[] = array( \IPS\Member::loggedIn()->language()->addToStack( 'forums_forum_' . $id ), 'number', 'COUNT(*)', FALSE, $id );
				}
				return $series;
			},
			'defaultSeries' => function()
			{
				$series = array();
				foreach( \IPS\Db::i()->select( '*', 'forums_forums', array( 'topics>? and ( forums_bitoptions & ? or forums_bitoptions & ? or forums_bitoptions & ? )', 0, 4, 8, 16 ), 'last_post desc', array( 0, 50 ) ) as $forum )
				{
					$series[] = array( \IPS\Member::loggedIn()->language()->addToStack( 'forums_forum_' . $forum['id'] ), 'number', 'COUNT(*)', FALSE, $forum['id'] );
				}
				
				return $series;
			}
		);
		
		return $chart;
	}
	
	/**
	 * Get valid forum IDs to protect against bad data when a forum is removed
	 *
	 * @return array
	 */
	protected function getValidForumIds()
	{
		$validForumIds = [];
		
		foreach( \IPS\Db::i()->select( 'value_1', 'core_statistics', [ 'type=?', 'solved' ], NULL, NULL, 'value_1' ) as $forumId )
		{
			try
			{
				$validForumIds[ $forumId ] = \IPS\forums\Forum::load( $forumId );
			}
			catch( \Exception $e ) { }
		}
		
		return $validForumIds;
	}
}