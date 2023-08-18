<?php
/**
 * @brief		Background Task: Rebuild Report Index Data
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Sept 2022
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild Item Counts (comments, etc)
 */
class _RebuildReportIndexData
{
	/**
	 * @brief Number of content items to index per cycle
	 */
	public $index	= \IPS\REBUILD_QUICK;
	
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		try
		{			
			$data['count']		= \IPS\Db::i()->select( 'MAX(id)', 'core_rc_index' )->first();
			$data['realCount']	= \IPS\Db::i()->select( 'COUNT(*)', 'core_rc_index' )->first();
		}
		catch( \IPS\Db\Exception $ex )
		{
			return NULL;
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}
		
		if( $data['count'] == 0 )
		{
			return null;
		}

		$data['indexed']	= 0;
		
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
	public function run( &$data, $offset )
	{
		$last = NULL;

		try
		{
			foreach( \IPS\Db::i()->select( '*', 'core_rc_index', array( 'id > ?',  $offset ), 'id ASC', array( 0, $this->index ) ) as $report )
			{
				$last = $report['id'];
				$classname = $report['class'];
				$exploded = explode( '\\', $classname );
				if ( ! class_exists( $classname ) or ! \IPS\Application::appIsEnabled( $exploded[1] ) )
				{
					continue;
				}
			
				try
				{
					$content = $classname::load( $report['content_id'] );
					$itemId = 0;
					$nodeId = 0;
					$item = null;
					
					if ( $content instanceof \IPS\Content\Item )
					{
						$item = $content;
					}
					else
					{
						$item = $content->item();
					}
					
					$idColumn = $item::$databaseColumnId;
					$itemId = $item->$idColumn;
					if ( $node = $item->containerWrapper() )
					{
						$nodeId = $node->_id;
					}
					
					\IPS\Db::i()->update( 'core_rc_index', [
						'item_id' => $itemId,
						'node_id' => $nodeId
					],
					[
						'id=?', $report['id']
					] );
				}
				catch( \Exception $e )
				{
					continue;
				}
			}
		}
		catch( \Exception $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		if( $last === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		return $last;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 */
	public function getProgress( $data, $offset )
	{
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_report_index_data'), 'complete' => $data['realCount'] ? ( round( 100 / $data['realCount'] * $data['indexed'], 2 ) ) : 100 );
	}

}