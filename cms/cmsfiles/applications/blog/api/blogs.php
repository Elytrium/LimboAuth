<?php
/**
 * @brief		Blog Blogs API
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
 * @brief	Blog Blogs API
 */
class _blogs extends \IPS\Node\Api\NodeController
{

	/**
	 * Class
	 */
	protected $class = 'IPS\blog\Blog';

	/**
	 * GET /blog/blogs
	 * Get list of blogs
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only blogs that are not disabled and not belonging to a particular club or social group will be included
	 * @apiparam	string	ids 	Comma-delimited list of blog ids
	 * @apiparam	string	owners	Comma-delimited list of member IDs - if provided, only blogs owned by those members are returned (can be used in conjection with groups or set to 0 to exclude member-created blogs)
	 * @apiparam	string	groups	Comma-delimited list of group IDs - if provided, only blogs owned by those groups are returned (can be used in conjection with members or set to 0 to exclude group blogs)
	 * @apiparam	int		pinned	If 1, only blogs which are pinned are returned, if 0 only not pinned
	 * @apiparam	string	sortBy	What to sort by. Can be 'count_entries' for number of entries, 'last_edate' for last entry date or do not specify for ID
	 * @apiparam	string	sortDir	Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page	Page number
	 * @apiparam	int		perPage	Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\blog\Entry>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = $this->_globalWhere();

		/* Owners */
		if ( isset( \IPS\Request::i()->owners ) and isset( \IPS\Request::i()->groups ) )
		{
			$where[] = array( '( ' . \IPS\Db::i()->in( 'blog_member_id', array_filter( explode( ',', \IPS\Request::i()->owners ) ) ) . ' OR ' . \IPS\Db::i()->findInSet( 'blog_groupblog_ids', array_filter( explode( ',', \IPS\Request::i()->groups ) ) ) . ' )' );
		}
		elseif ( isset( \IPS\Request::i()->owners ) )
		{
			$where[] = array( '( ' . \IPS\Db::i()->in( 'blog_member_id', array_filter( explode( ',', \IPS\Request::i()->owners ) ) ) . ' OR blog_groupblog_ids<>? )', '' );
		}
		elseif ( isset( \IPS\Request::i()->groups ) )
		{
			$where[] = array( '( blog_member_id>0 OR ' . \IPS\Db::i()->findInSet( 'blog_groupblog_ids', array_filter( explode( ',', \IPS\Request::i()->groups ) ) ) . ' )' );
		}
		
		/* Pinned */
		if ( isset( \IPS\Request::i()->pinned ) )
		{
			$where[] = array( 'blog_pinned=?', \intval( \IPS\Request::i()->pinned ) );
		}
		
		/* Permission */
		if ( $this->member )
		{
			$where[] = array( 'blog_disabled=0 AND blog_social_group IS NULL AND blog_club_id IS NULL' );
		}
	
		/* Sort */
		if ( isset( \IPS\Request::i()->sortBy ) and \in_array( \IPS\Request::i()->sortBy, array( 'count_entries', 'last_edate' ) ) )
		{
			$sortBy = 'blog_' . \IPS\Request::i()->sortBy;
		}
		else
		{
			$sortBy = 'blog_id';
		}
		$sortDir = ( isset( \IPS\Request::i()->sortDir ) and \in_array( mb_strtolower( \IPS\Request::i()->sortDir ), array( 'asc', 'desc' ) ) ) ? \IPS\Request::i()->sortDir : 'asc';
		
		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			\IPS\Db::i()->select( '*', 'blog_blogs', $where, "{$sortBy} {$sortDir}" ),
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			'IPS\blog\Blog',
			\IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs', $where )->first(),
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}
	
	/**
	 * GET /blog/blogs/{id}
	 * Get information about a specific blog
	 *
	 * @param		int		$id			ID Number
	 * @throws		2B302/1	INVALID_ID	The blog ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\blog\Blog
	 */
	public function GETitem( $id )
	{
		return $this->_view( $id );
	}

	/**
	 * DELETE /blog/blogs/{id}
	 * Delete a blog
	 *
	 * @param		int		$id			ID Number
	 * @return		void
	 * @throws		2B302/2	INVALID_ID		The blog ID does not exist or the authorized user does not have permission to view it
	 * @throws		2B302/3	NO_PERMISSION	The authorized user does not have permission to delete the blog
	 */
	public function DELETEitem( $id )
	{
		$class = $this->class;
		
		try
		{
			$blog = $this->member ? $class::loadAndCheckPerms( $id ) : $class::load( $id );
			if ( !$blog->canDelete( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'INVALID_ID', '2B302/3', 404 );
			}
			$blog->delete();

			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B302/2', 404 );
		}
	}
}