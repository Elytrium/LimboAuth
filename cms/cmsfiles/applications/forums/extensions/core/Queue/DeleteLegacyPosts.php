<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		18 Mar 2015
 */

namespace IPS\forums\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _DeleteLegacyPosts
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'forums_posts', 'queued > 2' )->first();
		
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
		$select = \IPS\Db::i()->select( '*', 'forums_posts', 'queued > 2', NULL, array( 0, \IPS\REBUILD_NORMAL ) );
		if ( !\count( $select ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$done = 0;
		foreach( $select as $post )
		{
			try
			{
				\IPS\forums\Topic\Post::constructFromData( $post )->logDelete( FALSE );
			}
			catch( \Exception $e )
			{
				\IPS\Db::i()->update( 'forums_posts', [ 'queued' => '-1' ], [ 'pid=?', $post['pid'] ] );
			}

			$done++;
		}
		
		return $offset + $done;
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('queue_deleting_legacy_posts'), 'complete' => round( 100 / ( $data['count'] ?: 1 + $offset ) * $offset, 2 ) );
	}	
}