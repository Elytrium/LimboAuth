<?php

/**
 * @brief		Converter vBulletin 5.x Forums Class
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
 * vBulletin 5 Forums Converter
 */
class _Vbulletin5 extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "vBulletin Forums (5.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "vbulletin5";
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
				'table'		=> 'forum',
				'where'		=> NULL,
			),
			'convertForumsTopics'	=> array(
				'table'		=> 'thread',
				'where'		=> NULL,
			),
			'convertForumsPosts'	=> array(
				'table'		=> 'post',
				'where'		=> NULL,
			),
			'convertAttachments'	=> array(
				'table'		=> 'attachment',
				'where'		=> NULL,
				'extra_steps'	=> array( 'convertAttachments2' )
			),
			'convertAttachments2'	=> array(
				'table'		=> 'attachment',
				'where'		=> NULL
			),
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
		$rows['convertAttachments2'] = array(
			'step_method'		=> 'convertAttachments2',
			'step_title'		=> 'convert_attachments',
			'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments_map', array( "location_key=?", 'gallery_Images' ) ),
			'source_rows'		=> array( 'table' => static::canConvert()['convertAttachments2']['table'], 'where' => static::canConvert()['convertAttachments2']['where'] ),
			'per_cycle'			=> 10,
			'dependencies'		=> array( 'convertAttachments' ),
			'link_type'			=> 'core_attachments',
		);

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
		switch( $table )
		{
			case 'forum':
				return \count( $this->fetchForums() );
				break;
			
			case 'thread':
				try
				{
					return parent::countRows( 'node', array( \IPS\Db::i()->in( 'contenttypeid', array( $this->fetchType( 'Text' ), $this->fetchType( 'Poll' ), $this->fetchType( 'Gallery' ), $this->fetchType( 'Video' ), $this->fetchType( 'Link' ) ) ) . " AND " . \IPS\Db::i()->in( 'parentid', array_keys( $this->fetchForums() ) ), $this->fetchType( 'Text' ), $this->fetchType( 'Poll' ) ) );
				}
				catch( \Exception $e )
				{
					throw new \IPS\convert\Exception( sprintf( \IPS\Member::loggedIn()->language()->get( 'could_not_count_rows' ), $table ) );
				}
				break;
			
			case 'post':
				try
				{
					return parent::countRows( 'node', array( \IPS\Db::i()->in( 'contenttypeid', array( $this->fetchType( 'Text' ), $this->fetchType( 'Gallery' ), $this->fetchType( 'Video' ), $this->fetchType( 'Link' ), $this->fetchType( 'Poll' ) ) ) ) );
				}
				catch( \Exception $e )
				{
					throw new \IPS\convert\Exception( sprintf( \IPS\Member::loggedIn()->language()->get( 'could_not_count_rows' ), $table ) );
				}
				break;
			
			case 'attachment':
				return parent::countRows( 'node', array( "contenttypeid IN (" . $this->fetchType( 'Attach' ) . ',' . $this->fetchType( 'Photo' ) . ")" ) );
				break;
			
			default:
				return parent::countRows( $table, $where, $recache );
				break;
		}
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
		return array( 'core' => array( 'vbulletin5' ) );
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
		\IPS\Task::queue( 'convert', 'RebuildFirstPostIds', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );
		\IPS\Task::queue( 'convert', 'DeleteEmptyTopics', array( 'app' => $this->app->app_id ), 5, array( 'app' ) );

		/* Rebuild Leaderboard */
		\IPS\Task::queue( 'core', 'RebuildReputationLeaderboard', array(), 4 );
		\IPS\Db::i()->delete('core_reputation_leaderboard_history');
		
		return array( 'f_rebuild_posts', 'f_recounting_forums', 'f_recounting_topics' );
	}
	
	/**
	 * Fix Post Data
	 *
	 * @param	string	$post	Post
	 * @return	string	Fixed Posts
	 */
	public static function fixPostData( $post )
	{
		return \IPS\convert\Software\Core\Vbulletin5::fixPostData( $post );
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
					'rep_like'	=> array(
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
						'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'			=> 'database',
						'field_required'		=> TRUE,
						'field_extra'			=> array(
							'options'				=> array(
								'database'				=> \IPS\Member::loggedIn()->language()->addToStack( 'conv_store_database' ),
								'file_system'			=> \IPS\Member::loggedIn()->language()->addToStack( 'conv_store_file_system' ),
							),
							'userSuppliedInput'	=> 'file_system',
						),
						'field_hint'			=> NULL,
						'field_validation'		=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
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
		
		$libraryClass::setKey( 'node.nodeid' );
		$clubs = $this->fetchClubs();

		foreach( $this->fetch( 'node', 'nodeid', array( "node.nodeid<>? AND ( ( closure.parent=? AND node.contenttypeid=? ) OR " . $this->db->in( 'nodeid', array_keys( $clubs ) ) . " )", 2, 2, $this->fetchType( 'Channel' ) ) )->join( 'closure', "closure.child=node.nodeid" ) AS $forum )
		{
			/* This may be a club forum that already exists */
			try
			{
				$this->app->getLink( $forum['nodeid'], 'forums_forums' );
				$libraryClass->setLastKeyValue( $forum['nodeid'] );
				$this->app->log( 'vb5_skipped_forum', __METHOD__, \IPS\convert\App::LOG_WARNING );
				continue;
			}
			catch( \OutOfRangeException $e ){}

			$self = $this;
			$checkpermission = function( $name, $perm ) use ( $forum, $self )
			{
				$key = $name;
				if ( $name == 'forumoptions' )
				{
					$key = 'nodeoptions';
				}
				
				if ( $forum[$key] & $self::$bitOptions[$name][$perm] )
				{
					return TRUE;
				}
				
				return FALSE;
			};
			
			$info = array(
				'id'					=> $forum['nodeid'],
				'name'					=> $forum['title'],
				'description'			=> $forum['description'],
				'topics'				=> $forum['textcount'],
				'posts'					=> $forum['totalcount'],
				'last_post'				=> $forum['lastcontent'],
				'last_poster_id'		=> $forum['lastauthorid'],
				'last_poster_name'		=> $forum['lastcontentauthor'],
				'parent_id'				=> ( !array_key_exists( $forum['nodeid'], $clubs ) ) ? $forum['parentid'] : -1,
				'club_id'				=> ( array_key_exists( $forum['nodeid'], $clubs ) ) ? $forum['nodeid'] : NULL,
				'position'				=> $forum['displayorder'],
				'preview_posts'			=> $checkpermission( 'forumoptions', 'moderatenewpost' ),
				'inc_postcount'			=> 1,
				'forum_allow_rating'	=> 1,
				'sub_can_post'			=> ( $forum['parentid'] == 2 AND !array_key_exists( $forum['nodeid'], $clubs ) ) ? 0 : 1,
			);
			
			$libraryClass->convertForumsForum( $info );
			
			$libraryClass->setLastKeyValue( $forum['nodeid'] );
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
		
		$libraryClass::setKey( 'node.nodeid' );
		
		$clubs = $this->fetchClubs();
		foreach( $this->fetch( 'node', 'node.nodeid', array( "( " . \IPS\Db::i()->in( 'node.parentid', array_keys( $this->fetchForums() ) ) . " OR " . $this->db->in( 'node.parentid', array_keys( $clubs ) ) . " ) AND " . \IPS\Db::i()->in( 'node.contenttypeid', array( $this->fetchType( 'Text' ), $this->fetchType( 'Poll' ), $this->fetchType( 'Gallery' ), $this->fetchType( 'Video' ), $this->fetchType( 'Link' ) ) ) ) ) AS $topic )
		{
			/* Pesky Polls */
			$poll		= NULL;
			$lastVote	= 0;
			try
			{
				/* Does this topic have a corresponding poll? */
				$pollData		= $this->db->select( '*', 'poll', array( "nodeid=?", $topic['nodeid'] ) )->first();
				$pollOptions	= @\unserialize( $pollData['options'] );
				$lastVote		= $pollData['lastvote'];
				
				if ( $pollOptions === FALSE )
				{
					throw new \UnexpectedValueException;
				}
				
				$choices	= array();
				$numvotes	= 0;
				$votes		= array();
				foreach( $pollOptions AS $option )
				{
					$choices[$option['polloptionid']]	= $option['title'];
					$votes[$option['polloptionid']]		= $option['votes'];
					$numvotes							+= $option['votes'];
				}
				
				$poll = array();
				$poll['poll_data'] = array(
					'pid'				=> $pollData['nodeid'],
					'choices'			=> array( 1 => array(
						'question'			=> $topic['title'],
						'multi'				=> $pollData['multiple'],
						'choice'			=> $choices,
						'votes'				=> $votes
					) ),
					'poll_question'		=> $topic['title'],
					'start_date'		=> $topic['publishdate'],
					'starter_id'		=> $topic['userid'],
					'votes'				=> $numvotes,
					'poll_view_voters'	=> $pollData['public']
				);
				
				$poll['vote_data'] = array();
				$ourVotes = array();
				foreach( $this->db->select( '*', 'pollvote', array( "nodeid=?", $pollData['nodeid'] ) ) AS $vote )
				{
					if ( !isset( $ourVotes[$vote['userid']] ) )
					{
						/* Create our structure - vB stores each individual vote as a different row whereas we combine them per user */
						$ourVotes[$vote['userid']] = array( 'votes' => array() );
					}
					
					$ourVotes[$vote['userid']]['votes'][]		= $vote['voteoption'];
					
					/* These don't matter - just use the latest one */
					$ourVotes[$vote['userid']]['vid']			= $vote['pollvoteid'];
					$ourVotes[$vote['userid']]['vote_date'] 	= $vote['votedate'];
					$ourVotes[$vote['userid']]['member_id']		= $vote['userid'];
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
			catch( \UnexpectedValueException $e ) {} # if poll data is corrupt, then skip
			
			$info = array(
				'tid'				=> $topic['nodeid'],
				'title'				=> $topic['title'],
				'forum_id'			=> $topic['parentid'],
				'state'				=> $topic['open'] ? 'open' : 'closed',
				'posts'				=> $topic['totalcount'],
				'starter_id'		=> $topic['userid'],
				'start_date'		=> $topic['publishdate'],
				'last_poster_id'	=> $topic['lastauthorid'],
				'last_post'			=> $topic['lastcontent'],
				'starter_name'		=> $topic['authorname'],
				'last_poster_name'	=> $topic['lastcontentauthor'],
				'poll_state'		=> $poll,
				'last_vote'			=> $lastVote,
				'approved'			=> $topic['approved'],
				'pinned'			=> $topic['sticky'],
				'topic_open_time'	=> $topic['publishdate'],
				'topic_hiddenposts'	=> $topic['totalunpubcount']
			);
			
			unset( $poll );

			$softDeleted = FALSE;
			if ( $topic['deleteuserid'] )
			{
				$info['approved'] = -1;
				$softDeleted = TRUE;
			}
			
			$topicId = $libraryClass->convertForumsTopic( $info );

			/* post conversion things */
			if( !empty( $topicId ) )
			{
				/* Record old vB 2/3/4 ID */
				if ( !empty( $topic['oldid'] ) )
				{
					$this->app->addLink( $topicId, $topic['oldid'], 'forums_topics_old' );
				}

				/* Need to insert into the SDL? */
				if ( $softDeleted )
				{
					try
					{
						$softDeleteId = $this->app->getLink( $topic['deleteuserid'], 'core_members', TRUE );
					}
						/* User link doesn't exist */
					catch ( \OutOfRangeException $e )
					{
						$softDeleteId = 0;
					}

					\IPS\Db::i()->insert( 'core_soft_delete_log', array(
						'sdl_obj_id' => $topicId,
						'sdl_obj_key' => 'topic',
						'sdl_obj_member_id' => $softDeleteId,
						'sdl_obj_date' => $topic['unpublishdate'] ?: time(),
						'sdl_obj_reason' => $topic['deletereason'] ?: NULL,
						'sdl_locked' => 0
					) );
				}
			}
			
			$libraryClass->setLastKeyValue( $topic['nodeid'] );
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
		
		$libraryClass::setKey( 'node.nodeid' );

		foreach( $this->fetch( 'node', 'node.nodeid', array( \IPS\Db::i()->in( 'node.contenttypeid', array( $this->fetchType( 'Text' ), $this->fetchType( 'Gallery' ), $this->fetchType( 'Video' ), $this->fetchType( 'Link' ), $this->fetchType( 'Poll' ) ) ) ), 'node.*, text.rawtext, text.htmlstate' )->join( 'text', 'text.nodeid = node.nodeid' ) AS $post )
		{
			/* Comments of comments of comments of comments */
			$isSubComment = FALSE;
			try
			{
				/* Is this the first post? */
				$this->app->getLink( $post['parentid'], 'forums_forums' );
				
				$post['parentid'] = $post['nodeid'];
			}
			catch( \OutOfRangeException $e )
			{
				try
				{
					/* If parentid exists as a forums_topics link, we don't actually need to do anything */
					$this->app->getLink( $post['parentid'], 'forums_topics' );
				}
				catch( \OutOfRangeException $e )
				{
					try
					{
						$this->app->getLink( $post['parentid'], 'forums_posts' );
						
						$isSubComment = TRUE;
						
						/* Load the parent */
						$parent = $this->db->select( '*', 'node', array( "node.nodeid=?", $post['parentid'] ) )->join( 'text', 'text.nodeid = node.nodeid' )->first();
						
						$post['approved']	= $parent['approved'];
						$post['parentid']	= $parent['parentid'];
						$post['rawtext']	= "[quote name='" . $parent['authorname'] ."' timestamp='" . $parent['created'] . "']" . $parent['rawtext'] . "[/quote]\n" . $post['rawtext'];
					}
					catch( \OutOfRangeException $e ) # we need to go deeper!
					{
						/* Actually, we don't - end of the line */
						$libraryClass->setLastKeyValue( $post['nodeid'] );
						$this->app->log( 'vb5_post_missing_parent_post', __METHOD__, \IPS\convert\App::LOG_WARNING, $post['nodeid'] );
						continue;
					}
					catch( \UnderflowException $e )
					{
						/* Loading the parent failed - move on */
						$libraryClass->setLastKeyValue( $post['nodeid'] );
						$this->app->log( 'vb5_post_missing_parent_post_2', __METHOD__, \IPS\convert\App::LOG_WARNING, $post['nodeid'] );
						continue;
					}
				}
			}
			
			/* We may need to insert some data in core_soft_delete_log later if the post was hidden by a moderator. */
			$softDeleted = FALSE;
			$queued = 0;
			if ( !$post['approved'] )
			{
				/* If the post is unapproved, then that's a direct translation and the post should then be marked as hidden pending approval. */
				$queued = 1;
			}
			else if ( $post['deleteuserid'] )
			{
				$queued = -1;
				$softDeleted = TRUE;
			}
			
			$info = array(
				'pid'				=> $post['nodeid'],
				'topic_id'			=> $post['parentid'],
				'post'				=> $post['rawtext'],
				'author_id'			=> $post['userid'],
				'author_name'		=> $post['authorname'],
				'ip_address'		=> $post['ipaddress'],
				'post_date'			=> $post['publishdate'],
				'queued'			=> $queued,
				'post_htmlstate'	=> ( \in_array( $post['htmlstate'], array( 'on', 'on_nl2br' ) ) ) ? 1 : 0,
			);

			/* Any vB5 Links? */
			if( $post['contenttypeid'] == $this->fetchType( 'Link' ) )
			{
				try
				{
					$link = $this->db->select( 'url, url_title', 'link', array('nodeid=?', $post['nodeid']) )->first();

					if ( !empty( $link['url_title'] ) )
					{
						$info['post'] .= <<<LINK

[url={$link['url']}]{$link['url_title']}[/url]
LINK;
					}
					else
					{
						$info['post'] .= <<<LINK

[url]{$link['url']}[/url]
LINK;
					}
				}
				catch ( \UnderflowException $e ) {}
			}
			
			$post_id = $libraryClass->convertForumsPost( $info );

			if( !empty( $post_id ) )
			{
				/* Record old vB 2/3/4 ID */
				if( !empty( $post['oldid'] ) )
				{
					$this->app->addLink( $post_id, $post['oldid'], 'forums_posts_old' );
				}

				/* Need to insert into the SDL? */
				if ( $softDeleted )
				{
					try
					{
						$softDeleteId = $this->app->getLink( $post['deleteuserid'], 'core_members', TRUE );
					}
						/* User link doesn't exist */
					catch ( \OutOfRangeException $e )
					{
						$softDeleteId = 0;
					}

					\IPS\Db::i()->insert( 'core_soft_delete_log', array(
						'sdl_obj_id' => $post_id,
						'sdl_obj_key' => 'post',
						'sdl_obj_member_id' => $softDeleteId,
						'sdl_obj_date' => $post['unpublishdate'] ?: time(),
						'sdl_obj_reason' => $post['deletereason'] ?: NULL,
						'sdl_locked' => 0
					) );
				}

				/* Reputation */
				foreach ( $this->db->select( '*', 'reputation', array( "nodeid=?", $post['nodeid'] ) ) as $rep )
				{
					$libraryClass->convertReputation( array(
						'id' => $rep['reputationid'],
						'app' => 'forums',
						'type' => 'pid',
						'type_id' => $post['nodeid'],
						'member_id' => $rep['whoadded'],
						'member_received' => $rep['userid'],
						'rep_date' => $rep['dateline'],
						'reaction' => $this->app->_session['more_info']['convertForumsPosts']['rep_like']
					) );
				}

				/* Edit History */
				$latestedit = 0;
				$reason = NULL;
				$name = NULL;
				$newText = $post['rawtext'];

				foreach ( $this->db->select( '*', 'postedithistory', array( "nodeid=?", $post['nodeid'] ) ) as $edit )
				{
					$libraryClass->convertEditHistory( array(
						'id' => $edit['postedithistoryid'],
						'class' => 'IPS\\forums\\Topic\\Post',
						'comment_id' => $post['nodeid'],
						'member' => $edit['userid'],
						'time' => $edit['dateline'],
						'old' => $edit['pagetext'],
						'new' => $newText
					) );

					$newText = $edit['pagetext'];

					if ( $edit['dateline'] > $latestedit )
					{
						$latestedit = $edit['dateline'];
						$reason = $edit['reason'];
						$name = $edit['username'];
					}
				}

				/* Warnings */
				foreach ( $this->db->select( '*', 'infraction', array( "nodeid=?", $post['nodeid'] ) ) as $warn )
				{
					$warnId = $libraryClass->convertWarnLog( array(
						'wl_id' => $warn['infractionid'],
						'wl_member' => $warn['nodeid'],
						'wl_moderator' => $warn['whoadded'],
						'wl_date' => $warn['dateline'],
						'wl_points' => $warn['points'],
						'wl_note_member' => $warn['note'],
						'wl_note_mods' => $warn['customreason'],
					) );

					/* Add a member history record for this member */
					$libraryClass->convertMemberHistory( array(
							'log_id' => 'w' . $warn['infractionid'],
							'log_member' => $warn['userid'],
							'log_by' => $warn['whoadded'],
							'log_type' => 'warning',
							'log_data' => array( 'wid' => $warnId ),
							'log_date' => $warn['dateline']
						)
					);
				}

				/* If we have a latest edit, then update the main post - this should really be in the library, as the converters should not be altering data */
				if ( $latestedit )
				{
					\IPS\Db::i()->update( 'forums_posts', array( 'append_edit' => 1, 'edit_time' => $latestedit, 'edit_name' => $name, 'post_edit_reason' => $reason ), array( "pid=?", $post_id ) );
				}
			}
			
			$libraryClass->setLastKeyValue( $post['nodeid'] );
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
		
		$libraryClass::setKey( 'node.nodeid' );
		
		$main = $this->fetch( 'node', 'node.nodeid', array( "node.contenttypeid=?", $this->fetchType( 'Attach' ) ) )
			->join( 'attach', 'attach.nodeid = node.nodeid' )
			->join( 'filedata', 'filedata.filedataid = attach.filedataid' );
		
		foreach( $main AS $attachment )
		{
			/* This is the first post in the topic */
			try
			{
				$this->app->getLink( $attachment['parentid'], 'forums_topics' );
				$post_id = $attachment['parentid'];
			}
			/* It isn't the first post in the topic */
			catch( \OutOfRangeException $e )
			{
				try
				{
					$this->app->getLink( $attachment['parentid'], 'forums_posts' );
					
					$parent					= $this->db->select( 'nodeid, parentid', 'node', array( "nodeid=?", $attachment['parentid'] ) )->first();
					$attachment['parentid']	= $parent['parentid'];
					$post_id				= $parent['nodeid'];
				}
				catch( \OutOfRangeException $e )
				{
					/* No valid converter link for the parent post */
					$libraryClass->setLastKeyValue( $attachment['nodeid'] );
					$this->app->log( 'vb5_attach_missing_post_link', __METHOD__, \IPS\convert\App::LOG_WARNING, $attachment['nodeid'] );
					continue;
				}
				catch( \UnderflowException $e )
				{
					/* Parent post does not exist in vB database */
					$libraryClass->setLastKeyValue( $attachment['nodeid'] );
					$this->app->log( 'vb5_attach_missing_post', __METHOD__, \IPS\convert\App::LOG_WARNING, $attachment['nodeid'] );
					continue;
				}
			}
			
			$map = array(
				'id1'		=> $attachment['parentid'],
				'id2'		=> $post_id
			);
			
			$info = array(
				'attach_id'			=> $attachment['nodeid'],
				'attach_file'		=> $attachment['filename'],
				'attach_date'		=> $attachment['dateline'],
				'attach_member_id'	=> $attachment['userid'],
				'attach_hits'		=> $attachment['counter'],
				'attach_ext'		=> $attachment['extension'],
				'attach_filesize'	=> $attachment['filesize'],
			);
			
			if ( $this->app->_session['more_info']['convertAttachments']['file_location'] == 'database' )
			{
				/* Simples! */
				$data = $attachment['filedata'];
				$path = NULL;
			}
			else
			{
				$data = NULL;
				$path = implode( '/', preg_split( '//', $attachment['userid'], -1, PREG_SPLIT_NO_EMPTY ) );
				$path = rtrim( $this->app->_session['more_info']['convertAttachments']['file_location'], '/' ) . '/' . $path . '/' . $attachment['filedataid'] . '.attach';
				
				/* Apparently vBulletin had a bug between 5.0.0 and 5.1.3 where attachments were incorrectly stored in /path/userid/file.attach rather than in the user ID split location */
				if ( !file_exists( $path ) )
				{
					$path = rtrim( $this->app->_session['more_info']['convertAttachments']['file_location'], '/' ) . '/' . $attachment['userid'] . '/' . $attachment['filedataid'] . '.attach';
				}
				
				/* Still not there? It might be in the database still */
				if ( !file_exists( $path ) AND $attachment['filedata'] )
				{
					$data = $attachment['filedata'];
					$path = NULL;
				}
			}
			
			$attach_id = $libraryClass->convertAttachment( $info, $map, $path, $data );
			
			/* Do some re-jiggery on the post itself to make sure attachment displays */
			if ( $attach_id !== FALSE )
			{
				try
				{
					$pid = $this->app->getLink( $post_id, 'forums_posts' );
					
					$post = \IPS\Db::i()->select( 'post', 'forums_posts', array( "pid=?", $pid ) )->first();
				}
				catch( \OutOfRangeException $e )
				{
					/* If the post didn't exist and this is just an orphaned attachment, move along */
					$libraryClass->setLastKeyValue( $attachment['nodeid'] );
					continue;
				}
				
				if ( preg_match( "/\[ATTACH([^\]]+?)?\]n" . $attachment['nodeid'] . "\[\/ATTACH\]/i", $post ) )
				{
					$post = preg_replace( "/\[ATTACH([^\]]+?)?\]n" . $attachment['nodeid'] . "\[\/ATTACH\]/i", '[attachment=' . $attach_id . ':name]', $post );

					\IPS\Db::i()->update( 'forums_posts', array( 'post' => $post ), array( "pid=?", $pid ) );
				}
				else
				{
					$oldAttachId = NULL;
					$update = FALSE;

					/* Try old attach ID */
					try
					{
						$oldAttachId = $this->db->select( 'attachmentid', 'attachment', array( 'filedataid=?', $attachment['filedataid'] ) )->first();
					}
					/* Don't fail if this table/value doesn't exist */
					catch( \Exception $e ) { }

					if ( $oldAttachId !== NULL and preg_match( "/\[ATTACH([^\]]+?)?\]" . $oldAttachId . "\[\/ATTACH\]/i", $post ) )
					{
						$update = TRUE;
						$post = preg_replace( "/\[ATTACH([^\]]+?)?\]" . $oldAttachId . "\[\/ATTACH\]/i", '[attachment=' . $attach_id . ':name]', $post );
					}
					elseif( preg_match( "/\[ATTACH=JSON](.+?)\"data-attachmentid\":\"?{$attachment['nodeid']}\"?(.+?)\[\/ATTACH\]/i", $post ) )
					{
						$update = TRUE;
						$post = preg_replace( "/\[ATTACH=JSON](.+?)\"data-attachmentid\":\"?{$attachment['nodeid']}\"?(.+?)\[\/ATTACH\]/i", '[attachment=' . $attach_id . ':name]', $post );
					}

					if( $update )
					{
						\IPS\Db::i()->update( 'forums_posts', array( 'post' => $post ), array( "pid=?", $pid ) );
					}
				}
			}
			
			$libraryClass->setLastKeyValue( $attachment['nodeid'] );
		}
	}

	/**
	 * Convert attachments (photo posts)
	 *
	 * @return	void
	 */
	public function convertAttachments2()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'node.nodeid' );
		
		$main = $this->fetch( 'node', 'node.nodeid', array( "node.contenttypeid=?", $this->fetchType( 'Photo' ) ) )
			->join( 'photo', 'photo.nodeid = node.nodeid' )
			->join( 'filedata', 'filedata.filedataid = photo.filedataid' );
		
		foreach( $main AS $attachment )
		{
			/* This is the first post in the topic */
			try
			{
				$this->app->getLink( $attachment['parentid'], 'forums_topics' );
				$post_id = $attachment['parentid'];
			}
			/* It isn't the first post in the topic */
			catch( \OutOfRangeException $e )
			{
				try
				{
					$this->app->getLink( $attachment['parentid'], 'forums_posts' );
					
					$parent					= $this->db->select( 'nodeid, parentid', 'node', array( "nodeid=?", $attachment['parentid'] ) )->first();
					$attachment['parentid']	= $parent['parentid'];
					$post_id				= $parent['nodeid'];
				}
				catch( \OutOfRangeException $e )
				{
					/* No valid converter link for the parent post */
					$libraryClass->setLastKeyValue( $attachment['nodeid'] );
					$this->app->log( 'vb5_attach_missing_post_link', __METHOD__, \IPS\convert\App::LOG_WARNING, $attachment['nodeid'] );
					continue;
				}
				catch( \UnderflowException $e )
				{
					/* Parent post does not exist in vB database */
					$libraryClass->setLastKeyValue( $attachment['nodeid'] );
					$this->app->log( 'vb5_attach_missing_post', __METHOD__, \IPS\convert\App::LOG_WARNING, $attachment['nodeid'] );
					continue;
				}
			}
			
			$map = array(
				'id1'		=> $attachment['parentid'],
				'id2'		=> $post_id
			);
			
			$info = array(
				'attach_id'			=> $attachment['nodeid'],
				'attach_file'		=> $attachment['filehash'] . '.' . $attachment['extension'],
				'attach_date'		=> $attachment['dateline'],
				'attach_member_id'	=> $attachment['userid'],
				'attach_hits'		=> 0,
				'attach_ext'		=> $attachment['extension'],
				'attach_filesize'	=> $attachment['filesize'],
			);
			
			if ( $this->app->_session['more_info']['convertAttachments']['file_location'] == 'database' )
			{
				/* Simples! */
				$data = $attachment['filedata'];
				$path = NULL;
			}
			else
			{
				$data = NULL;
				$path = implode( '/', preg_split( '//', $attachment['userid'], -1, PREG_SPLIT_NO_EMPTY ) );
				$path = rtrim( $this->app->_session['more_info']['convertAttachments']['file_location'], '/' ) . '/' . $path . '/' . $attachment['filedataid'] . '.attach';
				
				/* Apparently vBulletin had a bug between 5.0.0 and 5.1.3 where attachments were incorrectly stored in /path/userid/file.attach rather than in the user ID split location */
				if ( !file_exists( $path ) )
				{
					$path = rtrim( $this->app->_session['more_info']['convertAttachments']['file_location'], '/' ) . '/' . $attachment['userid'] . '/' . $attachment['filedataid'] . '.attach';
				}
				
				/* Still not there? It might be in the database still */
				if ( !file_exists( $path ) AND $attachment['filedata'] )
				{
					$data = $attachment['filedata'];
					$path = NULL;
				}
			}
			
			$attach_id = $libraryClass->convertAttachment( $info, $map, $path, $data );
			
			$libraryClass->setLastKeyValue( $attachment['nodeid'] );
		}
	}
	
	/* !vBulletin Stuff */
	
	/**
	 * @brief	Silly Bitwise
	 */
	public static $bitOptions = array (
		'forumoptions' => array(
			'active' => 1,
			'allowposting' => 2,
			'cancontainthreads' => 4,
			'moderatenewpost' => 8,
			'moderatenewthread' => 16,
			'moderateattach' => 32,
			'allowbbcode' => 64,
			'allowimages' => 128,
			'allowhtml' => 256,
			'allowsmilies' => 512,
			'allowicons' => 1024,
			'allowratings' => 2048,
			'countposts' => 4096,
			'canhavepassword' => 8192,
			'indexposts' => 16384,
			'styleoverride' => 32768,
			'showonforumjump' => 65536,
			'prefixrequired' => 131072,
			'allowvideos' => 262144,
			'bypassdp' => 524288,
			'displaywrt' => 1048576,
			'canreputation' => 2097152,
		),
	);
	
	/**
	 * Helper method to retrieve forums from nodes
	 *
	 * @return array
	 */
	protected function fetchForums()
	{
		$forums = array();
		$clubs = $this->fetchClubs();
		foreach( $this->db->select( '*', 'node', array( "node.nodeid<>2 AND ( ( closure.parent=? AND node.contenttypeid=? ) OR " . $this->db->in( 'nodeid', array_keys( $clubs ) ) . " )", $this->fetchType( 'Thread' ), $this->fetchType( 'Channel' ) ) )->join( 'closure', "closure.child = node.nodeid" ) AS $node )
		{
			$forums[$node['nodeid']] = $node;
		}

		return $forums;
	}
	
	/**
	 * Fetch Clubs
	 *
	 * @return	array
	 */
	protected function fetchClubs()
	{
		$clubs = array();
	
		try
		{
			/* Get the main social group node ID from the channel GUID. */
			$mainNodeId = $this->db->select( 'nodeid', 'channel', array( "guid=?", \IPS\convert\Software\Core\Vbulletin5::$guids['clubs'] ) )->first();
			
			$categories = array();
			foreach( $this->db->select( 'nodeid', 'node', array( "parentid=?", $mainNodeId ) ) AS $cat )
			{
				$categories[] = $cat;
			}
			
			/* The actual clubs are all two levels deep from the main channel node, so we need to sub-query the categories. */
			foreach( $this->db->select( '*', 'node', array( $this->db->in( 'parentid', $categories ) ) ) AS $row )
			{
				$clubs[ $row['nodeid'] ] = $row;
			}
		}
		catch( \UnderflowException $e ) { /* Just in case something goofy happened and there is no main channel */ }
		
		return $clubs;
	}
	
	/**
	 * @brief	Types Cache
	 */
	protected $typesCache = array();

	/**
	 * Helper method to retrieve content type ids
	 *
	 * @param	int|string	$type	The content type
	 * @return void
	 */
	protected function fetchType( $type )
	{
		if ( \count( $this->typesCache ) )
		{
			return $this->typesCache[ $type ];
		}
		else
		{
			foreach( $this->db->select( '*', 'contenttype' ) AS $contenttype )
			{
				$this->typesCache[ $contenttype['class'] ] = $contenttype['contenttypeid'];
			}

			return $this->typesCache[ $type ];
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 * @note	Forum URLs do not have IDs in them, so we cannot redirect them reliably
	 */
	public function checkRedirects()
	{
		$url = \IPS\Request::i()->url();

		if(
			( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'showthread.php' ) !== FALSE OR
				mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'showpost.php' ) !== FALSE OR
				preg_match( '#/threads/([0-9]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ] )
			)
			AND ( isset( \IPS\Request::i()->p ) OR isset( \IPS\Request::i()->postid ) )
		)
		{
			try
			{
				$postId = \IPS\Request::i()->postid ?? \IPS\Request::i()->p;
				try
				{
					$data = (string) $this->app->getLink( $postId, array( 'forums_posts_old' ) );
				}
				catch( \OutOfRangeException $e )
				{
					/* Try the main table */
					$data = (string) $this->app->getLink( $postId, array( 'forums_posts_old' ), FALSE, TRUE );
				}
				$item = \IPS\forums\Topic\Post::load( $data );

				if( $item->canView() )
				{
					return $item->url();
				}
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}
		/* And then topic URLs, same idea */
		elseif( ( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'showthread.php' ) !== FALSE OR
			mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'printthread.php' ) !== FALSE )
			AND ( isset( \IPS\Request::i()->t ) OR isset( \IPS\Request::i()->threadid ) )
		)
		{
			/* Topic URLs.
			 * /showthread.php?t=1
			 * /printthread.php?t=1
			 * /showthread.php?threadid=?
			 * /printthread.php?threadid=?
			 */
			$path = $url->data[ \IPS\Http\Url::COMPONENT_PATH ];
			if( mb_strpos( $path, 'showthread.php' ) !== FALSE OR mb_strpos( $path, 'printthread.php' ) !== FALSE )
			{
				if( isset( \IPS\Request::i()->t ) )
				{
					$oldId	= \IPS\Request::i()->t;
				}
				elseif( isset( \IPS\Request::i()->threadid ) )
				{
					$oldId	= \IPS\Request::i()->threadid;
				}
				elseif( preg_match( '#^(\d+)-[^/]+#i', $url->data[ \IPS\Http\Url::COMPONENT_QUERY ], $matches ) )
				{
					$oldId = $matches[1];
				}
				else
				{
					$queryStringPieces	= explode( '-', mb_substr( $path, mb_strpos( $path, 'showthread.php/' ) + mb_strlen( 'showthread.php/' ) ) );
					$oldId				= $queryStringPieces[0];
				}
			}

			if( isset( $oldId ) )
			{
				try
				{
					try
					{
						$data = (string) $this->app->getLink( $oldId, array( 'forums_topics_old' ) );
					}
					catch( \OutOfRangeException $e )
					{
						/* Try the main table */
						$data = (string) $this->app->getLink( $oldId, array( 'forums_topics_old' ), FALSE, TRUE );
					}
					$item = \IPS\forums\Topic::load( $data );

					if( $item->canView() )
					{
						return $item->url();
					}
				}
				catch( \Exception $e )
				{
					return NULL;
				}
			}
		}

		if( preg_match( '#(?<!topic|file|image)/([0-9]+)\-([^/]*?)#', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
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
				return NULL;
			}
		}

		return NULL;
	}
}