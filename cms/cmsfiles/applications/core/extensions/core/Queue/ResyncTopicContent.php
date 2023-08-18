<?php
/**
 * @brief		Background Task: Resynchronise the automatically generated topic created by other content items
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		11 Jun 2018
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild database records
 */
class _ResyncTopicContent
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];

		try
		{
			$category = $classname::load( $data['categoryId'] );

			$data['count'] = (int) $category->getContentItemCount();
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
		$classname	= $data['class'];
		$itemClass	= $classname::$contentItemClass;
		$rebuilt	= 0;

		try
		{
			$category = $classname::load( $data['categoryId'] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		try
		{
			$iterator = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', $itemClass::$databaseTable, array( array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'] . '=' . $data['categoryId'] ) ), $itemClass::$databasePrefix . $itemClass::$databaseColumnId . ' DESC', array( $offset, \IPS\REBUILD_SLOW ) ), $itemClass );

			foreach( $iterator as $item )
			{
				$item->syncTopic();
				$rebuilt++;
			}
		}
		catch( \Exception $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		if( !$rebuilt )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return ( $offset + \IPS\REBUILD_SLOW );
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
		$classname = $data['class'];

		$title = $classname::load( $data['categoryId'] )->_title;
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_stuff', FALSE, array( 'sprintf' => array( $title ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}
}