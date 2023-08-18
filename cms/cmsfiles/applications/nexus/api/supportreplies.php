<?php
/**
 * @brief		Support Replies API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		11 Dec 2015
 */

namespace IPS\nexus\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Support Replies API
 */
class _supportreplies extends \IPS\Content\Api\CommentController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\nexus\Support\Reply';
	
	/**
	 * GET /nexus/supportreplies
	 * Get list of support replies
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only topics the authorized user can view will be included - if the user is a staff member, will use their staff permissions
	 * @apiparam	int		staffReplies	If 1, only replies by staff will be included. If 0, only replies by non-staff.
	 * @apiparam	string	authors			Comma-delimited list of member IDs - if provided, only replies by those members are returned
	 * @apiparam	string	departments		Comma-delimited list of department IDs
	 * @apiparam	string	statuses		Comma-delimited list of status IDs
	 * @apiparam	string	severities		Comma-delimited list of severity IDs
	 * @apiparam	string	sortBy			What to sort by. Can be 'date', 'title' or leave unspecified for ID
	 * @apiparam	string	sortDir			Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page			Page number
	 * @apiparam	int		perPage			Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\nexus\Support\Reply>
	 */
	public function GETindex()
	{
		/* Init */
		$where = array();
		
		/* Type */
		if ( isset( \IPS\Request::i()->staffReplies ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'reply_type', \IPS\Request::i()->staffReplies ? array( \IPS\nexus\Support\Reply::REPLY_STAFF, \IPS\nexus\Support\Reply::REPLY_HIDDEN ) : array( \IPS\nexus\Support\Reply::REPLY_MEMBER, \IPS\nexus\Support\Reply::REPLY_ALTCONTACT, \IPS\nexus\Support\Reply::REPLY_EMAIL ) ) );
		}
		else
		{
			$where[] = array( 'reply_type<>?', \IPS\nexus\Support\Reply::REPLY_PENDING );
		}
		
		/* Departments */
		if ( isset( \IPS\Request::i()->departments ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_department', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->departments ) ) ) ) );
		}
		
		/* Statuses */
		if ( isset( \IPS\Request::i()->statuses ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_status', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->statuses ) ) ) ) );
		}

		/* Severities */
		if ( isset( \IPS\Request::i()->severities ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_severity', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->statuses ) ) ) ) );
		}
		
		/* Are we a staff member? */
		$bypassPerms = FALSE;
		if ( $this->member and $this->member->isAdmin() and \count( \IPS\nexus\Support\Department::departmentsWithPermission( $this->member ) ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_department', array_keys( iterator_to_array( \IPS\nexus\Support\Department::departmentsWithPermission( $this->member ) ) ) ) );
			$bypassPerms = TRUE;
		}

		/* Return */
		return $this->_list( $where, NULL, $bypassPerms );
	}
	
	/**
	 * GET /nexus/supportreplies/{id}
	 * View information about a specific reply
	 *
	 * @param		int		$id			ID Number
	 * @throws		2X314/1	INVALID_ID	The reply ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\nexus\Support\Reply
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
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X314/1', 404 );
		}
	}
	
	/**
	 * POST /nexus/supportreplies
	 * Create a reply
	 *
	 * @reqapiparam	int			request				The ID number of the request the reply is for
	 * @reqapiparam	int			author				The ID number of the member making the post (0 for guest).  Required for requests made using an API Key or the Client Credentials Grant Type. For requests using an OAuth Access Token for a particular member, that member will always be the author.
	 * @reqapiparam	string		message				The reply content as HTML (e.g. "<p>This is a post.</p>"). Will be sanatized for requests using an OAuth Access Token for a particular member; will be saved unaltered for requests made using an API Key or the Client Credentials Grant Type. 
	 * @apiparam	datetime	date				The date/time that should be used for the topic/post post date. If not provided, will use the current date/time. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	string		ip_address			The IP address that should be stored for the topic/post. If not provided, will use the IP address from the API request. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	bool		hidden				If true will be added as a hidden note. Ignored if authorized member is not staff
	 * @throws		2X314/2		NO_REQUEST		The support request ID does not exist or the authorized user does not have permission to access it
	 * @throws		1X314/3		NO_AUTHOR		The author ID does not exist
	 * @throws		1X314/4		NO_MESSAGE		No message was supplied
	 * @return		\IPS\nexus\Support\Reply
	 */
	public function POSTindex()
	{
		/* Get request */
		try
		{
			$request = \IPS\nexus\Support\Request::load( \IPS\Request::i()->request );
			if ( $this->member )
			{
				if ( $request->canView( $this->member ) )
				{
					// Okay
				} 
				elseif ( $this->member->isAdmin() and ( $request->department->staff === '*' or \count( array_intersect( explode( ',', $request->department->staff ), Department::staffDepartmentPerms( $this->member ) ) ) ) )
				{
					// Okay
				}
				else
				{
					throw new \OutOfRangeException;
				}
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_REQUEST', '2X314/2', 403 );
		}
		
		/* Get author */
		if ( $this->member )
		{
			$author = $this->member;
		}
		else
		{
			if ( \IPS\Request::i()->author )
			{
				$author = \IPS\Member::load( \IPS\Request::i()->author );
				if ( !$author->member_id )
				{
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1X314/3', 403 );
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
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1X314/3', 400 );
				}
			}
		}
		
		/* Check we have a post */
		if ( !\IPS\Request::i()->message )
		{
			throw new \IPS\Api\Exception( 'NO_MESSAGE', '1X314/4', 403 );
		}
		
		/* Do it */
		return $this->_create( $request, $author, 'message' );
	}
	
	/**
	 * Create
	 *
	 * @param	\IPS\Content\Item	$item			Content Item
	 * @param	\IPS\Member			$author			Author
	 * @param	string				$contentParam	The parameter that contains the content body
	 * @return	\IPS\Api\Response
	 */
	protected function _createComment( \IPS\Content\Item $item, \IPS\Member $author, $contentParam='content' )
	{
		$comment = parent::_createComment( $item, $author, $contentParam );
		if ( $this->member )
		{
			if ( !$item->canView( $this->member ) )
			{
				$comment->type = $comment::REPLY_STAFF;
				if ( \IPS\Request::i()->hidden )
				{
					$comment->type = $comment::REPLY_HIDDEN;
				}
			}
		}
		$comment->save();
		return $comment;
	}
	
	/**
	 * POST /nexus/supportreplies/{id}
	 * Edit a reply
	 *
	 * @apiclientonly
	 * @param		int			$id			ID Number
	 * @apiparam	int			author		The ID number of the member making the post (0 for guest)
	 * @apiparam	string		message		The post content as HTML (e.g. "<p>This is a post.</p>")
	 * @throws		2X314/5		INVALID_ID	The post ID does not exist
	 * @throws		1X314/6		NO_AUTHOR	The author ID does not exist
	 * @return		\IPS\forums\Topic\Post
	 */
	public function POSTitem( $id )
	{
		try
		{
			/* Load */
			$post = \IPS\nexus\Support\Reply::load( $id );
			
			/* Do it */
			try
			{
				return $this->_edit( $post, 'message' );
			}
			catch ( \InvalidArgumentException $e )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1X314/6', 400 );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X314/5', 404 );
		}
	}
		
	/**
	 * DELETE /nexus/supportreplies/{id}
	 * Deletes a reply
	 *
	 * @apiclientonly
	 * @param		int			$id							ID Number
	 * @throws		2X314/7		INVALID_ID					The post ID does not exist
	 * @throws		1X314/8		CANNOT_DELETE_FIRST_POST	You cannot delete the first reply to a request. Delete the request itself instead.
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		try
		{
			$reply = \IPS\nexus\Support\Reply::load( $id );
			
			if ( $reply->isFirst() )
			{
				throw new \IPS\Api\Exception( 'CANNOT_DELETE_FIRST_POST', '1X314/8', 403 );
			}
			
			$reply->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X314/7', 404 );
		}
	}
}