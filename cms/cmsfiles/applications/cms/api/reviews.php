<?php
/**
 * @brief		Pages Database Reviews API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		21 Feb 2020
 */

namespace IPS\cms\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Pages Database Reviews API
 */
class _reviews extends \IPS\Content\Api\CommentController
{
	/**
	 * Class
	 */
	protected $class = NULL;
	
	/**
	 * Get endpoint data
	 *
	 * @param	array	$pathBits	The parts to the path called
	 * @param	string	$method		HTTP method verb
	 * @return	array
	 * @throws	\RuntimeException
	 */
	protected function _getEndpoint( $pathBits, $method = 'GET' )
	{
		if ( !\count( $pathBits ) )
		{
			throw new \RuntimeException;
		}
		
		$database = array_shift( $pathBits );
		if ( !\count( $pathBits ) )
		{
			return array( 'endpoint' => 'index', 'params' => array( $database ) );
		}
		
		$nextBit = array_shift( $pathBits );
		if ( \intval( $nextBit ) != 0 )
		{
			if ( \count( $pathBits ) )
			{
				return array( 'endpoint' => 'item_' . array_shift( $pathBits ), 'params' => array( $database, $nextBit ) );
			}
			else
			{				
				return array( 'endpoint' => 'item', 'params' => array( $database, $nextBit ) );
			}
		}
				
		throw new \RuntimeException;
	}
	
