<?php
/**
 * @brief		Background Task: Move Files from one storage method to another
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 May 2014
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Delete orphaned files
 */
class _DeleteOrphanedFiles
{
	/**
	 * @brief Number of files to delete per cycle
	 */
	public $batch = \IPS\REBUILD_NORMAL;
	
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
		$ids   = array();
		
		foreach( \IPS\Db::i()->select( '*', 'core_file_logs', array( 'log_type=?', 'orphaned' ), 'log_date DESC', array( 0, $this->batch ) ) as $row )
		{
			$ids[] = $row['log_id'];
			try
			{
				/* We shouldn't need to make sure the image has moved because any issue would have been logged and the moved flag not set */
				\IPS\File::get( $row['log_configuration_id'], trim( ( ( ! empty( $row['log_container'] ) ) ? $row['log_container'] . '/'  : '' ) . $row['log_filename'], '/' ) )->delete();
			}
			catch( \Exception $e )
			{
				/* Any issues with deletion will be logged, so we can still remove this row */
			}
		}

		if ( \count( $ids ) )
		{
			\IPS\Db::i()->delete( 'core_file_logs', array( \IPS\Db::i()->in( 'log_id', array_values( $ids ) ) ) );
			
			return $this->batch + $offset;
		}
		
		throw new \IPS\Task\Queue\OutOfRangeException;
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
		$extensionName = '';
		$count = \intval( $data['count'] );
		try
		{
			$extensionName = \IPS\Db::i()->select( 'method', 'core_file_storage', array( 'id=?', $data['configurationId'] ) )->first();
		}
		catch( \Exception $e ) { }
		
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('deleting_orphaned_files', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $extensionName ) ) ) ), 'complete' => $count ? round( ( 100 / $count * $offset ), 2 ) : 100 );
	}	
}