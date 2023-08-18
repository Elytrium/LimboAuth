<?php
/**
 * @brief		Background task to fix refunded transactions following the 4.3.5 change to treat refunds and credits separately
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		20 June 2018
 */

namespace IPS\nexus\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background task to fix refunded transactions following the 4.3.5 change to treat refunds and credits separately
 */
class _FixRefundTransactions
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
		$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( \IPS\Db::i()->in( 't_status', array( \IPS\nexus\Transaction::STATUS_REFUNDED, \IPS\nexus\Transaction::STATUS_PART_REFUNDED ) ) ) )->first();

		if( $data['count'] == 0 )
		{
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
		$transactions = \IPS\Db::i()->select( '*', 'nexus_transactions', array( array( 't_id>?', $offset ), array( \IPS\Db::i()->in( 't_status', array( \IPS\nexus\Transaction::STATUS_REFUNDED, \IPS\nexus\Transaction::STATUS_PART_REFUNDED ) ) ) ), 't_id ASC', $this->perCycle );
		$lastId = 0;
		
		foreach( $transactions as $transaction )
		{									
			$extra = json_decode( $transaction['t_extra'], TRUE );
			if ( $extra and isset( $extra['history'] ) )
			{
				$credit = new \IPS\Math\Number('0');
				$totalRefunded = new \IPS\Math\Number('0');
				
				$update = array();
				$updateHistory = FALSE;
				foreach ( $extra['history'] as $i => $history )
				{
					if ( ( $history['s'] === 'rfnd' or $history['s'] === 'prfd' ) )
					{
						if ( $history['to'] === 'credit' )
						{
							if ( $history['s'] === 'rfnd' )
							{
								$updateHistory = TRUE;
								$extra['history'][ $i ]['s'] = 'prfd';
							}
							
							if ( isset( $history['amount'] ) )
							{
								$credit = $credit->add( new \IPS\Math\Number( (string) $history['amount'] ) );
							}
							else
							{
								$credit = $credit->add( new \IPS\Math\Number( (string) $transaction['t_amount'] ) );
							}
						}
						else
						{
							if ( isset( $history['amount'] ) )
							{
								$totalRefunded = $totalRefunded->add( new \IPS\Math\Number( (string) $history['amount'] ) );
							}
							else
							{
								$totalRefunded = $totalRefunded->add( new \IPS\Math\Number( (string) $transaction['t_amount'] ) );
							}
						}
					}
				}
				
				if ( $updateHistory )
				{
					$update['t_extra'] = json_encode( $extra );
				}
				if ( $credit->isGreaterThanZero() )
				{
					$update['t_credit'] = (string) $credit;
				}
				if ( $transaction['t_status'] === \IPS\nexus\Transaction::STATUS_PART_REFUNDED and $totalRefunded->compare( new \IPS\Math\Number( (string) $transaction['t_partial_refund'] ) ) !== 0 )
				{
					$update['t_partial_refund'] = (string) $totalRefunded;
				}
				if ( $transaction['t_status'] === \IPS\nexus\Transaction::STATUS_REFUNDED and $totalRefunded->compare( new \IPS\Math\Number( (string) $transaction['t_amount'] ) ) !== 0 )
				{
					$update['t_status'] = \IPS\nexus\Transaction::STATUS_PART_REFUNDED;
				}
				if ( $update )
				{
					\IPS\Db::i()->update( 'nexus_transactions', $update, array( 't_id=?', $transaction['t_id'] ) );
				}
			}
			
			$lastId = $transaction['t_id'];
		}
		
		if( $lastId == 0 )
		{
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('upgrade_fix_refund_transactions'), 'complete' => $data['done'] ? ( round( 100 / $data['done'] * $data['count'], 2 ) ) : 0  );
	}
}