<?php
/**
 * @brief		Background Task: Remove orphaned reputation
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Oct 2018
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Remove Orphaned Reputation
 */
class _RemoveOrphanedReputation
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

		/* Make sure there's something to process */
		if( !\IPS\IPS::classUsesTrait( $classname, 'IPS\Content\Reactable' ) )
		{
			$data['count'] = 0;
		}
		else
		{
			try
			{
				$data['count']		= \IPS\Db::i()->select( 'MAX( id )', 'core_reputation_index', array( 'app=? and type=?', $classname::$application, $classname::reactionType() ) )->first();
				$data['realCount']	= \IPS\Db::i()->select( 'COUNT(*)', 'core_reputation_index', array( 'app=? and type=?', $classname::$application, $classname::reactionType() ) )->first();
			}
			catch( \Exception $ex )
			{
				throw new \OutOfRangeException;
			}
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

		/* Make sure there's something to process */
		if( !\IPS\IPS::classUsesTrait( $classname, 'IPS\Content\Reactable' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$toDelete = array();
		$last     = NULL;
		foreach( \IPS\Db::i()->select( '*', 'core_reputation_index', array( 'app=? and type=? and id > ?', $classname::$application, $classname::reactionType(), $offset ), 'id asc', array( 0, $this->rebuild ) ) as $row )
		{
			try
			{
				$post = $classname::load( $row['type_id'] );
			}
			catch( \OutOfRangeException $ex )
			{
				$toDelete[] = $row['id'];
			}

			$last = $row['id'];
			$data['indexed']++;
		}

		if( \count( $toDelete ) )
		{
			\IPS\Db::i()->delete( 'core_reputation_index', array( \IPS\Db::i()->in( 'id', $toDelete ) ) );
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

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('removing_orphaned_reputation', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE, array( 'strtolower' => TRUE ) ) ) ) ), 'complete' => $data['realCount'] ? ( round( 100 / $data['realCount'] * $data['indexed'], 2 ) ) : 100 );
	}
}