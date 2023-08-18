<?php
/**
 * @brief		Invoices API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		9 Dec 2015
 */

namespace IPS\nexus\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Invoices API
 */
class _invoices extends \IPS\Api\Controller
{
	/**
	 * GET /nexus/invoices
	 * Get list of invoices
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only the members own invoices will be included
	 * @apiparam	string	customers			Comma-delimited list of customer IDs - if provided, only invoices belonging to those customers are returned. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	string	statuses			Comma-delimited list of statuses - if provided, only invoices with those statuses are returned - see invoice object for status keys
	 * @apiparam	string	sortBy				What to sort by. Can be 'date', 'paid' (for paid date), 'total', 'title' or do not specify for ID
	 * @apiparam	string	sortDir				Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page				Page number
	 * @apiparam	int		perPage				Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\nexus\Invoice>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = array();
		
		/* Customers */
		if ( $this->member )
		{
			$where[] = array( 'i_member=?', $this->member->member_id );
		}
		elseif ( isset( \IPS\Request::i()->customers ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'i_member', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->customers ) ) ) ) );
		}
		
		/* Statuses */
		if ( isset( \IPS\Request::i()->statuses ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'i_status', array_filter( explode( ',', \IPS\Request::i()->statuses ) ) ) );
		}
				
		/* Sort */
		if ( isset( \IPS\Request::i()->sortBy ) and \in_array( \IPS\Request::i()->sortBy, array( 'date', 'title', 'total' ) ) )
		{
			$sortBy = 'i_' . \IPS\Request::i()->sortBy;
		}
		else
		{
			$sortBy = 'i_id';
		}
		$sortDir = ( isset( \IPS\Request::i()->sortDir ) and \in_array( mb_strtolower( \IPS\Request::i()->sortDir ), array( 'asc', 'desc' ) ) ) ? \IPS\Request::i()->sortDir : 'asc';
		
		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			\IPS\Db::i()->select( '*', 'nexus_invoices', $where, "{$sortBy} {$sortDir}" ),
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			'IPS\nexus\Invoice',
			\IPS\Db::i()->select( 'COUNT(*)', 'nexus_invoices', $where )->first(),
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}
	
	/**
	 * GET /nexus/invoices/{id}
	 * Get information about a specific invoice
	 *
	 * @param		int		$id			ID Number
	 * @throws		2X299/1	INVALID_ID	The invoice ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\nexus\Invoice
	 */
	public function GETitem( $id )
	{
		try
		{
			$object = \IPS\nexus\Invoice::load( $id );
			if ( $this->member and !$object->canView( $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			
			return new \IPS\Api\Response( 200, $object->apiOutput( $this->member ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X299/1', 404 );
		}
	}
}