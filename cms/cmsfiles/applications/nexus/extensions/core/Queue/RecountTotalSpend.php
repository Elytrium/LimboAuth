<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		08 Aug 2018
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
class _RecountTotalSpend
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
		$data['count'] = (int) \IPS\Db::i()->select( 'count(*)', 'nexus_customers' )->first();

		if( $data['count'] == 0 )
		{
			return null;
		}

		$data['completed'] = 0;

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
		$last	= null;

		/* Loop over members to fix them */
		foreach( \IPS\Db::i()->select( '*', 'nexus_customers', array( 'member_id > ?', $offset ), 'member_id ASC', array( 0, $this->perCycle ) ) as $customer )
		{
			$last = $customer['member_id'];
			$data['completed']++;

			try
			{
				$customer = \IPS\nexus\Customer::load( $customer['member_id'] );
				$customer->recountTotalSpend();
			}
			catch( \Exception $ex ) { }
		}

		if( $last === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $last;
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'recounting_total_spend' ), 'complete' => $data['count'] ? ( round(  100 / $data['count'] * $data['completed'], 2 ) ) : 100 );
	}
}