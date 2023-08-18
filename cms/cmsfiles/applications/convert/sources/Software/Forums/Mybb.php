<?php

/**
 * @brief		Converter MyBB Class
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
 * MyBB Forums Converter
 */
class _Mybb extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "MyBB 1.8.x";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "mybb";
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
				'table'						=> 'forums',
				'where'						=> NULL
			),
			'convertForumsTopics'		=> array(
				'table'						=> 'threads',
				'where'						=> NULL
			),
			'convertForumsPosts'		=> array(
				'table'						=> 'posts',
				'where'						=> NULL
			),
			'convertAttachments'		=> array(
				'table'						=> 'attachments',
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
		return array( 'core' => array( 'mybb' ) );
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
					$options[ $reaction->id ]		= $reaction->_icon->url;;
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
					'attach_location'	=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Text',
						'field_default'		=> NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array(),
						'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_mybb_attach_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
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

		/* Rebuild Leaderboard */
		\IPS\Task::queue( 'core', 'RebuildReputationLeaderboard', array(), 4 );
		\IPS\Db::i()->delete('core_reputation_leaderboard_history');

		/* Caches */
		\IPS\Task::queue( 'convert', 'RebuildTagCache', array( 'app' => $this->app->app_id, 'link' => 'forums_topics', 'class' => 'IPS\forums\Topic' ), 3, array( 'app', 'link', 'class' ) );

		return array( "f_forum_last_post_data", "f_rebuild_posts", "f_recounting_forums", "f_recounting_topics", "f_topic_tags_recount" );
	}

	/**
	 * Fix Post Data
	 *
	 * @param	string	$post	Post
	 * @return	string	Fixed Post
	 */
	public static function fixPostData( $post )
	{
		return \IPS\convert\Software\Core\Mybb::fixPostData( $post );
	}

	/**
	 * Convert forums
	 *
	 * @return	void
	 */
	public function convertForumsForums()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'fid' );
		
		foreach( $this->fetch( 'forums', 'fid' ) AS $row )
		{
			$info = array(
				'id'					=> $row['fid'],
				'name'					=> $row['name'],
				'description'			=> $row['description'],
				'topics'				=> $row['threads'],
				'posts'					=> $row['posts'],
				'last_post'				=> $row['lastpost'],
				'last_poster_id'		=> $row['lastposteruid'],
				'last_poster_name'		=> $row['lastposter'],
				'parent_id'				=> $row['pid'] ?: -1,
				'position'				=> $row['disporder'],
				'password'				=> $row['password'] ?: NULL,
				'last_title'			=> $row['lastpostsubject'],
				'inc_postcount'			=> $row['usepostcounts'],
				'redirect_url'			=> $row['linkto'],
				'sub_can_post'			=> ( $row['type'] == 'c' ) ? 0 : 1,
				'queued_topics'			=> $row['unapprovedthreads'],
				'queued_posts'			=> $row['unapprovedposts'],
				'forum_allow_rating'	=> $row['allowtratings'],
			);
			
			$libraryClass->convertForumsForum( $info );
			
			/* Followers */
			foreach( $this->db->select( '*', 'forumsubscriptions', array( "fid=?", $row['fid'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'forums',
					'follow_area'			=> 'forum',
					'follow_rel_id'			=> $row['fid'],
					'follow_rel_id_type'	=> 'forums_forums',
					'follow_member_id'		=> $follow['uid'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 1,
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> 'immediate',
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1,
				) );
			}
			
			$libraryClass->setLastKeyValue( $row['fid'] );
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
		
		$libraryClass::setKey( 'tid' );
		
		foreach( $this->fetch( 'threads', 'tid' ) AS $row )
		{
			/* Poll */
			$poll = NULL;
			if ( $row['poll'] > 0 )
			{
				try
				{
					$pollData = $this->db->select( '*', 'polls', array( "pid=?", $row['poll'] ) )->first();
					
					$choices	= array();
					$index		= 1;
					foreach( explode( '||~|~||', $pollData['options'] ) AS $choice )
					{
						$choices[$index] = trim( $choice );
						$index++;
					}
					
					/* Reset Index */
					$index		= 1;
					$votes		= array();
					$numvotes	= 0;
					foreach( explode( '||~|~||', $pollData['votes'] ) AS $vote )
					{
						$count = $this->db->select( 'COUNT(*)', 'pollvotes', array( "pid=? AND voteoption=?", $row['poll'], $index ) )->first();
						$votes[$index] = $count;
						$index++;
						
						$numvotes += $count;
					}
					
					$poll = array();
					$poll['poll_data'] = array(
						'pid'				=> $pollData['pid'],
						'choices'			=> array( 1 => array(
							'question'			=> $pollData['question'],
							'multi'				=> $pollData['multiple'],
							'choice'			=> $choices,
							'votes'				=> $votes
						) ),
						'poll_question'		=> $pollData['question'],
						'start_date'		=> $pollData['dateline'],
						'starter_id'		=> $row['uid'],
						'votes'				=> $numvotes,
						'poll_view_voters'	=> $pollData['public']
					);
					
					$poll['vote_data'] = array();
					$ourVotes = array();
					foreach( $this->db->select( '*', 'pollvotes', array( "pid=?", $pollData['pid'] ) ) AS $vote )
					{
						if ( !isset( $ourVotes[$vote['uid']] ) )
						{
							/* Create our structure - vB stores each individual vote as a different row whereas we combine them per user */
							$ourVotes[$vote['uid']] = array( 'votes' => array() );
						}
						
						$ourVotes[$vote['uid']]['votes'][]		= $vote['voteoption'];
						
						/* These don't matter - just use the latest one */
						$ourVotes[$vote['uid']]['vid']			= $vote['vid'];
						$ourVotes[$vote['uid']]['vote_date'] 	= $vote['dateline'];
						$ourVotes[$vote['uid']]['member_id']		= $vote['uid'];
					}
					
					/* Now we need to re-wrap it all for storage */
					foreach( $ourVotes AS $member_id => $vote )
					{
						$poll['vote_data'][$member_id] = array(
							'vid'				=> $vote['vid'],
							'vote_date'			=> $vote['vote_date'],
							'member_id'			=> $vote['member_id'],
							'member_choices'	=> array( 1 => $vote['votes'] ),
						);
					}
				}
				catch( \UnderflowException $e ) {} # if the poll is missing, don't bother
			}
			
			/* Moved ?*/
			$moved		= explode( "|", $row['closed'] );
			$moved_to	= NULL;
			if ( isset( $moved[0] ) AND $moved[0] == 'moved' )
			{
				try
				{
					$moved_to = array(
						$moved[1],
						$this->db->select( 'fid', 'threads', array( "tid=?", $moved[1] ) )->first()
					);
				}
				catch( \UnderflowException $e )
				{
					$moved_to = NULL;
				}
			}
			
			$info = array(
				'tid'					=> $row['tid'],
				'title'					=> $row['subject'],
				'forum_id'				=> $row['fid'],
				'state'					=> ( $row['closed'] == 1 ) ? 'closed' : 'open',
				'starter_id'			=> $row['uid'],
				'start_date'			=> $row['dateline'],
				'last_poster_id'		=> $row['lastposteruid'],
				'last_post'				=> $row['lastpost'],
				'starter_name'			=> $row['username'],
				'last_poster_name'		=> $row['lastposter'],
				'poll_state'			=> $poll,
				'views'					=> $row['views'],
				'approved'				=> $row['visible'], # it's handled exactly the same
				'pinned'				=> $row['sticky'],
				'moved_to'				=> $moved_to,
				'topic_queuedposts'		=> $row['unapprovedposts'],
				'topic_rating_total'	=> $row['totalratings'],
				'topic_rating_hits'		=> $row['numratings'],
				'topic_hiddenposts'		=> $row['deletedposts']
			);
			
			$libraryClass->convertForumsTopic( $info );
			
			/* If we have a prefix, convert it */
			if ( $row['prefix'] > 0 )
			{
				try
				{
					$prefix = $this->db->select( 'prefix', 'threadprefixes', array( "pid=?", $row['prefix'] ) )->first();
					
					$libraryClass->convertTag( array(
						'tag_meta_app'			=> 'forums',
						'tag_meta_area'			=> 'forums',
						'tag_meta_parent_id'	=> $row['fid'],
						'tag_meta_id'			=> $row['tid'],
						'tag_text'				=> $prefix,
						'tag_member_id'			=> $row['uid'],
						'tag_added'             => $row['dateline'],
						'tag_prefix'			=> 1,
					) );
				}
				catch( \UnderflowException $e ) {}
			}
			
			/* Follows */
			foreach( $this->db->select( '*', 'threadsubscriptions', array( "tid=?", $row['tid'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'forums',
					'follow_area'			=> 'topic',
					'follow_rel_id'			=> $row['tid'],
					'follow_rel_id_type'	=> 'forums_topics',
					'follow_member_id'		=> $follow['uid'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 1,
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> 'immediate',
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1,
				) );
			}
			
			/* Ratings */
			foreach( $this->db->select( '*', 'threadratings', array( "tid=?", $row['tid'] ) ) AS $rating )
			{
				$libraryClass->convertRating( array(
					'id'		=> $rating['rid'],
					'class'		=> 'IPS\\forums\\Topic',
					'item_link'	=> 'forums_topics',
					'item_id'	=> $rating['tid'],
					'ip'		=> $rating['ipaddress'],
					'rating'	=> $rating['rating'],
					'member'	=> $rating['uid']
				) );
			}
			
			$libraryClass->setLastKeyValue( $row['tid'] );
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
		
		$libraryClass::setKey( 'pid' );
		
		foreach( $this->fetch( 'posts', 'pid' ) AS $row )
		{
			switch( $row['visible'] )
			{
				case 1:
					$queued = 0;
					break;
				
				case 0:
					$queued = 1;
					break;
				
				case -1:
					if( !$row['replyto'] )
					{
						$queued = 2; // First post in topic
					}
					else
					{
						$queued = -1;
					}
					break;
			}
			
			$info = array(
				'pid'				=> $row['pid'],
				'topic_id'			=> $row['tid'],
				'post'				=> $row['message'],
				'edit_time'			=> $row['edittime'],
				'author_id'			=> $row['uid'],
				'author_name'		=> $row['username'],
				'ip_address'		=> $row['ipaddress'],
				'post_date'			=> $row['dateline'],
				'queued'			=> $queued,
				'post_edit_reason'	=> $row['editreason'],
			);

			// Flag as first post in topic
			if( $queued == 2 )
			{
				$info['new_topic'] = 1;
			}
			
			$libraryClass->convertForumsPost( $info );
			
			/* Reputation */
			foreach( $this->db->select( '*', 'reputation', array( "pid=?", $row['pid'] ) ) AS $rep )
			{
				$reaction = ( $rep['reputation'] > 0 ) ? $this->app->_session['more_info']['convertForumsPosts']['rep_positive'] : $this->app->_session['more_info']['convertForumsPosts']['rep_negative'];

				$libraryClass->convertReputation( array(
					'id'				=> $rep['rid'],
					'app'				=> 'forums',
					'type'				=> 'pid',
					'type_id'			=> $row['pid'],
					'member_id'			=> $rep['adduid'],
					'member_received'	=> $rep['uid'],
					'rep_date'			=> $rep['dateline'],
					'reaction'			=> $reaction
				) );
			}
			
			/* Warnings */
			foreach( $this->db->select( '*', 'warnings', array( "pid=?", $row['pid'] ) ) AS $warn )
			{
				$warnId = $libraryClass->convertWarnLog( array(
					'wl_id'					=> $warn['wid'],
					'wl_member'				=> $warn['uid'],
					'wl_moderator'			=> $warn['issuedby'],
					'wl_date'				=> $warn['dateline'],
					'wl_points'				=> $warn['points'],
					'wl_note_mods'			=> $warn['notes'],
				) );

				/* Add a member history record for this member */
				$libraryClass->convertMemberHistory( array(
						'log_id'		=> 'w' . $warn['wid'],
						'log_member'	=> $warn['uid'],
						'log_by'		=> $warn['issuedby'],
						'log_type'		=> 'warning',
						'log_data'		=> array( 'wid' => $warnId ),
						'log_date'		=> $warn['dateline']
					)
				);
			}
			
			$libraryClass->setLastKeyValue( $row['pid'] );
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
		
		$libraryClass::setKey( 'aid' );
		
		foreach( $this->fetch( 'attachments', 'aid' ) AS $row )
		{
			try
			{
				$topic_id = $this->db->select( 'tid', 'posts', array( "pid=?", $row['pid'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				/* Post is orphaned */
				$libraryClass->setLastKeyValue( $row['aid'] );
				continue;
			}
			
			$map = array(
				'id1'	=> $topic_id,
				'id2'	=> $row['pid'],
			);
			
			$ext = explode( '.', $row['filename'] );
			$ext = array_pop( $ext );
			
			$info = array(
				'attach_id'			=> $row['aid'],
				'attach_file'		=> $row['filename'],
				'attach_date'		=> $row['dateuploaded'],
				'attach_member_id'	=> $row['uid'],
				'attach_hits'		=> $row['downloads'],
				'attach_ext'		=> $ext,
				'attach_filesize'	=> $row['filesize'],
			);
			
			$attachId = $libraryClass->convertAttachment( $info, $map, rtrim( $this->app->_session['more_info']['convertAttachments']['attach_location'], '/' ) . '/' . $row['attachname'] );

			/* Update Post if we can */
			try
			{
				if ( $attachId !== FALSE )
				{
					$pid = $this->app->getLink( $row['pid'], 'forums_posts' );

					$post = \IPS\Db::i()->select( 'post', 'forums_posts', array( "pid=?", $pid ) )->first();

					if( \preg_match( '#\[attachment=' . $row['aid'] . ']#i', $post ) )
					{
						$post = str_ireplace( '[attachment=' . $row['aid'] . ']', '[attachment=' . $attachId . ':name]', $post );
						\IPS\Db::i()->update( 'forums_posts', array( 'post' => $post ), array( "pid=?", $pid ) );
					}
				}
			}
			catch( \UnderflowException $e ) {}
			catch( \OutOfRangeException $e ) {}
			
			$libraryClass->setLastKeyValue( $row['aid'] );
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

		if( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'showthread.php' ) !== FALSE )
		{
			$class	= '\IPS\forums\Topic';
			$types	= array( 'topics', 'forums_topics' );
			$oldId	= \IPS\Request::i()->tid;
		}
		elseif( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'forumdisplay.php' ) !== FALSE )
		{
			$class	= '\IPS\forums\Forum';
			$types	= array( 'forums', 'forums_forums' );
			$oldId	= \IPS\Request::i()->fid;
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