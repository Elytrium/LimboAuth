<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		04 Jan 2016
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
class _RecountStarRatings
{
	/**
	 * @brief Number of items to build per cycle
	 */
	public $index = \IPS\REBUILD_QUICK;
	
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];
		
		if ( ! isset( $classname::$databaseColumnMap['rating_total'] ) )
		{
			return null;
		}
		
		try
		{			
			$select = \IPS\Db::i()->select( 'count(*)', $classname::$databaseTable, array( $classname::$databaseColumnMap['rating_total'] . ' > 0' ) );
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

		/* This could be a pages database that has since been deleted */
		try
		{
			$dateColumn = $classname::$databaseColumnMap['date'];
			$select = \IPS\Db::i()->select( '*', $classname::$databaseTable, array( $classname::$databaseColumnMap['rating_total'] . ' > 0' ), $classname::$databasePrefix . $dateColumn . ' DESC', array( $offset, $this->index ) );

			$column = $classname::$databaseColumnMap['rating_average'];
			$idColumn = $classname::$databaseColumnId;
			$iterator = new \IPS\Patterns\ActiveRecordIterator( $select, $classname );
			foreach( $iterator as $item )
			{
				$item->$column = round( \IPS\Db::i()->select( 'AVG(rating)', 'core_ratings', array( 'class=? AND item_id=?', $classname, $item->$idColumn ) )->first(), 1 );
				$item->save();
				$indexed++;
			}
		}
		catch( \Exception $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		if( $indexed != $this->index )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return ( $offset + $this->index );
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

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_starratings', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE, array( 'strtolower' => TRUE ) ) ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}		
}