<?php
/**
 * @brief		Background Task: Rebuild Solved Index
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 February 2020
 */

namespace IPS\forums\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild Solved Index
 */
class _RebuildSolvedIndex
{
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
			$data['count'] = \IPS\Db::i()->select( 'MAX(tid)', 'forums_topics', array( 'topic_answered_pid > 0' ) )->first();
			$data['realCount'] = \IPS\Db::i()->select( 'COUNT(*)', 'forums_topics', array( 'topic_answered_pid > 0' ) )->first();
		}
		catch( \Exception $e )
		{
			throw new \OutOfRangeException;
		}
		
		if( $data['count'] == 0 )
		{
			return NULL;
		}

		$data['completed'] = 0;
		
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
		if ( !class_exists( 'IPS\forums\Topic' ) OR !\IPS\Application::appisEnabled( 'forums' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		$last = NULL;
		
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'forums_topics', array( "tid>? and topic_answered_pid > 0", $offset ), "tid ASC", array( 0, \IPS\REBUILD_QUICK ) ), 'IPS\forums\Topic' ) AS $topic )
		{
			/* I told him we already got one! */
			try 
			{
				\IPS\Db::i()->select( '*', 'core_solved_index', array( 'comment_class=? and item_id=? and comment_id=?', 'IPS\\forums\\Topic\\Post', $topic->tid, $topic->topic_answered_pid ) )->first();
			}
			catch( \UnderflowException $e )
			{
				/* No? Best slap it in then */
				try 
				{
					$comment = \IPS\forums\Topic\Post::load( $topic->topic_answered_pid );
					
					\IPS\Db::i()->insert( 'core_solved_index', array(
						'member_id' => $comment->author()->member_id,
						'app'	=> 'forums',
						'comment_class' => 'IPS\\forums\\Topic\\Post',
						'comment_id' => $comment->pid,
						'item_id'	 => $topic->tid,
						'solved_date' => $comment->post_date // We don't have the real solve date so this will have to do
					) );
				}
				catch( \Exception $e ) { }
			}
			
			$data['completed']++;
			$last = $topic->tid;
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
		return array( 'text' =>  \IPS\Member::loggedIn()->language()->addToStack('queue_rebuilding_solved_posts'), 'complete' => $data['realCount'] ? ( round( 100 / $data['realCount'] * $data['completed'], 2 ) ) : 100 );
	}	

}