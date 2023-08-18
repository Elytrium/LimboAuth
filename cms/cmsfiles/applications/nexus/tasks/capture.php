<?php
/**
 * @brief		Task to capture payments approaching their authorization deadlines
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		12 Mar 2014
 */

namespace IPS\nexus\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Task to capture payments approaching their authorization deadlines
 */
class _capture extends \IPS\Task
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
		$taskFrequency = new \DateInterval( $this->frequency );
		$time = \IPS\DateTime::create()->add( $taskFrequency )->add( $taskFrequency );
		
		$this->runUntilTimeout( function() use ( $time )
		{
			try
			{
				$transaction = \IPS\nexus\Transaction::constructFromData( \IPS\Db::i()->select( '*', 'nexus_transactions', array( 't_auth<?', $time->getTimestamp() ), 't_auth ASC', 1 )->first() );
				
				if ( $transaction->method )
				{
					try
					{
						$transaction->capture();
					}
					catch ( \Exception $e )
					{
						if ( \in_array( $transaction->status, array( $transaction::STATUS_PENDING, $transaction::STATUS_WAITING, $transaction::STATUS_GATEWAY_PENDING ) ) )
						{
							$transaction->status = \IPS\nexus\Transaction::STATUS_REFUSED;
							$extra = $transaction->extra;
							$extra['history'][] = array( 's' => \IPS\nexus\Transaction::STATUS_REFUSED, 'noteRaw' => $e->getMessage() );
							$transaction->extra = $extra;
						}
						
						$transaction->auth = NULL;
						$transaction->save();
					}
				}
				else
				{
					/* the gateway doesn't exist anymore, so reset the auth time */
					$transaction->auth = NULL;
					$extra = $transaction->extra;
					$extra['history'][] = array( 's' => \IPS\nexus\Transaction::STATUS_REFUSED, 'on' => time(), 'noteRaw' => 'invalid_gateway' );
					$transaction->extra = $extra;
					$transaction->status = \IPS\nexus\Transaction::STATUS_REFUSED;
					$transaction->save();
	
					throw new \IPS\Task\Exception( $this, array( 'invalid_gateway', $transaction->id ) );
				}
			}
			catch ( \UnderflowException $e )
			{
				return FALSE;
			}
		});
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