	/**
	 * GET /cms/reviews/{database_id}
	 * Get list of review
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only reviews the authorized user can view will be included
	 * @param		int		$database			Database ID
	 * @apiparam	string	categories			Comma-delimited list of category IDs
	 * @apiparam	string	authors				Comma-delimited list of member IDs - if provided, only topics started by those members are returned
	 * @apiparam	int		locked				If 1, only comments from events which are locked are returned, if 0 only unlocked
	 * @apiparam	int		hidden				If 1, only comments which are hidden are returned, if 0 only not hidden
	 * @apiparam	int		featured			If 1, only comments from  events which are featured are returned, if 0 only not featured
	 * @apiparam	string	sortBy				What to sort by. Can be 'date', 'title' or leave unspecified for ID
	 * @apiparam	string	sortDir				Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page				Page number
	 * @apiparam	int		perPage				Number of results per page - defaults to 25
	 * @throws		2T312/1	INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\Api\PaginatedResponse<IPS\cms\Records\Review>
	 */
	public function GETindex( $database )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			$this->class = 'IPS\cms\Records\Review' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/1', 404 );
		}	
		
		/* Return */
		return $this->_list( array( array( 'review_database_id=?', $database->id ) ), 'categories' );
	}
	
	/**
	 * GET /cms/reviews/{database_id}/{id}
	 * View information about a specific review
	 *
	 * @param		int		$database			Database ID
	 * @param		int		$review			Comment ID
	 * @throws		2T312/2	INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @throws		2T311/3	INVALID_ID	The comment ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\cms\Records\Review
	 */
	public function GETitem( $database, $review )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/2', 404 );
		}	
		
		/* Return */
		try
		{
			$class = 'IPS\cms\Records\Review' . $database->id;
			if ( $this->member )
			{
				$object = $class::loadAndCheckPerms( $review, $this->member );
			}
			else
			{
				$object = $class::load( $review );
			}
			
			return new \IPS\Api\Response( 200, $object->apiOutput( $this->member ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T312/3', 404 );
		}
	}
	
	/**
	 * POST /cms/reviews/{database_id}
	 * Create a review
	 *
	 * @note	For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, hidden will only be honoured if the authenticated user has permission to hide content).
	 * @param		int			$database			Database ID
	 * @reqapiparam	int			record				The ID number of the record the comment is for
	 * @reqapiparam	int			author				The ID number of the member making the comment (0 for guest). Required for requests made using an API Key or the Client Credentials Grant Type. For requests using an OAuth Access Token for a particular member, that member will always be the author
	 * @apiparam	string		author_name			If author is 0, the guest name that should be used
	 * @reqapiparam	string		content				The comment content as HTML (e.g. "<p>This is a comment.</p>"). Will be sanatized for requests using an OAuth Access Token for a particular member; will be saved unaltered for requests made using an API Key or the Client Credentials Grant Type. 
	 * @apiparam	datetime	date				The date/time that should be used for the comment date. If not provided, will use the current date/time. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	string		ip_address			The IP address that should be stored for the comment. If not provided, will use the IP address from the API request. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	int			hidden				0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	bool		anonymous			If 1, the item will be posted anonymously.
	 * @reqapiparam	int			rating				Star rating
	 * @throws		2T312/4		INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @throws		2T312/5		INVALID_ID			The comment ID does not exist
	 * @throws		1T312/6		NO_AUTHOR			The author ID does not exist
	 * @throws		1T312/7		NO_CONTENT			No content was supplied
	 * @throws		1T312/8		INVALID_RATING		The rating is not a valid number up to the maximum rating
	 * @throws		2T312/E		NO_PERMISSION		The authorized user does not have permission to review that record
	 * @return		\IPS\cms\Records\Review
	 */
	public function POSTindex( $database )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			$this->class = 'IPS\cms\Records\Review' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/4', 404 );
		}	
		
		/* Get record */
		try
		{
			$recordClass = 'IPS\cms\Records' . $database->id;
			$record = $recordClass::load( \IPS\Request::i()->record );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T312/5', 403 );
		}
		
		/* Get author */
		if ( $this->member )
		{
			if ( !$record->canReview( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2T312/E', 403 );
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
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T312/6', 404 );
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
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T312/6', 400 );
				}
			}
		}
		
		/* Check we have a post */
		if ( !\IPS\Request::i()->content )
		{
			throw new \IPS\Api\Exception( 'NO_CONTENT', '1T311/7', 403 );
		}
		
		/* Check we have a rating */
		if ( !\IPS\Request::i()->rating or !\in_array( (int) \IPS\Request::i()->rating, range( 1, \IPS\Settings::i()->reviews_rating_out_of ) ) )
		{
			throw new \IPS\Api\Exception( 'INVALID_RATING', '1T312/8', 403 );
		}
		
		/* Do it */
		return $this->_create( $record, $author );
	}
	
	/**
	 * POST /cms/reviews/{database_id}/{review_id}
	 * Edit a review
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, hidden will only be honoured if the authenticated user has permission to hide content).
	 * @param		int			$database		Database ID
	 * @param		int			$review			Review ID
	 * @apiparam	int			author			The ID number of the member making the review (0 for guest). Ignored for requests using an OAuth Access Token for a particular member.
	 * @apiparam	string		author_name		If author is 0, the guest name that should be used
	 * @apiparam	string		content			The comment content as HTML (e.g. "<p>This is a comment.</p>"). Will be sanatized for requests using an OAuth Access Token for a particular member; will be saved unaltered for requests made using an API Key or the Client Credentials Grant Type. 
	 * @apiparam	int			hidden				1/0 indicating if the topic should be hidden
	 * @apiparam	int			rating				Star rating
	 * @apiparam	bool				anonymous		If 1, the item will be posted anonymously.
	 * @throws		2T312/9		INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @throws		2T312/A		INVALID_ID			The comment ID does not exist or the authorized user does not have permission to view it
	 * @throws		1T312/B		NO_AUTHOR			The author ID does not exist
	 * @throws		2T312/F		NO_PERMISSION		The authorized user does not have permission to edit the review
	 * @return		\IPS\cms\Records\Review
	 */
	public function POSTitem( $database, $review )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			$this->class = 'IPS\cms\Records\Review' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/9', 404 );
		}	
		
		/* Do it */
		try
		{
			/* Load */
			$className = $this->class;
			$review = $className::load( $review );
			if ( $this->member and !$review->canView( $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			if ( $this->member and !$review->canEdit( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2T312/F', 403 );
			}
						
			/* Do it */
			try
			{
				return $this->_edit( $review );
			}
			catch ( \InvalidArgumentException $e )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T312/B', 400 );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T312/A', 404 );
		}
	}
		
	/**
	 * DELETE /cms/reviews/{database_id}/{review_id}
	 * Deletes a review
	 *
	 * @param		int			$database			Database ID
	 * @param		int			$review				Comment ID
	 * @throws		2T312/C		INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @throws		2T312/D		INVALID_ID			The comment ID does not exist
	 * @throws		2T312/G		NO_PERMISSION		The authorized user does not have permission to delete the review
	 * @return		void
	 */
	public function DELETEitem( $database, $review )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			$this->class = 'IPS\cms\Records\Review' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/C', 404 );
		}	
		
		/* Do it */
		try
		{			
			$class = $this->class;
			$object = $class::load( $review );
			if ( $this->member and !$object->canDelete( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2T312/G', 403 );
			}
			$object->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T312/D', 404 );
		}
	}

	/**
	 * POST /cms/reviews/{database_id}/{comment_id}/react
	 * Add a reaction
	 *
	 * @param		int			$database			Database ID
	 * @param		int			$comment			Comment ID
	 * @apiparam	int		id			ID of the reaction to add
	 * @apiparam	int     author      ID of the member reacting
	 * @return		\IPS\cms\Records\Review
	 * @throws		1S425/2		NO_REACTION	The reaction ID does not exist
	 * @throws		1S425/3		NO_AUTHOR	The author ID does not exist
	 * @throws		1S425/4		REACT_ERROR	Error adding the reaction
	 * @throws		1S425/5		INVALID_ID	Object ID does not exist
	 * @throws		2T312/E		INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @note		If the author has already reacted to this content, any existing reaction will be removed first
	 */
	public function POSTitem_react( $database, $comment )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			$this->class = 'IPS\cms\Records\Comment' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/E', 404 );
		}

		return $this->_reactAdd( $comment );
	}

	/**
	 * DELETE /cms/reviews/{database_id}/{comment_id}/react
	 * Delete a reaction
	 *
	 * @param		int			$database			Database ID
	 * @param		int			$comment			Comment ID
	 * @apiparam	int     author      ID of the member who reacted
	 * @return		\IPS\cms\Records\Review
	 * @throws		1S425/6		NO_AUTHOR	The author ID does not exist
	 * @throws		1S425/7		REACT_ERROR	Error adding the reaction
	 * @throws		1S425/8		INVALID_ID	Object ID does not exist
	 * @throws		2T312/F		INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @note		If the author has already reacted to this content, any existing reaction will be removed first
	 */
	public function DELETEitem_react( $database, $comment )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			$this->class = 'IPS\cms\Records\Comment' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/F', 404 );
		}

		return $this->_reactRemove( $comment );
	}

	/**
	 * POST /cms/reviews/{database_id}/{comment_id}/report
	 * Reports a review
	 *
	 * @param		int			$database			Database ID
	 * @param		int			$comment			Comment ID
	 * @apiparam	int			author			    ID of the member reporting
	 * @apiparam	int			report_type		    Report type (0 is default and is for letting CMGR team know, more options via core_automatic_moderation_types)
	 * @apiparam	string		message			    Optional message
	 * @throws		1S425/B		NO_AUTHOR			The author ID does not exist
	 * @throws		1S425/C		REPORTED_ALREADY	The member has reported this item in the past 24 hours
	 * @return		\IPS\cms\Records\Review
	 */
	public function POSTitem_report( $database, $comment )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			$this->class = 'IPS\cms\Records\Comment' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T311/D', 404 );
		}

		return $this->_report( $comment );
	}
}