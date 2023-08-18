<?php
/**
 * @brief		solvedNotifications Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	forums
 * @since		15 Jul 2022
 */

namespace IPS\forums\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * solvedNotifications Task
 */
class _solvedNotifications extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		if ( ! \IPS\Settings::i()->forums_solved_topic_reengage )
		{
			return NULL;
		}

		/* Get forums with solved mode where the member can mark stuff as solved but have a hard line at 1 year ago as the oldest to ask about. We will need to watch this query. If it proves to be
		   super inefficient with sites with millions of members, we can consider an index on members_bitoptions2 or remove that sub-query and just check the author in the code before sending
		   which would suck if all 50 were for members with re-enagement emails off, but we will see */
		$where = [
			[ \IPS\Db::i()->in( 'approved', array( -2, -3 ), TRUE ) ],
			[ 'posts > 1 and start_date > ? and start_date < ? and solved_reminder_sent=0 and topic_answered_pid=0', ( time() - ( 365 * 86400) ), ( time() - ( (int) \IPS\Settings::i()->forums_solved_topic_reengage * 86400 ) ) ],
			[ \IPS\Db::i()->in( 'forum_id', iterator_to_array( \IPS\Db::i()->select( 'id', 'forums_forums', '(' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers_member' ) . ') AND (' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers_moderator' ) . ')' ) ) ) ],
			[ 'starter_id NOT IN (?)', \IPS\Db::i()->select( 'member_id', 'core_members', \IPS\Db::i()->bitwiseWhere( \IPS\Member::$bitOptions['members_bitoptions'], 'no_solved_reenage' ) ) ]
		];

		$topics = [];
		foreach( \IPS\Db::i()->select( '*', 'forums_topics', $where, 'start_date ASC', [ 0, 50 ] ) as $row )
		{
			$topics[ $row['tid'] ] = $row;
		}

		$starters = [];
		if ( \count( $topics ) )
		{
			/* Get a count of how many reminders have been sent already */
			foreach( \IPS\Db::i()->select( 'starter_id, COUNT(*) as count', 'forums_topics', [
				[ 'solved_reminder_sent > ?', time() - ( 86400 * 7 ) ]
			], NULL, NULL, 'starter_id' ) as $row ) {
				$starters[ $row['starter_id'] ] = $row['count'];
			}

			foreach( $topics as $row )
			{
				if ( ! isset( $starters[ $row['starter_id'] ] ) )
				{
					$starters[ $row['starter_id'] ] = 0;
				}

				if ( $starters[ $row['starter_id'] ] >= 5 )
				{
					/*
					 	We don't want to send this one as the starter has had too many sent, so reset time to 5 days ago so a task can check again on this one in a few days
					 	There is hardcoded to 5 max a week. We could consider a setting but I feel that 5 max per week isn't overwhelming but is enough to prompt re-engagement
					*/
					\IPS\Db::i()->update( 'forums_topics', [ 'solved_reminder_sent' => time() - ( 86400 * 5 ) ], ['tid=?', $row['tid']] );
				}
				else
				{
					/* Avoid race conditions and make sure its updated even if constructFromData fails */
					\IPS\Db::i()->update( 'forums_topics', [ 'solved_reminder_sent' => time() ], ['tid=?', $row['tid']] );

					/* Increment so the next foreach will count it correctly */
					$starters[ $row['starter_id'] ]++;

					try
					{
						$topic = \IPS\forums\Topic::constructFromData( $row );

						/* Don't send for converted live topics */
						if ( $topic->getLiveTopic() )
						{
							continue;
						}
						
						if ( $topic->canView( $topic->author() ) )
						{
							$firstPost = $topic->comments( 1, 0, 'date', 'asc' );
							$replies = $topic->comments( 3, 0, 'date', 'asc', NULL, NULL, NULL, array( 'author_id !=?', $topic->author()->member_id  ) );

							if( !count( $replies ) )
							{
								continue;
							}

							\IPS\Email::buildFromTemplate( 'core', 'solved_reengagement', array($topic, $firstPost, $replies), \IPS\Email::TYPE_TRANSACTIONAL )->send( $topic->author() );
						}
					}
					catch ( \Exception $e )
					{
					}
				}
			}
		}

		return NULL;
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}