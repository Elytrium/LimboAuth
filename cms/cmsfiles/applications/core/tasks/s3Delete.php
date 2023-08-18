<?php
/**
 * @brief		s3Delete Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Jul 2020
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * s3_delete Task
 */
class _s3Delete extends \IPS\Task
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
		try 
		{
			$s3 = \IPS\Db::i()->select( '*', 'core_s3_deletions' )->first();
			
			$obj = \IPS\File::getClass('core_Theme');
			
			if ( !( $obj instanceof \IPS\File\Amazon ) )
			{
				/* Class is not Amazon, so just stop here and truncate the table as there's nothing we can really do now. */
				\IPS\Db::i()->delete( 'core_s3_deletions' );
				\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 0 ), array( '`key`=?', 's3_delete' ) );
				return NULL;
			}
			
			$keys = $obj->getContainerKeys( $s3['s3_container'], 101, '', $s3['s3_marker'] );
			
			if ( \count( $keys ) )
			{
				/* Get the 51st key, which will be the marker for the next iteration if we have 51 keys, otherwise, make marker null so it's removed in the next iteration */
				if ( \count( $keys ) < 101 )
				{
					$marker = NULL;
				}
				else
				{
					end( $keys );
					$marker = key( $keys );
					reset( $keys );
					array_pop( $keys );
				}
				
				foreach( $keys as $key => $time )
				{
					if ( $time <= $s3['s3_added'] )
					{
						\IPS\File::getClass('core_Theme')->deleteByKey( $key );
					}
				}
				
				if ( $marker )
				{
					/* Update the marker, there may be more to go */
					\IPS\Db::i()->update( 'core_s3_deletions', array( 's3_marker' => $marker ), array( 's3_container=?', $s3['s3_container'] ) );
				}
				else
				{
					/* Last batch, possible some keys not deleted if time didn't match */
					\IPS\Db::i()->delete( 'core_s3_deletions', array( 's3_container=?', $s3['s3_container'] ) );
				}
			}
			else
			{
				/* If no keys, delete container row from s3_deletions */
				\IPS\Db::i()->delete( 'core_s3_deletions', array( 's3_container=?', $s3['s3_container'] ) );
			}
		}
		catch( \UnderflowException $ex )
		{
			/* Nothing to do, so switch off the task */
			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 0 ), array( '`key`=?', 's3_delete' ) );
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