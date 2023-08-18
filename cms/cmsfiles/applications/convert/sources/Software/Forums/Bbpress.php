<?php

/**
 * @brief		Converter bbPress Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Nov 2016
 */

namespace IPS\convert\Software\Forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * BBPress Forums Converter
 */
class _Bbpress extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "bbPress (for WordPress)";
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "bbpress";
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
				'table'						=> 'posts',
				'where'						=> array( 'post_type=?', 'forum' ),
			),
			'convertForumsTopics'		=> array(
				'table'						=> 'posts',
				'where'						=> array( 'post_type=?', 'topic' ),
			),
			'convertForumsPosts'		=> array(
				'table'						=> 'posts',
				'where'						=> array( '(post_type=? OR post_type=?)', 'topic', 'reply' ),
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
		return array( 'core' => array( 'wordpress' ) );
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
	 * Convert forums
	 *
	 * @return	void
	 */
	public function convertForumsForums()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'ID' );

		foreach( $this->fetch( 'posts', 'ID', array( 'post_type=?', 'forum' )  ) AS $row )
		{
			$info = array(
				'id'				=> $row['ID'],
				'name'				=> $row['post_title'],
				'description'		=> $row['post_content'],
				'parent_id'			=> $row['post_parent'] ?: NULL,
				'sub_can_post'		=> 1
			);

			$libraryClass->convertForumsForum( $info );
			$libraryClass->setLastKeyValue( $row['ID'] );
		}
	}

	/**
	 * @brief	User cache to minimise repeated DB lookups
	 */
	protected static $userCache = array();

	/**
	 * Convert topics
	 *
	 * @return	void
	 */
	public function convertForumsTopics()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'ID' );

		/* Which username type? */
		$userName = ( isset( $this->app->_parent->_session['more_info']['convertMembers']['username'] ) AND  $this->app->_parent->_session['more_info']['convertMembers']['username'] == 'username') ? 'user_login' : 'display_name';

		foreach( $this->fetch( 'posts', 'ID', array( 'post_type=?', 'topic' ) ) AS $row )
		{
			if( !isset( static::$userCache[ $row['post_author'] ] ) )
			{
				try
				{
					static::$userCache[ $row['post_author'] ] = $this->db->select( $userName, 'users', array( 'ID=?', $row['post_author'] ) )->first();
				}
				catch( \UnderflowException $e )
				{
					static::$userCache[ $row['post_author'] ] = 'Guest';
				}
			}

			$info = array(
				'tid'				=> $row['ID'],
				'title'				=> $row['post_title'],
				'forum_id'			=> $row['post_parent'],
				'state'				=> ( $row['post_status'] == 'publish' ) ? 'open' : 'closed',
				'starter_id'		=> $row['post_author'],
				'start_date'		=> \strtotime( $row['post_date'] ),
				'starter_name'		=> static::$userCache[ $row['post_author'] ],
			);

			$libraryClass->convertForumsTopic( $info );
			$libraryClass->setLastKeyValue( $row['ID'] );
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
		$libraryClass::setKey( 'ID' );

		/* Which username type? */
		$userName = ( isset( $this->app->_parent->_session['more_info']['convertMembers']['username'] ) AND  $this->app->_parent->_session['more_info']['convertMembers']['username'] == 'username') ? 'user_login' : 'display_name';

		foreach( $this->fetch( 'posts', 'ID', array( '(post_type=? OR post_type=?)', 'topic', 'reply' ) ) AS $row )
		{
			if( !isset( static::$userCache[ $row['post_author'] ] ) )
			{
				try
				{
					static::$userCache[ $row['post_author'] ] = $this->db->select( $userName, 'users', array( 'ID=?', $row['post_author'] ) )->first();
				}
				catch( \UnderflowException $e )
				{
					static::$userCache[ $row['post_author'] ] = 'Guest';
				}
			}

			$info = array(
				'pid'			=> $row['ID'],
				'topic_id'		=> $row['post_type'] == 'topic' ? $row['ID'] : $row['post_parent'],
				'post'			=> $row['post_content'],
				'author_id'		=> $row['post_author'],
				'author_name'	=> static::$userCache[ $row['post_author'] ],
				'post_date'		=> \strtotime( $row['post_date'] )
			);

			$libraryClass->convertForumsPost( $info );
			$libraryClass->setLastKeyValue( $row['ID'] );
		}
	}
}