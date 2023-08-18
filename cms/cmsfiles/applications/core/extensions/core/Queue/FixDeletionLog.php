<?php
/**
 * @brief		Background Task: Restore missing items to deletion log
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Oct 2017
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Restore missing items to deletion log
 */
class _FixDeletionLog
{
	/**
	 * @brief Number of entries per cycle to restore
	 */
	public $perCycle	= \IPS\REBUILD_QUICK;
	
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];

		/* If this class doesn't support hiding we can't use deletion log */
		if ( !\in_array( 'IPS\Content\Hideable', class_implements( $classname ) ) )
		{
			throw new \OutOfRangeException;
		}
		
		\IPS\Log::debug( "Getting preQueueData for " . $classname, 'fixDeletionLog' );
		
		try
		{
			$where = array();
			$where[] = array( 'dellog_id IS NULL' );

			if ( isset( $classname::$databaseColumnMap['approved'] ) )
			{
				$where[] = array( $classname::$databaseTable . '.' . $classname::$databasePrefix . $classname::$databaseColumnMap['approved'] . '=?', -2 );
			}
			elseif ( isset( $classname::$databaseColumnMap['hidden'] ) )
			{
				$where[] = array( $classname::$databaseTable . '.' . $classname::$databasePrefix . $classname::$databaseColumnMap['hidden'] . '=?', -2 );
			}
			else
			{
				/* Failsafe */
				throw new \OutOfRangeException;
			}

			$select = \IPS\Db::i()->select( 'count(*)', $classname::$databaseTable, $where )->join( 'core_deletion_log', array( 'dellog_content_class=? and dellog_content_id=' . $classname::$databaseTable . '.' . $classname::$databasePrefix . $classname::$databaseColumnId, $classname ) );
			$data['count'] = $select->first();
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}
		
		if( $data['count'] == 0 )
		{
			return null;
		}
		
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
		$classname = $data['class'];
        $exploded = explode( '\\', $classname );
        if ( !class_exists( $classname ) or !\IPS\Application::appIsEnabled( $exploded[1] ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		$indexed = 0;
		
		\IPS\Log::debug( "Running " . $classname . ", with an offset of " . $offset, 'fixDeletionLog' );

		/* This could be a pages database that has since been deleted */
		try
		{
			$where = array();
			$where[] = array( 'dellog_id IS NULL' );

			if ( isset( $classname::$databaseColumnMap['approved'] ) )
			{
				$where[] = array( $classname::$databaseTable . '.' . $classname::$databasePrefix . $classname::$databaseColumnMap['approved'] . '=?', -2 );
			}
			elseif ( isset( $classname::$databaseColumnMap['hidden'] ) )
			{
				$where[] = array( $classname::$databaseTable . '.' . $classname::$databasePrefix . $classname::$databaseColumnMap['hidden'] . '=?', -2 );
			}

			$select = \IPS\Db::i()->select( $classname::$databaseTable . '.*', $classname::$databaseTable, $where )->join( 'core_deletion_log', array( 'dellog_content_class=? and dellog_content_id=' . $classname::$databaseTable . '.' . $classname::$databasePrefix . $classname::$databaseColumnId, $classname ) );

			$iterator = new \IPS\Patterns\ActiveRecordIterator( $select, $classname );
			foreach( $iterator as $item )
			{
				$idColumn = $classname::$databaseColumnId;

				/* Try to figure out member */
				$memberId	= NULL;
				try
				{
					if ( \in_array( 'IPS\Content\Review', class_parents( $classname ) ) )
					{
						$memberId	= \IPS\Db::i()->select( 'member_id', 'core_moderator_logs', array( 'class=? AND item_id=? AND ( lang_key=? AND note LIKE ? )', $classname, $item->$idColumn, 'modlog__action_delete', '%review%' ) )->first();
					}
					elseif ( \in_array( 'IPS\Content\Comment', class_parents( $classname ) ) )
					{
						$memberId	= \IPS\Db::i()->select( 'member_id', 'core_moderator_logs', array( 'class=? AND item_id=? AND lang_key=?', $classname, $item->$idColumn, 'modlog__comment_delete' ) )->first();
					}
					else
					{
						$memberId	= \IPS\Db::i()->select( 'member_id', 'core_moderator_logs', array( 'class=? AND item_id=? AND lang_key=?', $classname, $item->$idColumn, 'modlog__action_delete' ) )->first();
					}
				}
				catch( \Exception $e ){}

				$log = new \IPS\core\DeletionLog;
				$log->setContentAndMember( $item, $memberId ? \IPS\Member::load( $memberId ) : FALSE );

				$log->save();
				$indexed++;
			}
		}
		catch( \Exception $e )
		{
			\IPS\Log::log( $e, 'deletion_log' );
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* If we didn't find any we are done */
		if( !$indexed )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return ( $offset + $indexed );
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
        $class = $data['class'];
        $exploded = explode( '\\', $class );
        if ( !class_exists( $class ) or !\IPS\Application::appIsEnabled( $exploded[1] ) )
		{
			throw new \OutOfRangeException;
		}

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('fixdellog_task', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE, array( 'strtolower' => TRUE ) ) ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}	
}