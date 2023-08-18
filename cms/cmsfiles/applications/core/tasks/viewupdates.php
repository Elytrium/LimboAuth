<?php
/**
 * @brief		view_updates Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Nov 2015
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * view_updates Task
 */
class _viewupdates extends \IPS\Task
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
		$this->runUntilTimeout(function(){
			$hasCount = FALSE;

			try
			{
				$database = \IPS\Db::i()->select( 'classname, id, count(*) AS count', 'core_view_updates', NULL, NULL, 20, array( 'classname', 'id' ), NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER );
				
				/* Database results first */
				foreach( $database as $row )
				{
					$hasCount = TRUE;

					try
					{
						$this->update( $row['classname'], $row['id'], $row['count'] );
					}
					catch( \OutOfRangeException $e )
					{
						\IPS\Db::i()->delete( 'core_view_updates', array( 'classname=?', $row['classname'] ) );
					}
					
					\IPS\Db::i()->delete( 'core_view_updates', array( 'classname=? AND id=?', $row['classname'], $row['id'] ) );
				}
			}
			catch ( \UnderflowException $e ) { }
			
			/* Now try redis */
			if ( \IPS\REDIS_ENABLED and \IPS\CACHE_METHOD == 'Redis' and ( \IPS\CACHE_CONFIG or \IPS\REDIS_CONFIG ) )
			{
				try
				{
					$redis = \IPS\Redis::i()->zRevRangeByScore( 'topic_views', '+inf', '-inf', array('withscores' => TRUE, 'limit' => array( 0, 20 ) ) );

					if( \is_array( $redis ) )
					{
						foreach ( $redis as $data => $count )
						{
							$hasCount = TRUE;

							list( $class, $id ) = explode( '__', $data );

							try
							{
								$this->update( $class, $id, \intval( $count ) );
							}
							catch ( \OutOfRangeException $e ) {}
						}

						\IPS\Redis::i()->zRem( 'topic_views', ...array_keys( $redis ) );
					}
				}
				catch( \Exception $e ) { }

				/* Now try advert impressions */
				try
				{
					$redis = \IPS\Redis::i()->zRevRangeByScore( 'advert_impressions', '+inf', '-inf', array('withscores' => TRUE, 'limit' => array( 0, 20 ) ) );

					if( \is_array( $redis ) )
					{
						$updates = [];
						foreach ( $redis as $id => $count )
						{
							$hasCount = TRUE;
							$updates[ $count ][] = $id;

							\IPS\Redis::i()->zRem( 'advert_impressions', $id );
						}

						foreach ( $updates as $incrementBy => $ids )
						{
							\IPS\Db::i()->update( 'core_advertisements', "ad_impressions=ad_impressions+" . $incrementBy, [ \IPS\Db::i()->in( 'ad_id', $ids ) ] );
						}
					}
				}
				catch( \Exception $e ) { }
			}
			
			/* Go for another go? */
			if ( $hasCount === TRUE )
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		});
	}
	
	/**
	 * Update the row
	 *
	 * @param	string	$class  Class to update
	 * @param	int		$id		ID of item
	 * @param	int		$count	Count to update
	 * @throws \OutOfRangeException	When table to update no longer exists
	 */
	protected function update( $class, $id, $count )
	{
		if ( class_exists( $class ) and \IPS\IPS::classUsesTrait( $class, 'IPS\Content\ViewUpdates' ) AND isset( $class::$databaseColumnMap['views'] ) )
		{
			try
			{
				\IPS\Db::i()->update(
					$class::$databaseTable,
					"`{$class::$databasePrefix}{$class::$databaseColumnMap['views']}`=`{$class::$databasePrefix}{$class::$databaseColumnMap['views']}`+{$count}",
					array( "{$class::$databasePrefix}{$class::$databaseColumnId}=?", $id )
				);
			}
			catch( \IPS\Db\Exception $e )
			{
				/* Table to update no longer exists */
				if( $e->getCode() == 1146 )
				{
					throw new \OutOfRangeException;
				}

				throw $e;
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