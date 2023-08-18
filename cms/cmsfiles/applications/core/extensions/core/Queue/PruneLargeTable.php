<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Jan 2020
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
class _PruneLargeTable
{
	/**
	 * @brief	Number of rows to prune at once
	 */
	const ROWS_TO_PRUNE = 10000;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		/* How many rows are there total, and how many will we be pruning? */
		$data['total']		= \IPS\Db::i()->select( 'COUNT(*)', $data['table'] )->first();
		$data['count']		= \IPS\Db::i()->select( 'COUNT(*)', $data['table'], $data['where'] )->first();
		$data['pruned']		= 0;

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
		$offset = (int) $offset;

		if( isset( $data['deleteJoin'] ) )
		{
			$select = \IPS\Db::i()->select( $data['deleteJoin']['column'], $data['deleteJoin']['table'], $data['deleteJoin']['where'], $data['deleteJoin']['column'] . ' ASC', $offset + static::ROWS_TO_PRUNE );

			$deleted = \IPS\Db::i()->delete( $data['table'], $select, NULL, NULL, array( $data['deleteJoin']['outerColumn'], $data['deleteJoin']['column'] ), \IPS\Db::i()->prefix . $data['table'] );
		}
		else
		{
			$deleted = \IPS\Db::i()->delete( $data['table'], $data['where'], $data['orderBy'] ?? NULL, static::ROWS_TO_PRUNE );
		}

		/* Are we done? */
		if( !$deleted )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$data['pruned'] += $deleted;

		return $offset + static::ROWS_TO_PRUNE;
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'backgroundQueue_pruning_table', FALSE, array( 'sprintf' => \IPS\Member::loggedIn()->language()->addToStack( 'prunetable_' . $data['setting'] ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $data['pruned'], 2 ) ) : 100 );
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
		/* If this was pruning follows, make sure to clear the follow count cache so it can rebuild */
		if( $data['setting'] == 'prune_follows' )
		{
			\IPS\Db::i()->delete( 'core_follow_count_cache' );
		}
	}
}