<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	convert
 * @since		28 July 2016
 */

namespace IPS\convert\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _RebuildConversationFirstIds
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data	Data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		try
		{
			$data['count'] = \IPS\Db::i()->select( 'count(mt_id)', 'core_message_topics' )->first();
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
		$last = NULL;

		$topicIdsToReset			= array();
		$firstPostIds				= array();

		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_message_topics', array( "mt_id>?", $offset ), "mt_id ASC", array( 0, \IPS\REBUILD_SLOW ) ), 'IPS\core\Messenger\Conversation' ) AS $conversation )
		{
			try
			{
				/* Set first post */
				$conversation->first_msg_id = \IPS\Db::i()->select( 'msg_id', 'core_message_posts', array( 'msg_topic_id=?', $conversation->id ), 'msg_date ASC', 1 )->first();
				$conversation->save();

				/* Reset new_topic value for topic */
				$topicIdsToReset[]	= $conversation->id;
				$firstPostIds[]		= $conversation->first_msg_id;
			}
			/* Underflow exception may occur if the topic doesn't have any posts for an unknown reason */
			catch( \UnderflowException $e ) {}

			$last = $conversation->id;
			$data['completed']++;
		}

		/* Reset flags as needed */
		if( \count( $topicIdsToReset ) )
		{
			\IPS\Db::i()->update( 'core_message_posts', array( 'msg_is_first_post' => 0 ), array( 'msg_topic_id IN(' . implode( ',', $topicIdsToReset ) . ')' ) );
		}

		if( \count( $firstPostIds ) )
		{
			\IPS\Db::i()->update( 'core_message_posts', array( 'msg_is_first_post' => 1 ), array( 'msg_id IN(' . implode( ',', $firstPostIds ) . ')' ) );
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
	 * @return	array	Text explaining task and percentage complete
	 */
	public function getProgress( $data, $offset )
	{
		return array( 'text' =>  \IPS\Member::loggedIn()->language()->addToStack('queue_rebuilding_conversation_first_id'), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $data['completed'], 2 ) ) : 100 );
	}
}