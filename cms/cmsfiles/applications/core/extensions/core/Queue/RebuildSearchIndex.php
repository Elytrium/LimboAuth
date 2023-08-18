<?php
/**
 * @brief		Background Task: Rebuild Search Index
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Aug 2014
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild Search Index
 */
class _RebuildSearchIndex
{
	/**
	 * @brief Number of content items to index per cycle
	 */
	public $index	= \IPS\REBUILD_QUICK;
	
	/**
	 * Build query
	 *
	 * @param	array	$data
	 * @return	array	array( 'where' => xxx, 'joins' => array() )
	 */
	protected function _buildQuery( $data )
	{
		$classname = $data['class'];
		
		$where = array();
		$joins = array();
		
		if ( isset( $data['container'] ) )
		{
			if ( \in_array( 'IPS\Content\Comment', class_parents( $classname ) ) )
			{
				$itemClass = $classname::$itemClass;
				$where[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'] . '=' . $data['container'] );
				$joins[ $itemClass::$databaseTable ] = $classname::$databasePrefix . $classname::$databaseColumnMap['item'] . '=' . $itemClass::$databasePrefix . $itemClass::$databaseColumnId;
			}
			else
			{
				$where[] = array( $classname::$databasePrefix . $classname::$databaseColumnMap['container'] . '=' . $data['container'] );
			}
		}

		if ( is_subclass_of( $classname, 'IPS\Content\Comment' ) AND $classname::commentWhere() !== NULL )
		{
			$where[] = $classname::commentWhere();
		}
		
		if( \IPS\Settings::i()->search_method == 'mysql' and \IPS\Settings::i()->search_index_timeframe )
		{
			if( isset( $classname::$databaseColumnMap['date'] ) )
			{
				$where[] = array( $classname::$databasePrefix . $classname::$databaseColumnMap['date'] . '> ?', \IPS\DateTime::ts( time() - ( 86400 * \IPS\Settings::i()->search_index_timeframe ) )->getTimestamp() );
			}
		}
		
		return array( 'where' => $where, 'joins' => $joins );
	}

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];
		
		\IPS\Log::debug( "Getting preQueueData for " . $classname, 'rebuildSearchIndex' );
		
		$queryData = $this->_buildQuery( $data );
		try
		{
			$select = \IPS\Db::i()->select( 'MAX(' . $classname::$databasePrefix . $classname::$databaseColumnId . ')', $classname::$databaseTable, $queryData['where'] );
			foreach ( $queryData['joins'] as $table => $on )
			{
				$select->join( $table, $on );
			}
			$data['count'] = $select->first();
			
			/* We're going to use the < operator, so we need to ensure the most recent item is indexed */
		    $data['runPid'] = $data['count'] + 1;
		    
			$select = \IPS\Db::i()->select( 'COUNT(*)', $classname::$databaseTable, $queryData['where'] );
			foreach ( $queryData['joins'] as $table => $on )
			{
				$select->join( $table, $on );
			}
			$data['realCount'] = $select->first();
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
		/* We want to allow read/write separation in this task */
		\IPS\Db::i()->readWriteSeparation = TRUE;

		$classname = $data['class'];		
        $exploded = explode( '\\', $classname );
        if ( !class_exists( $classname ) or !\IPS\Application::appIsEnabled( $exploded[1] ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		$indexed = NULL;
		
		\IPS\Log::debug( "Running " . $classname . ", with an offset of " . $offset, 'rebuildSearchIndex' );
		
		$queryData = $this->_buildQuery( $data );		
		
		$indexer = \IPS\Content\Search\Index::massIndexer();
				
		/* A pages database may have been deleted */
		try
		{
			$select = \IPS\Db::i()->select( '*', $classname::$databaseTable, array_merge( $queryData['where'], array( array( $classname::$databasePrefix . $classname::$databaseColumnId . ' < ?',  $data['runPid'] ) ) ), $classname::$databasePrefix . $classname::$databaseColumnId . ' DESC', array( 0, $this->index ) );
			foreach ( $queryData['joins'] as $table => $on )
			{
				$select->join( $table, $on );
			}
			
			try
			{
				$iterator = new \IPS\Patterns\ActiveRecordIterator( $select, $classname );
			
				foreach( $iterator as $item )
				{
					$idColumn = $classname::$databaseColumnId;
		
					try
					{
						if ( !$item->isFutureDate() )
						{
							$indexer->index($item);
						}
					}
					catch( \OutOfRangeException $e )
					{
						/* This can happen if there are older, orphaned posts/comments. Just do nothing here,
						don't even log it, because we end up with pages and pages of logs. */
					}
					catch ( \Exception $e )
					{
						/* There was an issue indexing the item - skip and log it */
						\IPS\Log::log( $e, 'rebuildSearchIndex' );
					}
					catch( \Throwable $e )
					{
						/* There was an issue indexing the item - skip and log it */
						\IPS\Log::log( $e, 'rebuildSearchIndex' );
					}
		
					$indexed = $item->$idColumn;
					
					/* Store the runPid for the next iteration of this Queue task. This allows the progress bar to show correctly. */
					$data['runPid'] = $item->$idColumn;
					$data['indexed']++;
				}
			}
			catch( \OutOfRangeException $e )
			{
				/* Turn off read/write separation before returning */
				\IPS\Db::i()->readWriteSeparation = FALSE;

				/* Something has gone wrong with iterator attempting to use constructFromData */
				throw new \IPS\Task\Queue\OutOfRangeException;
			}
		}
		catch( \IPS\Db\Exception $e )
		{
			/* Turn off read/write separation before returning */
			\IPS\Db::i()->readWriteSeparation = FALSE;

			/* Something has gone wrong with the query, like the table not existing */
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		if( $indexed === NULL )
		{
			/* Turn off read/write separation before returning */
			\IPS\Db::i()->readWriteSeparation = FALSE;

			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* Turn off read/write separation before returning */
		\IPS\Db::i()->readWriteSeparation = FALSE;
				
		/* Return the number indexed so far, so that the rebuild progress bar text makes sense */
		return $data['indexed'];
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
		$class = $data['class'];
		$exploded = explode( '\\', $class );
		if ( !class_exists( $class ) or !\IPS\Application::appIsEnabled( $exploded[1] ) )
		{
			throw new \OutOfRangeException;
		}
		
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('reindexing_stuff', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl_lc' ) ) ) ), 'complete' => $data['realCount'] ? ( round( 100 / $data['realCount'] * $data['indexed'], 2 ) ) : 100 );
	}	
}