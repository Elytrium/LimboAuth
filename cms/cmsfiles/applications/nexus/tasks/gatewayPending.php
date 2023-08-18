<?php
/**
 * @brief		gatewayPending Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	nexus
 * @since		06 Jun 2016
 */

namespace IPS\nexus\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * gatewayPending Task
 */
class _gatewayPending extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		$transactions = \IPS\Db::i()->select( '*', 'nexus_transactions', array( 't_status=?', \IPS\nexus\Transaction::STATUS_GATEWAY_PENDING ), 't_id ASC', 20 ); // We deliberately don't run until exhaustion here because we want to wait and try again later if it fails
		if ( !\count( $transactions ) )
		{
			$this->enabled = FALSE;
			$this->save();
		}
		else
		{
			foreach ( $transactions as $transaction )
			{
				/* Get it */
				$transaction = \IPS\nexus\Transaction::constructFromData( $transaction );

				if ( $transaction->method instanceof \IPS\nexus\Gateway\Paypal )
				{
					/* Try to capture it */
					try
					{
						$transaction->capture();
					}
					catch ( \Exception $e )
					{
						if ( $e->getMessage() === 'FAIL' or $e->getMessage() === 'RFND' or $transaction->date->getTimestamp() < ( time() - ( 86400 * 5 ) ) )
						{
							$status = $e->getMessage() === 'RFND' ? \IPS\nexus\Transaction::STATUS_REFUNDED : \IPS\nexus\Transaction::STATUS_REFUSED;

							$transaction->status = $status;
							$transaction->save();
							$transaction->member->log( 'transaction', array(
								'type'		=> 'status',
								'status'	=> $status,
								'id'		=> $transaction->id
							), FALSE );

							if ( $status === \IPS\nexus\Transaction::STATUS_REFUNDED )
							{
								$transaction->sendNotification();
							}
						}
						continue;
					}

					/* If it succeeded, take any fraud action */
					$fraudResult = $transaction->fraud_blocked ? $transaction->fraud_blocked->action : NULL;
					if ( $fraudResult )
					{
						$transaction->executeFraudAction( $fraudResult, TRUE );
					}
					if ( !$fraudResult or $fraudResult === \IPS\nexus\Transaction::STATUS_PAID )
					{
						$transaction->approve();
					}

					/* Let the customer know */
					$transaction->sendNotification();
				}
			}
		}
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