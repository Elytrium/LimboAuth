<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		27 Jul 2022
 */

namespace IPS\forums\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _RebuildSolvedStats
{
	/**
	 * @brief Number of days to rebuild per cycle
	 */
	public $rebuild	= 30;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		try
		{
			$forum = \IPS\forums\Forum::load( $data['forum_id'] );
			$timezone = new \DateTimeZone( \IPS\Settings::i()->reputation_timezone );

			/* Reset solved time */
			$forum->solved_stats_from = 0;
			$forum->save();

			/* We work a day in arrears */
			$oldest = \IPS\DateTime::ts( $forum->getFirstSolvedTime() )->setTimezone( $timezone )->setTime( 12, 0 );
			$newest = \IPS\DateTime::create()->setTimezone( $timezone )->setTime( 12, 0 );

			$diff = $newest->diff( $oldest );

			$data['count'] = $diff->days;
			$data['date'] = $oldest->getTimeStamp();
			$data['max'] = $newest->getTimeStamp();

			\IPS\Db::i()->delete( 'core_statistics', [ 'type=? and value_1=?', 'solved', $forum->_id ] );
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}

		if( $data['count'] == 0 )
		{
			return null;
		}

		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed						$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int							$offset	Offset
	 * @return	int							New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( &$data, $offset )
	{
		if ( !class_exists( 'IPS\forums\Topic' ) OR !\IPS\Application::appisEnabled( 'forums' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		try
		{
			$forum = \IPS\forums\Forum::load( $data['forum_id'] );
		}
		catch( \Exception $ex )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$done = 0;
		for( $i = 0; $i < $this->rebuild; $i++ )
		{
			$timezone = new \DateTimeZone( \IPS\Settings::i()->reputation_timezone );
			$end   = \IPS\DateTime::ts( $data['date'], true )->setTimezone( $timezone )->sub( new \DateInterval('P1D') )->setTime( 23, 59 );

			if ( $end->getTimeStamp() >= $data['max'] )
			{
				/* We're done */
				throw new \IPS\Task\Queue\OutOfRangeException;
			}

			$start = \IPS\DateTime::ts( $forum->getFirstSolvedTime() )->setTimezone( $timezone );

			$where = [
				[ \IPS\Db::i()->in( 'state', array( 'link', 'merged' ), TRUE ) ],
				[ \IPS\Db::i()->in( 'approved', array( -2, -3 ), TRUE ) ],
				[ 'forum_id=?', $forum->_id ],
				[ 'start_date > ? AND start_date < ?', $start->getTimestamp(), $end->getTimestamp() ]
			];


			$total	= \IPS\Db::i()->select( 'COUNT(*)', 'forums_topics', $where )->first();
			$solved = \IPS\Db::i()->select( 'COUNT(*)', 'forums_topics', array_merge( $where, [ [ 'core_solved_index.id IS NOT NULL' ] ] ) )->join( 'core_solved_index', "core_solved_index.app='forums' AND core_solved_index.item_id=forums_topics.tid")->first();
			$avg	= \IPS\Db::i()->select( 'AVG(CAST(core_solved_index.solved_date AS SIGNED)-forums_topics.start_date)', 'forums_topics', array_merge( $where, [ [ 'core_solved_index.id IS NOT NULL' ] ] ) )->join( 'core_solved_index', "core_solved_index.app='forums' AND core_solved_index.item_id=forums_topics.tid")->first();

			\IPS\Db::i()->insert( 'core_statistics', [
				'type'    => 'solved',
				'value_1' => $forum->_id,
				'value_2' => $total,
				'value_3' => $solved,
				'value_4' => $avg,
				'time'    => $end->getTimestamp()
			] );

			$data['date'] = \IPS\DateTime::ts( $data['date'] )->add( new \DateInterval('P1D') )->getTimeStamp();
			$done++;
		}

		if ( ! $done )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $done + $offset;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		try
		{
			$forum = \IPS\forums\Forum::load( $data['forum_id'] );
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('solved_rebuilding_stats', NULL, [ 'sprintf' => [ $forum->_title ] ] ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}

	/**
	 * Perform post-completion processing
	 *
	 * @param	array	$data		Data returned from preQueueData
	 * @param	bool	$processed	Was anything processed or not? If preQueueData returns NULL, this will be FALSE.
	 * @return	void
	 */
	public function postComplete( $data, $processed = TRUE )
	{

	}
}