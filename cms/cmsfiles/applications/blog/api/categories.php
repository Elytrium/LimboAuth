<?php
/**
 * @brief		Blog Categories API
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
 * @brief	Blog Blogs API
 */
class _categories extends \IPS\Node\Api\NodeController
{

	/**
	 * Class
	 */
	protected $class = 'IPS\blog\Category';

	/**
	 * GET /blog/categories
	 * Get list of blog categories
	 *
     * @apiparam	string	ids 	Comma-delimited list of category ids
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

		/* Sort */
		if ( isset( \IPS\Request::i()->sortBy ) and \in_array( \IPS\Request::i()->sortBy, array( 'position' ) ) )
		{
			$sortBy = 'category_' . \IPS\Request::i()->sortBy;
		}
		else
		{
			$sortBy = 'category_id';
		}
		$sortDir = ( isset( \IPS\Request::i()->sortDir ) and \in_array( mb_strtolower( \IPS\Request::i()->sortDir ), array( 'asc', 'desc' ) ) ) ? \IPS\Request::i()->sortDir : 'asc';

		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			\IPS\Db::i()->select( '*', 'blog_categories', $where, "{$sortBy} {$sortDir}" ),
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			'IPS\blog\Category',
			\IPS\Db::i()->select( 'COUNT(*)', 'blog_categories', $where )->first(),
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}

	/**
	 * GET /blog/categories/{id}
	 * Get information about a specific blog category
	 *
	 * @param		int		$id			ID Number
	 * @return		\IPS\blog\Blog
	 */
	public function GETitem( $id )
	{
		return $this->_view( $id );
	}

	/**
	 * POST /blog/categories
	 * Create a blog category
	 *
	 * @apiclientonly
	 * @reqapiparam	string		name				The category name
	 * @apiparam	int|null	parent				The ID number of the parent the category should be created in. NULL for root.
	 * @apiparam	int			position			The category position
	 * @return		\IPS\blog\Category
	 * @throws		1B408/1		NO_TITLE			A name for the category must be supplied
	 */
	public function POSTindex()
	{
		if ( !\IPS\Request::i()->name )
		{
			throw new \IPS\Api\Exception( 'NO_TITLE', '1B408/1', 400 );
		}

		return new \IPS\Api\Response( 201, $this->_create()->apiOutput( $this->member ) );
	}

	/**
	 * POST /blog/categories/{id}
	 * Edit a blog category
	 *
	 * @apiclientonly
	 * @reqapiparam	string		name				The category name
	 * @apiparam	int|null	parent				The ID number of the parent the category should be created in. NULL for root.
	 * @apiparam	int			position			The category position
	 * @param		int		$id			ID Number
	 * @return		\IPS\blog\Category
	 */
	public function POSTitem( $id )
	{
		$class = $this->class;
		$category = $class::load( $id );

		return new \IPS\Api\Response( 200, $this->_createOrUpdate( $category )->apiOutput( $this->member ) );
	}

	/**
	 * DELETE /blog/categories/{id}
	 * Delete a blog category
	 *
	 * @apiclientonly
	 * @param		int		$id			ID Number
	 * @return		void
	 * @throws		2B408/3	INVALID_ID		The blog category ID does not exist or the authorized user does not have permission to delete it
	 */
	public function DELETEitem( $id )
	{
		$class = $this->class;

		try
		{
			$category = $class::load( $id );
			$category->delete();

			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B408/3', 404 );
		}
	}

	/**
	 * Create or update node
	 *
	 * @param	\IPS\Node\Model	$category				The node
	 * @return	\IPS\Node\Model
	 */
	protected function _createOrUpdate( \IPS\Node\Model $category )
	{
		if ( isset( \IPS\Request::i()->name ) )
		{
			\IPS\Lang::saveCustom( 'blog', "blog_category_{$category->id}", \IPS\Request::i()->name );

			$category->seo_name = \IPS\Http\Url\Friendly::seoTitle( \IPS\Request::i()->name );
		}

		$category->parent = (int) \IPS\Request::i()->parent?: \IPS\blog\Category::$databaseColumnParentRootValue;

		return parent::_createOrUpdate( $category );
	}
}