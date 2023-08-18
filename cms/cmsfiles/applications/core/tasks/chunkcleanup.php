<?php
/**
 * @brief		chunkcleanup Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		01 May 2020
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * chunkcleanup Task
 */
class _chunkcleanup extends \IPS\Task
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
		$chunksCleanedUp = 0;

		/* Loop over file storage configurations */
		foreach( \IPS\File::getStore() as $configuration )
		{
			/* If this is a filesystem config... */
			if( $configuration['method'] == 'FileSystem' )
			{
				/* Get the configuration object */
				$fileStorageObject = \IPS\File::getClass( $configuration['id'] );

				/* If we have a chunks folder */
				if( \is_dir( $fileStorageObject->configuration['dir'] . '/chunks' ) )
				{
					/* Loop over all the files in the directory */
					$iterator = new \DirectoryIterator( $fileStorageObject->configuration['dir'] . '/chunks' );

					foreach( $iterator as $file )
					{
						/* If this is a file and the name isn't index.html... */
						if( $file->isFile() AND $file->getFilename() != 'index.html' )
						{
							/* If the last modification time is more than 12 hours old, prune */
							if( $file->getMTime() < time() - ( 60 * 60 * 12 ) )
							{
								@unlink( $file->getPathname() );
								$chunksCleanedUp++;
							}
						}
					}
				}
			}
		}

		return $chunksCleanedUp ? "Deleted {$chunksCleanedUp} orphaned chunks" : NULL;
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