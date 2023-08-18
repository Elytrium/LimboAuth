<?php

/**
 * @brief		Converter WoltLab Forum Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		01 Apr 2020
 */

namespace IPS\convert\Software\Forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Woltlab Forums Converter
 */
class _Woltlab extends \IPS\convert\Software
{
	/**
	 * @brief	The WBB table prefix can change depending on the number of installs
	 */
	public static $installId = 1;
	
	/**
	 * Software Name
	 *
	 * @return    string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "WoltLab Suite Forum";
	}

	/**
	 * Software Key
	 *
	 * @return    string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "woltlab";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertForumsForums'		=> array(
				'table'						=> 'wbb' . static::$installId . '_board',
				'where'						=> NULL
			),
			'convertForumsTopics'		=> array(
				'table'						=> 'wbb' . static::$installId . '_thread',
				'where'						=> array( 'movedThreadID IS NULL' )
			),
			'convertForumsPosts'		=> array(
				'table'						=> 'wbb' . static::$installId . '_post',
				'where'						=> NULL
			),
			'convertAttachments'		=> array(
				'table'						=> 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_attachment',
				'where'						=> NULL
			)
		);
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
		return array( 'core' => array( 'woltlab' ) );
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

		/* Rebuild Leaderboard */
		\IPS\Task::queue( 'core', 'RebuildReputationLeaderboard', array(), 4 );
		\IPS\Db::i()->delete('core_reputation_leaderboard_history');

		/* Caches */
		\IPS\Task::queue( 'convert', 'RebuildTagCache', array( 'app' => $this->app->app_id, 'link' => 'forums_topics', 'class' => 'IPS\forums\Topic' ), 3, array( 'app', 'link', 'class' ) );

		return array( "f_forum_last_post_data", "f_rebuild_posts", "f_recounting_forums", "f_recounting_topics", "f_topic_tags_recount" );
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
		switch( $table )
		{
			case 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_attachment':
				try
				{
					$postObjects = iterator_to_array( $this->db->select( 'objectTypeID', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_object_type', "objectType='com.woltlab.wbb.post'" ) );
					return $this->db->select( 'count(attachmentID)', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_attachment', array( $this->db->in( 'objectTypeID', $postObjects ) ) )->first();
				}
				catch( \UnderflowException $e )
				{
					return 0;
				}
				catch( \Exception $e )
				{
					throw new \IPS\convert\Exception( sprintf( \IPS\Member::loggedIn()->language()->get( 'could_not_count_rows' ), $table ) );
				}
				break;

			default:
				return parent::countRows( $table, $where, $recache );
				break;
		}
	}

	/**
	 * Fix Post Data
	 *
	 * @param	string	$post	Post
	 * @return	string	Fixed Post
	 */
	public static function fixPostData( $post )
	{
		return \IPS\convert\Software\Core\Woltlab::fixPostData( $post );
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertAttachments',
			'convertForumsPosts'
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
			case 'convertForumsPosts':
				/* Get our reactions to let the admin map them */
				$options		= array();
				$descriptions	= array();
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_reactions' ), 'IPS\Content\Reaction' ) AS $reaction )
				{
					$options[ $reaction->id ]		= $reaction->_icon->url;
					$descriptions[ $reaction->id ]	= \IPS\Member::loggedIn()->language()->addToStack('reaction_title_' . $reaction->id ) . '<br>' . $reaction->_description;
				}

				$return['convertForumsPosts'] = array(
					'rep_positive'	=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'		=> NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array( 'parse' => 'image', 'options' => $options, 'descriptions' => $descriptions, 'gridspan' => 2 ),
						'field_hint'		=> NULL,
						'field_validation'	=> NULL,
					),
					'rep_negative'	=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'		=> NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array( 'parse' => 'image', 'options' => $options, 'descriptions' => $descriptions, 'gridspan' => 2 ),
						'field_hint'		=> NULL,
						'field_validation'	=> NULL,
					),
				);
				break;

			case 'convertAttachments':
				$return['convertAttachments'] = array(
					'file_location' => array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Text',
						'field_default'			=> NULL,
						'field_required'		=> TRUE,
						'field_extra'			=> array(),
						'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_woltlab_attach_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					)
				);
				break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}

