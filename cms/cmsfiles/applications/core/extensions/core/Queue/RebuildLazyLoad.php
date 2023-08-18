<?php
/**
 * @brief		Background Task: Rebuild posts to apply or remove lazy loading
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 July 2018
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild posts to apply or remove lazy loading
 */
class _RebuildLazyLoad
{
	/**
	 * @brief Number of content items to rebuild per cycle
	 */
	public $rebuild	= \IPS\REBUILD_NORMAL;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];

		\IPS\Log::debug( "Getting preQueueData for " . $classname, 'rebuildLazyLoad' );

		try
		{
			$data['count']		= $classname::db()->select( 'MAX(' . $classname::$databasePrefix . $classname::$databaseColumnId . ')', $classname::$databaseTable, ( is_subclass_of( $classname, 'IPS\Content\Comment' ) ) ? $classname::commentWhere() : array() )->first();
			$data['realCount']	= $classname::db()->select( 'COUNT(*)', $classname::$databaseTable, ( is_subclass_of( $classname, 'IPS\Content\Comment' ) ) ? $classname::commentWhere() : array() )->first();

			/* We're going to use the < operator, so we need to ensure the most recent item is rebuilt */
		    $data['runPid'] = $data['count'] + 1;
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}

		\IPS\Log::debug( "PreQueue count for " . $classname . " is " . $data['count'], 'rebuildLazyLoad' );

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
		$classname = $data['class'];
        $exploded = explode( '\\', $classname );
        if ( !class_exists( $classname ) or !\IPS\Application::appIsEnabled( $exploded[1] ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* Make sure there's even content to parse */
		if( !isset( $classname::$databaseColumnMap['content'] ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		\IPS\Log::debug( "Running " . $classname . ", with an offset of " . $offset, 'rebuildLazyLoad' );

		$where	  = ( is_subclass_of( $classname, 'IPS\Content\Comment' ) ) ? ( \is_array( $classname::commentWhere() ) ? array( $classname::commentWhere() ) : array() ) : array();
		$select   = $classname::db()->select( '*', $classname::$databaseTable, array_merge( $where, array( array( $classname::$databasePrefix . $classname::$databaseColumnId . ' < ?',  $data['runPid'] ) ) ), $classname::$databasePrefix . $classname::$databaseColumnId . ' DESC', array( 0, $this->rebuild ) );
		$iterator = new \IPS\Patterns\ActiveRecordIterator( $select, $classname );
		$last     = NULL;
		
		/* This could be a pages database that has since been deleted */
		try
		{
			foreach( $iterator as $item )
			{
				$idColumn = $classname::$databaseColumnId;
	
				/* Did the rebuild previously time out on this? If so we need to skip it and move along */
				if( isset( \IPS\Data\Store::i()->currentLazyLoadRebuild ) )
				{
					/* If the last rebuild cycle timed out, currentRebuild might be set and we might have already rebuilt this post (the post that caused the rebuild to fail might come after this (but before in chronological order)).
						If that is the case, we should skip rebuilding this post again. */
					if( \is_array( \IPS\Data\Store::i()->currentLazyLoadRebuild ) AND \IPS\Data\Store::i()->currentLazyLoadRebuild[0] == $classname AND \IPS\Data\Store::i()->currentLazyLoadRebuild[1] < $item->$idColumn )
					{
						$last = $item->$idColumn;
						continue;
					}
	
					/* If the last rebuild cycle failed and we have just retrieved the post we last attempted to rebuild, skip it and move along */
					if( \is_array( \IPS\Data\Store::i()->currentLazyLoadRebuild ) AND \IPS\Data\Store::i()->currentLazyLoadRebuild[0] == $classname AND \IPS\Data\Store::i()->currentLazyLoadRebuild[1] == $item->$idColumn )
					{
						unset( \IPS\Data\Store::i()->currentLazyLoadRebuild );
						$last = $item->$idColumn;
						continue;
					}
				}
	
				$contentColumn	= $classname::$databaseColumnMap['content'];
	
				/* Before we start trying to rebuild, set a flag to note what we are trying to rebuild. If it times out, we can check
					this on the next load and skip the problematic content */
				\IPS\Data\Store::i()->currentLazyLoadRebuild = array( $classname, $item->$idColumn );
	
				$item->$contentColumn = \IPS\Text\Parser::parseLazyLoad( $item->$contentColumn, (bool) $data['status'] );
	
				try
				{
					$item->save();
				}
				/* Content item may be orphaned, continue if we cannot save it. */
				catch( \OutOfRangeException $e ) {}
	
				$last = $item->$idColumn;
	
				$data['indexed']++;
	
				/* Now we will reset the rebuild flag we previously set since it rebuilt and saved successfully */
				unset( \IPS\Data\Store::i()->currentLazyLoadRebuild );
			}
		}
		catch( \Throwable $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* Store the runPid for the next iteration of this Queue task. This allows the progress bar to show correctly. */
		$data['runPid'] = $last;

		if( $last === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* Return the number rebuilt so far, so that the rebuild progress bar text makes sense */
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

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_lazyload_stuff', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl_lc' ) ) ) ), 'complete' => $data['realCount'] ? ( round( 100 / $data['realCount'] * $data['indexed'], 2 ) ) : 100 );
	}
}