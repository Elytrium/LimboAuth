<?php
/**
 * @brief		Background Task: Rebuild content images following storage method change
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Jun 2014
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild content images
 */
class _RebuildContentImages
{
	/**
	 * @brief Number of content items to rebuild per cycle
	 */
	public $rebuild	= \IPS\REBUILD_SLOW;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];
		
		\IPS\Log::debug( "Getting preQueueData for " . $classname, 'rebuildContentImages' );
		
		try
		{			
			$data['count']		= $classname::db()->select( 'MAX(' . $classname::$databasePrefix . $classname::$databaseColumnId . ')', $classname::$databaseTable )->first();
			$data['realCount']	= $classname::db()->select( 'COUNT(*)', $classname::$databaseTable )->first();
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

		\IPS\Log::debug( "Running " . $classname . ", with an offset of " . $offset, 'rebuildContentImages' );

		/* This could be a pages database that has since been deleted */
		try
		{
			$contentColumn	= $classname::$databaseColumnMap['content'];
			$idColumn = $classname::$databaseColumnId;

			$iterator = new \IPS\Patterns\ActiveRecordIterator( $classname::db()->select( '*', $classname::$databaseTable, array( array( $classname::$databasePrefix . $classname::$databaseColumnId . ' > ?',  $offset ) ) , $classname::$databasePrefix . $classname::$databaseColumnId . ' ASC', array( 0, $this->rebuild ) ), $classname );
			$last     = NULL;

			foreach( $iterator as $item )
			{
				$rebuilt = \IPS\Text\Parser::rebuildAttachmentUrls( $item->$contentColumn );

				if( $rebuilt )
				{
					$item->$contentColumn = $rebuilt;
					$item->save();
				}

				$last = $item->$idColumn;
				$data['indexed']++;
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
		$class = $data['class'];
        $exploded = explode( '\\', $class );
        if ( !class_exists( $class ) OR !\IPS\Application::appIsEnabled( $exploded[1] ) )
        {
	        throw new \OutOfRangeException;
        }

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_content_images', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE, array( 'strtolower' => TRUE ) ) ) ) ), 'complete' => $data['realCount'] ? ( round( 100 / $data['realCount'] * $data['indexed'], 2 ) ) : 100 );
	}	
}