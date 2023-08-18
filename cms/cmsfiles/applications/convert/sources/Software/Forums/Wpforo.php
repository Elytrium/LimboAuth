<?php

/**
 * @brief		Converter wpForo Forum Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		24 Jan 2021
 */

namespace IPS\convert\Software\Forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * wpForo Forums Converter
 */
class _Wpforo extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return    string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "wpForo (2.1.x)";
	}

	/**
	 * Software Key
	 *
	 * @return    string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "wpforo";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return    array
	 */
	public static function canConvert()
	{
		return [
			'convertForumsForums' => [
				'table' => 'wpforo_forums',
				'where' => NULL
			],
			'convertForumsTopics' => [
				'table' => 'wpforo_topics',
				'where' => NULL
			],
			'convertForumsPosts' => [
				'table' => 'wpforo_posts',
				'where' => NULL
			],
			'convertAttachments' => [
				'table' => 'wpforo_posts',
				'where' => NULL
			]
		];
	}

	/**
	 * Requires Parent
	 *
	 * @return    boolean
	 */
	public static function requiresParent()
	{
		return TRUE;
	}

	/**
	 * Possible Parent Conversions
	 *
	 * @return    array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'wpforo' ) );
	}

	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return    array        Messages to display
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
		\IPS\Db::i()->delete( 'core_reputation_leaderboard_history' );

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
		return \IPS\convert\Software\Core\Wpforo::fixPostData( $post );
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
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
		$return = [];
		switch( $method )
		{
			case 'convertForumsPosts':
				/* Get our reactions to let the admin map them */
				$options		= [];
				$descriptions	= [];
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_reactions' ), 'IPS\Content\Reaction' ) AS $reaction )
				{
					$options[ $reaction->id ]		= $reaction->_icon->url;
					$descriptions[ $reaction->id ]	= \IPS\Member::loggedIn()->language()->addToStack('reaction_title_' . $reaction->id ) . '<br>' . $reaction->_description;
				}

				$return['convertForumsPosts'] = [
					'rep_positive'	=> [
						'field_class'		=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'		=> NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> [ 'parse' => 'image', 'options' => $options, 'descriptions' => $descriptions, 'gridspan' => 2 ],
						'field_hint'		=> NULL,
						'field_validation'	=> NULL,
					],
				];
				break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : [];
	}

	/**
	 * Convert forums
	 *
	 * @return	void
	 */
	public function convertForumsForums()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'forumid' );

		foreach( $this->fetch( 'wpforo_forums', 'forumid' ) AS $row )
		{
			$info = [
				'id'					=> $row['forumid'],
				'name'					=> $row['title'],
				'description'			=> $row['description'],
				'topics'				=> $row['topics'],
				'posts'					=> $row['posts'],
				'parent_id'				=> ( !$row['parentid'] OR $row['parentid'] == $row['forumid'] ) ? -1 : $row['parentid'],
				'position'				=> $row['order'],
				'sub_can_post'			=> ( !$row['parentid'] OR $row['is_cat'] ) ? 0 : 1,
				'feature_color'         => $row['color']
			];

			$forumId = $libraryClass->convertForumsForum( $info );

			if( $forumId !== FALSE )
			{
				$this->app->addLink( $forumId, $row['slug'], 'forum_furl' );

				/* Followers */
				foreach ( $this->db->select( '*', 'wpforo_subscribes', [ 'itemid=? AND type=?', $row['forumid'], 'forum' ] ) as $follow )
				{
					$libraryClass->convertFollow( [
						'follow_app' => 'forums',
						'follow_area' => 'forum',
						'follow_rel_id' => $row['forumid'],
						'follow_rel_id_type' => 'forums_forums',
						'follow_member_id' => $follow['userid'],
						'follow_is_anon' => 0,
						'follow_added' => time(),
						'follow_notify_do' => $follow['active'],
						'follow_notify_meta' => '',
						'follow_notify_freq' => 'immediate',
						'follow_notify_sent' => 0,
						'follow_visible' => 1,
					] );
				}
			}

			$libraryClass->setLastKeyValue( $row['forumid'] );
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
		$libraryClass::setKey( 'topicid' );

		foreach( $this->fetch( 'wpforo_topics', 'topicid' ) AS $row )
		{
			$info = [
				'tid'					=> $row['topicid'],
				'title'					=> $row['title'],
				'forum_id'				=> $row['forumid'],
				'state'					=> ( $row['closed'] == 1 ) ? 'closed' : 'open',
				'starter_id'			=> $row['userid'],
				'start_date'			=> \strtotime( $row['created'] ),
				'posts'                 => $row['posts'],
				'views'					=> $row['views'],
				'approved'				=> $row['private'] ? 0 : 1
			];

			$topicId = $libraryClass->convertForumsTopic( $info );

			if( $topicId !== FALSE )
			{
				$this->app->addLink( $topicId, $row['slug'], 'topic_furl' );

				/* Tags */
				$tags = explode( ',', $row['tags'] );
				foreach( $tags as $text )
				{
					$libraryClass->convertTag( [
						'tag_meta_app' 			=> 'forums',
						'tag_meta_area' 		=> 'forums',
						'tag_meta_parent_id' 	=> $row['forumid'],
						'tag_meta_id' 			=> $row['topicid'],
						'tag_text' 				=> $text,
						'tag_member_id' 		=> $row['userid'],
						'tag_added'             => $info['start_date']
					] );
				}

				/* Follows */
				foreach( $this->db->select( '*', 'wpforo_subscribes', [ 'itemid=? AND type=?', $row['topicid'], 'topic' ] ) AS $follow )
				{
					$libraryClass->convertFollow( [
						'follow_app'			=> 'forums',
						'follow_area'			=> 'topic',
						'follow_rel_id'			=> $row['topicid'],
						'follow_rel_id_type'	=> 'forums_topics',
						'follow_member_id'		=> $follow['userid'],
						'follow_is_anon'		=> 0,
						'follow_added'			=> time(),
						'follow_notify_do'		=> $follow['active'],
						'follow_notify_meta'	=> '',
						'follow_notify_freq'	=> 'immediate',
						'follow_notify_sent'	=> 0,
						'follow_visible'		=> 1,
					] );
				}
			}

			$libraryClass->setLastKeyValue( $row['topicid'] );
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
		$libraryClass::setKey( 'postid' );

		foreach( $this->fetch( 'wpforo_posts', 'postid' ) AS $row )
		{
			$info = [
				'pid'				=> $row['postid'],
				'topic_id'			=> $row['topicid'],
				'post'				=> $row['body'],
				'edit_time'			=> $row['modified'] != $row['created'] ? \strtotime( $row['modified'] ) : null,
				'author_id'			=> $row['userid'],
				'post_date'			=> \strtotime( $row['created'] ),
				'queued'			=> $row['private'] ? 1 : 0,
			];

			$libraryClass->convertForumsPost( $info );

			/* Reputation */
			foreach( $this->db->select( '*', 'wpforo_reactions', [ 'postid=? AND type=?', $row['postid'], 'up' ] ) AS $rep )
			{
				$libraryClass->convertReputation( [
					'id'				=> $rep['reactionid'],
					'app'				=> 'forums',
					'type'				=> 'pid',
					'type_id'			=> $row['postid'],
					'member_id'			=> $rep['userid'],
					'member_received'	=> $rep['post_userid'],
					'rep_date'			=> $info['post_date'],
					'reaction'			=> $this->app->_session['more_info']['convertForumsPosts']['rep_positive']
				] );
			}

			$libraryClass->setLastKeyValue( $row['postid'] );
		}
	}

	/**
	 * @brief   temporarily store post content
	 */
	protected $_postContent = [];

	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'postid' );

		foreach( $this->fetch( 'wpforo_posts', 'postid' ) AS $row )
		{
			if( !\stristr( $row['body'], '[attach]' ) AND !\stristr( $row['body'], 'wpforo-attached-file' ) )
			{
				$libraryClass->setLastKeyValue( $row['postid'] );
				continue;
			}

			$map = [
				'id1'				=> $row['topicid'],
				'id2'				=> $row['postid']
			];

			/* Advanced Attachments */
			$matches = [];
			preg_match_all( '/\[attach\](\d+)\[\/attach\]/i', $row['body'], $matches );

			if( \count( $matches ) )
			{
				foreach( $matches[1] as $key => $id )
				{
					$sourceAttachment = $this->db->select( '*', 'wpforo_attachments', [ 'attachid=?', $id ] )->first();
					$url = explode( '/', $sourceAttachment['fileurl'] );
					$filename = array_pop( $url );

					$info = [
						'attach_id'			=> $row['postid'],
						'attach_file'		=> $sourceAttachment['filename'],
						'attach_date'		=> \strtotime( $row['created'] ),
						'attach_member_id'	=> $sourceAttachment['userid'],
						'attach_filesize'	=> $sourceAttachment['size'],
					];

					$realFilePath = '/wpforo/attachments/' . $sourceAttachment['userid'] . '/' . $filename;
					$path = rtrim( $this->app->_parent->_session['more_info']['convertMembers']['wpuploads'], '/' ) . $realFilePath;

					$attachId = $libraryClass->convertAttachment( $info, $map, $path );

					/* Update post if we can */
					try
					{
						if ( $attachId !== FALSE )
						{
							$pid = $this->app->getLink( $row['postid'], 'forums_posts' );

							if( !isset( $this->_postContent[ $pid ] ) )
							{
								$this->_postContent[ $pid ] = \IPS\Db::i()->select( 'post', 'forums_posts', array( "pid=?", $pid ) )->first();
							}

							$this->_postContent[ $pid ] = str_replace( $matches[0][ $key ], '[attachment=' . $attachId . ':name]', $this->_postContent[ $pid ] );
						}
					}
					catch( \UnderflowException $e ) {}
					catch( \OutOfRangeException $e ) {}
				}
			}

			/* Default Attachments */
			$matches = [];
			preg_match_all( '/\<div id\="wpfa\-[\d]+"(.+?)?>\<a class\="wpforo\-default\-attachment" href\="(.+?)"(.+?)?>\<i class\="(.+?)">\<\/i>(.+?)<\/a><\/div>/i', $row['body'], $matches );

			if( \count( $matches ) )
			{
				foreach( $matches[2] as $key => $url )
				{
					$url = explode( '/', $url );
					$filename = array_pop( $url );
					$info = [
						'attach_id'			=> $row['postid'],
						'attach_file'		=> $filename,
						'attach_date'		=> \strtotime( $row['created'] ),
						'attach_member_id'	=> $row['userid'],
					];

					$realFilePath = '/wpforo/default_attachments/' . $filename;
					$path = rtrim( $this->app->_parent->_session['more_info']['convertMembers']['wpuploads'], '/' ) . $realFilePath;

					$attachId = $libraryClass->convertAttachment( $info, $map, $path );

					/* Update post if we can */
					try
					{
						if ( $attachId !== FALSE )
						{
							$pid = $this->app->getLink( $row['postid'], 'forums_posts' );

							if( !isset( $this->_postContent[ $pid ] ) )
							{
								$this->_postContent[ $pid ] = \IPS\Db::i()->select( 'post', 'forums_posts', array( "pid=?", $pid ) )->first();
							}

							$this->_postContent[ $pid ] = str_replace( $matches[0][ $key ], '[attachment=' . $attachId . ':name]', $this->_postContent[ $pid ] );
						}
					}
					catch( \UnderflowException $e ) {}
					catch( \OutOfRangeException $e ) {}
				}
			}

			$libraryClass->setLastKeyValue( $row['postid'] );
		}

		/* Do the updates */
		foreach( $this->_postContent as $id => $content )
		{
			\IPS\Db::i()->update( 'forums_posts', array( 'post' => $content ), array( 'pid=?', $id ) );
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
		$wpForoSlug = \defined('WPFORO_SLUG') ? WPFOROSLUG : 'community';

		$matches = [];
		if( preg_match( '#/' . $wpForoSlug . '/([a-z0-9-]+)/([a-z0-9-]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			$class	= '\IPS\forums\Topic';
			$types	= [ 'topic_furl' ];
			$oldId	= $matches[2];
		}
		elseif( preg_match( '#/' . $wpForoSlug . '/([a-z0-9-]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			$class	= '\IPS\forums\Forum';
			$types	= [ 'forum_furl' ];
			$oldId	= $matches[1];
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