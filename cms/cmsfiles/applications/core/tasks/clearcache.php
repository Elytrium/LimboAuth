<?php
/**
 * @brief		clearcache Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 May 2015
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * clearcache Task
 */
class _clearcache extends \IPS\Task
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
		/* Note: We previously disabled the task if the caching method was disabled however this lead to people re-enabling through a constants edit which would not re-enabled the task */
		\IPS\Db::i()->delete( 'core_cache', array( 'cache_expire<?', time() ) );
		\IPS\Output\Cache::i()->deleteExpired();

		/* If we are using Redis, ensure that core_store is empty. We may have switched over to Redis from MySQL and if we switch back, we do not want stale data being used */
		if ( \IPS\STORE_METHOD !== 'Database' AND \IPS\Db::i()->select( 'count(*)', 'core_store' )->first() )
		{
			\IPS\Db::i()->delete( 'core_store' );
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