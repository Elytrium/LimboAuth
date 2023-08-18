<?php
/**
 * @brief		Background Task: Rebuild reputation leader board
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Jun 2014
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild Reputation leader board
 */
class _RebuildReputationLeaderboard
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
			$date = \IPS\Db::i()->select( 'MIN(rep_date)', 'core_reputation_index' )->first();
			$data = array();
			
			/* We work a day in arrears */
			$oldest = \IPS\DateTime::ts( $date )->setTime( 12, 0 )->add( new \DateInterval('P1D') );
			$newest = \IPS\DateTime::ts( time() )->setTime( 12, 0 );
			
			$diff = $newest->diff( $oldest );
			
			$data['count'] = $diff->days;
			$data['date'] = $oldest->getTimeStamp();
			$data['max'] = $newest->getTimeStamp();
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
		$done = 0;
		for( $i = 0; $i < $this->rebuild; $i++ )
		{
			$timezone = new \DateTimeZone( \IPS\Settings::i()->reputation_timezone );
			$start = \IPS\DateTime::ts( $data['date'], true )->setTimezone( $timezone )->sub( new \DateInterval('P1D') )->setTime( 0, 0, 1 );
			$end   = \IPS\DateTime::ts( $data['date'], true )->setTimezone( $timezone )->sub( new \DateInterval('P1D') )->setTime( 23, 59, 59 );
			
			if ( $end->getTimeStamp() >= $data['max'] )
			{
				/* We're done */
				throw new \IPS\Task\Queue\OutOfRangeException;
			}
			
			$position = 0;
			
			/* Get top rated contributors */
			$where = array();
			$where[] = array( 'member_received > 0 AND rep_date BETWEEN ? and ?', $start->getTimeStamp(), $end->getTimeStamp() );
			$where[] = \IPS\Db::i()->in( 'member_group_id', explode( ',',  \IPS\Settings::i()->leaderboard_excluded_groups ), TRUE );

			foreach( \IPS\Db::i()->select( 'core_reputation_index.member_received as themember, SUM(rep_rating) as rep', 'core_reputation_index', $where, 'rep DESC', 4, 'themember' )->join( 'core_members', array( 'core_reputation_index.member_received = core_members.member_id' ) )->setKeyField('themember')->setValueField('rep') as $member => $rep )
			{
				if ( $member and $rep )
				{
					\IPS\Db::i()->replace( 'core_reputation_leaderboard_history', array(
						'leader_date' 	   => $start->setTime( 12, 0 )->getTimeStamp(),
						'leader_member_id' => $member,
						'leader_position'  => ++$position,
						'leader_rep_total' => $rep
					) );
				}
			}
			
			/* Fill in the blanks */
			if ( $position and $position < 5 )
			{
				while( $position < 4 )
				{
					\IPS\Db::i()->replace( 'core_reputation_leaderboard_history', array(
						'leader_date' 	   => $start->setTime( 12, 0 )->getTimeStamp() - $position,
						'leader_member_id' => 0,
						'leader_position'  => ++$position,
						'leader_rep_total' => 0
					) );
				}
			}
			
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_reputation_leaderboard'), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}
}