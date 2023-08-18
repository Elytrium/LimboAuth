<?php
/**
 * @brief		solvedStatsAggregation Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	forums
 * @since		25 Jul 2022
 */

namespace IPS\forums\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * solvedStatsAggregation Task
 */
class _solvedStatsAggregation extends \IPS\Task
{
	/**
	 * Aggregate yesterday's (server time 0:00:00 to 23:59:59) solve stats per forum. Depending when the task runs, it is possible for it to run multiple
	 * times in a day, so make sure we remove any previous entries. I've put the data in core_statistics but if this becomes too large to manage, we can
	 * create a new table for it. Ostensibly this is a core feature, but really our focus is on forums to use the solve feature.
	 *
	 * core_statistics mapping:
	 * type: solved
	 * value_1: forum_id
	 * value_2: total topics added
	 * value_3: total solved
	 * value_4: AVG time to solved (in seconds)
	 * time: timestamp of the start of the day (so 0:00:00)
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		$timezone = new \DateTimeZone( \IPS\Settings::i()->reputation_timezone );
		$end = \IPS\DateTime::create()->setTimezone( $timezone )->sub( new \DateInterval( 'P1D' ) )->setTime( 23, 59 );
			
		/* Now to iterate over all forums that have solved enabled and crunch the statistics */
		foreach( \IPS\Db::i()->select( '*', 'forums_forums', [ '(' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers' ) . ') OR ( ' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers_moderator' ) . ' )' ] ) as $forum )
		{
			$start = \IPS\DateTime::ts( \IPS\forums\Forum::constructFromData( $forum )->getFirstSolvedTime() )->setTimezone( $timezone );

			$where = [
				[ \IPS\Db::i()->in( 'state', array( 'link', 'merged' ), TRUE ) ],
				[ \IPS\Db::i()->in( 'approved', array( -2, -3 ), TRUE ) ],
				[ 'forum_id=?', $forum['id'] ],
				[ 'start_date > ? AND start_date < ?', $start->getTimestamp(), $end->getTimestamp() ]
			];
		
			$total	= \IPS\Db::i()->select( 'COUNT(*)', 'forums_topics', $where )->first();
			$solved = \IPS\Db::i()->select( 'COUNT(*)', 'forums_topics', array_merge( $where, [ [ 'core_solved_index.id IS NOT NULL' ] ] ) )->join( 'core_solved_index', "core_solved_index.app='forums' AND core_solved_index.item_id=forums_topics.tid")->first();
			$avg	= \IPS\Db::i()->select( 'AVG(CAST(core_solved_index.solved_date AS SIGNED)-forums_topics.start_date)', 'forums_topics', array_merge( $where, [ [ 'core_solved_index.id IS NOT NULL' ] ] ) )->join( 'core_solved_index', "core_solved_index.app='forums' AND core_solved_index.item_id=forums_topics.tid")->first();
			
			\IPS\Db::i()->delete( 'core_statistics', [ 'type=? and time=? and value_1=?', 'solved', $end->getTimestamp(), $forum['id'] ] );
			\IPS\Db::i()->insert( 'core_statistics', [
				'type'    => 'solved',
				'value_1' => $forum['id'],
				'value_2' => $total,
				'value_3' => $solved,
				'value_4' => $avg,
				'time'    => $end->getTimestamp()
			] );
		}
		
		
		return NULL;
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}