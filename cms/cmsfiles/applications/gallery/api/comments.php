<?php
/**
 * @brief		Gallery Comments API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		8 Dec 2015
 */

namespace IPS\gallery\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Gallery Comments API
 */
class _comments extends \IPS\Content\Api\CommentController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\gallery\Image\Comment';
	
	/**
	 * GET /gallery/comments
	 * Get list of comments
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only comments the authorized user can view will be included
	 * @apiparam	string	categories		Comma-delimited list of category IDs (will also include images in albums in those categories)
	 * @apiparam	string	albums			Comma-delimited list of album IDs
	 * @apiparam	string	authors			Comma-delimited list of member IDs - if provided, only topics started by those members are returned
	 * @apiparam	int		locked			If 1, only comments from images which are locked are returned, if 0 only unlocked
	 * @apiparam	int		hidden			If 1, only comments which are hidden are returned, if 0 only not hidden
	 * @apiparam	int		featured		If 1, only comments from  images which are featured are returned, if 0 only not featured
	 * @apiparam	string	sortBy			What to sort by. Can be 'date', 'title' or leave unspecified for ID
	 * @apiparam	string	sortDir			Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page			Page number
	 * @apiparam	int		perPage			Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\gallery\Image\Comment>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = array();
		
		/* Albums */
		if ( isset( \IPS\Request::i()->albums ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'image_album_id', array_filter( explode( ',', \IPS\Request::i()->albums ) ) ) );
		}
		
		return $this->_list( $where, 'categories' );
	}
	
	/**
	 * GET /gallery/comments/{id}
	 * View information about a specific comment
	 *
	 * @param		int		$id			ID Number
	 * @throws		2L297/1	INVALID_ID	The comment ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\gallery\Image\Comment
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
			throw new \IPS\Api\Exception( 'INVALID_ID', '2G317/1', 404 );
		}
	}
	
	/**
	 * POST /gallery/comments
	 * Create a comment
	 *
	 * @note	For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, hidden will only be honoured if the authenticated user has permission to hide content).
	 * @reqapiparam	int			image				The ID number of the image the comment is for
	 * @reqapiparam	int			author				The ID number of the member making the comment (0 for guest). Required for requests made using an API Key or the Client Credentials Grant Type. For requests using an OAuth Access Token for a particular member, that member will always be the author
	 * @apiparam	string		author_name			If author is 0, the guest name that should be used
	 * @reqapiparam	string		content				The comment content as HTML (e.g. "<p>This is a comment.</p>"). Will be sanatized for requests using an OAuth Access Token for a particular member; will be saved unaltered for requests made using an API Key or the Client Credentials Grant Type. 
	 * @apiparam	datetime	date				The date/time that should be used for the comment date. If not provided, will use the current date/time. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	string		ip_address			The IP address that should be stored for the comment. If not provided, will use the IP address from the API request. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	int			hidden				0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	bool		anonymous			If 1, the item will be posted anonymously.
	 * @throws		2G317/3		INVALID_ID	The comment ID does not exist
	 * @throws		1G317/4		NO_AUTHOR	The author ID does not exist
	 * @throws		1G317/5		NO_CONTENT	No content was supplied
	 * @throws		2G317/9		NO_PERMISSION	The authorized user does not have permission to comment on that image
	 * @return		\IPS\gallery\Image\Comment
	 */
	public function POSTindex()
	{
		/* Get image */
		try
		{
			$image = \IPS\gallery\Image::load( \IPS\Request::i()->image );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2L297/3', 403 );
		}
		
		/* Get author */
		if ( $this->member )
		{
			if ( !$image->canComment( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2G317/9', 403 );
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
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1G317/4', 404 );
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
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1G317/4', 400 );
				}
			}
		}
		
		/* Check we have a post */
		if ( !\IPS\Request::i()->content )
		{
			throw new \IPS\Api\Exception( 'NO_CONTENT', '1G317/5', 403 );
		}
		
		/* Do it */
		return $this->_create( $image, $author );
	}
	
	/**
	 * POST /gallery/comments/{id}
	 * Edit a comment
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, hidden will only be honoured if the authenticated user has permission to hide content).
	 * @param		int			$id					ID Number
	 * @apiparam	int			author				The ID number of the member making the comment (0 for guest). Ignored for requests using an OAuth Access Token for a particular member.
	 * @apiparam	string		author_name			If author is 0, the guest name that should be used
	 * @apiparam	string		content				The comment content as HTML (e.g. "<p>This is a comment.</p>"). Will be sanatized for requests using an OAuth Access Token for a particular member; will be saved unaltered for requests made using an API Key or the Client Credentials Grant Type. 
	 * @apiparam	int			hidden				1/0 indicating if the topic should be hidden
	 * @apiparam	bool		anonymous			If 1, the item will be posted anonymously.
	 * @throws		2G317/6		INVALID_ID			The comment ID does not exist or the authorized user does not have permission to view it
	 * @throws		1G317/7		NO_AUTHOR			The author ID does not exist
	 * @throws		2G317/A		NO_PERMISSION		The authorized user does not have permission to edit the comment
	 * @return		\IPS\gallery\Image\Comment
	 */
	public function POSTitem( $id )
	{
		try
		{
			/* Load */
			$comment = \IPS\gallery\Image\Comment::load( $id );
			if ( $this->member and !$comment->canView( $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			if ( $this->member and !$comment->canEdit( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2G317/A', 403 );
			}
						
			/* Do it */
			try
			{
				return $this->_edit( $comment );
			}
			catch ( \InvalidArgumentException $e )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1G317/7', 400 );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2G317/6', 404 );
		}
	}
		
	/**
	 * DELETE /gallery/comments/{id}
	 * Deletes a comment
	 *
	 * @param		int			$id			ID Number
	 * @throws		2G317/8		INVALID_ID		The comment ID does not exist
	 * @throws		2G317/B		NO_PERMISSION	The authorized user does not have permission to delete the comment
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		try
		{			
			$class = $this->class;
			$object = $class::load( $id );
			if ( $this->member and !$object->canDelete( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2G317/B', 403 );
			}
			$object->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2G317/8', 404 );
		}
	}

	/**
	 * POST /gallery/comments/{id}/react
	 * Add a reaction
	 *
	 * @param		int		$id			ID Number
	 * @apiparam	int		id			ID of the reaction to add
	 * @apiparam	int     author      ID of the member reacting
	 * @return		\IPS\gallery\Image\Comment
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
	 * DELETE /gallery/comments/{id}/react
	 * Delete a reaction
	 *
	 * @param		int		$id			ID Number
	 * @apiparam	int     author      ID of the member who reacted
	 * @return		\IPS\gallery\Image\Comment
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
	 * POST /gallery/comments/{id}/report
	 * Reports a comment
	 *
	 * @param       int         $id             ID Number
	 * @apiparam	int			author			ID of the member reporting
	 * @apiparam	int			report_type		Report type (0 is default and is for letting CMGR team know, more options via core_automatic_moderation_types)
	 * @apiparam	string		message			Optional message
	 * @throws		1S425/B		NO_AUTHOR			The author ID does not exist
	 * @throws		1S425/C		REPORTED_ALREADY	The member has reported this item in the past 24 hours
	 * @return		\IPS\gallery\Image\Comment
	 */
	public function POSTitem_report( $id )
	{
		return $this->_report( $id );
	}
}