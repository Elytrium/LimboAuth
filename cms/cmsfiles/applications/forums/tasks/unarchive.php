<?php
/**
 * @brief		unarchive Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		07 Sep 2016
 */

namespace IPS\forums\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * unarchive Task
 */
class _unarchive extends \IPS\Task
{
	/**
	 * @brief	Number of items to process per cycle
	 */
	const PROCESS_PER_BATCH = 250;

	/**
	 * @brief	Storage database
	 */
	protected $storage;

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
		/* If we're disabled, disable the task, but only if we don't have things that need restoring */
		$count = \IPS\Db::i()->select( 'COUNT(*)', 'forums_topics', array( 'topic_archive_status=?', \IPS\forums\Topic::ARCHIVE_RESTORE ) )->first();

		if ( !\IPS\Settings::i()->archive_on AND !$count )
		{
			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 0 ), array( '`key`=?', 'unarchive' ) );

			return NULL;
		}

		/* Connect to the remote DB if needed */
		if ( \IPS\CIC2 )
		{
			$this->storage = \IPS\Cicloud\getForumArchiveDb();
		}
		else
		{
			$this->storage = !\IPS\Settings::i()->archive_remote_sql_host ? \IPS\Db::i() : \IPS\Db::i( 'archive', array(
				'sql_host'		=> \IPS\Settings::i()->archive_remote_sql_host,
				'sql_user'		=> \IPS\Settings::i()->archive_remote_sql_user,
				'sql_pass'		=> \IPS\Settings::i()->archive_remote_sql_pass,
				'sql_database'	=> \IPS\Settings::i()->archive_remote_sql_database,
				'sql_port'		=> \IPS\Settings::i()->archive_sql_port,
				'sql_socket'	=> \IPS\Settings::i()->archive_sql_socket,
				'sql_tbl_prefix'=> \IPS\Settings::i()->archive_sql_tbl_prefix,
				'sql_utf8mb4'	=> isset( \IPS\Settings::i()->sql_utf8mb4 ) ? \IPS\Settings::i()->sql_utf8mb4 : FALSE
			) );
		}

		$this->runUntilTimeout( function(){
			/* Init */
			$postsProcessed = 0;
			
			/* Loop topics */
			try
			{
				$topic = \IPS\forums\Topic::constructFromData( \IPS\Db::i()->select( '*', 'forums_topics', array( 'topic_archive_status=?', \IPS\forums\Topic::ARCHIVE_RESTORE ), 'tid ASC', 1 )->first() );
			}
			catch( \UnderflowException $e )
			{
				return FALSE;
			}

			/* Do as many posts as we can */
			$offset = \IPS\Db::i()->select( 'MAX(pid)', 'forums_posts', array( 'topic_id=?', $topic->tid ) )->first();
			foreach ( $this->storage->select( '*', 'forums_archive_posts', array( 'archive_id>? AND archive_topic_id=?', \intval( $offset ), $topic->tid ), 'archive_id ASC', static::PROCESS_PER_BATCH - $postsProcessed ) as $post )
			{						
				$unarchivedPost = $this->unarchive( $post );
				$postsProcessed++;
				if ( $postsProcessed >= static::PROCESS_PER_BATCH )
				{
					return TRUE;
				}
			}
								
			/* Set that it's done */
			\IPS\Content\Search\Index::i()->indexSingleItem( $topic );
			\IPS\Db::i()->update( 'forums_topics', array( 'topic_archive_status' => \IPS\forums\Topic::ARCHIVE_EXCLUDE ), array( 'tid=?', $topic->tid ) );
			$this->storage->delete( 'forums_archive_posts', array( 'archive_topic_id=?', $topic->tid ) );

			return TRUE;
		});
	}

	/**
	 * Unarchive
	 *
	 * @param	array	$post	The post to be unarchived
	 * @return	\IPS\forums\Topic\Post
	 */
	protected function unarchive( $post )
	{
		/* Translate the fields */
		$row = array(
			'pid'				=> \intval( $post['archive_id'] ),
			'author_id'			=> \intval( $post['archive_author_id'] ),
			'author_name'		=> $post['archive_author_name'],
			'ip_address'		=> $post['archive_ip_address'],
			'post_date'			=> \intval( $post['archive_content_date'] ),
			'post'				=> $post['archive_content'],
			'queued'			=> $post['archive_queued'],
			'topic_id'			=> \intval( $post['archive_topic_id'] ),
			'new_topic'			=> \intval( $post['archive_is_first'] ),
			'post_bwoptions'	=> $post['archive_bwoptions'],
			'post_key'			=> $post['archive_attach_key'],
			'post_htmlstate'	=> $post['archive_html_mode'],
			'append_edit'		=> $post['archive_show_edited_by'],
			'edit_time'			=> $post['archive_edit_time'],
			'edit_name'			=> $post['archive_edit_name'],
			'post_edit_reason'	=> $post['archive_edit_reason'],
			'post_field_int'	=> $post['archive_field_int'],
		);
		
		/* Insert */
		\IPS\Db::i()->replace( 'forums_posts', $row );
		
		/* Update reports and promotes tables */
		\IPS\Db::i()->update( 'core_rc_index', array( 'class' => 'IPS\forums\Topic\Post' ), array( 'class=? AND content_id=?', 'IPS\forums\Topic\ArchivedPost', $post['archive_id'] ) );
		\IPS\Db::i()->update( 'core_social_promote', array( 'promote_class' => 'IPS\forums\Topic\Post' ), array( "promote_class=? AND promote_class_id=?", 'IPS\forums\Topic\ArchivedPost', $post['archive_id'] ) );

		return \IPS\forums\Topic\Post::constructFromData( $row );
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