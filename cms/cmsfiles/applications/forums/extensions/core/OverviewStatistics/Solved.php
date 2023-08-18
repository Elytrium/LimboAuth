<?php
/**
 * @brief		Overview statistics extension: Solved
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		01 Dec 2020
 */

namespace IPS\forums\extensions\core\OverviewStatistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Overview statistics extension: Solved
 */
class _Solved
{
	/**
	 * @brief	Which statistics page (activity or user)
	 */
	public $page	= 'activity';

	/**
	 * Return the sub-block keys
	 *
	 * @note This is designed to allow one class to support multiple blocks, for instance using the ContentRouter to generate blocks.
	 * @return array
	 */
	public function getBlocks()
	{
		return array( 'percentagesolved', 'averagetimesolved' );
	}

	/**
	 * Return block details (title and description)
	 *
	 * @param	string|NULL	$subBlock	The subblock we are loading as returned by getBlocks()
	 * @return	array
	 */
	public function getBlockDetails( $subBlock = NULL )
	{
		switch( $subBlock )
		{
			case 'percentagesolved':
				return array( 'app' => 'forums', 'title' => 'stats_percentagesolved', 'description' => 'stats_percentagesolved_desc', 'refresh' => 60, 'form' => true );
			break;

			case 'averagetimesolved':
				return array( 'app' => 'forums', 'title' => 'stats_averagetimesolved', 'description' => 'stats_averagetimesolved_desc', 'refresh' => 60, 'form' => true );
			break;
		}
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
		/* Make sure someone isn't trying to manipulate the request or do something weird */
		if( !\in_array( $subBlock, $this->getBlocks() ) )
		{
			return '';
		}

		$value			= 0;
		$previousValue	= 0;
		$nodeNames		= array();

		/* Build where clause in the event we are filtering */
		$where			= array();
		$previousWhere	= NULL;

		if( $dateRange !== NULL )
		{
			if( \is_array( $dateRange ) )
			{
				$where = array(
					array( 'start_date > ?', $dateRange['start']->getTimestamp() ),
					array( 'start_date < ?', $dateRange['end']->getTimestamp() ),
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
				$where			= array( array( 'start_date > ?', $initialTimestamp ) );
				$previousWhere	= array( array( 'start_date BETWEEN ? AND ?', $currentDate->sub( $interval )->getTimestamp(), $initialTimestamp ) );
			}
		}
		else if ( $dateRange === NULL and isset( \IPS\Request::i()->nodes ) and \IPS\Request::i()->nodes )
		{
			$where = array( array( \IPS\Db::i()->in( 'forum_id', explode( ',', \IPS\Request::i()->nodes ) ) ) );
		}

		$where[] = array( \IPS\Db::i()->in( 'forum_id', iterator_to_array( \IPS\Db::i()->select( 'id', 'forums_forums', '(' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers' ) . ') OR ( ' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers_moderator' ) . ' )' ) ) ) );

		if( \IPS\Request::i()->nodes )
		{
			foreach( explode( ',', \IPS\Request::i()->nodes ) as $nodeId )
			{
				$nodeNames[] = \IPS\forums\Forum::load( $nodeId )->_title;
			}
		}

		/* Get the current and previous values */
		switch( $subBlock )
		{
			case 'percentagesolved':
				$total	= \IPS\Db::i()->select( 'COUNT(*)', 'forums_topics', $this->_modifyWhereClause( $where ) )->first();
				$solved	= \IPS\Db::i()->select( 'COUNT(*)', 'forums_topics', array_merge( $this->_modifyWhereClause( $where ), array( array( 'core_solved_index.id IS NOT NULL' ) ) ) )->join( 'core_solved_index', "core_solved_index.app='forums' AND core_solved_index.item_id=forums_topics.tid")->first();

				$value = $total ? round( $solved / $total * 100, 2 ) : 0;

				$previousTotal = $previousSolved = NULL;

				if( $previousWhere !== NULL )
				{
					$previousTotal	= \IPS\Db::i()->select( 'COUNT(*)', 'forums_topics', $this->_modifyWhereClause( $previousWhere ) )->first();
					$previousSolved	= \IPS\Db::i()->select( 'COUNT(*)', 'forums_topics', array_merge( $this->_modifyWhereClause( $previousWhere ), array( array( 'core_solved_index.id IS NOT NULL' ) ) ) )->join( 'core_solved_index', "core_solved_index.app='forums' AND core_solved_index.item_id=forums_topics.tid")->first();

					$previousValue = $previousTotal ? round( $previousSolved / $previousTotal * 100, 2 ) : 0;
				}

				return \IPS\Theme::i()->getTemplate( 'stats', 'forums' )->solvedPercentage( $value, $total, $solved, $previousValue, $previousTotal, $previousSolved, $nodeNames );
			break;

			case 'averagetimesolved':
				$value	= \IPS\Db::i()->select( 'AVG(core_solved_index.solved_date-forums_topics.start_date)', 'forums_topics', array_merge( $this->_modifyWhereClause( $where ), array( array( 'core_solved_index.id IS NOT NULL' ) ) ) )->join( 'core_solved_index', "core_solved_index.app='forums' AND core_solved_index.item_id=forums_topics.tid")->first();

				if( $previousWhere !== NULL )
				{
					$previousValue = \IPS\Db::i()->select( 'AVG(core_solved_index.solved_date-forums_topics.start_date)', 'forums_topics', array_merge( $this->_modifyWhereClause( $previousWhere ), array( array( 'core_solved_index.id IS NOT NULL' ) ) ) )->join( 'core_solved_index', "core_solved_index.app='forums' AND core_solved_index.item_id=forums_topics.tid")->first();
				}

				return \IPS\Theme::i()->getTemplate( 'stats', 'forums' )->timeToSolved( $value, $previousValue, $nodeNames );
			break;
		}

		return \IPS\Theme::i()->getTemplate( 'stats', 'forums' )->solvedPercentage( $values, $previousValues, $nodeNames );
	}

