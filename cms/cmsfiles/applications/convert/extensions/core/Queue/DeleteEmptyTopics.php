<?php

/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	convert
 * @since		26 July 2016
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
class _DeleteEmptyTopics
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
			$data['count'] = \IPS\Db::i()->select( 'count(tid)', 'forums_topics', array( 'forums_posts.pid IS NULL' ) )->join( 'forums_posts', 'forums_posts.topic_id=forums_topics.tid' )->first();
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
	 * @param	mixed			$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int				$offset	Offset
	 * @return	int|null		New offset or NULL if complete
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( &$data, $offset )
	{
		if ( !class_exists( 'IPS\forums\Topic' ) OR !\IPS\Application::appisEnabled( 'forums' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* If app was removed, then cancel this */
		try
		{
			$app = \IPS\convert\App::load( $data['app'] );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$last = NULL;

		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'forums_topics', array( "tid>? AND forums_posts.pid IS NULL", $offset ), "tid ASC", array( 0, \IPS\REBUILD_SLOW ) )->join( 'forums_posts', 'forums_posts.topic_id=forums_topics.tid' ), 'IPS\forums\Topic' ) AS $topic )
		{
			$tid = $topic->tid;

			/* Is this converted content? */
			try
			{
				/* Just checking, we don't actually need anything */
				$app->checkLink( $tid, 'forums_topics' );
			}
			catch( \OutOfRangeException $e )
			{
				$last = $tid;
				$data['completed']++;
				continue;
			}

			/* If the topic isn't archived, we can delete it */
			if ( $topic->isArchived() == FALSE )
			{
				$topic->delete();
			}
			else
			{
				/* Archived topics will erroneously be picked up by our query, but that's ok...we'll just check them here and delete if empty */
				try
				{
					/* Do we have any posts? This is more efficient than running a COUNT(*) query, funny enough */
					\IPS\forums\Topic\ArchivedPost::db()->select( 'archive_id', 'forums_archive_posts', array( "archive_topic_id=?", $topic->tid ), NULL, 1 )->first();
				}
				/* This topic is empty */
				catch( \UnderflowException $e )
				{
					$topic->delete();
				}
			}

			$last = $tid;
			$data['completed']++;
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
	 * @return	array	Text explaning task and percentage complete
	 */
	public function getProgress( $data, $offset )
    {
        return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'queue_deleting_empty_topics' ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $data['completed'], 2 ) ) : 100 );
    }
}