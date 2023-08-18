<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		27 Jun 2022
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _BuildTimeToRank
{
	/**
	 * @brief Number of topics to build per cycle
	 */
	public $perCycle	= \IPS\REBUILD_QUICK;

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
			$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_points_log', array( 'new_rank IS NOT NULL') )->first();
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
	public function run( $data, $offset )
	{
		$count = 0;

		foreach( \IPS\Db::i()->select( '*', 'core_points_log', array( 'new_rank IS NOT NULL'), 'id ASC', array( $offset, $this->perCycle ) )->join( 'core_members', 'core_members.member_id = core_points_log.member' ) as $log )
		{
			$count++;
			\IPS\Db::i()->update( 'core_points_log', array( 'time_to_new_rank' => $log['datetime'] - $log['joined'] ), array( 'id=?', $log['id'] ) );

			$offset = $log['id'];
		}

		if( !$count )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return ( $offset );
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rankprogression_rebuild'), 'complete' => round( 100 / $data['count'] * $offset, 2 ) );
	}
}