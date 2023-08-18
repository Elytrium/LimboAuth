<?php
/**
 * @brief		cleanupfiles Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		6 July 2016
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Task that removes files from core_files_temp that were never claimed
 */
class _cleanupfiles extends \IPS\Task
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
		foreach ( \IPS\Db::i()->select( '*', 'core_files_temp', array( 'time < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp() ) ) as $file )
		{
			
			try
			{
				\IPS\Db::i()->delete( 'core_files_temp', array( '`id`=?', $file['id'] ) );
				
				/* storage_extension was added in 4.1.14. If available, remove the file on disk too */
				if ( ! empty( $file['storage_extension'] ) )
				{
					\IPS\File::get( $file['storage_extension'], $file['contents'] )->delete();
				}
			}
			catch ( \Exception $e ) { }
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