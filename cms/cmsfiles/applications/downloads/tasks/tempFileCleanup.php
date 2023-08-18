<?php
/**
 * @brief		tempFileCleanup Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		10 Oct 2013
 */

namespace IPS\downloads\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * tempFileCleanup Task
 */
class _tempFileCleanup extends \IPS\Task
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
		foreach ( \IPS\Db::i()->select( '*', 'downloads_files_records', array( 'record_file_id=0 AND record_time<?', \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp() ) ) as $file )
		{
			try
			{
				\IPS\File::get( $file['record_type'] === 'upload' ? 'downloads_Files' : 'downloads_Screenshots', $file['record_location'] )->delete();
			}
			catch ( \Exception $e ) { }

			if( $file['record_thumb'] )
			{
				try
				{
					\IPS\File::get( 'downloads_Screenshots', $file['record_thumb'] )->delete();
				}
				catch ( \Exception $e ) { }
			}

			if( $file['record_no_watermark'] )
			{
				try
				{
					\IPS\File::get( 'downloads_Screenshots', $file['record_no_watermark'] )->delete();
				}
				catch ( \Exception $e ) { }
			}
		}
		
		\IPS\Db::i()->delete( 'downloads_files_records', array( 'record_file_id=0 AND record_time<?', \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp() ) );
		
		\IPS\Db::i()->delete( 'downloads_sessions', array( 'dsess_start<?', \IPS\DateTime::create()->sub( new \DateInterval( 'PT6H' ) )->getTimestamp() ) );
		
		return NULL;
	}
}