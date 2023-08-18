<?php
/**
 * @brief		Gallery Categories API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		20 Feb 2020
 */

namespace IPS\gallery\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Gallery Categories API
 */
class _categories extends \IPS\Node\Api\NodeController
{

	/**
	 * Class
	 */
	protected $class = 'IPS\gallery\Category';

	/**
	 * GET /gallery/categories
	 * Get list of gallery categories
	 *
	 * @apiparam	string	sortBy	What to sort by. Can be 'count_imgs' for number of entries, 'last_img_date' for last image date or do not specify for ID
	 * @apiparam	string	sortDir	Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page	Page number
	 * @apiparam	int		perPage	Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\blog\Entry>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = array();

		/* Sort */
		if ( isset( \IPS\Request::i()->sortBy ) and \in_array( \IPS\Request::i()->sortBy, array( 'count_imgs', 'last_img_date' ) ) )
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
			\IPS\Db::i()->select( '*', 'gallery_categories', $where, "{$sortBy} {$sortDir}" ),
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			'IPS\gallery\Category',
			\IPS\Db::i()->select( 'COUNT(*)', 'gallery_categories', $where )->first(),
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}

	/**
	 * GET /gallery/categories/{id}
	 * Get information about a specific gallery category
	 *
	 * @param		int		$id			ID Number
	 * @return		\IPS\blog\Blog
	 */
	public function GETitem( $id )
	{
		return $this->_view( $id );
	}
}