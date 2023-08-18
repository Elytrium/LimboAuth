<?php
/**
 * @brief		updaterecords Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content 
 * @since		11 Dec 2015
 */

namespace IPS\cms\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * updaterecords Task
 */
class _updaterecords extends \IPS\Task
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
		foreach( \IPS\cms\Databases::databases() as $database )
		{
			$recordClass = 'IPS\cms\Records' . $database->id;
			
			$fixedFields = $database->fixed_field_perms;
			if ( ! \in_array( 'record_expiry_date', array_keys( $fixedFields ) ) )
			{
				continue;
			}

			/* Check the database is placed on a valid page */
			try
			{
				\IPS\cms\Pages\Page::loadByDatabaseId( $database->id );
			}
			catch( \OutOfRangeException $ex )
			{
				continue;
			}
			
			$permissions = $fixedFields['record_expiry_date'];
			
			if ( ! empty( $permissions['visible'] ) )
			{
				foreach( \IPS\Db::i()->select( '*', $recordClass::$databaseTable, array( 'record_expiry_date > 0 and record_expiry_date <= ? and record_approved=1', time() ),'primary_id_field ASC', 250 ) as $row )
				{
					$record = $recordClass::constructFromData( $row );
					$record->hide( FALSE );
				}
			}
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