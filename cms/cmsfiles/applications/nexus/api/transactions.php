<?php
/**
 * @brief		Transactions API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Dec 2015
 */

namespace IPS\nexus\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Transactions API
 */
class _transactions extends \IPS\Api\Controller
{
	/**
	 * GET /nexus/transactions
	 * Get list of transactions
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only the members own transactions will be included
	 * @apiparam	string	customers			Comma-delimited list of customer IDs - if provided, only transactions from those customers are returned. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	string	statuses			Comma-delimited list of statuses - if provided, only transactions with those statuses are returned - see transaction object for status keys
	 * @apiparam	string	gateways			Comma-delimited list of gateway IDs - if provided, only transactions from those gateways are returned
	 * @apiparam	string	sortBy				What to sort by. Can be 'date', 'amount' or do not specify for ID
	 * @apiparam	string	sortDir				Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page				Page number
	 * @apiparam	int		perPage				Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\nexus\Transaction>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = array();
		
		/* Customers */
		if ( $this->member )
		{
			$where[] = array( 't_member=?', $this->member->member_id );
		}
		elseif ( isset( \IPS\Request::i()->customers ) )
		{
			$where[] = array( \IPS\Db::i()->in( 't_member', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->customers ) ) ) ) );
		}
		
		/* Statuses */
		if ( isset( \IPS\Request::i()->statuses ) )
		{
			$where[] = array( \IPS\Db::i()->in( 't_status', array_filter( explode( ',', \IPS\Request::i()->statuses ) ) ) );
		}

		/* Methods */
		if ( isset( \IPS\Request::i()->gateways ) )
		{
			$where[] = array( \IPS\Db::i()->in( 't_method', array_filter( explode( ',', \IPS\Request::i()->gateways ) ) ) );
		}
				
		/* Sort */
		if ( isset( \IPS\Request::i()->sortBy ) and \in_array( \IPS\Request::i()->sortBy, array( 'date', 'amount' ) ) )
		{
			$sortBy = 't_' . \IPS\Request::i()->sortBy;
		}
		else
		{
			$sortBy = 't_id';
		}
		$sortDir = ( isset( \IPS\Request::i()->sortDir ) and \in_array( mb_strtolower( \IPS\Request::i()->sortDir ), array( 'asc', 'desc' ) ) ) ? \IPS\Request::i()->sortDir : 'asc';
		
		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			\IPS\Db::i()->select( '*', 'nexus_transactions', $where, "{$sortBy} {$sortDir}" ),
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			'IPS\nexus\Transaction',
			\IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', $where )->first(),
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}
	
	/**
	 * GET /nexus/transactions/{id}
	 * Get information about a specific transaction
	 *
	 * @param		int		$id			ID Number
	 * @throws		2X307/1	INVALID_ID	The transaction ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\nexus\Transaction
	 */
	public function GETitem( $id )
	{
		try
		{			
			$object = $this->member ? \IPS\nexus\Transaction::loadAndCheckPerms( $id ) : \IPS\nexus\Transaction::load( $id );
			return new \IPS\Api\Response( 200, $object->apiOutput( $this->member ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X307/1', 404 );
		}
	}
}