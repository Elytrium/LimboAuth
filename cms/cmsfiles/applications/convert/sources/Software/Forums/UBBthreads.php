<?php
/**
 * @brief		Converter UBBthreads Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		IPS Social Suite
 * @subpackage	convert
 * @since		21 Jan 2015
 * @version		
 */

namespace IPS\convert\Software\Forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * UBBThreads Forums Converter
 */
class _UBBthreads extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "UBBthreads";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "ubbthreads";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertForumsForums'             => array(
				'table'                             => 'CATEGORIES',
				'where'                             => NULL,
				'extra_steps'                       => array( 'convertForumsForumsFollowers' )
			),
			'convertForumsForumsFollowers'   => array(
				'table'                             => 'WATCH_LISTS',
				'where'                             => array( 'WATCH_TYPE=?', 'f' )
			),
			'convertForumsTopics'             => array(
				'table'                             => 'TOPICS',
				'where'                             => NULL,
				'extra_steps'                       => array( 'convertForumsTopicsRatings', 'convertForumsTopicsFollowers' )
			),
			'convertForumsTopicsRatings'     => array(
				'table'                             => 'RATINGS',
				'where'                             => array( 'RATING_TYPE=?', 't' )
			),
			'convertForumsTopicsFollowers'   => array(
				'table'                             => 'WATCH_LISTS',
				'where'                             => array( 'WATCH_TYPE=?', 't' )
			),
			'convertForumsPosts'              => array(
				'table'                             => 'POSTS',
				'where'                             => NULL
			),
			'convertAttachments'               => array(
				'table'                             => 'FILES',
				'where'                             => array( 'POST_ID>?', 0 )
			)
		);
	}

	/**
	 * Allows software to add additional menu row options
	 *
	 * @return	array
	 */
	public function extraMenuRows()
	{
		$rows = array();
		$count = $this->countRows( static::canConvert()['convertForumsTopicsRatings']['table'], static::canConvert()['convertForumsTopicsRatings']['where'] );

		if( $count )
		{
			$rows['convertForumsTopicsRatings'] = array(
				'step_method'		=> 'convertForumsTopicsRatings',
				'step_title'		=> 'convert_ratings',
				'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'core_ratings' ),
				'source_rows'		=> $count,
				'per_cycle'			=> 200,
				'dependencies'		=> array( 'convertForumsTopics' ),
				'link_type'			=> 'topic_ratings',
			);
		}

		$count = $this->countRows( static::canConvert()['convertForumsTopicsFollowers']['table'], static::canConvert()['convertForumsTopicsFollowers']['where'] );

		if( $count )
		{
			$rows['convertForumsTopicsFollowers'] = array(
				'step_method'		=> 'convertForumsTopicsFollowers',
				'step_title'		=> 'convert_follows',
				'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'core_follow' ),
				'source_rows'		=> $count,
				'per_cycle'			=> 200,
				'dependencies'		=> array( 'convertForumsTopics' ),
				'link_type'			=> 'core_follows',
			);
		}

		return $rows;
	}

	/**
	 * Count Source Rows for a specific step
	 *
	 * @param	string		$table		The table containing the rows to count.
	 * @param	array|NULL	$where		WHERE clause to only count specific rows, or NULL to count all.
	 * @param	bool		$recache	Skip cache and pull directly (updating cache)
	 * @return	integer
	 * @throws	\IPS\convert\Exception
	 */
	public function countRows( $table, $where=NULL, $recache=FALSE )
	{
		switch ( $table )
		{
			case 'CATEGORIES':
				return parent::countRows( 'CATEGORIES' ) + parent::countRows( 'FORUMS' );
		}

		return parent::countRows( $table, $where, $recache );
	}

	/**
	 * Requires Parent
	 *
	 * @return	boolean
	 */
	public static function requiresParent()
	{
		return TRUE;
	}

	/**
	 * Possible Parent Conversions
	 *
	 * @return	array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'ubbthreads' ) );
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertAttachments'
		);
	}

	/**
	 * Get More Information
	 *
	 * @param	string	$method	Conversion method
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();

		switch( $method )
		{
			case 'convertAttachments':
				$return['convertAttachments'] = array(
						'attach_location'   => array(
						'field_class'       => 'IPS\\Helpers\\Form\\Text',
						'field_default'	    => NULL,
						'field_required'    => TRUE,
						'field_extra'       => array(),
						'field_hint'        => \IPS\Member::loggedIn()->language()->addToStack('convert_ubb_attach_path'),
					),
				);
				break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}
	
	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		/* Content Rebuilds */
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\forums\Forum', 'count' => 0 ), 4, array( 'class' ) );
		\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'link' => 'forums_posts', 'class' => 'IPS\forums\Topic\Post' ), 2, array( 'app', 'link', 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\forums\Topic' ), 3, array( 'class' ) );
		\IPS\Task::queue( 'convert', 'RebuildFirstPostIds', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );
		\IPS\Task::queue( 'convert', 'DeleteEmptyTopics', array( 'app' => $this->app->app_id ), 5, array( 'app' ) );

		return array( "f_forum_last_post_data", "f_rebuild_posts", "f_recounting_forums", "f_recounting_topics" );
	}
	
	/**
	 * Fix post data
	 *
	 * @param 	string		$post	raw post data
	 * @return 	string		Parsed post data
	 */
	public static function fixPostData( $post )
	{
		return \IPS\convert\Software\Core\UBBthreads::fixPostData( $post );
	}

	/**
	 * Convert forums
	 *
	 * @return	void
	 */
	public function convertForumsForums()
	{
		$libraryClass = $this->getLibrary();

		/**
		 * Categories in UBB are abstracted. So, before we actually convert any real "forums", we will want to convert
		 * the categories they are contained in.
		 */
		foreach ( $this->db->select( '*', 'CATEGORIES' ) as $row )
		{
			$libraryClass->convertForumsForum( array(
				'id'            => 10000 + $row['CATEGORY_ID'],
				'name'          => $row['CATEGORY_TITLE'],
				'description'   => $row['CATEGORY_DESCRIPTION'],
				'position'      => $row['CATEGORY_SORT_ORDER'],
				'sub_can_post'  => 0,
				'parent_id'		=> -1
			) );
		}

		/* Here is where we actually convert the real forums */
		foreach( $this->db->select( '*', 'FORUMS' ) AS $row )
		{
			$libraryClass->convertForumsForum( array(
				'id'					=> $row['FORUM_ID'],
				'name'					=> $row['FORUM_TITLE'],
				'description'			=> $row['FORUM_DESCRIPTION'],
				'topics'				=> $row['FORUM_TOPICS'],
				'posts'					=> $row['FORUM_POSTS'],
				'last_post'				=> \IPS\DateTime::create()->setTimestamp( $row['FORUM_LAST_POST_TIME'] ),
				'last_poster_id'		=> $row['FORUM_LAST_POSTER_ID'],
				'last_poster_name'		=> $row['FORUM_LAST_POSTER_NAME'],
				'parent_id'				=> $row['FORUM_PARENT_ID'] ?: ( 10000 + $row['CATEGORY_ID'] ),
				'position'				=> $row['FORUM_SORT_ORDER'],
				'last_title'			=> $row['FORUM_LAST_POST_SUBJECT'],
				'allow_poll'            => $row['FORUM_ALLOW_POLLS']
			) );
		}

		throw new \IPS\convert\Software\Exception;
	}

	/**
	 * Convert forum followers
	 *
	 * @return	void
	 */
	public function convertForumsForumsFollowers()
	{
		$libraryClass = $this->getLibrary();

		foreach ( $this->fetch( 'WATCH_LISTS', 'WATCH_ID', array( 'WATCH_TYPE=?', 'f' ) ) as $row )
		{
			$libraryClass->convertFollow( array(
				'follow_app'            => 'forums',
				'follow_area'           => 'forum',
				'follow_rel_id'         => $row['WATCH_ID'],
				'follow_rel_id_type'    => 'forums_forums',
				'follow_member_id'      => $row['USER_ID'],
				'follow_notify_freq'    => $row['WATCH_NOTIFY_IMMEDIATE'] ? 'immediate' : 'none',
			) );
		}
	}

	/**
	 * Convert topics
	 *
	 * @return	void
	 */
	public function convertForumsTopics()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'TOPIC_ID' );

		foreach ( $this->fetch( 'TOPICS', 'TOPIC_ID' ) as $row )
		{
			/* Poll */
			$poll = NULL;
			if ( $row['TOPIC_HAS_POLL'] > 0 )
			{
				try
				{
					$pollData = $this->db->select( '*', 'POLL_DATA', array( "POST_ID=?", $row['POST_ID'] ) )->first();

					$choices    = array();
					$index      = 1;
					foreach( $this->db->select( '*', 'POLL_OPTIONS', array( 'POLL_ID=?', $pollData['POLL_ID'] ), 'OPTION_ID ASC' ) AS $choice )
					{
						$choices[ $index ] = strip_tags( $choice['CHOICE_BODY'] );  // Unlike forum titles, I don't believe we allow HTML in poll choices
						$index++;
					}

					/* Reset Index */
					$index          = 1;
					$votes          = array();
					$rawVotes = iterator_to_array( $this->db->select( '*', 'POLL_VOTES', array( 'POLL_ID=?', $pollData['POLL_ID'] ) ) );
					foreach( $rawVotes AS $vote )
					{
						$votes[ $index ] = $vote['OPTION_ID'];
						$index++;
					}

					$poll = array();
					$poll['poll_data'] = array(
						'pid'               => $pollData['POLL_ID'],
						'choices'           => array( 1 => array(
							'question'          => strip_tags( $pollData['POLL_BODY'] ),
							'multi'             => ( $pollData['POLL_TYPE'] != 'one' ),
							'choice'            => $choices,
							'votes'             => $votes
						) ),
						'poll_question'     => strip_tags( $pollData['POLL_BODY'] ),
						'start_date'        => \IPS\DateTime::create()->setTimestamp( $row['POLL_START_TIME'] ),
						'starter_id'        => $row['USER_ID'],
					);

					$poll['vote_data'] = array();
					$ourVotes = array();
					foreach( $rawVotes AS $vote )
					{
						/* "Votes need a member account", apparently, so we will probably lose guest votes </sigh> */
						if ( !isset( $ourVotes[ $vote['VOTES_USER_ID_IP'] ] ) )
						{
							$ourVotes[ $vote['VOTES_USER_ID_IP'] ] = array( 'votes' => array() );
						}

						$ourVotes[ $vote['uid'] ]['votes'][]    = $vote['OPTION_ID'];
						$ourVotes[ $vote['uid'] ]['member_id']  = $vote['VOTES_USER_ID_IP'];
					}

					/* Now we need to re-wrap it all for storage */
					foreach( $ourVotes AS $member_id => $vote )
					{
						$poll['vote_data'][ $member_id ] = array(
							'vote_date'         => $vote['vote_date'],
							'member_id'         => $vote['member_id'],
							'member_choices'    => array( 1 => $vote['votes'] ),
						);
					}
				}
				catch( \UnderflowException $e ) {} # if the poll is missing, don't bother
			}

			$libraryClass->convertForumsTopic( array(
				'tid'               => $row['TOPIC_ID'],
				'title'             => mb_substr( $row['TOPIC_SUBJECT'], 0, 250 ),
				'forum_id'          => $row['FORUM_ID'],
				'state'             => ( $row['TOPIC_STATUS'] == 'C' ) ? 'closed' : 'open',
				'posts'             => $row['TOPIC_REPLIES'],
				'starter_id'        => $row['USER_ID'],
				'start_date'        => \IPS\DateTime::create()->setTimestamp( $row['TOPIC_CREATED_TIME'] ),
				'last_poster_id'    => $row['TOPIC_LAST_POSTER_ID'],
				'last_post'         => \IPS\DateTime::create()->setTimestamp( $row['TOPIC_LAST_REPLY_TIME'] ),
				'last_poster_name'  => $row['TOPIC_LAST_POSTER_NAME'],
				'views'             => $row['TOPIC_VIEWS'],
				'approved'          => $row['TOPIC_IS_APPROVED'],
				'pinned'            => $row['TOPIC_IS_STICKY'],
				'poll_state'        => $poll
			) );

			$libraryClass->setLastKeyValue( $row['TOPIC_ID'] );
		}
	}

	/**
	 * Convert topic ratings
	 *
	 * @return	void
	 */
	public function convertForumsTopicsRatings()
	{
		$libraryClass = $this->getLibrary();

		foreach ( $this->db->select( '*', 'RATINGS', array( 'RATING_TYPE=?', 't' ) ) as $row )
		{
			$libraryClass->convertRating( array(
				'class'     => 'IPS\\forums\\Topic',
				'item_link' => 'forums_topics',
				'item_id'   => $row['RATING_TARGET'],
				'rating'    => $row['RATING_VALUE'],
				'member'    => $row['RATING_RATER']
			) );
		}
	}

	/**
	 * Convert topic followers
	 *
	 * @return	void
	 */
	public function convertForumsTopicsFollowers()
	{
		$libraryClass = $this->getLibrary();

		foreach ( $this->fetch( 'WATCH_LISTS', 'WATCH_ID', array( 'WATCH_TYPE=?', 't' ) ) as $row )
		{
			$libraryClass->convertFollow( array(
				'follow_app'            => 'forums',
				'follow_area'           => 'topic',
				'follow_rel_id'         => $row['WATCH_ID'],
				'follow_rel_id_type'    => 'forums_topics',
				'follow_member_id'      => $row['USER_ID'],
				'follow_notify_freq'    => $row['WATCH_NOTIFY_IMMEDIATE'] ? 'immediate' : 'none',
			) );
		}
	}

	/**
	 * Convert posts
	 *
	 * @return	void
	 */
	public function convertForumsPosts()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'POST_ID' );

		foreach ( $this->fetch( 'POSTS', 'POST_ID' ) as $row )
		{
			$libraryClass->convertForumsPost( array(
				'pid'           => $row['POST_ID'],
				'topic_id'      => $row['TOPIC_ID'],
				'post'          => $row['POST_DEFAULT_BODY'],
				'append_edit'   => ( (int) $row['POST_LAST_EDITED_TIME'] > 0 ),
				'edit_time'     => $row['POST_LAST_EDITED_TIME'],
				'edit_name'     => $row['POST_LAST_EDITED_BY'],
				'author_id'     => $row['USER_ID'],
				'author_name'   => $row['POST_POSTER_NAME'],
				'ip_address'    => $row['POST_POSTER_IP'],
				'post_date'     => \IPS\DateTime::create()->setTimestamp( $row['POST_POSTED_TIME'] ),
				'queued'        => ( ! $row['POST_IS_APPROVED'] ),
				'new_topic'     => $row['POST_IS_TOPIC'],
			) );

			$libraryClass->setLastKeyValue( $row['POST_ID'] );
		}
	}

	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'FILE_ID' );

		/* Only load files with a post id */
		foreach( $this->fetch( 'FILES', 'FILE_ID', array( 'POST_ID>?', 0 ) ) AS $row )
		{
			$map = array();

			/* Try to load the topic ID */
			try
			{
				$topicId = $this->db->select( 'TOPIC_ID', 'POSTS', array( "POST_ID=?", $row['POST_ID'] ) )->first();

				$map['id1'] = $topicId;
				$map['id2'] = $row['POST_ID'];
			}
			catch( \UnderflowException $e ) {}

			$info = array(
				'attach_id'			=> $row['FILE_ID'],
				'attach_file'		=> $row['FILE_NAME'],
				'attach_date'		=> \IPS\DateTime::create()->setTimestamp( $row['FILE_ADD_TIME'] ),
				'attach_member_id'	=> $row['USER_ID'],
				'attach_hits'		=> $row['FILE_DOWNLOADS'],
				'attach_ext'		=> pathinfo( $row['FILE_NAME'], \PATHINFO_EXTENSION ),
				'attach_filesize'	=> $row['FILE_SIZE'],  // Note: Apparently not always readily available?
			);

			$libraryClass->convertAttachment( $info, $map, rtrim( $this->app->_session['more_info']['convertAttachments']['attach_location'], '/' ) . '/' . $row['FILE_NAME'] );
			$libraryClass->setLastKeyValue( $row['FILE_ID'] );
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		$url = \IPS\Request::i()->url();

		/* Make sure it's a UBBThreads URL */
		if( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'ubbthreads.php' ) === FALSE )
		{
			return NULL;
		}

		//ubbthreads.php?ubb=showflat&Number=?
		if( isset( \IPS\Request::i()->Number ) AND isset( \IPS\Request::i()->ubb ) )
		{
			try
			{
				$data = (string) $this->app->getLink( (int) \IPS\Request::i()->Number, array( 'posts', 'forums_posts' ) );
				$post = \IPS\forums\Topic\Post::load( $data );

				if( $post->item()->canView() )
				{
					return $post->url();
				}
			}
			catch ( \OutOfRangeException $e )
			{
				return NULL;
			}
		}
		elseif( preg_match( '#/ubbthreads.php/topics/([0-9]+)($|/)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			try
			{
				try
				{
					$data = (string) $this->app->getLink( (int) $matches[1], array( 'topics', 'forums_topics' ) );
				}
				catch( \OutOfRangeException $e )
				{
					$data = (string) $this->app->getLink( (int) $matches[1], array( 'topics', 'forums_topics' ), FALSE, TRUE );
				}
				$item = \IPS\forums\Topic::load( $data );

				if( $item->canView() )
				{
					return $item->url();
				}
			}
			catch( \Exception $e )
			{
				try
				{
					$data = (string) $this->app->getLink( (int) $matches[1], array( 'posts', 'forums_posts' ) );
					$post = \IPS\forums\Topic\Post::load( $data );

					if( $post->item()->canView() )
					{
						return $post->url();
					}
				}
				catch ( \OutOfRangeException $e ) { }

				return NULL;
			}
		}
		elseif( preg_match( '#/ubbthreads.php/forums/([0-9]+)($|/)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			try
			{
				$data = (string) $this->app->getLink( (int) $matches[1], array( 'forums', 'forums_forums' ) );
				$item = \IPS\forums\Forum::load( $data );

				if( $item->can( 'view' ) )
				{
					return $item->url();
				}
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}
		elseif( preg_match( '#ubbthreads.php/ubb/showflat/Number/([0-9]+)($|/)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			try
			{
				$data = (string) $this->app->getLink( (int) $matches[1], array( 'posts', 'forums_posts' ) );
				$post = \IPS\forums\Topic\Post::load( $data );

				if( $post->item()->canView() )
				{
					return $post->url();
				}
			}
			catch ( \OutOfRangeException $e )
			{
				return NULL;
			}
		}

		return NULL;
	}
}