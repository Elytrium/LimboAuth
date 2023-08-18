<?php
/**
 * @brief		Blog Entries API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		9 Dec 2015
 */

namespace IPS\blog\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Blog Entries API
 */
class _entries extends \IPS\Content\Api\ItemController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\blog\Entry';
	
	/**
	 * GET /blog/entries
	 * Get list of entries
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only entries that are published and in blogs that are not disabled and not belonging to a particular club or social group will be included
	 * @apiparam	string	ids			    Comma-delimited list of entry IDs
	 * @apiparam	string	blogs			Comma-delimited list of blog IDs
	 * @apiparam	string	authors			Comma-delimited list of member IDs - if provided, only entries started by those members are returned
	 * @apiparam	int		locked			If 1, only entries which are locked are returned, if 0 only unlocked
	 * @apiparam	int		hidden			If 1, only entries which are hidden are returned, if 0 only not hidden
	 * @apiparam	int		pinned			If 1, only entries which are pinned are returned, if 0 only not pinned
	 * @apiparam	int		featured		If 1, only entries which are featured are returned, if 0 only not featured
	 * @apiparam	int		draft			If 1, only draft entries are returned, if 0 only published
	 * @apiparam	string	sortBy			What to sort by. Can be 'date' for creation date, 'title', 'updated' or leave unspecified for ID
	 * @apiparam	string	sortDir			Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page			Page number
	 * @apiparam	int		perPage			Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\blog\Entry>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = array();
		
		/* Permission */
		if ( $this->member )
		{
			$where[] = array( 'entry_status=? AND blog_disabled=0 AND blog_social_group IS NULL AND blog_club_id IS NULL', 'published' );
		}
		elseif ( isset( \IPS\Request::i()->draft ) )
		{
			$where[] = array( 'entry_status=?', \IPS\Request::i()->draft ? 'draft' : 'published' );
		}
				
		/* Return */
		return $this->_list( $where, 'blogs' );
	}
	
	/**
	 * GET /blog/entries/{id}
	 * View information about a specific blog entry
	 *
	 * @param		int		$id				ID Number
	 * @throws		2B300/A	INVALID_ID		The entry ID does not exist
	 * @return		\IPS\blog\Entry
	 */
	public function GETitem( $id )
	{
		try
		{
			return $this->_view( $id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B300/A', 404 );
		}
	}

	/**
	 * GET /blog/entries/{id}/comments
	 * View comments on an entry
	 *
	 * @param		int		$id			ID Number
	 * @apiparam	int		page		Page number
	 * @apiparam	int		perPage			Number of results per page - defaults to 25
	 * @throws		2B300/1	INVALID_ID	The entry ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\Api\PaginatedResponse<IPS\blog\Entry\Comment>
	 */
	public function GETitem_comments( $id )
	{
		try
		{
			return $this->_comments( $id, 'IPS\blog\Entry\Comment' );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B300/1', 404 );
		}
	}
	
	/**
	 * POST /blog/entries
	 * Create an entry
	 *
	 * @note	For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, locked will only be honoured if the authenticated user has permission to lock topics).
	 * @reqapiparam	int					blog			The ID number of the blog the entry should be created in
	 * @reqapiparam	int					author			The ID number of the member creating the entry (0 for guest). Required for requests made using an API Key or the Client Credentials Grant Type. For requests using an OAuth Access Token for a particular member, that member will always be the author
	 * @reqapiparam	string				title			The entry title
	 * @reqapiparam	string				entry			The entry content as HTML (e.g. "<p>This is a blog entry.</p>"). Will be sanatized for requests using an OAuth Access Token for a particular member; will be saved unaltered for requests made using an API Key or the Client Credentials Grant Type.
	 * @apiparam	bool				draft			If this is a draft
	 * @apiparam	string				prefix			Prefix tag
	 * @apiparam	string				tags			Comma-separated list of tags (do not include prefix)
	 * @apiparam	datetime			date			The date/time that should be used for the entry date. If not provided, will use the current date/time. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	string				ip_address		The IP address that should be stored for the entry/post. If not provided, will use the IP address from the API request. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	int					locked			1/0 indicating if the entry should be locked
	 * @apiparam	int					hidden			0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	int					pinned			1/0 indicating if the entry should be pinned
	 * @apiparam	int					featured		1/0 indicating if the entry should be featured
	 * @apiparam	int					category		The blog entry category
	 * @apiparam	string				poll_title		Poll title (to create a poll)
	 * @apiparam	int		            poll_public		1/0 indicating if the poll is public
	 * @apiparam	int		            poll_only		1/0 indicating if this a poll-only topic
	 * @apiparam	array		        poll_options	Array of objects with keys 'title' (string), 'answers' (array of objects with key 'value' set to the choice) and 'multichoice' (int 1/0)
	 * @apiparam	bool				anonymous		If 1, the item will be posted anonymously.
	 * @throws		1B300/2				NO_BLOG			The blog ID does not exist
	 * @throws		1B300/3				NO_AUTHOR		The author ID does not exist
	 * @throws		1B300/4				NO_TITLE		No title was supplied
	 * @throws		1B300/5				NO_CONTENT		No content was supplied
	 * @throws		1B300/A				NO_PERMISSION	The authorized user does not have permission to create an entry in that blog
	 * @return		\IPS\blog\Entry
	 */
	public function POSTindex()
	{		
		/* Get blog */
		try
		{
			$blog = \IPS\blog\Blog::load( \IPS\Request::i()->blog );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_BLOG', '1B300/2', 400 );
		}
		
		/* Get author */
		if ( $this->member )
		{
			if ( !$blog->can( 'add', $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '1B300/A', 403 );
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
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1B300/3', 400 );
				}
			}
			else
			{
				if ( \IPS\Request::i()->author === 0 ) 
				{
					$author = new \IPS\Member;
				}
				else 
				{
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1B300/3', 400 );
				}
			}
		}
		
		/* Check we have a title and a description */
		if ( !\IPS\Request::i()->title )
		{
			throw new \IPS\Api\Exception( 'NO_TITLE', '1B300/4', 400 );
		}
		if ( !\IPS\Request::i()->entry )
		{
			throw new \IPS\Api\Exception( 'NO_CONTENT', '1B300/5', 400 );
		}
		
		/* Do it */
		return new \IPS\Api\Response( 201, $this->_create( $blog, $author )->apiOutput( $this->member ) );
	}
	
	/**
	 * POST /blog/entries/{id}
	 * Edit a blog entry
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, locked will only be honoured if the authenticated user has permission to lock topics).
	 * @reqapiparam	int					blog			The ID number of the blog the entry should be created in
	 * @reqapiparam	int					author			The ID number of the member creating the entry (0 for guest). Ignored for requests using an OAuth Access Token for a particular member.
	 * @reqapiparam	string				title			The entry title
	 * @reqapiparam	string				entry			The entry content as HTML (e.g. "<p>This is a blog entry.</p>"). Will be sanatized for requests using an OAuth Access Token for a particular member; will be saved unaltered for requests made using an API Key or the Client Credentials Grant Type.
	 * @apiparam	bool				draft			If this is a draft
	 * @apiparam	string				prefix			Prefix tag
	 * @apiparam	string				tags			Comma-separated list of tags (do not include prefix)
	 * @apiparam	string				ip_address		The IP address that should be stored for the entry/post. If not provided, will use the IP address from the API request. Ignored for requests using an OAuth Access Token for a particular member.
	 * @apiparam	int					locked			1/0 indicating if the entry should be locked
	 * @apiparam	int					hidden			0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	int					pinned			1/0 indicating if the entry should be pinned
	 * @apiparam	int					featured		1/0 indicating if the entry should be featured
	 * @apiparam	int					category		The blog entry category
	 * @apiparam	string				poll_title		Poll title (to create a poll)
	 * @apiparam	int		            poll_public		1/0 indicating if the poll is public
	 * @apiparam	int		            poll_only		1/0 indicating if this a poll-only topic
	 * @apiparam	array		        poll_options	Array of objects with keys 'title' (string), 'answers' (array of objects with key 'value' set to the choice) and 'multichoice' (int 1/0)
	 * @param		int		$id			ID Number
	 * @throws		2B300/6				INVALID_ID		The entry ID is invalid or the authorized user does not have permission to view it
	 * @throws		1B300/7				NO_BLOG			The blog ID does not exist or the authorized user does not have permission to post in it
	 * @throws		1B300/8				NO_AUTHOR		The author ID does not exist
	 * @throws		1B300/B				NO_PERMISSION	The authorized user does not have permission to edit that blog entry
	 * @return		\IPS\blog\Entry
	 */
	public function POSTitem( $id )
	{
		try
		{
			$entry = \IPS\blog\Entry::load( $id );
			if ( $this->member and !$entry->can( 'read', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			if ( $this->member and !$entry->canEdit( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '1B300/B', 403 );
			}
			
			/* New blog */
			if ( isset( \IPS\Request::i()->blog ) and \IPS\Request::i()->blog != $entry->blog_id and ( !$this->member or $entry->canMove( $this->member ) ) )
			{
				try
				{
					$newBlog = \IPS\blog\Blog::load( \IPS\Request::i()->blog );
					if ( $this->member and !$newBlog->can( 'add', $this->member ) )
					{
						throw new \OutOfRangeException;
					}
					
					$entry->move( $newBlog );
				}
				catch ( \OutOfRangeException $e )
				{
					throw new \IPS\Api\Exception( 'NO_BLOG', '1B300/7', 400 );
				}
			}
			
			/* New author */
			if ( !$this->member and isset( \IPS\Request::i()->author ) )
			{				
				try
				{
					$member = \IPS\Member::load( \IPS\Request::i()->author );
					if ( !$member->member_id )
					{
						throw new \OutOfRangeException;
					}
					
					$entry->changeAuthor( $member );
				}
				catch ( \OutOfRangeException $e )
				{
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1B300/8', 400 );
				}
			}
			
			/* Everything else */
			$this->_createOrUpdate( $entry, 'edit' );
			
			/* Save and return */
			$entry->save();
			return new \IPS\Api\Response( 200, $entry->apiOutput( $this->member ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B300/6', 404 );
		}
	}

	/**
	 * Create or update entry
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @param	string				$type	add or edit
	 * @return	\IPS\Content\Item
	 */
	protected function _createOrUpdate( \IPS\Content\Item $item, $type='add' )
	{
		/* Is draft */
		if ( isset( \IPS\Request::i()->draft ) )
		{
			$item->status = \IPS\Request::i()->draft ? 'draft' : 'published';
		}
		
		/* Content */
		if ( isset( \IPS\Request::i()->entry ) )
		{
			$entryContents = \IPS\Request::i()->entry;
			if ( $this->member )
			{
				$entryContents = \IPS\Text\Parser::parseStatic( $entryContents, TRUE, NULL, $this->member, 'blog_Entries' );
			}
			$item->content = $entryContents;
		}

		/* Do we have a poll to attach? */
		$this->_createOrUpdatePoll( $item, $type );

		/* Category */
		if ( isset( \IPS\Request::i()->category ) )
		{
			$item->category_id = \IPS\Request::i()->category;
		}
		
		/* Pass up */
		return parent::_createOrUpdate( $item, $type );
	}
		
	/**
	 * DELETE /blog/entries/{id}
	 * Delete an entry
	 *
	 * @param		int		$id			ID Number
	 * @throws		2B300/9	INVALID_ID		The entry ID does not exist
	 * @throws		2B300/C	NO_PERMISSION	The authorized user does not have permission to delete the entry
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		try
		{
			$item = \IPS\blog\Entry::load( $id );
			if ( $this->member and !$item->canDelete( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2B300/C', 404 );
			}
			
			$item->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B300/9', 404 );
		}
	}
}