<?php

/**
 * @brief		Converter ExpressionEngine Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		17 June 2016
 */

namespace IPS\convert\Software\Forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ExpressionEngine Forums Converter
 */
class _Expressionengine extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "ExpressionEngine";
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "expressionengine";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertForumsForums' => array(
				'table'					=> 'exp_forums',
				'where'					=> NULL
			),
			'convertForumsTopics'	=> array(
				'table'					=> 'exp_forum_topics',
				'where'					=> NULL
			),
			'convertForumsPosts'	=> array(
				'table'					=> 'exp_forum_topics',
				'where'					=> NULL,
				'extra_steps'			=> array( 'convertOtherPosts' )
			),
			'convertOtherPosts' => array(
				'table'					=> 'exp_forum_posts',
				'where'					=> NULL,
			),
			'convertAttachments'	=> array(
				'table'					=> 'expForumAttachments',
				'where'					=> NULL
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
		$rows['convertOtherPosts'] = array(
			'step_title'		=> 'convert_forums_posts',
			'step_method'		=> 'convertOtherPosts',
			'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'forums_posts' ),
			'source_rows'		=> array( 'table' => static::canConvert()['convertOtherPosts']['table'], 'where' => static::canConvert()['convertOtherPosts']['where'] ),
			'per_cycle'			=> 200,
			'dependencies'		=> array( 'convertForumsPosts' ),
			'link_type'			=> 'forums_posts',
			'requires_rebuild'	=> TRUE
		);

		return $rows;
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
		return array( 'core' => array( 'expressionengine' ) );
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
					'attach_location' => array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Text',
						'field_default'			=> NULL,
						'field_required'		=> TRUE,
						'field_extra'			=> array(),
						'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_ee_forum_at_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					)
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
	 * @param 	string		$post	Raw post data
	 * @return 	string		Parsed post data
	 */
	public static function fixPostData( $post )
	{
		return \IPS\convert\Software\Core\Expressionengine::fixPostData( $post );
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

		foreach( $this->fetch( 'exp_forums', 'forum_id' ) AS $row )
		{
			$info = array(
				'id'				=> $row['forum_id'],
				'name'				=> $row['forum_name'],
				'description'		=> $row['forum_description'],
				'topics'			=> $row['forum_total_topics'],
				'posts'				=> $row['forum_total_posts'],
				'last_post'			=> $row['forum_last_post_date'],
				'last_poster_id'	=> $row['forum_last_post_author_id'],
				'last_poster_name'	=> $row['forum_last_post_author'],
				'parent_id'			=> ( $row['forum_parent'] == 0 ) ? -1 : $row['forum_parent'],
				'position'			=> $row['forum_order'],
				'last_title'		=> $row['forum_last_post_title'],
				'allow_poll'		=> 1,
				'inc_postcount'		=> 1,
				'sub_can_post'		=> 1,
			);

			$libraryClass->convertForumsForum( $info );

			$libraryClass->setLastKeyValue( $row['forum_id'] );
		}
	}

	/**
	 * Convert forums
	 *
	 * @return	void
	 */
	public function convertForumsTopics()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'topic_id' );

		foreach( $this->fetch( 'exp_forum_topics', 'topic_id' )->join( 'exp_members', 'exp_members.member_id = exp_forum_topics.author_id' ) AS $row )
		{
			/* Poll */
			$poll = NULL;

			if( $row['poll'] == 'y' )
			{
				$choices	= array();
				$votes		= array();
				$index		= 1;

				$pollData	= $this->db->select( '*', 'exp_forum_polls', array( 'topic_id=?', $row['topic_id'] ) )->first();

				foreach( \unserialize( $pollData['poll_answers'] ) as $value )
				{
					$choices[ $index ] = $value['answer'];
					$votes[ $index ] = $value['votes'];
					$index++;
				}

				$poll['poll_data'] = array(
					'pid'		=> $pollData['poll_id'],
					'choices'	=> array(
						1 => array(
							'question'	=> $pollData['poll_question'],
							'multi'		=> 0,
							'choice'	=> $choices,
							'votes'		=> $votes
						)
					),
					'poll_question'		=> $pollData['poll_question'],
					'start_date'		=> $pollData['poll_date'],
					'starter_id'		=> $pollData['author_id'],
					'votes'				=> array_sum( $votes ),
					'poll_view_voters'	=> 0
				);

				$poll['vote_data']	= array();
				$ourVotes			= array();
				foreach( $this->db->select( '*', 'exp_forum_pollvotes', array( "poll_id=?", $pollData['poll_id'] ) ) AS $vote )
				{
					if ( !isset( $ourVotes[ $vote['member_id'] ] ) )
					{
						$ourVotes[ $vote['member_id'] ] = array( 'votes' => array() );
					}

					$ourVotes[ $vote['member_id'] ]['votes'][]		= ( $vote['choice_id'] + 1 );
					$ourVotes[ $vote['member_id'] ]['member_id']	= $vote['member_id'];
				}

				foreach( $ourVotes AS $memberId => $vote )
				{
					$poll['vote_data'][ $memberId ] = array(
						'member_id'			=> $vote['member_id'],
						'member_choices'	=> array( 1 => $vote['votes'] ),
					);
				}
			}

			$lastPoster = NULL;
			try
			{
				if( $row['last_post_author_id'] )
				{
					$lastPoster = $this->db->select( 'screen_name', 'exp_members', array( 'member_id=?', $row['last_post_author_id'] ) )->first();
				}
			}
			catch( \UnderflowException $e ) { }

			$info = array(
				'tid'				=> $row['topic_id'],
				'title'				=> $row['title'],
				'forum_id'			=> $row['forum_id'],
				'state'				=> ( $row['status'] == 'o' ) ? 'open' : 'closed',
				'posts'				=> $row['thread_total'],
				'starter_id'		=> $row['author_id'],
				'start_date'		=> $row['topic_date'],
				'last_poster_id'	=> $row['last_post_author_id'],
				'last_post'			=> $row['last_post_date'],
				'starter_name'		=> $row['screen_name'],
				'last_poster_name'	=> $lastPoster,
				'poll_state'		=> $poll,
				'views'				=> $row['thread_views'],
				'approved'			=> 1,
				'pinned'			=> ( $row['sticky'] == 'y' ) ? 1 : 0
			);

			$libraryClass->convertForumsTopic( $info );

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

		$libraryClass::setKey( 'topic_id' );

		foreach( $this->fetch( 'exp_forum_topics', 'topic_id', NULL, 'exp_forum_topics.*, exp_members.screen_name' )
			->join( 'exp_members', 'exp_members.member_id = exp_forum_topics.author_id' ) AS $row )
		{
			$editName = NULL;
			try
			{
				if( $row['topic_edit_date'] )
				{
					$editName = $this->db->select( 'screen_name', 'exp_members', array( 'member_id=?', $row['topic_edit_author'] ) )->first();
				}
			}
			catch( \UnderflowException $e ) { }

			$info = array(
				'pid'			=> 't' . $row['topic_id'],
				'topic_id'		=> $row['topic_id'],
				'post'			=> $this->fixPostData( $row['body'] ),
				'edit_time'		=> $row['topic_edit_date'],
				'edit_name'		=> $editName,
				'author_id'		=> $row['author_id'],
				'author_name'	=> $row['screen_name'],
				'ip_address'	=> $row['ip_address'],
				'post_date'		=> $row['topic_date'],
				'queued'		=> 0
			);

			$libraryClass->convertForumsPost( $info );
			$libraryClass->setLastKeyValue( $row['topic_id'] );
		}
	}

	/**
	 * Convert other posts
	 *
	 * @return	void
	 */
	public function convertOtherPosts()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'post_id' );

		foreach( $this->fetch( 'exp_forum_posts', 'post_id', NULL, 'exp_forum_posts.*, exp_members.screen_name' )
			->join( 'exp_members', 'exp_members.member_id = exp_forum_posts.author_id' ) AS $row )
		{
			$editName = NULL;
			try
			{
				if( $row['post_edit_date'] )
				{
					$editName = $this->db->select( 'screen_name', 'exp_members', array( 'member_id=?', $row['post_edit_author'] ) )->first();
				}
			}
			catch( \UnderflowException $e ) { }

			$info = array(
				'pid'			=> $row['post_id'],
				'topic_id'		=> $row['topic_id'],
				'post'			=> $this->fixPostData( $row['body'] ),
				'edit_time'		=> $row['post_edit_date'],
				'edit_name'		=> $editName,
				'author_id'		=> $row['author_id'],
				'author_name'	=> $row['screen_name'],
				'ip_address'	=> $row['ip_address'],
				'post_date'		=> $row['post_date'],
				'queued'		=> 0
			);

			$libraryClass->convertForumsPost( $info );
			$libraryClass->setLastKeyValue( $row['post_id'] );
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

		$libraryClass::setKey( 'attachment_id' );

		$it = $this->fetch( 'exp_forum_attachments', 'attachment_id' );

		foreach( $it AS $row )
		{
			$map = array(
				'id1'	=> $row['topic_id'],
				'id2'	=> $row['post_id'],
			);

			$info = array(
				'attach_id'			=> $row['attachment_id'],
				'attach_file'		=> $row['filename'],
				'attach_date'		=> $row['attachment_date'],
				'attach_member_id'	=> $row['member_id'],
				'attach_hits'		=> $row['hits'],
				'attach_ext'		=> \strtolower( trim( $row['extension'], '.' ) ),
				'attach_filesize'	=> ( $row['filesize'] * 1000 ), //convert to kb
			);

			$realName	= $row['filehash'] . $row['extension'];
			$path		= rtrim( $this->app->_session['more_info']['convertAttachments']['attach_location'], '/' ) . '/' . $realName;

			$libraryClass->convertAttachment( $info, $map, $path );

			$libraryClass->setLastKeyValue( $row['attachment_id'] );
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

		if( preg_match( '#/(viewforum|viewthread|viewreply)/([0-9]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			switch( $matches[1] )
			{
				case 'viewforum':
					$class	= '\IPS\forums\Forum';
					$types	= array( 'forums', 'forums_forums' );
				break;

				case 'viewthread':
					$class	= '\IPS\forums\Topic';
					$types	= array( 'topics', 'forums_topics' );
				break;

				case 'viewreply':
					$class	= '\IPS\forums\Topic\Post';
					$types	= array( 'posts', 'forums_posts' );
				break;
			}

			/* Sanity check - we found one right? */
			if( !isset( $class ) )
			{
				return NULL;
			}

			try
			{
				try
				{
					$data = (string) $this->app->getLink( (int) $matches[2], $types );
				}
				catch( \OutOfRangeException $e )
				{
					$data = (string) $this->app->getLink( (int) $matches[2], $types, FALSE, TRUE );
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