	/**
	 * Convert forums
	 *
	 * @return	void
	 */
	public function convertForumsForums()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'boardID' );

		$boardObjects = iterator_to_array( $this->db->select( 'objectTypeID', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_object_type', "objectType='com.woltlab.wbb.board'" ) );

		foreach( $this->fetch( 'wbb' . static::$installId . '_board', 'boardID' ) AS $row )
		{
			$info = array(
				'id'					=> $row['boardID'],
				'name'					=> $row['title'],
				'description'			=> $row['description'],
				'topics'				=> $row['threads'],
				'posts'					=> $row['posts'],
				'parent_id'				=> !$row['parentID'] ? -1 : $row['parentID'],
				'position'				=> $row['position'],
				'redirect_url'			=> $row['externalURL'],
				'redirect_hits'			=> $row['clicks'],
				'inc_postcount'			=> $row['countUserPosts'],
				'sub_can_post'			=> 1,
			);

			$libraryClass->convertForumsForum( $info );

			/* Followers */
			foreach( $this->db->select( 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_user_object_watch.*', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_user_object_watch', array( "objectID=? AND " . $this->db->in('objectTypeID', $boardObjects ), $row['boardID'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'forums',
					'follow_area'			=> 'forum',
					'follow_rel_id'			=> $row['boardID'],
					'follow_rel_id_type'	=> 'forums_forums',
					'follow_member_id'		=> $follow['userID'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> $follow['notification'],
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> 'immediate',
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1,
				) );
			}

			$libraryClass->setLastKeyValue( $row['boardID'] );
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
		$libraryClass::setKey( 'threadID' );

		$threadObjects = iterator_to_array( $this->db->select( 'objectTypeID', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_object_type', "objectType='com.woltlab.wbb.thread'" ) );

		foreach( $this->fetch( 'wbb' . static::$installId . '_thread', 'threadID', array( 'movedThreadID IS NULL' ) ) AS $row )
		{
			$info = array(
				'tid'					=> $row['threadID'],
				'title'					=> $row['topic'],
				'forum_id'				=> $row['boardID'],
				'state'					=> ( $row['isClosed'] == 1 ) ? 'closed' : 'open',
				'starter_id'			=> $row['userID'],
				'start_date'			=> $row['time'],
				'last_poster_id'		=> $row['lastPosterID'],
				'last_post'				=> $row['lastPostID'],
				'starter_name'			=> $row['username'],
				'last_poster_name'		=> $row['lastPoster'],
				'views'					=> $row['views'],
				'approved'				=> $row['isDeleted'] ? 0 : 1,
				'pinned'				=> ( $row['isSticky'] OR $row['isAnnouncement'] ) ? 1 : 0,
			);

			$libraryClass->convertForumsTopic( $info );

			/* Tags */
			$tags = $this->db->select( 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_tag_to_object.tagID', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_tag_to_object', array( "objectID=? AND " . $this->db->in('objectTypeID', $threadObjects ), $row['threadID'] ) );
			$tagText = $this->db->select( 'name', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_tag', array( $this->db->in( 'tagID', iterator_to_array( $tags ) ) ) );

			foreach( $tagText as $text )
			{
				$libraryClass->convertTag( array(
					'tag_meta_app' 			=> 'forums',
					'tag_meta_area' 		=> 'forums',
					'tag_meta_parent_id' 	=> $row['boardID'],
					'tag_meta_id' 			=> $row['threadID'],
					'tag_text' 				=> $text,
					'tag_member_id' 		=> $row['userID'],
					'tag_added'             => $info['start_date']
				) );
			}

			/* Follows */
			foreach( $this->db->select( 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_user_object_watch.*', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_user_object_watch', array( "objectID=? AND " . $this->db->in('objectTypeID', $threadObjects ), $row['threadID'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'forums',
					'follow_area'			=> 'topic',
					'follow_rel_id'			=> $row['threadID'],
					'follow_rel_id_type'	=> 'forums_topics',
					'follow_member_id'		=> $follow['userID'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> $follow['notification'],
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> 'immediate',
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1,
				) );
			}

			$libraryClass->setLastKeyValue( $row['threadID'] );
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
		$libraryClass::setKey( 'postID' );

		$postLikeObjects = iterator_to_array( $this->db->select( 'objectTypeID', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_object_type', "objectType='com.woltlab.wbb.likeablePost'" ) );

		foreach( $this->fetch( 'wbb' . static::$installId . '_post', 'postID' ) AS $row )
		{
			$info = array(
				'pid'				=> $row['postID'],
				'topic_id'			=> $row['threadID'],
				'post'				=> $row['message'],
				'edit_time'			=> $row['lastEditTime'],
				'author_id'			=> $row['userID'],
				'author_name'		=> $row['username'],
				'ip_address'		=> $row['ipAddress'],
				'post_date'			=> $row['time'],
				'queued'			=> ( $row['isDeleted'] OR $row['isClosed'] ) ? 1 : 0,
				'post_edit_reason'	=> $row['editReason'],
			);

			$libraryClass->convertForumsPost( $info );

			/* Reputation */
			foreach( $this->db->select( '*', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_like', array( "objectID=? AND " . $this->db->in('objectTypeID', $postLikeObjects ), $row['postID'] ) ) AS $rep )
			{
				$reaction = ( $rep['likeValue'] > 0 ) ? $this->app->_session['more_info']['convertForumsPosts']['rep_positive'] : $this->app->_session['more_info']['convertForumsPosts']['rep_negative'];

				$libraryClass->convertReputation( array(
					'id'				=> $rep['likeID'],
					'app'				=> 'forums',
					'type'				=> 'pid',
					'type_id'			=> $row['postID'],
					'member_id'			=> $rep['userID'],
					'member_received'	=> $rep['objectUserID'],
					'rep_date'			=> $rep['time'],
					'reaction'			=> $reaction
				) );
			}

			$libraryClass->setLastKeyValue( $row['postID'] );
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
		$libraryClass::setKey( 'attachmentID' );

		$postObjects = iterator_to_array( $this->db->select( 'objectTypeID', 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_object_type', "objectType='com.woltlab.wbb.post'" ) );

		foreach( $this->fetch( 'wcf' . \IPS\convert\Software\Core\Woltlab::$installId . '_attachment', 'attachmentID', array( $this->db->in( 'objectTypeID', $postObjects ) ) ) AS $row )
		{
			try
			{
				$topicId = $this->db->select( 'threadID', 'wbb' . static::$installId . '_post', array( "postID=?", $row['objectID'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				/* Post is orphaned */
				$libraryClass->setLastKeyValue( $row['attachmentID'] );
				continue;
			}

			$map = array(
				'id1'	=> $topicId,
				'id2'	=> $row['objectID'],
			);

			$info = array(
				'attach_id'			=> $row['attachmentID'],
				'attach_file'		=> $row['filename'],
				'attach_date'		=> $row['uploadTime'],
				'attach_member_id'	=> $row['userID'],
				'attach_hits'		=> $row['downloads'],
				'attach_filesize'	=> $row['filesize'],
			);

			$attachId = $libraryClass->convertAttachment( $info, $map, rtrim( $this->app->_session['more_info']['convertAttachments']['file_location'], '/' ) . '/' . mb_substr( $row['fileHash'], 0, 2 ) . '/' . $row['attachmentID'] . '-' . $row['fileHash'] );

			/* Update Post if we can */
			try
			{
				if ( $attachId !== FALSE )
				{
					$pid = $this->app->getLink( $row['objectID'], 'forums_posts' );

					$post = \IPS\Db::i()->select( 'post', 'forums_posts', array( "pid=?", $pid ) )->first();

					if( \preg_match( '#\[attach]#i', $post ) )
					{
						$post = str_ireplace( '[attach]' . $row['attachmentID'] . '[/attach]', '[attachment=' . $attachId . ':name]', $post );
						\IPS\Db::i()->update( 'forums_posts', array( 'post' => $post ), array( "pid=?", $pid ) );
					}
				}
			}
			catch( \UnderflowException | \OutOfRangeException $e ) {}

			$libraryClass->setLastKeyValue( $row['attachmentID'] );
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

		if( preg_match( '#/thread/([0-9]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			if( !empty( \IPS\Request::i()->postID ) )
			{
				$class	= '\IPS\forums\Topic\Post';
				$types	= array( 'posts', 'forums_posts' );
				$oldId	= (int) \IPS\Request::i()->postID;
			}
			else
			{
				$class	= '\IPS\forums\Topic';
				$types	= array( 'topics', 'forums_topics' );
				$oldId	= (int) $matches[1];
			}
		}
		elseif( preg_match( '#/board/([0-9]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			$class	= '\IPS\forums\Forum';
			$types	= array( 'forums', 'forums_forums' );
			$oldId	= (int) $matches[1];
		}

		if( isset( $class ) )
		{
			try
			{
				try
				{
					$data = (string) $this->app->getLink( $oldId, $types );
				}
				catch( \OutOfRangeException $e )
				{
					$data = (string) $this->app->getLink( $oldId, $types, FALSE, TRUE );
				}
				$item = $class::load( $data );

				if( $item instanceof \IPS\Content )
				{
					if( $item->canView() )
					{
						return $item->url();
					}
				}
				elseif( $item instanceof \IPS\Node\Model )
				{
					if( $item->can( 'view' ) )
					{
						return $item->url();
					}
				}
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}

		return NULL;
	}
}