<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		03 Aug 2017
 */

namespace IPS\nexus\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _MoveCustomerHistory
{
	/**
	 * @brief	Number of records to process per cycle
	 */
	public $perCycle = \IPS\REBUILD_QUICK;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		/* Task is not needed if this table doesn't exist */
		if( !\IPS\Db::i()->checkForTable( 'nexus_customer_history' ) )
		{
			return NULL;
		}

		$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_history' )->first();

		if( $data['count'] == 0 )
		{
			\IPS\Db::i()->dropTable( 'nexus_customer_history' );
			return NULL;
		}

		$data['done'] = 0;
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
		$logs = \IPS\Db::i()->select( '*', 'nexus_customer_history', array( 'log_id>?', $offset ), 'log_id ASC', $this->perCycle );
		$lastId = 0;

		foreach( $logs as $log )
		{
			$lastId = $log['log_id'];
			unset( $log['log_id'] );

			/* Set log app */
			$log['log_app'] = 'nexus';

			/* Convert nexus info into member history format */
			if( $log['log_data'] == 'info' )
			{
				$json = json_decode( $log['log_data'], TRUE );

				if( isset( $json['email'] ) )
				{
					$log['log_app'] = 'core';
					$log['log_data'] = json_encode( array( 'old' => $data['email'], 'new' => '' ) );
				}
				elseif( isset( $data['password'] ) )
				{
					$log['log_app'] = 'core';
					$log['log_data'] = json_encode( array( 'password' => '' ) );
				}
			}

			/* Insert into member history */
			\IPS\Db::i()->insert( 'core_member_history', $log );
		}

		if( $lastId == 0 )
		{
			\IPS\Db::i()->dropTable( 'nexus_customer_history' );
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$data['done'] += $this->perCycle;

		return $lastId;
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('moving_customer_history'), 'complete' => $data['done'] ? ( round( 100 / $data['done'] * $data['count'], 2 ) ) : 0  );
	}
}