	/**
	 * Modify the where clause to apply other filters
	 * 
	 * @param	array	$where	Current where clause
	 * @return	array
	 */
	protected function _modifyWhereClause( $where )
	{
		$where[] = array( \IPS\Db::i()->in( 'approved', array( -2, -3 ), TRUE ) );
		
		$where = array_merge( $where, \IPS\forums\Topic::overviewStatisticsWhere() );
		
		if( \IPS\Request::i()->nodes )
		{
			$where[] = array( \IPS\Db::i()->in( 'forum_id', explode( ',', \IPS\Request::i()->nodes ) ) );
		}

		return $where;
	}

	/**
	 * Return block filter form, or the updated block result upon submit
	 *
	 * @param	string|NULL	$subBlock	The subblock we are loading as returned by getBlocks()
	 * @return	array
	 */
	public function getBlockForm( $subBlock = NULL )
	{
		/* Make sure someone isn't trying to manipulate the request or do something weird */
		if( !\in_array( $subBlock, $this->getBlocks() ) )
		{
			return '';
		}

		$form = new \IPS\Helpers\Form;
		$form->attributes['data-controller'] = 'core.admin.stats.nodeFilters';
		$form->attributes['data-block'] = \IPS\Request::i()->blockKey;
		$form->attributes['data-subblock'] = $subBlock;
		$form->add( new \IPS\Helpers\Form\Node( \IPS\forums\Forum::$nodeTitle, NULL, TRUE, array( 'class' => '\IPS\forums\Forum', 'multiple' => TRUE, 'clubs' => FALSE ) ) );

		if( $values = $form->values() )
		{
			$dateFilters = NULL;

			if( \IPS\Request::i()->range )
			{
				$dateFilters = \IPS\Request::i()->range;
			}
			elseif( \IPS\Request::i()->start )
			{
				try
				{
					$timezone = \IPS\Member::loggedIn()->timezone ? new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) : NULL;
				}
				catch ( \Exception $e )
				{
					$timezone = NULL;
				}

				$dateFilters = array(
					'start'	=> new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->start ), $timezone ),
					'end'	=> new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->end ), $timezone )
				);
			}

			return $this->getBlock( $dateFilters, $subblock );
		}

		return $form;
	}
}