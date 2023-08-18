<?php

/**
 * @brief		Converter phpBB Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PhpBB Forums Converter
 */
class _Phpbb extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "phpBB (3.1.x/3.2.x/3.3.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "phpbb";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertForumsForums'	=> array(
				'table'		=> 'forums',
				'where'		=> NULL,
			),
			'convertForumsTopics'	=> array(
				'table'		=> 'topics',
				'where'		=> NULL
			),
			'convertForumsPosts'	=> array(
				'table'		=> 'posts',
				'where'		=> NULL
			),
			'convertAttachments'	=> array(
				'table'		=> 'attachments',
				'where'		=> NULL
			)
		);
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertForumsForums',
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
			case 'convertForumsForums':
				$return['convertForumsForums'] = array();
				$forums = $this->db->select( '*', 'forums' );
				foreach( $forums AS $forum )
				{
					if ( $forum['forum_password'] )
					{
						\IPS\Member::loggedIn()->language()->words["forum_password_{$forum['forum_id']}"] = \IPS\Member::loggedIn()->language()->addToStack( 'convert_forum_password', FALSE, array( 'sprintf' => array( $forum['forum_name'] ) ) );
						\IPS\Member::loggedIn()->language()->words["forum_password_{$forum['forum_id']}_desc"] = \IPS\Member::loggedIn()->language()->addToStack( 'convert_forum_password_desc' );
						
						$return['convertForumsForums']["forum_password_{$forum['forum_id']}"] = array(
							'field_class'		=> 'IPS\\Helpers\\Form\\Text',
							'field_default'		=> NULL,
							'field_required'	=> FALSE,
							'field_extra'		=> array(),
							'field_hint'		=> NULL,
						);
					}
				}
				break;
			case 'convertAttachments':
				$return['convertAttachments'] = array(
					'attach_location'	=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Text',
						'field_default'		=> NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array(),
						'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_phpbb_attach_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					),
				);
				break;
		}
		
		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
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
		return array( 'core' => array( 'phpbb' ) );
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
	 * @param 	string		$post	Raw post data
	 * @return 	string		Parsed post data
	 */
	public static function fixPostData( $post )
	{
		return \IPS\convert\Software\Core\Phpbb::fixPostData( $post );
	}
	
	/**
	 * Convert forums
	 *
	 * @return	void
	 */
	public function convertForumsForums()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'forum_id' );
		
		foreach( $this->fetch( 'forums', 'forum_id' ) AS $row )
		{
			$info = array(
				'id'				=> $row['forum_id'],
				'name'				=> $row['forum_name'],
				'description'		=> $row['forum_desc'],
				'topics'			=> isset( $row['forum_topics_approved'] ) ? $row['forum_topics_approved'] : $row['forum_topics'],
				'posts'				=> isset( $row['forum_posts_approved'] ) ? $row['forum_posts_approved'] : $row['forum_posts'],
				'last_post'			=> $row['forum_last_post_time'],
				'last_poster_id'	=> $row['forum_last_poster_id'],
				'last_poster_name'	=> $row['forum_last_poster_name'],
				'parent_id'			=> ( $row['parent_id'] != 0 ) ? $row['parent_id'] : -1,
				'conv_parent'		=> ( $row['parent_id'] != 0 ) ? $row['parent_id'] : -1,
				'position'			=> $row['left_id'],
				'password'			=> ( isset( $this->app->_session['more_info']['convertForumsForums']["forum_password_{$row['forum_id']}"] ) ) ? $this->app->_session['more_info']['convertForumsForums']["forum_password_{$row['forum_id']}"] : NULL,
				'last_title'		=> $row['forum_last_post_subject'],
				'queued_topics'		=> isset( $row['forum_topics_unapproved'] ) ? $row['forum_topics_unapproved'] : 0,
				'queued_posts'		=> isset( $row['forum_posts_unapproved'] ) ? $row['forum_topics_unapproved'] : 0,
				'sub_can_post'		=> ( $row['forum_type'] == 1 ) ? TRUE : FALSE
			);
			
			$libraryClass->convertForumsForum( $info );
			
			/* Follows */
			foreach( $this->db->select( '*', 'forums_watch', array( "forum_id=?", $row['forum_id'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'forums',
					'follow_area'			=> 'forum',
					'follow_rel_id'			=> $row['forum_id'],
					'follow_rel_id_type'	=> 'forums_forums',
					'follow_member_id'		=> $follow['user_id'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 1,
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> 'immediate',
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1,
				) );
			}
			
			$libraryClass->setLastKeyValue( $row['forum_id'] );
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
		
		$libraryClass::setKey( 'topic_id' );
		
		foreach( $this->fetch( 'topics', 'topic_id' ) AS $row )
		{
			$poll = NULL;
			$pollTitle = str_ireplace( array( '<t>', '</t>' ), '', $row['poll_title'] );

			if ( $pollTitle )
			{
				$poll = array();
				
				$choices	= array();
				$votes		= array();
				$index		= 1;
				$search		= array(); # make sure we actually assign the vote correctly
				foreach( $this->db->select( '*', 'poll_options', array( "topic_id=?", $row['topic_id'] ) ) AS $choice )
				{
					$choices[ $index ]	= str_ireplace( array( '<t>', '</t>' ), '', $choice['poll_option_text'] );
					$votes[ $index ]	= $choice['poll_option_total'];
					$search[ $index ]	= $choice['poll_option_id'];
					$index++;
				}
				
				$poll['poll_data'] = array(
					'pid'				=> $row['topic_id'],
					'choices'			=> array( 1 => array(
						'question'			=> $pollTitle,
						'multi'				=> ( $row['poll_max_options'] > 1 ) ? 1 : 0,
						'choice'			=> $choices,
						'votes'				=> $votes,
					) ),
					'poll_question'		=> $row['poll_title'],
					'start_date'		=> $row['poll_start'],
					'starter_id'		=> $row['topic_poster'],
					'votes'				=> array_sum( $votes ),
					'poll_view_voters'	=> 0,
				);
				
				$poll['vote_data']	= array();
				$ourVotes			= array();
				foreach( $this->db->select( '*', 'poll_votes', array( "topic_id=?", $row['topic_id'] ) ) AS $vote )
				{
					if ( !isset( $ourVotes[$vote['vote_user_id']] ) )
					{
						$ourVotes[$vote['vote_user_id']] = array( 'votes' => array() );
					}
					
					$ourVotes[ $vote['vote_user_id'] ]['votes'][]	= array_search( $vote['poll_option_id'], $search );
					$ourVotes[ $vote['vote_user_id'] ]['member_id']	= $vote['vote_user_id'];
				}
				
				foreach( $ourVotes AS $member_id => $vote )
				{
					$poll['vote_data'][ $member_id ] = array(
						'member_id'			=> $vote['member_id'],
						'member_choices'	=> array( 1 => $vote['votes'] ),
					);
				}
			}
			
			if ( isset( $row['topic_visibility'] ) )
			{
				$visibility = $row['topic_visibility'];
			}
			else
			{
				$visibility = $row['topic_approved'];
			}
			
			/* Global Topics */
			if ( !$row['forum_id'] )
			{
				try
				{
					$orphaned = $this->app->getLink( '__global__', 'forums_forums' );
				}
				catch( \OutOfRangeException $e )
				{
					/* Create a forum to store it in */
					$libraryClass->convertForumsForum( array(
						'id'			=> '__global__',
						'name'			=> 'Global Topics',
					) );
				}
				
				$row['forum_id'] = '__global__';
			}
			
			$info = array(
				'tid'				=> $row['topic_id'],
				'title'				=> $row['topic_title'],
				'forum_id'			=> $row['forum_id'],
				'state'				=> ( $row['topic_status'] == 0 ) ? 'open' : 'closed',
				'posts'				=> isset( $row['topic_posts_approved'] ) ? $row['topic_posts_approved'] : $row['topic_replies'],
				'starter_id'		=> $row['topic_poster'],
				'start_date'		=> $row['topic_time'],
				'last_poster_id'	=> $row['topic_last_poster_id'],
				'last_post'			=> $row['topic_last_post_time'],
				'starter_name'		=> $row['topic_first_poster_name'],
				'last_poster_name'	=> $row['topic_last_poster_name'],
				'poll_state'		=> $poll,
				'last_vote'			=> $row['poll_last_vote'],
				'views'				=> $row['topic_views'],
				'approved'			=> ( $visibility <> 1 ) ? -1 : 1,
				'pinned'			=> ( $row['topic_type'] == 0 ) ? 0 : 1,
				'topic_hiddenposts'	=> ( isset( $row['topic_posts_unapproved'] ) AND isset( $row['topic_posts_softdeleted'] ) ) ? $row['topic_posts_unapproved'] + $row['topic_posts_softdeleted'] : 0,
			);
			
			$libraryClass->convertForumsTopic( $info );
			
			/* Follows */
			foreach( $this->db->select( '*', 'topics_watch', array( "topic_id=?", $row['topic_id'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'forums',
					'follow_area'			=> 'topic',
					'follow_rel_id'			=> $row['topic_id'],
					'follow_rel_id_type'	=> 'forums_topics',
					'follow_member_id'		=> $follow['user_id'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 1,
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> 'immediate',
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1,
				) );
			}
			
			$libraryClass->setLastKeyValue( $row['topic_id'] );
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
		
		$libraryClass::setKey( 'post_id' );
		
		foreach( $this->fetch( 'posts', 'post_id' ) AS $row )
		{
			if ( isset( $row['post_visibility'] ) )
			{
				$visibility = $row['post_visibility'];
			}
			else
			{
				$visibility = $row['post_approved'];
			}

			$editName = null;
			if( $row['post_edit_time'] AND $row['post_edit_user'] )
			{
				try
				{
					$editName = $this->db->select( 'username', 'users', array( 'user_id=?', $row['post_edit_user'] ) )->first();
				}
				catch( \UnderflowException $e ) {}
			}
			
			$info = array(
				'pid'			=> $row['post_id'],
				'topic_id'		=> $row['topic_id'],
				'post'			=> \IPS\convert\Software\Core\Phpbb::stripUid( $row['post_text'], $row['bbcode_uid'] ),
				'append_edit'	=> ( $row['post_edit_user'] ) ? 1 : 0,
				'edit_time'		=> $row['post_edit_time'],
				'edit_name'     => $editName,
				'post_edit_reason'   => $row['post_edit_reason'] ?? null,
				'author_id'		=> $row['poster_id'],
				'ip_address'	=> $row['poster_ip'],
				'post_date'		=> $row['post_time'],
				'queued'		=> ( $visibility == 1 ) ? 0 : -1,
				'pdelete_time'	=> isset( $row['post_delete_time'] ) ? $row['post_delete_time'] : NULL,
			);
			
			$libraryClass->convertForumsPost( $info );
			
			/* Warnings */
			foreach( $this->db->select( '*', 'warnings', array( "post_id=?", $row['post_id'] ) ) AS $warning )
			{
				try
				{
					$log	= $this->db->select( '*', 'log', array( "log_id=?", $warning['log_id'] ) )->first();
					$data	= \unserialize( $log['log_data'] );
				}
				catch( \UnderflowException $e )
				{
					$log	= array( 'user_id' => 0 );
					$data	= array( 0 => NULL );
				}
				
				$warnId = $libraryClass->convertWarnLog( array(
						'wl_id'					=> $warning['warning_id'],
						'wl_member'				=> $warning['user_id'],
						'wl_moderator'			=> $log['user_id'],
						'wl_date'				=> $warning['warning_time'],
						'wl_points'				=> 1,
						'wl_note_member'		=> isset( $data[0] ) ? $data[0] : NULL,
					) );

				/* Add a member history record for this member */
				$libraryClass->convertMemberHistory( array(
						'log_id'		=> 'w' . $warning['warning_id'],
						'log_member'	=> $warning['user_id'],
						'log_by'		=> $log['user_id'],
						'log_type'		=> 'warning',
						'log_data'		=> array( 'wid' => $warnId ),
						'log_date'		=> $warning['warning_time']
					)
				);
			}
			
			$libraryClass->setLastKeyValue( $row['post_id'] );
		}
	}

	/**
	 * @brief   temporarily store post content
	 */
	protected $_postContent = array();
	
	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'attach_id' );
		
		foreach( $this->fetch( 'attachments', 'attach_id' ) AS $row )
		{
			$map = array(
				'id1'	=> $row['topic_id'],
				'id2'	=> $row['post_msg_id'],
			);
			
			$info = array(
				'attach_id'			=> $row['attach_id'],
				'attach_file'		=> $row['real_filename'],
				'attach_date'		=> $row['filetime'],
				'attach_member_id'	=> $row['poster_id'],
				'attach_hits'		=> $row['download_count'],
				'attach_ext'		=> $row['extension'],
				'attach_filesize'	=> $row['filesize'],
			);
			
			$attachId = $libraryClass->convertAttachment( $info, $map, rtrim( $this->app->_session['more_info']['convertAttachments']['attach_location'], '/' ) . '/' . $row['physical_filename'] );

			/* Do some re-jiggery on the post itself to make sure attachment displays */
			if ( $attachId !== FALSE )
			{
				try
				{
					$pid = $this->app->getLink( $row['post_msg_id'], 'forums_posts' );

					if( !isset( $this->_postContent[ $pid ] ) )
					{
						$this->_postContent[ $pid ] = \IPS\Db::i()->select( 'post', 'forums_posts', array( "pid=?", $pid ) )->first();
					}

					$attachmentName = preg_quote( $row['real_filename'], '/' );

					$regex31 = '/\[attachment=(\d+)?\]\<\!\-\- ia[\d]+ \-\-\>' . $attachmentName . '\<\!\-\- ia[\d]+ \-\-\>\[\/attachment\]/i';
					$regex32 = '/\<ATTACHMENT filename\="(.+?)?" index\="(.+?)?"\>\<s\>\[attachment\=(\d+)?\]\<\/s\>' . $attachmentName . '<e\>\[\/attachment\]\<\/e\>\<\/ATTACHMENT\>/i';

					$replacement = '[attachment=' . $attachId . ':name]';
					if( $row['attach_comment'] )
					{
						$replacement .= '<p>' . $row['attach_comment'] . '</p>';
					}
					if( preg_match( $regex31, $this->_postContent[ $pid ] ) )
					{
						$this->_postContent[ $pid ] = preg_replace( $regex31, $replacement, $this->_postContent[ $pid ] );
					}
					elseif( preg_match( $regex32, $this->_postContent[ $pid ] ) )
					{
						$this->_postContent[ $pid ] = preg_replace( $regex32, $replacement, $this->_postContent[ $pid ] );
					}
					else
					{
						$this->_postContent[ $pid ] .= '<br>' . $replacement;
					}
				}
				catch( \UnderflowException $e ) {}
				catch( \OutOfRangeException $e ) {}
			}
			
			$libraryClass->setLastKeyValue( $row['attach_id'] );
		}

		/* Do the updates */
		foreach( $this->_postContent as $pid => $content )
		{
			\IPS\Db::i()->update( 'forums_posts', array( 'post' => $content ), array( 'pid=?', $pid ) );
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

		if( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'viewtopic.php' ) !== FALSE )
		{
			if( isset( \IPS\Request::i()->p ) )
			{
				$class	= '\IPS\forums\Topic\Post';
				$types	= array( 'posts', 'forums_posts' );
				$oldId	= \IPS\Request::i()->p;
			}
			else
			{
				$class	= '\IPS\forums\Topic';
				$types	= array( 'topics', 'forums_topics' );
				$oldId	= \IPS\Request::i()->tid ?: \IPS\Request::i()->t;
			}
		}
		elseif( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'viewforum.php' ) !== FALSE )
		{
			$class	= '\IPS\forums\Forum';
			$types	= array( 'forums', 'forums_forums' );
			$oldId	= \IPS\Request::i()->f;
		}
		elseif( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'download/file.php' ) !== FALSE )
		{
			try
			{
				$data = (string) $this->app->getLink( \IPS\Request::i()->id, array( 'attachments', 'core_attachments' ) );

				return \IPS\Http\Url::external( \IPS\Settings::i()->base_url . 'applications/core/interface/file/attachment.php' )->setQueryString( 'id', $data );
			}
			catch( \Exception $e )
			{
				return NULL;
			}
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