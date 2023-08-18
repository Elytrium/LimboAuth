<?php
/**
 * @brief		Posts API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		7 Dec 2015
 */

namespace IPS\forums\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Posts API
 */
class _posts extends \IPS\Content\Api\CommentController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\forums\Topic\Post';
	
	/**
	 * GET /forums/posts
	 * Get list of posts
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only posts the authorized user can view will be included
	 * @apiparam	string	forums			Comma-delimited list of forum IDs
	 * @apiparam	string	authors			Comma-delimited list of member IDs - if provided, only posts by those members are returned
	 * @apiparam	int		hasBestAnswer	If 1, only posts from topics with a best answer are returned, if 0 only without
	 * @apiparam	int		hasPoll			If 1, only posts from  topics with a poll are returned, if 0 only without
	 * @apiparam	int		locked			If 1, only posts from  topics which are locked are returned, if 0 only unlocked
	 * @apiparam	int		hidden			If 1, only posts which are hidden are returned, if 0 only not hidden
	 * @apiparam	int		pinned			If 1, only posts from  topics which are pinned are returned, if 0 only not pinned
	 * @apiparam	int		featured		If 1, only posts from  topics which are featured are returned, if 0 only not featured
	 * @apiparam	int		archived		If 1, only posts from  topics which are archived are returned, if 0 only not archived
	 * @apiparam	string	sortBy			What to sort by. Can be 'date', 'title' or leave unspecified for ID
	 * @apiparam	string	sortDir			Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page			Page number
	 * @apiparam	int		perPage			Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\forums\Topic\Post>
	 */
	public function GETindex()
	{
		/* Init */
		$where = array();
		
		/* Has best answer */
		if ( isset( \IPS\Request::i()->hasBestAnswer ) )
		{
			if ( \IPS\Request::i()->hasBestAnswer )
			{
				$where[] = array( "topic_answered_pid>0" );
			}
			else
			{
				$where[] = array( "topic_answered_pid=0" );
			}
		}
		
		/* Archived */
		if ( isset( \IPS\Request::i()->archived ) )
		{
			if ( \IPS\Request::i()->archived )
			{
				$where[] = array( \IPS\Db::i()->in( 'topic_archive_status', array( \IPS\forums\Topic::ARCHIVE_DONE, \IPS\forums\Topic::ARCHIVE_WORKING, \IPS\forums\Topic::ARCHIVE_RESTORE ) ) );
			}
			else
			{
				$where[] = array( \IPS\Db::i()->in( 'topic_archive_status', array( \IPS\forums\Topic::ARCHIVE_NOT, \IPS\forums\Topic::ARCHIVE_EXCLUDE ) ) );
			}
		}
		
		/* Return */
		return $this->_list( $where, 'forums' );
	}
	
	/**
	 * GET /forums/posts/{id}
	 * View information about a specific post
	 *
	 * @param		int		$id			ID Number
	 * @throws		1F295/4	INVALID_ID	The post ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\forums\Topic\Post
	 */
	public function GETitem( $id )
	{
		try
		{
			$class = $this->class;
			if ( $this->member )
			{
				$object = $class::loadAndCheckPerms( $id, $this->member );
			}
			else
			{
				$object = $class::load( $id );
			}
			
			return new \IPS\Api\Response( 200, $object->apiOutput( $this->member ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1F295/4', 404 );
		}
	}

	/**
	 * POST /forums/posts/{id}/react
	 * Add a reaction
	 *
	 * @param		int		$id			ID Number
	 * @apiparam	int		id			ID of the reaction to add
	 * @apiparam	int     author      ID of the member reacting
	 * @return		\IPS\forums\Topic\Post
	 * @throws		1S425/2		NO_REACTION	The reaction ID does not exist
	 * @throws		1S425/3		NO_AUTHOR	The author ID does not exist
	 * @throws		1S425/4		REACT_ERROR	Error adding the reaction
	 * @throws		1S425/5		INVALID_ID	Object ID does not exist
	 * @note		If the author has already reacted to this content, any existing reaction will be removed first
	 */
	public function POSTitem_react( $id )
	{
		return $this->_reactAdd( $id );
	}

	/**
	 * DELETE /forums/posts/{id}/react
	 * Delete a reaction
	 *
	 * @param		int		$id			ID Number
	 * @apiparam	int     author      ID of the member who reacted
	 * @return		\IPS\forums\Topic\Post
	 * @throws		1S425/6		NO_AUTHOR	The author ID does not exist
	 * @throws		1S425/7		REACT_ERROR	Error adding the reaction
	 * @throws		1S425/8		INVALID_ID	Object ID does not exist
	 * @note		If the author has already reacted to this content, any existing reaction will be removed first
	 */
	public function DELETEitem_react( $id )
	{
		return $this->_reactRemove( $id );
	}

	/**
	 * POST /forums/posts
	 * Create a post
	 *
	 * @note	For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, hidden will only be honoured if the authenticated user has permission to hide content).
	 * @reqapiparam	int			topic				The ID number of the topic the post should be created in
	 * @reqapiparam	int			author				The ID number of the member making the post (0 for guest). Required for requests made using an API Key or the Client Credentials Grant Type. For requests using an OAuth Access Token for a particular member, that member will always be the author
	 * @apiparam	string		author_name			If author is 0, the guest name that should be used
	 * @reqapiparam	string		post				The post content as HTML (e.g. "<p>This is a post.</p>"). Will be sanatized for requests using an OAuth Access Token for a particular member; will be saved unaltered for requests made using an API Key or the Client Credentials Grant Type. 
	 * @apiparam	datetime	date				The date/time that should be used for the topic/post post date. If not provided, will use the current date/time. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	string		ip_address			The IP address that should be stored for the topic/post. If not provided, will use the IP address from the API request. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	int			hidden				0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	bool		anonymous		If 1, the item will be posted anonymously.
	 * @throws		1F295/1		NO_TOPIC	The topic ID does not exist
	 * @throws		1F295/2		NO_AUTHOR	The author ID does not exist
	 * @throws		1F295/3		NO_POST		No post was supplied
	 * @throws		2F294/A		NO_PERMISSION	The authorized user does not have permission to reply to that topic
	 * @throws		3F295/C		NO_ANON_PERMISSION	The topic is set for anonymous posting, but the author does not have permission to post anonymously
	 * @return		\IPS\forums\Topic\Post
	 */
	public function POSTindex()
	{
		/* Get topic */
		try
		{
			$topic = \IPS\forums\Topic::load( \IPS\Request::i()->topic );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_TOPIC', '1F295/1', 403 );
		}
		
		/* Get author */
		if ( $this->member )
		{
			if ( !$topic->canComment( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2F294/A', 403 );
			}
			$author = $this->member;
		}
		else
		{
			if ( \IPS\Request::i()->author )
			{
				$author = \IPS\Member::load( \IPS\Request::i()->author );
				if ( !$author->member_id )
				{
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1F295/2', 403 );
				}
			}
			else
			{
				if ( \IPS\Request::i()->author === 0 ) 
				{
					$author = new \IPS\Member;
					$author->name = \IPS\Request::i()->author_name;
				}
				else 
				{
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1F295/2', 400 );
				}
			}
		}

		/* Check anonymous posting */
		if ( isset( \IPS\Request::i()->anonymous ) and $author->member_id )
		{
			if ( ! $topic->container()->canPostAnonymously( 0, $author ) )
			{
				throw new \IPS\Api\Exception( 'NO_ANON_PERMISSION', '3F295/C', 403 );
			}
		}
		
		/* Check we have a post */
		if ( !\IPS\Request::i()->post )
		{
			throw new \IPS\Api\Exception( 'NO_POST', '1F295/3', 403 );
		}
		
		/* Do it */
		return $this->_create( $topic, $author, 'post' );
	}
	
	/**
	 * POST /forums/posts/{id}
	 * Edit a post
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, hidden will only be honoured if the authenticated user has permission to hide content).
	 * @param		int			$id				ID Number
	 * @apiparam	int			author			The ID number of the member making the post (0 for guest). Ignored for requests using an OAuth Access Token for a particular member.
	 * @apiparam	string		author_name		If author is 0, the guest name that should be used
	 * @apiparam	string		post			The post content as HTML (e.g. "<p>This is a post.</p>"). Will be sanatized for requests using an OAuth Access Token for a particular member; will be saved unaltered for requests made using an API Key or the Client Credentials Grant Type. 
	 * @apiparam	int			hidden			1/0 indicating if the topic should be hidden
	 * @apiparam	bool		anonymous		If 1, the item will be posted anonymously.
	 * @throws		2F295/6		INVALID_ID					The post ID does not exist or the authorized user does not have permission to view it
	 * @throws		2F295/7		NO_AUTHOR					The author ID does not exist
	 * @throws		1F295/8		CANNOT_HIDE_FIRST_POST		You cannot hide or unhide the first post in a topic. Hide/unhide the topic itself instead.
	 * @throws		1F295/9		CANNOT_AUTHOR_FIRST_POST	You cannot change the author for the first post in a topic. Change the author on the topic itself instead.
	 * @throws		2F295/A		NO_PERMISSION				The authorized user does not have permission to edit the post
	 * @return		\IPS\forums\Topic\Post
	 */
	public function POSTitem( $id )
	{
		try
		{
			/* Load */
			$post = \IPS\forums\Topic\Post::load( $id );
			if ( $this->member and !$post->canView( $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			if ( $this->member and !$post->canEdit( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2F295/A', 403 );
			}
			
			/* Check */
			if ( $post->isFirst() )
			{
				if ( isset( \IPS\Request::i()->hidden ) )
				{
					throw new \IPS\Api\Exception( 'CANNOT_HIDE_FIRST_POST', '1F295/8', 403 );
				}
				if ( isset( \IPS\Request::i()->author ) )
				{
					throw new \IPS\Api\Exception( 'CANNOT_AUTHOR_FIRST_POST', '1F295/9', 403 );
				}
			}
			
			/* Do it */
			try
			{
				return $this->_edit( $post, 'post' );
			}
			catch ( \InvalidArgumentException $e )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '2F295/7', 400 );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2F295/6', 404 );
		}
	}
		
	/**
	 * DELETE /forums/posts/{id}
	 * Deletes a post
	 *
	 * @param		int			$id							ID Number
	 * @throws		1F295/5		INVALID_ID					The post ID does not exist
	 * @throws		1F295/B		CANNOT_DELETE_FIRST_POST	You cannot delete the first post in a topic. Delete the topic itself instead.
	 * @throws		2F295/B		NO_PERMISSION				The authorized user does not have permission to delete the post
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		try
		{
			$class = $this->class;
			$object = $class::load( $id );
			if ( $object->isFirst() )
			{
				throw new \IPS\Api\Exception( 'CANNOT_DELETE_FIRST_POST', '1F295/B', 403 );
			}
			if ( $this->member and !$object->canDelete( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2F295/B', 403 );
			}
			$object->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1F295/5', 404 );
		}
	}

	/**
	 * POST /forums/posts/{id}/report
	 * Reports a post
	 *
	 * @param       int         $id             ID Number
	 * @apiparam	int			author			ID of the member reporting
	 * @apiparam	int			report_type		Report type (0 is default and is for letting CMGR team know, more options via core_automatic_moderation_types)
	 * @apiparam	string		message			Optional message
	 * @throws		1S425/B		NO_AUTHOR			The author ID does not exist
	 * @throws		1S425/C		REPORTED_ALREADY	The member has reported this item in the past 24 hours
	 * @return		\IPS\forums\Topic\Post
	 */
	public function POSTitem_report( $id )
	{
		return $this->_report( $id );
	}
}