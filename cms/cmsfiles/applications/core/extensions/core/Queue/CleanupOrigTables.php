<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		01 Aug 2017
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _CleanupOrigTables
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
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
		/* Get the first table with orig_ and drop it. We do one at a time to prevent timeouts. */
		$tables = \IPS\Db::i()->getTables( 'orig_' . \IPS\Db::i()->prefix );

		if( !\count( $tables ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$table = array_shift( $tables );

		/* We call query directly for the drop table statement because we do not want the prefix prepended to the table name */
		\IPS\Db::i()->query( "DROP TABLE `{$table}`" );

		/* Increment offset and return it */
		return ++$offset;
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
		$text = \IPS\Member::loggedIn()->language()->addToStack('orig_cleanup_task', FALSE, array() );

		return array( 'text' => $text, 'complete' => $data['originalCount'] ? ( round( 100 / $data['originalCount'] * $offset, 2 ) ) : 100 );
	}

	/**
	 * Perform post-completion processing
	 *
	 * @param	array	$data		Data returned from preQueueData
	 * @param	bool	$processed	Was anything processed or not? If preQueueData returns NULL, this will be FALSE.
	 * @return	void
	 */
	public function postComplete( $data, $processed = TRUE )
	{
		/* Update the setting so we don't keep checking */
		\IPS\Settings::i()->changeValues( array( 'orig_tables_checked' => 1 ) );
	}
}