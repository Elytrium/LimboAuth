<?php
/**
 * @brief		Blog Entry Category API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		4 Sep 2019
 */

namespace IPS\blog\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Blog Categories API
 */
class _entrycategories extends \IPS\Node\Api\NodeController
{

	/**
	 * Class
	 */
	protected $class = 'IPS\blog\Entry\Category';

	/**
	 * GET /blog/entrycategories
	 * Get list of entry categories
	 *
	 * @apiparam	int		blog	ID of blog. Null for all.
	 * @apiparam	string	sortBy	What to sort by. Can be 'position' for category position or do not specify for ID
	 * @apiparam	string	sortDir	Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page	Page number
	 * @apiparam	int		perPage	Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\blog\Entry\Category>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = array();

		$class = $this->class;

		if ( isset( \IPS\Request::i()->ids ) )
		{
			$idField = $class::$databaseTable . '.' . $class::$databasePrefix . '.' . $class::$databaseColumnId;
			$where[] = array( \IPS\Db::i()->in( $idField, array_map( 'intval', explode( ',', \IPS\Request::i()->ids ) ) ) );
		}

		/* Blog */
		if ( isset( \IPS\Request::i()->blog ) )
		{
			$where[] = array( 'entry_category_blog_id=?', \intval( \IPS\Request::i()->blog ) );
		}

		/* Sort */
		if ( isset( \IPS\Request::i()->sortBy ) and \in_array( \IPS\Request::i()->sortBy, array( 'position' ) ) )
		{
			$sortBy = 'entry_category_' . \IPS\Request::i()->sortBy;
		}
		else
		{
			$sortBy = 'entry_category_id';
		}
		$sortDir = ( isset( \IPS\Request::i()->sortDir ) and \in_array( mb_strtolower( \IPS\Request::i()->sortDir ), array( 'asc', 'desc' ) ) ) ? \IPS\Request::i()->sortDir : 'asc';

		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			\IPS\Db::i()->select( '*', 'blog_entry_categories', $where, "{$sortBy} {$sortDir}" ),
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			'IPS\blog\Entry\Category',
			\IPS\Db::i()->select( 'COUNT(*)', 'blog_entry_categories', $where )->first(),
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}

	/**
	 * GET /blog/entrycategories/{id}
	 * Get information about a specific entry category
	 *
	 * @param		int		$id			ID Number
	 * @return		\IPS\blog\Entry\Category
	 */
	public function GETitem( $id )
	{
		return $this->_view( $id );
	}

	/**
	 * DELETE /blog/entrycategories/{id}
	 * Delete an entry category
	 *
	 * @param		int		$id			ID Number
	 * @return		void
	 * @throws		2B408/5	INVALID_ID		The category ID does not exist or the authorized user does not have permission to delete it
	 */
	public function DELETEitem( $id )
	{
		$class = $this->class;

		try
		{
			$category = $class::load( $id );
			if ( !$category->canDelete( $this->member ) )
			{
				throw new \IPS\Api\Exception( 'INVALID_ID', '2B408/5', 404 );
			}
			$category->delete();

			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B408/6', 404 );
		}
	}

}